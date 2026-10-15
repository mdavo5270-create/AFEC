<?php
/**
 * Portail solidaire ACFE — Gestion des 7 pages distinctes & formulaire public
 *
 * @package GestionCandidatures
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers sécurité (rate-limit, clip, IP fiable).
 */
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

function gc_rate_limited( $action, $max = 5, $window = HOUR_IN_SECONDS ) {
	$ip  = gc_client_ip();
	$key = 'gc_rl_' . $action . '_' . md5( $ip );
	$hit = (int) get_transient( $key );
	if ( $hit >= $max ) {
		return true;
	}
	set_transient( $key, $hit + 1, $window );
	return false;
}

function gc_clip( $str, $max ) {
	$str = (string) $str;
	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $str, 0, $max );
	}
	return substr( $str, 0, $max );
}

// Charge le reste (pages, suivi, submit)
require_once __DIR__ . '/form-core.php';
