<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Optional explicit deep-search of one chain's current structured flyer offers.
 * /v1/offers has no free-text q parameter: we search the returned names locally.
 * At most four 100-row pages per click, cached per store for six hours.
 */
final class MP_OfferFlyers {
    private const PAGE_LIMIT=100;
    private const MAX_PAGES=4;
    private const CACHE_SECONDS=6*HOUR_IN_SECONDS;
    private const CHAIN_SLUGS=[
        'Føtex'=>'fotex','Bilka'=>'bilka','BilkaToGo'=>'bilkatogo',
        'Netto'=>'netto','Rema 1000'=>'rema1000','Lidl'=>'lidl',
        '365discount'=>'365discount','SuperBrugsen'=>'superbrugsen',
        'Kvickly'=>'kvickly','Meny'=>'meny','Spar'=>'spar',
        'Min Købmand'=>'minkobmand','Brugsen'=>'brugsen',
        'Nemlig'=>'nemlig','Wolt Market'=>'wolt-market','Motatos'=>'motatos',
    ];
    public static function chain_slug(string $chain): string {
        return self::CHAIN_SLUGS[$chain]??'';
    }
    public static function matching_name(string $offer_name,string $query): bool {
        $query=mb_strtolower(trim($query),'UTF-8');
        $name=mb_strtolower(trim($offer_name),'UTF-8');
        if ($query==='' || $name==='') { return false; }
        $terms=preg_split('/[^\p{L}\p{N}]+/u',$query,-1,PREG_SPLIT_NO_EMPTY);
        if (!$terms) { return false; }
        foreach ($terms as $term) {
            if (mb_strlen($term)<2) { continue; }
            if (mb_strpos($name,$term)===false) { return false; }
        }
        return true;
    }
    public static function normalize_offer(array $offer,string $today,string $chain): ?array {
        $name=sanitize_text_field((string)($offer['name']??''));
        if ($name==='') { return null; }
        $start=(string)($offer['offer_starts']??'');
        $end=(string)($offer['offer_ends']??'');
        if (($start!=='' && $start>$today) || ($end!=='' && $end<$today)) { return null; }
        $price=filter_var($offer['price']??null,FILTER_VALIDATE_INT);
        if ($price===false || $price<=0 || $price>1000000) { return null; }
        $multi=is_array($offer['multi_buy']??null)?$offer['multi_buy']:null;
        $multi_text='';
        if ($multi) {
            $qty=filter_var($multi['quantity']??null,FILTER_VALIDATE_INT);
            $total=filter_var($multi['price']??null,FILTER_VALIDATE_INT);
            if ($qty!==false && $qty>=2 && $qty<=100 && $total!==false && $total>0 && $total<=10000000) {
                $multi_text=$qty.' for '.number_format($total/100,2,',','.').' kr.';
            }
        }
        return [
            'name'=>$name,'store'=>$chain,'price_ore'=>(int)$price,
            'price_label'=>number_format($price/100,2,',','.').' kr.',
            'quantity'=>sanitize_text_field((string)($offer['quantity_label']??'')),
            'unit_price'=>sanitize_text_field((string)($offer['unit_price_label']??'')),
            'starts'=>$start,'ends'=>$end,
            'source'=>(string)($offer['source']??'')==='flyer'?'Tilbudsavis':'Butikskatalog',
            'multi_buy'=>$multi_text,'last_seen'=>'',
        ];
    }
    private static function page(string $chain_slug,int $page): array|WP_Error {
        $key='mp_prej_flyer_v1_'.md5($chain_slug.'|'.$page);
        $cached=get_transient($key);
        if (is_array($cached)) { return $cached; }
        if (!MP_Offers::reserve_flyer_call()) {
            return new WP_Error('mp_prej_quota','Den daglige gratis tilbudskvote er opbrugt.');
        }
        $url='https://api.prej.app/v1/offers?'.http_build_query([
            'chain'=>$chain_slug,'source'=>'flyer','exclude_border_trade'=>1,
            'page'=>$page,'limit'=>self::PAGE_LIMIT,
        ],'','&',PHP_QUERY_RFC3986);
        $response=wp_safe_remote_get($url,[
            'timeout'=>12,'redirection'=>0,
            'headers'=>[
                'Authorization'=>'Bearer '.MP_Offers::api_key(),
                'Accept'=>'application/json',
            ],
        ]);
        if (is_wp_error($response)) {
            return new WP_Error('mp_prej_network','Forbindelsen til tilbudskilden fejlede.');
        }
        $status=(int)wp_remote_retrieve_response_code($response);
        if ($status===429) {
            return new WP_Error('mp_prej_rate','Tilbudskildens kvote er opbrugt.');
        }
        if ($status!==200) {
            return new WP_Error('mp_prej_http','Tilbudsavisopslag returnerede HTTP '.$status.'.');
        }
        $body=json_decode((string)wp_remote_retrieve_body($response),true);
        if (!is_array($body) || !is_array($body['offers']??null)) {
            return new WP_Error('mp_prej_data','Uventet format fra tilbudsavis-API.');
        }
        $result=['offers'=>array_slice($body['offers'],0,self::PAGE_LIMIT),
                 'total'=>max(0,(int)($body['total']??count($body['offers'])))];
        set_transient($key,$result,self::CACHE_SECONDS);
        return $result;
    }
    public static function scan(int $id,string $chain): array|WP_Error {
        if (!MP_Offers::configured()) {
            return new WP_Error('mp_prej_not_configured','Prej API-nøglen mangler.',['status'=>503]);
        }
        if ($id<=0 || !array_key_exists($chain,self::CHAIN_SLUGS) ||
            !in_array($chain,MP_ShoppingDetails::selected_stores(),true)) {
            return new WP_Error('mp_prej_chain','Vælg én af familiens godkendte tilbudsbutikker.',['status'=>400]);
        }
        global $wpdb;
        $table=MP_Storage::table('shopping');
        $row=$wpdb->get_row($wpdb->prepare(
            "SELECT id,title,checked FROM $table WHERE id=%d",$id
        ),ARRAY_A);
        if (!$row || (int)$row['checked']!==0) {
            return new WP_Error('mp_prej_item','Varen findes ikke længere på den aktive indkøbsliste.',['status'=>404]);
        }
        $term=MP_Offers::search_phrase((string)$row['title']);
        if (mb_strlen($term)<2) {
            return new WP_Error('mp_prej_query','Skriv mindst to tegn i varenavnet.',['status'=>400]);
        }
        $slug=self::chain_slug($chain);
        $results=[];$read=0;$partial=false;$total=0;$last_error='';
        $today=current_datetime()->format('Y-m-d');
        for ($page=1;$page<=self::MAX_PAGES;$page++) {
            $data=self::page($slug,$page);
            if (is_wp_error($data)) {
                $last_error=$data->get_error_message();
                $partial=true;
                break;
            }
            $read+=$data['offers']?count($data['offers']):0;
            $total=max($total,$data['total']);
            foreach ($data['offers'] as $candidate) {
                if (!is_array($candidate) ||
                    !self::matching_name((string)($candidate['name']??''),$term)) {
                    continue;
                }
                $offer=self::normalize_offer($candidate,$today,$chain);
                if ($offer===null) { continue; }
                $unique=md5($offer['name'].'|'.$offer['price_ore'].'|'.$offer['quantity']);
                $results[$unique]=$offer;
            }
            if ($read >= $total || !$data['offers']) { break; }
        }
        if ($read<$total) { $partial=true; }
        $offers=array_slice(array_values($results),0,24);
        $message=$offers?
            'Fundet '.count($offers).' mulige avis-tilbud hos '.$chain.'.':
            'Ingen tekstmatch i de gennemgåede tilbud hos '.$chain.'. Se eventuelt butikkens avis direkte.';
        if ($partial) {
            $message.=' Kun '.$read.' af '.$total.' tilbud gennemgået (maks. fire sider eller kvotegrænse).';
        }
        if ($last_error!=='') { $message.=' '.$last_error; }
        return [
            'items'=>[[
                'id'=>$id,'title'=>(string)$row['title'],'offers'=>$offers,
                'message'=>$message,'expanded'=>true,'store'=>$chain,'partial'=>$partial,
                'checked_offers'=>$read,'total_offers'=>$total,
            ]],
            'usage'=>MP_Offers::usage(),'source'=>'Prej tilbudsavis',
            'note'=>'Tekstmatch på strukturerede tilbud; avisbilleder kan vise varer uden søgeresultat.',
        ];
    }
}
