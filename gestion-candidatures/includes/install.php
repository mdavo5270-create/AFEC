<?php
/**
 * Activation : tables, rôles, options par défaut.
 *
 * @package GestionCandidatures
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exécuté à l'activation du plugin.
 */
function gc_activate() {
	gc_create_tables();
	gc_register_roles();

	add_option( 'gc_places', 58 );
	add_option( 'gc_deadline', '' );
	update_option( 'gc_db_version', GC_VERSION );
}

/**
 * Crée les tables personnalisées.
 */
function gc_create_tables() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$prefix  = $wpdb->prefix;

	// Historique des changements de statut. Ne jamais supprimer de lignes.
	dbDelta(
		"CREATE TABLE {$prefix}gc_history (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			candidature_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			old_status varchar(30) NOT NULL DEFAULT '',
			new_status varchar(30) NOT NULL,
			reason text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY candidature_id (candidature_id)
		) $charset;"
	);

	// Évaluations (une par évaluateur et par candidature). Interface à construire (étape 1).
	dbDelta(
		"CREATE TABLE {$prefix}gc_evaluations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			candidature_id bigint(20) unsigned NOT NULL,
			evaluator_id bigint(20) unsigned NOT NULL,
			scores longtext NULL,
			total decimal(6,2) NOT NULL DEFAULT 0,
			comment text NULL,
			conflict tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY candidature_evaluator (candidature_id,evaluator_id)
		) $charset;"
	);

	// Suivi des contacts avec les candidats. Interface à construire (étape 2).
	dbDelta(
		"CREATE TABLE {$prefix}gc_contacts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			candidature_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			channel varchar(20) NOT NULL,
			outcome varchar(30) NOT NULL,
			note text NULL,
			next_followup date NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY candidature_id (candidature_id)
		) $charset;"
	);
}

/**
 * Crée les rôles et donne les droits à l'administrateur.
 *
 * - gc_view   : voir et analyser les dossiers (évaluateur, responsable, admin)
 * - gc_decide : retenir, refuser, payer (responsable, admin)
 * - gc_settings / gc_delete : réglages et suppression (admin)
 */
function gc_register_roles() {
	add_role(
		'gc_evaluateur',
		'Évaluateur de candidatures',
		array(
			'read'    => true,
			'gc_view' => true,
		)
	);

	add_role(
		'gc_responsable',
		'Responsable de la sélection',
		array(
			'read'      => true,
			'gc_view'   => true,
			'gc_decide' => true,
		)
	);

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( array( 'gc_view', 'gc_decide', 'gc_settings', 'gc_delete' ) as $cap ) {
			$admin->add_cap( $cap );
		}
	}
}
