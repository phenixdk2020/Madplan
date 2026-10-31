<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
/** Optional free non-commercial TMDB/JustWatch DK streaming lookup. */
final class MP_Streaming {
    // Shared household subscriptions, unlike individual color themes.
    private const PREF='mp_household_streaming_services_v1';
    private const LEGACY_USER_PREF='mp_streaming_subscriptions_v1';
    private const TOKEN_OPTION='mp_tmdb_token_v1';
    public static function services(): array {
        return ['Netflix','HBO Max','Disney+','Prime Video','Apple TV+','SkyShowtime','Viaplay','TV 2 Play','DRTV','BritBox','Nordisk Film+','MUBI','Crunchyroll','hayu','Pluto TV','Filmstriben','Allente Stream','YouSee Play','Norlys Play'];
    }
    public static function chosen(int $user_id=0): array {
        // The optional argument is retained for compatibility with existing calls.
        // Selection never depends on which household member is logged in.
        $stored=get_option(self::PREF,null);
        if (!is_array($stored)) {
            // One-time migration: preserve pre-v0.1.12 checkboxes from the
            // first signed-in household member who still has legacy selections.
            // An explicitly saved empty global list is never re-populated.
            $uid=$user_id ?: get_current_user_id();
            $legacy=$uid>0 ? get_user_meta($uid,self::LEGACY_USER_PREF,true) : [];
            $stored=is_array($legacy) ? array_values(array_intersect(self::services(),$legacy)) : [];
            if ($uid>0 && $stored) {
                update_option(self::PREF,$stored,false);
            }
        }
        return array_values(array_intersect(self::services(),$stored));
    }
    public static function save_shared(array $services): array|WP_Error {
        if (count($services)>40) {
            return new WP_Error('mp_services_too_many','For mange tjenester.',['status'=>400]);
        }
        foreach ($services as $service) {
            if (!is_string($service) || !in_array($service,self::services(),true)) {
                return new WP_Error('mp_service_invalid','Ukendt streamingtjeneste.',['status'=>400]);
            }
        }
        update_option(self::PREF,array_values(array_unique($services)),false);
        return ['saved'=>true,'shared'=>true,'selected'=>self::chosen()];
    }
    public static function api_token(): string {
        if (defined('MP_TMDB_API_TOKEN') && is_string(MP_TMDB_API_TOKEN) && MP_TMDB_API_TOKEN !== '') { return MP_TMDB_API_TOKEN; }
        return (string)get_option(self::TOKEN_OPTION,'');
    }
    public static function configured(): bool { return self::api_token() !== ''; }
    public static function save_token(string $value): bool {
        if (!preg_match('/^[A-Za-z0-9_.\-]{20,600}$/',$value)) { return false; }
        update_option(self::TOKEN_OPTION,$value,false); return true;
    }
    public static function clear_token(): void { delete_option(self::TOKEN_OPTION); }
    public static function normalize(string $name): string {
        $name=strtolower(trim($name));
        $name=str_replace(['+','&'],[' plus ',' and '],$name);
        return (string)preg_replace('/[^a-z0-9]+/','',$name);
    }
    public static function canonical_provider(string $name): string {
        $aliases=[
          'netflix'=>'Netflix','netflixstandardwithads'=>'Netflix','netflixbasicwithads'=>'Netflix',
          'hbomax'=>'HBO Max','max'=>'HBO Max','disneyplus'=>'Disney+',
          'amazonprimevideo'=>'Prime Video','primevideo'=>'Prime Video',
          'appletvplus'=>'Apple TV+','skyshowtime'=>'SkyShowtime',
          'viaplay'=>'Viaplay','tv2play'=>'TV 2 Play','drtv'=>'DRTV',
          'britbox'=>'BritBox','nordiskfilmplus'=>'Nordisk Film+','mubi'=>'MUBI',
          'crunchyroll'=>'Crunchyroll','hayu'=>'hayu','plutotv'=>'Pluto TV',
          'filmstriben'=>'Filmstriben','allentestream'=>'Allente Stream',
          'youseeplay'=>'YouSee Play','norlysplay'=>'Norlys Play'
        ];
        return $aliases[self::normalize($name)]??'';
    }
    public static function identify_show(array $results,string $name,string $premiered): int {
        $target=self::normalize($name);
        if ($target==='') { return 0; }
        $same=[];
        foreach ($results as $item) {
            if (!is_array($item) || absint($item['id']??0)<1) { continue; }
            $names=[(string)($item['name']??''),(string)($item['original_name']??'')];
            if (in_array($target,array_map([self::class,'normalize'],$names),true)) { $same[]=$item; }
        }
        $year=substr($premiered,0,4);
        if (count($same)===1) {
            $match_year=substr((string)($same[0]['first_air_date']??''),0,4);
            if (preg_match('/^\d{4}$/',$year) && preg_match('/^\d{4}$/',$match_year) && $year!==$match_year) { return 0; }
            return absint($same[0]['id']);
        }
        if (count($same)>1 && preg_match('/^\d{4}$/',$year)) {
            $sameYear=array_values(array_filter($same,static function($item) use($year) {
                return substr((string)($item['first_air_date']??''),0,4)===$year;
            }));
            if (count($sameYear)===1) { return absint($sameYear[0]['id']); }
        }
        return 0;
    }
    public static function selected_matches(array $dk_region,array $chosen): array {
        $found=[];
        foreach (['flatrate','free','ads'] as $kind) {
            foreach (($dk_region[$kind]??[]) as $entry) {
                if (!is_array($entry)) { continue; }
                $provider=self::canonical_provider((string)($entry['provider_name']??''));
                if ($provider!=='' && in_array($provider,$chosen,true)) { $found[$provider]=$provider; }
            }
        }
        return array_values($found);
    }
    /** Reuse the same server-only TMDB credentials/cache for movies. */
    public static function tmdb_get(string $path,array $query=[]): array|WP_Error {
        if (!preg_match('~^(?:search/movie|movie/[1-9][0-9]{0,7}(?:/(?:release_dates|watch/providers))?)$~',$path)) {
            return new WP_Error('mp_tmdb_path','Ikke-tilladt TMDB-ressource.',['status'=>400]);
        }
        return self::get_tmdb($path,$query);
    }
    private static function get_tmdb(string $path,array $query=[]): array|WP_Error {
        $token=self::api_token();
        if ($token==='') { return new WP_Error('mp_tmdb_no_key','TMDB er ikke konfigureret.'); }
        $url='https://api.themoviedb.org/3/'.$path;
        if ($query) { $url.='?'.http_build_query($query,'','&',PHP_QUERY_RFC3986); }
        $cache='mp_tmdb_'.md5($url);
        $cached=get_transient($cache);
        if (is_array($cached)) { return $cached; }
        $headers=['Accept'=>'application/json'];
        if (str_contains($token,'.')) { $headers['Authorization']='Bearer '.$token; }
        else { $url.=(str_contains($url,'?')?'&':'?').'api_key='.rawurlencode($token); }
        $response=wp_safe_remote_get($url,['timeout'=>12,'redirection'=>0,'headers'=>$headers]);
        if (is_wp_error($response)) { return $response; }
        if ((int)wp_remote_retrieve_response_code($response)!==200) {
            return new WP_Error('mp_tmdb_http','TMDB kunne ikke hente aktuelle danske streamingdata.');
        }
        $data=json_decode((string)wp_remote_retrieve_body($response),true);
        if (!is_array($data)) { return new WP_Error('mp_tmdb_json','TMDB returnerede ugyldige data.'); }
        set_transient($cache,$data,6*HOUR_IN_SECONDS);
        return $data;
    }
    public static function lookup(int $tvmaze_id,int $user_id=0): array {
        $selected=self::chosen($user_id);
        $base=['matches'=>[],'selected'=>$selected,'status'=>'unknown',
          'source'=>'JustWatch via TMDB (Danmark)','url'=>'','tmdb_id'=>0,
          'notice'=>'Kataloger, abonnementer og sæsoner kan ændre sig. Kontroller altid selve streamingtjenesten.'];
        if (!$selected) { $base['status']='no_services'; return $base; }
        if (!self::configured()) { $base['status']='not_configured'; return $base; }
        $show=MP_TVmaze::details($tvmaze_id);
        if (is_wp_error($show)) { $base['status']='lookup_error'; return $base; }
        $name=(string)($show['title']??'');
        if ($name==='') { $base['status']='unmatched'; return $base; }
        $results=self::get_tmdb('search/tv',['query'=>$name,'language'=>'en-US','page'=>1]);
        if (is_wp_error($results)) { $base['status']='lookup_error'; return $base; }
        $tmdb_id=self::identify_show((array)($results['results']??[]),$name,(string)($show['premiered']??''));
        if (!$tmdb_id) { $base['status']='unmatched'; return $base; }
        $base['tmdb_id']=$tmdb_id;
        $providers=self::get_tmdb('tv/'.$tmdb_id.'/watch/providers');
        if (is_wp_error($providers)) { $base['status']='lookup_error'; return $base; }
        $dk=(array)($providers['results']['DK']??[]);
        $base['url']=esc_url_raw((string)($dk['link']??''));
        if (!$dk) { $base['status']='no_dk_data'; return $base; }
        $base['matches']=self::selected_matches($dk,$selected);
        $base['status']=$base['matches']?'found':'not_listed';
        return $base;
    }
}
