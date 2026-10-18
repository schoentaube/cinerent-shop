<?php
/**
 * Widget: Zeitraum-Auswahl.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Abholung und Rückgabe. Der Zeitraum gilt seitenübergreifend (Cookie).
 */
class Zeitraum_Widget extends Base_Widget {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-zeitraum';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Zeitraum-Auswahl', 'cinerent-shop' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-calendar';
	}

	/**
	 * Steuerelemente.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'inhalt', array( 'label' => __( 'Zeitraum', 'cinerent-shop' ) ) );

		$this->add_control(
			'variante',
			array(
				'label'   => __( 'Variante', 'cinerent-shop' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'gross',
				'options' => array(
					'kompakt' => __( 'Kompakt (Kopfzeile)', 'cinerent-shop' ),
					'gross'   => __( 'Groß (Seite)', 'cinerent-shop' ),
				),
			)
		);
		$this->add_control(
			'label_von',
			array(
				'label'   => __( 'Beschriftung Abholung', 'cinerent-shop' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Abholung', 'cinerent-shop' ),
			)
		);
		$this->add_control(
			'label_bis',
			array(
				'label'   => __( 'Beschriftung Rückgabe', 'cinerent-shop' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Rückgabe', 'cinerent-shop' ),
			)
		);
		$this->add_control(
			'dauer',
			array(
				'label'        => __( 'Mietdauer anzeigen', 'cinerent-shop' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'neu_laden',
			array(
				'label'        => __( 'Seite nach Änderung neu laden', 'cinerent-shop' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Für Katalogseiten mit Loop Grid und Query-ID „cinerent_frei“, damit die Liste neu gefiltert wird.', 'cinerent-shop' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'stil_layout',
			array(
				'label' => __( 'Layout', 'cinerent-shop' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_gap_control( 'abstand', '.cr-zeitraum__felder' );
		$this->add_box_controls( 'rahmen', '.cr-zeitraum' );
		$this->end_controls_section();

		$this->add_text_style_section( 'label', __( 'Beschriftung', 'cinerent-shop' ), '.cr-zeitraum__label', Global_Typography::TYPOGRAPHY_TEXT, Global_Colors::COLOR_SECONDARY );

		$this->start_controls_section(
			'stil_feld',
			array(
				'label' => __( 'Datumsfeld', 'cinerent-shop' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_text_controls( 'feld', '.cr-input' );
		$this->add_box_controls( 'feld_box', '.cr-input' );
		$this->end_controls_section();

		$this->add_text_style_section( 'dauer_stil', __( 'Mietdauer', 'cinerent-shop' ), '.cr-zeitraum__dauer', Global_Typography::TYPOGRAPHY_ACCENT, Global_Colors::COLOR_ACCENT );
	}

	/**
	 * Ausgabe.
	 */
	protected function render() {
		$s        = $this->get_settings_for_display();
		$variante = 'kompakt' === $s['variante'] ? 'kompakt' : 'gross';
		$uid      = 'cr-zr-' . $this->get_id();
		?>
		<div class="cr-zeitraum cr-zeitraum--<?php echo esc_attr( $variante ); ?>" data-cr="zeitraum" data-reload="<?php echo esc_attr( 'yes' === $s['neu_laden'] ? '1' : '0' ); ?>">
			<div class="cr-zeitraum__felder">
				<label class="cr-zeitraum__feld" for="<?php echo esc_attr( $uid ); ?>-von">
					<span class="cr-zeitraum__label"><?php $this->print_icon( 'kalender' ); ?> <?php echo esc_html( $s['label_von'] ); ?></span>
					<input class="cr-input" type="date" id="<?php echo esc_attr( $uid ); ?>-von" name="von" data-cr-von>
				</label>
				<span class="cr-zeitraum__pfeil"><?php $this->print_icon( 'pfeil' ); ?></span>
				<label class="cr-zeitraum__feld" for="<?php echo esc_attr( $uid ); ?>-bis">
					<span class="cr-zeitraum__label"><?php $this->print_icon( 'kalender' ); ?> <?php echo esc_html( $s['label_bis'] ); ?></span>
					<input class="cr-input" type="date" id="<?php echo esc_attr( $uid ); ?>-bis" name="bis" data-cr-bis>
				</label>
			</div>
			<?php if ( 'yes' === $s['dauer'] ) : ?>
				<span class="cr-zeitraum__dauer" data-cr-dauer aria-live="polite"></span>
			<?php endif; ?>
			<p class="cr-zeitraum__fehler" data-cr-fehler role="alert" hidden></p>
		</div>
		<?php
	}
}
