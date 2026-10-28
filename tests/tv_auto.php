<?php
declare(strict_types=1);

/** Test TVmaze parsing without internet, WordPress or paid APIs. */
define('ABSPATH', '/fake-wordpress/');
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
class WP_Error {
    public function __construct(public string $code, public string $message, public array $data = []) {}
}
$GLOBALS['mock_cache']=[];
$GLOBALS['fixtures']=[
 '/shows/99'=>['id'=>99,'name'=>'Demo Streaming','webChannel'=>['name'=>'Netflix'],'network'=>null],
 '/shows/99/seasons'=>[
    ['number'=>1,'episodeOrder'=>8,'premiereDate'=>'2026-11-05','webChannel'=>['name'=>'Netflix']],
 ],
 '/shows/99/episodes'=>[
    ['season'=>1,'number'=>1,'airdate'=>'2026-11-05'],
    ['season'=>1,'number'=>2,'airdate'=>'2026-11-12'],
 ],
 '/shows/101'=>['id'=>101,'name'=>'Unknown Episodes','webChannel'=>null,'network'=>['name'=>'BBC']],
 '/shows/101/seasons'=>[['number'=>2,'episodeOrder'=>null,'premiereDate'=>null]],
 '/shows/101/episodes'=>[['season'=>2,'number'=>1,'airdate'=>null]],
];
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function wp_safe_remote_get(string $url, array $args = []): array {
    $path=parse_url($url,PHP_URL_PATH);
    if (!array_key_exists($path,$GLOBALS['fixtures'])) {
        throw new RuntimeException('Unexpected TVmaze path: ' . $path);
    }
    return ['response'=>['code'=>200],'body'=>json_encode($GLOBALS['fixtures'][$path])];
}
function wp_remote_retrieve_response_code(array $res): int { return $res['response']['code']; }
function wp_remote_retrieve_body(array $res): string { return $res['body']; }
function get_transient(string $key) { return $GLOBALS['mock_cache'][$key] ?? false; }
function set_transient(string $key,$value,int $timeout): bool { $GLOBALS['mock_cache'][$key]=$value; return true; }
function absint($value): int { return abs((int)$value); }
function sanitize_text_field(string $value): string { return strip_tags($value); }
function check(bool $condition,string $label): void {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
}
require_once dirname(__DIR__) . '/includes/class-mp-tvmaze.php';

$a=MP_TVmaze::details(99);
check(!is_wp_error($a),'TVmaze result valid');
check($a['suggested_season']===1,'selected season');
check($a['seasons'][0]['total']===8,'season full count from episodeOrder');
check($a['seasons'][0]['known']===2,'known episodes count');
check($a['seasons'][0]['first_date']==='2026-11-05','first release date');
check($a['seasons'][0]['weekly']===true,'known 7-day pattern');
check($a['original_provider']==='Netflix','original web channel');
check($a['provider_kind']==='web','provider classified as web, not Denmark');

$b=MP_TVmaze::details(101);
check($b['seasons'][0]['total']===0,'unknown season count must stay unknown');
check($b['seasons'][0]['weekly']===false,'no unsupported guessed weekly dates');
check($b['provider_kind']==='broadcast','network is not treated as streaming service');
check($b['seasons'][0]['first_date']==='','no invented premiere date');

$again=MP_TVmaze::details(99);
check($again===$a,'transient cache reuse');

/* Regression: watchlist persists after last episode and automatically detects S3. */
$completed=[];
$first=new DateTimeImmutable('2026-09-03',new DateTimeZone('UTC'));
for($i=1;$i<=8;$i++){
    $completed[]=['season'=>2,'number'=>$i,
                  'airdate'=>$first->modify('+'.(($i-1)*7).' days')->format('Y-m-d')];
}
$two=[['number'=>1,'episodeOrder'=>8,'premiereDate'=>'2025-09-04'],
      ['number'=>2,'episodeOrder'=>8,'premiereDate'=>'2026-09-03',
       'endDate'=>'2026-10-22','webChannel'=>['name'=>'Prime Video']]];
$waiting=MP_TVmaze::watch_state($two,$completed,2,'2026-10-28');
check($waiting['state']==='awaiting_next_season','Completed last season remains watched');
check($waiting['season']===2 && !$waiting['new_season'],'No phantom new season');
check($waiting['original_provider']==='Prime Video','Original provider retained');
$three=array_merge($two,[
 ['number'=>3,'episodeOrder'=>8,'premiereDate'=>'2027-02-04',
  'webChannel'=>['name'=>'Prime Video']],
]);
$new=MP_TVmaze::watch_state($three,$completed,2,'2026-10-28');
check($new['state']==='new_season' && $new['new_season'],'Detects next season announcement');
check($new['season']===3 && $new['season_total']===8,'Tracks new season and planned episode count');
check($new['first_air_date']==='2027-02-04','Tracks original upcoming premiere');
check($new['original_provider']==='Prime Video' && $new['provider_kind']==='web',
      'Identifies original Prime Video premiere; no Denmark claim');
$upcoming=MP_TVmaze::watch_state($three,$completed,3,'2026-10-28');
check($upcoming['state']==='upcoming' && !$upcoming['new_season'],
      'Repeated daily checks keep upcoming premiere visible');
$unknown=MP_TVmaze::watch_state(
 [['number'=>1,'episodeOrder'=>null,'premiereDate'=>null]],
 [['season'=>1,'number'=>1,'airdate'=>'2026-01-01']],
 1,'2026-10-28'
);
check($unknown['state']==='monitoring','Unknown season total must not be marked completed');
$summary=MP_TVmaze::season_summary(99);
check(!is_wp_error($summary) && $summary['season_count']===1,'Search displays season count');
$summaryCached=MP_TVmaze::season_summary(99);
check($summaryCached===$summary,'Season count cached independently');
echo "Watchlist season monitoring tests PASS\n";
echo "TVmaze auto-series tests PASS\n";
