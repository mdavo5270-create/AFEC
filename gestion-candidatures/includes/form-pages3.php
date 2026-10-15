<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gc_render_mentions_legales() {
	$url_contact = esc_url( home_url( '/faq/' ) );
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'faq' ); ?>
		<section class="gc-section">
			<div class="gc-section-inner">
				<h1>Mentions légales &amp; RGPD</h1>
				<p>Les données collectées via le formulaire de candidature sont utilisées exclusivement pour l'instruction des dossiers de l'initiative ACFE.</p>
				<p>Conformément au RGPD, vous disposez d'un droit d'accès, de rectification et de suppression. Contact : via la page <a href="<?php echo $url_contact; ?>">FAQ / Contact</a>.</p>
				<p>Aucune donnée n'est cédée à des tiers à des fins commerciales. Les pièces d'identité sont stockées hors webroot, avec accès restreint.</p>
			</div>
		</section>
		<?php echo function_exists( 'gc_render_footer' ) ? gc_render_footer() : ''; ?>
	</div>
	<?php
	return ob_get_clean();
}

function gc_render_footer() {
	ob_start();
	?>
	<footer class="gc-site-footer">
		<div class="gc-footer-inner">
			<p><strong>ACFE</strong> — A Chance for Everyone</p>
			<p>Initiative solidaire fondée par Richard &amp; Debbie Nuttall</p>
			<p>WhatsApp officiel : +1 (518) 963-6316</p>
			<p><a href="<?php echo esc_url( home_url( '/mentions-legales/' ) ); ?>">Mentions légales</a></p>
		</div>
	</footer>
	<?php
	return ob_get_clean();
}

function gc_render_notre_histoire() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'notre-histoire' ); ?>
		<section class="gc-section">
			<div class="gc-section-inner">
				<h1>Our story</h1>
				<p>ACFE — A Chance for Everyone — is a solidarity initiative founded by Richard &amp; Debbie Nuttall to support people facing hardship, free of charge.</p>
			</div>
		</section>
		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

function gc_render_404() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'accueil' ); ?>
		<section class="gc-section">
			<div class="gc-section-inner">
				<h1>Page not found</h1>
				<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to home</a></p>
			</div>
		</section>
		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

function gc_override_404_template( $template ) {
	if ( is_404() ) {
		$custom = GC_PATH . 'templates/404.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	return $template;
}
