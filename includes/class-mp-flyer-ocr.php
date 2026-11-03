<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * OCR pilot for a family member's manually supplied, legitimately accessible
 * flyer page. Never downloads supplier pages; never stores the image or full
 * OCR output. Only short, unverified product/price candidates are retained.
 */
final class MP_FlyerOCR {
    private const OPTION='mp_grocery_ocr_candidates_v1';
    private const MAX_ENTRIES=300;
    private const MAX_IMAGE_BYTES=6*1024*1024;
    private const MAX_PIXELS=36000000;
    private const MAX_TEXT_BYTES=200000;
    private const LANGUAGES=['dan+eng','eng'];

    private static function binary(): string {
        if (defined('MP_TESSERACT_PATH') && is_string(MP_TESSERACT_PATH) && MP_TESSERACT_PATH!=='') {
            return MP_TESSERACT_PATH;
        }
        return 'tesseract';
    }
    private static function run(array $args): array|WP_Error {
        if (!function_exists('proc_open')) {
            return new WP_Error('mp_ocr_unavailable',
                'Webhotellet tillader ikke lokale programmer (proc_open). Brug manuel tekstimport.');
        }
        $descriptors=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
        try {
            $process=@proc_open($args,$descriptors,$pipes,null,null,['bypass_shell'=>true]);
        } catch (Throwable $e) {
            return new WP_Error('mp_ocr_unavailable','Tesseract kan ikke startes på webhotellet.');
        }
        if (!is_resource($process)) {
            return new WP_Error('mp_ocr_unavailable','Tesseract kan ikke startes på webhotellet.');
        }
        fclose($pipes[0]);
        // OCR output is bounded to avoid storing an entire copyrighted leaflet.
        $stdout=(string)stream_get_contents($pipes[1],self::MAX_TEXT_BYTES+1);
        fclose($pipes[1]);
        $stderr=(string)stream_get_contents($pipes[2],12000);
        fclose($pipes[2]);
        $exit=proc_close($process);
        if (strlen($stdout)>self::MAX_TEXT_BYTES) {
            return new WP_Error('mp_ocr_too_long','OCR-svaret er for langt. Prøv med én avisside.');
        }
        return ['code'=>$exit,'text'=>$stdout,'error'=>$stderr];
    }
    /**
     * Return text from a temporary local image produced by an authorized
     * source adapter. This never stores the raw text or image in WordPress.
     */
    public static function text_from_local_image(string $path): string|WP_Error {
        if (!is_file($path) || filesize($path)<1 ||
            filesize($path)>self::MAX_IMAGE_BYTES) {
            return new WP_Error('mp_ocr_local_file','Ugyldig midlertidig billedfil.');
        }
        $info=@getimagesize($path);
        if (!$info || empty($info[0]) || empty($info[1]) ||
            $info[0]*$info[1]>self::MAX_PIXELS ||
            !in_array($info[2]??0,[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)) {
            return new WP_Error('mp_ocr_local_image','Midlertidigt billede er ikke understøttet.');
        }
        if (!self::status()['available']) {
            return new WP_Error('mp_ocr_unavailable','Tesseract er ikke installeret på webhotellet.');
        }
        foreach (self::LANGUAGES as $lang) {
            $result=self::run([self::binary(),$path,'stdout','-l',$lang,'--psm','11']);
            if (is_wp_error($result)) { return $result; }
            if ($result['code']===0 && trim($result['text'])!=='') {
                return $result['text'];
            }
        }
        return new WP_Error('mp_ocr_no_text','Ingen læsbar tekst på avissiden.');
    }

    public static function status(): array {
        $cache=get_transient('mp_ocr_engine_status_v1');
        if (is_array($cache)) { return $cache; }
        $r=self::run([self::binary(),'--version']);
        $ok=is_array($r) && $r['code']===0;
        $answer=[
            'available'=>$ok,
            'message'=>$ok?'Tesseract findes på WordPress-serveren.':
                'Tesseract kan ikke køres her. Du kan stadig importere OCR-tekst fra din egen computer.',
        ];
        set_transient('mp_ocr_engine_status_v1',$answer,6*HOUR_IN_SECONDS);
        return $answer;
    }
    private static function valid_date(string $date): bool {
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('UTC'));
        return $dt!==false && $dt->format('Y-m-d')===$date;
    }
    private static function metadata(string $store,string $until): array|WP_Error {
        if (!in_array($store,MP_ShoppingDetails::selected_stores(),true)) {
            return new WP_Error('mp_ocr_store','Vælg en af familiens valgte butikker.',['status'=>400]);
        }
        $now=current_datetime()->format('Y-m-d');
        if (!self::valid_date($until) || $until<$now || $until>date('Y-m-d',strtotime('+35 days',strtotime($now)))) {
            return new WP_Error('mp_ocr_date','Angiv gyldig slutdato (fra i dag til højst 35 dage frem).',['status'=>400]);
        }
        return ['store'=>$store,'until'=>$until,'today'=>$now];
    }
    public static function parse_candidates(string $text,string $store,string $valid_to): array {
        $text=mb_substr($text,0,self::MAX_TEXT_BYTES,'UTF-8');
        $lines=preg_split('/\R/u',str_replace("\r","\n",$text));
        if (!is_array($lines)) { return []; }
        $lines=array_slice(array_values(array_filter(array_map(static function($s) {
            return trim((string)preg_replace('/\s+/u',' ',(string)$s));
        },$lines),static fn($s)=>$s!=='')),0,1600);
        $result=[];
        // Handles "65,-", "65,00" and "65 kr". Decimal numbers within
        // quantities are not treated as standalone price labels.
        $pricePattern='~(?<![\p{L}\p{N}])(\d{1,4})(?:\s*[,.]\s*(\d{2})|\s*[,.]\s*[-–]|\s+kr\.?)(?!\d)~iu';
        foreach ($lines as $i=>$line) {
            if (mb_strlen($line,'UTF-8')>170 ||
                !preg_match($pricePattern,$line,$match,PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $whole=(int)$match[1][0];
            $minor=isset($match[2][0]) && $match[2][0]!==''?(int)$match[2][0]:0;
            $price=($whole*100)+$minor;
            if ($price<100 || $price>100000) { continue; }
            $priceMatch=$match[0][0];
            $name=trim((string)preg_replace($pricePattern,' ',$line,1));
            $name=trim((string)preg_replace('/\b(?:tilbud|førpris|spar)\b/iu','',$name));
            if (mb_strlen($name,'UTF-8')<4 || !preg_match('/\p{L}/u',$name)) {
                // Printed advertising often places name and price on
                // separate lines; skip line consisting only of pack sizes.
                for ($j=$i-1;$j>=max(0,$i-3);$j--) {
                    $prev=$lines[$j];
                    if (preg_match('/^\s*\d+\s*(?:x|×)\s*\d+/iu',$prev) ||
                        preg_match('/^\s*\d+(?:[,.]\d+)?\s*(?:g|kg|cl|ml|l)\s*$/iu',$prev)) {
                        continue;
                    }
                    if (mb_strlen($prev,'UTF-8')>=4 && mb_strlen($prev,'UTF-8')<=95 &&
                        preg_match('/\p{L}/u',$prev)) {
                        $name=$prev;break;
                    }
                }
            }
            $name=sanitize_text_field(trim($name));
            if ($name==='' || mb_strlen($name,'UTF-8')<4 || mb_strlen($name,'UTF-8')>110 ||
                !preg_match('/\p{L}/u',$name)) { continue; }
            $key=md5(mb_strtolower($name,'UTF-8').'|'.$price.'|'.$store);
            $result[$key]=[
                'name'=>$name,'store'=>$store,'price_ore'=>$price,
                'price_label'=>number_format($price/100,2,',','.').' kr.',
                'valid_to'=>$valid_to,'source'=>'OCR-forslag – kontroller i avisen',
            ];
            if (count($result)>=50) { break; }
        }
        return array_values($result);
    }
    public static function import_text(string $text,string $store,string $until): array|WP_Error {
        $info=self::metadata($store,$until);
        if (is_wp_error($info)) { return $info; }
        if (strlen($text)>self::MAX_TEXT_BYTES || trim($text)==='') {
            return new WP_Error('mp_ocr_text','Indsæt OCR-tekst (maks. 200 KB).',['status'=>400]);
        }
        $found=self::parse_candidates($text,$store,$until);
        if (!$found) {
            return new WP_Error('mp_ocr_no_candidates',
                'Ingen sikre prislinjer genkendt. Prøv tydeligere billede eller korrektér OCR-teksten.',['status'=>422]);
        }
        $stored=get_option(self::OPTION,[]);
        if (!is_array($stored)) { $stored=[]; }
        $today=current_datetime()->format('Y-m-d');
        $stored=array_values(array_filter($stored,static function($entry) use($today) {
            return is_array($entry) && ($entry['valid_to']??'')>=$today;
        }));
        // Replace the same store/price/title rather than accumulating doubles.
        $keys=[];
        foreach (array_merge($found,$stored) as $entry) {
            $key=md5(mb_strtolower((string)($entry['name']??''),'UTF-8').'|'.
                (string)($entry['price_ore']??'').'|'.(string)($entry['store']??''));
            $keys[$key]=$entry;
        }
        $stored=array_slice(array_values($keys),0,self::MAX_ENTRIES);
        update_option(self::OPTION,$stored,false);
        return [
            'imported'=>count($found),'stored'=>count($stored),
            'examples'=>array_slice($found,0,12),
            'note'=>'OCR er ikke en verificeret pris. Kontrollér selv varen, mængden og perioden i avisen.',
        ];
    }
    public static function scan_upload(array $file,string $store,string $until): array|WP_Error {
        $info=self::metadata($store,$until);
        if (is_wp_error($info)) { return $info; }
        $path=(string)($file['tmp_name']??'');
        $size=(int)($file['size']??0);
        if ($size<1 || $size>self::MAX_IMAGE_BYTES || !is_uploaded_file($path)) {
            return new WP_Error('mp_ocr_upload','Vælg ét billedark (højst 6 MB).',['status'=>400]);
        }
        $image=@getimagesize($path);
        if (!is_array($image) || empty($image[0]) || empty($image[1]) ||
            $image[0]*$image[1]>self::MAX_PIXELS ||
            !in_array($image[2]??0,[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)) {
            return new WP_Error('mp_ocr_image','Kun JPG, PNG og WebP (maks. 36 megapixel).',['status'=>400]);
        }
        if (!self::status()['available']) {
            return new WP_Error('mp_ocr_unavailable',
                'Webhotellet har ikke Tesseract. Benyt OCR-tekst fra din PC i stedet.',['status'=>503]);
        }
        $output=null;$error='';
        foreach (self::LANGUAGES as $language) {
            $run=self::run([self::binary(),$path,'stdout','-l',$language,'--psm','11']);
            if (is_wp_error($run)) { return $run; }
            $error=(string)$run['error'];
            if ($run['code']===0 && trim($run['text'])!=='') { $output=$run['text'];break; }
        }
        if ($output===null) {
            return new WP_Error('mp_ocr_failed',
                'Tesseract kunne ikke læse billedet. Prøv en skarpere side eller indsæt OCR-tekst.',['status'=>422]);
        }
        // The temporary PHP upload is discarded at end of request; no media
        // attachment, copy, or image/complete OCR-text database record.
        return self::import_text($output,$store,$until);
    }
    public static function match_for_item(int $id): array|WP_Error {
        global $wpdb;
        $table=MP_Storage::table('shopping');
        $row=$wpdb->get_row($wpdb->prepare(
            "SELECT id,title,checked FROM $table WHERE id=%d",$id
        ),ARRAY_A);
        if (!$row) { return new WP_Error('mp_item_missing','Varen findes ikke.',['status'=>404]); }
        $phrase=MP_Offers::search_phrase((string)$row['title']);
        $today=current_datetime()->format('Y-m-d');
        $matches=[];
        foreach ((array)get_option(self::OPTION,[]) as $entry) {
            if (!is_array($entry) || (string)($entry['valid_to']??'')<$today) { continue; }
            if (!MP_OfferFlyers::matching_name((string)($entry['name']??''),$phrase)) { continue; }
            if (!in_array((string)($entry['store']??''),MP_ShoppingDetails::selected_stores(),true)) { continue; }
            $matches[]=$entry;
        }
        return [
            'id'=>$id,'title'=>$row['title'],'matches'=>array_slice($matches,0,15),
            'source'=>'OCR-import fra familien',
            'note'=>'Automatisk tekstgenkendelse kan sammenblande produkter og priser. Kontrollér den originale avis.',
        ];
    }
    public static function clear(): array {
        delete_option(self::OPTION);
        return ['cleared'=>true];
    }
}
