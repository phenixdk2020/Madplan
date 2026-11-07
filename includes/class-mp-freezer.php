<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Shared family freezer inventory. Seven physical positions:
 * left-to-right rooms 1-4 and baskets 1-3 (no basket 4).
 */
final class MP_Freezer {
    private const SCHEMA = 'mp_freezer_schema_v1';
    private const LOCATIONS = [
        'room1'=>'Rum 1','basket1'=>'Kurv 1',
        'room2'=>'Rum 2','basket2'=>'Kurv 2',
        'room3'=>'Rum 3','basket3'=>'Kurv 3',
        'room4'=>'Rum 4',
    ];

    public static function locations(): array { return self::LOCATIONS; }

    public static function install(): void {
        if (get_option(self::SCHEMA) === '1') { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table=MP_Storage::table('freezer');
        $charset=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            barcode varchar(14) NOT NULL DEFAULT '',
            quantity smallint unsigned NOT NULL DEFAULT 1,
            unit varchar(80) NOT NULL DEFAULT 'stk',
            location varchar(12) NOT NULL,
            frozen_at date NOT NULL,
            best_before date DEFAULT NULL,
            source_shopping_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY location (location),
            KEY barcode (barcode)
        ) $charset;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table) {
            update_option(self::SCHEMA,'1',false);
        }
    }

    private static function date(string $value): bool {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d')===$value;
    }

    private static function validate(array $input): array|WP_Error {
        $title=sanitize_text_field(trim((string)($input['title']??'')));
        $barcode=trim((string)($input['barcode']??''));
        $qty=$input['quantity']??'1';
        $unit=sanitize_text_field(trim((string)($input['unit']??'stk')));
        $location=(string)($input['location']??'');
        $frozen=(string)($input['frozen_at']??current_datetime()->format('Y-m-d'));
        $best=trim((string)($input['best_before']??''));
        if ($title==='' || mb_strlen($title)>255) {
            return new WP_Error('mp_freezer_title','Angiv et varenavn på højst 255 tegn.',['status'=>400]);
        }
        if ($barcode!=='' && !MP_Barcode::valid($barcode)) {
            return new WP_Error('mp_freezer_barcode','Stregkoden skal have gyldigt kontrolciffer.',['status'=>400]);
        }
        if (!is_scalar($qty) || !preg_match('/^[0-9]{1,3}$/',(string)$qty)
            || (int)$qty<1 || (int)$qty>999) {
            return new WP_Error('mp_freezer_quantity','Antal skal være 1-999.',['status'=>400]);
        }
        if (mb_strlen($unit)>80) {
            return new WP_Error('mp_freezer_unit','Enheden er for lang.',['status'=>400]);
        }
        if (!array_key_exists($location,self::LOCATIONS)) {
            return new WP_Error('mp_freezer_location','Vælg en af fryserens syv placeringer.',['status'=>400]);
        }
        if (!self::date($frozen) || ($best!=='' && !self::date($best))) {
            return new WP_Error('mp_freezer_date','Angiv en gyldig dato.',['status'=>400]);
        }
        return ['title'=>$title,'barcode'=>$barcode,'quantity'=>(int)$qty,'unit'=>$unit,
            'location'=>$location,'frozen_at'=>$frozen,
            'best_before'=>$best===''?null:$best];
    }

    public static function all(): array {
        global $wpdb;
        $table=MP_Storage::table('freezer');
        $rows=$wpdb->get_results("SELECT id,title,barcode,quantity,unit,location,frozen_at,best_before,source_shopping_id
            FROM $table ORDER BY location, title, id LIMIT 1500",ARRAY_A);
        return ['locations'=>self::LOCATIONS,'items'=>is_array($rows)?$rows:[]];
    }

    public static function add(array $input): array|WP_Error {
        $item=self::validate($input);
        if (is_wp_error($item)) { return $item; }
        global $wpdb;
        $item['source_shopping_id']=absint($input['source_shopping_id']??0);
        $item['created_at']=current_time('mysql');
        $item['updated_at']=current_time('mysql');
        $item['updated_by']=get_current_user_id();
        $ok=$wpdb->insert(MP_Storage::table('freezer'),$item);
        if (!$ok) { return new WP_Error('mp_freezer_write','Kunne ikke gemme varen i fryseren.',['status'=>500]); }
        if ($item['barcode']!=='') { MP_Barcode::remember($item['barcode'],$item['title'],$item['unit']); }
        return ['saved'=>true,'id'=>(int)$wpdb->insert_id];
    }

    public static function update(int $id,array $input): array|WP_Error {
        global $wpdb;
        $table=MP_Storage::table('freezer');
        $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id),ARRAY_A);
        if (!$old) { return new WP_Error('mp_freezer_missing','Varen findes ikke.',['status'=>404]); }
        $item=self::validate($input);
        if (is_wp_error($item)) { return $item; }
        $item['updated_at']=current_time('mysql');
        $item['updated_by']=get_current_user_id();
        $ok=$wpdb->update($table,$item,['id'=>$id]);
        if ($ok===false) { return new WP_Error('mp_freezer_update','Kunne ikke opdatere varen.',['status'=>500]); }
        if ($item['barcode']!=='') { MP_Barcode::remember($item['barcode'],$item['title'],$item['unit']); }
        return ['saved'=>true,'id'=>$id];
    }

    public static function remove(int $id): array|WP_Error {
        global $wpdb;
        $deleted=$wpdb->delete(MP_Storage::table('freezer'),['id'=>$id],['%d']);
        if ($deleted===false) { return new WP_Error('mp_freezer_delete','Kunne ikke slette varen.',['status'=>500]); }
        if (!$deleted) { return new WP_Error('mp_freezer_missing','Varen findes ikke.',['status'=>404]); }
        return ['deleted'=>true];
    }

    public static function from_shopping(int $id,string $location): array|WP_Error {
        global $wpdb;
        $table=MP_Storage::table('shopping');
        $row=$wpdb->get_row($wpdb->prepare(
            "SELECT id,title,amount,checked FROM $table WHERE id=%d",$id),ARRAY_A);
        if (!$row || !(int)$row['checked']) {
            return new WP_Error('mp_freezer_shopping','Vælg en købt vare fra indkøbslisten.',['status'=>400]);
        }
        $details=MP_ShoppingDetails::for_item($row);
        return self::add([
            'title'=>$row['title'],
            'barcode'=>$details['barcode']??'',
            'quantity'=>$details['quantity']??1,
            'unit'=>$details['package_size']?:'stk',
            'location'=>$location,
            'frozen_at'=>current_datetime()->format('Y-m-d'),
            'source_shopping_id'=>$id,
        ]);
    }

    public static function to_shopping(int $id): array|WP_Error {
        global $wpdb;
        $table=MP_Storage::table('freezer');
        $row=$wpdb->get_row($wpdb->prepare(
            "SELECT title,barcode,quantity,unit FROM $table WHERE id=%d",$id),ARRAY_A);
        if (!$row) { return new WP_Error('mp_freezer_missing','Varen findes ikke.',['status'=>404]); }
        $shopping=MP_Storage::table('shopping');
        $ok=$wpdb->insert($shopping,[
            'title'=>$row['title'],'amount'=>(string)$row['quantity'].' '.$row['unit'],
            'checked'=>0,'created_at'=>current_time('mysql'),
        ],['%s','%s','%d','%s']);
        if (!$ok) { return new WP_Error('mp_freezer_shopping','Kunne ikke tilføje varen til indkøbslisten.',['status'=>500]); }
        $shopping_id=(int)$wpdb->insert_id;
        $details=MP_ShoppingDetails::save_item($shopping_id,[
            'title'=>$row['title'],'store'=>MP_ShoppingStores::preferred($row['title']),
            'quantity'=>(int)$row['quantity'],'package_size'=>$row['unit'],
            'price'=>'','notes'=>'','favorite'=>false,'barcode'=>$row['barcode'],
        ]);
        if (is_wp_error($details)) {
            $wpdb->delete($shopping,['id'=>$shopping_id],['%d']);
            return $details;
        }
        return ['saved'=>true,'shopping_id'=>$shopping_id];
    }
}
