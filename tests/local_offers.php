<?php
declare(strict_types=1);
define('ABSPATH','/mock/');
define('ARRAY_A','ARRAY_A');
class WP_Error {
 public function __construct(public string $code,public string $message,public array $data=[]) {}
 public function get_error_message():string{return $this->message;}
}
function is_wp_error($x):bool{return $x instanceof WP_Error;}
function absint($v):int{return abs((int)$v);}
function current_datetime():DateTimeImmutable{return new DateTimeImmutable('2026-11-08');}
class MP_ShoppingDetails {public static function selected_stores():array{return ['Føtex'];}}
class MP_Storage {public static function table(string $table):string{return 'wp_mp_'.$table;}}
class FakeDB {
 public function prepare(string $query,int $id):string{return str_replace('%d',(string)$id,$query);}
 public function get_row(string $query,string $format):?array {
  if(str_contains($query,'id=1'))return ['id'=>1,'title'=>'Sensodyne Tandpasta','checked'=>0];
  if(str_contains($query,'id=2'))return ['id'=>2,'title'=>'Købt vare','checked'=>1];
  return null;
 }
}
$GLOBALS['wpdb']=new FakeDB();
class MP_DailyOffers {
 public static function matches(int $id):array{
  return ['offers'=>[
   ['name'=>'Sensodyne Tandpasta','store'=>'Føtex','price_ore'=>2000,
    'price_label'=>'20,00 kr.','ends'=>'2026-11-11','source'=>'Tilbudsavis'],
   ['name'=>'Sensodyne Tandpasta','store'=>'Netto','price_ore'=>1500,'ends'=>'2026-11-11'],
   ['name'=>'Sensodyne Tandpasta','store'=>'Føtex','price_ore'=>1000,'ends'=>'2026-11-07'],
  ]];
 }
}
class MP_DailyOCR {
 public static function matches(int $id):array{
  return ['offers'=>[
   ['name'=>'Sensodyne Tandpasta','store'=>'Føtex','price_ore'=>2000,'valid_to'=>'2026-11-11','source'=>'OCR'],
   ['name'=>'Sensodyne Repair','store'=>'Føtex','price_ore'=>2500,'valid_to'=>'2026-11-12','source'=>'OCR'],
  ]];
 }
}
class MP_FlyerOCR {
 public static function match_for_item(int $id):array{
  return ['matches'=>[
   ['name'=>'Sensodyne Repair','store'=>'Føtex','price_ore'=>2500,'valid_to'=>'2026-11-12','source'=>'OCR'],
  ]];
 }
}
require dirname(__DIR__).'/includes/class-mp-local-offers.php';
function check(bool $x,string $why):void{if(!$x)throw new RuntimeException('FAIL: '.$why);}
$r=MP_LocalOffers::scan([1,1,2]);
check(!is_wp_error($r),'Local lookup succeeds');
check(count($r['items'])===1,'Duplicates and checked items ignored');
check($r['items'][0]['local_index']===true,'Local-only marker');
check(count($r['items'][0]['offers'])===2,'Selected store, deduplication and expiry');
check($r['items'][0]['offers'][0]['price_label']==='20,00 kr.','Structured offer preferred');
check(is_wp_error(MP_LocalOffers::scan(range(1,11))),'Bounded bulk lookup');
echo "Local-first offers without external API requests PASS\n";
