<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gc_render_accueil() {
	$places_total  = function_exists( 'gc_places' ) ? gc_places() : 0;
	$url_soumettre = esc_url( home_url( '/soumettre-une-demande/' ) );
	$url_qui       = esc_url( home_url( '/qui-sommes-nous/' ) );
	$url_suivi     = esc_url( home_url( '/suivi-de-dossier/' ) );
	$url_faq       = esc_url( home_url( '/faq/' ) );

	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'accueil' ); ?>

		<section class="gc-hero-split">
			<div class="gc-hero-split-container">
				<div class="gc-hero-left">
					<div class="gc-hero-tag">DIRECT SOLIDARITY AID</div>
					<h1 class="gc-hero-main-title">Support that reaches people directly</h1>
					<p class="gc-hero-lead">
						Founded by <strong>Richard &amp; Debbie Nuttall</strong>. A free solidarity initiative.
						No money will ever be asked of you.
					</p>
					<div class="gc-hero-actions">
						<a class="gc-btn-cta-orange" href="<?php echo $url_soumettre; ?>">Apply now</a>
						<a class="gc-nav-link" href="<?php echo $url_qui; ?>">About us</a>
					</div>
					<?php if ( $places_total ) : ?>
						<p class="gc-hero-places"><?php echo esc_html( (string) $places_total ); ?> places in this session</p>
					<?php endif; ?>
				</div>
				<div class="gc-hero-right">
					<div class="gc-hero-card">
						<h2>A Chance for Everyone</h2>
						<ul class="gc-hero-list">
							<li>100% free application</li>
							<li>Transparent selection process</li>
							<li>Direct support to selected households</li>
							<li>Never pay anyone claiming to represent us</li>
						</ul>
						<a class="gc-btn-cta-orange" href="<?php echo $url_soumettre; ?>">Submit an application</a>
					</div>
				</div>
			</div>
		</section>

		<section class="gc-section">
			<div class="gc-section-inner gc-grid-3">
				<div class="gc-card">
					<h3>Apply</h3>
					<p>Fill in the official form with accurate information and supporting documents.</p>
					<a href="<?php echo $url_soumettre; ?>">Go to the form &rarr;</a>
				</div>
				<div class="gc-card">
					<h3>Track</h3>
					<p>Already applied? Follow the status of your application with your file number.</p>
					<a href="<?php echo $url_suivi; ?>">Track my file &rarr;</a>
				</div>
				<div class="gc-card">
					<h3>Stay safe</h3>
					<p>We never ask for money by phone, SMS or WhatsApp. Report any scam attempt.</p>
					<a href="<?php echo $url_faq; ?>">Security &amp; FAQ &rarr;</a>
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
		<section class="gc-section">
			<div class="gc-section-inner">
				<h1>Who we are</h1>
				<p><strong>ACFE — A Chance for Everyone</strong> is a free solidarity initiative founded by Richard &amp; Debbie Nuttall.</p>
				<p>Our goal is to provide direct support to households in difficulty, with a transparent process and no payment required at any stage.</p>
				<h2>Our principles</h2>
				<ul>
					<li>Free application and free support</li>
					<li>Clear eligibility rules published on this site</li>
					<li>Careful review of each complete application</li>
					<li>Zero tolerance for intermediaries asking for money</li>
				</ul>
				<p><a class="gc-btn-cta-orange" href="<?php echo esc_url( home_url( '/soumettre-une-demande/' ) ); ?>">Apply now</a></p>
			</div>
		</section>
		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

function gc_render_reglement() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'reglement' ); ?>
		<section class="gc-section">
			<div class="gc-section-inner">
				<h1>Rules &amp; eligibility</h1>
				<p>Applications are reviewed according to published criteria. Incomplete files may be delayed or declined.</p>
				<ul>
					<li>One application per person / household</li>
					<li>Accurate contact details (phone and email)</li>
					<li>Supporting documents when available</li>
					<li>Honest description of your situation</li>
				</ul>
				<p>Submitting false information leads to exclusion from the process.</p>
			</div>
		</section>
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
		<section class="gc-section">
			<div class="gc-section-inner">
				<h1>Security &amp; anti-scam</h1>
				<p><strong>We will never ask you to pay money</strong> to submit or process an application.</p>
				<ul>
					<li>Ignore anyone asking for fees via WhatsApp, SMS or phone</li>
					<li>Only use this official website</li>
					<li>Keep your application number private</li>
					<li>Contact us only through the official channels listed on the FAQ page</li>
				</ul>
			</div>
		</section>
		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}
