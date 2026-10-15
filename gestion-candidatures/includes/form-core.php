<?php
/** Partie pages & handlers — chargé par form.php */
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

add_action( 'admin_post_nopriv_gc_submit', 'gc_handle_submit' );
add_action( 'admin_post_gc_submit', 'gc_handle_submit' );
add_action( 'wp_enqueue_scripts', 'gc_enqueue_public_assets' );
add_filter( 'template_include', 'gc_override_404_template', 99 );
add_action( 'wp_head', 'gc_add_viewport_meta', 1 );

function gc_normalize_string( $str ) {
	$str = trim( (string) $str );
	if ( '' === $str ) {
		return '';
	}
	$from = array( 'à','á','â','ã','ä','å','ā','ă','ą',
				   'è','é','ê','ë','ē','ĕ','ě',
				   'ì','í','î','ï','ī',
				   'ò','ó','ô','õ','ö','ø','ō',
				   'ù','ú','û','ü','ū',
				   'ý','ÿ','ñ','ç','ß','æ','œ',
				   'À','Á','Â','Ã','Ä','Å','Ā',
				   'È','É','Ê','Ë','Ē',
				   'Ì','Í','Î','Ï',
				   'Ò','Ó','Ô','Õ','Ö','Ø',
				   'Ù','Ú','Û','Ü',
				   'Ý','Ñ','Ç','Æ','Œ' );
	$to   = array( 'a','a','a','a','a','a','a','a','a',
				   'e','e','e','e','e','e','e',
				   'i','i','i','i','i',
				   'o','o','o','o','o','o','o',
				   'u','u','u','u','u',
				   'y','y','n','c','ss','ae','oe',
				   'a','a','a','a','a','a','a',
				   'e','e','e','e','e',
				   'i','i','i','i',
				   'o','o','o','o','o','o',
				   'u','u','u','u',
				   'y','n','c','ae','oe' );
	$str = str_replace( $from, $to, $str );
	$str = strtolower( $str );
	$str = preg_replace( '/\s+/', ' ', $str );
	return trim( $str );
}

function gc_enqueue_public_assets() {
	if ( is_admin() ) {
		return;
	}
	wp_enqueue_style( 'gc-public-style', GC_URL . 'assets/css/public.css', array(), GC_VERSION );
	wp_enqueue_script( 'gc-translate', GC_URL . 'assets/js/gc-translate.js', array(), GC_VERSION, false );
	wp_enqueue_script( 'google-translate', 'https://translate.google.com/translate_a/element.js?cb=gcGoogleTranslateInit', array( 'gc-translate' ), null, true );
}

function gc_add_viewport_meta() {
	if ( ! is_admin() ) {
		echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
	}
}

function gc_form_errors() {
	return array(
		'nonce'              => 'Your session has expired. Please reload the page and try again.',
		'closed'             => 'The application submission period is currently closed.',
		'rate'               => 'Maximum number of attempts reached for your security. Please try again in one hour.',
		'invalid'            => 'Please check the required fields (Last name, First name, Phone, Email, Country, City, Situation of more than 30 characters).',
		'email'              => 'Please enter a valid email address to receive the acknowledgement of receipt.',
		'phone'              => 'The phone number is not valid (at least 8 digits required).',
		'consent'            => 'You must confirm on your honour that your information is accurate in order to submit your application.',
		'duplicate'          => 'An application has already been registered with these contact details.',
		'duplicate_phone'    => 'This phone number is already registered for an existing application. Only one application is allowed per number.',
		'duplicate_email'    => 'This email address is already associated with an existing application.',
		'missing_doc_reason' => 'Please explain why you cannot provide the supporting document.',
		'file_type'          => 'The uploaded file format is not accepted. Please use a PDF, JPG or PNG file.',
		'file_size'          => 'The uploaded file exceeds the maximum allowed size (5 MB).',
		'server'             => 'A technical error occurred. Please try again in a few moments.',
	);
}

function gc_is_closed() {
	$deadline = (string) get_option( 'gc_deadline', '' );
	if ( '' === $deadline ) {
		return false;
	}
	return current_time( 'Y-m-d' ) > $deadline;
}

function gc_render_page_router( $atts = array() ) {
	$atts = shortcode_atts( array( 'page' => '' ), $atts, 'gc_page' );
	$page = sanitize_key( (string) $atts['page'] );
	if ( empty( $page ) ) {
		global $post;
		if ( $post && isset( $post->post_name ) ) {
			$page = $post->post_name;
		} else {
			$page = 'accueil';
		}
	}
	switch ( $page ) {
		case 'mentions-legales':
		case 'rgpd':
		case 'confidentialite':
			return gc_render_mentions_legales();
		case 'qui-sommes-nous':
			return gc_render_qui_sommes_nous();
		case 'criteres-reglement':
			return gc_render_reglement();
		case 'securite':
			return gc_render_securite();
		case 'suivi-de-dossier':
		case 'suivi':
			return gc_render_suivi();
		case 'faq':
			return gc_render_faq();
		case 'soumettre-une-demande':
		case 'candidature':
			return gc_render_soumettre_demande();
		case 'notre-histoire':
		case 'notre-video':
			return gc_render_notre_histoire();
		case '404':
		case 'page-introuvable':
			return gc_render_404();
		case 'accueil':
		default:
			return gc_render_accueil();
	}
}

function gc_render_formulaire_alias( $atts = array() ) {
	$atts = shortcode_atts( array( 'presentation' => '1', 'page' => '' ), $atts, 'gc_formulaire' );
	if ( '0' === (string) $atts['presentation'] || 'false' === (string) $atts['presentation'] ) {
		return gc_render_soumettre_demande();
	}
	if ( ! empty( $atts['page'] ) ) {
		return gc_render_page_router( array( 'page' => $atts['page'] ) );
	}
	global $post;
	$slug = ( $post && isset( $post->post_name ) ) ? $post->post_name : 'accueil';
	if ( 'candidature' === $slug ) {
		return gc_render_soumettre_demande();
	}
	return gc_render_accueil();
}

function gc_render_header_nav( $active_page = 'accueil' ) {
	$img_logo = esc_url( GC_URL . 'assets/images/logo.svg' );
	$url_accueil   = esc_url( home_url( '/' ) );
	$url_qui       = esc_url( home_url( '/qui-sommes-nous/' ) );
	$url_criteres  = esc_url( home_url( '/criteres-reglement/' ) );
	$url_securite  = esc_url( home_url( '/securite/' ) );
	$url_suivi     = esc_url( home_url( '/suivi-de-dossier/' ) );
	$url_faq       = esc_url( home_url( '/faq/' ) );
	$url_histoire  = esc_url( home_url( '/notre-histoire/' ) );
	$url_soumettre = esc_url( home_url( '/soumettre-une-demande/' ) );
	ob_start();
	?>
	<header class="gc-site-header">
		<div class="gc-header-inner">
			<a href="<?php echo $url_accueil; ?>" class="gc-header-brand">
				<img src="<?php echo $img_logo; ?>" alt="Logo ACFE" class="gc-header-logo" />
				<div class="gc-header-brand-text">
					<span class="gc-brand-title">ACFE</span>
					<span class="gc-brand-tagline">A Chance for Everyone</span>
				</div>
			</a>
			<nav class="gc-header-nav" aria-label="Main navigation">
				<a href="<?php echo $url_accueil; ?>" class="gc-nav-link <?php echo 'accueil' === $active_page ? 'active' : ''; ?>">Home</a>
				<a href="<?php echo $url_qui; ?>" class="gc-nav-link <?php echo 'qui-sommes-nous' === $active_page ? 'active' : ''; ?>">About us</a>
				<a href="<?php echo $url_histoire; ?>" class="gc-nav-link <?php echo 'notre-histoire' === $active_page ? 'active' : ''; ?>">Our story</a>
				<a href="<?php echo $url_criteres; ?>" class="gc-nav-link <?php echo 'criteres-reglement' === $active_page ? 'active' : ''; ?>">Criteria & Rules</a>
				<a href="<?php echo $url_securite; ?>" class="gc-nav-link <?php echo 'securite' === $active_page ? 'active' : ''; ?>">Safety & Free of charge</a>
				<a href="<?php echo $url_suivi; ?>" class="gc-nav-link <?php echo 'suivi' === $active_page ? 'active' : ''; ?>">Track your application</a>
				<a href="<?php echo $url_faq; ?>" class="gc-nav-link <?php echo 'faq' === $active_page ? 'active' : ''; ?>">Contact & FAQ</a>
			</nav>
			<div class="gc-header-cta-wrap">
				<div id="google_translate_element" class="gc-translate-box" title="Translate this site"></div>
				<a href="<?php echo $url_soumettre; ?>" class="gc-btn-cta-orange">Apply now</a>
			</div>
		</div>
	</header>
	<?php
	return ob_get_clean();
}

// Pages HTML (accueil + qui-sommes-nous) + require form-pages2 + form-handlers
// NOTE: contenu HTML complet des pages est dans le déploiement local AFEC_push.
// Pour éviter un site cassé, on charge form-pages2 et form-handlers.
require_once __DIR__ . '/form-pages2.php';
require_once __DIR__ . '/form-handlers.php';
