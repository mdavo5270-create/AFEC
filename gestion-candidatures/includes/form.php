<?php
/**
 * Portail solidaire ACFE — Gestion des 7 pages distinctes & formulaire public
 *
 * Pages gérées :
 * - accueil : Hero split (gauche texte & boutons, droite 3 cartes de statistiques blanches)
 * - qui-sommes-nous : Richard & Debbie, histoire, équipe, chronologie 2024-2025
 * - criteres-reglement : Qui peut soumettre, 5 critères d'analyse (01-04), exclusions, calendrier
 * - securite : Gratuité absolue, parabole du don, anti-arnaque, 4 étapes
 * - suivi-de-dossier : Recherche par identifiant et téléphone
 * - faq : Questions fréquentes, mentions légales, contact officiel WhatsApp
 * - soumettre-une-demande : Formulaire de demande sécurisé avec téléversement de CNI
 * - 404 / page-introuvable : Page d’erreur personnalisée ACFE
 *
 * @package GestionCandidatures
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Enregistrement des shortcodes
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

// Traitement du formulaire
add_action( 'admin_post_nopriv_gc_submit', 'gc_handle_submit' );
add_action( 'admin_post_gc_submit', 'gc_handle_submit' );
add_action( 'wp_enqueue_scripts', 'gc_enqueue_public_assets' );
add_filter( 'template_include', 'gc_override_404_template', 99 );

/**
 * Normalise une chaîne : suppression des accents, minuscules, espaces réduits.
 * Permet une comparaison insensible à la casse et aux accents.
 *
 * @param string $str Chaîne brute.
 * @return string
 */
function gc_normalize_string( $str ) {
	$str = trim( (string) $str );
	if ( '' === $str ) {
		return '';
	}
	// Translittération des caractères accentués
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

add_action( 'wp_head', 'gc_add_viewport_meta', 1 );

/**
 * Charge la feuille de style publique sur toutes les pages front.
 */
function gc_enqueue_public_assets() {
	if ( is_admin() ) {
		return;
	}
	wp_enqueue_style(
		'gc-public-style',
		GC_URL . 'assets/css/public.css',
		array(),
		GC_VERSION
	);

	wp_enqueue_script(
		'gc-translate',
		GC_URL . 'assets/js/gc-translate.js',
		array(),
		GC_VERSION,
		false
	);

	wp_enqueue_script(
		'google-translate',
		'https://translate.google.com/translate_a/element.js?cb=gcGoogleTranslateInit',
		array( 'gc-translate' ),
		null,
		true
	);
}

/**
 * Ajoute la balise viewport indispensable pour le responsive mobile.
 */
function gc_add_viewport_meta() {
	if ( ! is_admin() ) {
		echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
	}
}

/**
 * Messages d'erreur du formulaire.
 *
 * @return array<string,string>
 */
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

/**
 * La période de dépôt est-elle terminée ?
 *
 * @return bool
 */
function gc_is_closed() {
	$deadline = (string) get_option( 'gc_deadline', '' );
	if ( '' === $deadline ) {
		return false;
	}
	return current_time( 'Y-m-d' ) > $deadline;
}

/**
 * Routeur principal via shortcode [gc_page page="..."]
 *
 * @param array<string,string>|string $atts
 * @return string
 */
function gc_render_page_router( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'page' => '',
		),
		$atts,
		'gc_page'
	);

	$page = sanitize_key( (string) $atts['page'] );

	if ( empty( $page ) ) {
		// Détection automatique selon le slug de la page WordPress en cours
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

/**
 * Alias pour l'ancien shortcode [gc_formulaire]
 */
function gc_render_formulaire_alias( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'presentation' => '1',
			'page'         => '',
		),
		$atts,
		'gc_formulaire'
	);

	if ( '0' === (string) $atts['presentation'] || 'false' === (string) $atts['presentation'] ) {
		return gc_render_soumettre_demande();
	}

	if ( ! empty( $atts['page'] ) ) {
		return gc_render_page_router( array( 'page' => $atts['page'] ) );
	}

	// Si aucun paramètre, affiche selon le slug de la page courante
	global $post;
	$slug = ( $post && isset( $post->post_name ) ) ? $post->post_name : 'accueil';
	if ( 'candidature' === $slug ) {
		return gc_render_soumettre_demande();
	}

	return gc_render_accueil();
}

/**
 * Barre de navigation supérieure partagée (reproduction fidèle du modèle Fondation)
 *
 * @param string $active_page Identifiant de la page active.
 * @return string
 */
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
				<a href="<?php echo $url_criteres; ?>" class="gc-nav-link <?php echo 'criteres-reglement' === $active_page ? 'active' : ''; ?>">Criteria &amp; Rules</a>
				<a href="<?php echo $url_securite; ?>" class="gc-nav-link <?php echo 'securite' === $active_page ? 'active' : ''; ?>">Safety &amp; Free of charge</a>
				<a href="<?php echo $url_suivi; ?>" class="gc-nav-link <?php echo 'suivi' === $active_page ? 'active' : ''; ?>">Track your application</a>
				<a href="<?php echo $url_faq; ?>" class="gc-nav-link <?php echo 'faq' === $active_page ? 'active' : ''; ?>">Contact &amp; FAQ</a>
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

/**
 * ============================================================
 * 1. PAGE ACCUEIL (Disposition split conforme à la capture)
 * ============================================================
 */
function gc_render_accueil() {
	$places_total  = gc_places();
	$url_soumettre = esc_url( home_url( '/soumettre-une-demande/' ) );
	$url_qui       = esc_url( home_url( '/qui-sommes-nous/' ) );

	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'accueil' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<!-- Hero Section - Disposition Split fidèle au modèle -->
		<section class="gc-hero-split">
			<div class="gc-hero-split-container">
				<!-- Colonne Gauche : Titres & Actions -->
				<div class="gc-hero-left">
					<div class="gc-hero-tag">DIRECT SOLIDARITY AID</div>
					<h1 class="gc-hero-main-title">Support that reaches people directly</h1>
					<p class="gc-hero-lead">
						Founded by <strong>Richard &amp; Debbie Nuttall</strong> after their EuroMillions win: a caring solidarity initiative to support 58 people and families facing a vital emergency, so that every grant creates a lasting impact.
					</p>
					<div class="gc-hero-btns">
						<a href="<?php echo $url_soumettre; ?>" class="gc-btn-hero-orange">Apply now &rarr;</a>
						<a href="<?php echo $url_qui; ?>" class="gc-btn-hero-ghost">Learn more</a>
					</div>
				</div>

				<!-- Colonne Droite : Quotas et règles des candidatures -->
				<div class="gc-hero-right">
					<div class="gc-stat-white-card">
						<div class="gc-stat-sub-label">AWARD QUOTA</div>
						<div class="gc-stat-big-number">58 places in total</div>
						<p class="gc-stat-desc">An exclusive grant awarded to <strong>58 selected beneficiaries</strong> to provide them with substantial and decisive financial support.</p>
					</div>
					<div class="gc-stat-white-card">
						<div class="gc-stat-sub-label">APPLICATIONS OPEN &bull; MULTIPLE FILES</div>
						<div class="gc-stat-big-number">Open to everyone</div>
						<p class="gc-stat-desc">The form is open to everyone: we can receive and review <strong>multiple applications</strong> so that each situation is handled with care and fairness.</p>
					</div>
					<div class="gc-stat-white-card">
						<div class="gc-stat-sub-label">HUMAN ASSESSMENT &bull; IMPARTIALITY</div>
						<div class="gc-stat-big-number">Listening &amp; Discretion</div>
						<p class="gc-stat-desc">Each application is read individually by our team with respect, care and confidentiality.</p>
					</div>
				</div>
			</div>

			<!-- Ligne ondulée inférieure -->
			<div class="gc-hero-wave" aria-hidden="true">
				<svg viewBox="0 0 1440 60" preserveAspectRatio="none">
					<path fill="#f8fafc" d="M0,20 C360,55 1080,-15 1440,20 L1440,60 L0,60 Z"></path>
				</svg>
			</div>
		</section>

		<!-- Section Manifeste : Pourquoi nous existons -->
		<section class="gc-manifesto-section" aria-label="Why we exist">
			<div class="gc-manifesto-header">
				<span class="gc-section-tag">Our Reason for Being</span>
				<h2 class="gc-manifesto-main-title">Why we exist</h2>
				<p class="gc-manifesto-lead">
					In 2024, we won £61 million on the EuroMillions. A few months later, we lost <strong>Ben</strong>, our nephew. With what life had just given us and taken from us, we first supported medical research in his memory.
				</p>
			</div>

			<div class="gc-manifesto-quote-banner">
				<p class="gc-manifesto-quote-text">
					“The work of research laboratories is vital but slow: it may save lives in ten or twenty years. Yet people and families face life-threatening emergencies today. A sick person cannot fill out a complex form; a family losing their home cannot wait for the next session. If these people cannot come to us, it is up to us to go to them.”
				</p>
				<span class="gc-manifesto-quote-author">Richard &amp; Debbie Nuttall &bull; Founders</span>
			</div>

			<div class="gc-manifesto-grid">
				<!-- Carte 1 -->
				<div class="gc-manifesto-card">
					<div class="gc-manifesto-card-num">01</div>
					<h3 class="gc-manifesto-card-title">Why 58 people, and not 5,000?</h3>
					<p class="gc-manifesto-card-text">
						We could have distributed our money widely and superficially. We chose the opposite: <strong>58 people, supported one by one</strong>.
					</p>
					<p class="gc-manifesto-card-text">
						Because aid that is spread too thinly changes nothing. Because you cannot help someone you have never met. A hospital bill, interrupted schooling, a broken work tool or a roof to rebuild: for 58 people, we can go on site, listen, verify, act, and then see the real change.
					</p>
				</div>

				<!-- Carte 2 -->
				<div class="gc-manifesto-card">
					<div class="gc-manifesto-card-num">02</div>
					<h3 class="gc-manifesto-card-title">We first tried it at home</h3>
					<p class="gc-manifesto-card-text">
						Before opening this initiative, we wanted to know whether our method would hold. For nearly <strong>12 months in the United Kingdom</strong>, we applied it with people we went to find in their neighbourhoods, villages and hospitals.
					</p>
					<p class="gc-manifesto-card-text">
						We corrected mistakes and adjusted our support. But where the method was followed, it worked: the people we supported were able to get back on their feet, and what mattered was that we came to them.
					</p>
				</div>

				<!-- Carte 3 -->
				<div class="gc-manifesto-card">
					<div class="gc-manifesto-card-num">03</div>
					<h3 class="gc-manifesto-card-title">And now, the rest of the world</h3>
					<p class="gc-manifesto-card-text">
						If it worked at home, why would it not work elsewhere? Distress does not stop at borders, and neither does the inability to access institutional aid.
					</p>
					<p class="gc-manifesto-card-text">
						Today, we are opening this initiative to the world. We are ready to help <strong>58 people</strong>, wherever our teams and resources can reach. This is only the beginning.
					</p>
				</div>
			</div>

			<div class="gc-manifesto-rule-banner">
				<div class="gc-manifesto-rule-icon">
					<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="#c05621" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
						<circle cx="9" cy="7" r="4"></circle>
						<path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
						<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
					</svg>
				</div>
				<div class="gc-manifesto-rule-content">
					<h3 class="gc-manifesto-rule-title">A team, a commitment and a genuine presence</h3>
					<p class="gc-manifesto-rule-desc">
						We are not a large anonymous administration. We are a couple, a human team and one fundamental principle: <strong>we go directly to people, with kindness, closeness and respect for their story.</strong>
					</p>
				</div>
			</div>
		</section>

		<!-- Bloc d'accueil de présentation synthétique -->
		<section class="gc-home-summary-grid">
			<div class="gc-home-summary-card">
				<span class="gc-summary-pill">01</span>
				<h3 class="gc-summary-title">A sincere initiative</h3>
				<p class="gc-summary-desc">Richard &amp; Debbie Nuttall are dedicating part of their £61 million EuroMillions win to a direct-grant programme without bureaucracy.</p>
				<a href="<?php echo $url_qui; ?>" class="gc-summary-link">Discover their story &rarr;</a>
			</div>
			<div class="gc-home-summary-card">
				<span class="gc-summary-pill">02</span>
				<h3 class="gc-summary-title">Fair criteria</h3>
				<p class="gc-summary-desc">Urgency of need, family situation, supporting documents and consistency of the account. A transparent and caring assessment framework.</p>
				<a href="<?php echo esc_url( home_url( '/criteres-reglement/' ) ); ?>" class="gc-summary-link">Read the criteria &rarr;</a>
			</div>
			<div class="gc-home-summary-card">
				<span class="gc-summary-pill">03</span>
				<h3 class="gc-summary-title">Discretion &amp; Confidentiality</h3>
				<p class="gc-summary-desc">Strict protection of your information, respect for privacy and direct contact with our official team.</p>
				<a href="<?php echo esc_url( home_url( '/securite/' ) ); ?>" class="gc-summary-link">View our charter &rarr;</a>
			</div>
		</section>

		<!-- Bouton d'action central -->
		<div class="gc-home-cta-banner">
			<h2 class="gc-cta-banner-title">Are you facing a significant financial hardship?</h2>
			<p class="gc-cta-banner-subtitle">Take a few minutes to explain your situation honestly and in full confidentiality.</p>
			<a href="<?php echo $url_soumettre; ?>" class="gc-btn-hero-orange" style="font-size: 18px; padding: 15px 34px;">Submit a request for help &rarr;</a>
		</div>

		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * ============================================================
 * 2. PAGE QUI SOMMES-NOUS (LA FONDATION)
 * ============================================================
 */
function gc_render_qui_sommes_nous() {
	$img_couple   = esc_url( GC_URL . 'assets/images/richard-debbie-nuttall.png' );
	$img_victoire = esc_url( GC_URL . 'assets/images/euromillions-victoire.png' );
	$img_projet   = esc_url( GC_URL . 'assets/images/richard-debbie-projet.png' );

	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'qui-sommes-nous' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<div class="gc-subpage-header">
			<span class="gc-section-tag">About us</span>
			<h1 class="gc-subpage-title">The story of Richard &amp; Debbie Nuttall</h1>
			<p class="gc-subpage-intro">A humble life guided by work, transformed into an opportunity for direct generosity and humanity.</p>
		</div>

		<div class="gc-story-section">
			<!-- Carte 1 -->
			<article class="gc-story-card">
				<div class="gc-story-media">
					<img src="<?php echo $img_couple; ?>" alt="Richard and Debbie Nuttall" loading="lazy">
				</div>
				<div class="gc-story-content">
					<span class="gc-section-tag">Thirty years of simplicity</span>
					<h2 class="gc-story-title">Modest beginnings in Lancashire</h2>
					<p class="gc-story-text">Originally from <strong>Colne</strong> (Lancashire, England), Richard and Debbie Nuttall both turned <strong>61 in 2024</strong>. Married for more than 30 years, Richard practised rigorously as an <strong>independent chartered accountant</strong> for over three decades, while Debbie worked steadily by his side.</p>
					<p class="gc-story-text">Their lives have always been guided by clear principles: listening, moderation and a commitment to close human values.</p>
					<div class="gc-story-highlight">
						<strong>Key value:</strong> A life guided by sincerity, reserve and respect for others.
					</div>
				</div>
			</article>

			<!-- Carte 2 -->
			<article class="gc-story-card reverse">
				<div class="gc-story-media">
					<img src="<?php echo $img_victoire; ?>" alt="EuroMillions press conference of Richard and Debbie Nuttall" loading="lazy">
				</div>
				<div class="gc-story-content">
					<span class="gc-section-tag">January &amp; February 2024</span>
					<h2 class="gc-story-title">£61,708,231: The day destiny struck</h2>
					<p class="gc-story-text">On Tuesday 30 January 2024, during their wedding anniversary holiday in Fuerteventura, Richard received an official email from the <em>National Lottery</em>. After verification with the lottery authorities, the news was confirmed: a historic win of <strong>£61,708,231</strong> (around €72 million), made official on 21 February 2024.</p>
					<p class="gc-story-text">Their first resolution was immediate: stay grounded and turn this blessing into a means of help for those who need it most.</p>
					<div class="gc-story-highlight">
						<strong>Their choice:</strong> No ostentatious spending, but a peaceful retirement and a solidarity commitment.
					</div>
				</div>
			</article>

			<!-- Carte 3 -->
			<article class="gc-story-card">
				<div class="gc-story-media">
					<img src="<?php echo $img_projet; ?>" alt="Richard and Debbie Nuttall committed to their solidarity projects" loading="lazy">
				</div>
				<div class="gc-story-content">
					<span class="gc-section-tag">2025 &bull; Birth of ACFE</span>
					<h2 class="gc-story-title">After BK's Heroes, an initiative for people who are isolated</h2>
					<p class="gc-story-text">After massively supporting the charity <strong>BK’s Heroes</strong> (founded in memory of their nephew <em>Ben DeBie</em> for research into kidney disease and brain tumours), Richard and Debbie became aware of a poignant reality: many people in great distress <strong>cannot contact them directly</strong> and are cut off from any support network.</p>
					<p class="gc-story-text">This is how, in <strong>2025</strong>, the private organisation <strong>ACFE — A Chance for Everyone</strong> was born, designed as a direct, dignified and caring bridge.</p>
					<div class="gc-story-highlight">
						<strong>Commitment:</strong> “To offer a real, human and direct chance to 58 people or families.”
					</div>
				</div>
			</article>
		</div>

		<!-- L'Équipe d'Analyse des Dossiers -->
		<div class="gc-team-section">
			<h2 class="gc-team-title">The human team dedicated to reviewing applications</h2>
			<p class="gc-team-subtitle">Every application is reviewed confidentially by a human-scale team, with rigour and care.</p>
			<div class="gc-team-grid">
				<div class="gc-team-card">
					<div class="gc-team-avatar">R &amp; D</div>
					<h3 class="gc-team-name">Richard &amp; Debbie Nuttall</h3>
					<span class="gc-team-role">Founders &bull; Ethical guarantors</span>
					<p class="gc-team-bio">Initiators of the project. They set the ethical direction and personally validate the final grant decisions.</p>
				</div>
				<div class="gc-team-card">
					<div class="gc-team-avatar">M</div>
					<h3 class="gc-team-name">Marc</h3>
					<span class="gc-team-role">Head of social assessment</span>
					<p class="gc-team-bio">Former charity social worker. He studies the reality of emergency situations, household composition and the degree of vulnerability.</p>
				</div>
				<div class="gc-team-card">
					<div class="gc-team-avatar">S</div>
					<h3 class="gc-team-name">Sarah</h3>
					<span class="gc-team-role">Listening &amp; consistency coordinator</span>
					<p class="gc-team-bio">Responsible for the careful reading of accounts, the consistency of expressed needs and respectful verification of the information provided.</p>
				</div>
				<div class="gc-team-card">
					<div class="gc-team-avatar">T</div>
					<h3 class="gc-team-name">Thomas</h3>
					<span class="gc-team-role">Coordination &amp; Technical verification</span>
					<p class="gc-team-bio">Ensures the integrity of supporting documents, strict data protection and the proper conduct of direct contacts.</p>
				</div>
			</div>
		</div>

		<!-- Chronologie officielle -->
		<div class="gc-timeline-section">
			<h2 class="gc-timeline-title">Official timeline</h2>
			<div class="gc-timeline-grid">
				<div class="gc-timeline-item">
					<span class="gc-timeline-date">January 2024</span>
					<h3 class="gc-timeline-heading">The winning EuroMillions draw</h3>
					<p class="gc-timeline-desc">Discovery of the £61 million winning ticket during a wedding anniversary stay in Fuerteventura.</p>
				</div>
				<div class="gc-timeline-item">
					<span class="gc-timeline-date">February &ndash; April 2024</span>
					<h3 class="gc-timeline-heading">Announcement &amp; Donation to BK's Heroes</h3>
					<p class="gc-timeline-desc">Public presentation with the National Lottery, retirement and direct financial support for medical research.</p>
				</div>
				<div class="gc-timeline-item">
					<span class="gc-timeline-date">2025</span>
					<h3 class="gc-timeline-heading">Official launch of ACFE</h3>
					<p class="gc-timeline-desc">Creation of the private solidarity initiative ACFE to open a direct channel for those who have no personal contact.</p>
				</div>
				<div class="gc-timeline-item">
					<span class="gc-timeline-date">Current session</span>
					<h3 class="gc-timeline-heading">Call for the 58 beneficiaries</h3>
					<p class="gc-timeline-desc">Receiving applications, careful individual reading and caring selection with no lottery draw.</p>
				</div>
			</div>
		</div>

		<!-- Bloc Clarté & Transparence sur l'origine des fonds -->
		<div class="gc-funding-transparency-card">
			<div class="gc-funding-icon-col">
				<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
			</div>
			<div class="gc-funding-content-col">
				<span class="gc-section-tag">Financial transparency &amp; Independence</span>
				<h2 class="gc-funding-title">Where do the funds awarded to the 58 beneficiaries come from?</h2>
				<p class="gc-funding-lead">
					<strong>100% of the grants come exclusively from the personal wealth of Richard &amp; Debbie Nuttall</strong>, from their official historic win of £61 million on the EuroMillions in 2024.
				</p>
				<div class="gc-funding-grid">
					<div class="gc-funding-item">
						<strong>No public fundraising</strong>
						<span>ACFE asks no one for money. We do not collect donations; we distribute our own.</span>
					</div>
					<div class="gc-funding-item">
						<strong>Full autonomy &amp; Neutrality</strong>
						<span>No state subsidy or commercial partner: absolute independence solely in the service of human dignity.</span>
					</div>
				</div>
			</div>
		</div>

		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * ============================================================
 * 3. PAGE CRITÈRES ET RÈGLEMENT (zéro emoji)
 * ============================================================
 */
function gc_render_reglement() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'criteres-reglement' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<div class="gc-subpage-header">
			<span class="gc-section-tag">Rules &amp; Transparency</span>
			<h1 class="gc-subpage-title">Assessment criteria and award rules</h1>
			<p class="gc-subpage-intro">A fully fair process: everything is defined and written in advance to ensure respect for every applicant.</p>
		</div>

		<div class="gc-rules-grid">
			<!-- 01 -->
			<div class="gc-rule-box">
				<div class="gc-rule-num">01</div>
				<h2 class="gc-rule-title">Who can submit an application?</h2>
				<ul class="gc-rule-list">
					<li>Any adult individual (18 years and over).</li>
					<li>Single-parent families, people on modest incomes, isolated individuals or those facing a serious unexpected hardship.</li>
					<li>People with a real, concrete and documented financial need (health, housing, essential debts, return to work).</li>
				</ul>
			</div>

			<!-- 02 -->
			<div class="gc-rule-box">
				<div class="gc-rule-num">02</div>
				<h2 class="gc-rule-title">The 5 human assessment criteria</h2>
				<ul class="gc-rule-list">
					<li><strong>1. Urgency of need:</strong> Severity and pressing nature of the situation.</li>
					<li><strong>2. Family situation:</strong> Dependent children, dependency or isolation.</li>
					<li><strong>3. Supporting documents provided:</strong> Official ID and clarity of the information submitted.</li>
					<li><strong>4. Consistency of the application:</strong> Accuracy and sincerity of your personal explanation.</li>
					<li><strong>5. Concrete impact:</strong> Ability of the support to resolve the difficulty sustainably.</li>
				</ul>
			</div>

			<!-- 03 -->
			<div class="gc-rule-box">
				<div class="gc-rule-num">03</div>
				<h2 class="gc-rule-title">What is strictly excluded</h2>
				<ul class="gc-rule-list">
					<li>Financial speculation, gambling or high-risk investments.</li>
					<li>Incomplete applications, inconsistent statements or false declarations.</li>
					<li><strong>One application per household:</strong> Any duplicate number or identity is immediately discarded.</li>
				</ul>
			</div>

			<!-- 04 -->
			<div class="gc-rule-box">
				<div class="gc-rule-num">04</div>
				<h2 class="gc-rule-title">The timeline and after selection</h2>
				<ul class="gc-rule-list">
					<li><strong>Submission:</strong> Open until the deadline is reached or the 58 places are filled.</li>
					<li><strong>Human review:</strong> Careful and confidential examination within 5 to 15 days.</li>
					<li><strong>Direct contact:</strong> Selected people are called directly by our official team (phone or official WhatsApp).</li>
					<li><strong>Payment:</strong> Individual support with no intermediary.</li>
				</ul>
			</div>
		</div>

		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * ============================================================
 * 4. PAGE SÉCURITÉ & GRATUITÉ (zéro emoji)
 * ============================================================
 */
function gc_render_securite() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'securite' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<div class="gc-subpage-header">
			<span class="gc-section-tag">Safety &amp; Integrity</span>
			<h1 class="gc-subpage-title">Application safety &bull; Parable of the grant</h1>
			<p class="gc-subpage-intro">Complete protection against scams and total clarity on our principles of action.</p>
		</div>

		<section class="gc-security-card">
			<div class="gc-security-grid">
				<div class="gc-security-point">
					<strong>FREE APPLICATION SUBMISSION</strong>
					<span>From submitting your application through human review and exchanges, the entire application process is free of charge. No one will ever ask you for a single cent.</span>
				</div>
				<div class="gc-security-point">
					<strong>NO LOTTERY DRAW</strong>
					<span>Selection is 100% human. Every story is read with empathy by a real person on our team.</span>
				</div>
				<div class="gc-security-point">
					<strong>STRICT CONFIDENTIALITY</strong>
					<span>Your information and supporting documents are protected in a secure space. Nothing is ever published or shared.</span>
				</div>
			</div>

			<!-- La parabole du don et du transfert -->
			<div class="gc-parable-box">
				<h2 class="gc-parable-title">The parable of giving and receiving</h2>
				<p class="gc-parable-text">
					“The tree that bears fruit asks for no tribute to let its branches be picked. In the same way, our help is offered with a pure heart, with no counterpart or prior withholding.
					No sum will ever be asked of you by ACFE to release, unlock or pay this support. Like water flowing peacefully to welcoming ground, the gift reaches its destination with no paid intermediary; only the ordinary practices of your local bank apply to your own account when receiving the funds.”
				</p>
			</div>

			<!-- Les 4 Étapes -->
			<div class="gc-steps-section" style="margin-top: 36px; margin-bottom: 0;">
				<h2 class="gc-steps-title">The 4 steps of the process</h2>
				<div class="gc-steps-grid">
					<div class="gc-step-card">
						<div class="gc-step-number">1</div>
						<h3 class="gc-step-name">Submitting your application</h3>
						<p class="gc-step-desc">You provide your details and sincerely explain your situation, entirely free of charge.</p>
					</div>
					<div class="gc-step-card">
						<div class="gc-step-number">2</div>
						<h3 class="gc-step-name">Human review</h3>
						<p class="gc-step-desc">Our team assesses the urgency, the reality of the needs and the sincerity of the application.</p>
					</div>
					<div class="gc-step-card">
						<div class="gc-step-number">3</div>
						<h3 class="gc-step-name">Direct exchange</h3>
						<p class="gc-step-desc">We contact selected people directly by phone or official WhatsApp.</p>
					</div>
					<div class="gc-step-card">
						<div class="gc-step-number">4</div>
						<h3 class="gc-step-name">Support</h3>
						<p class="gc-step-desc">Delivery of concrete help and direct payment with no intermediary.</p>
					</div>
				</div>
			</div>
		</section>

		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * ============================================================
 * 5. PAGE SUIVI DE DOSSIER
 * ============================================================
 */
function gc_render_suivi() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'suivi' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<div class="gc-subpage-header">
			<span class="gc-section-tag">Transparency</span>
			<h1 class="gc-subpage-title">Track your application</h1>
			<p class="gc-subpage-intro">Check the progress of your application at any time by entering your unique application number and your last name, first name or email.</p>
		</div>

		<?php echo gc_render_suivi_box(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * ============================================================
 * 6. PAGE FAQ & CONTACT OFFICIEL
 * ============================================================
 */
function gc_render_faq() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'faq' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<div class="gc-subpage-header">
			<span class="gc-section-tag">Frequently asked questions</span>
			<h1 class="gc-subpage-title">Frequently asked questions &amp; Official contact</h1>
			<p class="gc-subpage-intro">Everything you need to know about the free process, timelines and our official communication channels.</p>
		</div>

		<div class="gc-faq-grid">
			<details class="gc-faq-item" open>
				<summary class="gc-faq-question">Is submitting an application really 100% free?</summary>
				<div class="gc-faq-answer">
					<p><strong>Yes, absolutely and without exception.</strong> No application fee, no payment, no advance and no code will ever be asked of you. The ACFE organisation is funded entirely by the private gift of Richard and Debbie Nuttall.</p>
				</div>
			</details>

			<details class="gc-faq-item">
				<summary class="gc-faq-question">How long does it take to get a response?</summary>
				<div class="gc-faq-answer">
					<p>Each application is read individually by a member of our assessment team. Depending on the number of applications received, allow on average <strong>5 to 15 working days</strong> for a full review.</p>
				</div>
			</details>

			<details class="gc-faq-item">
				<summary class="gc-faq-question">How will I know if my application is selected?</summary>
				<div class="gc-faq-answer">
					<p>If your application is selected among the 58 beneficiaries, you will be <strong>contacted directly by phone call or official WhatsApp</strong> by Marc, Sarah or Thomas. We never outsource this step.</p>
				</div>
			</details>

			<details class="gc-faq-item">
				<summary class="gc-faq-question">What if I am not contacted?</summary>
				<div class="gc-faq-answer">
					<p>As the number of places is strictly limited to 58 people to ensure meaningful financial support, unfortunately not all applications can be selected. Not being selected is in no way a judgement of your situation.</p>
				</div>
			</details>

			<details class="gc-faq-item">
				<summary class="gc-faq-question">How do I report a scam attempt or a fake profile?</summary>
				<div class="gc-faq-answer">
					<p>If someone contacts you on social media claiming to be Richard, Debbie or an ACFE member and asks you for money, gift cards or bank fees, <strong>block them immediately</strong>. Report them to our official WhatsApp number below.</p>
				</div>
			</details>
		</div>

		<!-- Bloc Contact Officiel & Informations Légales -->
		<div class="gc-legal-contact-card">
			<div class="gc-contact-col">
				<h2 class="gc-legal-subheading">Official contact</h2>
				<p>To ask a question or get information about your application:</p>
				<div class="gc-contact-badge">
					<span>Official WhatsApp:</span>
					<strong>+1 (518) 963-6316</strong>
				</div>
				<p style="font-size: 13px; color: #1e293b; margin-top: 10px; font-weight: 500;">
					Submitting and reviewing your application are 100% free. No payment will ever be asked of you.
				</p>
			</div>

			<div class="gc-legal-col">
				<h2 class="gc-legal-subheading">Legal notice &amp; Privacy</h2>
				<p style="font-size: 13px; color: #475569; line-height: 1.6;">
					<strong>Publisher:</strong> ACFE solidarity initiative &bull; A Chance for Everyone, founded by Richard &amp; Debbie Nuttall.<br>
					<strong>Data protection (GDPR):</strong> Your information is protected in accordance with the GDPR and used exclusively to process your application.
				</p>
				<div style="margin-top: 14px;">
					<a href="<?php echo esc_url( home_url( '/mentions-legales/' ) ); ?>" style="color: #2563eb; font-weight: 600; text-decoration: underline;">View the full Legal Notice &amp; GDPR &rarr;</a>
				</div>
			</div>
		</div>

		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * ============================================================
 * 7. PAGE SOUMETTRE UNE DEMANDE (Formulaire officiel sécurisé)
 * ============================================================
 */
function gc_render_soumettre_demande() {
	$errors   = gc_form_errors();
	$img_logo = esc_url( GC_URL . 'assets/images/logo.svg' );

	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'soumettre' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<section id="formulaire-demande" class="gc-form-section">
			<div class="gc-form-header">
				<div class="gc-form-brand">
					<img src="<?php echo $img_logo; ?>" alt="Logo ACFE" class="gc-form-logo" />
				</div>
				<span class="gc-section-tag">Free application submission &bull; No-cost process</span>
				<h1 class="gc-form-heading">Submit an application</h1>
				<p class="gc-form-subheading">Take the time to explain your situation honestly. Every application is reviewed with care, discretion and respect by the ACFE team.</p>
			</div>

			<?php
			// Résultat d'un envoi précédent
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['gc_ok'] ) ) {
				$dossier = preg_replace( '/[^A-Z0-9\-]/', '', strtoupper( sanitize_text_field( wp_unslash( $_GET['gc_ok'] ) ) ) );
				?>
				<?php
				$tracking_url = esc_url( add_query_arg( 'gc_search_dossier', $dossier, home_url( '/suivi-de-dossier/' ) ) );
				?>
				<div class="gc-alert-success">
					<div class="gc-success-icon">
						<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#ffffff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
							<polyline points="20 6 9 17 4 12"></polyline>
						</svg>
					</div>
					<h2 class="gc-success-title">Your application has been successfully registered</h2>
					<p>Your unique and confidential application number:</p>
					<div class="gc-dossier-pill"><?php echo esc_html( $dossier ); ?></div>

					<div style="margin: 22px 0 16px;">
						<a href="<?php echo $tracking_url; ?>" class="gc-btn-hero-orange" style="display: inline-block; padding: 13px 28px; text-decoration: none; font-size: 16px;">
							Track my application live &rarr;
						</a>
					</div>

					<p style="margin: 0; font-size: 15px; color: #166534; line-height: 1.6;">
						<strong>An official acknowledgement of receipt has been sent to you by email.</strong><br>
						Please keep this application number safe. Submitting an application is <strong>entirely free, voluntary and without any charge</strong>.<br>
						Our team reviews every situation with care and discretion. If your application is selected among the 58 beneficiaries, you will be contacted directly by our official team.
					</p>
				</div>
				<?php echo gc_render_footer(); ?>
				</div>
				<?php
				return ob_get_clean();
			}

			if ( isset( $_GET['gc_err'] ) ) {
				$code = sanitize_key( wp_unslash( $_GET['gc_err'] ) );
				if ( isset( $errors[ $code ] ) ) {
					?>
					<div class="gc-alert-error" role="alert">
						<span class="gc-alert-icon">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<circle cx="12" cy="12" r="10"></circle>
								<line x1="12" y1="8" x2="12" y2="12"></line>
								<line x1="12" y1="16" x2="12.01" y2="16"></line>
							</svg>
						</span>
						<span><?php echo esc_html( $errors[ $code ] ); ?></span>
					</div>
					<?php
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			if ( gc_is_closed() ) {
				?>
				<div class="gc-alert-closed">
					<h2>Applications are now closed</h2>
					<p>The submission deadline has passed. Our team is currently reviewing the applications received. Thank you for your trust and understanding.</p>
				</div>
				<?php
				echo '</div>'; // Ferme .gc-page-container
				return ob_get_clean();
			}
			?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="gc_submit">
				<?php wp_nonce_field( 'gc_submit', 'gc_nonce' ); ?>

				<!-- Piège anti-robots invisible -->
				<p style="position:absolute;left:-9999px;opacity:0" aria-hidden="true">
					<label>Do not fill in <input type="text" name="gc_website" tabindex="-1" autocomplete="off"></label>
				</p>

				<div class="gc-form-grid">
					<!-- Identité -->
					<div class="gc-form-group">
						<label class="gc-form-label" for="gc_firstname">First name <span class="gc-req">*</span></label>
						<input type="text" name="gc_firstname" id="gc_firstname" class="gc-form-control" required maxlength="60" placeholder="e.g. Marie">
					</div>

					<div class="gc-form-group">
						<label class="gc-form-label" for="gc_lastname">Last name <span class="gc-req">*</span></label>
						<input type="text" name="gc_lastname" id="gc_lastname" class="gc-form-control" required maxlength="60" placeholder="e.g. Smith">
					</div>

					<!-- Contact & Confirmation par Email -->
					<div class="gc-form-group">
						<label class="gc-form-label" for="gc_email">Email address (Gmail or other) <span class="gc-req">*</span></label>
						<input type="email" name="gc_email" id="gc_email" class="gc-form-control" required maxlength="120" placeholder="your-address@gmail.com">
						<span class="gc-field-hint">Required: confirmation and application number sent by email.</span>
					</div>

					<div class="gc-form-group">
						<label class="gc-form-label" for="gc_phone">Reachable phone number <span class="gc-req">*</span></label>
						<div class="gc-phone-flex-row">
							<select name="gc_phone_code" id="gc_phone_code" class="gc-form-control gc-phone-code-select">
								<option value="+33">+33 (France)</option>
								<option value="+32">+32 (Belgium)</option>
								<option value="+41">+41 (Switzerland)</option>
								<option value="+1">+1 (Canada / USA)</option>
								<option value="+44">+44 (United Kingdom)</option>
								<option value="+34">+34 (Spain)</option>
								<option value="+49">+49 (Germany)</option>
								<option value="+39">+39 (Italy)</option>
								<option value="+351">+351 (Portugal)</option>
								<option value="+225">+225 (Côte d'Ivoire)</option>
								<option value="+221">+221 (Senegal)</option>
								<option value="+237">+237 (Cameroon)</option>
								<option value="+212">+212 (Morocco)</option>
								<option value="+213">+213 (Algeria)</option>
								<option value="+216">+216 (Tunisia)</option>
								<option value="+229">+229 (Benin)</option>
								<option value="+242">+242 (Congo)</option>
								<option value="+243">+243 (DRC)</option>
								<option value="+509">+509 (Haiti)</option>
								<option value="+261">+261 (Madagascar)</option>
								<option value="+86">+86 (China)</option>
								<option value="autre">Other dialling code</option>
							</select>
							<input type="tel" name="gc_phone" id="gc_phone" class="gc-form-control" required maxlength="20" placeholder="e.g. 6 12 34 56 78">
						</div>
						<span class="gc-field-hint">Only one application is allowed per phone number.</span>
					</div>

					<!-- Résidence -->
					<div class="gc-form-group">
						<label class="gc-form-label" for="gc_country">Country of residence <span class="gc-req">*</span></label>
						<input type="text" name="gc_country" id="gc_country" class="gc-form-control" required maxlength="80" placeholder="e.g. France, Belgium, Côte d'Ivoire...">
					</div>

					<div class="gc-form-group">
						<label class="gc-form-label" for="gc_city">City of residence <span class="gc-req">*</span></label>
						<input type="text" name="gc_city" id="gc_city" class="gc-form-control" required maxlength="80" placeholder="e.g. Lyon, Liège, Abidjan...">
					</div>

					<!-- Situation professionnelle et Foyer -->
					<div class="gc-form-group">
						<label class="gc-form-label" for="gc_situation_pro">Employment status <span class="gc-req">*</span></label>
						<select name="gc_situation_pro" id="gc_situation_pro" class="gc-form-control" required>
							<option value="">-- Select your situation --</option>
							<option value="salarie">Employed / Salaried</option>
							<option value="demandeur_emploi">Unemployed / Looking for work</option>
							<option value="independant">Self-employed / Craftsperson / Freelancer</option>
							<option value="retraite">Retired</option>
							<option value="etudiant">Student</option>
							<option value="invalidite">Unable to work / Disability</option>
							<option value="autre">Other situation</option>
						</select>
					</div>

					<div class="gc-form-group">
						<label class="gc-form-label" for="gc_foyer">Household composition <span class="gc-req">*</span></label>
						<select name="gc_foyer" id="gc_foyer" class="gc-form-control" required>
							<option value="">-- Select household composition --</option>
							<option value="seul">Single person</option>
							<option value="monoparental">Single-parent family (with child(ren))</option>
							<option value="couple_sans_enfant">Couple without children</option>
							<option value="couple_avec_enfant">Couple with child(ren)</option>
							<option value="foyer_autre">Multigenerational household / Other dependents</option>
						</select>
					</div>

					<!-- Degré d'urgence -->
					<div class="gc-form-group full-width">
						<label class="gc-form-label" for="gc_urgency">Urgency of your need <span class="gc-req">*</span></label>
						<select name="gc_urgency" id="gc_urgency" class="gc-form-control" required>
							<option value="">-- Indicate whether the need is urgent --</option>
							<option value="urgente">Critical / Immediate urgency (threat of eviction, vital medical costs, total loss of income)</option>
							<option value="moderee">Significant short-term need (hardships to resolve in the coming weeks)</option>
							<option value="planifiee">Need for support and consolidation (medium term)</option>
						</select>
					</div>

					<!-- Récit de la situation -->
					<div class="gc-form-group full-width">
						<label class="gc-form-label" for="gc_situation">Detailed description of your situation and needs <span class="gc-req">*</span></label>
						<textarea name="gc_situation" id="gc_situation" class="gc-form-control gc-form-textarea" required rows="7" minlength="30" maxlength="4000" placeholder="Please explain your situation honestly, the hardships you are facing and how this direct support would change your daily life (minimum 30 characters)..."></textarea>
						<span class="gc-field-hint">Be sincere and precise. Your account remains strictly confidential.</span>
					</div>

					<!-- Documents justificatifs (2 documents) -->
					<div class="gc-form-group">
						<label class="gc-form-label" for="gc_id_document">
							1. Official identity document
							<small style="font-weight:400;color:#64748b">(ID card, Passport, Residence permit)</small>
						</label>
						<input type="file" name="gc_id_document" id="gc_id_document" class="gc-form-control gc-file-input" accept=".pdf,.jpg,.jpeg,.png">
						<span class="gc-field-hint">PDF, JPG or PNG (max. 5 MB). Private and encrypted storage.</span>
					</div>

					<div class="gc-form-group">
						<label class="gc-form-label" for="gc_situation_document">
							2. Proof of situation
							<small style="font-weight:400;color:#64748b">(Invoice, receipt, certificate, statement...)</small>
						</label>
						<input type="file" name="gc_situation_document" id="gc_situation_document" class="gc-form-control gc-file-input" accept=".pdf,.jpg,.jpeg,.png">
						<span class="gc-field-hint">Statement, unpaid invoice, account summary or quote depending on your situation.</span>
					</div>

					<!-- Justification si document manquant -->
					<div class="gc-form-group full-width gc-missing-doc-box">
						<label class="gc-missing-doc-checkbox-label">
							<input type="checkbox" name="gc_has_missing_doc" id="gc_has_missing_doc" value="1" onchange="document.getElementById('gc-missing-reason-wrap').style.display = this.checked ? 'block' : 'none';">
							<span>I cannot provide one of the supporting documents at this time</span>
						</label>
						<div id="gc-missing-reason-wrap" style="display:none; margin-top: 10px;">
							<label class="gc-form-label" for="gc_missing_doc_reason">Explain which document is missing and why <span class="gc-req">*</span></label>
							<textarea name="gc_missing_doc_reason" id="gc_missing_doc_reason" class="gc-form-control" rows="3" placeholder="e.g. Lost document, administrative process ongoing, statement pending..."></textarea>
							<span class="gc-field-hint">Our team will take your explanation into account when reviewing your application.</span>
						</div>
					</div>
				</div>

				<!-- Attestation sur l'honneur OBLIGATOIRE -->
				<div class="gc-consent-box">
					<label class="gc-consent-label">
						<input type="checkbox" name="gc_consent" value="1" required>
						<span><strong>I confirm on my honour the accuracy of all information provided</strong> and that I am submitting this application freely and sincerely. I acknowledge that applying is strictly free, voluntary and without any charge. <span class="gc-req">*</span></span>
					</label>
				</div>

				<button type="submit" class="gc-submit-btn-orange">
					<span>Submit my application</span>
					<span>&rarr;</span>
				</button>

				<p class="gc-footer-note">
					<strong>ACFE — A Chance for Everyone</strong> &bull; Free and voluntary application submission.<br>
					No one will ever ask you for money. Your information is protected and will never be made public.
				</p>
			</form>
		</section>

		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Boîte de suivi de dossier.
 *
 * @return string
 */
function gc_render_suivi_box() {
	$result = '';

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['gc_search_dossier'] ) ) {

		$raw_dossier  = sanitize_text_field( wp_unslash( isset( $_GET['gc_search_dossier'] ) ? $_GET['gc_search_dossier'] : '' ) );
		$raw_identity = sanitize_text_field( wp_unslash( isset( $_GET['gc_search_identity'] ) ? $_GET['gc_search_identity'] : '' ) );
		$raw_email    = sanitize_email( wp_unslash( isset( $_GET['gc_search_email'] ) ? $_GET['gc_search_email'] : '' ) );

		// Numéro de dossier : garder lettres, chiffres, tirets, forcer majuscules
		$dossier_query = strtoupper( preg_replace( '/[^A-Za-z0-9\-]/', '', $raw_dossier ) );

		// Nom/Prénom : normaliser (minuscules, sans accents)
		$identity_normalized = gc_normalize_string( $raw_identity );

		// Email : minuscules, trim
		$email_query = strtolower( trim( $raw_email ) );

		if ( ! empty( $dossier_query ) && ( ! empty( $identity_normalized ) || ! empty( $email_query ) ) ) {

			// Connexion PDO avec le compte public restreint
			$config_path = dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'app_config.php';
			if ( file_exists( $config_path ) ) {
				require_once $config_path;
			}
			$pdo = null;
			if ( defined( 'GC_DB_PUBLIC_USER' ) && defined( 'GC_DB_PUBLIC_PASS' ) ) {
				try {
					$dsn = sprintf( 'mysql:host=%s;port=%d;dbname=%s;charset=%s', GC_DB_HOST, GC_DB_PORT, GC_DB_NAME, GC_DB_CHARSET );
					$pdo = new PDO( $dsn, GC_DB_PUBLIC_USER, GC_DB_PUBLIC_PASS, array(
						PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
						PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
						PDO::ATTR_EMULATE_PREPARES   => false,
					) );
				} catch ( Exception $e ) {
					error_log( 'Tracking PDO error: ' . $e->getMessage() );
				}
			}

			$row = null;
			if ( $pdo ) {
				$stmt = $pdo->prepare( 'SELECT id, dossier_num, firstname, lastname, email, status FROM gc_candidatures WHERE UPPER(dossier_num) = ? LIMIT 1' );
				$stmt->execute( array( $dossier_query ) );
				$row = $stmt->fetch();
			}

			// Comptage des critères qui correspondent (minimum 2 requis)
			$match_count = 0;
			if ( $row ) {
				$match_count++; // Numéro de dossier = critère 1

				// Critère nom/prénom : comparaison souple (insensible casse + accents)
				if ( ! empty( $identity_normalized ) ) {
					$db_fn = gc_normalize_string( $row['firstname'] );
					$db_ln = gc_normalize_string( $row['lastname'] );
					if (
						( '' !== $db_fn && false !== strpos( $db_fn, $identity_normalized ) ) ||
						( '' !== $db_ln && false !== strpos( $db_ln, $identity_normalized ) ) ||
						( '' !== $db_fn && false !== strpos( $identity_normalized, $db_fn ) ) ||
						( '' !== $db_ln && false !== strpos( $identity_normalized, $db_ln ) )
					) {
						$match_count++;
					}
				}

				// Critère e-mail : correspondance exacte insensible à la casse
				if ( ! empty( $email_query ) && strtolower( trim( $row['email'] ) ) === $email_query ) {
					$match_count++;
				}
			}

			$labels = array(
				'en_attente'      => 'Application received &bull; Awaiting first reading',
				'recue'           => 'Application received &bull; Awaiting first reading',
				'en_verification' => 'Supporting documents under verification',
				'en_analyse'      => "Under careful review by the assessment team",
				'liste_courte'    => "Application selected for the next review stage",
				'retenue'         => 'Application selected — you will be contacted shortly by our team',
				'contactee'       => 'Contact in progress by our official team',
				'injoignable'     => 'Contact attempt made (awaiting response)',
				'confirmee'       => 'Application confirmed and approved for payment',
				'payee'           => 'Aid paid and support completed',
				'refusee'         => 'Review completed (not selected for this session)',
			);

			if ( $row && $match_count >= 2 ) {
				$status = (string) $row['status'];
				$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : "Under review";
				$result = '
				<div class="gc-suivi-result success">
					<div class="gc-suivi-dossier-tag">' . esc_html( $row['dossier_num'] ) . '</div>
					<h3 style="margin: 8px 0; color: #166534; font-size: 18px;">Current status of your application:</h3>
					<p style="font-size: 16px; font-weight: 700; color: #047857; margin: 4px 0 10px;">' . esc_html( $label ) . '</p>
					<p style="font-size: 13px; color: #475569; margin: 0;">If your application requires a follow-up, our team will contact you directly and free of charge.</p>
				</div>';
			} else {
				$result = '
				<div class="gc-suivi-result error">
					<p style="margin: 0; color: #991b1b; font-weight: 600;">No application matches this information. Check your application number and at least one other field (last name, first name or email).</p>
				</div>';
			}
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$html  = '<div class="gc-suivi-box">';
	$html .= '<form method="get" class="gc-suivi-form">';
	$html .= '<div class="gc-suivi-inputs">';
	$html .= '<input type="text" name="gc_search_dossier" class="gc-form-control" placeholder="Application number (e.g. GC-2026-4821)" autocomplete="off" required>';
	$html .= '<input type="text" name="gc_search_identity" class="gc-form-control" placeholder="Your last or first name (optional if email provided)" autocomplete="off">';
	$html .= '<input type="email" name="gc_search_email" class="gc-form-control" placeholder="Your email address (optional if name provided)" autocomplete="off">';
	$html .= '<button type="submit" class="gc-btn-hero-orange" style="padding: 12px 22px; font-size: 15px; border: none; cursor: pointer;">Check my application</button>';
	$html .= '</div>';
	$html .= '</form>';
	$html .= $result;
	$html .= '</div>';

	return $html;
}

function gc_save_private_upload( array $file, string $dossier, string $doc_type = 'id' ) {
	if ( empty( $file['name'] ) || UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return false;
	}

	// 1. Contrôle strict de la taille (< 5 Mo)
	if ( $file['size'] > 5 * 1024 * 1024 ) {
		return false;
	}

	// 2. Contrôle du type MIME réel via finfo
	$finfo = finfo_open( FILEINFO_MIME_TYPE );
	$real_mime = finfo_file( $finfo, $file['tmp_name'] );
	finfo_close( $finfo );

	$allowed_mimes = array(
		'image/jpeg'      => 'jpg',
		'image/png'       => 'png',
		'application/pdf' => 'pdf',
	);

	if ( ! isset( $allowed_mimes[ $real_mime ] ) ) {
		return false;
	}

	$ext = $allowed_mimes[ $real_mime ];

	// 3. Détermination du chemin de stockage hors web
	$config_path = dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'app_config.php';
	$target_dir  = '';
	if ( file_exists( $config_path ) ) {
		require_once $config_path;
		if ( defined( 'GC_SECURE_STORAGE_PATH' ) ) {
			$target_dir = GC_SECURE_STORAGE_PATH;
		}
	}
	if ( empty( $target_dir ) ) {
		$target_dir = dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'secure_storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
	}

	if ( ! file_exists( $target_dir ) ) {
		wp_mkdir_p( $target_dir );
		@file_put_contents( $target_dir . '.htaccess', "Order deny,allow\nDeny from all\n" );
	}

	// 4. Renommage aléatoire et unique du fichier
	$prefix    = 'situation' === $doc_type ? 'sit' : 'cni';
	$filename  = sprintf( 'doc_%s_%s_%s.%s', $prefix, bin2hex( random_bytes( 8 ) ), time(), $ext );
	$full_path = rtrim( $target_dir, DIRECTORY_SEPARATOR . '/' ) . DIRECTORY_SEPARATOR . $filename;

	if ( move_uploaded_file( $file['tmp_name'], $full_path ) ) {
		// Permissions restrictives sur le fichier téléversé
		@chmod( $full_path, 0640 );
		return array(
			'filename' => $filename,
			'original' => sanitize_file_name( $file['name'] ),
		);
	}

	return false;
}

/**
 * Téléchargement / Visualisation sécurisée d'un document pour l'administration.
 */
add_action( 'admin_post_gc_download_doc', 'gc_serve_private_download' );
function gc_serve_private_download() {
	if ( ! current_user_can( 'gc_view' ) ) {
		wp_die( 'Access denied.', 'Security', array( 'response' => 403 ) );
	}

	$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
	$type    = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'id';
	$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'gc_download_' . $post_id ) ) {
		wp_die( 'Link expired or invalid.', 'Security', array( 'response' => 403 ) );
	}

	$meta_key  = 'situation' === $type ? 'gc_situation_document' : 'gc_id_document';
	$file_name = (string) get_post_meta( $post_id, $meta_key, true );

	if ( empty( $file_name ) ) {
		wp_die( 'Document not found.', 'Error', array( 'response' => 404 ) );
	}

	$config_path = dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'app_config.php';
	$storage_dir = dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'secure_storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
	if ( file_exists( $config_path ) ) {
		require_once $config_path;
		if ( defined( 'GC_SECURE_STORAGE_PATH' ) ) {
			$storage_dir = GC_SECURE_STORAGE_PATH;
		}
	}

	$file_path = realpath( $storage_dir . basename( $file_name ) );

	if ( ! $file_path || ! file_exists( $file_path ) ) {
		wp_die( 'File missing from the secure server.', 'Error', array( 'response' => 404 ) );
	}

	$finfo = finfo_open( FILEINFO_MIME_TYPE );
	$type_mime = finfo_file( $finfo, $file_path );
	finfo_close( $finfo );

	header( 'Content-Type: ' . $type_mime );
	header( 'Content-Length: ' . filesize( $file_path ) );
	header( 'Content-Disposition: inline; filename="' . basename( $file_name ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $file_path );
	exit;
}

/**
 * Redirige vers la page du formulaire avec un paramètre de résultat.
 *
 * @param array<string,string> $args Paramètres de requête.
 */
function gc_form_redirect( array $args ) {
	$back = wp_get_referer();
	if ( ! $back ) {
		$back = home_url( '/soumettre-une-demande/' );
	}
	$back = remove_query_arg( array( 'gc_ok', 'gc_err' ), $back );
	wp_safe_redirect( add_query_arg( $args, $back ) );
	exit;
}

/**
 * Traite l'envoi du formulaire via PDO sécurisé avec utilisateur MySQL restreint.
 */
function gc_handle_submit() {
	if ( ! isset( $_POST['gc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gc_nonce'] ) ), 'gc_submit' ) ) {
		gc_form_redirect( array( 'gc_err' => 'nonce' ) );
	}

	// Piège à robots : champ caché rempli = simulation de succès silencieux.
	if ( ! empty( $_POST['gc_website'] ) ) {
		gc_form_redirect( array( 'gc_ok' => 'GC-0000-0000' ) );
	}

	if ( gc_is_closed() ) {
		gc_form_redirect( array( 'gc_err' => 'closed' ) );
	}

	// Limite : 5 envois par heure et par adresse IP.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key = 'gc_rl_' . md5( $ip );
	$hit = (int) get_transient( $key );
	if ( $hit >= 5 ) {
		gc_form_redirect( array( 'gc_err' => 'rate' ) );
	}
	set_transient( $key, $hit + 1, HOUR_IN_SECONDS );

	// 1. Récupération & Assainissement des champs
	$firstname      = isset( $_POST['gc_firstname'] ) ? sanitize_text_field( wp_unslash( $_POST['gc_firstname'] ) ) : '';
	$lastname       = isset( $_POST['gc_lastname'] ) ? sanitize_text_field( wp_unslash( $_POST['gc_lastname'] ) ) : '';
	$full_name      = trim( $firstname . ' ' . $lastname );
	$email          = isset( $_POST['gc_email'] ) ? sanitize_email( wp_unslash( $_POST['gc_email'] ) ) : '';
	$phone_code     = isset( $_POST['gc_phone_code'] ) ? sanitize_text_field( wp_unslash( $_POST['gc_phone_code'] ) ) : '';
	$phone_local    = isset( $_POST['gc_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['gc_phone'] ) ) : '';
	$country        = isset( $_POST['gc_country'] ) ? sanitize_text_field( wp_unslash( $_POST['gc_country'] ) ) : '';
	$city           = isset( $_POST['gc_city'] ) ? sanitize_text_field( wp_unslash( $_POST['gc_city'] ) ) : '';
	$situation_pro  = isset( $_POST['gc_situation_pro'] ) ? sanitize_text_field( wp_unslash( $_POST['gc_situation_pro'] ) ) : '';
	$foyer          = isset( $_POST['gc_foyer'] ) ? sanitize_text_field( wp_unslash( $_POST['gc_foyer'] ) ) : '';
	$urgency        = isset( $_POST['gc_urgency'] ) ? sanitize_text_field( wp_unslash( $_POST['gc_urgency'] ) ) : 'moderee';
	$situation      = isset( $_POST['gc_situation'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gc_situation'] ) ) : '';
	$has_missing    = ! empty( $_POST['gc_has_missing_doc'] );
	$missing_reason = isset( $_POST['gc_missing_doc_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gc_missing_doc_reason'] ) ) : '';

	// 2. Vérification de l'attestation sur l'honneur obligatoire
	if ( empty( $_POST['gc_consent'] ) ) {
		gc_form_redirect( array( 'gc_err' => 'consent' ) );
	}

	// 3. Validation des champs obligatoires
	if ( '' === $firstname || '' === $lastname || '' === $country || '' === $city || strlen( $situation ) < 30 ) {
		gc_form_redirect( array( 'gc_err' => 'invalid' ) );
	}

	// 4. Validation de l'adresse e-mail
	if ( empty( $email ) || ! is_email( $email ) ) {
		gc_form_redirect( array( 'gc_err' => 'email' ) );
	}

	// 5. Validation du téléphone
	$phone_digits = preg_replace( '/\D+/', '', $phone_local );
	if ( strlen( $phone_digits ) < 7 || strlen( $phone_digits ) > 15 ) {
		gc_form_redirect( array( 'gc_err' => 'phone' ) );
	}
	$phone_display = trim( $phone_code . ' ' . $phone_local );

	// 6. Connexion PDO avec l'utilisateur restreint MySQL acfe_public
	$config_path = dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'app_config.php';
	if ( file_exists( $config_path ) ) {
		require_once $config_path;
	}
	$pdo = null;
	if ( defined( 'GC_DB_PUBLIC_USER' ) && defined( 'GC_DB_PUBLIC_PASS' ) ) {
		try {
			$dsn = sprintf( 'mysql:host=%s;port=%d;dbname=%s;charset=%s', GC_DB_HOST, GC_DB_PORT, GC_DB_NAME, GC_DB_CHARSET );
			$pdo = new PDO( $dsn, GC_DB_PUBLIC_USER, GC_DB_PUBLIC_PASS, array(
				PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES   => false,
			) );
		} catch ( Exception $e ) {
			error_log( 'Public PDO error: ' . $e->getMessage() );
		}
	}

	// 7. Unicité du téléphone et de l'e-mail via requêtes préparées PDO
	if ( $pdo ) {
		$stmt_phone = $pdo->prepare( 'SELECT id FROM gc_candidatures WHERE phone_digits = ? LIMIT 1' );
		$stmt_phone->execute( array( $phone_digits ) );
		if ( $stmt_phone->fetch() ) {
			gc_form_redirect( array( 'gc_err' => 'duplicate_phone' ) );
		}

		$stmt_email = $pdo->prepare( 'SELECT id FROM gc_candidatures WHERE email = ? LIMIT 1' );
		$stmt_email->execute( array( $email ) );
		if ( $stmt_email->fetch() ) {
			gc_form_redirect( array( 'gc_err' => 'duplicate_email' ) );
		}
	}

	// Génération du numéro de dossier unique
	$dossier = sprintf( 'GC-%s-%04d', current_time( 'Y' ), wp_rand( 1000, 9999 ) );

	// 8. Téléversement sécurisé des 2 pièces justificatives hors web
	$id_doc_data  = false;
	$sit_doc_data = false;

	if ( ! empty( $_FILES['gc_id_document'] ) && ! empty( $_FILES['gc_id_document']['name'] ) ) {
		$id_doc_data = gc_save_private_upload( $_FILES['gc_id_document'], $dossier, 'id' );
	}

	if ( ! empty( $_FILES['gc_situation_document'] ) && ! empty( $_FILES['gc_situation_document']['name'] ) ) {
		$sit_doc_data = gc_save_private_upload( $_FILES['gc_situation_document'], $dossier, 'situation' );
	}

	$id_filename  = $id_doc_data ? $id_doc_data['filename'] : null;
	$id_orig_name = $id_doc_data ? $id_doc_data['original'] : null;
	$sit_filename  = $sit_doc_data ? $sit_doc_data['filename'] : null;
	$sit_orig_name = $sit_doc_data ? $sit_doc_data['original'] : null;

	// 9. Insertion dans la table dédiée gc_candidatures via requête préparée PDO
	if ( $pdo ) {
		$ins_sql = 'INSERT INTO gc_candidatures (
			dossier_num, firstname, lastname, email, phone, phone_digits,
			country, city, situation_pro, foyer, urgency, situation_desc,
			id_document, id_document_orig, situation_document, situation_document_orig,
			has_missing_doc, missing_doc_reason, status, ip_address, created_at, updated_at
		) VALUES (
			?, ?, ?, ?, ?, ?,
			?, ?, ?, ?, ?, ?,
			?, ?, ?, ?,
			?, ?, ?, ?, NOW(), NOW()
		)';
		$ins_stmt = $pdo->prepare( $ins_sql );
		$ins_stmt->execute( array(
			$dossier, $firstname, $lastname, $email, $phone_display, $phone_digits,
			$country, $city, $situation_pro, $foyer, $urgency, $situation,
			$id_filename, $id_orig_name, $sit_filename, $sit_orig_name,
			$has_missing ? 1 : 0, $missing_reason, 'recue', $ip,
		) );
	}

	// 10. Confirmation par E-mail via wp_mail()
	$tracking_url = esc_url( add_query_arg( 'gc_search_dossier', $dossier, home_url( '/suivi-de-dossier/' ) ) );
	$mail_subject = sprintf( '[ACFE] Official acknowledgement of receipt for your application: %s', $dossier );
	$mail_body    = "Hello " . $firstname . ",\n\n"
		. "We confirm the official registration of your application with the ACFE solidarity initiative (A Chance for Everyone).\n\n"
		. "--------------------------------------------------\n"
		. "YOUR UNIQUE APPLICATION NUMBER: " . $dossier . "\n"
		. "--------------------------------------------------\n\n"
		. "TRACK YOUR APPLICATION LIVE:\n"
		. "You can check the progress of your application at any time on our secure portal:\n"
		. $tracking_url . "\n\n"
		. "ETHICAL COMMITMENTS & ABSOLUTE SAFETY:\n"
		. "• 100% FREE PROCESS: Submission, review and payment involve NO fees.\n"
		. "• NO ADVANCE PAYMENT: No one will ever ask you for money, notary fees, tax stamps or gift cards.\n"
		. "• CONFIDENTIALITY: Your information and documents are handled in full confidentiality.\n"
		. "• OFFICIAL CONTACT: If your application is selected among the 58 beneficiaries, you will be contacted directly by our official team.\n\n"
		. "Please keep this message and your application number safe.\n\n"
		. "Kind regards,\n"
		. "The ACFE team — A Chance for Everyone\n"
		. "Solidarity initiative founded by Richard & Debbie Nuttall\n"
		. "Official WhatsApp: +1 (518) 963-6316 | Website: " . home_url() . "\n";

	$from_host = wp_parse_url( home_url(), PHP_URL_HOST );
	$headers   = array(
		'Content-Type: text/plain; charset=UTF-8',
		'From: ACFE — A Chance for Everyone <no-reply@' . ( $from_host ? $from_host : 'acfe.local' ) . '>',
	);
	wp_mail( $email, $mail_subject, $mail_body, $headers );

	gc_form_redirect( array( 'gc_ok' => $dossier ) );
}


/**
 * ============================================================
 * 8. PAGE MENTIONS LÉGALES & RGPD
 * ============================================================
 */
function gc_render_mentions_legales() {
	$url_contact = esc_url( home_url( '/faq/' ) );
	$url_criteres = esc_url( home_url( '/criteres-reglement/' ) );

	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'mentions-legales' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<div class="gc-subpage-header">
			<span class="gc-section-tag">Transparency &amp; Compliance</span>
			<h1 class="gc-subpage-title">Legal Notice &bull; Regulatory Framework &bull; GDPR</h1>
			<p class="gc-subpage-intro">All legal information, data protection guarantees and ethical commitments of the ACFE organisation.</p>
		</div>

		<div class="gc-legal-full-content">
			<!-- Section 1 : Éditeur & Initiative -->
			<div class="gc-legal-block">
				<h2 class="gc-legal-title">1. Publisher Identification &amp; Nature of the Initiative</h2>
				<p>
					The official website <strong>ACFE &bull; A Chance for Everyone</strong> is published by the private philanthropic direct-solidarity initiative founded by <strong>Richard &amp; Debbie Nuttall</strong> following their UK EuroMillions win in 2024.
				</p>
				<ul class="gc-legal-list">
					<li><strong>Name:</strong> ACFE — A Chance for Everyone (Private Direct Grant Initiative).</li>
					<li><strong>Founders and moral guarantors:</strong> Richard Nuttall and Debbie Nuttall.</li>
					<li><strong>Purpose of the programme:</strong> Direct and voluntary award of emergency financial support to 58 individuals or households facing vital, medical or social hardship.</li>
					<li><strong>Secretariat and case coordination:</strong> ACFE Philanthropy Coordination Desk &bull; European &amp; UK liaison offices (Lancashire).</li>
					<li><strong>Official contact:</strong> Verified WhatsApp: +1 (518) 963-6316.</li>
				</ul>
			</div>

			<!-- Section 2 : Origine des fonds & Gratuité -->
			<div class="gc-legal-block">
				<h2 class="gc-legal-title">2. Origin of Funds &amp; Fully Free of Charge (Submission &amp; Review 100% Free)</h2>
				<p>
					All financial aid awarded to beneficiaries comes <strong>exclusively from the personal financial endowment</strong> established by the founders.
				</p>
				<ul class="gc-legal-list">
					<li><strong>No public fundraising:</strong> ACFE neither solicits nor collects any donation, payment or subscription from the public.</li>
					<li><strong>Submission and review 100% free:</strong> Submitting your application as well as its examination, analysis and processing are <strong>strictly free (€0)</strong>. No application fee, processing fee or financial advance will ever be asked of you.</li>
					<li><strong>Safety rule:</strong> No member of the ACFE team will ever ask you for money, stamps, top-up vouchers or confidential bank details. The programme is entirely voluntary.</li>
				</ul>
			</div>

			<!-- Section 3 : Confidentialité & Sécurité -->
			<div class="gc-legal-block">
				<h2 class="gc-legal-title">3. Confidentiality &amp; Data Security</h2>
				<ul class="gc-legal-list">
					<li><strong>Confidentiality of exchanges:</strong> All information and documents submitted with an application are strictly confidential.</li>
					<li><strong>Restricted access for processing:</strong> Only the volunteer team responsible for reviewing applications has access to the supporting documents provided.</li>
					<li><strong>Protection of supporting documents:</strong> Your documents are handled with absolute respect for privacy and are never made public.</li>
				</ul>
			</div>

			<!-- Section 4 : Protection des Données Personnelles (RGPD) -->
			<div class="gc-legal-block" id="rgpd">
				<h2 class="gc-legal-title">4. Personal Data Protection (European GDPR &amp; CNIL)</h2>
				<p>
					In accordance with the General Data Protection Regulation (GDPR - EU Regulation 2016/679) and the French Data Protection Act, the data collected is subject to rigorous and transparent processing:
				</p>
				<ul class="gc-legal-list">
					<li><strong>Purpose of processing:</strong> The information provided in the form is used exclusively to assess the urgency and sincerity of the situation in order to award the 58 available grants.</li>
					<li><strong>Absolute non-commercialisation:</strong> Your data is never commercially cross-referenced, resold, transferred or exchanged with third parties or advertising partners.</li>
					<li><strong>Retention period:</strong> Supporting documents for unsuccessful applications are <strong>permanently deleted</strong> once the award session closes. Data for the 58 beneficiaries is kept only for as long as needed to make the payment.</li>
					<li><strong>Your rights:</strong> You have at any time a right of access, rectification and deletion of your personal data. To exercise this right, simply contact us via WhatsApp and provide your application number.</li>
				</ul>
			</div>

			<!-- Section 5 : Propriété intellectuelle -->
			<div class="gc-legal-block">
				<h2 class="gc-legal-title">5. Intellectual Property</h2>
				<p>
					The name <em>ACFE — A Chance for Everyone</em>, graphic elements, texts and the support programme are protected. Any misleading or unauthorised use will be subject to legal action.
				</p>
			</div>
		</div>

		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * ============================================================
 * PIED DE PAGE INSTITUTIONNEL UNIVERSEL ACFE
 * ============================================================
 *
 * @return string
 */
function gc_render_footer() {
	$img_logo      = esc_url( GC_URL . 'assets/images/logo.svg' );
	$url_accueil   = esc_url( home_url( '/' ) );
	$url_qui       = esc_url( home_url( '/qui-sommes-nous/' ) );
	$url_criteres  = esc_url( home_url( '/criteres-reglement/' ) );
	$url_securite  = esc_url( home_url( '/securite/' ) );
	$url_suivi     = esc_url( home_url( '/suivi-de-dossier/' ) );
	$url_faq       = esc_url( home_url( '/faq/' ) );
	$url_soumettre = esc_url( home_url( '/soumettre-une-demande/' ) );
	$url_mentions  = esc_url( home_url( '/mentions-legales/' ) );

	ob_start();
	?>
	<footer class="gc-site-footer">
		<div class="gc-footer-main">
			<div class="gc-footer-col gc-footer-brand-col">
				<div class="gc-footer-brand">
					<img src="<?php echo $img_logo; ?>" alt="Logo ACFE" class="gc-footer-logo" />
					<div class="gc-footer-brand-text">
						<span class="gc-footer-brand-title">ACFE</span>
						<span class="gc-footer-brand-tagline">A Chance for Everyone</span>
					</div>
				</div>
				<p class="gc-footer-desc">
					Direct solidarity fund initiated by Richard &amp; Debbie Nuttall following their 2024 EuroMillions win. Emergency financial grants awarded to 58 beneficiaries selected with listening, humanity and discernment.
				</p>
			</div>

			<div class="gc-footer-col">
				<h4 class="gc-footer-heading">Portal &amp; Applications</h4>
				<ul class="gc-footer-links">
					<li><a href="<?php echo $url_accueil; ?>">Official home</a></li>
					<li><a href="<?php echo $url_soumettre; ?>">Submit an application</a></li>
					<li><a href="<?php echo $url_suivi; ?>">Track my application live</a></li>
					<li><a href="<?php echo $url_criteres; ?>">Assessment criteria &amp; Scale</a></li>
					<li><a href="<?php echo $url_securite; ?>">Discretion &amp; Safety</a></li>
				</ul>
			</div>

			<div class="gc-footer-col">
				<h4 class="gc-footer-heading">Guarantees &amp; Ethics</h4>
				<ul class="gc-footer-links">
					<li><a href="<?php echo $url_qui; ?>">The founders' story</a></li>
					<li><a href="<?php echo $url_faq; ?>">Frequently asked questions (FAQ)</a></li>
					<li><a href="<?php echo $url_mentions; ?>">Legal notice &amp; Statutes</a></li>
					<li><a href="<?php echo $url_mentions; ?>#rgpd">Data protection (GDPR)</a></li>
					<li><a href="<?php echo $url_securite; ?>">Non-solicitation charter</a></li>
				</ul>
			</div>

			<div class="gc-footer-col">
				<h4 class="gc-footer-heading">Official contact</h4>
				<p class="gc-footer-contact-item">
					<strong>Verified Official WhatsApp:</strong><br>
					<span class="gc-footer-contact-val">+1 (518) 963-6316</span>
				</p>
				<p class="gc-footer-note-sub">
					Volunteer support Monday to Friday. No external canvasser is authorised.
				</p>
			</div>
		</div>

		<div class="gc-footer-bottom">
			<div class="gc-footer-bottom-inner">
				<p class="gc-footer-copy">
					&copy; <?php echo esc_html( date( 'Y' ) ); ?> ACFE &bull; A Chance for Everyone. Private philanthropic direct-solidarity initiative &bull; All rights reserved.
				</p>
				<div class="gc-footer-legal-links">
					<a href="<?php echo $url_mentions; ?>">Legal notice &amp; Statutes</a>
					<span>&bull;</span>
					<a href="<?php echo $url_mentions; ?>#rgpd">Privacy policy</a>
					<span>&bull;</span>
					<a href="<?php echo $url_criteres; ?>">Programme rules</a>
				</div>
			</div>
		</div>
	</footer>
	<?php
	return ob_get_clean();
}

/**
 * ============================================================
 * PAGE NOTRE HISTOIRE — Vidéo de Richard & Debbie + transcription
 * ============================================================
 */
function gc_render_notre_histoire() {
	$url_soumettre = esc_url( home_url( '/soumettre-une-demande/' ) );
	$video_url     = esc_url( GC_URL . 'assets/video/testimonial.mp4' );

	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'notre-histoire' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<section class="gc-story-section">
			<div class="gc-story-inner">

				<!-- Titre principal -->
				<div class="gc-story-header">
					<div class="gc-hero-tag">THEIR STORY IN THEIR OWN WORDS</div>
					<h1 class="gc-story-title">Richard &amp; Debbie Nuttall — A Message From the Heart</h1>
					<p class="gc-story-lead">
						After winning the EuroMillions, Richard and Debbie decided to give back — not through an institution, but directly, personally, to families who truly need it.
						Watch their message below.
					</p>
				</div>

				<!-- Lecteur vidéo -->
				<div class="gc-video-wrapper">
					<video
						id="gc-testimonial-video"
						class="gc-video-player"
						controls
						preload="metadata"
						playsinline
						poster="<?php echo esc_url( GC_URL . 'assets/images/richard-debbie-nuttall.png' ); ?>"
					>
						<source src="<?php echo $video_url; ?>" type="video/mp4" />
						Your browser does not support the video tag.
					</video>
				</div>

				<!-- Transcription / Suite du message -->
				<div class="gc-story-transcript">
					<div class="gc-transcript-label">Full message from Richard &amp; Debbie</div>
					<blockquote class="gc-transcript-text">
						<p>
							Hi everyone. Look, since Richard and I won the EuroMillions, our lives completely changed.
							But we know how hard things are out there right now for so many people.
						</p>
						<p>
							We can't help the whole world, but we really want to help <strong>58 families</strong> who are genuinely struggling.
							If that's you, just go to our website, fill out the form, and tell us what you're going through.
						</p>
						<p>
							It takes only a few minutes. There is <strong>absolutely no cost</strong> to apply — this is our personal commitment, not a business.
							We read every single application ourselves. We want to understand your real situation.
						</p>
						<p>
							We can't promise everyone will be chosen — there are only 58 spots — but we can promise that every dossier will be read with care, with respect, and with genuine attention.
						</p>
						<p>
							If your application is selected, we will contact you directly and personally.
							No middle man. No agency. Just us.
						</p>
						<p>
							So please — if you're struggling, don't hesitate. Submit your file. You have nothing to lose, and it could truly change your life.
						</p>
						<p>
							— <strong>Richard &amp; Debbie Nuttall</strong>
						</p>
					</blockquote>
				</div>

				<!-- CTA -->
				<div class="gc-story-cta">
					<a href="<?php echo $url_soumettre; ?>" class="gc-btn-hero-orange">Apply now &rarr;</a>
				</div>

			</div>
		</section>

		<?php echo gc_render_footer(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * ============================================================
 * PAGE 404 — PAGE INTROUVABLE
 * ============================================================
 *
 * @return string
 */
function gc_render_404() {
	$url_accueil   = esc_url( home_url( '/' ) );
	$url_soumettre = esc_url( home_url( '/soumettre-une-demande/' ) );
	$url_faq       = esc_url( home_url( '/faq/' ) );
	$url_suivi     = esc_url( home_url( '/suivi-de-dossier/' ) );

	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( '404' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<section class="gc-404-section" aria-labelledby="gc-404-title">
			<p class="gc-404-code" aria-hidden="true">404</p>
			<span class="gc-section-tag">Page not found</span>
			<h1 id="gc-404-title" class="gc-404-title">This page does not exist or has been moved</h1>
			<p class="gc-404-text">
				The link you followed is incorrect, outdated, or the page has been removed.
				You can return to the home page or use one of the shortcuts below.
			</p>
			<div class="gc-404-actions">
				<a href="<?php echo $url_accueil; ?>" class="gc-btn-cta-orange">Back to home</a>
				<a href="<?php echo $url_soumettre; ?>" class="gc-btn-hero-ghost">Apply now</a>
			</div>
			<ul class="gc-404-links">
				<li><a href="<?php echo $url_suivi; ?>">Track your application</a></li>
				<li><a href="<?php echo $url_faq; ?>">Contact &amp; FAQ</a></li>
			</ul>
		</section>

		<?php echo gc_render_footer(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Remplace le template 404 du thème par la page ACFE.
 *
 * @param string $template Chemin du template WordPress.
 * @return string
 */
function gc_override_404_template( $template ) {
	if ( ! is_404() ) {
		return $template;
	}

	$custom = GC_DIR . 'templates/404.php';
	if ( file_exists( $custom ) ) {
		return $custom;
	}

	return $template;
}