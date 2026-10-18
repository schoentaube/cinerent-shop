<?php
/**
 * Widget: Verfügbarkeits-Badge.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Widgets;

use CineRent\Shop\Post_Type;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Zeigt frei / belegt / auf Anfrage für das aktuelle Equipment und den gewählten
 * Zeitraum. Der Status wird live per REST abgefragt (ein Aufruf für alle Badges
 * einer Seite), damit er auch auf gecachten Seiten und im Loop Grid stimmt.
 */
class Verfuegbarkeit_Widget extends Base_Widget {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-verfuegbarkeit';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Verfügbarkeits-Badge', 'cinerent-shop' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-check-circle-o';
	}

	/**
	 * Steuerelemente.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'inhalt', array( 'label' => __( 'Texte', 'cinerent-shop' ) ) );
		$this->add_control(
			'text_frei',
			array(
				'label'   => __( 'Frei', 'cinerent-shop' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Frei', 'cinerent-shop' ),
			)
		);
		$this->add_control(
			'text_belegt',
			array(
				'label'   => __( 'Belegt', 'cinerent-shop' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Belegt', 'cinerent-shop' ),
			)
		);
		$this->add_control(
			'text_anfrage',
			array(
				'label'   => __( 'Auf Anfrage', 'cinerent-shop' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Auf Anfrage', 'cinerent-shop' ),
			)
		);
		$this->add_control(
			'punkt',
			array(
				'label'        => __( 'Statuspunkt anzeigen', 'cinerent-shop' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'stil',
			array(
				'label' => __( 'Badge', 'cinerent-shop' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typo',
				'selector' => '{{WRAPPER}} .cr-badge',
				'global'   => array( 'default' => Global_Typography::TYPOGRAPHY_TEXT ),
			)
		);
		$farben = array(
			'frei'    => array( __( 'Farbe frei', 'cinerent-shop' ), '#2F7A4E' ),
			'belegt'  => array( __( 'Farbe belegt', 'cinerent-shop' ), '#B3261E' ),
			'anfrage' => array( __( 'Farbe auf Anfrage', 'cinerent-shop' ), '#59656E' ),
		);
		foreach ( $farben as $status => $farbe ) {
			$this->add_control(
				'farbe_' . $status,
				array(
					'label'     => $farbe[0],
					'type'      => Controls_Manager::COLOR,
					'default'   => $farbe[1],
					'selectors' => array( '{{WRAPPER}} .cr-badge--' . $status => '--cr-badge-farbe: {{VALUE}};' ),
				)
			);
		}
		$this->add_box_controls( 'box', '.cr-badge' );
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

		$texts = array(
			'frei'    => $s['text_frei'],
			'belegt'  => $s['text_belegt'],
			'anfrage' => $s['text_anfrage'],
		);
		?>
		<span class="cr-badge cr-badge--anfrage<?php echo 'yes' === $s['punkt'] ? ' cr-badge--punkt' : ''; ?>"
			data-cr="badge"
			data-id="<?php echo esc_attr( $item ? $item['id'] : '' ); ?>"
			data-texte="<?php echo esc_attr( wp_json_encode( $texts ) ); ?>">
			<span class="cr-badge__punkt" aria-hidden="true"></span>
			<span class="cr-badge__text"><?php echo esc_html( $s['text_anfrage'] ); ?></span>
		</span>
		<?php
	}
}
