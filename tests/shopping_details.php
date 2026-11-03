<?php
declare(strict_types=1);
/** Shared shopping item editor and safe clear-purchased regression. */
define('ABSPATH','/mock/');
define('ARRAY_A','ARRAY_A');
class WP_Error {
    public function __construct(public string $code,public string $message,public array $data=[]) {}
}
function is_wp_error($x): bool{return $x instanceof WP_Error;}
function absint($v): int{return abs((int)$v);}
function sanitize_text_field($v): string{return trim(strip_tags((string)$v));}
function sanitize_textarea_field($v): string{return sanitize_text_field($v);}
function rest_sanitize_boolean($v): bool{return (bool)$v;}
function sanitize_title($name): string{
    return trim(preg_replace('/[^\p{L}\p{N}]+/u','-',mb_strtolower(trim((string)$name),'UTF-8')),'-');
}
function get_current_user_id(): int {return $GLOBALS['user_id'];}
function get_option($n,$default=false){return array_key_exists($n,$GLOBALS['opts'])?$GLOBALS['opts'][$n]:$default;}
function update_option($n,$value,$autoload=false): bool{$GLOBALS['opts'][$n]=$value;return true;}
function current_user_can($cap): bool{return false;}
function is_user_logged_in(): bool{return true;}
class WP_REST_Request implements ArrayAccess {
    private array $params;
    public function __construct(array $params){$this->params=$params;}
    public function get_param(string $n){return $this->params[$n]??null;}
    public function get_file_params(){return [];}
    public function offsetExists($offset): bool {return isset($this->params[$offset]);}
    public function offsetGet($offset): mixed {return $this->params[$offset]??null;}
    public function offsetSet($offset,$value): void {$this->params[$offset]=$value;}
    public function offsetUnset($offset): void {unset($this->params[$offset]);}
}
class MP_Storage {public static function table(string $t): string {return 'wp_mp_'.$t;}}
class MP_Offers {public static function configured(): bool{return false;}}
class FakeDb {
    public array $rows=[
      1=>['id'=>1,'title'=>'Kaffe','amount'=>'1 stk','checked'=>1],
      2=>['id'=>2,'title'=>'Pommes','amount'=>'2','checked'=>0],
      3=>['id'=>3,'title'=>'Mælk','amount'=>'2 l','checked'=>1],
      4=>['id'=>4,'title'=>'Brød','amount'=>'1','checked'=>0],
    ];
    public function prepare(string $query,...$args): string {return str_replace('%d',(string)$args[0],$query);}
    public function get_row(string $q,$format): ?array{
       preg_match('/id=([0-9]+)/',$q,$matches);
       return $this->rows[(int)($matches[1]??0)]??null;
    }
    public function get_col(string $q): array {
       return array_keys(array_filter($this->rows,static fn($r)=>(int)$r['checked']===1));
    }
    public function get_results(string $q,$format): array {
       return array_values($this->rows);
    }
    public function update(string $table,array $changes,array $where,...$other): int{
       $id=(int)$where['id'];
       if(!isset($this->rows[$id]))return 0;
       $this->rows[$id]=array_merge($this->rows[$id],$changes);
       return 1;
    }
    public function delete(string $table,array $where,...$rest): int{
       $id=(int)$where['id'];
       if(!isset($this->rows[$id]))return 0;
       if(isset($where['checked']) && (int)$this->rows[$id]['checked'] !== (int)$where['checked'])return 0;
       unset($this->rows[$id]);return 1;
    }
}
$GLOBALS['user_id']=1;
$GLOBALS['opts']=[];
$GLOBALS['wpdb']=new FakeDb();
function expect_true(bool $v,string $m): void{if(!$v)throw new RuntimeException('FAIL: '.$m);}
require dirname(__DIR__).'/includes/class-mp-shopping-stores.php';
require dirname(__DIR__).'/includes/class-mp-barcode.php';
require dirname(__DIR__).'/includes/class-mp-shopping-details.php';
require dirname(__DIR__).'/includes/class-mp-shopping-history.php';
require dirname(__DIR__).'/includes/class-mp-rest.php';

expect_true(MP_ShoppingDetails::for_item($GLOBALS['wpdb']->rows[2])['quantity']===2,
    'Existing textual amount becomes useful initial count');
expect_true(MP_ShoppingDetails::selected_stores()===MP_ShoppingDetails::available_offer_stores(),
    'Initial household offer search covers all selected chains');
$save=MP_ShoppingDetails::save_stores(['Netto','Føtex']);
expect_true(!is_wp_error($save) && $save['shared'],'Household store selection saved');
expect_true(is_wp_error(MP_ShoppingDetails::save_stores(['Unknown'])),
    'Unknown offer store rejected');
$GLOBALS['user_id']=2;
expect_true(MP_ShoppingDetails::selected_stores()===['Føtex','Netto'],
    'Offer stores remain common to another family account');

$r=new WP_REST_Request([
    'id'=>2,'title'=>'Pommes med krydderi','store'=>'Føtex','quantity'=>2,
    'package_size'=>'500 g','price'=>'24,95','notes'=>'Tjek frostvarer',
    'barcode'=>'5701234567899','favorite'=>true
]);
$saved=MP_REST::save_shopping_details($r);
expect_true(!is_wp_error($saved) && $saved['saved'],'Subscriber can change shopping details');
$item=MP_ShoppingDetails::for_item($GLOBALS['wpdb']->rows[2]);
expect_true($item['quantity']===2 && $item['package_size']==='500 g','Quantity and size saved');
expect_true($item['price_ore']===2495 && $item['favorite'],'Price and favorite saved');
expect_true($item['notes']==='Tjek frostvarer' && $item['barcode']==='5701234567899','Note and barcode saved');
expect_true($GLOBALS['wpdb']->rows[2]['title']==='Pommes med krydderi','Title edited in original shopping row');
expect_true(MP_ShoppingStores::preferred('Pommes med krydderi')==='Føtex','Store category grouped after save');
expect_true(count(MP_REST::shopping())===4,'Existing items returned unchanged');

$bad=MP_ShoppingDetails::save_item(2,[
 'quantity'=>0,'package_size'=>'','price'=>'','notes'=>'','barcode'=>'','favorite'=>false
]);
expect_true(is_wp_error($bad),'Quantity zero rejected');
expect_true(is_wp_error(MP_ShoppingDetails::save_item(900,[
 'quantity'=>1
])),'Cannot edit a missing row');

MP_ShoppingDetails::save_item(1,['title'=>'Kaffe','store'=>'Alle','quantity'=>1,
 'package_size'=>'','price'=>'','notes'=>'','barcode'=>'','favorite'=>false]);
MP_ShoppingDetails::save_item(3,['title'=>'Mælk','store'=>'Alle','quantity'=>1,
 'package_size'=>'','price'=>'','notes'=>'','barcode'=>'','favorite'=>false]);
$GLOBALS['user_id']=1;
$cleared=MP_REST::clear_purchased();
expect_true($cleared['saved'] && $cleared['deleted']===2,'Exactly two checked items removed');
expect_true(array_keys($GLOBALS['wpdb']->rows)===[2,4],'All unchecked items preserved');
expect_true(count(MP_REST::shopping())===2,'Shared shopping page only lists pending items');
$all=MP_ShoppingDetails::all_details();
expect_true(isset($all['2']) && !isset($all['1']) && !isset($all['3']),
 'Orphan checked details removed without erasing unchecked details');
expect_true(MP_ShoppingDetails::selected_stores()===['Føtex','Netto'],
 'Clear purchased preserves household offer filters');
expect_true(MP_ShoppingStores::preferred('Pommes med krydderi')==='Føtex',
 'Clear purchased preserves favorite store mappings');
$again=MP_REST::clear_purchased();
expect_true($again['deleted']===0 && array_keys($GLOBALS['wpdb']->rows)===[2,4],
 'Repeated clearing cannot delete pending items');
echo "Shopping item details, shared stores, safe clear-purchased PASS\n";
