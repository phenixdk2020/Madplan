<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/** Shared household's habitual grocery-store mapping, keyed by product title. */
final class MP_ShoppingStores {
    private const OPTION='mp_shopping_favourite_stores_v1';
    public static function stores(): array {
        return ['Alle','Tilbud','Føtex','Bilka','Netto','Rema 1000','Lidl',
            '365discount','SuperBrugsen','Kvickly','Meny','Spar',
            'Min Købmand','Brugsen','Nemlig','Løvbjerg','Apotek','Andre'];
    }
    private static function key(string $name): string {
        return sanitize_title($name);
    }
    public static function preferred(string $name): string {
        $saved=get_option(self::OPTION,[]);
        if (!is_array($saved)) { return 'Alle'; }
        $value=(string)($saved[self::key($name)]??'Alle');
        return in_array($value,self::stores(),true)?$value:'Alle';
    }
    public static function save(string $title,string $store): array|WP_Error {
        if (!in_array($store,self::stores(),true)) {
            return new WP_Error('mp_shopping_store_invalid','Vælg en butik fra listen.',['status'=>400]);
        }
        $key=self::key($title);
        if (!$key) {
            return new WP_Error('mp_shopping_name_invalid','Varen skal have et gyldigt navn.',['status'=>400]);
        }
        $saved=get_option(self::OPTION,[]);
        if (!is_array($saved)) { $saved=[]; }
        if ($store==='Alle') { unset($saved[$key]); }
        else { $saved[$key]=$store; }
        update_option(self::OPTION,$saved,false);
        return ['saved'=>true,'store'=>$store,'shared'=>true];
    }
}
