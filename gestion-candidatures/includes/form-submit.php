<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gc_private_storage_dir() {
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
	$target_dir = rtrim( $target_dir, DIRECTORY_SEPARATOR . '/' ) . DIRECTORY_SEPARATOR;
	if ( ! file_exists( $target_dir ) ) {
		wp_mkdir_p( $target_dir );
		@chmod( $target_dir, 0750 );
	}
	return $target_dir;
}

function gc_pdf_looks_safe( $path ) {
	$content = file_get_contents( $path );
	if ( false === $content || false === strpos( substr( $content, 0, 1024 ), '%PDF-' ) ) {
		return false;
	}
	return 1 !== preg_match( '#/(JavaScript|JS|Launch|EmbeddedFile|RichMedia|XFA)\b#', $content );
}

function gc_save_private_upload( array $file, string $dossier, string $doc_type = 'id' ) {
	if ( empty( $file['name'] ) || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return false;
	}
	if ( $file['size'] > 5 * 1024 * 1024 ) {
		return false;
	}
	$finfo = finfo_open( FILEINFO_MIME_TYPE );
	$real_mime = finfo_file( $finfo, $file['tmp_name'] );
	finfo_close( $finfo );
	$allowed_mimes = array(
		'application/pdf' => 'pdf',
		'image/jpeg'      => 'jpg',
		'image/png'       => 'png',
	);
	if ( ! isset( $allowed_mimes[ $real_mime ] ) ) {
		return false;
	}
	if ( 'application/pdf' === $real_mime ) {
		if ( ! gc_pdf_looks_safe( $file['tmp_name'] ) ) {
			return false;
		}
	} else {
		$dimensions = @getimagesize( $file['tmp_name'] );
		if ( false === $dimensions || $dimensions[0] > 12000 || $dimensions[1] > 12000 ) {
			return false;
		}
	}
	$ext = $allowed_mimes[ $real_mime ];
	$target_dir = gc_private_storage_dir();
	$prefix = 'situation' === $doc_type ? 'sit' : 'cni';
	$filename = sprintf( 'doc_%s_%s_%s.%s', $prefix, bin2hex( random_bytes( 16 ) ), time(), $ext );
	$full_path = $target_dir . $filename;
	if ( move_uploaded_file( $file['tmp_name'], $full_path ) ) {
		@chmod( $full_path, 0640 );
		return array( 'filename' => $filename, 'original' => sanitize_file_name( $file['name'] ) );
	}
	return false;
}

function gc_delete_private_upload( $doc ) {
	if ( is_array( $doc ) && ! empty( $doc['filename'] ) ) {
		$path = gc_private_storage_dir() . basename( $doc['filename'] );
		if ( is_file( $path ) ) {
			@unlink( $path );
		}
	}
}

function gc_handle_submit() {
	if ( ! isset( $_POST['gc_submit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gc_submit_nonce'] ) ), 'gc_submit_application' ) ) {
		gc_form_redirect( array( 'gc_err' => 'nonce' ) );
	}
	if ( gc_rate_limited( 'submit', 5, HOUR_IN_SECONDS ) ) {
		gc_form_redirect( array( 'gc_err' => 'rate' ) );
	}

	$firstname = gc_clip( sanitize_text_field( wp_unslash( $_POST['gc_firstname'] ?? '' ) ), 100 );
	$lastname  = gc_clip( sanitize_text_field( wp_unslash( $_POST['gc_lastname'] ?? '' ) ), 100 );
	$email     = gc_clip( sanitize_email( wp_unslash( $_POST['gc_email'] ?? '' ) ), 190 );
	$phone_code = gc_clip( sanitize_text_field( wp_unslash( $_POST['gc_phone_code'] ?? '' ) ), 8 );
	$phone_local = gc_clip( sanitize_text_field( wp_unslash( $_POST['gc_phone_local'] ?? '' ) ), 30 );
	$country = gc_clip( sanitize_text_field( wp_unslash( $_POST['gc_country'] ?? '' ) ), 100 );
	$city = gc_clip( sanitize_text_field( wp_unslash( $_POST['gc_city'] ?? '' ) ), 150 );
	$situation_pro = gc_clip( sanitize_text_field( wp_unslash( $_POST['gc_situation_pro'] ?? '' ) ), 150 );
	$foyer = gc_clip( sanitize_text_field( wp_unslash( $_POST['gc_foyer'] ?? '' ) ), 100 );
	$urgency = sanitize_key( wp_unslash( $_POST['gc_urgency'] ?? 'moderee' ) );
	$situation = gc_clip( sanitize_textarea_field( wp_unslash( $_POST['gc_situation'] ?? '' ) ), 5000 );
	$has_missing = ! empty( $_POST['gc_has_missing_doc'] );
	$missing_reason = gc_clip( sanitize_textarea_field( wp_unslash( $_POST['gc_missing_doc_reason'] ?? '' ) ), 2000 );

	if ( empty( $_POST['gc_consent'] ) ) {
		gc_form_redirect( array( 'gc_err' => 'consent' ) );
	}
	if ( ! is_email( $email ) ) {
		gc_form_redirect( array( 'gc_err' => 'email' ) );
	}
	$phone_digits = preg_replace( '/\D+/', '', $phone_code . $phone_local );
	if ( strlen( $phone_digits ) < 8 ) {
		gc_form_redirect( array( 'gc_err' => 'phone' ) );
	}
	if ( $has_missing && '' === $missing_reason ) {
		gc_form_redirect( array( 'gc_err' => 'missing_doc_reason' ) );
	}
	if ( ! in_array( $urgency, array( 'urgente', 'moderee', 'planifiee' ), true ) ) {
		$urgency = 'moderee';
	}

	$pdo = gc_public_pdo();
	if ( ! $pdo ) {
		gc_form_redirect( array( 'gc_err' => 'server' ) );
	}

	try {
		$stmt_dup = $pdo->prepare( 'SELECT id FROM gc_candidatures WHERE phone_digits = ? OR email = ? LIMIT 1' );
		$stmt_dup->execute( array( $phone_digits, $email ) );
		if ( $stmt_dup->fetch() ) {
			gc_form_redirect( array( 'gc_err' => 'duplicate' ) );
		}
	} catch ( Exception $e ) {
		gc_form_redirect( array( 'gc_err' => 'server' ) );
	}

	$id_doc_data = false;
	$sit_doc_data = false;
	if ( ! empty( $_FILES['gc_id_document']['name'] ) ) {
		$id_doc_data = gc_save_private_upload( $_FILES['gc_id_document'], 'pending', 'id' );
		if ( false === $id_doc_data ) {
			gc_form_redirect( array( 'gc_err' => 'file_type' ) );
		}
	}
	if ( ! empty( $_FILES['gc_situation_document']['name'] ) ) {
		$sit_doc_data = gc_save_private_upload( $_FILES['gc_situation_document'], 'pending', 'situation' );
		if ( false === $sit_doc_data ) {
			gc_delete_private_upload( $id_doc_data );
			gc_form_redirect( array( 'gc_err' => 'file_type' ) );
		}
	}

	$phone_display = trim( $phone_code . ' ' . $phone_local );
	$ip = gc_client_ip();
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
	$dossier = '';
	$inserted = false;
	for ( $attempt = 0; $attempt < 5 && ! $inserted; $attempt++ ) {
		$dossier = gc_generate_dossier_number();
		try {
			$pdo->prepare( $ins_sql )->execute( array(
				$dossier, $firstname, $lastname, $email, gc_clip( $phone_display, 40 ), $phone_digits,
				$country, $city, $situation_pro, $foyer, $urgency, $situation,
				$id_doc_data ? $id_doc_data['filename'] : null, $id_doc_data ? $id_doc_data['original'] : null,
				$sit_doc_data ? $sit_doc_data['filename'] : null, $sit_doc_data ? $sit_doc_data['original'] : null,
				$has_missing ? 1 : 0, $missing_reason, 'recue', gc_clip( $ip, 45 ),
			) );
			$inserted = true;
		} catch ( PDOException $e ) {
			$is_dup = ( '23000' === (string) $e->getCode() ) || ( isset( $e->errorInfo[1] ) && 1062 === (int) $e->errorInfo[1] );
			if ( $is_dup && false === stripos( (string) $e->getMessage(), 'dossier_num' ) ) {
				gc_delete_private_upload( $id_doc_data );
				gc_delete_private_upload( $sit_doc_data );
				gc_form_redirect( array( 'gc_err' => 'duplicate' ) );
			}
			if ( ! $is_dup ) {
				break;
			}
		}
	}
	if ( ! $inserted ) {
		gc_delete_private_upload( $id_doc_data );
		gc_delete_private_upload( $sit_doc_data );
		gc_form_redirect( array( 'gc_err' => 'server' ) );
	}

	gc_form_redirect( array( 'gc_ok' => $dossier ) );
}
