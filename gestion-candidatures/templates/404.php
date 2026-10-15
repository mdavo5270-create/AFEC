<?php
/**
 * Template 404 ACFE — remplace le modèle du thème.
 *
 * @package GestionCandidatures
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

status_header( 404 );
nocache_headers();

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( 'Page not found — ACFE' ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'gc-404-body' ); ?>>
<?php wp_body_open(); ?>
<main class="gc-404-main" id="main">
	<?php echo gc_render_404(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
