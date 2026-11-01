<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Optional Danish grocery offers via the official Prej API.
 * Personal, non-commercial use only; no third-party scraping.
 * Explicit user-initiated scans with server-side cache and a conservative
 * daily limit of 20 API requests (free account limit is currently 25).
 */
final class MP_Offers {
    private const OPTION='mp_prej_api_key_v1';
    private const USAGE='mp_prej_usage_v1';
    private const DAILY_LIMIT=20;
    private const MAX_ITEMS=10;
    private const CACHE_SECONDS=6 * HOUR_IN_SECONDS;
    public static function api_key(): string {
        if (defined('MP_PREJ_API_TOKEN') && is_string(MP_PREJ_API_TOKEN) && MP_PREJ_API_TOKEN!=='') {
            return MP_PREJ_API_TOKEN;
        }
        return (string)get_option(self::OPTION,'');
    }
    public static function configured(): bool { return self::api_key()!==''; }
    public static function save_key(string $key): bool {
        if (!preg_match('/^prej_[A-Za-z0-9_-]{12,250}$/',$key)) { return false; }
        update_option(self::OPTION,$key,false);
        return true;
    }
    public static function clear_key(): void { delete_option(self::OPTION); }
    public static function usage(): array {
        $stored=get_option(self::USAGE,[]);
        $today=gmdate('Y-m-d');
        $used=is_array($stored) && ($stored['date']??'')===$today?
            max(0,absint($stored['count']??0)):0;
        return ['used'=>$used,'remaining'=>max(0,self::DAILY_LIMIT-$used),
                'limit'=>self::DAILY_LIMIT,'reset'=>'00:00 UTC'];
    }
    private static function consume(): bool {
        $usage=self::usage();
        if ($usage['remaining']<=0) { return false; }
        update_option(self::USAGE,['date'=>gmdate('Y-m-d'),'count'=>$usage['used']+1],false);
        return true;
    }
    public static function search_phrase(string $title): string {
        $title=trim(sanitize_text_field($title));
        // The existing shopping list keeps quantity in a separate field, but
        // some users type '2 l mælk' as title. Remove only unambiguous prefixes.
        $title=(string)preg_replace('/^\d+(?:[.,]\d+)?\s*(?:stk\.?|pakker?|kg|g|l|liter|ml)\s+/iu','',$title);
        $title=(string)preg_replace('/\s+/u',' ',$title);
        return trim(mb_substr($title,0,90));
    }
    public static function normalize_product(array $product,string $today): array {
        $suggestions=[];
        foreach ((array)($product['prices']??[]) as $price) {
            if (!is_array($price) || empty($price['is_offer']) || !isset($price['price'])) { continue; }
            $ends=(string)($price['offer_ends']??'');
            $starts=(string)($price['offer_starts']??'');
            if (($ends!=='' && $ends<$today) || ($starts!=='' && $starts>$today)) { continue; }
            $cents=filter_var($price['price'],FILTER_VALIDATE_INT);
            if ($cents===false || $cents<1 || $cents>1000000) { continue; }
            $chain=sanitize_text_field((string)($price['chain_name']??''));
            if ($chain==='') { continue; }
            $suggestions[]=[
                'name'=>sanitize_text_field((string)($product['name']??'')),
                'store'=>$chain,
                'price_ore'=>$cents,
                'price_label'=>number_format($cents/100,2,',','.').' kr.',
                'quantity'=>sanitize_text_field((string)($product['quantity_label']??'')),
                'unit_price'=>sanitize_text_field((string)($price['unit_price_label']??'')),
                'starts'=>$starts,'ends'=>$ends,
                'source'=>($price['source']??'')==='flyer'?'Tilbudsavis':'Butikskatalog',
                'last_seen'=>sanitize_text_field((string)($price['last_seen_date']??'')),
            ];
        }
        return $suggestions;
    }
    private static function fetch(string $term): array|WP_Error {
        $cache='mp_prej_item_'.md5($term);
        $cached=get_transient($cache);
        if (is_array($cached)) { return $cached; }
        if (!self::consume()) {
            return new WP_Error('mp_prej_quota','Den daglige gratis tilbudskvote er opbrugt. Prøv igen efter midnat UTC.');
        }
        $url='https://api.prej.app/v1/products?'.http_build_query([
            'q'=>$term,'on_offer'=>1,'exclude_border_trade'=>1,'limit'=>75,
        ],'','&',PHP_QUERY_RFC3986);
        $response=wp_safe_remote_get($url,[
            'timeout'=>12,'redirection'=>0,
            'headers'=>['Authorization'=>'Bearer '.self::api_key(),'Accept'=>'application/json'],
        ]);
        if (is_wp_error($response)) { return $response; }
        $code=(int)wp_remote_retrieve_response_code($response);
        if ($code===429) {
            return new WP_Error('mp_prej_rate','Tilbudskilden har nået sin opkaldsgrænse. Prøv igen senere.');
        }
        if ($code!==200) {
            return new WP_Error('mp_prej_http','Kunne ikke hente tilbud. Kontroller API-nøglen i Hangar18 → Oversigt.');
        }
        $body=json_decode((string)wp_remote_retrieve_body($response),true);
        if (!is_array($body) || !isset($body['products']) || !is_array($body['products'])) {
            return new WP_Error('mp_prej_data','Tilbudskilden returnerede uventede data.');
        }
        $today=current_datetime()->format('Y-m-d');
        $offers=[];
        foreach ($body['products'] as $product) {
            if (!is_array($product)) { continue; }
            foreach (self::normalize_product($product,$today) as $offer) {
                $offers[]=$offer;
            }
        }
        // Keep a few product alternatives, not a misleading "cheapest" claim
        // across different sizes and brands. Exclude duplicate store/product.
        $unique=[];
        foreach ($offers as $offer) {
            $key=md5($offer['name'].'|'.$offer['store'].'|'.$offer['price_ore']);
            if (!isset($unique[$key])) { $unique[$key]=$offer; }
        }
        $data=['offers'=>array_slice(array_values($unique),0,75),
               'as_of'=>(string)($body['as_of']??$today)];
        set_transient($cache,$data,self::CACHE_SECONDS);
        return $data;
    }
    /**
     * Only fetch products that really exist in the shared WP shopping table.
     * Checked-off items are ignored; no arbitrary external queries or background
     * scans. A single click checks at most 10 active items.
     */
    public static function scan(array $ids): array|WP_Error {
        if (!self::configured()) {
            return new WP_Error('mp_prej_not_configured',
                'Tilbudssøgning kræver en gratis Prej API-nøgle. Administrator kan tilføje den under Hangar18 → Oversigt.',
                ['status'=>503]);
        }
        if (count($ids)>self::MAX_ITEMS) {
            return new WP_Error('mp_prej_max','Undersøg højst ti varer ad gangen.',['status'=>400]);
        }
        $ids=array_values(array_unique(array_filter(array_map('absint',$ids))));
        $selectedStores=class_exists('MP_ShoppingDetails') ?
            MP_ShoppingDetails::selected_stores() : null;
        if (!$ids) { return ['items'=>[],'usage'=>self::usage()]; }
        global $wpdb;
        $table=MP_Storage::table('shopping');
        $out=[];
        foreach ($ids as $id) {
            $row=$wpdb->get_row($wpdb->prepare(
                "SELECT id,title,checked FROM $table WHERE id=%d",$id
            ),ARRAY_A);
            if (!$row || (int)$row['checked']!==0) { continue; }
            $term=self::search_phrase((string)$row['title']);
            if (mb_strlen($term)<2) {
                $out[]=['id'=>$id,'title'=>(string)$row['title'],'offers'=>[],
                        'message'=>'Skriv et mere præcist varenavn.'];
                continue;
            }
            $data=self::fetch($term);
            if (is_wp_error($data)) {
                $out[]=['id'=>$id,'title'=>(string)$row['title'],'offers'=>[],
                        'message'=>$data->get_error_message()];
                if ($data->get_error_code()==='mp_prej_quota') { break; }
                continue;
            }
            $visible=$data['offers'];
            if (is_array($selectedStores)) {
                $visible=array_values(array_filter($visible,static function($offer) use($selectedStores) {
                    return in_array(mb_strtolower(trim((string)($offer['store']??'')),'UTF-8'),
                        array_map(static fn($s)=>mb_strtolower(trim((string)$s),'UTF-8'),$selectedStores),true);
                }));
            }
            $visible=array_slice($visible,0,6);
            $out[]=['id'=>$id,'title'=>(string)$row['title'],
                    'offers'=>$visible,'as_of'=>$data['as_of'],
                    'message'=>$visible?'':($selectedStores===[] ?
                        'Der er ikke valgt butikker til tilbudssøgningen.':
                        'Ingen aktuelle tilbud fundet i de valgte butikker.')];
        }
        return ['items'=>$out,'usage'=>self::usage(),'source'=>'Prej',
                'note'=>'Mulige varematch. Kontroller mærke, mængde, butik og tilbudsdato før køb.'];
    }
}
