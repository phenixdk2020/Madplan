<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Opt-in, rate-limited OCR of flyer pages ONLY with permission from the
 * relevant image rights holders. Exposes neither image bytes nor full text.
 * Pages are read to a temporary file then deleted in finally.
 */
final class MP_DailyOCR {
    private const SETTINGS='mp_daily_ocr_settings_v1';
    private const STATE='mp_daily_ocr_state_v1';
    private const MAX_PAGES_PER_DAY=48;
    private const MAX_PAGES_PER_FLYER=24;
    private const MAX_OFFERS=300;
    private const MAX_BYTES=6*1024*1024;

    public static function config(): array {
        $s=get_option(self::SETTINGS,[]);
        return is_array($s)?$s:[];
    }
    public static function enabled(): bool {
        $s=self::config();
        return !empty($s['enabled']) && !empty($s['rights_confirmed']);
    }
    public static function set_enabled(bool $enabled,bool $rights): array|WP_Error {
        if ($enabled && !$rights) {
            return new WP_Error('mp_ocr_rights',
                'Automatisk billed-OCR kræver, at du har tilladelse til at behandle kildernes avisbilleder.',
                ['status'=>400]);
        }
        if ($enabled && !MP_FlyerOCR::status()['available']) {
            return new WP_Error('mp_ocr_engine',
                'Tesseract kan ikke køres på webhotellet. Automatisk OCR kan ikke aktiveres.',
                ['status'=>503]);
        }
        update_option(self::SETTINGS,['enabled'=>$enabled,'rights_confirmed'=>$enabled && $rights],false);
        if ($enabled) { self::ensure_schedule(); }
        else { wp_clear_scheduled_hook('mp_daily_ocr_tick'); }
        return self::status();
    }
    public static function schedule_interval(array $schedules): array {
        if (!isset($schedules['mp_quarter_hour'])) {
            $schedules['mp_quarter_hour']=['interval'=>900,'display'=>'Madplan hvert kvarter'];
        }
        return $schedules;
    }
    public static function ensure_if_enabled(): void {
        if (self::enabled()) { self::ensure_schedule(); }
    }
    public static function ensure_schedule(): void {
        if (!wp_next_scheduled('mp_daily_ocr_tick')) {
            $next=new DateTimeImmutable('today 06:00:00',wp_timezone());
            if ($next->getTimestamp()<=time()) { $next=$next->modify('+1 day'); }
            wp_schedule_event($next->getTimestamp(),'mp_quarter_hour','mp_daily_ocr_tick');
        }
    }
    private static function day(): string { return current_datetime()->format('Y-m-d'); }
    private static function state(): array {
        $s=get_option(self::STATE,[]);
        return is_array($s)?$s:[];
    }
    public static function status(): array {
        $s=self::state();
        $today=self::day();
        $fresh=($s['day']??'')===$today;
        return [
            'enabled'=>self::enabled(),'day'=>$today,
            'rights_confirmed'=>!empty(self::config()['rights_confirmed']),
            'tesseract'=>MP_FlyerOCR::status()['available'],
            'processed'=>$fresh?(int)($s['processed']??0):0,
            'total'=>$fresh?(int)($s['total']??0):0,
            'offers'=>$fresh?count((array)($s['offers']??[])):0,
            'partial'=>$fresh&&!empty($s['partial']),
            'complete'=>$fresh&&!empty($s['complete']),
            'last_checked'=>$fresh?(string)($s['checked_at']??''):'',
            'error'=>$fresh?(string)($s['error']??''):'',
            'cap'=>self::MAX_PAGES_PER_DAY,
            'note'=>'Automatisk OCR er kun beregnet til avissider, du har ret til at behandle. Maks. 48 sider/døgn, én side pr. WordPress-Cron-kørsel.',
        ];
    }
    private static function prepare(): array|WP_Error {
        // Fetch currently valid source references through the official API.
        // This listing has an explicit maximum of 200 flyers and reports partial.
        delete_transient('mp_flyer_library_v1');
        $listing=MP_FlyerLibrary::listing();
        if (is_wp_error($listing)) { return $listing; }
        $queues=[];$partial=!empty($listing['partial']);
        // Round-robin by household-selected chain, so a long flyer from one
        // shop does not starve all other shops under the daily page cap.
        foreach ((array)($listing['flyers']??[]) as $flyer) {
            $chain=(string)($flyer['chain']??'');
            if ($chain==='') { continue; }
            $urls=array_slice((array)($flyer['page_urls']??[]),0,self::MAX_PAGES_PER_FLYER);
            if (count((array)($flyer['page_urls']??[]))>count($urls)) { $partial=true; }
            if (!isset($queues[$chain])) { $queues[$chain]=[]; }
            foreach ($urls as $url) {
                if (!is_string($url)) { continue; }
                $queues[$chain][]=[
                    'url'=>$url,'store'=>$chain,
                    'valid_to'=>(string)$flyer['valid_to'],
                ];
            }
        }
        $queue=[];
        do {
            $added=0;
            foreach ($queues as &$storePages) {
                if (!$storePages) { continue; }
                $queue[]=array_shift($storePages);
                $added++;
                if (count($queue)>=self::MAX_PAGES_PER_DAY) { break; }
            }
            unset($storePages);
        } while ($added && count($queue)<self::MAX_PAGES_PER_DAY);
        foreach ($queues as $remaining) {
            if ($remaining) { $partial=true;break; }
        }
        return [
            'day'=>self::day(),'pages'=>$queue,'total'=>count($queue),
            'next'=>0,'processed'=>0,'offers'=>[],
            'complete'=>!$queue,'partial'=>$partial,'error'=>'',
            'checked_at'=>gmdate('c'),'failed'=>0,
        ];
    }
    /** Grab a single allowlisted URL with no redirects; never save outside temp. */
    private static function page_candidates(array $page): array|WP_Error {
        $url=(string)($page['url']??'');
        if (!preg_match('~^https://img\.prej\.app/flyers/[a-z0-9-]+/[A-Za-z0-9_-]+/[A-Za-z0-9_-]+\.(?:jpg|png|webp|jpeg)$~D',$url)) {
            return new WP_Error('mp_ocr_image_url','Ukendt avis-billedkilde.');
        }
        $response=wp_safe_remote_get($url,[
            'timeout'=>12,'redirection'=>0,'limit_response_size'=>self::MAX_BYTES+1,
            'headers'=>['Accept'=>'image/jpeg,image/png,image/webp'],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response)!==200) {
            return new WP_Error('mp_ocr_image_network','Kunne ikke hente avissiden.');
        }
        $bytes=(string)wp_remote_retrieve_body($response);
        if (!$bytes || strlen($bytes)>self::MAX_BYTES) {
            return new WP_Error('mp_ocr_image_large','Avissiden overskrider maksimum 6 MB.');
        }
        $dim=@getimagesizefromstring($bytes);
        if (!$dim || empty($dim[0]) || empty($dim[1]) || $dim[0]*$dim[1]>36000000 ||
            !in_array($dim[2]??0,[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)) {
            return new WP_Error('mp_ocr_image_invalid','Avissiden er ikke et gyldigt billede.');
        }
        $tmp=tempnam(sys_get_temp_dir(),'mp_flyer_');
        if ($tmp===false) { return new WP_Error('mp_ocr_tmp','Kunne ikke oprette midlertidig OCR-fil.'); }
        try {
            if (file_put_contents($tmp,$bytes,LOCK_EX)!==strlen($bytes)) {
                return new WP_Error('mp_ocr_write','Kunne ikke oprette OCR-billedfil.');
            }
            $text=MP_FlyerOCR::text_from_local_image($tmp);
            if (is_wp_error($text)) { return $text; }
            return MP_FlyerOCR::parse_candidates(
                $text,(string)$page['store'],(string)$page['valid_to']);
        } finally {
            @unlink($tmp); // image and OCR source never retained
        }
    }
    public static function tick(): array {
        if (!self::enabled() || current_datetime()->format('Hi')<'0600') {
            return self::status();
        }
        if (get_transient('mp_daily_ocr_lock_v1')) { return self::status(); }
        set_transient('mp_daily_ocr_lock_v1',1,180);
        try {
            $s=self::state();
            if (($s['day']??'')!==self::day()) {
                $s=self::prepare();
                if (is_wp_error($s)) {
                    $s=[
                        'day'=>self::day(),'pages'=>[],'total'=>0,
                        'next'=>0,'processed'=>0,'offers'=>[],
                        'complete'=>true,'partial'=>true,
                        'error'=>'Kunne ikke hente avisoversigten: '.$s->get_error_message(),
                        'checked_at'=>gmdate('c'),'failed'=>0
                    ];
                    update_option(self::STATE,$s,false);
                    return self::status();
                }
            }
            if (!empty($s['complete'])) {
                update_option(self::STATE,$s,false);
                return self::status();
            }
            $n=(int)$s['next'];
            if ($n >= count((array)$s['pages'])) {
                $s['complete']=true;
                update_option(self::STATE,$s,false);
                return self::status();
            }
            $data=self::page_candidates($s['pages'][$n]);
            $s['next']=$n+1;
            $s['processed']++;
            if (is_wp_error($data)) {
                $s['failed']++;
                $s['partial']=true;
                $s['error']='En eller flere avis-sider kunne ikke OCR-behandles: '.$data->get_error_message();
            } else {
                foreach ($data as $offer) {
                    $key=md5($offer['store'].'|'.$offer['name'].'|'.$offer['price_ore']);
                    $s['offers'][$key]=$offer;
                    if (count($s['offers'])>self::MAX_OFFERS) {
                        $s['offers']=array_slice($s['offers'],0,self::MAX_OFFERS,true);
                        $s['partial']=true;
                    }
                }
            }
            $s['checked_at']=gmdate('c');
            if ($s['next']>=count($s['pages'])) {
                $s['complete']=true;
                // Preserve the selected flyer metadata only until midnight:
                // status and matches check date before exposing any data.
                $s['pages']=[];
            }
            update_option(self::STATE,$s,false);
            return self::status();
        } finally {
            delete_transient('mp_daily_ocr_lock_v1');
        }
    }
    public static function matches(int $id): array|WP_Error {
        global $wpdb;
        $table=MP_Storage::table('shopping');
        $row=$wpdb->get_row($wpdb->prepare(
            "SELECT id,title,checked FROM $table WHERE id=%d",$id),ARRAY_A);
        if (!$row) { return new WP_Error('mp_item_missing','Varen findes ikke.',['status'=>404]); }
        $s=self::state();$hits=[];
        if (($s['day']??'')===self::day() && (int)$row['checked']===0) {
            $term=MP_Offers::search_phrase((string)$row['title']);
            foreach ((array)($s['offers']??[]) as $offer) {
                if (MP_OfferFlyers::matching_name((string)($offer['name']??''),$term)) {
                    $hits[]=$offer;
                }
            }
        }
        return ['day'=>self::day(),'id'=>$id,'offers'=>array_slice($hits,0,20),
            'note'=>'OCR-læsning af kilder med dokumenteret tilladelse. Fundene er ikke verificeret.',
        ];
    }
}
