<?php
/**
 * Dynamic Tag: Technische Daten.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Tags;

use CineRent\Shop\Format;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Technische Daten als Liste (Label/Wert).
 */
class Technische_Daten_Tag extends Base_Tag {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-technische-daten';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Technische Daten', 'cinerent-shop' );
	}

	/**
	 * Steuerelemente.
	 */
	protected function register_controls() {
		$this->add_control(
			'darstellung',
			array(
				'label'   => __( 'Darstellung', 'cinerent-shop' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'liste',
				'options' => array(
					'liste' => __( 'Liste (Label und Wert)', 'cinerent-shop' ),
					'zeile' => __( 'Eine Zeile, getrennt durch ·', 'cinerent-shop' ),
				),
			)
		);
		$this->add_control(
			'anzahl',
			array(
				'label'       => __( 'Höchstens anzeigen', 'cinerent-shop' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'default'     => 0,
				'description' => __( '0 = alle. Nützlich für Karten im Loop Grid.', 'cinerent-shop' ),
			)
		);
	}

	/**
	 * Ausgabe.
	 */
	public function render() {
		$item = $this->item();
		if ( ! $item || ! $item['technische_daten'] ) {
			return;
		}
		$rows   = $item['technische_daten'];
		$anzahl = (int) $this->get_settings( 'anzahl' );
		if ( $anzahl > 0 ) {
			$rows = array_slice( $rows, 0, $anzahl );
		}

		if ( 'zeile' === $this->get_settings( 'darstellung' ) ) {
			echo '<span class="cr-specs-zeile">' . esc_html( implode( ' · ', wp_list_pluck( $rows, 'wert' ) ) ) . '</span>';
			return;
		}
		echo wp_kses_post( Format::spec_list( $rows ) );
	}
}
