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
echo "TVmaze auto-series tests PASS\n";
