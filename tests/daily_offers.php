<?php
declare(strict_types=1);
define('ABSPATH','/mock/');
define('ARRAY_A','ARRAY_A');
class WP_Error {
 public function __construct(public string $code,public string $message,public array $data=[]) {}
 public function get_error_message(): string {return $this->message;}
}
function is_wp_error($v): bool{return $v instanceof WP_Error;}
function absint($v): int{return abs((int)$v);}
function wp_json_encode($v): string{return json_encode($v);}
$GLOBALS['opts']=[];$GLOBALS['cache']=[];$GLOBALS['remote']=[];
$GLOBALS['quota']=0;$GLOBALS['wp_jobs']=[];
$GLOBALS['time']='2026-11-03T06:00:00+01:00';
function current_datetime():DateTimeImmutable{return new DateTimeImmutable($GLOBALS['time']);}
function wp_timezone():DateTimeZone{return new DateTimeZone('Europe/Copenhagen');}
function get_option($key,$def=false){return array_key_exists($key,$GLOBALS['opts'])?$GLOBALS['opts'][$key]:$def;}
function update_option($key,$val,$a=false){$GLOBALS['opts'][$key]=$val;return true;}
function get_transient($key){return $GLOBALS['cache'][$key]??false;}
function set_transient($key,$value,$ttl){$GLOBALS['cache'][$key]=$value;return true;}
function delete_transient($key){unset($GLOBALS['cache'][$key]);return true;}
function wp_next_scheduled($key){return $GLOBALS['wp_jobs'][$key]??false;}
function wp_schedule_event($when,$interval,$name){$GLOBALS['wp_jobs'][$name]=$when;return true;}
function wp_clear_scheduled_hook($name){unset($GLOBALS['wp_jobs'][$name]);return true;}
class MP_Offers {
 public static function configured():bool{return true;}
 public static function api_key():string{return 'prej_mock_key';}
 public static function reserve_flyer_call():bool{
  if($GLOBALS['quota']>=20)return false;
  $GLOBALS['quota']++;return true;
 }
 public static function usage():array{return ['used'=>$GLOBALS['quota'],'limit'=>20,'remaining'=>20-$GLOBALS['quota']];}
 public static function search_phrase(string $s):string{return trim($s);}
}
class MP_ShoppingDetails {
 public static array $selected=['SuperBrugsen','Netto'];
 public static function selected_stores():array{return self::$selected;}
}
class MP_OfferFlyers {
 public static function chain_slug(string $c):string{return ['SuperBrugsen'=>'superbrugsen','Netto'=>'netto'][$c]??'';}
 public static function matching_name(string $name,string $q):bool{
  foreach(explode(' ',mb_strtolower($q)) as $term){if($term!==''&&!str_contains(mb_strtolower($name),$term))return false;}
  return true;
 }
 public static function normalize_offer(array $row,string $today,string $chain):?array {
  return [
    'name'=>$row['name'],'price_ore'=>$row['price'],
    'price_label'=>number_format($row['price']/100,2,',','.').' kr.',
    'store'=>$chain,'source'=>'Tilbudsavis',
    'ends'=>$row['offer_ends']??'','quantity'=>'',
  ];
 }
}
class MP_Storage{public static function table(string $name):string{return 'wp_mp_'.$name;}}
class DB {
 public array $shopping=[['id'=>4,'title'=>'Faxe Kondi','checked'=>0],['id'=>5,'title'=>'Kaffe','checked'=>0]];
 public function get_col(string $query):array{return array_column(array_filter($this->shopping,static fn($r)=>!$r['checked']),'title');}
 public function prepare(string $query,$value):string{return str_replace('%d',(string)$value,$query);}
 public function get_row(string $query,$format):?array{
  preg_match('/id=([0-9]+)/',$query,$m);
  $id=(int)($m[1]??0);
  foreach($this->shopping as $row)if($row['id']===$id)return $row;
  return null;
 }
}
$GLOBALS['wpdb']=new DB();
function wp_safe_remote_get($url,$options){
 $GLOBALS['remote'][]=$url;
 parse_str(parse_url($url,PHP_URL_QUERY)??'', $q);
 $chain=$q['chain']??'unknown';
 $page=(int)($q['page']??1);
 if($chain==='superbrugsen' && $page===1){
  return ['status'=>200,'body'=>json_encode(['offers'=>[
   ['name'=>'Pepsi Max eller Faxe Kondi','price'=>6500,'offer_ends'=>'2026-11-05'],
   ['name'=>'Andet tilbud','price'=>999]
  ],'total'=>2])];
 }
 return ['status'=>200,'body'=>json_encode(['offers'=>[],'total'=>0])];
}
function wp_remote_retrieve_response_code($r):int{return $r['status'];}
function wp_remote_retrieve_body($r):string{return $r['body'];}
require dirname(__DIR__).'/includes/class-mp-daily-offers.php';
function verify(bool $cond,string $message):void{if(!$cond)throw new RuntimeException('FAIL: '.$message);}
verify(!MP_DailyOffers::enabled(),'Daily updates disabled by default');
verify(!MP_DailyOffers::tick()['updated_today'],'No calls when disabled');
verify($GLOBALS['quota']===0,'Disabled means no external requests');
MP_DailyOffers::set_enabled(true);
verify(MP_DailyOffers::enabled(),'Administrator setting enables updates');
verify(isset($GLOBALS['wp_jobs']['mp_daily_offer_tick']),'Cron schedule registered');
$before=MP_DailyOffers::status();
verify(!$before['updated_today'],'No stale daily index');
$a=MP_DailyOffers::tick();
verify($a['daily_calls']===1 && $GLOBALS['quota']===1,'At most one Prej call per tick');
verify($a['offers']===1,'Only matching shopping products retained');
verify($a['checked_stores']===['SuperBrugsen'],'First store scanned');
verify(MP_DailyOffers::matches(4)['offers'][0]['price_ore']===6500,'Faxe Kondi found in daily index');
verify(MP_DailyOffers::matches(5)['offers']===[],'Unrelated products not shown');
MP_DailyOffers::tick();
verify(count($GLOBALS['remote'])===2,'Next tick processes next store');
verify(MP_DailyOffers::status()['complete'],'Two selected stores finish');
verify(MP_DailyOffers::status()['daily_calls']===2,'Daily calls counted');
MP_DailyOffers::tick();
verify(count($GLOBALS['remote'])===2,'Completed day does not rerun requests');
$GLOBALS['time']='2026-11-04T04:00:00+01:00';
verify(!MP_DailyOffers::status()['updated_today'],'Yesterday data expires on next calendar day');
verify(MP_DailyOffers::matches(4)['offers']===[],'Old Faxe offers hidden until fresh import');
MP_DailyOffers::tick();
verify(count($GLOBALS['remote'])===2,'No morning data fetch before 05:30');
$GLOBALS['time']='2026-11-04T06:00:00+01:00';
MP_DailyOffers::tick();
verify(count($GLOBALS['remote'])===3,'Automatic daily new index starts after 05:30');
MP_DailyOffers::set_enabled(false);
verify(!isset($GLOBALS['wp_jobs']['mp_daily_offer_tick']),'Disabling clears cron schedule');
echo "Daily Prej offer index, free quota, local cache and date rollover PASS\n";
