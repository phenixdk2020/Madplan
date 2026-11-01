<?php
declare(strict_types=1);
define('ABSPATH','/mock/');
define('ARRAY_A','ARRAY_A');
define('HOUR_IN_SECONDS',3600);
class WP_Error {
 public function __construct(public string $code,private string $msg,public array $data=[]) {}
 public function get_error_message(): string {return $this->msg;}
 public function get_error_code(): string {return $this->code;}
}
function is_wp_error($x): bool{return $x instanceof WP_Error;}
function absint($x): int{return abs((int)$x);}
function sanitize_text_field($x): string{return trim(strip_tags((string)$x));}
function current_datetime(): DateTimeImmutable{return new DateTimeImmutable('2026-11-01T21:00:00+01:00');}
$GLOBALS['cache']=[];$GLOBALS['calls']=[];$GLOBALS['used']=0;
function get_transient($key){return $GLOBALS['cache'][$key]??false;}
function set_transient($key,$value,$ttl){$GLOBALS['cache'][$key]=$value;return true;}
class MP_Offers {
 public static function configured(): bool{return true;}
 public static function api_key(): string{return 'prej_dev_example_key';}
 public static function reserve_flyer_call(): bool{if($GLOBALS['used']>=20)return false;$GLOBALS['used']++;return true;}
 public static function usage(): array{return ['used'=>$GLOBALS['used'],'remaining'=>20-$GLOBALS['used'],'limit'=>20];}
 public static function search_phrase(string $s): string{return $s;}
}
class MP_Storage{public static function table(string $name): string{return 'wp_mp_'.$name;}}
class MP_ShoppingDetails{public static function selected_stores(): array{return ['SuperBrugsen','Netto'];}}
class FakeDb {
 public array $rows=[12=>['id'=>12,'title'=>'Faxe Kondi','checked'=>0],13=>['id'=>13,'title'=>'Pepsi Max','checked'=>1]];
 public function prepare(string $sql,...$a): string{return str_replace('%d',(string)$a[0],$sql);}
 public function get_row(string $q,$format): ?array{
  preg_match('/id=([0-9]+)/',$q,$m);
  return $this->rows[(int)($m[1]??0)]??null;
 }
}
$GLOBALS['wpdb']=new FakeDb();
function wp_safe_remote_get($url,$options) {
 $GLOBALS['calls'][]=['url'=>$url,'headers'=>$options['headers']];
 parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
 if(($query['chain']??'')!=='superbrugsen')return ['code'=>400,'body'=>'{}'];
 $page=(int)($query['page']??1);
 $rows=array_fill(0,$page===3?1:100,[
  'name'=>'Andet produkt','price'=>1000,'source'=>'flyer','offer_ends'=>'2026-11-05'
 ]);
 if($page===3){
  $rows[0]=[
   'name'=>'Faxe Kondi og Pepsi Max 24 x 33 cl',
   'price'=>6500,'source'=>'flyer',
   'offer_starts'=>'2026-10-30','offer_ends'=>'2026-11-05',
   'unit_price_label'=>'8,21 kr/L','quantity_label'=>'24 x 33 cl'
  ];
 }
 return ['code'=>200,'body'=>json_encode(['offers'=>$rows,'total'=>201,'page'=>$page,'limit'=>100])];
}
function wp_remote_retrieve_response_code($r): int{return $r['code'];}
function wp_remote_retrieve_body($r): string{return $r['body'];}
require dirname(__DIR__).'/includes/class-mp-offer-flyers.php';
function check(bool $v,string $reason): void{if(!$v)throw new RuntimeException('FAIL: '.$reason);}
check(MP_OfferFlyers::chain_slug('SuperBrugsen')==='superbrugsen','Official chain slug');
check(MP_OfferFlyers::chain_slug('Føtex')==='fotex','Føtex slug');
check(MP_OfferFlyers::matching_name('Pepsi Max / Faxe Kondi 24 pak','Faxe Kondi'),'Combined offer wording matches');
check(!MP_OfferFlyers::matching_name('Faxe Kondi Booster','Pepsi Max'),'No unrelated text matching');
check(is_wp_error(MP_OfferFlyers::scan(13,'SuperBrugsen')),'No requests for checked items');
check(is_wp_error(MP_OfferFlyers::scan(12,'Kvickly')),'Unselected chain cannot bypass household filter');
check(!$GLOBALS['calls'],'No calls on invalid requests');
$r=MP_OfferFlyers::scan(12,'SuperBrugsen');
check(!is_wp_error($r),'Expanded search completed');
check(count($GLOBALS['calls'])===3,'Three pages fetched to reach result on page 3');
check($GLOBALS['used']===3,'Each call accounted for in free quota');
check(count($r['items'][0]['offers'])===1,'Only matching product surfaced');
check($r['items'][0]['offers'][0]['store']==='SuperBrugsen','Offer attributed to requested chain');
check($r['items'][0]['offers'][0]['price_label']==='65,00 kr.','Price is converted from øre');
check($r['items'][0]['offers'][0]['ends']==='2026-11-05','Valid to Thursday Nov 5');
check(!$r['items'][0]['partial'],'Whole 201-offer result scanned');
check(str_contains($GLOBALS['calls'][0]['url'],'chain=superbrugsen'),'Chain-filtered API');
check(str_contains($GLOBALS['calls'][0]['url'],'source=flyer'),'Flyer-only structured offers');
check(str_contains($GLOBALS['calls'][0]['url'],'limit=100'),'Maximum documented page size');
$r2=MP_OfferFlyers::scan(12,'SuperBrugsen');
check(count($GLOBALS['calls'])===3 && $r2['usage']['used']===3,'Six-hour cache shared across repeat searches');
check(MP_OfferFlyers::normalize_offer(['name'=>'Faxe Kondi','price'=>1000,'offer_ends'=>'2026-10-31'],'2026-11-01','SuperBrugsen')===null,
 'Expired offer excluded');
check(MP_OfferFlyers::normalize_offer(['name'=>'Faxe Kondi','price'=>1000,'offer_starts'=>'2026-11-04'],'2026-11-01','SuperBrugsen')===null,
 'Future offer excluded');
echo "Prej SuperBrugsen deep search, pagination, quotas and caching PASS\n";
