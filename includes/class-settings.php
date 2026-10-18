<?php
/**
 * Einstellungsseite.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

defined( 'ABSPATH' ) || exit;

/**
 * Einstellungen unter "Equipment → Einstellungen".
 */
final class Settings {

	const OPTION    = 'cinerent_shop_settings';
	const PAGE_SLUG = 'cinerent-shop-settings';

	/**
	 * Standardwerte.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'datenquelle'             => 'local_json',
			'storybase_url'           => '',
			'storybase_key'           => '',
			'anfrage_empfaenger'      => get_option( 'admin_email' ),
			'absender_name'           => get_bloginfo( 'name' ),
			'mietbedingungen_url'     => '',
			'datenschutz_url'         => '',
			'rate_limit'              => 5,
			'altcha_komplexitaet'     => 100000,
			'schriften_laden'         => 1,
			'google_fonts_blockieren' => 1,
		);
	}

	/**
	 * Einzelner Wert.
	 *
	 * @param string $key Schlüssel.
	 * @return mixed
	 */
	public static function get( $key ) {
		$options = get_option( self::OPTION, array() );
		$options = wp_parse_args( is_array( $options ) ? $options : array(), self::defaults() );
		return $options[ $key ] ?? null;
	}

	/**
	 * Shop-Schlüssel. Eine Konstante in wp-config.php hat Vorrang vor der Datenbank.
	 *
	 * @return string
	 */
	public static function storybase_key() {
		if ( defined( 'CINERENT_STORYBASE_SHOP_KEY' ) && CINERENT_STORYBASE_SHOP_KEY ) {
			return (string) CINERENT_STORYBASE_SHOP_KEY;
		}
		return (string) self::get( 'storybase_key' );
	}

	/**
	 * Datenschutz-URL: Einstellung oder WordPress-Datenschutzseite.
	 *
	 * @return string
	 */
	public static function privacy_url() {
		$url = (string) self::get( 'datenschutz_url' );
		return '' !== $url ? $url : get_privacy_policy_url();
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Menüpunkt.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Post_Type::NAME,
			__( 'CineRent Shop – Einstellungen', 'cinerent-shop' ),
			__( 'Einstellungen', 'cinerent-shop' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Settings API.
	 */
	public static function register() {
		register_setting(
			'cinerent_shop',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section( 'quelle', __( 'Datenquelle', 'cinerent-shop' ), '__return_false', self::PAGE_SLUG );
		add_settings_section( 'anfrage', __( 'Anfragen', 'cinerent-shop' ), '__return_false', self::PAGE_SLUG );
		add_settings_section( 'datenschutz', __( 'Schriften und Datenschutz', 'cinerent-shop' ), '__return_false', self::PAGE_SLUG );

		$fields = array(
			array( 'datenquelle', __( 'Aktive Quelle', 'cinerent-shop' ), 'quelle', 'field_source' ),
			array( 'storybase_url', __( 'Storybase-URL', 'cinerent-shop' ), 'quelle', 'field_url' ),
			array( 'storybase_key', __( 'Shop-Schlüssel', 'cinerent-shop' ), 'quelle', 'field_key' ),
			array( 'anfrage_empfaenger', __( 'Empfänger für Anfragen', 'cinerent-shop' ), 'anfrage', 'field_email' ),
			array( 'absender_name', __( 'Absendername', 'cinerent-shop' ), 'anfrage', 'field_text' ),
			array( 'mietbedingungen_url', __( 'Mietbedingungen (URL)', 'cinerent-shop' ), 'anfrage', 'field_url' ),
			array( 'datenschutz_url', __( 'Datenschutzerklärung (URL)', 'cinerent-shop' ), 'anfrage', 'field_url' ),
			array( 'rate_limit', __( 'Anfragen je IP und Stunde', 'cinerent-shop' ), 'anfrage', 'field_number' ),
			array( 'altcha_komplexitaet', __( 'ALTCHA-Komplexität', 'cinerent-shop' ), 'anfrage', 'field_number' ),
			array( 'schriften_laden', __( 'Mitgelieferte Schriften laden', 'cinerent-shop' ), 'datenschutz', 'field_checkbox' ),
			array( 'google_fonts_blockieren', __( 'Google Fonts in Elementor unterbinden', 'cinerent-shop' ), 'datenschutz', 'field_checkbox' ),
		);

		foreach ( $fields as $field ) {
			add_settings_field(
				$field[0],
				$field[1],
				array( __CLASS__, $field[3] ),
				self::PAGE_SLUG,
				$field[2],
				array(
					'key'       => $field[0],
					'label_for' => 'cinerent-' . $field[0],
				)
			);
		}
	}

	/**
	 * Bereinigt die Eingaben.
	 *
	 * @param mixed $input Rohdaten.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$previous = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		$out      = self::defaults();

		$out['datenquelle']   = ( isset( $input['datenquelle'] ) && 'storybase' === $input['datenquelle'] ) ? 'storybase' : 'local_json';
		$out['storybase_url'] = esc_url_raw( trim( (string) ( $input['storybase_url'] ?? '' ) ), array( 'https' ) );

		// Leeres Feld behält den gespeicherten Schlüssel, "Löschen" entfernt ihn.
		$key                  = trim( (string) ( $input['storybase_key'] ?? '' ) );
		$out['storybase_key'] = ! empty( $input['storybase_key_loeschen'] ) ? '' : ( '' !== $key ? sanitize_text_field( $key ) : (string) $previous['storybase_key'] );

		$email = sanitize_email( (string) ( $input['anfrage_empfaenger'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			add_settings_error( self::OPTION, 'email', __( 'Bitte eine gültige Empfängeradresse angeben.', 'cinerent-shop' ) );
			$email = (string) $previous['anfrage_empfaenger'];
		}
		$out['anfrage_empfaenger'] = $email;

		$out['absender_name']           = sanitize_text_field( (string) ( $input['absender_name'] ?? '' ) );
		$out['mietbedingungen_url']     = esc_url_raw( trim( (string) ( $input['mietbedingungen_url'] ?? '' ) ) );
		$out['datenschutz_url']         = esc_url_raw( trim( (string) ( $input['datenschutz_url'] ?? '' ) ) );
		$out['rate_limit']              = min( 100, max( 1, absint( $input['rate_limit'] ?? 5 ) ) );
		$out['altcha_komplexitaet']     = min( 1000000, max( 1000, absint( $input['altcha_komplexitaet'] ?? 100000 ) ) );
		$out['schriften_laden']         = empty( $input['schriften_laden'] ) ? 0 : 1;
		$out['google_fonts_blockieren'] = empty( $input['google_fonts_blockieren'] ) ? 0 : 1;

		if ( $out['datenquelle'] !== $previous['datenquelle'] ) {
			Plugin::instance()->reset_source();
		}

		return $out;
	}

	/**
	 * Name-Attribut eines Feldes.
	 *
	 * @param string $key Schlüssel.
	 * @return string
	 */
	private static function name( $key ) {
		return self::OPTION . '[' . $key . ']';
	}

	/**
	 * Feld: Datenquelle.
	 *
	 * @param array<string, string> $args Argumente.
	 */
	public static function field_source( $args ) {
		$value = self::get( $args['key'] );
		?>
		<select id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( self::name( $args['key'] ) ); ?>">
			<option value="local_json" <?php selected( $value, 'local_json' ); ?>><?php esc_html_e( 'Lokale JSON-Datei (data/katalog.json)', 'cinerent-shop' ); ?></option>
			<option value="storybase" <?php selected( $value, 'storybase' ); ?>><?php esc_html_e( 'Storybase-API (in Vorbereitung)', 'cinerent-shop' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Die Quelle ist führend. Beim Wechsel bleiben alle Seiten und Vorlagen unverändert, nur die Daten kommen von woanders.', 'cinerent-shop' ); ?></p>
		<?php
	}

	/**
	 * Feld: URL.
	 *
	 * @param array<string, string> $args Argumente.
	 */
	public static function field_url( $args ) {
		printf(
			'<input type="url" class="regular-text" id="%1$s" name="%2$s" value="%3$s">',
			esc_attr( $args['label_for'] ),
			esc_attr( self::name( $args['key'] ) ),
			esc_attr( (string) self::get( $args['key'] ) )
		);
		if ( 'storybase_url' === $args['key'] ) {
			echo '<p class="description">' . esc_html__( 'Basis-URL der Supabase Edge Functions, z. B. https://<projekt>.supabase.co/functions/v1', 'cinerent-shop' ) . '</p>';
		}
		if ( 'datenschutz_url' === $args['key'] ) {
			echo '<p class="description">' . esc_html__( 'Leer lassen, um die WordPress-Datenschutzseite zu verwenden.', 'cinerent-shop' ) . '</p>';
		}
	}

	/**
	 * Feld: Shop-Schlüssel. Wird nie im Klartext ausgegeben.
	 *
	 * @param array<string, string> $args Argumente.
	 */
	public static function field_key( $args ) {
		if ( defined( 'CINERENT_STORYBASE_SHOP_KEY' ) && CINERENT_STORYBASE_SHOP_KEY ) {
			echo '<p>' . esc_html__( 'Der Schlüssel ist in wp-config.php als CINERENT_STORYBASE_SHOP_KEY hinterlegt.', 'cinerent-shop' ) . '</p>';
			return;
		}
		$stored = '' !== (string) self::get( 'storybase_key' );
		printf(
			'<input type="password" class="regular-text" autocomplete="new-password" id="%1$s" name="%2$s" value="" placeholder="%3$s">',
			esc_attr( $args['label_for'] ),
			esc_attr( self::name( $args['key'] ) ),
			esc_attr( $stored ? __( 'gespeichert, leer lassen zum Beibehalten', 'cinerent-shop' ) : '' )
		);
		if ( $stored ) {
			printf(
				' <label><input type="checkbox" name="%s" value="1"> %s</label>',
				esc_attr( self::name( 'storybase_key_loeschen' ) ),
				esc_html__( 'Schlüssel löschen', 'cinerent-shop' )
			);
		}
		echo '<p class="description">' . esc_html__( 'Wird nur serverseitig verwendet und nie an den Browser ausgeliefert. Sicherer: Konstante CINERENT_STORYBASE_SHOP_KEY in wp-config.php.', 'cinerent-shop' ) . '</p>';
	}

	/**
	 * Feld: E-Mail.
	 *
	 * @param array<string, string> $args Argumente.
	 */
	public static function field_email( $args ) {
		printf(
			'<input type="email" class="regular-text" id="%1$s" name="%2$s" value="%3$s" required>',
			esc_attr( $args['label_for'] ),
			esc_attr( self::name( $args['key'] ) ),
			esc_attr( (string) self::get( $args['key'] ) )
		);
	}

	/**
	 * Feld: Text.
	 *
	 * @param array<string, string> $args Argumente.
	 */
	public static function field_text( $args ) {
		printf(
			'<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s">',
			esc_attr( $args['label_for'] ),
			esc_attr( self::name( $args['key'] ) ),
			esc_attr( (string) self::get( $args['key'] ) )
		);
	}

	/**
	 * Feld: Zahl.
	 *
	 * @param array<string, string> $args Argumente.
	 */
	public static function field_number( $args ) {
		printf(
			'<input type="number" class="small-text" min="1" id="%1$s" name="%2$s" value="%3$d">',
			esc_attr( $args['label_for'] ),
			esc_attr( self::name( $args['key'] ) ),
			(int) self::get( $args['key'] )
		);
		if ( 'altcha_komplexitaet' === $args['key'] ) {
			echo '<p class="description">' . esc_html__( 'Höchstzahl für den Proof-of-Work. Höher = mehr Rechenaufwand für Bots (und Besucher). Standard: 100000.', 'cinerent-shop' ) . '</p>';
		}
	}

	/**
	 * Feld: Checkbox.
	 *
	 * @param array<string, string> $args Argumente.
	 */
	public static function field_checkbox( $args ) {
		printf(
			'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s>',
			esc_attr( $args['label_for'] ),
			esc_attr( self::name( $args['key'] ) ),
			checked( (int) self::get( $args['key'] ), 1, false )
		);
		if ( 'schriften_laden' === $args['key'] ) {
			echo '<p class="description">' . esc_html__( 'Bindet Schibsted Grotesk und IBM Plex Mono aus dem Plugin-Ordner ein und stellt sie in Elementor zur Auswahl. Kein externer Abruf.', 'cinerent-shop' ) . '</p>';
		}
		if ( 'google_fonts_blockieren' === $args['key'] ) {
			echo '<p class="description">' . esc_html__( 'Verhindert, dass Elementor Schriften von Google-Servern nachlädt.', 'cinerent-shop' ) . '</p>';
		}
	}

	/**
	 * Seite rendern.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$last = get_option( Sync::STATUS_OPTION );
		$next = wp_next_scheduled( Plugin::CRON_HOOK );
		?>
		<div class="wrap cinerent-admin">
			<h1><?php esc_html_e( 'CineRent Shop', 'cinerent-shop' ); ?></h1>

			<div class="cinerent-admin__sync">
				<h2><?php esc_html_e( 'Katalog-Sync', 'cinerent-shop' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: %s: Name der Datenquelle. */
						esc_html__( 'Aktive Quelle: %s', 'cinerent-shop' ),
						'<strong>' . esc_html( Plugin::instance()->source()->get_label() ) . '</strong>'
					);
					?>
				</p>
				<?php if ( is_array( $last ) ) : ?>
					<p>
						<?php
						printf(
							/* translators: 1: Datum, 2: Ergebnis. */
							esc_html__( 'Letzter Sync: %1$s – %2$s', 'cinerent-shop' ),
							esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last['zeit'] ) ),
							esc_html( Sync::describe( $last ) )
						);
						?>
					</p>
				<?php endif; ?>
				<?php if ( $next ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: Datum und Uhrzeit. */
							esc_html__( 'Nächster automatischer Sync: %s (stündlich per WP-Cron)', 'cinerent-shop' ),
							esc_html( wp_date( get_option( 'time_format' ), $next ) )
						);
						?>
					</p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cinerent_sync">
					<?php wp_nonce_field( 'cinerent_sync' ); ?>
					<?php submit_button( __( 'Jetzt synchronisieren', 'cinerent-shop' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'cinerent_shop' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
