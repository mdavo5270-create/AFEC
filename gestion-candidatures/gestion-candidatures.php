<?php
/**
 * Plugin Name: Gestion Candidatures
 * Description: Réception des candidatures, analyse des dossiers par une équipe, suivi des statuts et des contacts.
 * Version:     0.1.1
 * Requires PHP: 7.4
 * Text Domain: gestion-candidatures
 *
 * @package GestionCandidatures
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GC_VERSION', '0.1.1' );
define( 'GC_FILE', __FILE__ );
define( 'GC_DIR', plugin_dir_path( __FILE__ ) );
define( 'GC_URL', plugin_dir_url( __FILE__ ) );

require_once GC_DIR . 'includes/statuses.php';
require_once GC_DIR . 'includes/install.php';
require_once GC_DIR . 'includes/cpt.php';
require_once GC_DIR . 'includes/form.php';
require_once GC_DIR . 'includes/admin.php';
require_once GC_DIR . 'includes/settings.php';

register_activation_hook( __FILE__, 'gc_activate' );
