<?php
/**
 * Widget: Verfügbarkeitskalender.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Widgets;

use CineRent\Shop\Post_Type;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Monatsansicht mit belegten Tagen für die Einzelseite. Optional lässt sich
 * mit zwei Klicks der Mietzeitraum wählen.
 */
class Kalender_Widget extends Base_Widget {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-kalender';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Verfügbarkeitskalender', 'cinerent-shop' );
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
		$this->start_controls_section( 'inhalt', array( 'label' => __( 'Kalender', 'cinerent-shop' ) ) );
		$this->add_control(
			'monate',
			array(
				'label'   => __( 'Monate nebeneinander', 'cinerent-shop' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '1',
				'options' => array(
					'1' => '1',
					'2' => '2',
				),
			)
		);
		$this->add_control(
			'auswahl',
			array(
				'label'        => __( 'Zeitraum per Klick wählen', 'cinerent-shop' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'legende',
			array(
				'label'        => __( 'Legende anzeigen', 'cinerent-shop' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'stil',
			array(
				'label' => __( 'Kalender', 'cinerent-shop' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_text_controls( 'monat', '.cr-kalender__monat-titel', Global_Typography::TYPOGRAPHY_PRIMARY, Global_Colors::COLOR_PRIMARY );
		$this->add_control(
			'farbe_belegt',
			array(
				'label'     => __( 'Belegte Tage', 'cinerent-shop' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#B3261E',
				'selectors' => array( '{{WRAPPER}} .cr-kalender' => '--cr-belegt: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'farbe_auswahl',
			array(
				'label'     => __( 'Gewählter Zeitraum', 'cinerent-shop' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_ACCENT ),
				'selectors' => array( '{{WRAPPER}} .cr-kalender' => '--cr-akzent: {{VALUE}};' ),
			)
		);
		$this->add_gap_control( 'abstand', '.cr-kalender__monate', __( 'Abstand zwischen Monaten', 'cinerent-shop' ) );
		$this->add_box_controls( 'box', '.cr-kalender' );
		$this->end_controls_section();

		$this->add_text_style_section( 'tag', __( 'Tage', 'cinerent-shop' ), '.cr-kalender__tag', Global_Typography::TYPOGRAPHY_TEXT, Global_Colors::COLOR_TEXT );
	}

	/**
	 * Ausgabe. Der Kalender wird im Browser aufgebaut.
	 */
	protected function render() {
		$s    = $this->get_settings_for_display();
		$item = Post_Type::get_item();
		if ( ! $item && ! $this->is_editor() ) {
			return;
		}
		?>
		<div class="cr-kalender" data-cr="kalender"
			data-id="<?php echo esc_attr( $item ? $item['id'] : '' ); ?>"
			data-monate="<?php echo esc_attr( '2' === $s['monate'] ? '2' : '1' ); ?>"
			data-auswahl="<?php echo esc_attr( 'yes' === $s['auswahl'] ? '1' : '0' ); ?>">
			<div class="cr-kalender__kopf">
				<button type="button" class="cr-kalender__nav" data-cr-zurueck aria-label="<?php esc_attr_e( 'Vorheriger Monat', 'cinerent-shop' ); ?>"><?php $this->print_icon( 'zurueck' ); ?></button>
				<button type="button" class="cr-kalender__nav" data-cr-weiter aria-label="<?php esc_attr_e( 'Nächster Monat', 'cinerent-shop' ); ?>"><?php $this->print_icon( 'weiter' ); ?></button>
			</div>
			<div class="cr-kalender__monate" data-cr-monate></div>
			<p class="cr-kalender__hinweis" data-cr-hinweis hidden></p>
			<?php if ( 'yes' === $s['legende'] ) : ?>
				<ul class="cr-kalender__legende">
					<li><span class="cr-kalender__muster cr-kalender__muster--frei"></span><?php esc_html_e( 'frei', 'cinerent-shop' ); ?></li>
					<li><span class="cr-kalender__muster cr-kalender__muster--belegt"></span><?php esc_html_e( 'belegt', 'cinerent-shop' ); ?></li>
					<li><span class="cr-kalender__muster cr-kalender__muster--auswahl"></span><?php esc_html_e( 'Ihr Zeitraum', 'cinerent-shop' ); ?></li>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}
