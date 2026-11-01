<?php
declare(strict_types=1);
define('ABSPATH','/fake/');
class WP_Error { public function __construct(public string $code,public string $message,public array $data=[]) {} }
$GLOBALS['user']=101;
$GLOBALS['options']=[];
function sanitize_title($title) {
    $name=mb_strtolower(trim((string)$title),'UTF-8');
    return preg_replace('/[^\p{L}\p{N}]+/u','-',trim($name));
}
function get_option($name,$default=false) {
    return array_key_exists($name,$GLOBALS['options'])?$GLOBALS['options'][$name]:$default;
}
function update_option($name,$value,$autoload=false): bool {
    $GLOBALS['options'][$name]=$value; return true;
}
function expect(bool $ok,string $description) {
    if (!$ok)throw new RuntimeException('FAIL: '.$description);
}
require dirname(__DIR__).'/includes/class-mp-shopping-stores.php';
expect(MP_ShoppingStores::preferred('Hakket oksekød')==='Alle','New items initially unassigned');
expect(in_array('Føtex',MP_ShoppingStores::stores(),true),'Føtex available');
expect(in_array('Tilbud',MP_ShoppingStores::stores(),true),'Offer-watch category available');
$res=MP_ShoppingStores::save('Hakket oksekød','Føtex');
expect($res['saved']===true && $res['shared']===true,'Store saved for family');
expect(MP_ShoppingStores::preferred('Hakket oksekød')==='Føtex','Same item is placed in Føtex');
$GLOBALS['user']=202;
expect(MP_ShoppingStores::preferred('Hakket oksekød')==='Føtex','Other family users see same store');
expect(MP_ShoppingStores::preferred('Mælk')==='Alle','Unrelated item remains unassigned');
expect(MP_ShoppingStores::save('Hakket oksekød','Tilbud')['store']==='Tilbud','Can mark item for offers');
expect(MP_ShoppingStores::preferred('Hakket oksekød')==='Tilbud','Offer category remembered');
expect(MP_ShoppingStores::save('Hakket oksekød','Føtex')['store']==='Føtex','Can return to habitual store');
$bad=MP_ShoppingStores::save('Hakket oksekød','Unknown');
expect($bad instanceof WP_Error,'Unknown stores rejected');
expect(MP_ShoppingStores::preferred('Hakket oksekød')==='Føtex','Invalid stores do not overwrite preferences');
MP_ShoppingStores::save('Hakket oksekød','Alle');
expect(MP_ShoppingStores::preferred('Hakket oksekød')==='Alle','Reset to all works');
expect(($GLOBALS['options']['mp_shopping_favourite_stores_v1']??null)===[],'Reset removes stored preference');
echo "Household preferred-store mapping PASS\n";
