<?php
/**
 * Dynamic Tag: Kit-Inhalt.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Tags;

use CineRent\Shop\Format;

defined( 'ABSPATH' ) || exit;

/**
 * Inhalt eines Kits als Liste (Menge × Name).
 */
class Kit_Inhalt_Tag extends Base_Tag {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-kit-inhalt';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Kit-Inhalt', 'cinerent-shop' );
	}

	/**
	 * Ausgabe.
	 */
	public function render() {
		$item = $this->item();
		if ( $item && $item['kit_inhalt'] ) {
			echo wp_kses_post( Format::kit_list( $item['kit_inhalt'] ) );
		}
	}
}
