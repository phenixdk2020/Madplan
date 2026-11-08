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
    private const ARCHIVE='mp_daily_ocr_archive_v1';
    private const POSITIONS='mp_daily_ocr_positions_v1';
    private const MAX_PAGES_PER_DAY=192;
    private const MAX_PAGES_PER_FLYER=80;
    private const MAX_OFFERS=1200;
    private const MAX_BYTES=6*1024*1024;

    public static function config(): array {
        $s=get_option(self::SETTINGS,[]);
        return is_array($s)?$s:[];
    }
    public static function enabled(): bool {
        $s=self::config();
        return !empty($s['enabled']) && !empty($s['rights_confirmed']);
    }
    public static function mode(): string {
        return (string)(self::config()['mode']??'server')==='github'?'github':'server';
    }
    public static function set_enabled(bool $enabled,bool $rights,string $mode='server'): array|WP_Error {
        if (!in_array($mode,['server','github'],true)) {
            return new WP_Error('mp_ocr_mode','Ukendt OCR-tilstand.',['status'=>400]);
        }
        if ($enabled && !$rights) {
            return new WP_Error('mp_ocr_rights',
                'Automatisk billed-OCR kræver, at du har tilladelse til at behandle kildernes avisbilleder.',
                ['status'=>400]);
        }
        if ($enabled && $mode==='server' && !MP_FlyerOCR::status()['available']) {
            return new WP_Error('mp_ocr_engine',
                'Tesseract kan ikke køres på webhotellet. Automatisk OCR kan ikke aktiveres.',
                ['status'=>503]);
        }
        update_option(self::SETTINGS,[
            'enabled'=>$enabled,'rights_confirmed'=>$enabled && $rights,'mode'=>$mode,
        ],false);
        if ($enabled && $mode==='server') { self::ensure_schedule(); }
        else { wp_clear_scheduled_hook('mp_daily_ocr_tick'); }
        return self::status();
    }
    public static function schedule_interval(array $schedules): array {
        $schedules['mp_five_minutes']=['interval'=>300,'display'=>'Madplan OCR hvert femte minut'];
        return $schedules;
    }
    public static function ensure_if_enabled(): void {
        if (self::enabled() && self::mode()==='server') { self::ensure_schedule(); }
    }
    public static function ensure_schedule(): void {
        // Migrate old 15-minute schedule on upgrades, retaining the opt-in.
        if (function_exists('wp_get_schedule') && wp_next_scheduled('mp_daily_ocr_tick') &&
            wp_get_schedule('mp_daily_ocr_tick')!=='mp_five_minutes') {
            wp_clear_scheduled_hook('mp_daily_ocr_tick');
        }
        if (!wp_next_scheduled('mp_daily_ocr_tick')) {
            $next=new DateTimeImmutable('today 06:00:00',wp_timezone());
            $at=$next->getTimestamp();
            if ($at<=time()) { $at=time()+60; } // Start today after activation.
            wp_schedule_event($at,'mp_five_minutes','mp_daily_ocr_tick');
        }
    }
    private static function day(): string { return current_datetime()->format('Y-m-d'); }
    private static function state(): array {
        $s=get_option(self::STATE,[]);
        return is_array($s)?$s:[];
    }
    /** Retain only short product/price candidates for as long as the flyer is valid. */
    private static function retained(): array {
        $raw=get_option(self::ARCHIVE,[]);
        if (!is_array($raw)) { return []; }
        $today=self::day();$result=[];
        foreach ($raw as $key=>$entry) {
            if (!is_array($entry) || (string)($entry['valid_to']??'')<$today ||
                (string)($entry['name']??'')==='') { continue; }
            $result[$key]=$entry;
        }
        return array_slice($result,0,self::MAX_OFFERS,true);
    }
    private static function retain_new(array $offers): void {
        $archive=self::retained();
        foreach ($offers as $entry) {
            if (!is_array($entry) || (string)($entry['valid_to']??'')<self::day()) { continue; }
            $key=md5(mb_strtolower((string)$entry['store'].'|'.$entry['name'],'UTF-8').'|'.
                (string)$entry['price_ore'].'|'.(string)$entry['valid_to']);
            $archive[$key]=$entry;
        }
        // Most recent findings first; no images or full OCR text retained.
        $archive=array_slice(array_reverse($archive,true),0,self::MAX_OFFERS,true);
        update_option(self::ARCHIVE,$archive,false);
    }
    public static function status(): array {
        $s=self::state();
        $today=self::day();
        $fresh=($s['day']??'')===$today;
        return [
            'enabled'=>self::enabled(),'mode'=>self::mode(),'day'=>$today,
            'rights_confirmed'=>!empty(self::config()['rights_confirmed']),
            'tesseract'=>MP_FlyerOCR::status()['available'],
            'processed'=>$fresh?(int)($s['processed']??0):0,
            'total'=>$fresh?(int)($s['total']??0):0,
            'offers'=>$fresh?count((array)($s['offers']??[])):0,
            'saved_offers'=>count(self::retained()),
            'cron_scheduled'=>(bool)wp_next_scheduled('mp_daily_ocr_tick'),
            'external_worker_required'=>self::enabled() && self::mode()==='github',
            'partial'=>$fresh&&!empty($s['partial']),
            'complete'=>$fresh&&!empty($s['complete']),
            'last_checked'=>$fresh?(string)($s['checked_at']??''):'',
            'error'=>$fresh?(string)($s['error']??''):'',
            'cap'=>self::MAX_PAGES_PER_DAY,
            'note'=>'OCR behandler kun avissider, du har tilladelse til at behandle. Maks. 192 sider/døgn, én side pr. femminutters WP-Cron-kørsel. Gemte tilbud bevares til avisens udløb.',
        ];
    }
    private static function prepare(): array|WP_Error {
        // Fetch currently valid source references through the official API.
        // This listing has an explicit maximum of 200 flyers and reports partial.
        $listing=MP_FlyerLibrary::listing();
        if (is_wp_error($listing)) { return $listing; }
        $queues=[];$partial=!empty($listing['partial']);
        $positions=get_option(self::POSITIONS,[]);
        if (!is_array($positions)) { $positions=[]; }
        // Round-robin by selected chain; resume large flyers after yesterday's
        // last processed page rather than repeatedly scanning only page one.
        foreach ((array)($listing['flyers']??[]) as $flyer) {
            $chain=(string)($flyer['chain']??'');
            if ($chain==='') { continue; }
            $allUrls=(array)($flyer['page_urls']??[]);
            $urls=array_values(array_filter(array_slice($allUrls,0,self::MAX_PAGES_PER_FLYER),'is_string'));
            $count=count($urls);
            if (count($allUrls)>$count || (int)($flyer['source_page_count']??$count)>$count) {
                $partial=true;
            }
            if ($count===0) { continue; }
            $valid=(string)($flyer['valid_to']??'');
            $key=md5($chain.'|'.(string)($flyer['flyer_id']??'').'|'.$valid.'|'.$urls[0]);
            $start=((int)($positions[$key]['next']??0))%$count;
            if (!isset($queues[$chain])) { $queues[$chain]=[]; }
            for ($i=0;$i<$count;$i++) {
                $n=($start+$i)%$count;
                $queues[$chain][]=[
                    'url'=>$urls[$n],'store'=>$chain,
                    'valid_to'=>$valid,
                    'cursor_key'=>$key,'page_count'=>$count,'position'=>$n,
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
    /** Remember page progress so a large flyer continues at another page tomorrow. */
    private static function advance_page(array $page): void {
        $key=(string)($page['cursor_key']??'');
        $count=(int)($page['page_count']??0);
        if ($key==='' || $count<1) { return; }
        $positions=get_option(self::POSITIONS,[]);
        if (!is_array($positions)) { $positions=[]; }
        $today=self::day();
        foreach ($positions as $k=>$value) {
            if (!is_array($value) || (string)($value['valid_to']??'')<$today) {
                unset($positions[$k]);
            }
        }
        $positions[$key]=[
            'next'=>((int)($page['position']??0)+1)%$count,
            'valid_to'=>(string)($page['valid_to']??$today),
        ];
        // Keep a bounded progress record, never image data.
        $positions=array_slice($positions,-200,null,true);
        update_option(self::POSITIONS,$positions,false);
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
        if (!self::enabled() || self::mode()!=='server' || current_datetime()->format('Hi')<'0600') {
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
                        'complete'=>false,'partial'=>true,
                        'retry_after'=>time()+3600,
                        'error'=>'Kunne ikke hente avisoversigten: '.$s->get_error_message(),
                        'checked_at'=>gmdate('c'),'failed'=>0
                    ];
                    update_option(self::STATE,$s,false);
                    return self::status();
                }
            }
            if (!empty($s['retry_after'])) {
                if (time()<(int)$s['retry_after']) { return self::status(); }
                $s=self::prepare();
                if (is_wp_error($s)) {
                    $error=$s->get_error_message();
                    $s=self::state();
                    $s['retry_after']=time()+3600;
                    $s['error']='Nyt OCR-forsøg mislykkedes: '.$error;
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
            self::advance_page($s['pages'][$n]);
            $s['next']=$n+1;
            $s['processed']++;
            if (is_wp_error($data)) {
                $s['failed']++;
                $s['partial']=true;
                $s['error']='En eller flere avis-sider kunne ikke OCR-behandles: '.$data->get_error_message();
            } else {
                self::retain_new($data);
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
    /** API for the external runner. Requires an authenticated WP administrator. */
    public static function external_queue(): array|WP_Error {
        if (!self::enabled() || self::mode()!=='github') {
            return new WP_Error('mp_ocr_disabled',
                'Aktivér daglig OCR og vælg GitHub Actions som OCR-motor.',['status'=>409]);
        }
        if (get_transient('mp_daily_ocr_external_lock')) {
            return new WP_Error('mp_ocr_busy','OCR-køen er låst kortvarigt.',['status'=>503]);
        }
        $s=self::state();
        if (($s['day']??'')!==self::day()) {
            $s=self::prepare();
            if (is_wp_error($s)) { return $s; }
            update_option(self::STATE,$s,false);
        }
        return [
            'day'=>self::day(),'next'=>(int)($s['next']??0),
            'total'=>(int)($s['total']??0),
            'pages'=>array_slice((array)($s['pages']??[]),(int)($s['next']??0)),
            'complete'=>!empty($s['complete']),
            'partial'=>!empty($s['partial']),
        ];
    }
    /** Accept only the next page in the *server prepared* queue, never arbitrary stores or URLs. */
    public static function external_result(int $index,string $day,string $text,string $error=''): array|WP_Error {
        if (!self::enabled() || self::mode()!=='github') {
            return new WP_Error('mp_ocr_disabled','GitHub OCR er ikke aktiveret.',['status'=>409]);
        }
        if ($day!==self::day()) {
            return new WP_Error('mp_ocr_day','Kørslen hører til en anden dag.',['status'=>409]);
        }
        if (strlen($text)>200000 || strlen($error)>200 || $index<0) {
            return new WP_Error('mp_ocr_limit','OCR-svaret er for stort eller ugyldigt.',['status'=>400]);
        }
        if (get_transient('mp_daily_ocr_external_lock')) {
            return new WP_Error('mp_ocr_busy','OCR-køen opdateres.',['status'=>503]);
        }
        set_transient('mp_daily_ocr_external_lock',1,90);
        try {
            $s=self::state();
            if (($s['day']??'')!==$day || !isset($s['pages'][$index])) {
                return new WP_Error('mp_ocr_page','Avissiden findes ikke i dagens godkendte kø.',['status'=>409]);
            }
            if ($index<(int)$s['next']) { // Safe retry after a lost HTTP response.
                return ['accepted'=>true,'already_processed'=>true,'next'=>(int)$s['next']];
            }
            if ($index!==(int)$s['next']) {
                return new WP_Error('mp_ocr_order','Avissider skal indlæses i rækkefølge.',['status'=>409]);
            }
            $page=$s['pages'][$index];
            $candidates=[];
            if ($error==='' && trim($text)!=='') {
                $candidates=MP_FlyerOCR::parse_candidates(
                    $text,(string)$page['store'],(string)$page['valid_to']);
                self::retain_new($candidates);
                foreach ($candidates as $offer) {
                    $key=md5($offer['store'].'|'.$offer['name'].'|'.$offer['price_ore']);
                    $s['offers'][$key]=$offer;
                    if (count($s['offers'])>self::MAX_OFFERS) {
                        $s['offers']=array_slice($s['offers'],0,self::MAX_OFFERS,true);
                        $s['partial']=true;
                    }
                }
            } elseif ($error!=='') {
                $s['failed']=(int)($s['failed']??0)+1;
                $s['partial']=true;
                $s['error']='En avisside fejlede i ekstern OCR: '.sanitize_text_field($error);
            }
            self::advance_page($page);
            $s['next']=$index+1;
            $s['processed']=(int)($s['processed']??0)+1;
            $s['checked_at']=gmdate('c');
            if ($s['next']>=count($s['pages'])) {
                $s['complete']=true;
                $s['pages']=[];
            }
            update_option(self::STATE,$s,false);
            return ['accepted'=>true,'imported'=>count($candidates),
                'next'=>$s['next'],'complete'=>!empty($s['complete']),
                'saved_offers'=>count(self::retained())];
        } finally {
            delete_transient('mp_daily_ocr_external_lock');
        }
    }

    public static function matches(int $id): array|WP_Error {
        global $wpdb;
        $table=MP_Storage::table('shopping');
        $row=$wpdb->get_row($wpdb->prepare(
            "SELECT id,title,checked FROM $table WHERE id=%d",$id),ARRAY_A);
        if (!$row) { return new WP_Error('mp_item_missing','Varen findes ikke.',['status'=>404]); }
        $hits=[];
        if ((int)$row['checked']===0) {
            $term=MP_Offers::search_phrase((string)$row['title']);
            $allowed=class_exists('MP_ShoppingDetails')?MP_ShoppingDetails::selected_stores():null;
            foreach (self::retained() as $offer) {
                if ($allowed!==null && !in_array((string)($offer['store']??''),$allowed,true)) {
                    continue;
                }
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
