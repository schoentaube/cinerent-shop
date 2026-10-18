<?php
/**
 * Dynamic Tag: Kurztext.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Tags;

defined( 'ABSPATH' ) || exit;

/**
 * Kurztext des aktuellen Equipments.
 */
class Kurztext_Tag extends Base_Tag {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-kurztext';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Kurztext', 'cinerent-shop' );
	}

	/**
	 * Ausgabe.
	 */
	public function render() {
		$item = $this->item();
		if ( $item ) {
			echo esc_html( $item['kurztext'] );
		}
	}
}
