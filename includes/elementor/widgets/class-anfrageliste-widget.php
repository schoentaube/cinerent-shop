<?php
/**
 * Widget: Anfrageliste.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Mini-Variante mit Zähler für die Kopfzeile, volle Ansicht mit Richtwert.
 * Die Liste liegt im localStorage des Browsers.
 */
class Anfrageliste_Widget extends Base_Widget {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-anfrageliste';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Anfrageliste', 'cinerent-shop' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-bullet-list';
	}

	/**
	 * Steuerelemente.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'inhalt', array( 'label' => __( 'Anfrageliste', 'cinerent-shop' ) ) );
		$this->add_control(
			'variante',
			array(
				'label'   => __( 'Variante', 'cinerent-shop' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'voll',
				'options' => array(
					'mini' => __( 'Mini (Kopfzeile, mit Zähler)', 'cinerent-shop' ),
					'voll' => __( 'Volle Ansicht mit Richtwert', 'cinerent-shop' ),
				),
			)
		);
		$this->add_control(
			'mini_text',
			array(
				'label'     => __( 'Text', 'cinerent-shop' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'Anfrageliste', 'cinerent-shop' ),
				'condition' => array( 'variante' => 'mini' ),
			)
		);
		$this->add_control(
			'link',
			array(
				'label'       => __( 'Link', 'cinerent-shop' ),
				'type'        => Controls_Manager::URL,
				'dynamic'     => array( 'active' => true ),
				'description' => __( 'Mini: Seite mit der Anfrageliste. Voll: Seite mit dem Anfrageformular (leer lassen, wenn das Formular auf derselben Seite steht).', 'cinerent-shop' ),
			)
		);
		$this->add_control(
			'link_text',
			array(
				'label'     => __( 'Linktext', 'cinerent-shop' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'Weiter zur Anfrage', 'cinerent-shop' ),
				'condition' => array( 'variante' => 'voll' ),
			)
		);
		$this->add_control(
			'bilder',
			array(
				'label'        => __( 'Vorschaubilder anzeigen', 'cinerent-shop' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'variante' => 'voll' ),
			)
		);
		$this->end_controls_section();

		$this->add_text_style_section( 'text', __( 'Text', 'cinerent-shop' ), '.cr-liste', Global_Typography::TYPOGRAPHY_TEXT, Global_Colors::COLOR_TEXT );
		$this->add_text_style_section( 'preis', __( 'Preise', 'cinerent-shop' ), '.cr-liste .cr-preis', Global_Typography::TYPOGRAPHY_ACCENT, Global_Colors::COLOR_TEXT );

		$this->start_controls_section(
			'stil_zaehler',
			array(
				'label'     => __( 'Zähler', 'cinerent-shop' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'variante' => 'mini' ),
			)
		);
		$this->add_control(
			'zaehler_bg',
			array(
				'label'     => __( 'Hintergrund', 'cinerent-shop' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_ACCENT ),
				'selectors' => array( '{{WRAPPER}} .cr-liste__zaehler' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'zaehler_farbe',
			array(
				'label'     => __( 'Textfarbe', 'cinerent-shop' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cr-liste__zaehler' => 'color: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'stil_box',
			array(
				'label' => __( 'Box', 'cinerent-shop' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_gap_control( 'abstand', '.cr-liste__eintraege', __( 'Abstand zwischen Einträgen', 'cinerent-shop' ) );
		$this->add_box_controls( 'box', '.cr-liste' );
		$this->end_controls_section();
	}

	/**
	 * Ausgabe. Inhalt wird im Browser aus dem localStorage aufgebaut.
	 */
	protected function render() {
		$s    = $this->get_settings_for_display();
		$url  = ! empty( $s['link']['url'] ) ? $s['link']['url'] : '';
		$mini = 'mini' === $s['variante'];

		if ( $mini ) {
			$tag = $url ? 'a' : 'span';
			?>
			<<?php echo esc_html( $tag ); ?> class="cr-liste cr-liste--mini" data-cr="liste" data-variante="mini"<?php echo $url ? ' href="' . esc_url( $url ) . '"' : ''; ?>>
				<?php $this->print_icon( 'liste' ); ?>
				<span class="cr-liste__label"><?php echo esc_html( $s['mini_text'] ); ?></span>
				<span class="cr-liste__zaehler" data-cr-zaehler aria-live="polite">0</span>
			</<?php echo esc_html( $tag ); ?>>
			<?php
			return;
		}
		?>
		<div class="cr-liste cr-liste--voll" data-cr="liste" data-variante="voll" data-bilder="<?php echo esc_attr( 'yes' === $s['bilder'] ? '1' : '0' ); ?>">
			<div class="cr-liste__zeitraum" data-cr-zeitraum-info></div>
			<ul class="cr-liste__eintraege" data-cr-eintraege></ul>
			<div class="cr-liste__summe" data-cr-summe hidden></div>
			<p class="cr-liste__hinweis" data-cr-hinweis hidden></p>
			<?php if ( $url ) : ?>
				<p class="cr-liste__weiter" data-cr-weiter hidden><a class="cr-button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $s['link_text'] ); ?> <?php $this->print_icon( 'pfeil' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
