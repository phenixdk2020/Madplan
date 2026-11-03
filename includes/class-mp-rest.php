<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class MP_REST {
    private const NS = 'hangar18/v1';
    public static function can_read(): bool { return is_user_logged_in(); }
    // Every logged-in family member may edit the shared meal plan and add shows.
    public static function can_write(): bool { return is_user_logged_in(); }
    public static function can_manage(): bool { return current_user_can('manage_options'); }
    private const THEMES = ['default', 'dark', 'forest', 'purple', 'sand', 'ocean', 'rose', 'sunset', 'mint', 'burgundy'];
    public static function current_theme(): string {
        if (!is_user_logged_in()) { return 'default'; }
        $theme = (string)get_user_meta(get_current_user_id(), 'mp_portal_theme', true);
        return in_array($theme, self::THEMES, true) ? $theme : 'default';
    }
    public static function save_theme(WP_REST_Request $r) {
        $theme = sanitize_key((string)$r->get_param('theme'));
        if (!in_array($theme, self::THEMES, true)) {
            return new WP_Error('mp_theme_invalid', 'Ukendt farvetema.', ['status'=>400]);
        }
        $uid = get_current_user_id();
        if (!$uid) { return new WP_Error('mp_login_required', 'Log ind for at vælge tema.', ['status'=>401]); }
        update_user_meta($uid, 'mp_portal_theme', $theme);
        return ['theme'=>$theme, 'saved'=>true];
    }
    public static function streaming_services(): array {
        return ['options'=>MP_Streaming::services(),'selected'=>MP_Streaming::chosen(),'shared'=>true,
            'configured'=>MP_Streaming::configured(),
            'credits'=>'Streaming availability: JustWatch via TMDB (DK).'];
    }
    public static function save_streaming_services(WP_REST_Request $r) {
        $services=$r->get_param('services');
        if (!is_array($services)) {
            return new WP_Error('mp_services_invalid','Send en liste med streamingtjenester.',['status'=>400]);
        }
        return MP_Streaming::save_shared($services);
    }
    public static function streaming_lookup(WP_REST_Request $r): array {
        return MP_Streaming::lookup(absint($r->get_param('tvmaze_id')));
    }
    public static function check_streaming_show(WP_REST_Request $r) {
        global $wpdb;
        $id=absint($r['id']);
        $table=MP_Storage::table('shows');
        $show=$wpdb->get_row($wpdb->prepare("SELECT id,tvmaze_id,service FROM $table WHERE id=%d AND active=1",$id),ARRAY_A);
        if (!$show) { return new WP_Error('mp_show_missing','Serien findes ikke.',['status'=>404]); }
        $lookup=MP_Streaming::lookup((int)$show['tvmaze_id']);
        $current=(string)($show['service']??'');
        if (($current==='' || $current==='Ukendt') && count($lookup['matches'])===1) {
            $current=$lookup['matches'][0];
            $wpdb->update($table,['service'=>$current],['id'=>$id],['%s'],['%d']);
            $lookup['auto_assigned']=$current;
        } else { $lookup['auto_assigned']=''; }
        $lookup['service']=$current;
        return $lookup;
    }
    private static function route(string $path, string $method, string $callback, bool $write = false, bool $admin_only = false): void {
        register_rest_route(self::NS, $path, [
            'methods'=>$method, 'callback'=>[self::class, $callback],
            'permission_callback'=>[self::class, $admin_only ? 'can_manage' : ($write ? 'can_write' : 'can_read')],
        ]);
    }
    public static function register(): void {
        self::route('/theme', 'POST', 'save_theme', true);
        self::route('/streaming-services', 'GET', 'streaming_services');
        self::route('/streaming-services', 'POST', 'save_streaming_services', true);
        self::route('/streaming-lookup', 'GET', 'streaming_lookup');
        self::route('/shows/(?P<id>\d+)/streaming-check', 'POST', 'check_streaming_show', true);
        self::route('/movies', 'GET', 'movies');
        self::route('/movies/search', 'GET', 'movie_search');
        self::route('/movies', 'POST', 'add_movie', true);
        self::route('/movies/(?P<id>\\d+)', 'POST', 'save_movie', true);
        self::route('/movies/(?P<id>\\d+)/refresh', 'POST', 'refresh_movie', true);
        self::route('/movies/(?P<id>\\d+)', 'DELETE', 'delete_movie', true, true);
        self::route('/week', 'GET', 'week');
        self::route('/recipes', 'GET', 'recipes');
        self::route('/recipe/(?P<id>\d+)', 'GET', 'recipe');
        self::route('/shows', 'GET', 'shows');
        self::route('/tv-search', 'GET', 'tv_search', true);
        self::route('/tv-details', 'GET', 'tv_details', true);
        self::route('/tv-seasons', 'GET', 'tv_seasons');
        self::route('/meal', 'POST', 'save_meal', true);
        self::route('/shows', 'POST', 'create_show', true);
        self::route('/shows/(?P<id>\d+)', 'DELETE', 'delete_show', true, true);
        self::route('/shows/(?P<id>\d+)/sync', 'POST', 'sync_show', true);
        self::route('/shows/(?P<id>\d+)/dk-schedule', 'POST', 'save_danish_schedule', true);
        self::route('/shopping/stores', 'GET', 'shopping_stores');
        self::route('/shopping/history', 'GET', 'shopping_history');
        self::route('/shopping/offer-stores', 'POST', 'save_offer_stores', true);
        self::route('/shopping/(?P<id>\d+)/details', 'POST', 'save_shopping_details', true);
        self::route('/shopping/(?P<id>\d+)/photo', 'POST', 'upload_shopping_photo', true);
        self::route('/shopping/(?P<id>\\d+)/store', 'POST', 'save_shopping_store', true);
        self::route('/shopping', 'GET', 'shopping');
        self::route('/shopping/offers', 'POST', 'shopping_offers', true);
        self::route('/shopping/flyer-search', 'POST', 'shopping_flyer_search', true);
        self::route('/shopping/flyers', 'GET', 'shopping_flyers');
        self::route('/shopping/ocr/status', 'GET', 'shopping_ocr_status');
        self::route('/shopping/ocr/matches', 'GET', 'shopping_ocr_matches');
        self::route('/shopping/ocr/upload', 'POST', 'shopping_ocr_upload', true, true);
        self::route('/shopping/ocr/import-text', 'POST', 'shopping_ocr_import_text', true, true);
        self::route('/shopping/ocr/clear', 'DELETE', 'shopping_ocr_clear', true, true);
        self::route('/shopping/daily/status', 'GET', 'shopping_daily_status');
        self::route('/shopping/daily/matches', 'GET', 'shopping_daily_matches');
        self::route('/shopping/daily/settings', 'POST', 'shopping_daily_settings', true, true);
        self::route('/shopping/daily/run', 'POST', 'shopping_daily_run', true, true);
        self::route('/shopping/clear-purchased', 'POST', 'clear_purchased', true);
        self::route('/shopping', 'POST', 'add_shopping');
        self::route('/shopping/(?P<id>\d+)', 'POST', 'toggle_shopping');
        self::route('/shopping/(?P<id>\d+)', 'DELETE', 'delete_shopping', true, true);
    }
    private static function valid_date(string $date): bool {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $dt !== false && $dt->format('Y-m-d') === $date;
    }
    public static function week(WP_REST_Request $r) {
        $start = sanitize_text_field((string)$r->get_param('start'));
        if (!self::valid_date($start)) { return new WP_Error('mp_date','Ugyldig dato.', ['status'=>400]); }
        $dt = new DateTimeImmutable($start, wp_timezone());
        $start = $dt->modify('monday this week')->format('Y-m-d');
        $end = $dt->modify('sunday this week')->format('Y-m-d');
        return ['start'=>$start, 'end'=>$end, 'people'=>MP_Storage::people(),
            'meals'=>MP_Storage::meals($start, $end), 'episodes'=>array_merge(MP_TVmaze::schedule($start, $end), MP_Movies::schedule($start,$end)),
            'can_edit'=>self::can_write()];
    }
    public static function recipes() { return MP_Storage::recipe_list(); }
    public static function recipe(WP_REST_Request $r) { return MP_Storage::recipe(absint($r['id'])); }
    public static function movies() { return MP_Movies::all(); }
    public static function movie_search(WP_REST_Request $r) {
        return MP_Movies::search(sanitize_text_field((string)$r->get_param('q')));
    }
    public static function add_movie(WP_REST_Request $r) {
        return MP_Movies::add([
            'tmdb_id'=>absint($r->get_param('tmdb_id')),
            'title'=>sanitize_text_field((string)$r->get_param('title')),
            'mode'=>sanitize_key((string)$r->get_param('mode')),
            'service'=>sanitize_text_field((string)$r->get_param('service')),
            'manual_date'=>sanitize_text_field((string)$r->get_param('manual_date')),
        ]);
    }
    public static function save_movie(WP_REST_Request $r) {
        $data=[];
        foreach (['mode','service','manual_date'] as $key) {
            if ($r->has_param($key)) {
                $data[$key]=sanitize_text_field((string)$r->get_param($key));
            }
        }
        return MP_Movies::update(absint($r['id']),$data);
    }
    public static function refresh_movie(WP_REST_Request $r) {
        return MP_Movies::refresh(absint($r['id']));
    }
    public static function delete_movie(WP_REST_Request $r) {
        return MP_Movies::remove(absint($r['id']));
    }
    public static function shows() { return MP_TVmaze::shows(); }
    public static function tv_search(WP_REST_Request $r) {
        return MP_TVmaze::search(sanitize_text_field((string)$r->get_param('q')));
    }
    public static function tv_seasons(WP_REST_Request $r) {
        return MP_TVmaze::season_summary(absint($r->get_param('id')));
    }
    public static function tv_details(WP_REST_Request $r) {
        return MP_TVmaze::details(absint($r->get_param('id')));
    }
    public static function save_meal(WP_REST_Request $r) {
        $date = sanitize_text_field((string)$r->get_param('date'));
        $person = (int)$r->get_param('person');
        $mode = sanitize_key((string)$r->get_param('mode'));
        $source = (int)$r->get_param('source_person');
        $title = sanitize_text_field((string)$r->get_param('title'));
        $recipe = absint($r->get_param('recipe_id'));
        if (!self::valid_date($date) || $person < 1 || $person > 4 || !in_array($mode, ['own','same'], true)) {
            return new WP_Error('mp_invalid','Ugyldigt måltid.', ['status'=>400]);
        }
        if ($mode === 'same' && ($source < 1 || $source > 4 || $source === $person)) {
            return new WP_Error('mp_invalid_ref', 'Vælg en anden gyldig person.', ['status'=>400]);
        }
        if ($mode === 'own') { $source = 0; }
        else { $title = ''; $recipe = 0; }
        if ($recipe) {
            $p = get_post($recipe);
            if (!$p || $p->post_type !== 'mp_recipe' || $p->post_status !== 'publish') {
                return new WP_Error('mp_invalid_recipe','Opskriften er ikke tilgængelig.', ['status'=>400]);
            }
            $title = get_the_title($recipe);
        }
        $result = MP_Storage::save_meal(compact('date','person','mode','source') + [
            'source_person'=>$source, 'title'=>$title, 'recipe_id'=>$recipe,
        ]);
        return is_wp_error($result) ? $result : ['saved'=>true];
    }
    public static function create_show(WP_REST_Request $r) {
        global $wpdb;
        $tvmaze = absint($r->get_param('tvmaze_id'));
        if (!$tvmaze) {
            return new WP_Error('mp_tv_id','Vælg først en serie fra søgeresultaterne.', ['status'=>400]);
        }
        $details=MP_TVmaze::details($tvmaze);
        if (is_wp_error($details)) { return $details; }

        // Canonical TVmaze name prevents an arbitrary show title supplied by a client.
        $name=sanitize_text_field((string)$details['title']);
        $service=sanitize_text_field((string)$r->get_param('service'));
        $streaming_result=null;
        if ($service === '') {
            $streaming_result=MP_Streaming::lookup($tvmaze);
            $service=count($streaming_result['matches'])===1 ? $streaming_result['matches'][0] : 'Ukendt';
        }
        if (!in_array($service,MP_Streaming::services(),true) && $service!=='Ukendt') {
            return new WP_Error('mp_bad_streaming_service','Vælg en kendt streamingtjeneste.',['status'=>400]);
        }
        $season=absint($r->get_param('season')) ?: (int)$details['suggested_season'];
        $seasonData=null;
        foreach ($details['seasons'] as $item) {
            if ((int)$item['season']===$season) { $seasonData=$item; break; }
        }

        $total=absint($r->get_param('season_total')) ?: (int)($seasonData['total'] ?? 0);
        $date=sanitize_text_field((string)$r->get_param('first_air_date'));
        if ($date==='') { $date=(string)($seasonData['first_date'] ?? ''); }
        $time=sanitize_text_field((string)$r->get_param('time_label'));
        $weeklyParam=$r->get_param('weekly');
        $weekly=$weeklyParam===null ? (int)($seasonData['weekly'] ?? false) : ($weeklyParam ? 1 : 0);

        if ($name==='' || $season<1 || $season>100 || $total>200 ||
            ($date!=='' && !self::valid_date($date))) {
            return new WP_Error('mp_show_invalid','Ugyldige serieoplysninger.', ['status'=>400]);
        }

        $shows=MP_Storage::table('shows');
        $exists=(int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $shows WHERE tvmaze_id=%d AND season=%d AND active=1 LIMIT 1",
            $tvmaze,$season
        ));
        if ($exists) {
            return new WP_Error('mp_show_exists', 'Serien og sæsonen findes allerede i den fælles watchlist.', ['status'=>409]);
        }
        $weekday=$date ? (int)(new DateTimeImmutable($date, wp_timezone()))->format('N') : 0;
        $wpdb->insert($shows, [
            'tvmaze_id'=>$tvmaze,
            'title'=>substr($name,0,255),
            'service'=>substr($service,0,100),
            'season'=>$season,
            'season_total'=>$total,
            'first_air_date'=>$date ?: null,
            'weekday'=>$weekday,
            'time_label'=>substr($time,0,40),
            'weekly'=>$weekly,
            'active'=>1,
            'updated_at'=>current_time('mysql'),
        ], ['%d','%s','%s','%d','%d','%s','%d','%s','%d','%s']);
        if (!$wpdb->insert_id) {
            return new WP_Error('mp_insert_failed','Kunne ikke tilføje serien.', ['status'=>500]);
        }
        $id=(int)$wpdb->insert_id;
        $sync=MP_TVmaze::sync_one($id);
        return [
            'id'=>$id,
            'detected'=>[
                'season'=>$season,
                'season_total'=>$total,
                'first_air_date'=>$date,
                'original_provider'=>$details['original_provider'],
                'weekly'=>$weekly,
            ],
            'sync'=>is_wp_error($sync) ? ['warning'=>$sync->get_error_message()] : $sync,
            'service'=>$service,
            'streaming_lookup'=>$streaming_result,
        ];
    }
    public static function delete_show(WP_REST_Request $r) {
        global $wpdb; $id = absint($r['id']);
        $wpdb->delete(MP_Storage::table('shows'), ['id'=>$id], ['%d']);
        $wpdb->delete(MP_Storage::table('episodes'), ['show_id'=>$id], ['%d']);
        $states=get_option('mp_watch_monitor_v1',[]);
        if (is_array($states) && isset($states[(string)$id])) {
            unset($states[(string)$id]);
            update_option('mp_watch_monitor_v1',$states,false);
        }
        $dk=get_option('mp_dk_schedules_v1',[]);
        if (is_array($dk) && isset($dk[(string)$id])) {
            unset($dk[(string)$id]);
            update_option('mp_dk_schedules_v1',$dk,false);
        }
        return ['deleted'=>true];
    }
    public static function sync_show(WP_REST_Request $r) { return MP_TVmaze::sync_one(absint($r['id'])); }
    public static function save_danish_schedule(WP_REST_Request $r) {
        $id=absint($r['id']);
        $date=sanitize_text_field((string)$r->get_param('first_date'));
        $weekly=rest_sanitize_boolean($r->get_param('weekly'));
        return MP_TVmaze::set_dk_schedule($id,$date,$weekly);
    }
    public static function shopping_flyers() { return MP_FlyerLibrary::listing(); }
    public static function shopping_ocr_status(): array { return MP_FlyerOCR::status(); }
    public static function shopping_ocr_matches(WP_REST_Request $r) {
        return MP_FlyerOCR::match_for_item(absint($r->get_param('id')));
    }
    public static function shopping_ocr_upload(WP_REST_Request $r) {
        $files=$r->get_file_params();
        return MP_FlyerOCR::scan_upload(
            is_array($files['image']??null)?$files['image']:[],
            sanitize_text_field((string)$r->get_param('store')),
            sanitize_text_field((string)$r->get_param('valid_to'))
        );
    }
    public static function shopping_ocr_import_text(WP_REST_Request $r) {
        return MP_FlyerOCR::import_text(
            (string)$r->get_param('text'),
            sanitize_text_field((string)$r->get_param('store')),
            sanitize_text_field((string)$r->get_param('valid_to'))
        );
    }
    public static function shopping_ocr_clear(): array { return MP_FlyerOCR::clear(); }
    public static function shopping_daily_status(): array {
        return MP_DailyOffers::status();
    }
    public static function shopping_daily_matches(WP_REST_Request $r) {
        return MP_DailyOffers::matches(absint($r->get_param('id')));
    }
    public static function shopping_daily_settings(WP_REST_Request $r): array {
        return MP_DailyOffers::set_enabled(rest_sanitize_boolean($r->get_param('enabled')));
    }
    public static function shopping_daily_run(): array {
        return MP_DailyOffers::tick();
    }
    public static function shopping_flyer_search(WP_REST_Request $r) {
        $id=$r->get_param('id');
        $store=$r->get_param('store');
        if (!is_scalar($id) || (string)$id!==(string)absint($id) || !is_string($store)) {
            return new WP_Error('mp_prej_flyer_input','Vælg en vare og en gyldig butik.',['status'=>400]);
        }
        return MP_OfferFlyers::scan(absint($id),sanitize_text_field($store));
    }
    public static function shopping_offers(WP_REST_Request $r) {
        $ids=$r->get_param('ids');
        if (!is_array($ids)) {
            return new WP_Error('mp_prej_ids','Angiv en liste af varer.', ['status'=>400]);
        }
        foreach ($ids as $id) {
            if (!is_scalar($id) || (string)$id!== (string)absint($id)) {
                return new WP_Error('mp_prej_id','Ugyldigt vare-ID.', ['status'=>400]);
            }
        }
        return MP_Offers::scan($ids);
    }
    public static function save_offer_stores(WP_REST_Request $r) {
        $stores=$r->get_param('stores');
        if (!is_array($stores)) {
            return new WP_Error('mp_offer_stores','Angiv en liste over butikker.',['status'=>400]);
        }
        return MP_ShoppingDetails::save_stores($stores);
    }
    public static function save_shopping_details(WP_REST_Request $r) {
        return MP_ShoppingDetails::save_item(absint($r['id']),[
            'title'=>$r->get_param('title'),
            'store'=>$r->get_param('store'),
            'quantity'=>$r->get_param('quantity'),
            'package_size'=>$r->get_param('package_size'),
            'price'=>$r->get_param('price'),
            'notes'=>$r->get_param('notes'),
            'favorite'=>$r->get_param('favorite'),
            'barcode'=>$r->get_param('barcode'),
        ]);
    }
    public static function upload_shopping_photo(WP_REST_Request $r) {
        $files=$r->get_file_params();
        if (!isset($files['photo']) || !is_array($files['photo'])) {
            return new WP_Error('mp_photo_missing','Vælg et billede.',['status'=>400]);
        }
        return MP_ShoppingDetails::attach_photo(absint($r['id']),$files['photo']);
    }
    public static function shopping_stores() {
        return ['options'=>MP_ShoppingStores::stores(),'configured'=>MP_Offers::configured(),
            'offer_options'=>MP_ShoppingDetails::available_offer_stores(),
            'offer_selected'=>MP_ShoppingDetails::selected_stores()];
    }
    public static function save_shopping_store(WP_REST_Request $r) {
        global $wpdb;
        $id=absint($r['id']);
        $table=MP_Storage::table('shopping');
        $row=$wpdb->get_row($wpdb->prepare("SELECT title FROM $table WHERE id=%d",$id),ARRAY_A);
        if (!$row) { return new WP_Error('mp_shopping_missing','Varen findes ikke.',['status'=>404]); }
        return MP_ShoppingStores::save((string)$row['title'],
            sanitize_text_field((string)$r->get_param('store')));
    }
    public static function shopping_history(): array { return MP_ShoppingHistory::suggestions(); }
    public static function shopping() {
        global $wpdb;
        $table = MP_Storage::table('shopping');
        $rows=$wpdb->get_results("SELECT id,title,amount,checked FROM $table ORDER BY checked ASC,id DESC LIMIT 500", ARRAY_A) ?: [];
        foreach ($rows as &$row) {
            $row['store']=MP_ShoppingStores::preferred((string)$row['title']);
            $row['details']=MP_ShoppingDetails::for_item($row);
        }
        unset($row);
        return $rows;
    }
    public static function add_shopping(WP_REST_Request $r) {
        global $wpdb;
        $title = sanitize_text_field((string)$r->get_param('title'));
        $amount = sanitize_text_field((string)$r->get_param('amount'));
        if (!$title || strlen($title) > 255) { return new WP_Error('mp_item_invalid','Indtast et varenavn.', ['status'=>400]); }
        $wpdb->insert(MP_Storage::table('shopping'), ['title'=>$title,'amount'=>substr($amount,0,80),'checked'=>0,'created_at'=>current_time('mysql')], ['%s','%s','%d','%s']);
        return ['id'=>(int)$wpdb->insert_id];
    }
    /**
     * Clear checked items only; preserve all unchecked rows, family store mapping,
     * and offer-filter preferences. Accessible to logged-in subscribers too.
     */
    public static function clear_purchased() {
        global $wpdb;
        $table=MP_Storage::table('shopping');
        $ids=$wpdb->get_col("SELECT id FROM $table WHERE checked=1");
        if (!is_array($ids)) {
            return new WP_Error('mp_clear_list','Kunne ikke hente købte varer.',['status'=>500]);
        }
        $deleted=0;
        foreach ($ids as $value) {
            $id=absint($value);
            if (!$id) { continue; }
            $row=$wpdb->get_row($wpdb->prepare(
                "SELECT id,title FROM $table WHERE id=%d AND checked=1",$id
            ),ARRAY_A);
            $result=$wpdb->delete($table,['id'=>$id,'checked'=>1],['%d','%d']);
            if ($result===false) {
                return new WP_Error('mp_clear_list','Sletningen blev afbrudt efter '.$deleted.' varer. Prøv igen.',['status'=>500]);
            }
            if ($result>0) {
                MP_ShoppingHistory::remember((string)($row['title']??''));
                MP_ShoppingDetails::delete_item($id);
                $deleted++;
            }
        }
        return ['deleted'=>$deleted,'saved'=>true];
    }
    public static function toggle_shopping(WP_REST_Request $r) {
        global $wpdb; $id = absint($r['id']);
        $checked = $r->get_param('checked') ? 1 : 0;
        $table=MP_Storage::table('shopping');
        $row=$wpdb->get_row($wpdb->prepare("SELECT id,title FROM $table WHERE id=%d",$id),ARRAY_A);
        if (!$row) { return new WP_Error('mp_item_missing','Varen findes ikke.', ['status'=>404]); }
        $result=$wpdb->update($table, ['checked'=>$checked], ['id'=>$id], ['%d'], ['%d']);
        if ($result===false) {
            return new WP_Error('mp_item_toggle_failed','Varen kunne ikke opdateres.', ['status'=>500]);
        }
        if ($checked) { MP_ShoppingHistory::remember((string)$row['title']); }
        return ['saved'=>true];
    }
    public static function delete_shopping(WP_REST_Request $r) {
        global $wpdb; $id=absint($r['id']);
        $wpdb->delete(MP_Storage::table('shopping'), ['id'=>$id], ['%d']);
        MP_ShoppingDetails::delete_item($id);
        return ['deleted'=>true];
    }
}
