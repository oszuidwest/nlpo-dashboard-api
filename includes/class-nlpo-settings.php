<?php
/**
 * NLPO Settings
 *
 * @package NLPO_API
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page for NLPO API plugin.
 *
 * Provides an admin interface for configuring Plausible Analytics
 * credentials and API authentication settings.
 */
final class NLPO_Settings {

	/**
	 * Option name for storing all settings.
	 */
	public const string OPTION_NAME = 'nlpo_settings';

	/**
	 * Settings page slug.
	 */
	private const string PAGE_SLUG = 'nlpo-settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', $this->add_settings_page( ... ) );
		add_action( 'admin_init', $this->register_settings( ... ) );
		add_action( 'admin_notices', $this->display_configuration_notices( ... ) );
	}

	/**
	 * Displays admin notices for missing configuration.
	 *
	 * Uses wp_admin_notice() introduced in WordPress 6.4.
	 *
	 * @return void
	 */
	public function display_configuration_notices(): void {
		// Only show on NLPO settings page or plugins page.
		$screen = get_current_screen();
		if ( ! $screen instanceof WP_Screen ) {
			return;
		}

		$is_nlpo_page = 'settings_page_' . self::PAGE_SLUG === $screen->id;
		$is_plugins   = 'plugins' === $screen->id;

		if ( ! $is_nlpo_page && ! $is_plugins ) {
			return;
		}

		$missing = $this->get_missing_configuration();

		if ( [] === $missing ) {
			return;
		}

		$message = sprintf(
			/* translators: 1: list of missing settings, 2: settings page URL */
			__( 'NLPO API: The following settings are not configured: %1$s. <a href="%2$s">Configure now</a>.', 'nlpo-api' ),
			implode( ', ', $missing ),
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
		);

		wp_admin_notice(
			$message,
			[
				'type'               => 'warning',
				'dismissible'        => true,
				'additional_classes' => [ 'nlpo-config-notice' ],
			],
		);
	}

	/**
	 * Gets list of missing required configuration.
	 *
	 * @return string[] List of missing setting labels.
	 */
	private function get_missing_configuration(): array {
		$missing  = [];
		$required = [
			'api_token'          => __( 'Endpoint Token', 'nlpo-api' ),
			'plausible_base_url' => __( 'Plausible Base URL', 'nlpo-api' ),
			'plausible_site_id'  => __( 'Plausible Site ID', 'nlpo-api' ),
			'plausible_token'    => __( 'Plausible API Token', 'nlpo-api' ),
		];

		foreach ( $required as $key => $label ) {
			$value = self::get( $key );
			if ( '' === $value || 0 === $value ) {
				$missing[] = $label;
			}
		}

		return $missing;
	}

	/**
	 * Adds the settings page to the WordPress admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'NLPO API Settings', 'nlpo-api' ),
			__( 'NLPO API', 'nlpo-api' ),
			'manage_options',
			self::PAGE_SLUG,
			$this->render_settings_page( ... ),
		);
	}

	/**
	 * Registers the settings and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::PAGE_SLUG,
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => $this->sanitize_settings( ... ),
				'default'           => [],
			],
		);

		add_settings_section(
			'nlpo_plausible_section',
			__( 'Plausible Analytics', 'nlpo-api' ),
			$this->render_plausible_section( ... ),
			self::PAGE_SLUG,
		);

		add_settings_section(
			'nlpo_api_section',
			__( 'API Settings', 'nlpo-api' ),
			$this->render_api_section( ... ),
			self::PAGE_SLUG,
		);

		$this->add_field( 'plausible_base_url', __( 'Plausible Base URL', 'nlpo-api' ), 'nlpo_plausible_section', 'url' );
		$this->add_field( 'plausible_site_id', __( 'Site ID', 'nlpo-api' ), 'nlpo_plausible_section', 'text' );
		$this->add_field( 'plausible_token', __( 'API Token', 'nlpo-api' ), 'nlpo_plausible_section', 'password' );
		$this->add_field( 'api_token', __( 'Endpoint Token', 'nlpo-api' ), 'nlpo_api_section', 'password' );
		$this->add_field( 'cache_expiration', __( 'Cache Duration (seconds)', 'nlpo-api' ), 'nlpo_api_section', 'number' );
		$this->add_field( 'debug_mode', __( 'Debug Mode', 'nlpo-api' ), 'nlpo_api_section', 'checkbox' );
	}

	/**
	 * Adds a settings field.
	 *
	 * @param string $id      Field ID.
	 * @param string $label   Field label.
	 * @param string $section Section ID.
	 * @param string $type    Input type.
	 * @return void
	 */
	private function add_field( string $id, string $label, string $section, string $type ): void {
		add_settings_field(
			'nlpo_' . $id,
			$label,
			fn() => $this->render_field( $id, $type ),
			self::PAGE_SLUG,
			$section,
		);
	}

	/**
	 * Renders the Plausible section description.
	 *
	 * @return void
	 */
	public function render_plausible_section(): void {
		echo '<p>' . esc_html__( 'Configure your Plausible Analytics connection for pageview tracking.', 'nlpo-api' ) . '</p>';
	}

	/**
	 * Renders the API section description.
	 *
	 * @return void
	 */
	public function render_api_section(): void {
		echo '<p>' . esc_html__( 'Configure the NLPO API endpoint settings.', 'nlpo-api' ) . '</p>';
	}

	/**
	 * Renders a settings field.
	 *
	 * @param string $id   Field ID.
	 * @param string $type Input type.
	 * @return void
	 */
	private function render_field( string $id, string $type ): void {
		$options = get_option( self::OPTION_NAME, [] );
		$value   = is_array( $options ) && isset( $options[ $id ] ) ? $options[ $id ] : $this->get_default( $id );

		if ( 'checkbox' === $type ) {
			$this->render_checkbox_field( $id, (bool) $value );
			return;
		}

		$attrs = [
			'type'  => $type,
			'id'    => 'nlpo_' . $id,
			'name'  => self::OPTION_NAME . '[' . $id . ']',
			'value' => is_scalar( $value ) ? (string) $value : '',
			'class' => 'regular-text',
		];

		if ( 'number' === $type ) {
			$attrs['min']  = '0';
			$attrs['step'] = '1';
		}

		echo '<input';
		foreach ( $attrs as $attr => $attr_value ) {
			echo ' ' . esc_attr( $attr ) . '="' . esc_attr( $attr_value ) . '"';
		}
		echo ' />';

		$this->render_field_description( $id );
	}

	/**
	 * Renders a checkbox field.
	 *
	 * @param string $id    Field ID.
	 * @param bool   $value Current value.
	 * @return void
	 */
	private function render_checkbox_field( string $id, bool $value ): void {
		?>
		<label for="nlpo_<?php echo esc_attr( $id ); ?>">
			<input
				type="checkbox"
				id="nlpo_<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>"
				value="1"
				<?php checked( $value ); ?>
			/>
			<?php $this->render_checkbox_label( $id ); ?>
		</label>
		<?php
	}

	/**
	 * Renders the checkbox label.
	 *
	 * @param string $id Field ID.
	 * @return void
	 */
	private function render_checkbox_label( string $id ): void {
		$labels = [
			'debug_mode' => __( 'Enable debug logging to PHP error log', 'nlpo-api' ),
		];

		if ( isset( $labels[ $id ] ) ) {
			echo esc_html( $labels[ $id ] );
		}
	}

	/**
	 * Renders the field description.
	 *
	 * @param string $id Field ID.
	 * @return void
	 */
	private function render_field_description( string $id ): void {
		$descriptions = [
			'plausible_base_url' => __( 'The base URL of your Plausible instance (e.g., https://plausible.io/api)', 'nlpo-api' ),
			'plausible_site_id'  => __( 'Your website ID in Plausible Analytics', 'nlpo-api' ),
			'plausible_token'    => __( 'Plausible API token with read access', 'nlpo-api' ),
			'api_token'          => __( 'Token to secure the NLPO endpoint', 'nlpo-api' ),
			'cache_expiration'   => __( 'How long to cache pageview data (default: 3600)', 'nlpo-api' ),
		];

		if ( isset( $descriptions[ $id ] ) ) {
			echo '<p class="description">' . esc_html( $descriptions[ $id ] ) . '</p>';
		}
	}

	/**
	 * Gets the default value for a field.
	 *
	 * @param string $id Field ID.
	 * @return string|int|bool Default value.
	 */
	private function get_default( string $id ): string|int|bool {
		$defaults = [
			'plausible_base_url' => '',
			'plausible_site_id'  => '',
			'plausible_token'    => '',
			'api_token'          => '',
			'cache_expiration'   => 3600,
			'debug_mode'         => false,
		];

		return $defaults[ $id ] ?? '';
	}

	/**
	 * Sanitizes the settings before saving.
	 *
	 * @param mixed $input The input array.
	 * @return array<string, string|int|bool> Sanitized settings.
	 */
	public function sanitize_settings( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		$sanitized = [];

		if ( isset( $input['plausible_base_url'] ) ) {
			$sanitized['plausible_base_url'] = esc_url_raw( rtrim( (string) $input['plausible_base_url'], '/' ) );
		}

		if ( isset( $input['plausible_site_id'] ) ) {
			$sanitized['plausible_site_id'] = sanitize_text_field( (string) $input['plausible_site_id'] );
		}

		if ( isset( $input['plausible_token'] ) ) {
			$sanitized['plausible_token'] = sanitize_text_field( (string) $input['plausible_token'] );
		}

		if ( isset( $input['api_token'] ) ) {
			$sanitized['api_token'] = sanitize_text_field( (string) $input['api_token'] );
		}

		if ( isset( $input['cache_expiration'] ) ) {
			$sanitized['cache_expiration'] = max( 0, (int) $input['cache_expiration'] );
		}

		$sanitized['debug_mode'] = isset( $input['debug_mode'] ) && '1' === $input['debug_mode'];

		return $sanitized;
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE_SLUG );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
			<hr />
			<h2><?php esc_html_e( 'Status', 'nlpo-api' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Cached Pageviews', 'nlpo-api' ); ?></th>
					<td><?php echo esc_html( (string) $this->get_cache_count() ); ?></td>
				</tr>
			</table>
			<hr />
			<h2><?php esc_html_e( 'Endpoint Information', 'nlpo-api' ); ?></h2>
			<p>
				<strong><?php esc_html_e( 'Endpoint URL:', 'nlpo-api' ); ?></strong><br />
				<code><?php echo esc_url( rest_url( 'zw/v1/nlpo' ) ); ?>?token=YOUR_TOKEN&amp;from=YYYY-MM-DD&amp;to=YYYY-MM-DD</code>
			</p>
		</div>
		<?php
	}

	/**
	 * Gets the number of cached pageview entries.
	 *
	 * @return int Number of cached entries.
	 */
	private function get_cache_count(): int {
		$cached = get_transient( 'nlpo_all_pageviews' );

		return is_array( $cached ) ? count( $cached ) : 0;
	}

	/**
	 * Gets a setting value.
	 *
	 * @param string $key Setting key.
	 * @return string|int|bool Setting value or default.
	 */
	public static function get( string $key ): string|int|bool {
		$options  = get_option( self::OPTION_NAME, [] );
		$defaults = [
			'plausible_base_url' => '',
			'plausible_site_id'  => '',
			'plausible_token'    => '',
			'api_token'          => '',
			'cache_expiration'   => 3600,
			'debug_mode'         => false,
		];

		if ( is_array( $options ) && isset( $options[ $key ] ) ) {
			return $options[ $key ];
		}

		return $defaults[ $key ] ?? '';
	}
}
