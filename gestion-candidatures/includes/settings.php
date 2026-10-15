<?php
/**
 * Réglages : nombre de places et date limite.
 *
 * @package GestionCandidatures
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'gc_settings_menu' );
add_action( 'admin_init', 'gc_register_settings' );

/**
 * Ajoute la page « Réglages » sous « Candidatures ».
 */
function gc_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=gc_candidature',
		'Réglages des candidatures',
		'Réglages',
		'gc_settings',
		'gc-settings',
		'gc_settings_page'
	);
}

/**
 * Déclare les options.
 */
function gc_register_settings() {
	register_setting(
		'gc_settings_group',
		'gc_places',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'gc_sanitize_places',
			'default'           => 58,
		)
	);
	register_setting(
		'gc_settings_group',
		'gc_deadline',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'gc_sanitize_deadline',
			'default'           => '',
		)
	);
}

/**
 * Nombre de places : entier entre 1 et 10000.
 *
 * @param mixed $value Valeur saisie.
 * @return int
 */
function gc_sanitize_places( $value ) {
	return min( 10000, max( 1, absint( $value ) ) );
}

/**
 * Date limite : AAAA-MM-JJ ou vide.
 *
 * @param mixed $value Valeur saisie.
 * @return string
 */
function gc_sanitize_deadline( $value ) {
	$value = sanitize_text_field( (string) $value );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
}

/**
 * Affiche la page de réglages.
 */
function gc_settings_page() {
	if ( ! current_user_can( 'gc_settings' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>Réglages des candidatures</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'gc_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="gc_places">Nombre de places</label></th>
					<td><input type="number" min="1" max="10000" id="gc_places" name="gc_places" value="<?php echo esc_attr( (string) gc_places() ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="gc_deadline">Date limite de candidature</label></th>
					<td>
						<input type="date" id="gc_deadline" name="gc_deadline" value="<?php echo esc_attr( (string) get_option( 'gc_deadline', '' ) ); ?>">
						<p class="description">Laissez vide pour ne pas fermer automatiquement. Le formulaire se ferme le lendemain de cette date.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
