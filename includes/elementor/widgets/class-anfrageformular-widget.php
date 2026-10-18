<?php
/**
 * Widget: Anfrageformular.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Widgets;

use CineRent\Shop\Inquiry;
use CineRent\Shop\Settings;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Formular mit Honeypot, Mindest-Ausfüllzeit, ALTCHA und Rate-Limit.
 * Übernimmt Anfrageliste und Zeitraum aus dem Browser.
 */
class Anfrageformular_Widget extends Base_Widget {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-anfrageformular';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Anfrageformular', 'cinerent-shop' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	/**
	 * Skripte.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'cinerent-frontend', 'cinerent-altcha', 'cinerent-anfrage' );
	}

	/**
	 * Steuerelemente.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'inhalt', array( 'label' => __( 'Formular', 'cinerent-shop' ) ) );
		$this->add_control(
			'zusammenfassung',
			array(
				'label'        => __( 'Anfrageliste und Zeitraum anzeigen', 'cinerent-shop' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'button_text',
			array(
				'label'   => __( 'Button-Text', 'cinerent-shop' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Anfrage senden', 'cinerent-shop' ),
			)
		);
		$this->add_control(
			'erfolg',
			array(
				'label'       => __( 'Erfolgsmeldung', 'cinerent-shop' ),
				'type'        => Controls_Manager::TEXTAREA,
				'default'     => '',
				'placeholder' => __( 'Leer = Standardtext', 'cinerent-shop' ),
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
		$this->add_gap_control( 'abstand', '.cr-formular__felder', __( 'Abstand zwischen Feldern', 'cinerent-shop' ) );
		$this->end_controls_section();

		$this->add_text_style_section( 'label', __( 'Beschriftungen', 'cinerent-shop' ), '.cr-formular__label', Global_Typography::TYPOGRAPHY_TEXT, Global_Colors::COLOR_SECONDARY );

		$this->start_controls_section(
			'stil_feld',
			array(
				'label' => __( 'Eingabefelder', 'cinerent-shop' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_text_controls( 'feld', '.cr-input' );
		$this->add_box_controls( 'feld_box', '.cr-input' );
		$this->end_controls_section();

		$this->start_controls_section(
			'stil_button',
			array(
				'label' => __( 'Button', 'cinerent-shop' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typo',
				'selector' => '{{WRAPPER}} .cr-button',
				'global'   => array( 'default' => Global_Typography::TYPOGRAPHY_ACCENT ),
			)
		);
		$this->add_control(
			'button_farbe',
			array(
				'label'     => __( 'Textfarbe', 'cinerent-shop' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .cr-button' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'button_bg',
			array(
				'label'     => __( 'Hintergrund', 'cinerent-shop' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_ACCENT ),
				'selectors' => array( '{{WRAPPER}} .cr-button' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ),
			)
		);
		$this->add_box_controls( 'button_box', '.cr-button' );
		$this->end_controls_section();
	}

	/**
	 * Ein Textfeld.
	 *
	 * @param string $uid      Präfix für IDs.
	 * @param string $name     Feldname.
	 * @param string $label    Beschriftung.
	 * @param string $type     Input-Typ.
	 * @param bool   $required Pflichtfeld.
	 * @param string $auto     autocomplete-Wert.
	 */
	private function field( $uid, $name, $label, $type, $required, $auto ) {
		$id = $uid . '-' . $name;
		?>
		<p class="cr-formular__feld cr-formular__feld--<?php echo esc_attr( $name ); ?>">
			<label class="cr-formular__label" for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $label ); ?><?php echo $required ? ' <span class="cr-formular__pflicht" aria-hidden="true">*</span>' : ''; ?>
			</label>
			<?php if ( 'textarea' === $type ) : ?>
				<textarea class="cr-input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="5" maxlength="5000"></textarea>
			<?php else : ?>
				<input class="cr-input" type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" autocomplete="<?php echo esc_attr( $auto ); ?>" maxlength="200"<?php echo $required ? ' required' : ''; ?>>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Ausgabe.
	 */
	protected function render() {
		$s         = $this->get_settings_for_display();
		$uid       = 'cr-af-' . $this->get_id();
		$agb_url   = (string) Settings::get( 'mietbedingungen_url' );
		$ds_url    = Settings::privacy_url();
		$erfolg    = '' !== trim( (string) $s['erfolg'] ) ? $s['erfolg'] : __( 'Vielen Dank! Ihre Anfrage ist eingegangen. Sie erhalten in Kürze eine Bestätigung per E-Mail.', 'cinerent-shop' );
		$link_kses = array(
			'a' => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
		);
		?>
		<form class="cr-formular" data-cr="formular" novalidate data-erfolg="<?php echo esc_attr( $erfolg ); ?>">
			<?php if ( 'yes' === $s['zusammenfassung'] ) : ?>
				<div class="cr-formular__liste cr-liste cr-liste--voll" data-cr="liste" data-variante="formular" data-bilder="0">
					<div class="cr-liste__zeitraum" data-cr-zeitraum-info></div>
					<ul class="cr-liste__eintraege" data-cr-eintraege></ul>
					<div class="cr-liste__summe" data-cr-summe hidden></div>
					<p class="cr-liste__hinweis" data-cr-hinweis hidden></p>
				</div>
			<?php endif; ?>

			<?php // Wird beim Laden per REST (/formular) erneuert, damit Seiten-Caches den Nonce nicht veralten lassen. ?>
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( Inquiry::NONCE_ACTION ) ); ?>">

			<div class="cr-formular__felder">
				<?php
				$this->field( $uid, 'firma', __( 'Firma', 'cinerent-shop' ), 'text', false, 'organization' );
				$this->field( $uid, 'name', __( 'Name', 'cinerent-shop' ), 'text', true, 'name' );
				$this->field( $uid, 'email', __( 'E-Mail', 'cinerent-shop' ), 'email', true, 'email' );
				$this->field( $uid, 'telefon', __( 'Telefon', 'cinerent-shop' ), 'tel', false, 'tel' );
				?>

				<fieldset class="cr-formular__feld cr-formular__feld--lieferung">
					<legend class="cr-formular__label"><?php esc_html_e( 'Übergabe', 'cinerent-shop' ); ?></legend>
					<label class="cr-formular__option"><input type="radio" name="lieferung" value="abholung" checked> <?php esc_html_e( 'Abholung', 'cinerent-shop' ); ?></label>
					<label class="cr-formular__option"><input type="radio" name="lieferung" value="versand"> <?php esc_html_e( 'Versand', 'cinerent-shop' ); ?></label>
				</fieldset>

				<?php $this->field( $uid, 'nachricht', __( 'Nachricht', 'cinerent-shop' ), 'textarea', false, 'off' ); ?>

				<?php // Honeypot: für Menschen unsichtbar, Bots füllen es aus. ?>
				<p class="cr-formular__hp" aria-hidden="true">
					<label for="<?php echo esc_attr( $uid ); ?>-website"><?php esc_html_e( 'Website (bitte leer lassen)', 'cinerent-shop' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $uid ); ?>-website" name="website" tabindex="-1" autocomplete="off">
				</p>

				<p class="cr-formular__feld cr-formular__feld--check">
					<label class="cr-formular__option">
						<input type="checkbox" name="mietbedingungen" value="1" required>
						<span>
							<?php
							if ( $agb_url ) {
								printf(
									/* translators: %s: Link zu den Mietbedingungen. */
									wp_kses( __( 'Ich akzeptiere die %s.', 'cinerent-shop' ), $link_kses ),
									'<a href="' . esc_url( $agb_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Mietbedingungen', 'cinerent-shop' ) . '</a>'
								);
							} else {
								esc_html_e( 'Ich akzeptiere die Mietbedingungen.', 'cinerent-shop' );
							}
							?>
							<span class="cr-formular__pflicht" aria-hidden="true">*</span>
						</span>
					</label>
				</p>
				<p class="cr-formular__feld cr-formular__feld--check">
					<label class="cr-formular__option">
						<input type="checkbox" name="datenschutz" value="1" required>
						<span>
							<?php
							$ds_link = $ds_url ? '<a href="' . esc_url( $ds_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Datenschutzerklärung', 'cinerent-shop' ) . '</a>' : esc_html__( 'Datenschutzerklärung', 'cinerent-shop' );
							printf(
								/* translators: %s: Link zur Datenschutzerklärung. */
								wp_kses( __( 'Ich habe die %s gelesen und bin mit der Verarbeitung meiner Angaben zur Bearbeitung der Anfrage einverstanden.', 'cinerent-shop' ), $link_kses ),
								wp_kses( $ds_link, $link_kses )
							);
							?>
							<span class="cr-formular__pflicht" aria-hidden="true">*</span>
						</span>
					</label>
				</p>
			</div>

			<div class="cr-formular__altcha" data-cr-altcha aria-live="polite">
				<span class="cr-formular__altcha-status" data-cr-altcha-status></span>
			</div>

			<p class="cr-formular__meldung" data-cr-meldung role="alert" hidden></p>

			<p class="cr-formular__absenden">
				<button type="submit" class="cr-button"><?php echo esc_html( $s['button_text'] ); ?></button>
			</p>
			<p class="cr-formular__pflichthinweis"><?php esc_html_e( '* Pflichtfelder', 'cinerent-shop' ); ?></p>
		</form>
		<?php
	}
}
