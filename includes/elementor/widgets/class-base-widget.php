<?php
/**
 * Basis für alle CineRent-Widgets.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Widgets;

use CineRent\Shop\Elementor\Integration;
use CineRent\Shop\Icons;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Gemeinsame Kategorie, Assets und Stil-Steuerelemente.
 *
 * Standardwerte zeigen auf die globalen Farben und Schriften des Elementor-Kits;
 * frontend.css nutzt zusätzlich die CSS-Variablen des Kits (--e-global-…).
 */
abstract class Base_Widget extends Widget_Base {

	/**
	 * Kategorie.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( Integration::GROUP );
	}

	/**
	 * Schlagwörter für die Suche im Panel.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'cinerent', 'miete', 'equipment', 'verleih' );
	}

	/**
	 * Skripte.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'cinerent-frontend' );
	}

	/**
	 * Styles.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'cinerent-frontend' );
	}

	/**
	 * Ob das Widget gerade im Elementor-Editor angezeigt wird.
	 *
	 * @return bool
	 */
	protected function is_editor() {
		return \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode();
	}

	/**
	 * Stil-Abschnitt für einen Textbereich: Typografie und Farbe.
	 *
	 * @param string $id       Abschnitts-ID.
	 * @param string $label    Titel.
	 * @param string $selector CSS-Selektor relativ zu {{WRAPPER}}.
	 * @param string $typo     Globale Typografie.
	 * @param string $color    Globale Farbe.
	 */
	protected function add_text_style_section( $id, $label, $selector, $typo = Global_Typography::TYPOGRAPHY_TEXT, $color = Global_Colors::COLOR_TEXT ) {
		$this->start_controls_section(
			$id,
			array(
				'label' => $label,
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_text_controls( $id, $selector, $typo, $color );
		$this->end_controls_section();
	}

	/**
	 * Typografie und Farbe (innerhalb eines Abschnitts).
	 *
	 * @param string $id       Präfix.
	 * @param string $selector Selektor.
	 * @param string $typo     Globale Typografie.
	 * @param string $color    Globale Farbe.
	 */
	protected function add_text_controls( $id, $selector, $typo = Global_Typography::TYPOGRAPHY_TEXT, $color = Global_Colors::COLOR_TEXT ) {
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => $id . '_typo',
				'selector' => '{{WRAPPER}} ' . $selector,
				'global'   => array( 'default' => $typo ),
			)
		);
		$this->add_control(
			$id . '_color',
			array(
				'label'     => __( 'Farbe', 'cinerent-shop' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => $color ),
				'selectors' => array( '{{WRAPPER}} ' . $selector => 'color: {{VALUE}};' ),
			)
		);
	}

	/**
	 * Hintergrund, Rahmen, Rundung und Innenabstand (innerhalb eines Abschnitts).
	 *
	 * @param string $id       Präfix.
	 * @param string $selector Selektor.
	 */
	protected function add_box_controls( $id, $selector ) {
		$this->add_control(
			$id . '_bg',
			array(
				'label'     => __( 'Hintergrund', 'cinerent-shop' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} ' . $selector => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => $id . '_border',
				'selector' => '{{WRAPPER}} ' . $selector,
			)
		);
		$this->add_responsive_control(
			$id . '_radius',
			array(
				'label'      => __( 'Rundung', 'cinerent-shop' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} ' . $selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			$id . '_padding',
			array(
				'label'      => __( 'Innenabstand', 'cinerent-shop' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} ' . $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
	}

	/**
	 * Abstand zwischen Elementen (gap).
	 *
	 * @param string $id       Control-ID.
	 * @param string $selector Selektor.
	 * @param string $label    Titel.
	 */
	protected function add_gap_control( $id, $selector, $label = '' ) {
		$this->add_responsive_control(
			$id,
			array(
				'label'      => $label ? $label : __( 'Abstand', 'cinerent-shop' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'selectors'  => array( '{{WRAPPER}} ' . $selector => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);
	}

	/**
	 * Gibt ein Inline-SVG-Icon aus (monochrom, currentColor).
	 *
	 * @param string $name Icon-Name.
	 */
	protected function print_icon( $name ) {
		echo wp_kses( Icons::svg( $name ), Icons::kses() );
	}
}
