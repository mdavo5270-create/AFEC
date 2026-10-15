<?php
/**
 * Must-Use Plugin : Gestion Candidatures — Style Loader & Viewport
 *
 * Charge et injecte la feuille de style publique du plugin gestion-candidatures
 * et s'assure que le viewport et la responsivité soient actifs sur tous les terminaux.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) {
		return;
	}

	$css_file = WP_CONTENT_DIR . '/plugins/gestion-candidatures/assets/css/public.css';
	if ( ! file_exists( $css_file ) ) {
		return;
	}

	$version = (string) filemtime( $css_file );
	$css_url = content_url( 'plugins/gestion-candidatures/assets/css/public.css' );

	wp_enqueue_style(
		'gc-public-style',
		$css_url,
		array(),
		$version
	);
}, 20 );

/**
 * Injection directe dans le <head> : garantit que le CSS et le viewport
 * sont appliqués même avec les thèmes WordPress FSE (Full Site Editing).
 */
add_action( 'wp_head', function () {
	if ( is_admin() ) {
		return;
	}

	// 1. Méta viewport automatique
	echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";

	// 2. CSS inline direct pour neutraliser tout cache ou blocage de thème FSE
	$css_file = WP_CONTENT_DIR . '/plugins/gestion-candidatures/assets/css/public.css';
	if ( file_exists( $css_file ) ) {
		$css_content = file_get_contents( $css_file );
		if ( ! empty( $css_content ) ) {
			echo '<style id="gc-public-embedded-style">' . $css_content . '</style>' . "\n";
		}
	}
}, 1 );

/**
 * Supprime le titre de page automatique injecté par le thème FSE (wp:post-title).
 * Empêche l'affichage du titre "Accueil – ACFE" au-dessus du header personnalisé.
 */
add_filter( 'render_block', function ( $block_content, $block ) {
	if ( is_admin() ) {
		return $block_content;
	}
	if ( isset( $block['blockName'] ) && 'core/post-title' === $block['blockName'] ) {
		return '';
	}
	return $block_content;
}, 10, 2 );

/**
 * Supprime la barre d'administration WordPress sur le front-end
 * et neutralise les marges/espaces par défaut du thème Twenty Twenty-Five.
 */
add_action( 'wp_head', function () {
	if ( is_admin() ) {
		return;
	}
	echo '<style id="gc-theme-reset">
/* Masquer la barre admin WordPress sur le front-end */
#wpadminbar { display: none !important; }
html { margin-top: 0 !important; }

/* Supprimer les espaces verticaux du thème FSE Twenty Twenty-Five */
.wp-site-blocks {
	padding-top: 0 !important;
	padding-bottom: 0 !important;
	margin-top: 0 !important;
}
.wp-block-template-part,
.wp-block-group.wp-block-group-is-layout-constrained {
	padding-top: 0 !important;
	padding-bottom: 0 !important;
	margin-top: 0 !important;
	margin-bottom: 0 !important;
}
main.wp-block-group,
main#primary,
.site-content,
.entry-content {
	padding-top: 0 !important;
	margin-top: 0 !important;
}
</style>' . "\n";
}, 5 );

/**
 * Désactivation totale des émojis WordPress (scripts, styles, prefetch DNS)
 */
add_action( 'init', function () {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'tiny_mce_plugins', function ( $plugins ) {
		return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
	} );
	add_filter( 'wp_resource_hints', function ( $urls, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$urls = array_filter( $urls, function ( $url ) {
				return false === strpos( $url, 's.w.org' );
			} );
		}
		return $urls;
	}, 10, 2 );
} );
