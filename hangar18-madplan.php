<?php
/**
 * Plugin Name: Hangar18 Madplan & TV-plan
 * Plugin URI: https://github.com/phenixdk2020/Madplan
 * Update URI: https://github.com/phenixdk2020/Madplan
 * Description: Mobilvenlig familieportal med fælles TV-ugeplan, watchlist, madplan og opskrifter.
 * Version: 0.1.8
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: hangar18-madplan
 */
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }
define('MP_VERSION', '0.1.8');
define('MP_FILE', __FILE__);
define('MP_DIR', plugin_dir_path(__FILE__));
define('MP_URL', plugin_dir_url(__FILE__));

require_once MP_DIR . 'includes/class-mp-storage.php';
require_once MP_DIR . 'includes/class-mp-tvmaze.php';
require_once MP_DIR . 'includes/class-mp-rest.php';
require_once MP_DIR . 'includes/class-mp-portal.php';
require_once MP_DIR . 'includes/class-mp-home.php';
require_once MP_DIR . 'includes/class-mp-checkpoints.php';
require_once MP_DIR . 'includes/class-mp-updater.php';

final class MP_Plugin {
    public static function boot(): void {
        MP_Checkpoints::register();
        MP_Updater::register();
        MP_Home::register();
        add_action('init', [self::class, 'recipes']);
        add_action('init', [self::class, 'shortcodes']);
        add_action('rest_api_init', [MP_REST::class, 'register']);
        add_action('admin_menu', [self::class, 'admin_menu']);
        add_action('add_meta_boxes_mp_recipe', [self::class, 'recipe_meta_box']);
        add_action('save_post_mp_recipe', [self::class, 'save_recipe'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);
        add_action('mp_sync_tv', [MP_TVmaze::class, 'sync_all']);
        add_action('init', [self::class, 'ensure_cron']);
        add_filter('plugin_action_links_' . plugin_basename(MP_FILE), [self::class, 'links']);
    }
    public static function activate(): void {
        MP_Storage::install();
        if (!get_option('mp_people')) {
            update_option('mp_people', ['Person 1', 'Person 2', 'Person 3', 'Person 4']);
        }
        if (!get_option('mp_portal_page')) {
            $existing = get_page_by_path('familieplan');
            if ($existing) {
                if (has_shortcode((string)$existing->post_content, 'hangar18_portal')) {
                    update_option('mp_portal_page', (int)$existing->ID);
                }
            } else {
                $id = wp_insert_post([
                    'post_title' => 'Familieplan',
                    'post_name' => 'familieplan',
                    'post_content' => '[hangar18_portal]',
                    'post_type' => 'page',
                    'post_status' => 'publish',
                ], true);
                if (!is_wp_error($id)) { update_option('mp_portal_page', (int)$id); }
            }
        }
        self::ensure_cron();
        MP_Home::configure();
    }
    public static function deactivate(): void {
        wp_clear_scheduled_hook('mp_sync_tv');
        // Data never removed on deactivation.
    }
    public static function ensure_cron(): void {
        if (!wp_next_scheduled('mp_sync_tv')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'mp_sync_tv');
        }
    }
    public static function recipes(): void {
        register_post_type('mp_recipe', [
            'labels' => ['name' => 'Opskrifter', 'singular_name' => 'Opskrift', 'add_new_item' => 'Opret opskrift',
                'edit_item' => 'Rediger opskrift', 'menu_name' => 'Opskrifter'],
            'public' => false, 'show_ui' => true, 'show_in_menu' => 'mp-hangar18',
            'show_in_rest' => false, 'supports' => ['title', 'editor', 'thumbnail', 'revisions'],
            'capability_type' => 'post', 'menu_icon' => 'dashicons-carrot',
        ]);
    }
    public static function shortcodes(): void {
        add_shortcode('hangar18_portal', [MP_Portal::class, 'render']);
    }
    public static function links(array $links): array {
        array_unshift($links,
            '<a href="' . esc_url(admin_url('admin.php?page=mp-updates')) . '">Opdateringer</a>',
            '<a href="' . esc_url(admin_url('admin.php?page=mp-hangar18')) . '">Indstillinger</a>'
        );
        return $links;
    }
    public static function admin_menu(): void {
        add_menu_page('Hangar18', 'Hangar18', 'manage_options', 'mp-hangar18', [self::class, 'settings_page'], 'dashicons-calendar-alt', 28);
        add_submenu_page('mp-hangar18','Oversigt','Oversigt','manage_options','mp-hangar18',[self::class,'settings_page']);
        add_submenu_page('mp-hangar18','TV-guide','TV-guide','manage_options','mp-hangar18-tv',[self::class,'admin_tv']);
        add_submenu_page('mp-hangar18','Madplan','Madplan','manage_options','mp-hangar18-food',[self::class,'admin_food']);
    }
    public static function admin_tv(): void {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="wrap"><h1>TV-guide</h1><p>Her kan du se TV-ugeplanen og tilføje serier til jeres fælles watchlist.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(MP_Home::url('tv')) . '">Åbn TV-ugeplan</a> ';
        echo '<a class="button" href="' . esc_url(MP_Home::url('tv-watch')) . '">Administrer watchlist</a></p>';
        echo '<p>Bemærk: TV-ugen indeholder først afsnit, når der er tilføjet serier til watchlisten.</p></div>';
    }
    public static function admin_food(): void {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="wrap"><h1>Madplan</h1><p>Fælles aftensmad til fire personer.</p><a class="button button-primary" href="' .
            esc_url(MP_Home::url('food')) . '">Åbn madplan</a></div>';
    }
    public static function settings_page(): void {
        if (!current_user_can('manage_options')) { return; }
        if (isset($_POST['mp_save_settings'])) {
            check_admin_referer('mp_save_settings');
            $names = [];
            for ($i = 0; $i < 4; $i++) {
                $names[] = sanitize_text_field(wp_unslash($_POST['mp_person'][$i] ?? ''));
                if ($names[$i] === '') { $names[$i] = 'Person ' . ($i + 1); }
            }
            update_option('mp_people', $names);
            echo '<div class="notice notice-success"><p>Personnavne gemt.</p></div>';
        }
        $people = MP_Storage::people();
        $page_id = (int)get_option('mp_portal_page');
        echo '<div class="wrap"><h1>Hangar18 Madplan & TV-plan</h1>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=mp-updates')) .
             '">Åbn opdateringer</a> <a class="button" href="' . esc_url(admin_url('plugins.php')) .
             '">Installerede plugins</a></p>';
        echo '<p>Fælles familieportal. Alle loggede brugere kan tilføje og redigere madplan og watchlist. Administratorer administrerer opskrifter, personnavne og sletning.</p>';
        $front = (int)get_option('page_on_front') === $page_id && get_option('show_on_front') === 'page';
        $homeResult = sanitize_key((string)($_GET['mp_home_result'] ?? ''));
        if ($homeResult === 'set') { echo '<div class="notice notice-success"><p>Familieplan er nu forsiden.</p></div>'; }
        if ($homeResult === 'restored') { echo '<div class="notice notice-success"><p>Den tidligere WordPress-forside er gendannet.</p></div>'; }
        if ($homeResult === 'error') { echo '<div class="notice notice-error"><p>Familieplan kunne ikke oprettes. Kontroller WordPress Sider.</p></div>'; }
        echo '<h2>Forside og direkte adgang</h2>';
        echo '<p>Forsidestatus: <strong>' . esc_html($front ? 'Familieplan er forsiden' : 'WordPress viser en anden forside') . '</strong></p>';
        echo '<p><a class="button button-primary" href="' . esc_url(MP_Home::url()) . '" target="_blank" rel="noopener">Åbn forsiden</a> ';
        echo '<a class="button" href="' . esc_url(MP_Home::url('tv')) . '" target="_blank" rel="noopener">TV-guide</a> ';
        echo '<a class="button" href="' . esc_url(MP_Home::url('tv-watch')) . '" target="_blank" rel="noopener">Watchlist</a> ';
        echo '<a class="button" href="' . esc_url(MP_Home::url('food')) . '" target="_blank" rel="noopener">Madplan</a></p>';
        if (!$front) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mp_set_home">';
            wp_nonce_field('mp_set_home');
            echo '<button class="button button-primary" type="submit">Sæt Familieplan som forside</button></form>';
        } else {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="mp_restore_home">';
            wp_nonce_field('mp_restore_home');
            echo '<button class="button" type="submit">Gendan tidligere WordPress-forside</button></form>';
        }
        echo '<hr>';
        echo '<p>Portal: <a href="' . esc_url($page_id ? get_permalink($page_id) : home_url('/familieplan/')) . '" target="_blank" rel="noopener">Åbn Familieplan</a>. Indsæt <code>[hangar18_portal]</code> på en anden side efter behov.</p>';
        echo '<p>Opskrifter oprettes i menupunktet <strong>Hangar18 → Opskrifter</strong> på PC. Madplan og TV-watchlist kan redigeres af alle loggede brugere direkte på mobilen.</p>';
        echo '<form method="post">';
        wp_nonce_field('mp_save_settings');
        echo '<h2>Fire faste personer</h2><table class="form-table"><tbody>';
        foreach ($people as $i => $name) {
            echo '<tr><th scope="row">Person ' . ($i + 1) . '</th><td><input class="regular-text" type="text" name="mp_person[]" value="' . esc_attr($name) . '" required></td></tr>';
        }
        echo '</tbody></table><button type="submit" name="mp_save_settings" value="1" class="button button-primary">Gem</button></form></div>';
    }
    public static function recipe_meta_box(): void {
        add_meta_box('mp_recipe_details', 'Opskriftens detaljer og billeder', [self::class, 'recipe_fields'], 'mp_recipe', 'normal', 'high');
    }
    public static function recipe_fields(WP_Post $post): void {
        wp_nonce_field('mp_recipe_save', 'mp_recipe_nonce');
        $servings = (int)get_post_meta($post->ID, '_mp_servings', true) ?: 4;
        $minutes = (int)get_post_meta($post->ID, '_mp_minutes', true);
        $ingredients = (string)get_post_meta($post->ID, '_mp_ingredients', true);
        $gallery = array_filter(array_map('absint', (array)get_post_meta($post->ID, '_mp_gallery', true)));
        echo '<p><label>Antal portioner <input type="number" min="1" max="100" name="mp_servings" value="' . esc_attr((string)$servings) . '" style="width:75px"></label> &nbsp; ';
        echo '<label>Tilberedning (minutter) <input type="number" min="0" max="1440" name="mp_minutes" value="' . esc_attr((string)$minutes) . '" style="width:75px"></label></p>';
        echo '<p><label for="mp_ingredients"><strong>Ingredienser</strong> (én pr. linje: <code>mængde|enhed|vare</code>, fx <code>500|g|Hakket oksekød</code>)</label></p>';
        echo '<textarea id="mp_ingredients" name="mp_ingredients" style="width:100%;min-height:180px" placeholder="500|g|Hakket oksekød&#10;1|stk|Løg">' . esc_textarea($ingredients) . '</textarea>';
        echo '<p><strong>Opskrift og trin:</strong> Brug den almindelige WordPress-editor ovenfor. Her kan du også indsætte billeder direkte i hvert trin.</p>';
        echo '<h3>Flere billeder</h3><p>Vælg så mange billeder som ønsket i WordPress Mediebibliotek. Det fremhævede billede vælges separat i højre side.</p>';
        echo '<input type="hidden" id="mp-gallery-ids" name="mp_gallery" value="' . esc_attr(implode(',', $gallery)) . '">';
        echo '<div id="mp-gallery-preview" class="mp-admin-gallery">';
        foreach ($gallery as $id) {
            echo '<span data-id="' . esc_attr((string)$id) . '">' . wp_get_attachment_image($id, 'thumbnail') . '</span>';
        }
        echo '</div><p><button type="button" class="button" id="mp-gallery-add">Vælg billeder</button> ';
        echo '<button type="button" class="button" id="mp-gallery-clear">Ryd galleri</button></p>';
    }
    public static function save_recipe(int $post_id, WP_Post $post): void {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) { return; }
        if (!isset($_POST['mp_recipe_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mp_recipe_nonce'])), 'mp_recipe_save')) { return; }
        if (!current_user_can('edit_post', $post_id)) { return; }
        update_post_meta($post_id, '_mp_servings', max(1, min(100, absint($_POST['mp_servings'] ?? 4))));
        update_post_meta($post_id, '_mp_minutes', min(1440, absint($_POST['mp_minutes'] ?? 0)));
        $raw = sanitize_textarea_field(wp_unslash($_POST['mp_ingredients'] ?? ''));
        update_post_meta($post_id, '_mp_ingredients', substr($raw, 0, 20000));
        $gallery = array_values(array_unique(array_filter(array_map('absint', explode(',', (string)($_POST['mp_gallery'] ?? ''))))));
        $gallery = array_slice(array_values(array_filter($gallery, static function($id) { return wp_attachment_is_image($id); })), 0, 100);
        update_post_meta($post_id, '_mp_gallery', $gallery);
    }
    public static function admin_assets(string $hook): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'mp_recipe') { return; }
        wp_enqueue_media();
        wp_enqueue_script('mp-admin', MP_URL . 'assets/admin.js', ['jquery'], MP_VERSION, true);
        wp_enqueue_style('mp-admin', MP_URL . 'assets/admin.css', [], MP_VERSION);
    }
}
register_activation_hook(MP_FILE, [MP_Plugin::class, 'activate']);
register_deactivation_hook(MP_FILE, [MP_Plugin::class, 'deactivate']);
MP_Plugin::boot();
