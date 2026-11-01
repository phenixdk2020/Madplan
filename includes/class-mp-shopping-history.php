<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Shared autocomplete history of products actually checked as purchased.
 * Independent of the current shopping rows, which may be cleared.
 * No external APIs, personal profile data or new DB tables.
 */
final class MP_ShoppingHistory {
    private const OPTION='mp_shopping_purchased_history_v1';
    private const LIMIT=250;

    private static function key(string $title): string {
        $title=mb_strtolower(trim($title),'UTF-8');
        return (string)preg_replace('/\s+/u',' ',$title);
    }

    private static function seed(): array {
        // Only import checked rows on first use after updating.
        // Older checked purchases already cleared cannot be recovered.
        global $wpdb;
        $table=MP_Storage::table('shopping');
        $rows=$wpdb->get_results("SELECT title FROM $table WHERE checked=1 ORDER BY id DESC LIMIT 250",ARRAY_A);
        $items=[];
        foreach (is_array($rows)?$rows:[] as $row) {
            $title=sanitize_text_field(trim((string)($row['title']??'')));
            $key=self::key($title);
            if ($key==='' || mb_strlen($title)>255 || isset($items[$key])) { continue; }
            $items[$key]=['title'=>$title,'last_purchased'=>''];
        }
        update_option(self::OPTION,$items,false);
        return $items;
    }

    private static function items(): array {
        $saved=get_option(self::OPTION,null);
        return is_array($saved)?$saved:self::seed();
    }

    public static function remember(string $title): void {
        $title=sanitize_text_field(trim($title));
        if ($title==='' || mb_strlen($title)>255) { return; }
        $key=self::key($title);
        if ($key==='') { return; }
        $items=self::items();
        $items[$key]=['title'=>$title,'last_purchased'=>gmdate('c')];
        uasort($items,static function($a,$b) {
            return strcmp((string)($b['last_purchased']??''),(string)($a['last_purchased']??''));
        });
        update_option(self::OPTION,array_slice($items,0,self::LIMIT,true),false);
    }

    /** Sorted server-side; filtering is local on each keystroke. */
    public static function suggestions(): array {
        $out=[];
        foreach (self::items() as $entry) {
            if (!is_array($entry)) { continue; }
            $title=sanitize_text_field((string)($entry['title']??''));
            if ($title!=='') {
                $out[]=['title'=>$title,'last_purchased'=>(string)($entry['last_purchased']??'')];
            }
        }
        usort($out,static fn($a,$b)=>strcmp($b['last_purchased'],$a['last_purchased']));
        return array_slice($out,0,self::LIMIT);
    }
}
