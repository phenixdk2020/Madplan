<?php
declare(strict_types=1);
/** Regression: identical freezer products must remain independently editable rows. */
define('ABSPATH','/mock-wordpress/');
define('ARRAY_A','ARRAY_A');

class WP_Error {
    public function __construct(public string $code, public string $message, public array $data=[]) {}
}
function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function sanitize_textarea_field(string $value): string { return trim(strip_tags($value)); }
function absint(mixed $value): int { return abs((int)$value); }
function get_current_user_id(): int { return 1; }
function current_time(string $format): string { return '2026-11-08 11:00:00'; }
function current_datetime(): DateTimeImmutable { return new DateTimeImmutable('2026-11-08'); }
class MP_Storage {
    public static function table(string $name): string { return 'wp_mp_'.$name; }
}
class MP_Barcode {
    public static function valid(string $barcode): bool { return true; }
    public static function remember(string $barcode,string $title,string $unit): void {}
}
class FreezerTestDB {
    public array $rows=[];
    public int $insert_id=0;
    public function insert(string $table,array $row): int {
        ++$this->insert_id;
        $this->rows[$this->insert_id]=['id'=>$this->insert_id]+$row;
        return 1;
    }
    public function prepare(string $sql,mixed ...$args): string {
        return vsprintf($sql,$args);
    }
    public function get_row(string $query,string $format): ?array {
        if (preg_match('/WHERE id=(\d+)/',$query,$match)) {
            return $this->rows[(int)$match[1]]??null;
        }
        return null;
    }
    public function get_results(string $query,string $format): array {
        return array_values($this->rows);
    }
    public function update(string $table,array $data,array $where): int {
        $id=(int)$where['id'];
        if (!isset($this->rows[$id])) return 0;
        $this->rows[$id]=array_merge($this->rows[$id],$data);
        return 1;
    }
    public function delete(string $table,array $where,array $types): int {
        $id=(int)$where['id'];
        if (!isset($this->rows[$id])) return 0;
        unset($this->rows[$id]);
        return 1;
    }
}
function check(bool $condition,string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
}
$GLOBALS['wpdb']=new FreezerTestDB();
require dirname(__DIR__).'/includes/class-mp-freezer.php';
$base=['title'=>'Pommes frites','barcode'=>'','quantity'=>'1','unit'=>'750 g',
       'location'=>'room2','frozen_at'=>'2026-11-08','best_before'=>''];
$whole=MP_Freezer::add($base+['notes'=>'']);
$opened=MP_Freezer::add($base+['notes'=>'Halv pose tilbage']);
check(!is_wp_error($whole) && !is_wp_error($opened),'Both products can be registered');
check($whole['id']!==$opened['id'],'Identical products use distinct IDs');

$inventory=MP_Freezer::all()['items'];
check(count($inventory)===2,'Same-name products are not merged in the inventory');
check($inventory[0]['notes']==='' && $inventory[1]['notes']==='Halv pose tilbage',
 'Each row retains its own comment');

$edited=MP_Freezer::update($opened['id'],$base+['notes'=>'Kun 1/4 pose tilbage']);
check(!is_wp_error($edited),'Updating the opened package succeeds');
$inventory=MP_Freezer::all()['items'];
check(count($inventory)===2,'Updating one package does not merge records');
check($inventory[0]['notes']==='' && $inventory[1]['notes']==='Kun 1/4 pose tilbage',
 'Editing one comment cannot alter the other product');

$deleted=MP_Freezer::remove($opened['id']);
check($deleted['deleted']===true,'Deleting opened package succeeds');
$remaining=MP_Freezer::all()['items'];
check(count($remaining)===1 && $remaining[0]['id']===$whole['id'],
 'Deleting one duplicate leaves the other intact');
echo "Freezer separate-records regression PASS\n";
