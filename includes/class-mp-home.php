<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Replaces the stock WordPress front page with the family portal once.
 * Never overwrites existing page content or repeatedly changes a user's homepage choice.
 */
final class MP_Home {
    private const SETUP = 'mp_home_configured_v1';
    private const PREVIOUS = 'mp_previous_home_settings_v1';

    public static function register(): void {
        add_action('init', [self::class,'maybe_configure'], 30);
        add_filter('template_include', [self::class,'template'], 99);
        add_action('admin_post_mp_restore_home', [self::class,'restore_home']);
        add_action('admin_post_mp_set_home', [self::class,'set_home']);
    }

    public static function maybe_configure(): void {
        if (get_option(self::SETUP, false)) { return; }
        self::configure();
    }

    public static function configure(): int|WP_Error {
        $page = self::portal_page();
        if (is_wp_error($page)) { return $page; }
        if (!get_option(self::PREVIOUS, false)) {
            add_option(self::PREVIOUS, [
                'show_on_front'=>(string)get_option('show_on_front','posts'),
                'page_on_front'=>(int)get_option('page_on_front',0),
            ], '', false);
        }
        update_option('show_on_front','page');
        update_option('page_on_front',$page);
        update_option('mp_portal_page',$page);
        update_option(self::SETUP, 1);
        return $page;
    }

    public static function portal_page(): int|WP_Error {
        $configured = (int)get_option('mp_portal_page');
        $candidate = $configured ? get_post($configured) : null;
        if ($candidate instanceof WP_Post && $candidate->post_type === 'page' &&
            $candidate->post_status === 'publish' &&
            has_shortcode((string)$candidate->post_content,'hangar18_portal')) {
            return $configured;
        }

        foreach (['familieplan','hangar18-familieplan'] as $slug) {
            $p = get_page_by_path($slug, OBJECT, 'page');
            if ($p && $p->post_status === 'publish' &&
                has_shortcode((string)$p->post_content,'hangar18_portal')) {
                update_option('mp_portal_page',(int)$p->ID);
                return (int)$p->ID;
            }
        }
        // If /familieplan already belongs to the user, don't modify it.
        $slug = get_page_by_path('familieplan',OBJECT,'page') ? 'hangar18-familieplan' : 'familieplan';
        $id = wp_insert_post([
            'post_title'=>'Familieplan',
            'post_name'=>$slug,
            'post_type'=>'page',
            'post_content'=>'[hangar18_portal]',
            'post_status'=>'publish',
            'comment_status'=>'closed',
        ],true);
        if (is_wp_error($id) || !$id) {
            return new WP_Error('mp_portal_create','Kunne ikke oprette Familieplan-siden.');
        }
        update_option('mp_portal_page',(int)$id);
        return (int)$id;
    }

    public static function template(string $template): string {
        $page=(int)get_option('mp_portal_page');
        if ($page > 0 && is_page($page)) {
            // Standalone app, independent of an installed WordPress theme.
            MP_Portal::assets();
            return MP_DIR . 'templates/portal-shell.php';
        }
        return $template;
    }

    public static function url(string $module = ''): string {
        $url=home_url('/');
        if ($module !== '') {
            $url .= '#' . rawurlencode($module);
        }
        return $url;
    }

    public static function set_home(): void {
        if (!current_user_can('manage_options')) { wp_die('Ingen adgang.'); }
        check_admin_referer('mp_set_home');
        $result=self::configure();
        $code=is_wp_error($result)?'error':'set';
        wp_safe_redirect(add_query_arg('mp_home_result',$code,admin_url('admin.php?page=mp-hangar18')));
        exit;
    }
    public static function restore_home(): void {
        if (!current_user_can('manage_options')) { wp_die('Ingen adgang.'); }
        check_admin_referer('mp_restore_home');
        $old=get_option(self::PREVIOUS,[]);
        if (is_array($old) && in_array((string)($old['show_on_front']??''),['posts','page'],true)) {
            update_option('show_on_front',(string)$old['show_on_front']);
            update_option('page_on_front',(int)($old['page_on_front']??0));
        } else {
            update_option('show_on_front','posts');
            update_option('page_on_front',0);
        }
        // Don't override the user's choice on the next page load.
        update_option(self::SETUP,1);
        wp_safe_redirect(add_query_arg('mp_home_result','restored',admin_url('admin.php?page=mp-hangar18')));
        exit;
    }
}
