<?php
/**
 * Dynamic Tag: Kit ja/nein.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Tags;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Gibt einen Text für Kits bzw. Einzelgeräte aus. Mit leerem Nein-Text
 * eignet sich der Tag auch für Elementors dynamische Anzeigebedingungen.
 */
class Ist_Kit_Tag extends Base_Tag {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-ist-kit';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Kit ja/nein', 'cinerent-shop' );
	}

	/**
	 * Steuerelemente.
	 */
	protected function register_controls() {
		$this->add_control(
			'text_ja',
			array(
				'label'   => __( 'Text bei Kit', 'cinerent-shop' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Kit', 'cinerent-shop' ),
			)
		);
		$this->add_control(
			'text_nein',
			array(
				'label'   => __( 'Text bei Einzelgerät', 'cinerent-shop' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);
	}

	/**
	 * Ausgabe.
	 */
	public function render() {
		$item = $this->item();
		if ( ! $item ) {
			return;
		}
		echo esc_html( (string) $this->get_settings( $item['ist_kit'] ? 'text_ja' : 'text_nein' ) );
	}
}
