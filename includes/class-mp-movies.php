<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Shared family movie watchlist. A movie has a single premiere date, not a
 * recurring TV schedule. WordPress options avoid a second DB migration.
 *
 * TMDB type 3 = theatrical DK, type 4 = digital DK, type 6 = TV DK.
 * Type 4 does not establish the date on a particular subscription service.
 */
final class MP_Movies {
    private const OPTION='mp_movies_v1';
    private const LIMIT=150;

    private static function valid_date(string $date): bool {
        if ($date==='') { return false; }
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('UTC'));
        return $dt!==false && $dt->format('Y-m-d')===$date;
    }
    private static function records(): array {
        $saved=get_option(self::OPTION,[]);
        return is_array($saved)?$saved:[];
    }
    private static function save(array $records): void {
        update_option(self::OPTION,$records,false);
    }
    private static function poster(string $path): string {
        if (!preg_match('~^/[A-Za-z0-9_-]+\.(?:jpg|png|webp)$~',$path)) { return ''; }
        return 'https://image.tmdb.org/t/p/w185'.$path;
    }
    private static function tmdb(int $id,string $append=''): array|WP_Error {
        if (!$id || $id>99999999) { return new WP_Error('mp_movie_id','Ugyldigt film-ID.',['status'=>400]); }
        return MP_Streaming::tmdb_get('movie/'.$id.($append!==''?'/'.$append:''),
            $append===''?['language'=>'da-DK']:[]);
    }
    public static function search(string $q): array|WP_Error {
        $q=trim($q);
        if (mb_strlen($q)>100 || mb_strlen($q)<2) {
            return new WP_Error('mp_movie_query','Skriv mellem 2 og 100 tegn.',['status'=>400]);
        }
        if (!MP_Streaming::configured()) {
            return new WP_Error('mp_movie_api_missing',
                'Filmsøgning kræver en gratis TMDB-nøgle. Brug manuel filmoprettelse eller opsæt nøglen under Hangar18 → Oversigt.',
                ['status'=>503]);
        }
        $data=MP_Streaming::tmdb_get('search/movie',[
            'query'=>$q,'language'=>'da-DK','include_adult'=>'false','page'=>1,
        ]);
        if (is_wp_error($data)) { return $data; }
        $out=[];
        foreach (array_slice((array)($data['results']??[]),0,10) as $row) {
            if (!is_array($row) || !empty($row['adult'])) { continue; }
            $out[]=[
                'tmdb_id'=>absint($row['id']??0),
                'title'=>sanitize_text_field((string)($row['title']??'')),
                'original_title'=>sanitize_text_field((string)($row['original_title']??'')),
                'original_date'=>self::valid_date((string)($row['release_date']??''))?
                    (string)$row['release_date']:'',
                'poster'=>self::poster((string)($row['poster_path']??'')),
            ];
        }
        return array_values(array_filter($out,static fn($r)=>$r['tmdb_id']>0 && $r['title']!==''));
    }
    /** Earliest dates per category from DK territory only; never read foreign dates. */
    public static function parse_dk_releases(array $data): array {
        $out=['digital'=>'','cinema'=>'','tv'=>''];
        foreach ((array)($data['results']??[]) as $country) {
            if (!is_array($country) || ($country['iso_3166_1']??'')!=='DK') { continue; }
            foreach ((array)($country['release_dates']??[]) as $row) {
                if (!is_array($row)) { continue; }
                $type=(int)($row['type']??0);
                $key=match($type) {3=>'cinema',4=>'digital',6=>'tv',default=>''};
                if ($key==='') { continue; }
                $date=substr((string)($row['release_date']??''),0,10);
                if (!self::valid_date($date)) { continue; }
                if ($out[$key]==='' || $date<$out[$key]) { $out[$key]=$date; }
            }
        }
        return $out;
    }
    /**
     * The selected release type controls exactly one calendar entry. An
     * unknown streaming date must not fall back to a theatrical date.
     */
    public static function resolve(array $item): array {
        $mode=($item['mode']??'streaming')==='cinema'?'cinema':'streaming';
        $manual=(string)($item['manual_date']??'');
        if (self::valid_date($manual)) {
            return ['date'=>$manual,'status'=>'movie_manual','note'=>'Premiere angivet af familien – ikke kildebekræftet'];
        }
        $date=$mode==='cinema'?(string)($item['dk_cinema_date']??''):
            (string)($item['dk_digital_date']??'');
        if (self::valid_date($date)) {
            return $mode==='cinema'
                ? ['date'=>$date,'status'=>'movie_cinema','note'=>'TMDB: registreret dansk biografpremiere']
                : ['date'=>$date,'status'=>'movie_digital','note'=>'TMDB: dansk digital udgivelse; bestemt streamingtjeneste og tidspunkt er ikke bekræftet'];
        }
        return ['date'=>'','status'=>'movie_pending','note'=>$mode==='cinema'?
            'Afventer dansk biografpremiere':'Afventer dansk digital/streamingpremiere'];
    }
    private static function enrich(array $item): array {
        $resolved=self::resolve($item);
        $item['date']=$resolved['date'];
        $item['status']=$resolved['status'];
        $item['date_note']=$resolved['note'];
        $item['release_type']=$item['mode']==='cinema'?'Biograf':'Streaming/digital';
        $item['released']=$resolved['date']!=='' && $resolved['date']<current_datetime()->format('Y-m-d');
        return $item;
    }
    public static function all(): array {
        $rows=array_values(self::records());
        $rows=array_map([self::class,'enrich'],$rows);
        usort($rows,static fn($a,$b)=>[
            $a['released']?1:0,$a['date']?:'9999-12-31',$a['title']
        ]<=>[
            $b['released']?1:0,$b['date']?:'9999-12-31',$b['title']
        ]);
        return $rows;
    }
    private static function metadata(int $id): array|WP_Error {
        $info=self::tmdb($id);
        if (is_wp_error($info)) { return $info; }
        $releases=self::tmdb($id,'release_dates');
        if (is_wp_error($releases)) { return $releases; }
        $dk=self::parse_dk_releases($releases);
        return [
            'title'=>sanitize_text_field((string)($info['title']??'')),
            'poster'=>self::poster((string)($info['poster_path']??'')),
            'original_date'=>self::valid_date((string)($info['release_date']??''))?
                (string)$info['release_date']:'',
            'dk_digital_date'=>$dk['digital'],
            'dk_cinema_date'=>$dk['cinema'],
            'dk_tv_date'=>$dk['tv'],
            'tmdb_url'=>'https://www.themoviedb.org/movie/'.$id,
        ];
    }
    public static function add(array $input): array|WP_Error {
        $rows=self::records();
        if (count($rows)>=self::LIMIT) {
            return new WP_Error('mp_movies_limit','Film-watchlisten er fuld (150 film).',['status'=>400]);
        }
        $id=absint($input['tmdb_id']??0);
        $manual_title=sanitize_text_field((string)($input['title']??''));
        $mode=($input['mode']??'streaming')==='cinema'?'cinema':'streaming';
        $service=sanitize_text_field((string)($input['service']??''));
        if ($service!=='' && $service!=='Ukendt' && !in_array($service,MP_Streaming::services(),true)) {
            return new WP_Error('mp_movie_service','Ukendt streamingtjeneste.',['status'=>400]);
        }
        $service=$service!==''?$service:'Ukendt';
        $manual_date=sanitize_text_field((string)($input['manual_date']??''));
        if ($manual_date!=='' && !self::valid_date($manual_date)) {
            return new WP_Error('mp_movie_date','Ugyldig premieredato.',['status'=>400]);
        }
        if ($id===0 && ($manual_title==='' || mb_strlen($manual_title)>255)) {
            return new WP_Error('mp_movie_title','Angiv filmens titel.',['status'=>400]);
        }
        foreach ($rows as $existing) {
            if ($id>0 && (int)($existing['tmdb_id']??0)===$id) {
                return new WP_Error('mp_movie_exists','Filmen er allerede på watchlisten.',['status'=>409]);
            }
            if ($id===0 && (int)($existing['tmdb_id']??0)===0 &&
                strcasecmp((string)($existing['title']??''),$manual_title)===0) {
                return new WP_Error('mp_movie_exists','Filmen er allerede på watchlisten.',['status'=>409]);
            }
        }
        $metadata=[
            'title'=>$manual_title,'poster'=>'','original_date'=>'',
            'dk_digital_date'=>'','dk_cinema_date'=>'','dk_tv_date'=>'','tmdb_url'=>'',
        ];
        if ($id>0) {
            $loaded=self::metadata($id);
            if (is_wp_error($loaded)) { return $loaded; }
            $metadata=$loaded;
        }
        if ($metadata['title']==='') {
            return new WP_Error('mp_movie_empty','Filmen findes ikke hos TMDB.',['status'=>404]);
        }
        // Auto-select a service only if exactly one of the household services
        // is listed for DK flatrate/free/ads on TMDB. Never infer premiere date
        // from the streaming provider listing.
        if ($service==='Ukendt' && $id>0) {
            $providers=self::tmdb($id,'watch/providers');
            if (!is_wp_error($providers)) {
                $matches=MP_Streaming::selected_matches(
                    (array)($providers['results']['DK']??[]),MP_Streaming::chosen());
                if (count($matches)===1) { $service=$matches[0]; }
            }
        }
        $newId=1;
        foreach (array_keys($rows) as $key) { $newId=max($newId,absint($key)+1); }
        $item=array_merge($metadata,[
            'id'=>$newId,'tmdb_id'=>$id,'mode'=>$mode,'service'=>$service,
            'manual_date'=>$manual_date,'created'=>gmdate('c'),'last_checked'=>gmdate('c'),
            'created_by'=>get_current_user_id(),
        ]);
        $rows[(string)$newId]=$item;
        self::save($rows);
        return self::enrich($item);
    }
    public static function update(int $id,array $input): array|WP_Error {
        $rows=self::records();
        if (!isset($rows[(string)$id])) {
            return new WP_Error('mp_movie_missing','Filmen findes ikke.',['status'=>404]);
        }
        $item=$rows[(string)$id];
        if (array_key_exists('mode',$input)) {
            if (!in_array($input['mode'],['streaming','cinema'],true)) {
                return new WP_Error('mp_movie_mode','Vælg streaming eller biograf.',['status'=>400]);
            }
            $item['mode']=$input['mode'];
        }
        if (array_key_exists('service',$input)) {
            $service=sanitize_text_field((string)$input['service']);
            if ($service!=='' && $service!=='Ukendt' && !in_array($service,MP_Streaming::services(),true)) {
                return new WP_Error('mp_movie_service','Ukendt streamingtjeneste.',['status'=>400]);
            }
            $item['service']=$service?:'Ukendt';
        }
        if (array_key_exists('manual_date',$input)) {
            $date=sanitize_text_field((string)$input['manual_date']);
            if ($date!=='' && !self::valid_date($date)) {
                return new WP_Error('mp_movie_date','Ugyldig premieredato.',['status'=>400]);
            }
            $item['manual_date']=$date;
        }
        $rows[(string)$id]=$item;
        self::save($rows);
        return self::enrich($item);
    }
    public static function remove(int $id): array|WP_Error {
        $rows=self::records();
        if (!isset($rows[(string)$id])) {
            return new WP_Error('mp_movie_missing','Filmen findes ikke.',['status'=>404]);
        }
        unset($rows[(string)$id]);
        self::save($rows);
        return ['deleted'=>true,'id'=>$id];
    }
    public static function refresh(int $id): array|WP_Error {
        $rows=self::records();
        if (!isset($rows[(string)$id])) {
            return new WP_Error('mp_movie_missing','Filmen findes ikke.',['status'=>404]);
        }
        $item=$rows[(string)$id];
        if ((int)($item['tmdb_id']??0)===0) {
            return new WP_Error('mp_movie_manual','Denne film er oprettet manuelt og har ikke et TMDB-ID.',['status'=>400]);
        }
        $metadata=self::metadata((int)$item['tmdb_id']);
        if (is_wp_error($metadata)) { return $metadata; }
        // Preserve family-selected release channel/date/mode on refresh.
        $item=array_merge($item,$metadata,['last_checked'=>gmdate('c')]);
        $rows[(string)$id]=$item;
        self::save($rows);
        return self::enrich($item);
    }
    public static function refresh_pending(): void {
        // WP-Cron is opportunistic; only pending and future films need scans.
        $today=current_datetime()->format('Y-m-d');
        $pending=array_filter(self::all(),static function($movie) use($today) {
            return (int)($movie['tmdb_id']??0)>0 &&
                ($movie['date']==='' || $movie['date'] >= $today);
        });
        usort($pending,static fn($a,$b)=>strcmp((string)($a['last_checked']??''),(string)($b['last_checked']??'')));
        foreach (array_slice($pending,0,8) as $movie) {
            self::refresh((int)$movie['id']);
        }
    }
    public static function schedule(string $start,string $end): array {
        $out=[];
        foreach (self::all() as $movie) {
            $date=(string)$movie['date'];
            if ($date==='' || $date<$start || $date>$end) { continue; }
            $out[]=[
                'kind'=>'movie','date'=>$date,'movie_id'=>$movie['id'],
                'show'=>$movie['title'],'title'=>$movie['title'],
                'service'=>$movie['service'],'poster'=>$movie['poster'],
                'status'=>$movie['status'],'note'=>$movie['date_note'],
                'time_label'=>'','source_url'=>$movie['tmdb_url'],
                'release_type'=>$movie['release_type'],
            ];
        }
        return $out;
    }
}
