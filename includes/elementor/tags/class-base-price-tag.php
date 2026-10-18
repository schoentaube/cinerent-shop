<?php
/**
 * Basis für Preis-Tags.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Tags;

use CineRent\Shop\Format;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Gibt einen Cent-Betrag deutsch formatiert aus.
 */
abstract class Base_Price_Tag extends Base_Tag {

	/**
	 * Feld in Post_Type::get_item().
	 *
	 * @return string
	 */
	abstract protected function field();

	/**
	 * Steuerelemente.
	 */
	protected function register_controls() {
		$this->add_control(
			'format',
			array(
				'label'   => __( 'Format', 'cinerent-shop' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'euro',
				'options' => array(
					'euro' => __( 'Mit Euro-Zeichen (149,00 €)', 'cinerent-shop' ),
					'zahl' => __( 'Nur Zahl (149,00)', 'cinerent-shop' ),
				),
			)
		);
	}

	/**
	 * Ausgabe.
	 */
	public function render() {
		$item = $this->item();
		if ( ! $item || empty( $item[ $this->field() ] ) ) {
			return;
		}
		$with_symbol = 'zahl' !== $this->get_settings( 'format' );
		echo '<span class="cr-preis">' . esc_html( Format::price( (int) $item[ $this->field() ], $with_symbol ) ) . '</span>';
	}
}
