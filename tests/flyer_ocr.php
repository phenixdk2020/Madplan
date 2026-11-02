<?php
declare(strict_types=1);
define('ABSPATH','/test/');
define('ARRAY_A','ARRAY_A');
define('HOUR_IN_SECONDS',3600);
class WP_Error {
 public function __construct(public string $code,public string $message,public array $data=[]) {}
 public function get_error_message(): string{return $this->message;}
}
function is_wp_error($e): bool{return $e instanceof WP_Error;}
function sanitize_text_field($v): string{return trim(strip_tags((string)$v));}
function get_option($key,$default=false){return array_key_exists($key,$GLOBALS['options'])?$GLOBALS['options'][$key]:$default;}
function update_option($key,$value,$a=false): bool{$GLOBALS['options'][$key]=$value;return true;}
function delete_option($key): void{unset($GLOBALS['options'][$key]);}
function get_transient($key){return $GLOBALS['cache'][$key]??false;}
function set_transient($key,$value,$ttl): bool{$GLOBALS['cache'][$key]=$value;return true;}
function current_datetime(): DateTimeImmutable{return new DateTimeImmutable('2026-11-02T10:00:00+01:00');}
$GLOBALS['options']=[];$GLOBALS['cache']=[];
class MP_ShoppingDetails {
 public static array $stores=['SuperBrugsen','Netto'];
 public static function selected_stores(): array{return self::$stores;}
}
class MP_Offers {
 public static function search_phrase(string $q): string{return trim($q);}
}
class MP_OfferFlyers {
 public static function matching_name(string $n,string $q):bool {
  foreach(explode(' ',mb_strtolower($q)) as $token){
   if($token!=='' && !str_contains(mb_strtolower($n),$token))return false;
  }
  return true;
 }
}
class MP_Storage{public static function table(string $suffix): string{return 'wp_mp_'.$suffix;}}
class TestDb {
 public function prepare(string $sql,int $id): string{return str_replace('%d',(string)$id,$sql);}
 public function get_row(string $sql,string $format): ?array{
  if(str_contains($sql,'id=12'))return ['id'=>12,'title'=>'Faxe Kondi','checked'=>0];
  if(str_contains($sql,'id=13'))return ['id'=>13,'title'=>'Hakket oksekød','checked'=>0];
  return null;
 }
}
$GLOBALS['wpdb']=new TestDb();
function verify(bool $ok,string $why): void{if(!$ok)throw new RuntimeException('FAIL: '.$why);}
require dirname(__DIR__).'/includes/class-mp-flyer-ocr.php';
$sample="SuperBrugsen\nFaxe Kondi\n24 x 33 cl\n65,-\nHakket oksekød\n400 g\n29,95 kr\n";
$candidates=MP_FlyerOCR::parse_candidates($sample,'SuperBrugsen','2026-11-05');
verify(count($candidates)>=2,'Two OCR item/price candidates identified');
verify($candidates[0]['name']==='Faxe Kondi','Faxe Kondi identified across separated price line');
verify($candidates[0]['price_ore']===6500,'65,- converts to 6500 øre');
verify($candidates[1]['price_ore']===2995,'29,95 converts to 2995 øre');
$import=MP_FlyerOCR::import_text($sample,'SuperBrugsen','2026-11-05');
verify(!is_wp_error($import),'One page OCR import works without Prej API key');
verify($import['imported']>=2,'Correct number imported');
verify(isset($GLOBALS['options']['mp_grocery_ocr_candidates_v1']),'Only candidates retained');
verify(!str_contains(json_encode($GLOBALS['options']),"24 x 33 cl\n"),'Raw OCR text not stored');
$matches=MP_FlyerOCR::match_for_item(12);
verify(!is_wp_error($matches),'Shopping item is found');
verify(count($matches['matches'])===1,'Faxe Kondi matches only one candidate');
verify($matches['matches'][0]['price_label']==='65,00 kr.','Displayed OCR price labelled');
verify($matches['matches'][0]['source']==='OCR-forslag – kontroller i avisen','Clearly unverified');
$again=MP_FlyerOCR::import_text($sample,'SuperBrugsen','2026-11-05');
verify($again['stored']===count($candidates),'Reimport deduplicated');
verify(is_wp_error(MP_FlyerOCR::import_text($sample,'Kvickly','2026-11-05')),
 'Only household selected chains allowed');
verify(is_wp_error(MP_FlyerOCR::import_text($sample,'SuperBrugsen','2026-11-01')),
 'Expired flyer date rejected');
MP_ShoppingDetails::$stores=['Netto'];
verify(MP_FlyerOCR::match_for_item(12)['matches']===[],'Family store filter enforced');
MP_ShoppingDetails::$stores=['SuperBrugsen'];
verify(is_wp_error(MP_FlyerOCR::match_for_item(199)),'Missing grocery item rejected');
verify(MP_FlyerOCR::clear()['cleared']===true,'Admin can clear candidate index');
verify(MP_FlyerOCR::match_for_item(12)['matches']===[],'Clear removes OCR candidates');
echo "Single-page OCR parsing, price extraction, indexing and family matching PASS\n";
