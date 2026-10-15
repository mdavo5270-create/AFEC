<?php
/**
 * Type de contenu privé « candidature ».
 *
 * Les candidatures ne sont jamais visibles côté public : pas d'URL, pas de recherche, pas de REST.
 *
 * @package GestionCandidatures
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'gc_register_cpt' );

/**
 * Déclare le type de contenu.
 */
function gc_register_cpt() {
	register_post_type(
		'gc_candidature',
		array(
			'labels'              => array(
				'name'          => 'Candidatures',
				'singular_name' => 'Candidature',
				'menu_name'     => 'Candidatures',
				'all_items'     => 'Toutes les candidatures',
				'edit_item'     => 'Dossier du candidat',
				'search_items'  => 'Rechercher un dossier',
				'not_found'     => 'Aucune candidature.',
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'menu_icon'           => 'dashicons-clipboard',
			'menu_position'       => 25,
			'supports'            => array( 'title' ),
			'map_meta_cap'        => true,
			'capabilities'        => array(
				'edit_post'              => 'gc_view',
				'read_post'              => 'gc_view',
				'delete_post'            => 'gc_delete',
				'edit_posts'             => 'gc_view',
				'edit_others_posts'      => 'gc_view',
				'edit_private_posts'     => 'gc_view',
				'edit_published_posts'   => 'gc_view',
				'publish_posts'          => 'gc_view',
				'read_private_posts'     => 'gc_view',
				'delete_posts'           => 'gc_delete',
				'delete_private_posts'   => 'gc_delete',
				'delete_published_posts' => 'gc_delete',
				'delete_others_posts'    => 'gc_delete',
				'create_posts'           => 'do_not_allow',
			),
		)
	);
}
