<?php
declare(strict_types=1);
define('ABSPATH','/mock/');
define('ARRAY_A','ARRAY_A');
class WP_Error {public function __construct(public string $code,public string $message,public array $data=[]){}}
function is_wp_error($v): bool {return $v instanceof WP_Error;}
function absint($v): int {return abs((int)$v);}
function sanitize_text_field($v): string {return trim(strip_tags((string)$v));}
function get_current_user_id(): int {return $GLOBALS['uid'];}
function get_option($key,$default=false) {return array_key_exists($key,$GLOBALS['opts'])?$GLOBALS['opts'][$key]:$default;}
function update_option($key,$value,$unused=false): bool {$GLOBALS['opts'][$key]=$value;return true;}
$GLOBALS['uid']=1;$GLOBALS['opts']=[];
class MP_Storage {public static function table(string $name): string{return 'wp_mp_'.$name;}}
class FakeDb {
 public array $rows=[
  1=>['id'=>1,'title'=>'Hakket oksekød','checked'=>1],
  2=>['id'=>2,'title'=>'Kaffe','checked'=>0],
  3=>['id'=>3,'title'=>'Faxe Kondi','checked'=>1],
 ];
 public function get_results(string $sql,$format): array {
  return array_values(array_filter($this->rows,static fn($r)=>(int)$r['checked']===1));
 }
 public function get_col(string $sql): array {
  return array_keys(array_filter($this->rows,static fn($r)=>(int)$r['checked']===1));
 }
 public function prepare(string $sql,...$args): string {
  return str_replace('%d',(string)$args[0],$sql);
 }
 public function get_row(string $sql,$format): ?array {
  if(!preg_match('/id=([0-9]+)/',$sql,$match))return null;
  $row=$this->rows[(int)$match[1]]??null;
  if($row!==null && str_contains($sql,'checked=1') && !$row['checked'])return null;
  return $row;
 }
 public function update(string $table,array $changes,array $where,...$extra): int|false {
  $id=$where['id'];
  if(!isset($this->rows[$id]))return false;
  $this->rows[$id]=array_merge($this->rows[$id],$changes);return 1;
 }
 public function delete(string $table,array $where,...$extra): int|false {
  $id=$where['id'];
  if(!isset($this->rows[$id]))return 0;
  if(isset($where['checked']) && (int)$where['checked']!==(int)$this->rows[$id]['checked'])return 0;
  unset($this->rows[$id]);return 1;
 }
}
$GLOBALS['wpdb']=new FakeDb();
require dirname(__DIR__).'/includes/class-mp-shopping-history.php';
function check(bool $v,string $why): void {if(!$v)throw new RuntimeException('FAIL: '.$why);}
$initial=MP_ShoppingHistory::suggestions();
check(count($initial)===2,'Seed only purchased historical rows on first use');
check(array_column($initial,'title')===['Hakket oksekød','Faxe Kondi'],'Seed existing bought items');
check(isset($GLOBALS['opts']['mp_shopping_purchased_history_v1']),'Persist global option');
$GLOBALS['uid']=2;
MP_ShoppingHistory::remember('Hakket oksekød');
MP_ShoppingHistory::remember('hakket OKSEKØD');
check(count(MP_ShoppingHistory::suggestions())===2,'Case-insensitive deduplication');
check(MP_ShoppingHistory::suggestions()[0]['title']==='hakket OKSEKØD','Newest spelling wins');
MP_ShoppingHistory::remember('<script>alert(1)</script> Bananer');
check(!str_contains(MP_ShoppingHistory::suggestions()[0]['title'],'<script>'),'History sanitized');
check(count(MP_ShoppingHistory::suggestions())===3,'Newly purchased item becomes available to all users');
unset($GLOBALS['wpdb']->rows[1],$GLOBALS['wpdb']->rows[3]);
$GLOBALS['uid']=1;
check(count(MP_ShoppingHistory::suggestions())===3,'History survives clearing shopping rows');
check(array_filter(MP_ShoppingHistory::suggestions(),static fn($x)=>mb_strtolower($x['title'])==='hakket oksekød')!==[],'Old purchase still suggested');
check(array_column(MP_ShoppingHistory::suggestions(),'title')!==['Kaffe'],'Unchecked never seeded');
echo "Shared purchased-item autocomplete history PASS\n";
