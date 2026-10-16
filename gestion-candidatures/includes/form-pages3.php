<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gc_render_mentions_legales() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'faq' ); ?>
		<section class="gc-section"><div class="gc-section-inner">
			<h1>Legal notice &amp; privacy</h1>
			<p>Data collected through the application form is used solely to process ACFE applications.</p>
			<p>Under GDPR you may request access, rectification or deletion via the FAQ / Contact page.</p>
			<p>Identity documents are stored outside the web root with restricted access.</p>
		</div></section>
		<?php echo gc_render_footer(); ?>
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
			<p>Solidarity initiative founded by Richard &amp; Debbie Nuttall</p>
			<p><a href="<?php echo esc_url( home_url( '/mentions-legales/' ) ); ?>">Legal notice</a> · <a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">FAQ</a></p>
			<p class="gc-footer-note">We never ask for money. Report any scam.</p>
		</div>
	</footer>
	<?php
	return ob_get_clean();
}

function gc_render_notre_histoire() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'qui-sommes-nous' ); ?>
		<section class="gc-section"><div class="gc-section-inner">
			<h1>Our story</h1>
			<p>ACFE was created to channel direct help to families in need, without intermediaries and without fees.</p>
			<p>Richard &amp; Debbie Nuttall founded the initiative to keep the process transparent, human and free of charge.</p>
		</div></section>
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
		<section class="gc-section"><div class="gc-section-inner">
			<h1>Page not found</h1>
			<p>The page you requested does not exist.</p>
			<p><a class="gc-btn-cta-orange" href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to home</a></p>
		</div></section>
		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}
