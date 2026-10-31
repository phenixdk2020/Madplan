<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class MP_Portal {
    /**
     * The WordPress profile first name is authoritative. If it has not been
     * filled in, use only the first word of the public display name.
     */
    public static function greeting_name($user): string {
        $first = sanitize_text_field(trim((string)($user->first_name ?? '')));
        if ($first !== '') { return $first; }
        $display = sanitize_text_field(trim((string)($user->display_name ?? '')));
        if ($display === '' || is_email($display)) { return ''; }
        $parts = preg_split('/\\s+/u', $display, 2);
        return sanitize_text_field((string)($parts[0] ?? ''));
    }
    public static function greeting($user): string {
        $name = self::greeting_name($user);
        return $name !== '' ? 'Velkommen ' . $name : 'Velkommen tilbage';
    }
    public static function enqueue_if_needed(): void {
        if (!is_singular()) { return; }
        $post = get_post();
        if ($post && has_shortcode((string)$post->post_content, 'hangar18_portal')) { self::assets(); }
    }
    public static function assets(): void {
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
                'canEdit'=>is_user_logged_in(),
                'theme'=>MP_REST::current_theme(),
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
        $theme = MP_REST::current_theme();
        $themes = [
            'default' => 'Default',
            'dark' => 'Mørk',
            'forest' => 'Skovgrøn',
            'purple' => 'Lilla',
            'sand' => 'Sand',
            'ocean' => 'Havblå',
            'rose' => 'Rosa',
            'sunset' => 'Solnedgang',
            'mint' => 'Mint',
            'burgundy' => 'Bordeaux',
        ];
        ob_start();
        ?>
        <section class="mp-portal" id="mp-app" data-mp-theme="<?php echo esc_attr($theme); ?>" aria-label="Hangar18 Familieplan">
          <header class="mp-topbar">
            <div class="mp-brand"><span aria-hidden="true">⌂</span><div><strong><?php echo esc_html(self::greeting(wp_get_current_user())); ?></strong><small>Familieplan</small></div></div>
            <div class="mp-header-right">
              <label class="mp-theme-control" for="mp-theme-select">
                <span>Farvetema</span>
                <select id="mp-theme-select" name="mp-theme" aria-label="Vælg dit personlige farvetema">
                  <?php foreach ($themes as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($theme, $value); ?>><?php echo esc_html($label); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <a class="mp-logout" href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>">Log ud</a>
            </div>
          </header>
          <div class="mp-home" id="mp-home">
            <p class="mp-intro">Hvad vil du se?</p>
            <div class="mp-home-cards">
              <button type="button" class="mp-home-card" data-module="tv"><span class="mp-emoji" aria-hidden="true">📺</span><strong>TV-guide</strong><span>Serier, film og kommende premierer</span></button>
              <button type="button" class="mp-home-card" data-module="food"><span class="mp-emoji" aria-hidden="true">🍽️</span><strong>Madplan</strong><span>Tilføj aftensmad til de fire personer</span></button>
            </div>
          </div>
          <div class="mp-main" id="mp-main" hidden>
            <div class="mp-module-head"><button class="mp-back" id="mp-back" type="button">← Tilbage til forsiden</button><h2 id="mp-module-title"></h2></div>
            <nav id="mp-tabs" class="mp-tabs" aria-label="Vælg funktion"></nav>
            <div id="mp-status" class="mp-status" role="status" aria-live="polite"></div>
            <div id="mp-content"></div>
            <footer class="mp-portal-footer"><button type="button" class="mp-btn mp-btn-primary mp-return-home" id="mp-back-bottom">← Tilbage til forsiden</button></footer>
          </div>
        </section>
        <?php
        return (string)ob_get_clean();
    }
}
add_action('wp_enqueue_scripts', [MP_Portal::class, 'enqueue_if_needed']);
