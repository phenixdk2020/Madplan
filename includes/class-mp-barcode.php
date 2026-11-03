<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Shared household barcode lookup and one-tap shopping additions.
 * Product names are never trusted without validation; scanning never purchases.
 * Order: local family mapping -> Prej GTIN scan -> Open Food Facts -> manual name.
 */
final class MP_Barcode {
    private const MEMORY='mp_shopping_barcode_catalog_v1';
    private const MAX_MEMORY=400;
    private const TTL_FOUND=30*DAY_IN_SECONDS;
    private const TTL_UNKNOWN=12*HOUR_IN_SECONDS;

    public static function valid(string $code): bool {
        if (!preg_match('/^(?:[0-9]{8}|[0-9]{12,14})$/D',$code)) { return false; }
        // GS1 check digit, including EAN-8, UPC-A, EAN-13 and GTIN-14.
        $sum=0; $weight=3;
        for ($i=strlen($code)-2;$i>=0;$i--) {
            $sum+=(int)$code[$i]*$weight;
            $weight=$weight===3?1:3;
        }
        return ((10-($sum%10))%10)===(int)substr($code,-1);
    }
    private static function memory(): array {
        $stored=get_option(self::MEMORY,[]);
        return is_array($stored)?$stored:[];
    }
    private static function local(string $code): array {
        $catalog=self::memory();
        $hit=$catalog[$code]??[];
        if (is_array($hit) && !empty($hit['title'])) {
            return [
                'title'=>sanitize_text_field((string)$hit['title']),
                'quantity_label'=>sanitize_text_field((string)($hit['quantity_label']??'')),
                'source'=>'Familien',
            ];
        }
        // Backwards compatibility with barcodes saved in the item editor.
        foreach (MP_ShoppingDetails::all_details() as $detail) {
            if (!is_array($detail) || (string)($detail['barcode']??'')!==$code) { continue; }
            $title=sanitize_text_field((string)($detail['title']??''));
            if ($title!=='' && $title!=='0') {
                return ['title'=>$title,'quantity_label'=>(string)($detail['package_size']??''),'source'=>'Familien'];
            }
        }
        return [];
    }
    public static function remember(string $code,string $title,string $quantity=''): void {
        if (!self::valid($code)) { return; }
        $title=sanitize_text_field(trim($title));
        $quantity=sanitize_text_field(trim($quantity));
        if ($title==='' || mb_strlen($title)>255 || mb_strlen($quantity)>80) { return; }
        $items=self::memory();
        $items[$code]=['title'=>$title,'quantity_label'=>$quantity,'last_seen'=>gmdate('c')];
        uasort($items,static fn($a,$b)=>strcmp((string)($b['last_seen']??''),(string)($a['last_seen']??'')));
        update_option(self::MEMORY,array_slice($items,0,self::MAX_MEMORY,true),false);
    }
    public static function product_from_prej(array $json): array {
        $product=is_array($json['product']??null)?$json['product']:$json;
        $title=sanitize_text_field((string)($product['name']??''));
        $quantity=sanitize_text_field((string)($product['quantity_label']??''));
        if ($quantity==='' && isset($product['quantity'],$product['unit']) &&
            is_scalar($product['quantity']) && is_scalar($product['unit'])) {
            $quantity=sanitize_text_field((string)$product['quantity'].' '.(string)$product['unit']);
        }
        return ['title'=>$title,'quantity_label'=>$quantity,'source'=>'Prej'];
    }
    public static function product_from_off(array $json): array {
        if ((int)($json['status']??0)!==1 || !is_array($json['product']??null)) { return []; }
        $p=$json['product'];
        $title=sanitize_text_field((string)($p['product_name_da']??''));
        if ($title==='') { $title=sanitize_text_field((string)($p['product_name']??'')); }
        $quantity=sanitize_text_field((string)($p['quantity']??''));
        return ['title'=>$title,'quantity_label'=>$quantity,'source'=>'Open Food Facts'];
    }
    private static function fetch(string $code): array {
        $cache='mp_barcode_v1_'.md5($code);
        $cached=get_transient($cache);
        if (is_array($cached)) { return $cached; }
        $found=[];
        if (MP_Offers::configured() && MP_Offers::reserve_barcode_call()) {
            $response=wp_safe_remote_get('https://api.prej.app/v1/scan/'.$code,[
                'timeout'=>10,'redirection'=>0,
                'headers'=>['Accept'=>'application/json',
                    'Authorization'=>'Bearer '.MP_Offers::api_key()],
            ]);
            if (!is_wp_error($response) && (int)wp_remote_retrieve_response_code($response)===200) {
                $body=json_decode((string)wp_remote_retrieve_body($response),true);
                if (is_array($body)) { $found=self::product_from_prej($body); }
            }
        }
        if (empty($found['title'])) {
            $response=wp_safe_remote_get(
                'https://world.openfoodfacts.org/api/v2/product/'.$code.
                '?fields=code,status,product_name,product_name_da,quantity',[
                'timeout'=>10,'redirection'=>0,
                'headers'=>['Accept'=>'application/json',
                    'User-Agent'=>'Hangar18-Madplan/0.1.25 (https://hangar18.dk)'],
            ]);
            if (!is_wp_error($response) && (int)wp_remote_retrieve_response_code($response)===200) {
                $body=json_decode((string)wp_remote_retrieve_body($response),true);
                if (is_array($body)) { $found=self::product_from_off($body); }
            }
        }
        $result=[
            'found'=>!empty($found['title']),
            'title'=>sanitize_text_field((string)($found['title']??'')),
            'quantity_label'=>sanitize_text_field((string)($found['quantity_label']??'')),
            'source'=>sanitize_text_field((string)($found['source']??'Ukendt')),
        ];
        if (mb_strlen($result['title'])>255) { $result['title']='';$result['found']=false; }
        if (mb_strlen($result['quantity_label'])>80) { $result['quantity_label']=''; }
        set_transient($cache,$result,$result['found']?self::TTL_FOUND:self::TTL_UNKNOWN);
        return $result;
    }
    public static function lookup(string $code): array|WP_Error {
        if (!self::valid($code)) {
            return new WP_Error('mp_barcode_invalid',
                'Stregkoden skal være EAN-8, UPC-A, EAN-13 eller GTIN-14 med korrekt kontrolciffer.',
                ['status'=>400]);
        }
        $item=self::local($code);
        if (empty($item['title'])) { $item=self::fetch($code); }
        return [
            'barcode'=>$code,
            'found'=>!empty($item['title']),
            'title'=>(string)($item['title']??''),
            'quantity_label'=>(string)($item['quantity_label']??''),
            'source'=>(string)($item['source']??'Ukendt'),
        ];
    }
    public static function add(string $code,string $manual_title='',string $manual_quantity=''): array|WP_Error {
        $lookup=self::lookup($code);
        if (is_wp_error($lookup)) { return $lookup; }
        $manual_title=sanitize_text_field(trim($manual_title));
        $manual_quantity=sanitize_text_field(trim($manual_quantity));
        if (mb_strlen($manual_title)>255 || mb_strlen($manual_quantity)>80) {
            return new WP_Error('mp_barcode_data','Varenavn eller mængde er for langt.',['status'=>400]);
        }
        $title=$manual_title!==''?$manual_title:$lookup['title'];
        $quantity=$manual_quantity!==''?$manual_quantity:$lookup['quantity_label'];
        if ($title==='') {
            return ['added'=>false,'needs_name'=>true,'barcode'=>$code,
                    'message'=>'Ukendt stregkode. Giv varen et navn én gang.'];
        }
        global $wpdb;
        $table=MP_Storage::table('shopping');
        // Prevent repeatedly scanning a product from creating duplicate entries.
        // Barcode associations are stored in item details and the family catalog.
        foreach (MP_ShoppingDetails::all_details() as $itemId=>$detail) {
            if (!is_array($detail) || (string)($detail['barcode']??'')!==$code) { continue; }
            $row=$wpdb->get_row($wpdb->prepare(
                "SELECT id,title,checked FROM $table WHERE id=%d",absint($itemId)
            ),ARRAY_A);
            if ($row && (int)$row['checked']===0) {
                return ['added'=>false,'existing'=>true,'id'=>(int)$row['id'],
                        'title'=>(string)$row['title'],'barcode'=>$code];
            }
        }
        $inserted=$wpdb->insert($table,[
            'title'=>$title,'amount'=>$quantity,'checked'=>0,'created_at'=>current_time('mysql'),
        ],['%s','%s','%d','%s']);
        if ($inserted===false) {
            return new WP_Error('mp_barcode_save','Varen kunne ikke tilføjes.',['status'=>500]);
        }
        $id=(int)$wpdb->insert_id;
        $saved=MP_ShoppingDetails::save_item($id,[
            'title'=>$title,'store'=>MP_ShoppingStores::preferred($title),
            'quantity'=>1,'package_size'=>$quantity,'price'=>'','notes'=>'',
            'favorite'=>false,'barcode'=>$code,
        ]);
        if (is_wp_error($saved)) {
            $wpdb->delete($table,['id'=>$id],['%d']);
            return $saved;
        }
        self::remember($code,$title,$quantity);
        return [
            'added'=>true,'existing'=>false,'id'=>$id,
            'title'=>$title,'barcode'=>$code,'quantity'=>$quantity,
            'source'=>$manual_title!==''?'Familien':$lookup['source'],
        ];
    }
}
