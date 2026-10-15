<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gc_render_accueil() {
	$url_soumettre = esc_url( home_url( '/soumettre-une-demande/' ) );
	$url_qui = esc_url( home_url( '/qui-sommes-nous/' ) );
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'accueil' ); ?>
		<section class="gc-hero-split">
			<div class="gc-hero-split-container">
				<div class="gc-hero-left">
					<h1>A Chance for Everyone</h1>
					<p>Solidarité gratuite. Aucune somme d'argent ne vous sera jamais demandée.</p>
					<a class="gc-btn-cta-orange" href="<?php echo $url_soumettre; ?>">Apply now</a>
					<a class="gc-nav-link" href="<?php echo $url_qui; ?>">About us</a>
				</div>
			</div>
		</section>
		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

function gc_render_qui_sommes_nous() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'qui-sommes-nous' ); ?>
		<section class="gc-section"><div class="gc-section-inner">
			<h1>About us</h1>
			<p>ACFE is a solidarity initiative founded by Richard &amp; Debbie Nuttall.</p>
		</div></section>
		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

function gc_render_reglement() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'criteres-reglement' ); ?>
		<section class="gc-section"><div class="gc-section-inner">
			<h1>Criteria &amp; Rules</h1>
			<p>Applications are reviewed according to published criteria. The process is free of charge.</p>
		</div></section>
		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

function gc_render_securite() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'securite' ); ?>
		<section class="gc-section"><div class="gc-section-inner">
			<h1>Safety &amp; Free of charge</h1>
			<p>ACFE never asks for money. Beware of scams using our name.</p>
		</div></section>
		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

require_once __DIR__ . '/form-pages2b.php';
