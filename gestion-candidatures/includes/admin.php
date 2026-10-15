<?php
/**
 * Écrans d'administration : liste, fiche candidat, statut, historique.
 *
 * @package GestionCandidatures
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes', 'gc_add_meta_boxes' );
add_action( 'save_post_gc_candidature', 'gc_save_status_box', 10, 1 );
add_action( 'admin_notices', 'gc_admin_notices' );
add_filter( 'manage_gc_candidature_posts_columns', 'gc_columns' );
add_action( 'manage_gc_candidature_posts_custom_column', 'gc_column_content', 10, 2 );
add_filter( 'views_edit-gc_candidature', 'gc_status_views' );
add_action( 'pre_get_posts', 'gc_filter_list_by_status' );

/**
 * Déclare les blocs de la fiche candidat.
 */
function gc_add_meta_boxes() {
	add_meta_box( 'gc_info', 'Informations du candidat', 'gc_box_info', 'gc_candidature', 'normal', 'high' );
	add_meta_box( 'gc_status_box', 'Statut et décision', 'gc_box_status', 'gc_candidature', 'side', 'high' );
	add_meta_box( 'gc_history_box', 'Historique des décisions', 'gc_box_history', 'gc_candidature', 'normal', 'default' );
}

/**
 * Bloc : informations du candidat (lecture seule enrichie).
 *
 * @param WP_Post $post Candidature.
 */
function gc_box_info( $post ) {
	$dossier        = get_post_meta( $post->ID, 'gc_dossier', true );
	$firstname      = get_post_meta( $post->ID, 'gc_firstname', true );
	$lastname       = get_post_meta( $post->ID, 'gc_lastname', true );
	$phone          = get_post_meta( $post->ID, 'gc_phone', true );
	$email          = get_post_meta( $post->ID, 'gc_email', true );
	$country        = get_post_meta( $post->ID, 'gc_country', true );
	$city           = get_post_meta( $post->ID, 'gc_city', true );
	$situation_pro  = get_post_meta( $post->ID, 'gc_situation_pro', true );
	$foyer          = get_post_meta( $post->ID, 'gc_foyer', true );
	$urgency        = get_post_meta( $post->ID, 'gc_urgency', true );
	$situation      = get_post_meta( $post->ID, 'gc_situation', true );
	$id_doc         = get_post_meta( $post->ID, 'gc_id_document', true );
	$id_orig        = get_post_meta( $post->ID, 'gc_id_document_original', true );
	$sit_doc        = get_post_meta( $post->ID, 'gc_situation_document', true );
	$sit_orig       = get_post_meta( $post->ID, 'gc_situation_document_original', true );
	$has_missing    = get_post_meta( $post->ID, 'gc_has_missing_doc', true );
	$missing_reason = get_post_meta( $post->ID, 'gc_missing_doc_reason', true );

	// Libellés clairs
	$pro_labels = array(
		'salarie'          => 'Salarié(e) / Employé(e)',
		'demandeur_emploi' => 'Sans emploi / Demandeur d’emploi',
		'independant'      => 'Indépendant(e) / Artisan / Auto-entrepreneur',
		'retraite'         => 'Retraité(e)',
		'etudiant'         => 'Étudiant(e)',
		'invalidite'       => 'Incapacité de travail / Invalidité',
		'autre'            => 'Autre situation',
	);
	$foyer_labels = array(
		'seul'               => 'Personne seule',
		'monoparental'       => 'Famille monoparentale (avec enfant(s))',
		'couple_sans_enfant' => 'Couple sans enfant',
		'couple_avec_enfant' => 'Couple avec enfant(s)',
		'foyer_autre'        => 'Foyer multigénérationnel / Autre',
	);

	// Badge d'urgence
	$urgency_badge = '';
	if ( 'urgente' === $urgency ) {
		$urgency_badge = '<span style="display:inline-block;padding:5px 12px;background:#fee2e2;color:#991b1b;border:1px solid #f87171;border-radius:4px;font-weight:700;font-size:13px;">URGENCE CRITIQUE / IMMÉDIATE (&lt; 7 jours)</span>';
	} elseif ( 'moderee' === $urgency ) {
		$urgency_badge = '<span style="display:inline-block;padding:5px 12px;background:#ffedd5;color:#9a3412;border:1px solid #fb923c;border-radius:4px;font-weight:700;font-size:13px;">Besoin important à court terme</span>';
	} else {
		$urgency_badge = '<span style="display:inline-block;padding:5px 12px;background:#e0f2fe;color:#075985;border:1px solid #7dd3fc;border-radius:4px;font-weight:600;font-size:13px;">Soutien planifié / consolidation</span>';
	}

	echo '<table class="form-table" role="presentation"><tbody>';

	// 1. Dossier & Urgence
	printf( '<tr><th scope="row">Numéro de dossier</th><td><strong style="font-size:16px;color:#0b1a2d;letter-spacing:1px;">%s</strong></td></tr>', esc_html( (string) $dossier ) );
	printf( '<tr><th scope="row">Degré d’urgence</th><td>%s</td></tr>', $urgency_badge ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	// 2. Identité & Contact
	$full_name = trim( $firstname . ' ' . $lastname );
	if ( empty( $full_name ) ) {
		$full_name = $post->post_title;
	}
	printf( '<tr><th scope="row">Identité du candidat</th><td><strong>%s</strong></td></tr>', esc_html( $full_name ) );
	printf(
		'<tr><th scope="row">Coordonnées de contact</th><td>Téléphone : <a href="tel:%s"><strong>%s</strong></a> &bull; E-mail (vérifié) : <a href="mailto:%s"><strong>%s</strong></a></td></tr>',
		esc_attr( (string) $phone ),
		esc_html( (string) $phone ),
		esc_attr( (string) $email ),
		esc_html( (string) $email )
	);
	printf(
		'<tr><th scope="row">Résidence</th><td>%s, %s</td></tr>',
		esc_html( (string) $city ),
		esc_html( (string) $country ? $country : 'Non précisé' )
	);

	// 3. Situation & Foyer
	$pro_text   = isset( $pro_labels[ $situation_pro ] ) ? $pro_labels[ $situation_pro ] : ( $situation_pro ? $situation_pro : 'Non précisée' );
	$foyer_text = isset( $foyer_labels[ $foyer ] ) ? $foyer_labels[ $foyer ] : ( $foyer ? $foyer : 'Non précisée' );
	printf( '<tr><th scope="row">Situation professionnelle</th><td>%s</td></tr>', esc_html( $pro_text ) );
	printf( '<tr><th scope="row">Composition du foyer</th><td>%s</td></tr>', esc_html( $foyer_text ) );
	printf( '<tr><th scope="row">Dossier reçu le</th><td>%s</td></tr>', esc_html( get_the_date( 'd/m/Y à H:i', $post ) ) );

	// 4. Documents justificatifs & téléchargement sécurisé
	$nonce_dl = wp_create_nonce( 'gc_download_' . $post->ID );

	$id_doc_html = '<span style="color:#64748b;">Aucune pièce d’identité jointe</span>';
	if ( ! empty( $id_doc ) ) {
		$dl_url_id   = admin_url( 'admin-post.php?action=gc_download_doc&post_id=' . $post->ID . '&type=id&_wpnonce=' . $nonce_dl );
		$id_doc_html = sprintf(
			'<a href="%s" target="_blank" class="button button-primary" style="margin-right:8px;">Ouvrir la pièce d’identité</a> <small style="color:#64748b;">(%s)</small>',
			esc_url( $dl_url_id ),
			esc_html( $id_orig ? $id_orig : $id_doc )
		);
	}

	$sit_doc_html = '<span style="color:#64748b;">Aucun justificatif de situation joint</span>';
	if ( ! empty( $sit_doc ) ) {
		$dl_url_sit   = admin_url( 'admin-post.php?action=gc_download_doc&post_id=' . $post->ID . '&type=situation&_wpnonce=' . $nonce_dl );
		$sit_doc_html = sprintf(
			'<a href="%s" target="_blank" class="button button-secondary" style="margin-right:8px;">Ouvrir le justificatif de situation</a> <small style="color:#64748b;">(%s)</small>',
			esc_url( $dl_url_sit ),
			esc_html( $sit_orig ? $sit_orig : $sit_doc )
		);
	}

	printf( '<tr><th scope="row">Pièce d’identité officielle</th><td>%s</td></tr>', $id_doc_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	printf( '<tr><th scope="row">Justificatif de situation</th><td>%s</td></tr>', $sit_doc_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	// Justification du document manquant si applicable
	if ( '1' === $has_missing || ! empty( $missing_reason ) ) {
		printf(
			'<tr><th scope="row" style="color:#b45309;">Justificatif manquant déclaré</th><td><div style="background:#fffbeb;border:1px solid #fde68a;padding:12px;border-radius:6px;color:#92400e;line-height:1.5;"><strong>Motif déclaré par le candidat :</strong><br>%s</div></td></tr>',
			nl2br( esc_html( (string) $missing_reason ) )
		);
	}

	// 5. Récit de la situation
	printf(
		'<tr><th scope="row">Récit et besoin détaillé</th><td><div style="background:#f8fafc;border:1px solid #cbd5e1;padding:16px;border-radius:8px;font-size:14px;line-height:1.7;color:#1e293b;max-height:300px;overflow-y:auto;">%s</div></td></tr>',
		wp_kses_post( nl2br( esc_html( (string) $situation ) ) )
	);

	echo '</tbody></table>';
}

/**
 * Statuts proposés à l'utilisateur courant.
 *
 * @param string $current Statut actuel.
 * @return array<string,string>
 */
function gc_selectable_statuses( $current ) {
	$all = gc_statuses();

	if ( current_user_can( 'gc_decide' ) ) {
		return $all;
	}

	// Évaluateur : seulement les statuts d'analyse, et seulement si le dossier y est encore.
	if ( ! in_array( $current, gc_reviewer_statuses(), true ) ) {
		return array( $current => $all[ $current ] );
	}
	return array_intersect_key( $all, array_flip( gc_reviewer_statuses() ) );
}

/**
 * Bloc : changement de statut.
 *
 * @param WP_Post $post Candidature.
 */
function gc_box_status( $post ) {
	$current = gc_get_status( $post->ID );
	$choices = gc_selectable_statuses( $current );
	$taken   = gc_count_by_status( gc_slot_statuses() );

	wp_nonce_field( 'gc_status_save', 'gc_status_nonce' );

	echo '<p>Statut actuel : ' . gc_status_badge( $current ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	printf( '<p><strong>Places :</strong> %d / %d</p>', (int) $taken, (int) gc_places() );

	echo '<p><label for="gc_new_status">Nouveau statut</label><br><select name="gc_new_status" id="gc_new_status" style="width:100%">';
	foreach ( $choices as $slug => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $slug, $current, false ), esc_html( $label ) );
	}
	echo '</select></p>';
	echo '<p><label for="gc_reason">Motif ou remarque (obligatoire pour un refus)</label><br><textarea name="gc_reason" id="gc_reason" rows="3" style="width:100%"></textarea></p>';
	echo '<p class="description">Cliquez sur « Mettre à jour » pour enregistrer.</p>';
}

/**
 * Bloc : historique des changements de statut.
 *
 * @param WP_Post $post Candidature.
 */
function gc_box_history( $post ) {
	global $wpdb;

	$table = $wpdb->prefix . 'gc_history';
	$rows  = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE candidature_id = %d ORDER BY id DESC", $post->ID ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);

	if ( empty( $rows ) ) {
		echo '<p>Aucun historique.</p>';
		return;
	}

	echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Par</th><th>Changement</th><th>Motif</th></tr></thead><tbody>';
	foreach ( $rows as $row ) {
		$user = $row->user_id ? get_userdata( (int) $row->user_id ) : false;
		$who  = $user ? $user->display_name : 'Candidat';
		$from = '' === $row->old_status ? '—' : gc_status_label( $row->old_status );
		printf(
			'<tr><td>%s</td><td>%s</td><td>%s → %s</td><td>%s</td></tr>',
			esc_html( mysql2date( 'd/m/Y H:i', $row->created_at ) ),
			esc_html( $who ),
			esc_html( $from ),
			esc_html( gc_status_label( $row->new_status ) ),
			esc_html( (string) $row->reason )
		);
	}
	echo '</tbody></table>';
}

/**
 * Enregistre le changement de statut depuis la fiche.
 *
 * @param int $post_id ID de la candidature.
 */
function gc_save_status_box( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['gc_status_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gc_status_nonce'] ) ), 'gc_status_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'gc_view' ) || ! isset( $_POST['gc_new_status'] ) ) {
		return;
	}

	$new    = sanitize_key( wp_unslash( $_POST['gc_new_status'] ) );
	$reason = isset( $_POST['gc_reason'] ) ? wp_unslash( $_POST['gc_reason'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nettoyé dans gc_change_status().

	$result = gc_change_status( $post_id, $new, $reason );
	if ( is_wp_error( $result ) ) {
		set_transient( 'gc_notice_' . get_current_user_id(), $result->get_error_message(), 60 );
	}
}

/**
 * Affiche l'erreur éventuelle du dernier enregistrement.
 */
function gc_admin_notices() {
	$key = 'gc_notice_' . get_current_user_id();
	$msg = get_transient( $key );
	if ( $msg ) {
		delete_transient( $key );
		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $msg ) );
	}
}

/**
 * Colonnes de la liste des candidatures.
 *
 * @param array $columns Colonnes d'origine.
 * @return array
 */
function gc_columns( $columns ) {
	return array(
		'cb'          => $columns['cb'],
		'gc_dossier'  => 'Dossier',
		'title'       => 'Candidat',
		'gc_urgency'  => 'Urgence',
		'gc_location' => 'Localisation',
		'gc_contact'  => 'Contact (Tél / Email)',
		'gc_docs'     => 'Justificatifs',
		'gc_status'   => 'Statut',
		'date'        => 'Reçue le',
	);
}

/**
 * Contenu des colonnes de la liste.
 *
 * @param string $column  Colonne.
 * @param int    $post_id ID de la candidature.
 */
function gc_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'gc_dossier':
			$dossier = (string) get_post_meta( $post_id, 'gc_dossier', true );
			printf( '<strong>%s</strong>', esc_html( $dossier ? $dossier : '—' ) );
			break;

		case 'gc_urgency':
			$urgency = (string) get_post_meta( $post_id, 'gc_urgency', true );
			if ( 'urgente' === $urgency ) {
				echo '<span style="display:inline-block;padding:3px 8px;background:#fee2e2;color:#991b1b;border-radius:4px;font-weight:700;font-size:11.5px;">CRITIQUE (&lt; 7j)</span>';
			} elseif ( 'moderee' === $urgency ) {
				echo '<span style="display:inline-block;padding:3px 8px;background:#ffedd5;color:#9a3412;border-radius:4px;font-weight:700;font-size:11.5px;">Court terme</span>';
			} else {
				echo '<span style="display:inline-block;padding:3px 8px;background:#f1f5f9;color:#475569;border-radius:4px;font-size:11.5px;">Planifié</span>';
			}
			break;

		case 'gc_location':
			$city    = (string) get_post_meta( $post_id, 'gc_city', true );
			$country = (string) get_post_meta( $post_id, 'gc_country', true );
			echo esc_html( $city . ( $country ? ' (' . $country . ')' : '' ) );
			break;

		case 'gc_contact':
			$phone = (string) get_post_meta( $post_id, 'gc_phone', true );
			$email = (string) get_post_meta( $post_id, 'gc_email', true );
			echo '<div><a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a></div>';
			if ( $email ) {
				echo '<div style="font-size:11.5px;color:#64748b;"><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></div>';
			}
			break;

		case 'gc_docs':
			$has_id  = (bool) get_post_meta( $post_id, 'gc_id_document', true );
			$has_sit = (bool) get_post_meta( $post_id, 'gc_situation_document', true );
			$missing = (bool) get_post_meta( $post_id, 'gc_has_missing_doc', true );

			$badges = array();
			if ( $has_id ) {
				$badges[] = '<span style="display:inline-block;padding:2px 6px;background:#dcfce7;color:#166534;border-radius:3px;font-size:11px;margin-right:3px;">ID</span>';
			}
			if ( $has_sit ) {
				$badges[] = '<span style="display:inline-block;padding:2px 6px;background:#e0e7ff;color:#3730a3;border-radius:3px;font-size:11px;margin-right:3px;">Situat.</span>';
			}
			if ( $missing ) {
				$badges[] = '<span style="display:inline-block;padding:2px 6px;background:#fef3c7;color:#92400e;border-radius:3px;font-size:11px;" title="Document manquant déclaré">Manquant</span>';
			}
			if ( empty( $badges ) ) {
				echo '<span style="color:#94a3b8;font-size:11px;">Aucun</span>';
			} else {
				echo implode( '', $badges ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			break;

		case 'gc_status':
			echo gc_status_badge( gc_get_status( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			break;
	}
}

/**
 * Liens de filtre par statut au-dessus de la liste.
 *
 * @param array $views Liens d'origine.
 * @return array
 */
function gc_status_views( $views ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$current = isset( $_GET['gc_status'] ) ? sanitize_key( wp_unslash( $_GET['gc_status'] ) ) : '';

	foreach ( gc_statuses() as $slug => $label ) {
		$url             = add_query_arg(
			array(
				'post_type' => 'gc_candidature',
				'gc_status' => $slug,
			),
			admin_url( 'edit.php' )
		);
		$views[ 'gc_' . $slug ] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( $url ),
			$current === $slug ? ' class="current" aria-current="page"' : '',
			esc_html( $label ),
			gc_count_by_status( array( $slug ) )
		);
	}

	return $views;
}

/**
 * Applique le filtre de statut et d'urgence à la liste.
 *
 * @param WP_Query $query Requête en cours.
 */
function gc_filter_list_by_status( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'gc_candidature' !== $query->get( 'post_type' ) ) {
		return;
	}

	$meta_query = array();

	// Filtre par statut
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_GET['gc_status'] ) ) {
		$status = sanitize_key( wp_unslash( $_GET['gc_status'] ) );
		if ( isset( gc_statuses()[ $status ] ) ) {
			$meta_query[] = array(
				'key'   => 'gc_status',
				'value' => $status,
			);
		}
	}

	// Filtre par urgence
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_GET['gc_urgency'] ) ) {
		$urgency = sanitize_key( wp_unslash( $_GET['gc_urgency'] ) );
		if ( in_array( $urgency, array( 'urgente', 'moderee', 'planifiee' ), true ) ) {
			$meta_query[] = array(
				'key'   => 'gc_urgency',
				'value' => $urgency,
			);
		}
	}

	if ( ! empty( $meta_query ) ) {
		if ( count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'AND';
		}
		$query->set( 'meta_query', $meta_query );
	}
}

/**
 * Menu déroulant de filtre par urgence au-dessus du tableau.
 */
add_action( 'restrict_manage_posts', 'gc_admin_urgency_filter_dropdown' );
function gc_admin_urgency_filter_dropdown( $post_type ) {
	if ( 'gc_candidature' !== $post_type ) {
		return;
	}

	$current = isset( $_GET['gc_urgency'] ) ? sanitize_key( wp_unslash( $_GET['gc_urgency'] ) ) : '';
	$options = array(
		'urgente'   => 'Urgence critique (< 7j)',
		'moderee'   => 'Besoin court terme',
		'planifiee' => 'Besoin planifié',
	);

	echo '<select name="gc_urgency">';
	echo '<option value="">Toutes les urgences</option>';
	foreach ( $options as $val => $label ) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $val ),
			selected( $current, $val, false ),
			esc_html( $label )
		);
	}
	echo '</select>';
}
