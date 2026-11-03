<?php
declare(strict_types=1);
define('ABSPATH','/mock/');
define('ARRAY_A','ARRAY_A');
define('HOUR_IN_SECONDS',3600);
class WP_Error {
 public function __construct(public string $code,public string $message,public array $data=[]) {}
 public function get_error_message(): string {return $this->message;}
}
function is_wp_error($x):bool{return $x instanceof WP_Error;}
function absint($v):int{return abs((int)$v);}
$GLOBALS['opts']=[];$GLOBALS['cache']=[];$GLOBALS['cron']=[];$GLOBALS['pages_fetched']=0;
$GLOBALS['clock']='2026-11-03T07:00:00+01:00';
function current_datetime():DateTimeImmutable{return new DateTimeImmutable($GLOBALS['clock']);}
function wp_timezone():DateTimeZone{return new DateTimeZone('Europe/Copenhagen');}
function get_option($key,$default=false){return $GLOBALS['opts'][$key]??$default;}
function update_option($key,$value,$autoload=false):bool{$GLOBALS['opts'][$key]=$value;return true;}
function get_transient($key){return $GLOBALS['cache'][$key]??false;}
function set_transient($key,$value,$ttl):bool{$GLOBALS['cache'][$key]=$value;return true;}
function delete_transient($key):bool{unset($GLOBALS['cache'][$key]);return true;}
function wp_next_scheduled($key){return $GLOBALS['cron'][$key]??false;}
function wp_schedule_event($time,$interval,$key){$GLOBALS['cron'][$key]=$time;return true;}
function wp_clear_scheduled_hook($key){unset($GLOBALS['cron'][$key]);return true;}
class MP_FlyerOCR {
 public static bool $available=true;
 public static function status():array{return ['available'=>self::$available];}
 public static function text_from_local_image(string $path):string|WP_Error{
  if(!is_file($path) || filesize($path)===0)throw new RuntimeException('Missing temporary image');
  return "Faxe Kondi\n24 x 33 cl\n65,-";
 }
 public static function parse_candidates(string $text,string $chain,string $valid):array {
  return [[
   'name'=>'Faxe Kondi','store'=>$chain,'price_ore'=>6500,
   'price_label'=>'65,00 kr.','valid_to'=>$valid,'source'=>'OCR-forslag',
  ]];
 }
}
class MP_FlyerLibrary {
 public static function listing():array{
  return [
   'partial'=>false,'flyers'=>[
    ['chain'=>'SuperBrugsen','valid_to'=>'2026-11-05',
     'page_urls'=>['https://img.prej.app/flyers/superbrugsen/20261105/page-01.jpg']],
    ['chain'=>'Netto','valid_to'=>'2026-11-08',
     'page_urls'=>['https://img.prej.app/flyers/netto/20261108/page-01.jpg']],
   ]
  ];
 }
}
class MP_Offers {public static function search_phrase(string $v):string{return $v;}}
class MP_OfferFlyers {
 public static function matching_name(string $name,string $term):bool{
  return str_contains(mb_strtolower($name),mb_strtolower($term));
 }
}
class MP_Storage {public static function table(string $name):string{return 'wp_mp_'.$name;}}
class FakeDB {
 public function prepare(string $sql,$id):string{return str_replace('%d',(string)$id,$sql);}
 public function get_row(string $sql,$format):?array{
  return str_contains($sql,'id=4')?['id'=>4,'title'=>'Faxe Kondi','checked'=>0]:null;
 }
}
$GLOBALS['wpdb']=new FakeDB();
function wp_safe_remote_get($url,$options){
 $GLOBALS['pages_fetched']++;
 return ['code'=>200,'body'=>base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jR90AAAAASUVORK5CYII=')];
}
function wp_remote_retrieve_response_code($r):int{return $r['code'];}
function wp_remote_retrieve_body($r):string{return $r['body'];}
require dirname(__DIR__).'/includes/class-mp-daily-ocr.php';
function verify(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException('FAIL: '.$msg);}
verify(!MP_DailyOCR::enabled(),'Disabled by default');
verify(is_wp_error(MP_DailyOCR::set_enabled(true,false)),'Rights confirmation mandatory');
verify(!MP_DailyOCR::enabled(),'Failed activation does not change settings');
MP_FlyerOCR::$available=false;
verify(is_wp_error(MP_DailyOCR::set_enabled(true,true)),'Tesseract required');
MP_FlyerOCR::$available=true;
$enabled=MP_DailyOCR::set_enabled(true,true);
verify($enabled['enabled'],'Rights-confirmed status');
verify(isset($GLOBALS['cron']['mp_daily_ocr_tick']),'Schedules 15 minute batches');
verify($GLOBALS['pages_fetched']===0,'Enabling never fetches images');
$a=MP_DailyOCR::tick();
verify($a['processed']===1 && $a['total']===2,'One flyer page per tick');
verify($a['offers']===1,'First OCR candidate indexed');
verify($GLOBALS['pages_fetched']===1,'One remote image fetched');
verify(MP_DailyOCR::matches(4)['offers'][0]['price_label']==='65,00 kr.','Faxe match in daily OCR index');
$b=MP_DailyOCR::tick();
verify($b['processed']===2 && $b['complete'],'All two pages handled');
verify($GLOBALS['pages_fetched']===2,'Two pages fetched total');
MP_DailyOCR::tick();
verify($GLOBALS['pages_fetched']===2,'Completion stops further fetches');
$GLOBALS['clock']='2026-11-04T04:00:00+01:00';
verify(MP_DailyOCR::matches(4)['offers']===[],'Yesterday OCR index expires automatically');
MP_DailyOCR::tick();
verify($GLOBALS['pages_fetched']===2,'OCR cannot start before 6AM');
$GLOBALS['clock']='2026-11-04T07:00:00+01:00';
MP_DailyOCR::tick();
verify($GLOBALS['pages_fetched']===3,'Next day begins new OCR pass');
MP_DailyOCR::set_enabled(false,false);
verify(!isset($GLOBALS['cron']['mp_daily_ocr_tick']),'Disabling removes scheduled task');
echo "Rights-gated daily OCR, temporary images, daily index and scheduling PASS\n";
