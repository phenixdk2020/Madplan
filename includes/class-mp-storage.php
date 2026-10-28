<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class MP_Storage {
    public static function table(string $suffix): string {
        global $wpdb;
        return $wpdb->prefix . 'mp_' . $suffix;
    }
    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $meals = self::table('meals');
        $shows = self::table('shows');
        $episodes = self::table('episodes');
        $shopping = self::table('shopping');
        $audit = self::table('audit');
        dbDelta("CREATE TABLE $meals (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            meal_date date NOT NULL,
            person tinyint(3) unsigned NOT NULL,
            mode varchar(12) NOT NULL DEFAULT 'own',
            title varchar(255) NOT NULL DEFAULT '',
            recipe_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source_person tinyint(3) unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY day_person (meal_date,person)
        ) $charset;");
        dbDelta("CREATE TABLE $shows (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tvmaze_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            service varchar(100) NOT NULL DEFAULT '',
            season smallint unsigned NOT NULL DEFAULT 1,
            season_total smallint unsigned NOT NULL DEFAULT 0,
            first_air_date date DEFAULT NULL,
            weekday tinyint unsigned NOT NULL DEFAULT 0,
            time_label varchar(40) NOT NULL DEFAULT '',
            weekly tinyint unsigned NOT NULL DEFAULT 0,
            active tinyint unsigned NOT NULL DEFAULT 1,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY tvmaze_id (tvmaze_id)
        ) $charset;");
        dbDelta("CREATE TABLE $episodes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            show_id bigint(20) unsigned NOT NULL,
            season smallint unsigned NOT NULL,
            episode_no smallint unsigned NOT NULL,
            title varchar(255) NOT NULL DEFAULT '',
            air_date date DEFAULT NULL,
            original_time varchar(40) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            UNIQUE KEY show_episode (show_id,season,episode_no),
            KEY air_date (air_date)
        ) $charset;");
        dbDelta("CREATE TABLE $shopping (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            amount varchar(80) NOT NULL DEFAULT '',
            checked tinyint unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset;");
        dbDelta("CREATE TABLE $audit (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            object_key varchar(100) NOT NULL,
            old_value longtext NOT NULL,
            new_value longtext NOT NULL,
            actor bigint(20) unsigned NOT NULL,
            changed_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY object_key (object_key),
            KEY changed_at (changed_at)
        ) $charset;");
        update_option('mp_db_version', '1');
    }
    public static function people(): array {
        $people = get_option('mp_people', ['Person 1','Person 2','Person 3','Person 4']);
        if (!is_array($people)) { $people = []; }
        return array_map(static function($i) use ($people) {
            return (string)($people[$i] ?? ('Person ' . ($i + 1)));
        }, range(0, 3));
    }
    public static function meals(string $start, string $end): array {
        global $wpdb;
        $table = self::table('meals');
        return $wpdb->get_results($wpdb->prepare("SELECT meal_date,person,mode,title,recipe_id,source_person FROM $table WHERE meal_date BETWEEN %s AND %s ORDER BY meal_date,person", $start,$end), ARRAY_A) ?: [];
    }
    public static function save_meal(array $v) {
        global $wpdb;
        $table = self::table('meals');
        $date = $v['date'];
        $person = $v['person'];
        $old = $wpdb->get_row($wpdb->prepare("SELECT meal_date,person,mode,title,recipe_id,source_person FROM $table WHERE meal_date=%s AND person=%d", $date, $person), ARRAY_A);
        $all = $wpdb->get_results($wpdb->prepare("SELECT person,mode,source_person FROM $table WHERE meal_date=%s", $date), ARRAY_A) ?: [];
        $refs = [];
        foreach ($all as $row) {
            if ($row['mode'] === 'same') { $refs[(int)$row['person']] = (int)$row['source_person']; }
        }
        unset($refs[$person]);
        if ($v['mode'] === 'same') { $refs[$person] = $v['source_person']; }
        foreach (range(1,4) as $start) {
            $seen = []; $cur = $start;
            while (isset($refs[$cur])) {
                if (isset($seen[$cur])) { return new WP_Error('mp_cycle', 'Henvisninger mellem personer må ikke danne en cirkel.', ['status'=>400]); }
                $seen[$cur] = true;
                $cur = $refs[$cur];
            }
        }
        $data = [
            'meal_date'=>$date, 'person'=>$person, 'mode'=>$v['mode'], 'title'=>$v['title'],
            'recipe_id'=>$v['recipe_id'], 'source_person'=>$v['source_person'],
            'updated_at'=>current_time('mysql'), 'updated_by'=>get_current_user_id(),
        ];
        $saved = $wpdb->replace($table, $data, ['%s','%d','%s','%s','%d','%d','%s','%d']);
        if ($saved === false) { return new WP_Error('mp_save_error','Kunne ikke gemme måltid.', ['status'=>500]); }
        self::audit('meal', $date . '#' . $person, $old, $data);
        return true;
    }
    public static function audit(string $type, string $key, $old, $new): void {
        global $wpdb;
        $wpdb->insert(self::table('audit'), [
            'event_type'=>$type, 'object_key'=>$key, 'old_value'=>wp_json_encode($old),
            'new_value'=>wp_json_encode($new), 'actor'=>get_current_user_id(), 'changed_at'=>current_time('mysql'),
        ], ['%s','%s','%s','%s','%d','%s']);
    }
    public static function recipe_list(): array {
        $posts = get_posts(['post_type'=>'mp_recipe', 'post_status'=>'publish', 'posts_per_page'=>300, 'orderby'=>'title', 'order'=>'ASC']);
        return array_map(static function($p) { return ['id'=>(int)$p->ID, 'title'=>$p->post_title, 'cover'=>get_the_post_thumbnail_url($p, 'medium') ?: '']; }, $posts);
    }
    public static function recipe(int $id) {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'mp_recipe' || $post->post_status !== 'publish') {
            return new WP_Error('mp_recipe_missing', 'Opskriften findes ikke.', ['status'=>404]);
        }
        $raw = (string)get_post_meta($id, '_mp_ingredients', true);
        $ingredients = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            if (trim($line) === '') { continue; }
            $parts = explode('|', $line, 3);
            $ingredients[] = count($parts) === 3
                ? ['amount'=>trim($parts[0]),'unit'=>trim($parts[1]),'name'=>trim($parts[2])]
                : ['amount'=>'','unit'=>'','name'=>trim($line)];
        }
        $gallery = array_filter(array_map('absint', (array)get_post_meta($id, '_mp_gallery', true)));
        $images = [];
        foreach ($gallery as $image_id) {
            $url = wp_get_attachment_image_url($image_id, 'large');
            if ($url) { $images[] = ['url'=>$url, 'alt'=>get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: $post->post_title]; }
        }
        return [
            'id'=>$id, 'title'=>$post->post_title, 'html'=>wp_kses_post(apply_filters('the_content', $post->post_content)),
            'cover'=>get_the_post_thumbnail_url($id, 'large') ?: '',
            'images'=>$images, 'servings'=>(int)get_post_meta($id,'_mp_servings',true) ?: 4,
            'minutes'=>(int)get_post_meta($id,'_mp_minutes',true), 'ingredients'=>$ingredients,
        ];
    }
}
