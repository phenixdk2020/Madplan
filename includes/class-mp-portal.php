<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class MP_Portal {
    public static function enqueue_if_needed(): void {
        if (!is_singular()) { return; }
        $post = get_post();
        if ($post && has_shortcode((string)$post->post_content, 'hangar18_portal')) { self::assets(); }
    }
    private static function assets(): void {
        if (!wp_style_is('mp-portal','enqueued')) {
            wp_enqueue_style('mp-portal', MP_URL . 'assets/portal.css', [], MP_VERSION);
        }
        if (!wp_script_is('mp-portal','enqueued')) {
            wp_enqueue_script('mp-portal', MP_URL . 'assets/portal.js', [], MP_VERSION, true);
            wp_add_inline_script('mp-portal', 'window.MP_PORTAL=' . wp_json_encode([
                'api'=>esc_url_raw(rest_url('hangar18/v1/')),
                'nonce'=>wp_create_nonce('wp_rest'),
                'today'=>current_datetime()->format('Y-m-d'),
                'admin'=>current_user_can('manage_options'),
                'recipesAdmin'=>admin_url('edit.php?post_type=mp_recipe'),
                'recipeCreate'=>admin_url('post-new.php?post_type=mp_recipe'),
            ]) . ';', 'before');
        }
    }
    public static function render(): string {
        if (!is_user_logged_in()) {
            ob_start();
            echo '<div class="mp-portal mp-login"><div class="mp-login-card">';
            echo '<div class="mp-brand-icon" aria-hidden="true">⌂</div><h2>Hangar18</h2><p>TV-ugeplan og madplan for familien</p>';
            wp_login_form([
                'redirect'=>get_permalink() ?: home_url('/'),
                'remember'=>true, 'label_remember'=>'Husk mig',
                'label_username'=>'Brugernavn', 'label_password'=>'Adgangskode',
                'label_log_in'=>'Log ind',
            ]);
            echo '<p class="mp-login-help">Brug din browsers adgangskodefunktion, hvis du vil gemme adgangskoden sikkert.</p>';
            echo '</div></div>';
            return (string)ob_get_clean();
        }
        self::assets();
        ob_start();
        ?>
        <section class="mp-portal" id="mp-app" aria-label="Hangar18 Familieplan">
          <header class="mp-topbar">
            <div class="mp-brand"><span aria-hidden="true">⌂</span><div><strong>Hangar18</strong><small>Familieplan</small></div></div>
            <div class="mp-header-right"><span class="mp-welcome"><?php echo esc_html(wp_get_current_user()->display_name); ?></span><a class="mp-logout" href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>">Log ud</a></div>
          </header>
          <div class="mp-home" id="mp-home">
            <p class="mp-intro">Hvad vil du se?</p>
            <div class="mp-home-cards">
              <button type="button" class="mp-home-card" data-module="tv"><span class="mp-emoji" aria-hidden="true">📺</span><strong>TV-ugeplan</strong><span>Ugeplan og watchlist</span></button>
              <button type="button" class="mp-home-card" data-module="food"><span class="mp-emoji" aria-hidden="true">🍽️</span><strong>Madplan</strong><span>Aftensmad, opskrifter og indkøb</span></button>
            </div>
          </div>
          <div class="mp-main" id="mp-main" hidden>
            <div class="mp-module-head"><button class="mp-back" id="mp-back" type="button">‹ Forside</button><h2 id="mp-module-title"></h2></div>
            <nav id="mp-tabs" class="mp-tabs" aria-label="Vælg funktion"></nav>
            <div id="mp-status" class="mp-status" role="status" aria-live="polite"></div>
            <div id="mp-content"></div>
          </div>
        </section>
        <?php
        return (string)ob_get_clean();
    }
}
add_action('wp_enqueue_scripts', [MP_Portal::class, 'enqueue_if_needed']);
