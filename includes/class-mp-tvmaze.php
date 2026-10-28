<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * TVmaze contains international/original broadcast dates, NOT verified Danish availability.
 * Never treat TVmaze airtime as Europe/Copenhagen streaming time.
 */
final class MP_TVmaze {
    private const API = 'https://api.tvmaze.com';
    private static function get(string $path) {
        $res = wp_safe_remote_get(self::API . $path, [
            'timeout'=>12, 'headers'=>['Accept'=>'application/json', 'User-Agent'=>'Hangar18Madplan/0.1 (WordPress)'],
        ]);
        if (is_wp_error($res)) { return $res; }
        if (wp_remote_retrieve_response_code($res) !== 200) {
            return new WP_Error('mp_tvmaze_http', 'TVmaze kunne ikke svare. Prøv senere.', ['status'=>502]);
        }
        $json = json_decode(wp_remote_retrieve_body($res), true);
        return is_array($json) ? $json : new WP_Error('mp_tvmaze_json', 'Ugyldigt svar fra TVmaze.', ['status'=>502]);
    }
    public static function search(string $term) {
        $term = trim($term);
        if (strlen($term) < 2) { return []; }
        $key = 'mp_tv_search_' . md5(strtolower($term));
        $cached = get_transient($key);
        if ($cached !== false) { return $cached; }
        $result = self::get('/search/shows?q=' . rawurlencode($term));
        if (is_wp_error($result)) { return $result; }
        $found = [];
        foreach (array_slice($result, 0, 15) as $item) {
            $show = $item['show'] ?? [];
            if (empty($show['id'])) { continue; }
            $found[] = ['id'=>(int)$show['id'], 'name'=>sanitize_text_field($show['name'] ?? ''),
                'premiered'=>(string)($show['premiered'] ?? ''),
                'image'=>esc_url_raw($show['image']['medium'] ?? '')];
        }
        set_transient($key, $found, 6 * HOUR_IN_SECONDS);
        return $found;
    }
    public static function sync_one(int $id) {
        global $wpdb;
        $shows = MP_Storage::table('shows');
        $eps = MP_Storage::table('episodes');
        $show = $wpdb->get_row($wpdb->prepare("SELECT * FROM $shows WHERE id=%d", $id), ARRAY_A);
        if (!$show) { return new WP_Error('mp_no_show', 'Serien findes ikke.', ['status'=>404]); }
        $items = self::get('/shows/' . (int)$show['tvmaze_id'] . '/episodes');
        if (is_wp_error($items)) { return $items; }
        $count = 0;
        $first = null;
        foreach ($items as $item) {
            if (!is_array($item)) { continue; }
            $season = absint($item['season'] ?? 0);
            $number = absint($item['number'] ?? 0);
            if ($season !== (int)$show['season'] || $number < 1 || $number > 500) { continue; }
            $air = (string)($item['airdate'] ?? '');
            if ($air && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $air)) { $air = ''; }
            if ($number === 1 && $air) { $first = $air; }
            $wpdb->replace($eps, [
                'show_id'=>$id, 'season'=>$season, 'episode_no'=>$number,
                'title'=>sanitize_text_field($item['name'] ?? ''),
                'air_date'=>$air ?: null,
                'original_time'=>sanitize_text_field((string)($item['airtime'] ?? '')),
            ], ['%d','%d','%d','%s','%s','%s']);
            $count++;
        }
        if ($first && !$show['first_air_date']) {
            $wpdb->update($shows, ['first_air_date'=>$first], ['id'=>$id], ['%s'], ['%d']);
        }
        $wpdb->update($shows, ['updated_at'=>current_time('mysql')], ['id'=>$id], ['%s'], ['%d']);
        return ['imported'=>$count, 'source'=>'TVmaze – oprindelige/internationale datoer'];
    }
    public static function sync_all(): void {
        global $wpdb;
        $shows = MP_Storage::table('shows');
        $ids = $wpdb->get_col("SELECT id FROM $shows WHERE active=1 ORDER BY updated_at ASC LIMIT 10");
        foreach ($ids as $id) {
            self::sync_one((int)$id);
        }
    }
    public static function shows(): array {
        global $wpdb;
        $table = MP_Storage::table('shows');
        return $wpdb->get_results("SELECT id,tvmaze_id,title,service,season,season_total,first_air_date,weekday,time_label,weekly,updated_at FROM $table WHERE active=1 ORDER BY title ASC", ARRAY_A) ?: [];
    }
    public static function schedule(string $start, string $end): array {
        global $wpdb;
        $shows = self::shows();
        $table = MP_Storage::table('episodes');
        $out = [];
        foreach ($shows as $show) {
            $episodes = $wpdb->get_results($wpdb->prepare(
                "SELECT season,episode_no,title,air_date FROM $table WHERE show_id=%d AND season=%d ORDER BY episode_no",
                $show['id'], $show['season']), ARRAY_A) ?: [];
            $known = [];
            foreach ($episodes as $episode) {
                $n = (int)$episode['episode_no'];
                $known[$n] = $episode;
            }
            $count = (int)$show['season_total'];
            // If the user has not configured a total, only schedule explicitly known episodes.
            $count = $count > 0 ? min($count, 200) : max(array_keys($known ?: [0=>null]));
            for ($n = 1; $n <= $count; $n++) {
                $episode = $known[$n] ?? null;
                $date = $episode['air_date'] ?? null;
                $status = 'original';
                if (!$date && (int)$show['weekly'] === 1 && $show['first_air_date']) {
                    try {
                        $anchor = new DateTimeImmutable($show['first_air_date'], new DateTimeZone('UTC'));
                        $date = $anchor->modify('+' . (($n - 1) * 7) . ' days')->format('Y-m-d');
                        $status = 'estimated';
                    } catch (Exception $e) { $date = null; }
                }
                if (!$date || $date < $start || $date > $end) { continue; }
                $out[] = [
                    'date'=>$date, 'show_id'=>(int)$show['id'], 'show'=>$show['title'],
                    'service'=>$show['service'], 'season'=>(int)$show['season'], 'episode'=>$n,
                    'episode_title'=>(string)($episode['title'] ?? ''),
                    'status'=>$status, 'time_label'=>$show['time_label'],
                    'note'=>$status === 'estimated' ? 'Beregnet ugentlig dato – ikke bekræftet' : 'TVmaze: oprindelig/international dato – dansk premiere ikke bekræftet',
                ];
            }
        }
        usort($out, static function($a,$b) { return [$a['date'],$a['time_label'],$a['show']] <=> [$b['date'],$b['time_label'],$b['show']]; });
        return $out;
    }
}
