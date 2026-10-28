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
    private static function route(string $path, string $method, string $callback, bool $write = false, bool $admin_only = false): void {
        register_rest_route(self::NS, $path, [
            'methods'=>$method, 'callback'=>[self::class, $callback],
            'permission_callback'=>[self::class, $admin_only ? 'can_manage' : ($write ? 'can_write' : 'can_read')],
        ]);
    }
    public static function register(): void {
        self::route('/theme', 'POST', 'save_theme', true);
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
        self::route('/shopping', 'GET', 'shopping');
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
            'meals'=>MP_Storage::meals($start, $end), 'episodes'=>MP_TVmaze::schedule($start, $end),
            'can_edit'=>self::can_write()];
    }
    public static function recipes() { return MP_Storage::recipe_list(); }
    public static function recipe(WP_REST_Request $r) { return MP_Storage::recipe(absint($r['id'])); }
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
        $service=$service !== '' ? $service : 'Ukendt';
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
        return ['deleted'=>true];
    }
    public static function sync_show(WP_REST_Request $r) { return MP_TVmaze::sync_one(absint($r['id'])); }
    public static function shopping() {
        global $wpdb;
        $table = MP_Storage::table('shopping');
        return $wpdb->get_results("SELECT id,title,amount,checked FROM $table ORDER BY checked ASC,id DESC LIMIT 500", ARRAY_A) ?: [];
    }
    public static function add_shopping(WP_REST_Request $r) {
        global $wpdb;
        $title = sanitize_text_field((string)$r->get_param('title'));
        $amount = sanitize_text_field((string)$r->get_param('amount'));
        if (!$title || strlen($title) > 255) { return new WP_Error('mp_item_invalid','Indtast et varenavn.', ['status'=>400]); }
        $wpdb->insert(MP_Storage::table('shopping'), ['title'=>$title,'amount'=>substr($amount,0,80),'checked'=>0,'created_at'=>current_time('mysql')], ['%s','%s','%d','%s']);
        return ['id'=>(int)$wpdb->insert_id];
    }
    public static function toggle_shopping(WP_REST_Request $r) {
        global $wpdb; $id = absint($r['id']);
        $checked = $r->get_param('checked') ? 1 : 0;
        $wpdb->update(MP_Storage::table('shopping'), ['checked'=>$checked], ['id'=>$id], ['%d'], ['%d']);
        return ['saved'=>true];
    }
    public static function delete_shopping(WP_REST_Request $r) {
        global $wpdb; $wpdb->delete(MP_Storage::table('shopping'), ['id'=>absint($r['id'])], ['%d']);
        return ['deleted'=>true];
    }
}
