<?php
/**
 * A focused full-page mobile-first shell for the Hangar18 portal.
 * Deliberately bypasses the WordPress theme header, posts and navigation.
 */
if (!defined('ABSPATH')) { exit; }
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#122942">
  <title><?php echo esc_html('Familieplan · ' . get_bloginfo('name')); ?></title>
  <?php wp_head(); ?>
  <style>
    html,body{min-height:100%;margin:0}
    body.mp-full-page{background:#f5f8fd;color:#182738;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
    body.mp-full-page .mp-portal{padding:0 12px}
    body.mp-full-page .mp-portal.mp-login{padding:30px 16px}
    body.mp-full-page a:focus-visible,body.mp-full-page button:focus-visible{outline:3px solid #ffb703;outline-offset:3px}
    @media(min-width:960px){body.mp-full-page .mp-portal{padding:0 22px}}
  </style>
</head>
<body <?php body_class(['mp-full-page', 'mp-theme-' . MP_REST::current_theme()]); ?>>
<?php wp_body_open(); ?>
<main id="main" role="main">
  <?php echo MP_Portal::render(); // HTML is escaped in portal renderer. ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
