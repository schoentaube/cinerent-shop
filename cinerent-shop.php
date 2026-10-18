<?php
/**
 * Plugin Name:       CineRent Shop
 * Plugin URI:        https://cinerent.de/
 * Description:       Equipment-Vermietung für cinerent.de: Katalog-Spiegelung als Custom Post Type, Elementor-Pro-Dynamic-Tags, Widgets für Zeitraum, Verfügbarkeit, Anfrageliste und Anfrageformular.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            CineRent
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cinerent-shop
 * Domain Path:       /languages
 * Elementor tested up to: 3.30
 * Elementor Pro tested up to: 3.30
 *
 * @package CineRent\Shop
 */

defined( 'ABSPATH' ) || exit;

define( 'CINERENT_SHOP_VERSION', '0.1.0' );
define( 'CINERENT_SHOP_FILE', __FILE__ );
define( 'CINERENT_SHOP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CINERENT_SHOP_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader für die Klassen im Namespace CineRent\Shop.
 *
 * CineRent\Shop\Data\Local_Json_Source wird zu includes/data/class-local-json-source.php,
 * Interfaces liegen in interface-*.php.
 *
 * @param string $class_name Vollqualifizierter Klassenname.
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'CineRent\\Shop\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$parts = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$name  = strtolower( str_replace( '_', '-', array_pop( $parts ) ) );
		$dir   = CINERENT_SHOP_DIR . 'includes/';
		foreach ( $parts as $part ) {
			$dir .= strtolower( str_replace( '_', '-', $part ) ) . '/';
		}

		foreach ( array( 'class-', 'interface-', 'abstract-' ) as $type ) {
			$file = $dir . $type . $name . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
);

register_activation_hook( __FILE__, array( 'CineRent\\Shop\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CineRent\\Shop\\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'CineRent\\Shop\\Plugin', 'instance' ) );
