<?php
/**
 * Portail solidaire ACFE — pages publiques + formulaire (contenu restauré + sécurité)
 * @package GestionCandidatures
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gc_normalize_string( $str ) {
	$str = trim( (string) $str );
	if ( '' === $str ) {
		return '';
	}
	$from = array( 'à','á','â','ã','ä','å','ā','ă','ą',
				   'è','é','ê','ë','ē','ĕ','ě',
				   'ì','í','î','ï','ī',
				   'ò','ó','ô','õ','ö','ø','ō',
				   'ù','ú','û','ü','ū','ů',
				   'ç','ć','č',
				   'ñ','ń',
				   'ý','ÿ',
				   'À','Á','Â','Ã','Ä','Å',
				   'È','É','Ê','Ë',
				   'Ì','Í','Î','Ï',
				   'Ò','Ó','Ô','Õ','Ö',
				   'Ù','Ú','Û','Ü',
				   'Ç','Ñ','Ý' );
	$to = array( 'a','a','a','a','a','a','a','a','a',
				 'e','e','e','e','e','e','e',
				 'i','i','i','i','i',
				 'o','o','o','o','o','o','o',
				 'u','u','u','u','u','u',
				 'c','c','c',
				 'n','n',
				 'y','y',
				 'a','a','a','a','a','a',
				 'e','e','e','e',
				 'i','i','i','i',
				 'o','o','o','o','o',
				 'u','u','u','u',
				 'c','n','y' );
	$str = str_replace( $from, $to, $str );
	$str = strtolower( $str );
	$str = preg_replace( '/\s+/', ' ', $str );
	return $str;
}

function gc_rate_limited( $bucket, $max, $window ) {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key = 'gc_rl_' . md5( $bucket . '|' . $ip );
	$hit = (int) get_transient( $key );
	if ( $hit >= $max ) {
		return true;
	}
	set_transient( $key, $hit + 1, $window );
	return false;
}

function gc_clip( $value, $max ) {
	$value = (string) $value;
	return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
}

function gc_client_ip() {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	$trusted = defined( 'GC_TRUSTED_PROXIES' ) && is_array( GC_TRUSTED_PROXIES ) ? GC_TRUSTED_PROXIES : array();
	if ( empty( $trusted ) || ! in_array( $remote, $trusted, true ) ) {
		return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';
	}
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$parts = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) );
		foreach ( $parts as $c ) {
			if ( filter_var( $c, FILTER_VALIDATE_IP ) && ! in_array( $c, $trusted, true ) ) {
				return $c;
			}
		}
	}
	return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';
}

function gc_public_pdo() {
	static $pdo = false;
	if ( false !== $pdo ) {
		return $pdo;
	}
	$pdo = null;
	$config_path = dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'app_config.php';
	if ( file_exists( $config_path ) ) {
		require_once $config_path;
	}
	if ( defined( 'GC_DB_PUBLIC_USER' ) && defined( 'GC_DB_PUBLIC_PASS' ) ) {
		try {
			$dsn = sprintf( 'mysql:host=%s;port=%d;dbname=%s;charset=%s', GC_DB_HOST, GC_DB_PORT, GC_DB_NAME, GC_DB_CHARSET );
			$options = array(
				PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES   => false,
			);
			if ( defined( 'GC_DB_SSL_CA' ) && '' !== GC_DB_SSL_CA ) {
				$options[ PDO::MYSQL_ATTR_SSL_CA ] = GC_DB_SSL_CA;
			}
			$pdo = new PDO( $dsn, GC_DB_PUBLIC_USER, GC_DB_PUBLIC_PASS, $options );
		} catch ( Exception $e ) {
			error_log( 'Public PDO error: ' . $e->getMessage() );
			$pdo = null;
		}
	}
	return $pdo;
}

function gc_generate_dossier_number() {
	$alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
	$code     = '';
	for ( $i = 0; $i < 10; $i++ ) {
		$code .= $alphabet[ random_int( 0, 31 ) ];
	}
	return sprintf( 'GC-%s-%s', current_time( 'Y' ), $code );
}

function gc_form_redirect( $args ) {
	$url = wp_get_referer();
	if ( ! $url ) {
		$url = home_url( '/' );
	}
	wp_safe_redirect( add_query_arg( $args, $url ) );
	exit;
}

function gc_is_closed() {
	return (bool) get_option( 'gc_form_closed', false );
}

require_once __DIR__ . '/form-core.php';
require_once __DIR__ . '/form-pages2.php';
require_once __DIR__ . '/form-pages2b.php';
require_once __DIR__ . '/form-pages3.php';
require_once __DIR__ . '/form-handlers.php';
require_once __DIR__ . '/form-submit.php';
