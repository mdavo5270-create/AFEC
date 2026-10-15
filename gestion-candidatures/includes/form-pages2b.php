<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gc_render_suivi() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'suivi' ); ?>
		<section class="gc-section"><div class="gc-section-inner">
			<h1>Track your application</h1>
			<?php echo gc_render_suivi_box(); ?>
		</div></section>
		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

function gc_render_faq() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'faq' ); ?>
		<section class="gc-section"><div class="gc-section-inner">
			<h1>Contact &amp; FAQ</h1>
			<p>Official WhatsApp: +1 (518) 963-6316</p>
			<p>We never ask for money by message or phone.</p>
		</div></section>
		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

function gc_render_soumettre_demande() {
	$errors = gc_form_errors();
	$err_key = isset( $_GET['gc_err'] ) ? sanitize_key( wp_unslash( $_GET['gc_err'] ) ) : '';
	$ok = isset( $_GET['gc_ok'] ) ? sanitize_text_field( wp_unslash( $_GET['gc_ok'] ) ) : '';
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'soumettre' ); ?>
		<section class="gc-section"><div class="gc-section-inner">
			<h1>Submit an application</h1>
			<?php if ( $ok ) : ?>
				<div class="gc-suivi-result success"><p>Application received. Reference: <strong><?php echo esc_html( $ok ); ?></strong></p></div>
			<?php elseif ( $err_key && isset( $errors[ $err_key ] ) ) : ?>
				<div class="gc-suivi-result error"><p><?php echo esc_html( $errors[ $err_key ] ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! $ok ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="gc-application-form" autocomplete="off">
				<?php wp_nonce_field( 'gc_submit', 'gc_nonce' ); ?>
				<input type="hidden" name="action" value="gc_submit">
				<p style="position:absolute;left:-9999px;" aria-hidden="true"><label>Website<input type="text" name="gc_website" tabindex="-1" autocomplete="off"></label></p>
				<div class="gc-form-group"><label for="gc_firstname">First name *</label><input class="gc-form-control" type="text" name="gc_firstname" id="gc_firstname" required maxlength="100"></div>
				<div class="gc-form-group"><label for="gc_lastname">Last name *</label><input class="gc-form-control" type="text" name="gc_lastname" id="gc_lastname" required maxlength="100"></div>
				<div class="gc-form-group"><label for="gc_email">Email *</label><input class="gc-form-control" type="email" name="gc_email" id="gc_email" required maxlength="190"></div>
				<div class="gc-form-group"><label for="gc_phone_code">Phone country code</label><input class="gc-form-control" type="text" name="gc_phone_code" id="gc_phone_code" maxlength="8" placeholder="+33"></div>
				<div class="gc-form-group"><label for="gc_phone">Phone *</label><input class="gc-form-control" type="tel" name="gc_phone" id="gc_phone" required maxlength="30"></div>
				<div class="gc-form-group"><label for="gc_country">Country *</label><input class="gc-form-control" type="text" name="gc_country" id="gc_country" required maxlength="100"></div>
				<div class="gc-form-group"><label for="gc_city">City *</label><input class="gc-form-control" type="text" name="gc_city" id="gc_city" required maxlength="150"></div>
				<div class="gc-form-group"><label for="gc_situation">Your situation * (min. 30 characters)</label><textarea class="gc-form-control" name="gc_situation" id="gc_situation" required minlength="30" maxlength="5000" rows="6"></textarea></div>
				<div class="gc-form-group"><label for="gc_id_document">ID document (PDF/JPG/PNG, max 5 MB)</label><input type="file" name="gc_id_document" id="gc_id_document" accept=".pdf,.jpg,.jpeg,.png"></div>
				<div class="gc-form-group"><label><input type="checkbox" name="gc_consent" value="1" required> I certify on my honour that the information is accurate *</label></div>
				<button type="submit" class="gc-submit-btn-orange">Submit application</button>
			</form>
			<?php endif; ?>
		</div></section>
		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

require_once __DIR__ . '/form-pages3.php';
