<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gc_render_suivi() {
	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'suivi' ); ?>
		<section class="gc-section"><div class="gc-section-inner">
			<h1>Track your application</h1>
			<p>Enter your application number and either your full name or the email used when applying.</p>
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
			<p><strong>Official WhatsApp:</strong> +1 (518) 963-6316</p>
			<p>We never ask for money by message or phone. Anyone requesting payment is a scammer.</p>
			<h2>Common questions</h2>
			<ul>
				<li><strong>Is the application free?</strong> Yes. Always.</li>
				<li><strong>How do I track my file?</strong> Use the tracking page with your application number.</li>
				<li><strong>How long does the review take?</strong> It depends on volume; incomplete files take longer.</li>
				<li><strong>Can someone apply for me against payment?</strong> No. Refuse and report.</li>
			</ul>
		</div></section>
		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

function gc_render_soumettre_demande() {
	if ( function_exists( 'gc_is_closed' ) && gc_is_closed() ) {
		ob_start();
		echo '<div class="gc-page-container">';
		echo gc_render_header_nav( 'soumettre-demande' );
		echo '<section class="gc-section"><div class="gc-section-inner"><h1>Applications closed</h1><p>Applications are temporarily closed. Please check back later.</p></div></section>';
		echo gc_render_footer();
		echo '</div>';
		return ob_get_clean();
	}

	$err = isset( $_GET['gc_err'] ) ? sanitize_key( wp_unslash( $_GET['gc_err'] ) ) : '';
	$ok  = isset( $_GET['gc_ok'] ) ? sanitize_text_field( wp_unslash( $_GET['gc_ok'] ) ) : '';
	$errors = function_exists( 'gc_form_errors' ) ? gc_form_errors() : array();

	ob_start();
	?>
	<div class="gc-page-container">
		<?php echo gc_render_header_nav( 'soumettre-demande' ); ?>
		<section class="gc-section"><div class="gc-section-inner">
			<h1>Submit an application</h1>
			<p>All fields marked as required must be completed. Never pay anyone to submit this form.</p>

			<?php if ( $ok ) : ?>
				<div class="gc-alert success">Your application has been registered. Reference: <strong><?php echo esc_html( $ok ); ?></strong></div>
			<?php elseif ( $err && isset( $errors[ $err ] ) ) : ?>
				<div class="gc-alert error"><?php echo esc_html( $errors[ $err ] ); ?></div>
			<?php elseif ( $err ) : ?>
				<div class="gc-alert error">The form could not be submitted. Please check your information and try again.</div>
			<?php endif; ?>

			<form class="gc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="gc_submit_application">
				<?php wp_nonce_field( 'gc_submit_application', 'gc_submit_nonce' ); ?>

				<h2>Identity</h2>
				<div class="gc-form-row">
					<label>First name *<input type="text" name="gc_firstname" required maxlength="100"></label>
					<label>Last name *<input type="text" name="gc_lastname" required maxlength="100"></label>
				</div>
				<div class="gc-form-row">
					<label>Email *<input type="email" name="gc_email" required maxlength="190"></label>
					<label>Phone code *<input type="text" name="gc_phone_code" required maxlength="8" placeholder="+229"></label>
					<label>Phone number *<input type="text" name="gc_phone_local" required maxlength="30"></label>
				</div>
				<div class="gc-form-row">
					<label>Country *<input type="text" name="gc_country" required maxlength="100"></label>
					<label>City *<input type="text" name="gc_city" required maxlength="150"></label>
				</div>

				<h2>Situation</h2>
				<div class="gc-form-row">
					<label>Professional situation<input type="text" name="gc_situation_pro" maxlength="150"></label>
					<label>Household<input type="text" name="gc_foyer" maxlength="100"></label>
					<label>Urgency
						<select name="gc_urgency">
							<option value="urgente">Urgent</option>
							<option value="moderee" selected>Moderate</option>
							<option value="planifiee">Planned</option>
						</select>
					</label>
				</div>
				<label>Describe your situation *
					<textarea name="gc_situation" rows="6" required maxlength="5000"></textarea>
				</label>

				<h2>Documents (PDF, JPG or PNG — max 5 MB)</h2>
				<label>ID document<input type="file" name="gc_id_document" accept=".pdf,.jpg,.jpeg,.png"></label>
				<label>Supporting document<input type="file" name="gc_situation_document" accept=".pdf,.jpg,.jpeg,.png"></label>
				<label><input type="checkbox" name="gc_has_missing_doc" value="1"> I cannot provide a document</label>
				<label>Reason if missing<textarea name="gc_missing_doc_reason" rows="3" maxlength="2000"></textarea></label>

				<label class="gc-consent"><input type="checkbox" name="gc_consent" value="1" required> I certify on my honour that the information provided is accurate. *</label>

				<button type="submit" class="gc-btn-cta-orange">Submit application</button>
			</form>
		</div></section>
		<?php echo gc_render_footer(); ?>
	</div>
	<?php
	return ob_get_clean();
}

function gc_render_suivi_box() {
	$result  = '';
	$prefill = '';
	if ( isset( $_GET['gc_search_dossier'] ) ) {
		$prefill = strtoupper( preg_replace( '/[^A-Za-z0-9\-]/', '', sanitize_text_field( wp_unslash( $_GET['gc_search_dossier'] ) ) ) );
		$prefill = substr( $prefill, 0, 30 );
	}

	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['gc_track_nonce'] ) ) {
		$error_html = function ( $message ) {
			return '<div class="gc-suivi-result error"><p style="margin:0;color:#991b1b;font-weight:600;">' . esc_html( $message ) . '</p></div>';
		};
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gc_track_nonce'] ) ), 'gc_track' ) ) {
			$result = $error_html( 'Your session has expired. Please reload the page and try again.' );
		} elseif ( gc_rate_limited( 'track', 10, 15 * MINUTE_IN_SECONDS ) ) {
			$result = $error_html( 'Too many attempts. Please try again in a few minutes.' );
		} else {
			$raw_dossier  = isset( $_POST['gc_search_dossier'] ) ? sanitize_text_field( wp_unslash( $_POST['gc_search_dossier'] ) ) : '';
			$raw_identity = isset( $_POST['gc_search_identity'] ) ? sanitize_text_field( wp_unslash( $_POST['gc_search_identity'] ) ) : '';
			$raw_email    = isset( $_POST['gc_search_email'] ) ? sanitize_email( wp_unslash( $_POST['gc_search_email'] ) ) : '';
			$dossier_query = strtoupper( preg_replace( '/[^A-Za-z0-9\-]/', '', $raw_dossier ) );
			$identity_normalized = trim( preg_replace( '/\s+/', ' ', gc_normalize_string( $raw_identity ) ) );
			$email_query = strtolower( trim( $raw_email ) );
			if ( '' === $dossier_query || ( '' === $identity_normalized && '' === $email_query ) ) {
				$result = $error_html( 'Please enter your application number and either your full name or your email address.' );
			} else {
				$pdo = gc_public_pdo();
				$row = null;
				if ( $pdo ) {
					try {
						$stmt = $pdo->prepare( 'SELECT id, dossier_num, firstname, lastname, email, status FROM gc_candidatures WHERE dossier_num = ? LIMIT 1' );
						$stmt->execute( array( $dossier_query ) );
						$row = $stmt->fetch();
					} catch ( Exception $e ) {
						error_log( 'Tracking query error: ' . $e->getMessage() );
					}
				}
				$verified = false;
				if ( $row ) {
					if ( '' !== $email_query && hash_equals( strtolower( trim( (string) $row['email'] ) ), $email_query ) ) {
						$verified = true;
					}
					if ( ! $verified && '' !== $identity_normalized ) {
						$fn = gc_normalize_string( $row['firstname'] );
						$ln = gc_normalize_string( $row['lastname'] );
						$full = trim( preg_replace( '/\s+/', ' ', $fn . ' ' . $ln ) );
						$rev  = trim( preg_replace( '/\s+/', ' ', $ln . ' ' . $fn ) );
						if ( '' !== $full && ( hash_equals( $full, $identity_normalized ) || hash_equals( $rev, $identity_normalized ) ) ) {
							$verified = true;
						}
					}
				}
				$labels = array(
					'en_attente' => 'Application received — awaiting first reading',
					'recue' => 'Application received — awaiting first reading',
					'en_verification' => 'Supporting documents under verification',
					'en_analyse' => 'Under careful review',
					'liste_courte' => 'Selected for the next review stage',
					'retenue' => 'Application selected — you will be contacted',
					'contactee' => 'Contact in progress',
					'injoignable' => 'Contact attempt made',
					'confirmee' => 'Application confirmed',
					'payee' => 'Aid paid and support completed',
					'refusee' => 'Review completed (not selected for this session)',
				);
				if ( $row && $verified ) {
					$status = (string) $row['status'];
					$label  = $labels[ $status ] ?? 'Under review';
					$result = '<div class="gc-suivi-result success"><div class="gc-suivi-dossier-tag">' . esc_html( $row['dossier_num'] ) . '</div><h3>Current status</h3><p>' . esc_html( $label ) . '</p></div>';
				} elseif ( ! $pdo ) {
					$result = $error_html( 'A technical error occurred. Please try again shortly.' );
				} else {
					$result = $error_html( 'No application matches this information.' );
				}
			}
		}
	}

	$html  = '<div class="gc-suivi-box">';
	$html .= '<form method="post" class="gc-suivi-form">';
	$html .= wp_nonce_field( 'gc_track', 'gc_track_nonce', true, false );
	$html .= '<div class="gc-suivi-inputs">';
	$html .= '<input type="text" name="gc_search_dossier" class="gc-form-control" placeholder="Application number" value="' . esc_attr( $prefill ) . '" maxlength="30" required>';
	$html .= '<input type="text" name="gc_search_identity" class="gc-form-control" placeholder="First and last name" maxlength="200">';
	$html .= '<input type="email" name="gc_search_email" class="gc-form-control" placeholder="Email used for the application" maxlength="190">';
	$html .= '<button type="submit" class="gc-btn-hero-orange">Check my application</button>';
	$html .= '</div></form>';
	$html .= $result;
	$html .= '</div>';
	return $html;
}
