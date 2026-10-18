<?php
/**
 * Widget: Button "Zur Anfrageliste".
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Widgets;

use CineRent\Shop\Post_Type;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Legt das aktuelle Equipment auf die Anfrageliste. Ist noch kein Zeitraum
 * gewählt, fragt ein kleiner Dialog danach.
 */
class Anfrage_Button_Widget extends Base_Widget {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-anfrage-button';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Zur Anfrageliste', 'cinerent-shop' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-button';
	}

	/**
	 * Steuerelemente.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'inhalt', array( 'label' => __( 'Button', 'cinerent-shop' ) ) );
		$this->add_control(
			'text',
			array(
				'label'   => __( 'Text', 'cinerent-shop' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Zur Anfrageliste', 'cinerent-shop' ),
			)
		);
		$this->add_control(
			'text_ok',
			array(
				'label'   => __( 'Text nach dem Hinzufügen', 'cinerent-shop' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Auf der Anfrageliste', 'cinerent-shop' ),
			)
		);
		$this->add_control(
			'icon',
			array(
				'label'        => __( 'Icon anzeigen', 'cinerent-shop' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_responsive_control(
			'ausrichtung',
			array(
				'label'     => __( 'Ausrichtung', 'cinerent-shop' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => __( 'Links', 'cinerent-shop' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => __( 'Mitte', 'cinerent-shop' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => __( 'Rechts', 'cinerent-shop' ),
						'icon'  => 'eicon-text-align-right',
					),
					'stretch'    => array(
						'title' => __( 'Volle Breite', 'cinerent-shop' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'selectors' => array( '{{WRAPPER}} .cr-add' => 'align-items: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'stil',
			array(
				'label' => __( 'Button', 'cinerent-shop' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typo',
				'selector' => '{{WRAPPER}} .cr-button',
				'global'   => array( 'default' => Global_Typography::TYPOGRAPHY_ACCENT ),
			)
		);

		$this->start_controls_tabs( 'zustand' );
		foreach ( array(
			'normal' => array( __( 'Normal', 'cinerent-shop' ), '.cr-button' ),
			'hover'  => array( __( 'Hover', 'cinerent-shop' ), '.cr-button:hover, {{WRAPPER}} .cr-button:focus-visible' ),
		) as $key => $tab ) {
			$this->start_controls_tab( 'zustand_' . $key, array( 'label' => $tab[0] ) );
			$this->add_control(
				'farbe_' . $key,
				array(
					'label'     => __( 'Textfarbe', 'cinerent-shop' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} ' . $tab[1] => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'hintergrund_' . $key,
				array(
					'label'     => __( 'Hintergrund', 'cinerent-shop' ),
					'type'      => Controls_Manager::COLOR,
					'global'    => 'normal' === $key ? array( 'default' => Global_Colors::COLOR_ACCENT ) : array(),
					'selectors' => array( '{{WRAPPER}} ' . $tab[1] => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();

		$this->add_box_controls( 'box', '.cr-button' );
		$this->end_controls_section();
	}

	/**
	 * Ausgabe.
	 */
	protected function render() {
		$s    = $this->get_settings_for_display();
		$item = Post_Type::get_item();
		if ( ! $item && ! $this->is_editor() ) {
			return;
		}

		$data = $item ? array(
			'id'               => $item['id'],
			'name'             => $item['name'],
			'url'              => $item['url'],
			'bild'             => $item['bild'] ? $item['bild'] : '',
			'preis_tag_cent'   => $item['preis_tag_cent'],
			'preis_woche_cent' => $item['preis_woche_cent'],
		) : array();
		?>
		<div class="cr-add">
			<button type="button" class="cr-button" data-cr="add"
				data-item="<?php echo esc_attr( wp_json_encode( $data ) ); ?>"
				data-text-ok="<?php echo esc_attr( $s['text_ok'] ); ?>">
				<?php
				if ( 'yes' === $s['icon'] ) {
					$this->print_icon( 'plus' );
				}
				?>
				<span class="cr-button__text"><?php echo esc_html( $s['text'] ); ?></span>
			</button>
		</div>
		<?php
	}
}
