<?php
/**
 * Statuts d'une candidature, règles de passage et historique.
 *
 * @package GestionCandidatures
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Liste des statuts : slug => libellé.
 *
 * @return array<string,string>
 */
function gc_statuses() {
	return array(
		'recue'           => 'Reçue',
		'en_verification' => 'En vérification',
		'en_analyse'      => 'En analyse',
		'liste_courte'    => 'Liste courte',
		'retenue'         => 'Retenue',
		'contactee'       => 'Contactée',
		'injoignable'     => 'Injoignable',
		'confirmee'       => 'Confirmée',
		'payee'           => 'Payée',
		'refusee'         => 'Refusée',
	);
}

/**
 * Couleurs des pastilles (fond, texte).
 *
 * @return array<string,array{0:string,1:string}>
 */
function gc_status_colors() {
	return array(
		'recue'           => array( '#e5e7eb', '#374151' ),
		'en_verification' => array( '#dbeafe', '#1e40af' ),
		'en_analyse'      => array( '#e0e7ff', '#3730a3' ),
		'liste_courte'    => array( '#fef3c7', '#92400e' ),
		'retenue'         => array( '#dcfce7', '#166534' ),
		'contactee'       => array( '#ccfbf1', '#115e59' ),
		'injoignable'     => array( '#ffedd5', '#9a3412' ),
		'confirmee'       => array( '#bbf7d0', '#14532d' ),
		'payee'           => array( '#166534', '#ffffff' ),
		'refusee'         => array( '#fee2e2', '#991b1b' ),
	);
}

/**
 * Statuts qui occupent l'une des places (ex. 58).
 *
 * @return string[]
 */
function gc_slot_statuses() {
	return array( 'retenue', 'contactee', 'injoignable', 'confirmee', 'payee' );
}

/**
 * Statuts que peut manipuler un évaluateur (sans droit de décision).
 *
 * @return string[]
 */
function gc_reviewer_statuses() {
	return array( 'recue', 'en_verification', 'en_analyse', 'liste_courte' );
}

/**
 * Nombre total de places à attribuer.
 *
 * @return int
 */
function gc_places() {
	return max( 1, (int) get_option( 'gc_places', 58 ) );
}

/**
 * Libellé d'un statut.
 *
 * @param string $slug Slug du statut.
 * @return string
 */
function gc_status_label( $slug ) {
	$all = gc_statuses();
	return isset( $all[ $slug ] ) ? $all[ $slug ] : $slug;
}

/**
 * Statut courant d'une candidature.
 *
 * @param int $post_id ID de la candidature.
 * @return string
 */
function gc_get_status( $post_id ) {
	$status = get_post_meta( $post_id, 'gc_status', true );
	$all    = gc_statuses();
	return isset( $all[ $status ] ) ? $status : 'recue';
}

/**
 * Pastille HTML d'un statut.
 *
 * @param string $slug Slug du statut.
 * @return string HTML déjà échappé.
 */
function gc_status_badge( $slug ) {
	$colors = gc_status_colors();
	$c      = isset( $colors[ $slug ] ) ? $colors[ $slug ] : array( '#e5e7eb', '#374151' );
	return sprintf(
		'<span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;background:%s;color:%s;">%s</span>',
		esc_attr( $c[0] ),
		esc_attr( $c[1] ),
		esc_html( gc_status_label( $slug ) )
	);
}

/**
 * Compte les candidatures dont le statut est dans la liste donnée.
 *
 * @param string[] $statuses Slugs de statuts.
 * @return int
 */
function gc_count_by_status( array $statuses ) {
	global $wpdb;

	if ( empty( $statuses ) ) {
		return 0;
	}

	$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
	$sql          = "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
		INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		WHERE pm.meta_key = 'gc_status'
		AND p.post_type = 'gc_candidature'
		AND p.post_status NOT IN ('trash','auto-draft')
		AND pm.meta_value IN ($placeholders)"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return (int) $wpdb->get_var( $wpdb->prepare( $sql, $statuses ) );
}

/**
 * Écrit une ligne dans l'historique (jamais supprimée).
 *
 * @param int    $post_id ID de la candidature.
 * @param string $old     Ancien statut.
 * @param string $new     Nouveau statut.
 * @param string $reason  Motif.
 * @param int    $user_id Auteur du changement (0 = candidat).
 */
function gc_log_status( $post_id, $old, $new, $reason, $user_id ) {
	global $wpdb;

	$wpdb->insert(
		$wpdb->prefix . 'gc_history',
		array(
			'candidature_id' => (int) $post_id,
			'user_id'        => (int) $user_id,
			'old_status'     => (string) $old,
			'new_status'     => (string) $new,
			'reason'         => (string) $reason,
			'created_at'     => current_time( 'mysql' ),
		),
		array( '%d', '%d', '%s', '%s', '%s', '%s' )
	);
}

/**
 * Change le statut d'une candidature en appliquant toutes les règles.
 *
 * @param int    $post_id ID de la candidature.
 * @param string $new     Nouveau statut.
 * @param string $reason  Motif (obligatoire pour un refus).
 * @return true|WP_Error
 */
function gc_change_status( $post_id, $new, $reason = '' ) {
	$all = gc_statuses();

	if ( ! isset( $all[ $new ] ) ) {
		return new WP_Error( 'gc_invalid', 'Statut inconnu.' );
	}

	if ( ! current_user_can( 'gc_view' ) ) {
		return new WP_Error( 'gc_forbidden', 'Accès refusé.' );
	}

	$old = gc_get_status( $post_id );
	if ( $old === $new ) {
		return true;
	}

	if ( ! current_user_can( 'gc_decide' ) ) {
		$allowed = gc_reviewer_statuses();
		if ( ! in_array( $old, $allowed, true ) || ! in_array( $new, $allowed, true ) ) {
			return new WP_Error( 'gc_forbidden', 'Vous n’avez pas le droit d’appliquer ce statut.' );
		}
	}

	$reason = sanitize_textarea_field( $reason );
	if ( 'refusee' === $new && '' === trim( $reason ) ) {
		return new WP_Error( 'gc_reason', 'Un motif est obligatoire pour un refus.' );
	}

	$slots = gc_slot_statuses();
	if ( in_array( $new, $slots, true ) && ! in_array( $old, $slots, true ) ) {
		$places = gc_places();
		if ( gc_count_by_status( $slots ) >= $places ) {
			return new WP_Error( 'gc_full', sprintf( 'Les %d places sont déjà attribuées.', $places ) );
		}
	}

	update_post_meta( $post_id, 'gc_status', $new );
	gc_log_status( $post_id, $old, $new, $reason, get_current_user_id() );

	do_action( 'gc_status_changed', $post_id, $old, $new, $reason );

	return true;
}
