<?php
/**
 * Inline-SVG-Icons.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

defined( 'ABSPATH' ) || exit;

/**
 * Monochrome Linien-Icons (24 × 24, currentColor, 1,5 px Strich).
 */
final class Icons {

	/**
	 * Pfade.
	 *
	 * @var array<string, string>
	 */
	const PATHS = array(
		'kalender'   => '<rect x="3.75" y="5.25" width="16.5" height="15" rx="0.5"/><path d="M3.75 9.75h16.5M8.25 3v4.5M15.75 3v4.5"/>',
		'pfeil'      => '<path d="M4.5 12h15M14.25 6.75 19.5 12l-5.25 5.25"/>',
		'liste'      => '<path d="M8.25 6.75h12M8.25 12h12M8.25 17.25h12M3.75 6.75h.01M3.75 12h.01M3.75 17.25h.01"/>',
		'plus'       => '<path d="M12 4.5v15M4.5 12h15"/>',
		'minus'      => '<path d="M4.5 12h15"/>',
		'schliessen' => '<path d="M6 6l12 12M18 6 6 18"/>',
		'haken'      => '<path d="m4.5 12.75 4.5 4.5 10.5-10.5"/>',
		'zurueck'    => '<path d="M15 5.25 8.25 12 15 18.75"/>',
		'weiter'     => '<path d="M9 5.25 15.75 12 9 18.75"/>',
	);

	/**
	 * SVG-Markup.
	 *
	 * @param string $name Icon-Name.
	 * @return string
	 */
	public static function svg( $name ) {
		if ( ! isset( self::PATHS[ $name ] ) ) {
			return '';
		}
		return '<svg class="cr-icon cr-icon--' . esc_attr( $name ) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" aria-hidden="true" focusable="false">' . self::PATHS[ $name ] . '</svg>';
	}

	/**
	 * Erlaubte Tags für wp_kses.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function kses() {
		return array(
			'svg'  => array(
				'class'          => true,
				'xmlns'          => true,
				'viewbox'        => true,
				'width'          => true,
				'height'         => true,
				'fill'           => true,
				'stroke'         => true,
				'stroke-width'   => true,
				'stroke-linecap' => true,
				'aria-hidden'    => true,
				'focusable'      => true,
			),
			'path' => array( 'd' => true ),
			'rect' => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
			),
		);
	}
}
