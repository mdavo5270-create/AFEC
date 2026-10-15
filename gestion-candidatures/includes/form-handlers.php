<?php
/** Handlers suivi / upload / submit — sécurité renforcée */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gc_render_suivi_box() {
	$result = '';
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['gc_suivi_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gc_suivi_nonce'] ) ), 'gc_suivi' ) ) {
			$result = '<div class="gc-suivi-result error"><p>Session expirée. Veuillez réessayer.</p></div>';
		} elseif ( gc_rate_limited( 'suivi', 8, HOUR_IN_SECONDS ) ) {
			$result = '<div class="gc-suivi-result error"><p>Trop de tentatives. Réessayez dans une heure.</p></div>';
		} else {
			$raw_dossier = sanitize_text_field( wp_unslash( $_POST['gc_search_dossier'] ?? '' ) );
			$raw_phone   = sanitize_text_field( wp_unslash( $_POST['gc_search_phone'] ?? '' ) );
			$dossier_query = strtoupper( preg_replace( '/[^A-Za-z0-9\-]/', '', $raw_dossier ) );
			$phone_digits  = preg_replace( '/\D+/', '', $raw_phone );
			$fail_msg = '<div class="gc-suivi-result error"><p>Aucun dossier ne correspond à ces informations. Vérifiez le numéro et le téléphone saisis.</p></div>';
			if ( strlen( $dossier_query ) < 6 || strlen( $phone_digits ) < 8 ) {
				$result = $fail_msg;
			} else {
				$config_path = dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'app_config.php';
				if ( file_exists( $config_path ) ) { require_once $config_path; }
				$pdo = null;
				if ( defined( 'GC_DB_PUBLIC_USER' ) && defined( 'GC_DB_PUBLIC_PASS' ) ) {
					try {
						$dsn = sprintf( 'mysql:host=%s;port=%d;dbname=%s;charset=%s', GC_DB_HOST, GC_DB_PORT, GC_DB_NAME, GC_DB_CHARSET );
						$pdo = new PDO( $dsn, GC_DB_PUBLIC_USER, GC_DB_PUBLIC_PASS, array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false ) );
					} catch ( Exception $e ) { error_log( 'Tracking PDO error: ' . $e->getMessage() ); }
				}
				$row = null;
				if ( $pdo ) {
					$stmt = $pdo->prepare( 'SELECT dossier_num, status FROM gc_candidatures WHERE UPPER(dossier_num) = ? AND phone_digits = ? LIMIT 1' );
					$stmt->execute( array( $dossier_query, $phone_digits ) );
					$row = $stmt->fetch();
				}
				if ( ! $row ) {
					$result = $fail_msg;
				} else {
					$labels = array(
						'en_attente' => 'Dossier reçu — en attente de première lecture', 'recue' => 'Dossier reçu — en attente de première lecture',
						'en_verification' => 'Pièces justificatives en cours de vérification', 'en_analyse' => 'Dossier en cours d\'étude par l\'équipe',
						'liste_courte' => 'Dossier retenu pour l\'étape suivante', 'retenue' => 'Dossier sélectionné — notre équipe vous contactera',
						'contactee' => 'Contact en cours par notre équipe officielle', 'injoignable' => 'Nous n\'avons pas pu vous joindre — nous réessayerons',
						'confirmee' => 'Dossier confirmé', 'payee' => 'Aide versée', 'refusee' => 'Résultat disponible — nous vous contacterons si nécessaire',
					);
					$status_label = isset( $labels[ $row['status'] ] ) ? $labels[ $row['status'] ] : 'Dossier en cours de traitement';
					$result  = '<div class="gc-suivi-result success">';
					$result .= '<div class="gc-suivi-dossier-tag">' . esc_html( $row['dossier_num'] ) . '</div>';
					$result .= '<p><strong>Statut :</strong> ' . esc_html( $status_label ) . '</p>';
					$result .= '<p class="gc-field-hint">Aucune somme d\'argent ne vous sera jamais demandée.</p></div>';
				}
			}
		}
	}
	$html  = '<div class="gc-suivi-box"><form method="post" class="gc-suivi-form" autocomplete="off">';
	$html .= wp_nonce_field( 'gc_suivi', 'gc_suivi_nonce', true, false );
	$html .= '<div class="gc-suivi-inputs">';
	$html .= '<div class="gc-form-group"><label for="gc_search_dossier">Numéro de dossier</label>';
	$html .= '<input type="text" name="gc_search_dossier" id="gc_search_dossier" class="gc-form-control" required maxlength="20" placeholder="GC-2026-XXXX" value=""></div>';
	$html .= '<div class="gc-form-group"><label for="gc_search_phone">Téléphone utilisé lors de la candidature</label>';
	$html .= '<input type="tel" name="gc_search_phone" id="gc_search_phone" class="gc-form-control" required maxlength="30" placeholder="+33 6 12 34 56 78" value=""></div></div>';
	$html .= '<button type="submit" class="gc-submit-btn-orange">Consulter mon dossier</button></form>' . $result . '</div>';
	return $html;
}

function gc_save_private_upload( array $file, string $dossier, string $doc_type = 'id' ) {
	if ( empty( $file['name'] ) || UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) { return false; }
	if ( $file['size'] > 5 * 1024 * 1024 ) { return false; }
	$finfo = finfo_open( FILEINFO_MIME_TYPE );
	$real_mime = finfo_file( $finfo, $file['tmp_name'] );
	finfo_close( $finfo );
	$allowed_mimes = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf' );
	if ( ! isset( $allowed_mimes[ $real_mime ] ) ) { return false; }
	$ext = $allowed_mimes[ $real_mime ];
	$config_path = dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'app_config.php';
	$target_dir  = '';
	if ( file_exists( $config_path ) ) { require_once $config_path; if ( defined( 'GC_SECURE_STORAGE_PATH' ) ) { $target_dir = GC_SECURE_STORAGE_PATH; } }
	if ( empty( $target_dir ) ) { $target_dir = dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'secure_storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR; }
	if ( ! file_exists( $target_dir ) ) { wp_mkdir_p( $target_dir ); @file_put_contents( $target_dir . '.htaccess', "Order deny,allow\nDeny from all\n" ); }
	$prefix    = 'situation' === $doc_type ? 'sit' : 'cni';
	$filename  = sprintf( 'doc_%s_%s_%s.%s', $prefix, bin2hex( random_bytes( 8 ) ), time(), $ext );
	$full_path = rtrim( $target_dir, DIRECTORY_SEPARATOR . '/' ) . DIRECTORY_SEPARATOR . $filename;
	if ( move_uploaded_file( $file['tmp_name'], $full_path ) ) {
		@chmod( $full_path, 0640 );
		return array( 'filename' => $filename, 'original' => sanitize_file_name( $file['name'] ) );
	}
	return false;
}

add_action( 'admin_post_gc_download_doc', 'gc_serve_private_download' );
function gc_serve_private_download() {
	if ( ! current_user_can( 'gc_view' ) ) { wp_die( 'Access denied.', 'Security', array( 'response' => 403 ) ); }
	$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
	$type    = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'id';
	$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'gc_download_' . $post_id ) ) { wp_die( 'Link expired or invalid.', 'Security', array( 'response' => 403 ) ); }
	$meta_key  = 'situation' === $type ? 'gc_situation_document' : 'gc_id_document';
	$file_name = (string) get_post_meta( $post_id, $meta_key, true );
	if ( empty( $file_name ) ) { wp_die( 'Document not found.', 'Error', array( 'response' => 404 ) ); }
	$config_path = dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'app_config.php';
	$storage_dir = dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'secure_storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
	if ( file_exists( $config_path ) ) { require_once $config_path; if ( defined( 'GC_SECURE_STORAGE_PATH' ) ) { $storage_dir = GC_SECURE_STORAGE_PATH; } }
	$file_path = realpath( $storage_dir . basename( $file_name ) );
	if ( ! $file_path || ! file_exists( $file_path ) ) { wp_die( 'File missing from the secure server.', 'Error', array( 'response' => 404 ) ); }
	$finfo = finfo_open( FILEINFO_MIME_TYPE );
	$type_mime = finfo_file( $finfo, $file_path );
	finfo_close( $finfo );
	header( 'Content-Type: ' . $type_mime );
	header( 'Content-Length: ' . filesize( $file_path ) );
	header( 'Content-Disposition: attachment; filename="' . basename( $file_name ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $file_path );
	exit;
}

function gc_form_redirect( array $args ) {
	$back = wp_get_referer();
	if ( ! $back ) { $back = home_url( '/soumettre-une-demande/' ); }
	$back = remove_query_arg( array( 'gc_ok', 'gc_err' ), $back );
	wp_safe_redirect( add_query_arg( $args, $back ) );
	exit;
}

function gc_handle_submit() {
	if ( ! isset( $_POST['gc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gc_nonce'] ) ), 'gc_submit' ) ) {
		gc_form_redirect( array( 'gc_err' => 'nonce' ) );
	}
	if ( ! empty( $_POST['gc_website'] ) ) { gc_form_redirect( array( 'gc_ok' => 'GC-0000-0000' ) ); }
	if ( gc_is_closed() ) { gc_form_redirect( array( 'gc_err' => 'closed' ) ); }
	$ip = gc_client_ip();
	if ( gc_rate_limited( 'submit', 5, HOUR_IN_SECONDS ) ) { gc_form_redirect( array( 'gc_err' => 'rate' ) ); }
	// Note: le reste du traitement (champs, clip, anti-doublons atomiques, insert) reste dans le déploiement local complet.
	// Voir AFEC_push/gestion-candidatures/includes/form-handlers.php pour la version complète.
	error_log( 'gc_handle_submit: partial deploy - use full form-handlers from local AFEC_push' );
	gc_form_redirect( array( 'gc_err' => 'server' ) );
}
