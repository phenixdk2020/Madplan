<?php
declare(strict_types=1);
define('ABSPATH','/mock/');
class WP_Error {
    public function __construct(public string $code,public string $message,public array $data=[]) {}
}
$GLOBALS['saved']=[];
$GLOBALS['fixtures']=[
 'movie/101'=>['id'=>101,'title'=>'Fremtidens film','release_date'=>'2026-09-17','poster_path'=>'/film101.jpg'],
 'movie/101/release_dates'=>['results'=>[
  ['iso_3166_1'=>'US','release_dates'=>[['type'=>4,'release_date'=>'2026-09-17T00:00:00.000Z']]],
  ['iso_3166_1'=>'DK','release_dates'=>[
   ['type'=>3,'release_date'=>'2026-10-01T00:00:00.000Z'],
   ['type'=>4,'release_date'=>'2026-11-06T00:00:00.000Z'],
  ]]
 ]],
 'movie/101/watch/providers'=>['results'=>['DK'=>['flatrate'=>[['provider_name'=>'Netflix']]]]],
 'movie/102'=>['id'=>102,'title'=>'Biograffilm','release_date'=>'2026-09-20','poster_path'=>null],
 'movie/102/release_dates'=>['results'=>[
  ['iso_3166_1'=>'DK','release_dates'=>[['type'=>3,'release_date'=>'2026-11-10T00:00:00.000Z']]]
 ]],
 'movie/102/watch/providers'=>['results'=>['DK'=>[]]],
 'search/movie'=>['results'=>[
  ['id'=>101,'title'=>'Fremtidens film','release_date'=>'2026-09-17','poster_path'=>'/film101.jpg','adult'=>false],
  ['id'=>103,'title'=>'Voksenfilm','adult'=>true]
 ]]
];
function get_option(string $name,$default=false) { return $GLOBALS['saved'][$name]??$default; }
function update_option(string $name,$value,$autoload=false) { $GLOBALS['saved'][$name]=$value;return true; }
function sanitize_text_field(string $value): string { return strip_tags($value); }
function absint($value): int { return abs((int)$value); }
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function current_datetime(): DateTimeImmutable { return new DateTimeImmutable('2026-10-31T12:00:00+01:00'); }
function get_current_user_id(): int { return 101; }
class MP_Streaming {
    public static bool $configured=true;
    public static function configured(): bool { return self::$configured; }
    public static function tmdb_get(string $path,array $q=[]): array|WP_Error {
        return $GLOBALS['fixtures'][$path]??new WP_Error('tmdb_missing','No fixture for '.$path);
    }
    public static function chosen(): array { return ['Netflix']; }
    public static function services(): array { return ['Netflix','Prime Video']; }
    public static function selected_matches(array $dk,array $subs): array {
        return !empty($dk['flatrate']) && in_array('Netflix',$subs,true)?['Netflix']:[];
    }
}
function check(bool $ok,string $label): void { if (!$ok)throw new RuntimeException('FAIL: '.$label); }
require dirname(__DIR__).'/includes/class-mp-movies.php';

$dates=MP_Movies::parse_dk_releases($GLOBALS['fixtures']['movie/101/release_dates']);
check($dates['digital']==='2026-11-06','DK digital date detected');
check($dates['cinema']==='2026-10-01','DK theatrical date detected separately');
check($dates['digital']!=='2026-09-17','US digital date ignored');

$film=MP_Movies::add(['tmdb_id'=>101,'mode'=>'streaming','service'=>'','manual_date'=>'']);
check(!is_wp_error($film),'New movie added');
check($film['date']==='2026-11-06' && $film['status']==='movie_digital','Digital date chosen');
check($film['service']==='Netflix','Single family provider may be automatically chosen');
check($film['date_note']!=='' && str_contains($film['date_note'],'ikke bekræftet'),
      'Digital date does not confirm specific Netflix premiere');
check(count(MP_Movies::schedule('2026-11-02','2026-11-08'))===1,'Film appears exactly once in correct week');
check(count(MP_Movies::schedule('2026-11-09','2026-11-15'))===0,'Film does not repeat following week');
check(count(MP_Movies::schedule('2026-10-26','2026-11-01'))===0,'Film does not appear on old DK cinema date');

$dup=MP_Movies::add(['tmdb_id'=>101]);
check(is_wp_error($dup),'Duplicate TMDB movie rejected');

$cinema=MP_Movies::add(['tmdb_id'=>102,'mode'=>'streaming','service'=>'','manual_date'=>'']);
check($cinema['date']==='' && $cinema['status']==='movie_pending',
      'Theatrical-only date must not impersonate streaming premiere');
check(count(MP_Movies::schedule('2026-11-09','2026-11-15'))===0,'Cinema-only not shown in streaming calendar');
$switched=MP_Movies::update($cinema['id'],['mode'=>'cinema']);
check($switched['date']==='2026-11-10' && $switched['status']==='movie_cinema','Explicit cinema mode uses DK cinema date');
check(count(MP_Movies::schedule('2026-11-09','2026-11-15'))===1,'Cinema premiere appears once after selecting cinema mode');

$manual=MP_Movies::add(['tmdb_id'=>0,'title'=>'Min kommende film','mode'=>'streaming','service'=>'Prime Video','manual_date'=>'2026-11-21']);
check($manual['status']==='movie_manual','Manual movie has explicit unverified status');
check(count(MP_Movies::schedule('2026-11-16','2026-11-22'))===1,'Manual movie appears once');
check(count(MP_Movies::schedule('2026-11-23','2026-11-29'))===0,'Manual movie does not recur');
check(count(MP_Movies::all())===3,'Films shared in one option with no user-specific storage');

$search=MP_Movies::search('Fremtidens film');
check(count($search)===1 && $search[0]['tmdb_id']===101,'Search is safe, returns poster and excludes adult results');
MP_Streaming::$configured=false;
check(is_wp_error(MP_Movies::search('Film')),'Search requires optional TMDB key');
check(!is_wp_error(MP_Movies::add(['title'=>'Film uden nøgle','mode'=>'streaming'])),'Manual film works without API key');
MP_Streaming::$configured=true;

$GLOBALS['fixtures']['movie/101/release_dates']['results'][1]['release_dates'][1]['release_date']='2026-11-13T00:00:00.000Z';
$ref=MP_Movies::refresh($film['id']);
check($ref['date']==='2026-11-13','Changed DK premiere is updated');
check(count(MP_Movies::schedule('2026-11-02','2026-11-08'))===0,'Old premiere disappears from calendar');
check(count(MP_Movies::schedule('2026-11-09','2026-11-15'))===2,'New date appears once along with existing cinema date');
check(!is_wp_error(MP_Movies::remove($film['id'])),'Delete successful');
check(count(MP_Movies::schedule('2026-11-09','2026-11-15'))===1,'Delete removes film calendar occurrence');
check(!empty($GLOBALS['saved']['mp_movies_v1']),'Options contain household movies');
echo "Future-only single movie premiere tests PASS\n";
