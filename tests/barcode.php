<?php
declare(strict_types=1);
define('ABSPATH','/mock/');
define('ARRAY_A','ARRAY_A');
define('DAY_IN_SECONDS',86400);
define('HOUR_IN_SECONDS',3600);
class WP_Error {
 public function __construct(public string $code,public string $message,public array $data=[]){}
}
function is_wp_error($x): bool{return $x instanceof WP_Error;}
function absint($v):int{return abs((int)$v);}
function sanitize_text_field($v):string{return trim(strip_tags((string)$v));}
function current_time($format):string{return '2026-11-03 12:00:00';}
$GLOBALS['options']=[];$GLOBALS['cached']=[];$GLOBALS['requests']=[];$GLOBALS['prej_reserves']=0;
function get_option($name,$default=false){return array_key_exists($name,$GLOBALS['options'])?$GLOBALS['options'][$name]:$default;}
function update_option($name,$value,$autoload=false){$GLOBALS['options'][$name]=$value;return true;}
function get_transient($name){return $GLOBALS['cached'][$name]??false;}
function set_transient($name,$value,$ttl){$GLOBALS['cached'][$name]=$value;return true;}
class MP_Storage {
 public static function table(string $name):string{return 'wp_mp_'.$name;}
}
class MP_ShoppingStores {
 public static function preferred(string $title):string{return 'Alle';}
}
class MP_ShoppingDetails {
 public static array $details=[];
 public static function all_details():array{return self::$details;}
 public static function save_item(int $id,array $data):array|WP_Error {
  if(!isset($GLOBALS['wpdb']->rows[$id]))return new WP_Error('missing','item missing');
  self::$details[(string)$id]=[
   'barcode'=>$data['barcode'],'title'=>$data['title'],'package_size'=>$data['package_size']
  ];
  return ['saved'=>true];
 }
}
class MP_Offers {
 public static function configured():bool{return true;}
 public static function reserve_barcode_call():bool{$GLOBALS['prej_reserves']++;return true;}
 public static function api_key():string{return 'prej_dev_test_only';}
}
class FakeDB {
 public array $rows=[];
 public int $insert_id=0;
 public function insert($table,$data,$format):int {
  $this->insert_id++;
  $this->rows[$this->insert_id]=['id'=>$this->insert_id]+$data;
  return 1;
 }
 public function prepare($query,...$values):string{
  return str_replace('%d',(string)$values[0],$query);
 }
 public function get_row($query,$format):?array{
  preg_match('/id=([0-9]+)/',$query,$m);
  return $this->rows[(int)($m[1]??0)]??null;
 }
 public function delete($table,$where,$format):int{
  unset($this->rows[(int)$where['id']]);return 1;
 }
}
$GLOBALS['wpdb']=new FakeDB();
function wp_safe_remote_get($url,$options){
 $GLOBALS['requests'][]=$url;
 if(str_contains($url,'api.prej.app/v1/scan/3017620422003')){
  return ['status'=>200,'body'=>json_encode(['product'=>[
   'name'=>'Nøddecreme','quantity_label'=>'400 g'
  ]])];
 }
 if(str_contains($url,'api.prej.app/v1/scan/')){
  return ['status'=>404,'body'=>'{}'];
 }
 if(str_contains($url,'world.openfoodfacts.org/api/v2/product/4006381333931')){
  return ['status'=>200,'body'=>json_encode(['status'=>1,'product'=>[
   'product_name_da'=>'Tysk chokolade','quantity'=>'100 g'
  ]])];
 }
 return ['status'=>200,'body'=>json_encode(['status'=>0])];
}
function wp_remote_retrieve_response_code($r):int{return $r['status'];}
function wp_remote_retrieve_body($r):string{return $r['body'];}
function check(bool $test,string $message):void{
 if(!$test)throw new RuntimeException('FAIL: '.$message);
}
require dirname(__DIR__).'/includes/class-mp-barcode.php';
check(MP_Barcode::valid('3017620422003'),'Valid EAN-13');
check(MP_Barcode::valid('4006381333931'),'Second valid EAN-13');
check(MP_Barcode::valid('5701234567899'),'Valid manually learned EAN-13');
check(!MP_Barcode::valid('5701234567890'),'Invalid GS1 check digit rejected');
check(!MP_Barcode::valid('301762042200'), 'Bad length/check digit rejected');
check(is_wp_error(MP_Barcode::add('5701234567890')),'Invalid barcode never adds item');
check(!$GLOBALS['requests'],'Invalid input makes no external API calls');
$prej=MP_Barcode::lookup('3017620422003');
check($prej['found'] && $prej['title']==='Nøddecreme' && $prej['source']==='Prej',
 'Prej GTIN endpoint used first');
check($GLOBALS['prej_reserves']===1,'Prej call accounted for');
$added=MP_Barcode::add('3017620422003');
check($added['added']===true && $added['barcode']==='3017620422003','Found product adds automatically');
check($GLOBALS['wpdb']->rows[$added['id']]['title']==='Nøddecreme','Product appears on family list');
check(MP_ShoppingDetails::$details[(string)$added['id']]['barcode']==='3017620422003',
 'Barcode attached to editable shopping item');
$duplicate=MP_Barcode::add('3017620422003');
check(!empty($duplicate['existing']) && empty($duplicate['added']),'Duplicate scans do not duplicate pending list');
check(count($GLOBALS['wpdb']->rows)===1,'List stays unique');
$off=MP_Barcode::lookup('4006381333931');
check($off['found'] && $off['source']==='Open Food Facts' && $off['title']==='Tysk chokolade',
 'Open Food Facts serves as free fallback');
$offAdd=MP_Barcode::add('4006381333931');
check($offAdd['added'] && $offAdd['quantity']==='100 g','Fallback product is added');
$unknown=MP_Barcode::add('5701234567899');
check(!empty($unknown['needs_name']),'Unknown barcode requests a user-defined product title');
$learn=MP_Barcode::add('5701234567899','Faxe Kondi','1,5 liter');
check($learn['added'] && $learn['title']==='Faxe Kondi','User-defined product added once');
check(MP_Barcode::lookup('5701234567899')['source']==='Familien',
 'Future scans use shared family catalog');
$again=MP_Barcode::add('5701234567899');
check(!empty($again['existing']),'Learned barcode does not duplicate active item');
check(isset($GLOBALS['options']['mp_shopping_barcode_catalog_v1']['5701234567899']),
 'Family mappings persist beyond transient cache');
check(count($GLOBALS['wpdb']->rows)===3,'Three distinct products on shared list');
echo "Barcode validation, Prej/OFF lookup, learning and duplicate prevention PASS\n";
