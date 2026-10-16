<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode( 'gc_page', 'gc_render_page_router' );
add_shortcode( 'gc_formulaire', 'gc_render_formulaire_alias' );
add_shortcode( 'gc_accueil', 'gc_render_accueil' );
add_shortcode( 'gc_qui_sommes_nous', 'gc_render_qui_sommes_nous' );
add_shortcode( 'gc_reglement', 'gc_render_reglement' );
add_shortcode( 'gc_securite', 'gc_render_securite' );
add_shortcode( 'gc_suivi', 'gc_render_suivi' );
add_shortcode( 'gc_faq', 'gc_render_faq' );
add_shortcode( 'gc_soumettre_demande', 'gc_render_soumettre_demande' );
add_shortcode( 'gc_notre_histoire', 'gc_render_notre_histoire' );
add_shortcode( 'gc_404', 'gc_render_404' );
add_shortcode( 'gc_mentions_legales', 'gc_render_mentions_legales' );

add_action( 'wp_enqueue_scripts', 'gc_enqueue_public_assets' );
add_action( 'wp_head', 'gc_add_viewport_meta', 1 );
add_filter( 'template_include', 'gc_override_404_template', 99 );
add_action( 'admin_post_nopriv_gc_submit_application', 'gc_handle_submit' );
add_action( 'admin_post_gc_submit_application', 'gc_handle_submit' );
add_action( 'admin_post_gc_download_doc', 'gc_serve_private_download' );

function gc_enqueue_public_assets() {
	$css_path = GC_DIR . 'assets/css/public.css';
	$ver = file_exists( $css_path ) ? (string) filemtime( $css_path ) : GC_VERSION;
	if ( file_exists( $css_path ) ) {
		wp_enqueue_style( 'gc-public', GC_URL . 'assets/css/public.css', array(), $ver );
	}
}

function gc_add_viewport_meta() {
	echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
}

function gc_form_errors() {
	return array(
		'nonce'              => 'Your session has expired. Please reload the page and try again.',
		'rate'               => 'Too many attempts. Please try again later.',
		'server'             => 'A technical error occurred. Please try again shortly.',
		'email'              => 'Please enter a valid email address.',
		'phone'              => 'The phone number is not valid (at least 8 digits required).',
		'consent'            => 'You must confirm on your honour that your information is accurate.',
		'duplicate'          => 'An application has already been registered with these contact details.',
		'missing_doc_reason' => 'Please explain why you cannot provide the supporting document.',
		'file_type'          => 'The uploaded file format is not accepted. Please use PDF, JPG or PNG.',
		'file_size'          => 'The uploaded file exceeds the maximum allowed size (5 MB).',
	);
}

function gc_render_page_router( $atts = array() ) {
	$atts = shortcode_atts( array( 'slug' => 'accueil' ), $atts, 'gc_page' );
	$slug = sanitize_key( $atts['slug'] );
	$map  = array(
		'accueil'             => 'gc_render_accueil',
		'qui-sommes-nous'     => 'gc_render_qui_sommes_nous',
		'reglement'           => 'gc_render_reglement',
		'securite'            => 'gc_render_securite',
		'suivi'               => 'gc_render_suivi',
		'faq'                 => 'gc_render_faq',
		'soumettre-demande'   => 'gc_render_soumettre_demande',
		'notre-histoire'      => 'gc_render_notre_histoire',
		'mentions-legales'    => 'gc_render_mentions_legales',
	);
	if ( isset( $map[ $slug ] ) && function_exists( $map[ $slug ] ) ) {
		return call_user_func( $map[ $slug ] );
	}
	return function_exists( 'gc_render_accueil' ) ? gc_render_accueil() : '';
}

function gc_render_formulaire_alias( $atts = array() ) {
	return function_exists( 'gc_render_soumettre_demande' ) ? gc_render_soumettre_demande() : '';
}

function gc_render_header_nav( $active_page = 'accueil' ) {
	$links = array(
		'accueil'           => array( 'url' => home_url( '/' ), 'label' => 'Home' ),
		'qui-sommes-nous'   => array( 'url' => home_url( '/qui-sommes-nous/' ), 'label' => 'About' ),
		'reglement'         => array( 'url' => home_url( '/reglement/' ), 'label' => 'Rules' ),
		'securite'          => array( 'url' => home_url( '/securite/' ), 'label' => 'Security' ),
		'suivi'             => array( 'url' => home_url( '/suivi-de-dossier/' ), 'label' => 'Track' ),
		'faq'               => array( 'url' => home_url( '/faq/' ), 'label' => 'FAQ' ),
		'soumettre-demande' => array( 'url' => home_url( '/soumettre-une-demande/' ), 'label' => 'Apply' ),
	);
	ob_start();
	?>
	<header class="gc-site-header">
		<div class="gc-header-inner">
			<a class="gc-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">ACFE</a>
			<nav class="gc-nav">
				<?php foreach ( $links as $key => $item ) : ?>
					<a class="gc-nav-link<?php echo $active_page === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>
	</header>
	<?php
	return ob_get_clean();
}

function gc_override_404_template( $template ) {
	if ( is_404() ) {
		$custom = GC_DIR . 'templates/404.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	return $template;
}
