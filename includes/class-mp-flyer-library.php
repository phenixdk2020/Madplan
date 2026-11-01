<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * A small, on-demand index of currently valid Danish flyer page links.
 * Reads Prej /v1/flyers, not just the structured /v1/offers descriptions.
 * Never downloads, mirrors, OCRs, or re-hosts third-party newspaper images.
 * Only displays external links to Prej-hosted flyer pages.
 */
final class MP_FlyerLibrary {
    private const CACHE='mp_flyer_library_v1';
    private const CACHE_SECONDS=6*HOUR_IN_SECONDS;
    private const LIMIT=100;
    private const MAX_PAGES=2;

    private static function valid_date(string $date): bool {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)) { return false; }
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('UTC'));
        return $dt!==false && $dt->format('Y-m-d')===$date;
    }
    private static function page_url(string $raw): string {
        $parts=parse_url($raw);
        if (!is_array($parts) || ($parts['scheme']??'')!=='https' ||
            ($parts['host']??'')!=='img.prej.app' ||
            isset($parts['user']) || isset($parts['pass']) ||
            isset($parts['query']) || isset($parts['fragment'])) {
            return '';
        }
        $path=(string)($parts['path']??'');
        if (!preg_match('~^/flyers/[a-z0-9-]+/[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)$~D',$path)) {
            return '';
        }
        return $raw;
    }
    public static function normalize(array $body,string $today): array {
        $selected=class_exists('MP_ShoppingDetails')?MP_ShoppingDetails::selected_stores():[];
        $slugs=[];
        foreach ($selected as $store) {
            $slug=MP_OfferFlyers::chain_slug((string)$store);
            if ($slug!=='') { $slugs[$slug]=(string)$store; }
        }
        $out=[];
        foreach ((array)($body['flyers']??[]) as $record) {
            if (!is_array($record)) { continue; }
            $slug=(string)($record['chain_slug']??'');
            if (!isset($slugs[$slug])) { continue; }
            $from=(string)($record['valid_from']??'');
            $to=(string)($record['valid_to']??'');
            if (!self::valid_date($from) || !self::valid_date($to) ||
                $today<$from || $today>$to) {
                continue;
            }
            $urls=[];
            foreach ((array)($record['page_urls']??[]) as $value) {
                if (!is_string($value)) { continue; }
                $url=self::page_url($value);
                if ($url!=='') { $urls[$url]=$url; }
                if (count($urls)>=80) { break; }
            }
            if (!$urls) { continue; }
            $id=sanitize_text_field((string)($record['flyer_id']??''));
            $key=$slug.'|'.$id;
            $out[$key]=[
                'chain'=>$slugs[$slug],'valid_from'=>$from,'valid_to'=>$to,
                'flyer_id'=>$id,'page_urls'=>array_values($urls),
                'page_count'=>count($urls),
            ];
        }
        $out=array_values($out);
        usort($out,static fn($a,$b)=>[$a['chain'],$a['valid_to']] <=> [$b['chain'],$b['valid_to']]);
        return $out;
    }
    private static function raw(): array|WP_Error {
        $saved=get_transient(self::CACHE);
        if (is_array($saved)) { return $saved; }
        $all=[];$pages=0;$total=0;$partial=false;
        for ($page=1;$page<=self::MAX_PAGES;$page++) {
            if (!MP_Offers::reserve_flyer_call()) {
                return new WP_Error('mp_prej_quota',
                    'Den daglige gratis kvote er brugt op. Prøv igen efter midnat UTC.');
            }
            $url='https://api.prej.app/v1/flyers?'.http_build_query([
                'page'=>$page,'limit'=>self::LIMIT,
            ],'','&',PHP_QUERY_RFC3986);
            $response=wp_safe_remote_get($url,[
                'timeout'=>12,'redirection'=>0,
                'headers'=>[
                    'Authorization'=>'Bearer '.MP_Offers::api_key(),
                    'Accept'=>'application/json',
                ],
            ]);
            if (is_wp_error($response)) {
                return new WP_Error('mp_prej_network','Kunne ikke nå Prejs tilbudsaviser.');
            }
            $status=(int)wp_remote_retrieve_response_code($response);
            if ($status!==200) {
                return new WP_Error('mp_prej_http',
                    'Prej tilbudsavisoversigt returnerede HTTP '.$status.'.');
            }
            $body=json_decode((string)wp_remote_retrieve_body($response),true);
            if (!is_array($body) || !is_array($body['flyers']??null)) {
                return new WP_Error('mp_prej_data','Prej returnerede ikke en liste med tilbudsaviser.');
            }
            $count=count($body['flyers']);
            $all=array_merge($all,$body['flyers']);
            $pages++;
            $total=max($total,absint($body['total']??0));
            if ($count<self::LIMIT || ($total>0 && count($all)>=$total)) { break; }
        }
        $partial=($total>0 && count($all)<$total) ||
            ($total===0 && $pages>=self::MAX_PAGES && count($all)>=self::MAX_PAGES*self::LIMIT);
        $result=['flyers'=>$all,'partial'=>$partial,'pages'=>$pages,
                 'total'=>$total,'retrieved'=>count($all)];
        set_transient(self::CACHE,$result,self::CACHE_SECONDS);
        return $result;
    }
    public static function listing(): array|WP_Error {
        if (!MP_Offers::configured()) {
            return new WP_Error('mp_prej_missing',
                'Administrator skal først konfigurere den gratis Prej-nøgle.',['status'=>503]);
        }
        if (class_exists('MP_ShoppingDetails') && !MP_ShoppingDetails::selected_stores()) {
            return ['flyers'=>[],'partial'=>false,'total_retrieved'=>0,'usage'=>MP_Offers::usage(),
                'note'=>'Vælg først de butikker, familien vil se tilbudsaviser fra.'];
        }
        $data=self::raw();
        if (is_wp_error($data)) { return $data; }
        return [
            'flyers'=>self::normalize($data,current_datetime()->format('Y-m-d')),
            'partial'=>$data['partial'],
            'total_retrieved'=>$data['retrieved'],
            'usage'=>MP_Offers::usage(),
            'note'=>'Avissiderne er billeder og kan ikke fritekstsøges uden OCR. '
                .'Vi gemmer kun links til aktuelle aviser, ikke billederne. '
                .'Prej giver ikke selv brugsret til tredjeparts billeder.',
        ];
    }
}
