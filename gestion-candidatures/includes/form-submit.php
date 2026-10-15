<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gc_handle_submit() {
	if ( ! isset( $_POST['gc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gc_nonce'] ) ), 'gc_submit' ) ) {
		gc_form_redirect( array( 'gc_err' => 'nonce' ) );
	}
	if ( ! empty( $_POST['gc_website'] ) ) {
		gc_form_redirect( array( 'gc_ok' => 'GC-0000-0000' ) );
	}
	if ( gc_is_closed() ) {
		gc_form_redirect( array( 'gc_err' => 'closed' ) );
	}
	$ip = gc_client_ip();
	if ( gc_rate_limited( 'submit', 5, HOUR_IN_SECONDS ) ) {
		gc_form_redirect( array( 'gc_err' => 'rate' ) );
	}

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

	$firstname      = gc_clip( $firstname, 100 );
	$lastname       = gc_clip( $lastname, 100 );
	$email          = gc_clip( $email, 190 );
	$phone_code     = gc_clip( $phone_code, 8 );
	$phone_local    = gc_clip( $phone_local, 30 );
	$country        = gc_clip( $country, 100 );
	$city           = gc_clip( $city, 150 );
	$situation_pro  = gc_clip( $situation_pro, 150 );
	$foyer          = gc_clip( $foyer, 100 );
	$situation      = gc_clip( $situation, 5000 );
	$missing_reason = gc_clip( $missing_reason, 2000 );

	if ( empty( $_POST['gc_consent'] ) ) {
		gc_form_redirect( array( 'gc_err' => 'consent' ) );
	}
	if ( strlen( $firstname ) < 2 || strlen( $lastname ) < 2 || strlen( $country ) < 2 || strlen( $city ) < 2 || strlen( $situation ) < 30 ) {
		gc_form_redirect( array( 'gc_err' => 'invalid' ) );
	}
	if ( ! is_email( $email ) ) {
		gc_form_redirect( array( 'gc_err' => 'email' ) );
	}
	$phone_digits = preg_replace( '/\D+/', '', $phone_local );
	if ( strlen( $phone_digits ) < 7 || strlen( $phone_digits ) > 15 ) {
		gc_form_redirect( array( 'gc_err' => 'phone' ) );
	}
	if ( $has_missing && strlen( $missing_reason ) < 10 ) {
		gc_form_redirect( array( 'gc_err' => 'missing_doc_reason' ) );
	}
	$phone_display = trim( $phone_code . ' ' . $phone_local );

	$config_path = dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'app_config.php';
	if ( file_exists( $config_path ) ) { require_once $config_path; }
	$pdo = null;
	if ( defined( 'GC_DB_PUBLIC_USER' ) && defined( 'GC_DB_PUBLIC_PASS' ) ) {
		try {
			$dsn = sprintf( 'mysql:host=%s;port=%d;dbname=%s;charset=%s', GC_DB_HOST, GC_DB_PORT, GC_DB_NAME, GC_DB_CHARSET );
			$pdo = new PDO( $dsn, GC_DB_PUBLIC_USER, GC_DB_PUBLIC_PASS, array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES => false,
			) );
		} catch ( Exception $e ) {
			error_log( 'Public PDO error: ' . $e->getMessage() );
		}
	}

	$dup_redirect = array( 'gc_err' => 'duplicate' );
	$dossier = sprintf( 'GC-%s-%s', current_time( 'Y' ), strtoupper( bin2hex( random_bytes( 3 ) ) ) );

	$id_doc_data  = false;
	$sit_doc_data = false;
	if ( ! empty( $_FILES['gc_id_document']['name'] ) ) {
		$id_doc_data = gc_save_private_upload( $_FILES['gc_id_document'], $dossier, 'id' );
	}
	if ( ! empty( $_FILES['gc_situation_document']['name'] ) ) {
		$sit_doc_data = gc_save_private_upload( $_FILES['gc_situation_document'], $dossier, 'situation' );
	}
	$id_filename   = $id_doc_data ? $id_doc_data['filename'] : null;
	$id_orig_name  = $id_doc_data ? $id_doc_data['original'] : null;
	$sit_filename  = $sit_doc_data ? $sit_doc_data['filename'] : null;
	$sit_orig_name = $sit_doc_data ? $sit_doc_data['original'] : null;

	if ( $pdo ) {
		try {
			$pdo->beginTransaction();
			try {
				$res = $pdo->prepare( 'INSERT INTO gc_phones (phone, candidature_id, created_at) VALUES (?, 0, NOW())' );
				$res->execute( array( $phone_digits ) );
			} catch ( PDOException $ex ) {
				$pdo->rollBack();
				gc_form_redirect( $dup_redirect );
			}
			$chk = $pdo->prepare( 'SELECT id FROM gc_candidatures WHERE email = ? LIMIT 1' );
			$chk->execute( array( $email ) );
			if ( $chk->fetch() ) {
				$pdo->rollBack();
				gc_form_redirect( $dup_redirect );
			}
			$ins = $pdo->prepare( 'INSERT INTO gc_candidatures (dossier_num, firstname, lastname, full_name, email, phone, phone_digits, country, city, situation_pro, foyer, urgency, situation, has_missing_doc, missing_doc_reason, id_document, id_document_original, situation_document, situation_document_original, status, ip_address, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())' );
			$ins->execute( array(
				$dossier, $firstname, $lastname, $full_name, $email, $phone_display, $phone_digits,
				$country, $city, $situation_pro, $foyer, $urgency, $situation,
				$has_missing ? 1 : 0, $missing_reason,
				$id_filename, $id_orig_name, $sit_filename, $sit_orig_name,
				'en_attente', $ip,
			) );
			$cid = (int) $pdo->lastInsertId();
			$upd = $pdo->prepare( 'UPDATE gc_phones SET candidature_id = ? WHERE phone = ?' );
			$upd->execute( array( $cid, $phone_digits ) );
			$pdo->commit();
		} catch ( Exception $e ) {
			if ( $pdo->inTransaction() ) { $pdo->rollBack(); }
			error_log( 'Submit error: ' . $e->getMessage() );
			gc_form_redirect( array( 'gc_err' => 'server' ) );
		}
	} else {
		error_log( 'Submit: no PDO public connection' );
		gc_form_redirect( array( 'gc_err' => 'server' ) );
	}

	$mail_subject = 'ACFE — Accusé de réception de votre dossier ' . $dossier;
	$mail_body = "Bonjour {$firstname},\n\nNous avons bien reçu votre demande.\nNuméro de dossier : {$dossier}\n\nConservez ce numéro pour le suivi.\nAucune somme d'argent ne vous sera jamais demandée.\n\nL'équipe ACFE\n";
	$from_host = wp_parse_url( home_url(), PHP_URL_HOST );
	$headers = array(
		'Content-Type: text/plain; charset=UTF-8',
		'From: ACFE <no-reply@' . ( $from_host ? $from_host : 'acfe.local' ) . '>',
	);
	wp_mail( $email, $mail_subject, $mail_body, $headers );

	gc_form_redirect( array( 'gc_ok' => $dossier ) );
}
