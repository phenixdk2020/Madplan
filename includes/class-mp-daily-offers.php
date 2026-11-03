<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * A conservative opt-in daily index of STRUCTURED Prej offers, for the
 * household's chosen stores and active shopping products.
 *
 * This deliberately does NOT download or OCR third-party flyer images:
 * third-party image rights must be cleared before automated page processing.
 * The manual single-page OCR pilot remains available separately.
 *
 * WP-Cron runs small batches, respects the same shared free-API daily quota,
 * and never republishes a bulk copy of Prej's dataset.
 */
final class MP_DailyOffers {
    private const OPTION='mp_daily_offer_settings_v1';
    private const STATE='mp_daily_offer_state_v1';
    private const MAX_DAILY_CALLS=8;
    private const MAX_PAGES_PER_STORE=2;
    private const MAX_CANDIDATES=100;
    private const MINUTE=60;

    public static function enabled(): bool {
        $s=get_option(self::OPTION,[]);
        return is_array($s) && !empty($s['enabled']);
    }
    public static function settings(): array {
        return ['enabled'=>self::enabled(),'limit'=>self::MAX_DAILY_CALLS,
            'note'=>'Automatisk daglig søgning i Prejs strukturerede tilbud for aktive indkøbsvarer. Ingen automatisk OCR af avisbilleder.'];
    }
    public static function set_enabled(bool $enabled): array {
        update_option(self::OPTION,['enabled'=>$enabled],false);
        if ($enabled) { self::ensure_schedule(); }
        else { wp_clear_scheduled_hook('mp_daily_offer_tick'); }
        return self::settings();
    }
    public static function ensure_if_enabled(): void {
        if (self::enabled()) { self::ensure_schedule(); }
    }
    public static function ensure_schedule(): void {
        if (!wp_next_scheduled('mp_daily_offer_tick')) {
            // Initial refresh after today's overnight Prej import, then
            // no more than one provider call per 15-minute WP-Cron tick.
            $tz=wp_timezone();
            $next=new DateTimeImmutable('today 05:30:00',$tz);
            if ($next->getTimestamp()<=time()) { $next=$next->modify('+1 day'); }
            wp_schedule_event($next->getTimestamp(),'mp_quarter_hour','mp_daily_offer_tick');
        }
    }
    public static function schedule_interval(array $schedules): array {
        $schedules['mp_quarter_hour']=['interval'=>15*self::MINUTE,
            'display'=>'Madplan – hvert 15. minut'];
        return $schedules;
    }
    private static function day(): string { return current_datetime()->format('Y-m-d'); }
    private static function stores(): array {
        $out=[];
        foreach (MP_ShoppingDetails::selected_stores() as $store) {
            $slug=MP_OfferFlyers::chain_slug((string)$store);
            if ($slug!=='') { $out[]=['name'=>(string)$store,'slug'=>$slug]; }
        }
        return $out;
    }
    private static function fresh(array $stores): array {
        return [
            'day'=>self::day(),'store_signature'=>md5(wp_json_encode($stores)),
            'started'=>gmdate('c'),'last_checked'=>'',
            'cursor'=>0,'page'=>1,'calls'=>0,'total_seen'=>0,
            'stores'=>array_values($stores),'checked'=>[],
            'offers'=>[],'complete'=>false,'partial'=>false,'error'=>'',
        ];
    }
    private static function state(): array {
        $s=get_option(self::STATE,[]);
        return is_array($s)?$s:[];
    }
    public static function status(): array {
        $s=self::state();
        $fresh=($s['day']??'')===self::day();
        return [
            'enabled'=>self::enabled(),
            'day'=>self::day(),
            'updated_today'=>$fresh && !empty($s['last_checked']),
            'last_checked'=>(string)($s['last_checked']??''),
            'checked_stores'=>$fresh?(array)($s['checked']??[]):[],
            'selected_stores'=>array_column(self::stores(),'name'),
            'offers'=>$fresh?count((array)($s['offers']??[])):0,
            'complete'=>$fresh && !empty($s['complete']),
            'partial'=>$fresh && !empty($s['partial']),
            'error'=>$fresh?(string)($s['error']??''):'',
            'daily_calls'=>$fresh?(int)($s['calls']??0):0,
            'daily_limit'=>self::MAX_DAILY_CALLS,
            'quota'=>MP_Offers::usage(),
            'ocr_automatic'=>false,
            'note'=>'Daglig baggrundskontrol af strukturerede tilbud (ikke billed-OCR). Begrænset af gratis API-kvote og antal resultatsider.',
        ];
    }
    private static function names(): array {
        global $wpdb;
        $table=MP_Storage::table('shopping');
        $rows=$wpdb->get_col("SELECT title FROM $table WHERE checked=0 ORDER BY id DESC LIMIT 80");
        $names=[];
        foreach (is_array($rows)?$rows:[] as $value) {
            $value=MP_Offers::search_phrase((string)$value);
            if (mb_strlen($value,'UTF-8')>=2) { $names[]=mb_strtolower($value,'UTF-8'); }
        }
        return array_values(array_unique($names));
    }
    private static function relevant(string $name,array $queries): bool {
        foreach ($queries as $q) {
            if (MP_OfferFlyers::matching_name($name,$q)) { return true; }
        }
        return false;
    }
    /**
     * Does at most ONE network request. No request at all when disabled,
     * there is no active grocery item, the daily budget is exhausted or the
     * previously cached index has already finished.
     */
    public static function tick(): array {
        if (!self::enabled()) { return self::status(); }
        // An overnight refresh should never publish data before Prej's
        // nightly ingestion (and before most families start shopping).
        if (current_datetime()->format('Hi') < '0530') { return self::status(); }
        if (!MP_Offers::configured()) { return self::error('Prej API-nøgle mangler.'); }
        if (get_transient('mp_daily_offer_tick_lock_v1')) { return self::status(); }
        set_transient('mp_daily_offer_tick_lock_v1',1,3*self::MINUTE);
        try {
            $stores=self::stores();
            $s=self::state();
            if (($s['day']??'')!==self::day() ||
                ($s['store_signature']??'')!==md5(wp_json_encode($stores))) {
                $s=self::fresh($stores);
            }
            $queries=self::names();
            if (!$queries || !$stores) {
                $s['complete']=true;
                $s['error']=!$queries?'Ingen ukøbte varer på indkøbslisten.':
                    'Ingen valgte butikker med Prej-tilbudsdata.';
                $s['last_checked']=gmdate('c');
                update_option(self::STATE,$s,false);
                return self::status();
            }
            if (!empty($s['complete'])) { return self::status(); }
            if ((int)$s['calls']>=self::MAX_DAILY_CALLS) {
                $s['complete']=true;
                $s['partial']=true;
                $s['error']='Dagens automatiske budget er brugt. Ikke alle resultatsider blev kontrolleret.';
                update_option(self::STATE,$s,false);
                return self::status();
            }
            $cursor=(int)$s['cursor'];
            if ($cursor>=count($stores)) {
                $s['complete']=true;
                update_option(self::STATE,$s,false);
                return self::status();
            }
            $store=$stores[$cursor];$page=max(1,(int)$s['page']);
            if (!MP_Offers::reserve_flyer_call()) {
                $s['error']='Den fælles Prej-kvote er opbrugt.';
                $s['partial']=true;
                $s['complete']=true;
                update_option(self::STATE,$s,false);
                return self::status();
            }
            $s['calls']++;
            // The source is explicitly structured offers, not the flyer scans.
            $url='https://api.prej.app/v1/offers?'.http_build_query([
                'chain'=>$store['slug'],'exclude_border_trade'=>1,
                'page'=>$page,'limit'=>100,
            ],'','&',PHP_QUERY_RFC3986);
            $r=wp_safe_remote_get($url,[
                'timeout'=>8,'redirection'=>0,
                'headers'=>['Authorization'=>'Bearer '.MP_Offers::api_key(),
                            'Accept'=>'application/json'],
            ]);
            if (is_wp_error($r) || (int)wp_remote_retrieve_response_code($r)!==200) {
                $s['partial']=true;
                $s['error']='Et API-opslag fejlede; det aktuelle butikskatalog er ufuldstændigt.';
                // Do not spin on a persistent failed page.
                $s['cursor']++;$s['page']=1;
            } else {
                $data=json_decode((string)wp_remote_retrieve_body($r),true);
                if (!is_array($data) || !is_array($data['offers']??null)) {
                    $s['partial']=true;
                    $s['error']='Prej returnerede et ukendt tilbudsformat.';
                    $s['cursor']++;$s['page']=1;
                } else {
                    $offers=array_slice($data['offers'],0,100);
                    $s['total_seen']+=count($offers);
                    $today=self::day();
                    foreach ($offers as $entry) {
                        if (!is_array($entry) ||
                            !self::relevant((string)($entry['name']??''),$queries)) {
                            continue;
                        }
                        $item=MP_OfferFlyers::normalize_offer($entry,$today,$store['name']);
                        if ($item===null) { continue; }
                        $key=md5($item['store'].'|'.$item['name'].'|'.$item['price_ore']);
                        $s['offers'][$key]=$item;
                        if (count($s['offers'])>self::MAX_CANDIDATES) {
                            $s['offers']=array_slice($s['offers'],0,self::MAX_CANDIDATES,true);
                            $s['partial']=true;
                        }
                    }
                    $total=max(0,(int)($data['total']??count($offers)));
                    if (count($offers)<100 || $page*100 >= $total ||
                        $page>=self::MAX_PAGES_PER_STORE) {
                        if ($page*100 < $total) { $s['partial']=true; }
                        $s['checked'][]=$store['name'];
                        $s['cursor']++;$s['page']=1;
                    } else { $s['page']=$page+1; }
                }
            }
            $s['last_checked']=gmdate('c');
            if ($s['cursor']>=count($stores)) { $s['complete']=true; }
            if ((int)$s['calls']>=self::MAX_DAILY_CALLS && empty($s['complete'])) {
                $s['complete']=true;$s['partial']=true;
                $s['error']='API-budgettet på 8 kald blev nået. Resten er ikke gennemgået.';
            }
            update_option(self::STATE,$s,false);
            return self::status();
        } finally {
            delete_transient('mp_daily_offer_tick_lock_v1');
        }
    }
    private static function error(string $message): array {
        $s=self::state();
        $s['error']=$message;
        update_option(self::STATE,$s,false);
        $result=self::status();$result['error']=$message;return $result;
    }
    public static function matches(int $id): array|WP_Error {
        global $wpdb;
        $table=MP_Storage::table('shopping');
        $row=$wpdb->get_row($wpdb->prepare(
            "SELECT id,title,checked FROM $table WHERE id=%d",$id
        ),ARRAY_A);
        if (!$row) { return new WP_Error('mp_shopping_missing','Varen findes ikke.',['status'=>404]); }
        $s=self::state();
        $phrase=MP_Offers::search_phrase((string)$row['title']);
        $hits=[];
        if (($s['day']??'')===self::day() && (int)$row['checked']===0) {
            foreach ((array)($s['offers']??[]) as $offer) {
                if (MP_OfferFlyers::matching_name((string)($offer['name']??''),$phrase)) {
                    $hits[]=$offer;
                }
            }
        }
        return ['id'=>$id,'day'=>self::day(),'offers'=>array_slice($hits,0,15),
                'partial'=>!empty($s['partial']),
                'source'=>'Prej – dagens delvist indekserede tilbud'];
    }
}
