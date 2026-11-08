<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Family's local-first offer search. Does not contact external services.
 * Reads only previously cached structured offers and authorized OCR findings.
 */
final class MP_LocalOffers {
    private const MAX_ITEMS=10;
    private const MAX_MATCHES=24;

    public static function scan(array $ids): array|WP_Error {
        if (count($ids)>self::MAX_ITEMS) {
            return new WP_Error('mp_local_max','Undersøg højst 10 varer ad gangen.',['status'=>400]);
        }
        $ids=array_values(array_unique(array_map('absint',$ids)));
        $ids=array_values(array_filter($ids,static fn($n)=>$n>0));
        $selected=MP_ShoppingDetails::selected_stores();
        $today=current_datetime()->format('Y-m-d');
        global $wpdb;
        $table=MP_Storage::table('shopping');
        $items=[];
        foreach ($ids as $id) {
            $row=$wpdb->get_row($wpdb->prepare(
                "SELECT id,title,checked FROM $table WHERE id=%d",$id),ARRAY_A);
            if (!$row || (int)$row['checked']!==0) { continue; }
            $sources=[
                MP_DailyOffers::matches($id),
                MP_DailyOCR::matches($id),
                MP_FlyerOCR::match_for_item($id),
            ];
            $all=[];
            foreach ($sources as $source) {
                if (is_wp_error($source) || !is_array($source)) { continue; }
                $offers=(array)($source['offers']??$source['matches']??[]);
                foreach ($offers as $offer) {
                    if (!is_array($offer)) { continue; }
                    $store=(string)($offer['store']??'');
                    $name=(string)($offer['name']??'');
                    $price=(int)($offer['price_ore']??0);
                    $ends=(string)($offer['valid_to']??$offer['ends']??'');
                    $starts=(string)($offer['starts']??'');
                    if ($store==='' || !in_array($store,$selected,true) ||
                        $name==='' || $price<1 || $price>1000000 ||
                        ($ends!=='' && $ends<$today) ||
                        ($starts!=='' && $starts>$today)) { continue; }
                    $key=md5(mb_strtolower($store.'|'.$name,'UTF-8').'|'.$price);
                    if (isset($all[$key])) { continue; }
                    $offer['name']=$name;
                    $offer['store']=$store;
                    $offer['price_ore']=$price;
                    $offer['price_label']=number_format($price/100,2,',','.').' kr.';
                    $offer['ends']=$ends;
                    $offer['quantity']=(string)($offer['quantity']??'');
                    $offer['unit_price']=(string)($offer['unit_price']??'');
                    $offer['source']=(string)($offer['source']??'Gemte tilbud');
                    $all[$key]=$offer;
                }
            }
            $offers=array_values($all);
            usort($offers,static fn($a,$b)=>[$a['store'],$a['price_ore'],$a['name']] <=>
                                             [$b['store'],$b['price_ore'],$b['name']]);
            $offers=array_slice($offers,0,self::MAX_MATCHES);
            $items[]=[
                'id'=>$id,'title'=>(string)$row['title'],
                'offers'=>$offers,'local_index'=>true,
                'message'=>$offers?'':
                    'Ingen gemte aktive tilbud på denne vare. OCR-/tilbudsindekset kan være ufuldstændigt. '
                    .'Du kan vælge at slå op hos Prej manuelt.',
            ];
        }
        return [
            'items'=>$items,'local_index'=>true,'day'=>$today,
            'source'=>'Madplans gemte tilbud og OCR-resultater',
            'note'=>'Denne søgning bruger ingen Prej API-kald. OCR-priser skal kontrolleres i tilbudsavisen.',
        ];
    }
}
