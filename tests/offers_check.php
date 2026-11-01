<?php
declare(strict_types=1);
define('ABSPATH','/mock/');
define('HOUR_IN_SECONDS',3600);
define('ARRAY_A','ARRAY_A');
class WP_Error {
 public function __construct(public string $code,private string $message,public array $data=[]){}
 public function get_error_message(): string {return $this->message;}
 public function get_error_code(): string {return $this->code;}
}
function is_wp_error($v): bool{return $v instanceof WP_Error;}
function absint($v): int{return abs((int)$v);}
function sanitize_text_field($v): string{return trim(strip_tags((string)$v));}
function current_datetime(): DateTimeImmutable{return new DateTimeImmutable('2026-11-01T08:00:00+01:00');}
$GLOBALS['opts']=[];$GLOBALS['cache']=[];$GLOBALS['calls']=[];
function get_option($name,$default=false){return array_key_exists($name,$GLOBALS['opts'])?$GLOBALS['opts'][$name]:$default;}
function update_option($name,$value,$autoload=false){$GLOBALS['opts'][$name]=$value;return true;}
function delete_option($name){unset($GLOBALS['opts'][$name]);}
function get_transient($name){return $GLOBALS['cache'][$name]??false;}
function set_transient($name,$value,$time){$GLOBALS['cache'][$name]=$value;return true;}
class MP_Storage {public static function table($name){return 'wp_mp_'.$name;}}
class MockDb {
 public array $rows=[5=>['id'=>5,'title'=>'2 l mælk','checked'=>0],6=>['id'=>6,'title'=>'Kaffe','checked'=>1],7=>['id'=>7,'title'=>'Kaffe','checked'=>0]];
 public function prepare($sql,$id){return (string)$id;}
 public function get_row($sql,$format){return $this->rows[(int)$sql]??null;}
}
$GLOBALS['wpdb']=new MockDb();
function wp_safe_remote_get($url,$args){
 $GLOBALS['calls'][]=['url'=>$url,'args'=>$args];
 return ['code'=>200,'body'=>json_encode(['as_of'=>'2026-11-01','products'=>[
 ['name'=>'Letmælk 1 liter','quantity_label'=>'1 l','prices'=>[
 ['chain_name'=>'Netto','price'=>1295,'unit_price_label'=>'12,95 kr/L','is_offer'=>true,'source'=>'flyer','offer_starts'=>'2026-10-30','offer_ends'=>'2026-11-03','last_seen_date'=>'2026-11-01'],
 ['chain_name'=>'Føtex','price'=>1395,'is_offer'=>true,'offer_ends'=>'2026-10-31'],
 ['chain_name'=>'Bilka','price'=>1195,'is_offer'=>false,'offer_ends'=>'2026-11-09'],
 ['chain_name'=>'Rema 1000','price'=>1145,'is_offer'=>true,'offer_starts'=>'2026-11-05','offer_ends'=>'2026-11-09'],
 ]]]])];
}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
require dirname(__DIR__).'/includes/class-mp-offers.php';
function check(bool $v,string $name): void{if(!$v)throw new RuntimeException('FAIL '.$name);}
check(!MP_Offers::configured(),'No key by default');
check(is_wp_error(MP_Offers::scan([5])),'No outbound without key');
check(!MP_Offers::save_key('invalid'),'Reject bad key');
check(MP_Offers::save_key('prej_dev_12345678901234567890'),'Accept free key');
check(MP_Offers::search_phrase('2 l mælk')==='mælk','Parse quantity prefix');
$r=MP_Offers::scan([5,6,5,999]);
check(count($r['items'])===1,'Only existing unchecked unique items');
check(count($r['items'][0]['offers'])===1,'Expired, future, non-offer removed');
check($r['items'][0]['offers'][0]['price_label']==='12,95 kr.','Kroner from ore');
check($r['items'][0]['offers'][0]['source']==='Tilbudsavis','Source flyer labelled');
check(count($GLOBALS['calls'])===1,'One provider request');
check(str_contains($GLOBALS['calls'][0]['url'],'on_offer=1'),'Offer query');
check(str_contains($GLOBALS['calls'][0]['url'],'exclude_border_trade=1'),'No border trade');
check(str_contains($GLOBALS['calls'][0]['url'],'q=m%C3%A6lk'),'URL encoded Danish word');
$r=MP_Offers::scan([5]);
check(count($GLOBALS['calls'])===1 && $r['usage']['used']===1,'Family cache prevents repeated calls');
check(is_wp_error(MP_Offers::scan(range(1,11))),'Max 10 items');
$GLOBALS['opts']['mp_prej_usage_v1']=['date'=>gmdate('Y-m-d'),'count'=>20];
$r=MP_Offers::scan([7]);
check(count($GLOBALS['calls'])===1 && $r['usage']['remaining']===0,'Daily quota stops calls');
echo "Shopping offers extraction, rate limit and cache PASS\n";
