<?php
declare(strict_types=1);
/** Current flyer links are visible to the household without mirroring images. */
define('ABSPATH','/mock/');
define('HOUR_IN_SECONDS',3600);
class WP_Error {
 public function __construct(public string $code,private string $message,public array $data=[]){}
 public function get_error_message(): string{return $this->message;}
}
function is_wp_error($x): bool{return $x instanceof WP_Error;}
function absint($v): int{return abs((int)$v);}
function sanitize_text_field($v): string{return trim(strip_tags((string)$v));}
function current_datetime(): DateTimeImmutable{return new DateTimeImmutable('2026-11-01T20:00:00+01:00');}
$GLOBALS['cache']=[];$GLOBALS['calls']=[];$GLOBALS['count']=0;
function get_transient($k){return $GLOBALS['cache'][$k]??false;}
function set_transient($k,$v,$ttl){$GLOBALS['cache'][$k]=$v;return true;}
class MP_ShoppingDetails{
 public static array $chosen=['SuperBrugsen'];
 public static function selected_stores(): array{return self::$chosen;}
}
class MP_OfferFlyers{
 public static function chain_slug(string $store): string{
  return ['SuperBrugsen'=>'superbrugsen','Netto'=>'netto'][$store]??'';
 }
}
class MP_Offers{
 public static bool $enabled=true;
 public static function configured(): bool{return self::$enabled;}
 public static function reserve_flyer_call(): bool{$GLOBALS['count']++;return $GLOBALS['count']<=20;}
 public static function api_key(): string{return 'prej_dev_mock';}
 public static function usage(): array{return ['used'=>$GLOBALS['count'],'limit'=>20,'remaining'=>max(0,20-$GLOBALS['count'])];}
}
function wp_safe_remote_get($url,$options){
 $GLOBALS['calls'][]=['url'=>$url,'authorization'=>$options['headers']['Authorization']];
 parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
 $page=(int)($query['page']??1);
 $flyers=$page===1?array_fill(0,100,[
  'chain_slug'=>'netto','flyer_id'=>'no','valid_from'=>'2026-10-25',
  'valid_to'=>'2026-11-05','page_urls'=>['https://img.prej.app/flyers/netto/99/page-01.jpg']
 ]):[
  ['chain_slug'=>'superbrugsen','flyer_id'=>'20261105','valid_from'=>'2026-10-30',
   'valid_to'=>'2026-11-05',
   'page_urls'=>[
    'https://img.prej.app/flyers/superbrugsen/20261105/page-01.jpg',
    'https://img.prej.app/flyers/superbrugsen/20261105/page-02.jpg',
    'https://evil.example/flyers/superbrugsen/20261105/page-03.jpg',
    'https://img.prej.app/flyers/superbrugsen/20261105/page-03.jpg?token=secret'
   ]],
  ['chain_slug'=>'superbrugsen','flyer_id'=>'old','valid_from'=>'2026-10-01',
   'valid_to'=>'2026-10-31','page_urls'=>['https://img.prej.app/flyers/superbrugsen/old/page-01.jpg']]
 ];
 return ['code'=>200,'body'=>json_encode(['flyers'=>$flyers,'total'=>102])];
}
function wp_remote_retrieve_response_code($r): int{return $r['code'];}
function wp_remote_retrieve_body($r): string{return $r['body'];}
require dirname(__DIR__).'/includes/class-mp-flyer-library.php';
function check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL: '.$label);}
$result=MP_FlyerLibrary::listing();
check(!is_wp_error($result),'Returns current flyers');
check(count($GLOBALS['calls'])===2,'Pagination includes second page');
check(count($result['flyers'])===1,'Selected household chain only, expired omitted');
check($result['flyers'][0]['chain']==='SuperBrugsen','Mapped chain correctly');
check($result['flyers'][0]['valid_to']==='2026-11-05','Validity preserved');
check($result['flyers'][0]['page_count']===2,'Only original HTTPS image host links allowed');
check($result['flyers'][0]['source_page_count']===4,'Original source page count reported separately');
check($result['flyers'][0]['page_urls'][0]==='https://img.prej.app/flyers/superbrugsen/20261105/page-01.jpg','First page link');
check($result['usage']['used']===2,'Free API quota counted');
check(!$result['partial'],'Complete two page listing');
$again=MP_FlyerLibrary::listing();
check(count($GLOBALS['calls'])===2,'Cached listing does not use more provider calls');
MP_ShoppingDetails::$chosen=['Netto'];
$other=MP_FlyerLibrary::listing();
check(count($other['flyers'])===1,'Changing family selection filters saved result');
MP_ShoppingDetails::$chosen=[];
$empty=MP_FlyerLibrary::listing();
check(!$empty['flyers'],'No selected chain means no displayed flyers');
MP_Offers::$enabled=false;
check(is_wp_error(MP_FlyerLibrary::listing()),'Missing API key produces clear error');
echo "Private household flyer listing, source links, expiry, pagination and quota PASS\n";
