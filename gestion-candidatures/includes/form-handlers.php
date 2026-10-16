<?php
/**
 * Handlers téléchargement documents privés (admin).
 * Les fonctions suivi / upload / submit sont dans form-pages2b.php et form-submit.php.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sert un document privé après vérification admin + token signé.
 */
function gc_serve_private_download() {
	if ( ! is_user_logged_in() && empty( $_SESSION['admin_user_id'] ) ) {
		// Autoriser uniquement via le flux admin (session admin ou capa WP).
		if ( ! current_user_can( 'manage_options' ) ) {
			status_header( 403 );
			exit( 'Forbidden' );
		}
	}

	$id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
	token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

	if ( $id <= 0 || ! in_array( $type, array( 'id', 'situation' ), true ) ) {
		status_header( 400 );
		exit( 'Bad request' );
	}

	// Token HMAC court (compat admin download.php / candidature.php).
	if ( defined( 'GC_CSRF_SECRET' ) && GC_CSRF_SECRET ) {
		$expected = hash_hmac( 'sha256', 'dl_' . $id . '_' . $type, GC_CSRF_SECRET );
		if ( ! hash_equals( $expected, $token ) ) {
			// Accepter aussi le format généré par gc_download_url si présent.
			if ( ! function_exists( 'gc_download_url' ) || ! hash_equals( hash_hmac( 'sha256', $id . '|' . $type, GC_CSRF_SECRET ), $token ) ) {
				status_header( 403 );
				exit( 'Invalid token' );
			}
		}
	}

	$pdo = function_exists( 'gc_public_pdo' ) ? gc_public_pdo() : null;
	if ( ! $pdo && function_exists( 'get_admin_pdo' ) ) {
		$pdo = get_admin_pdo();
	}
	if ( ! $pdo ) {
		status_header( 500 );
		exit( 'DB error' );
	}

	$col = ( 'situation' === $type ) ? 'situation_document' : 'id_document';
	$stmt = $pdo->prepare( "SELECT {$col} AS filename FROM gc_candidatures WHERE id = ? LIMIT 1" );
	$stmt->execute( array( $id ) );
	$row = $stmt->fetch();
	if ( ! $row || empty( $row['filename'] ) ) {
		status_header( 404 );
		exit( 'Not found' );
	}

	$dir  = function_exists( 'gc_private_storage_dir' ) ? gc_private_storage_dir() : ( dirname( ABSPATH ) . '/secure_storage/uploads/' );
	$path = rtrim( $dir, '/\\' ) . DIRECTORY_SEPARATOR . basename( $row['filename'] );
	if ( ! is_file( $path ) ) {
		status_header( 404 );
		exit( 'File missing' );
	}

	$mime = 'application/octet-stream';
	$finfo = finfo_open( FILEINFO_MIME_TYPE );
	if ( $finfo ) {
		$detected = finfo_file( $finfo, $path );
		finfo_close( $finfo );
		if ( is_string( $detected ) && $detected ) {
			$mime = $detected;
		}
	}

	header( 'Content-Type: ' . $mime );
	header( 'Content-Length: ' . (string) filesize( $path ) );
	header( 'Content-Disposition: inline; filename="' . basename( $path ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Cache-Control: private, no-store' );
	readfile( $path );
	exit;
}
