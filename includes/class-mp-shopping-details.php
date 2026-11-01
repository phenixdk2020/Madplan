<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/** Shared household shopping item details and chosen offer-search stores. */
final class MP_ShoppingDetails {
    private const DETAILS='mp_shopping_item_details_v1';
    private const OFFER_STORES='mp_shopping_offer_stores_v1';

    public static function available_offer_stores(): array {
        return ['Føtex','Bilka','BilkaToGo','Netto','Rema 1000','Lidl',
            '365discount','SuperBrugsen','Kvickly','Meny','Spar','Min Købmand',
            'Brugsen','Nemlig','Løvbjerg','Wolt Market','Motatos'];
    }
    public static function selected_stores(): array {
        $saved=get_option(self::OFFER_STORES,null);
        if ($saved===null) { return self::available_offer_stores(); }
        if (!is_array($saved)) { return []; }
        return array_values(array_intersect(self::available_offer_stores(),$saved));
    }
    public static function save_stores(array $stores): array|WP_Error {
        if (count($stores)>count(self::available_offer_stores())) {
            return new WP_Error('mp_stores_count','For mange valgte butikker.',['status'=>400]);
        }
        foreach ($stores as $store) {
            if (!is_string($store) || !in_array($store,self::available_offer_stores(),true)) {
                return new WP_Error('mp_store_invalid','Ukendt butik i tilbudssøgningen.',['status'=>400]);
            }
        }
        $saved=array_values(array_unique($stores));
        update_option(self::OFFER_STORES,$saved,false);
        return ['saved'=>true,'shared'=>true,'selected'=>$saved];
    }
    public static function all_details(): array {
        $saved=get_option(self::DETAILS,[]);
        return is_array($saved)?$saved:[];
    }
    public static function for_item(array $row): array {
        $saved=self::all_details();
        $detail=$saved[(string)($row['id']??'')]??[];
        if (!is_array($detail)) { $detail=[]; }
        $amount=trim((string)($row['amount']??''));
        $quantity=1;
        if (preg_match('/^\s*(\d{1,3})(?:\s|$)/u',$amount,$m)) {
            $quantity=max(1,(int)$m[1]);
        } elseif (preg_match('/^\d{1,3}$/',$amount)) {
            $quantity=max(1,(int)$amount);
        }
        return [
            'quantity'=>(int)($detail['quantity']??$quantity),
            'package_size'=>(string)($detail['package_size']??''),
            'price_ore'=>$detail['price_ore']??null,
            'notes'=>(string)($detail['notes']??''),
            'favorite'=>!empty($detail['favorite']),
            'barcode'=>(string)($detail['barcode']??''),
            'photo_url'=>(string)($detail['photo_url']??''),
            'has_details'=>array_key_exists((string)($row['id']??''),$saved),
        ];
    }
    public static function validate(array $input): array|WP_Error {
        $qty=$input['quantity']??null;
        if (!is_scalar($qty) || !preg_match('/^[0-9]{1,3}$/',(string)$qty) ||
            (int)$qty<1 || (int)$qty>999) {
            return new WP_Error('mp_quantity','Antal skal være mellem 1 og 999.',['status'=>400]);
        }
        $package=sanitize_text_field((string)($input['package_size']??''));
        if (mb_strlen($package)>80) {
            return new WP_Error('mp_package','Pakningsstørrelsen er for lang.',['status'=>400]);
        }
        $notes=sanitize_textarea_field((string)($input['notes']??''));
        if (mb_strlen($notes)>1000) {
            return new WP_Error('mp_notes','Noten må højst være 1.000 tegn.',['status'=>400]);
        }
        $barcode=trim((string)($input['barcode']??''));
        if ($barcode!=='' && !preg_match('/^(?:\d{8}|\d{12}|\d{13}|\d{14})$/',$barcode)) {
            return new WP_Error('mp_barcode','Stregkode skal være 8, 12, 13 eller 14 cifre.',['status'=>400]);
        }
        $raw=trim((string)($input['price']??''));
        $price=null;
        if ($raw!=='') {
            $raw=str_replace([',',' '],['.',''],$raw);
            if (!preg_match('/^\d{1,6}(?:\.\d{1,2})?$/',$raw)) {
                return new WP_Error('mp_price','Pris skal være et beløb som 12,95.',['status'=>400]);
            }
            $price=(int)round((float)$raw*100);
            if ($price>100000000) {
                return new WP_Error('mp_price','Prisen er for høj.',['status'=>400]);
            }
        }
        $favorite=$input['favorite']??false;
        return ['quantity'=>(int)$qty,'package_size'=>$package,'price_ore'=>$price,
            'notes'=>$notes,'barcode'=>$barcode,'favorite'=>rest_sanitize_boolean($favorite)];
    }
    private static function row(int $id): ?array {
        if ($id<1) { return null; }
        global $wpdb;
        $table=MP_Storage::table('shopping');
        $row=$wpdb->get_row($wpdb->prepare(
            "SELECT id,title,amount,checked FROM $table WHERE id=%d",$id
        ),ARRAY_A);
        return is_array($row)?$row:null;
    }
    public static function save_item(int $id,array $input): array|WP_Error {
        $row=self::row($id);
        if (!$row) {
            return new WP_Error('mp_item_missing','Varen findes ikke længere.',['status'=>404]);
        }
        $title=sanitize_text_field((string)($input['title']??$row['title']));
        if ($title==='' || mb_strlen($title)>255) {
            return new WP_Error('mp_title','Varenavn skal være mellem 1 og 255 tegn.',['status'=>400]);
        }
        $store=(string)($input['store']??MP_ShoppingStores::preferred($title));
        if (!in_array($store,MP_ShoppingStores::stores(),true)) {
            return new WP_Error('mp_store','Vælg en butik fra listen.',['status'=>400]);
        }
        $validated=self::validate($input);
        if (is_wp_error($validated)) { return $validated; }
        global $wpdb;
        $table=MP_Storage::table('shopping');
        if ($title!==$row['title']) {
            if ($wpdb->update($table,['title'=>$title],['id'=>$id],['%s'],['%d'])===false) {
                return new WP_Error('mp_save_title','Varenavnet kunne ikke gemmes.',['status'=>500]);
            }
            $row['title']=$title;
        }
        $savedStore=MP_ShoppingStores::save($title,$store);
        if (is_wp_error($savedStore)) { return $savedStore; }
        $all=self::all_details();
        $old=self::for_item($row);
        $validated['photo_url']=$old['photo_url'];
        $all[(string)$id]=$validated;
        update_option(self::DETAILS,$all,false);
        return ['saved'=>true,'details'=>self::for_item($row)];
    }
    public static function attach_photo(int $id,array $file): array|WP_Error {
        $row=self::row($id);
        if (!$row) {
            return new WP_Error('mp_item_missing','Varen findes ikke.',['status'=>404]);
        }
        if (empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name']) ||
            (int)($file['size']??0)<1 || (int)$file['size']>3*1024*1024) {
            return new WP_Error('mp_photo_size','Vælg et billede under 3 MB.',['status'=>400]);
        }
        $mimes=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
        $type=wp_check_filetype_and_ext((string)$file['tmp_name'],(string)($file['name']??''),$mimes);
        if (empty($type['type']) || !in_array($type['type'],$mimes,true) ||
            @getimagesize((string)$file['tmp_name'])===false) {
            return new WP_Error('mp_photo_type','Brug JPG, PNG eller WebP.',['status'=>400]);
        }
        require_once ABSPATH.'wp-admin/includes/file.php';
        $result=wp_handle_upload($file,['test_form'=>false,'mimes'=>$mimes]);
        if (!empty($result['error']) || empty($result['url'])) {
            return new WP_Error('mp_photo_upload','Kunne ikke gemme billedet.',['status'=>500]);
        }
        $all=self::all_details();
        $current=self::for_item($row);
        $current['photo_url']=esc_url_raw((string)$result['url']);
        unset($current['has_details']);
        $all[(string)$id]=$current;
        update_option(self::DETAILS,$all,false);
        return ['saved'=>true,'photo_url'=>$current['photo_url']];
    }
    public static function delete_item(int $id): void {
        $all=self::all_details();
        if (isset($all[(string)$id])) {
            unset($all[(string)$id]);
            update_option(self::DETAILS,$all,false);
        }
    }
}
