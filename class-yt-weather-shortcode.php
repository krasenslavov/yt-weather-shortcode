<?php
/**
 * Plugin Name: YT Weather Shortcode
 * Plugin URI: https://github.com/krasenslavov/yt-weather-shortcode
 * Description: Display current weather for any location using Open-Meteo API. Includes caching, Celsius/Fahrenheit toggle, and customizable display.
 * Version: 1.0.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Krasen Slavov
 * Author URI: https://krasenslavov.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: yt-weather-shortcode
 * Domain Path: /languages
 *
 * @package YT_Weather_Shortcode
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'YT_WEATHER_VERSION', '1.0.0' );

/**
 * Plugin base name.
 */
define( 'YT_WEATHER_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin directory path.
 */
define( 'YT_WEATHER_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'YT_WEATHER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class for Weather Shortcode.
 *
 * @since 1.0.0
 */
class YT_Weather_Shortcode {

	/**
	 * Single instance of the class.
	 *
	 * @var YT_Weather_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * Plugin options.
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Geocoding API endpoint.
	 *
	 * @var string
	 */
	private $geocoding_api = 'https://geocoding-api.open-meteo.com/v1/search';

	/**
	 * Weather API endpoint.
	 *
	 * @var string
	 */
	private $weather_api = 'https://api.open-meteo.com/v1/forecast';

	/**
	 * Get single instance of the class.
	 *
	 * @return YT_Weather_Shortcode
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->options = get_option( 'yt_weather_options', $this->get_default_options() );
		$this->init_hooks();
	}

	/**
	 * Get default plugin options.
	 *
	 * @return array
	 */
	private function get_default_options() {
		return array(
			'default_unit'   => 'celsius',
			'cache_duration' => 3600,
			'default_city'   => 'London',
			'show_icon'      => true,
			'show_wind'      => true,
			'show_humidity'  => true,
			'widget_style'   => 'card',
		);
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Load plugin text domain.
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Register shortcode.
		add_shortcode( 'weather', array( $this, 'render_weather_shortcode' ) );

		// Admin hooks.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_filter( 'plugin_action_links_' . YT_WEATHER_BASENAME, array( $this, 'add_action_links' ) );

			// AJAX handlers for admin.
			add_action( 'wp_ajax_yt_weather_clear_cache', array( $this, 'ajax_clear_cache' ) );
			add_action( 'wp_ajax_yt_weather_test_api', array( $this, 'ajax_test_api' ) );
		}

		// Frontend hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Cleanup old transients.
		add_action( 'wp_scheduled_delete', array( $this, 'cleanup_old_transients' ) );
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'yt-weather-shortcode',
			false,
			dirname( YT_WEATHER_BASENAME ) . '/languages'
		);
	}

	/**
	 * Enqueue frontend scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_enqueue_style(
			'yt-weather-style',
			YT_WEATHER_URL . 'assets/css/yt-weather-shortcode.css',
			array(),
			YT_WEATHER_VERSION
		);

		wp_enqueue_script(
			'yt-weather-script',
			YT_WEATHER_URL . 'assets/js/yt-weather-shortcode.js',
			array( 'jquery' ),
			YT_WEATHER_VERSION,
			true
		);

		wp_localize_script(
			'yt-weather-script',
			'ytWeatherData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Add plugin admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Weather Shortcode Settings', 'yt-weather-shortcode' ),
			__( 'Weather Shortcode', 'yt-weather-shortcode' ),
			'manage_options',
			'yt-weather-shortcode',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'yt_weather_options_group',
			'yt_weather_options',
			array( $this, 'sanitize_options' )
		);

		add_settings_section(
			'yt_weather_main_section',
			__( 'Default Settings', 'yt-weather-shortcode' ),
			array( $this, 'render_section_info' ),
			'yt-weather-shortcode'
		);

		add_settings_field(
			'default_city',
			__( 'Default City', 'yt-weather-shortcode' ),
			array( $this, 'render_default_city_field' ),
			'yt-weather-shortcode',
			'yt_weather_main_section'
		);

		add_settings_field(
			'default_unit',
			__( 'Default Temperature Unit', 'yt-weather-shortcode' ),
			array( $this, 'render_unit_field' ),
			'yt-weather-shortcode',
			'yt_weather_main_section'
		);

		add_settings_field(
			'cache_duration',
			__( 'Cache Duration (seconds)', 'yt-weather-shortcode' ),
			array( $this, 'render_cache_field' ),
			'yt-weather-shortcode',
			'yt_weather_main_section'
		);

		add_settings_field(
			'display_options',
			__( 'Display Options', 'yt-weather-shortcode' ),
			array( $this, 'render_display_options_field' ),
			'yt-weather-shortcode',
			'yt_weather_main_section'
		);

		add_settings_field(
			'widget_style',
			__( 'Widget Style', 'yt-weather-shortcode' ),
			array( $this, 'render_style_field' ),
			'yt-weather-shortcode',
			'yt_weather_main_section'
		);
	}

	/**
	 * Sanitize plugin options.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public function sanitize_options( $input ) {
		$sanitized = array();

		$sanitized['default_city'] = isset( $input['default_city'] )
			? sanitize_text_field( $input['default_city'] )
			: 'London';

		$sanitized['default_unit'] = isset( $input['default_unit'] ) && in_array( $input['default_unit'], array( 'celsius', 'fahrenheit' ), true )
			? $input['default_unit']
			: 'celsius';

		$sanitized['cache_duration'] = isset( $input['cache_duration'] )
			? absint( $input['cache_duration'] )
			: 3600;

		// Ensure cache duration is at least 300 seconds (5 minutes).
		$sanitized['cache_duration'] = max( 300, $sanitized['cache_duration'] );

		$sanitized['show_icon']     = isset( $input['show_icon'] ) ? (bool) $input['show_icon'] : true;
		$sanitized['show_wind']     = isset( $input['show_wind'] ) ? (bool) $input['show_wind'] : true;
		$sanitized['show_humidity'] = isset( $input['show_humidity'] ) ? (bool) $input['show_humidity'] : true;

		$sanitized['widget_style'] = isset( $input['widget_style'] ) && in_array( $input['widget_style'], array( 'card', 'minimal', 'detailed' ), true )
			? $input['widget_style']
			: 'card';

		return $sanitized;
	}

	/**
	 * Render settings section information.
	 *
	 * @return void
	 */
	public function render_section_info() {
		echo '<p>' . esc_html__( 'Configure default settings for the weather shortcode.', 'yt-weather-shortcode' ) . '</p>';
	}

	/**
	 * Render default city field.
	 *
	 * @return void
	 */
	public function render_default_city_field() {
		$value = isset( $this->options['default_city'] ) ? $this->options['default_city'] : 'London';
		?>
		<input type="text"
			name="yt_weather_options[default_city]"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text" />
		<p class="description">
			<?php esc_html_e( 'Default city to display when no city is specified in shortcode.', 'yt-weather-shortcode' ); ?>
		</p>
		<?php
	}

	/**
	 * Render temperature unit field.
	 *
	 * @return void
	 */
	public function render_unit_field() {
		$value = isset( $this->options['default_unit'] ) ? $this->options['default_unit'] : 'celsius';
		?>
		<label>
			<input type="radio"
				name="yt_weather_options[default_unit]"
				value="celsius"
				<?php checked( $value, 'celsius' ); ?> />
			<?php esc_html_e( 'Celsius (°C)', 'yt-weather-shortcode' ); ?>
		</label>
		<br>
		<label>
			<input type="radio"
				name="yt_weather_options[default_unit]"
				value="fahrenheit"
				<?php checked( $value, 'fahrenheit' ); ?> />
			<?php esc_html_e( 'Fahrenheit (°F)', 'yt-weather-shortcode' ); ?>
		</label>
		<?php
	}

	/**
	 * Render cache duration field.
	 *
	 * @return void
	 */
	public function render_cache_field() {
		$value = isset( $this->options['cache_duration'] ) ? $this->options['cache_duration'] : 3600;
		?>
		<input type="number"
			name="yt_weather_options[cache_duration]"
			value="<?php echo esc_attr( $value ); ?>"
			min="300"
			step="60"
			class="small-text" />
		<p class="description">
			<?php esc_html_e( 'How long to cache weather data (minimum 300 seconds). Default: 3600 (1 hour).', 'yt-weather-shortcode' ); ?>
		</p>
		<?php
	}

	/**
	 * Render display options checkboxes.
	 *
	 * @return void
	 */
	public function render_display_options_field() {
		$show_icon     = isset( $this->options['show_icon'] ) ? $this->options['show_icon'] : true;
		$show_wind     = isset( $this->options['show_wind'] ) ? $this->options['show_wind'] : true;
		$show_humidity = isset( $this->options['show_humidity'] ) ? $this->options['show_humidity'] : true;
		?>
		<label style="display: block; margin-bottom: 5px;">
			<input type="checkbox"
				name="yt_weather_options[show_icon]"
				value="1"
				<?php checked( $show_icon, true ); ?> />
			<?php esc_html_e( 'Show weather icon', 'yt-weather-shortcode' ); ?>
		</label>
		<label style="display: block; margin-bottom: 5px;">
			<input type="checkbox"
				name="yt_weather_options[show_wind]"
				value="1"
				<?php checked( $show_wind, true ); ?> />
			<?php esc_html_e( 'Show wind speed', 'yt-weather-shortcode' ); ?>
		</label>
		<label style="display: block; margin-bottom: 5px;">
			<input type="checkbox"
				name="yt_weather_options[show_humidity]"
				value="1"
				<?php checked( $show_humidity, true ); ?> />
			<?php esc_html_e( 'Show humidity', 'yt-weather-shortcode' ); ?>
		</label>
		<?php
	}

	/**
	 * Render widget style field.
	 *
	 * @return void
	 */
	public function render_style_field() {
		$value = isset( $this->options['widget_style'] ) ? $this->options['widget_style'] : 'card';
		?>
		<select name="yt_weather_options[widget_style]">
			<option value="card" <?php selected( $value, 'card' ); ?>>
				<?php esc_html_e( 'Card (Default)', 'yt-weather-shortcode' ); ?>
			</option>
			<option value="minimal" <?php selected( $value, 'minimal' ); ?>>
				<?php esc_html_e( 'Minimal', 'yt-weather-shortcode' ); ?>
			</option>
			<option value="detailed" <?php selected( $value, 'detailed' ); ?>>
				<?php esc_html_e( 'Detailed', 'yt-weather-shortcode' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Choose the display style for weather widgets.', 'yt-weather-shortcode' ); ?>
		</p>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'yt-weather-shortcode' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'yt_weather_options_group' );
				do_settings_sections( 'yt-weather-shortcode' );
				submit_button();
				?>
			</form>

			<hr>

			<div class="yt-weather-admin-tools">
				<h2><?php esc_html_e( 'Tools', 'yt-weather-shortcode' ); ?></h2>

				<p>
					<button type="button" id="yt-weather-clear-cache" class="button">
						<?php esc_html_e( 'Clear All Weather Cache', 'yt-weather-shortcode' ); ?>
					</button>
					<span id="yt-weather-clear-cache-result"></span>
				</p>

				<p>
					<button type="button" id="yt-weather-test-api" class="button">
						<?php esc_html_e( 'Test API Connection', 'yt-weather-shortcode' ); ?>
					</button>
					<span id="yt-weather-test-api-result"></span>
				</p>
			</div>

			<hr>

			<div class="yt-weather-usage">
				<h2><?php esc_html_e( 'Shortcode Usage', 'yt-weather-shortcode' ); ?></h2>

				<h3><?php esc_html_e( 'Basic Usage', 'yt-weather-shortcode' ); ?></h3>
				<code>[weather]</code>
				<p><?php esc_html_e( 'Uses default city from settings.', 'yt-weather-shortcode' ); ?></p>

				<h3><?php esc_html_e( 'Specify City', 'yt-weather-shortcode' ); ?></h3>
				<code>[weather city="New York"]</code>

				<h3><?php esc_html_e( 'Custom Temperature Unit', 'yt-weather-shortcode' ); ?></h3>
				<code>[weather city="Tokyo" unit="celsius"]</code>
				<code>[weather city="Chicago" unit="fahrenheit"]</code>

				<h3><?php esc_html_e( 'Custom Style', 'yt-weather-shortcode' ); ?></h3>
				<code>[weather city="Paris" style="minimal"]</code>
				<code>[weather city="Berlin" style="detailed"]</code>

				<h3><?php esc_html_e( 'All Parameters', 'yt-weather-shortcode' ); ?></h3>
				<code>[weather city="London" unit="celsius" style="card"]</code>
			</div>

			<style>
				.yt-weather-admin-tools {
					background: #fff;
					border: 1px solid #ccd0d4;
					padding: 20px;
					margin-top: 20px;
				}
				.yt-weather-usage {
					background: #fff;
					border: 1px solid #ccd0d4;
					padding: 20px;
					margin-top: 20px;
				}
				.yt-weather-usage code {
					display: block;
					background: #f0f0f1;
					padding: 10px;
					margin: 10px 0;
					border-left: 3px solid #2271b1;
				}
			</style>

			<script>
			jQuery(document).ready(function($) {
				$('#yt-weather-clear-cache').on('click', function() {
					var $button = $(this);
					var $result = $('#yt-weather-clear-cache-result');

					$button.prop('disabled', true).text('<?php esc_html_e( 'Clearing...', 'yt-weather-shortcode' ); ?>');
					$result.html('');

					$.post(ajaxurl, {
						action: 'yt_weather_clear_cache',
						nonce: '<?php echo esc_js( wp_create_nonce( 'yt_weather_clear_cache' ) ); ?>'
					}, function(response) {
						if (response.success) {
							$result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
						} else {
							$result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
						}
						$button.prop('disabled', false).text('<?php esc_html_e( 'Clear All Weather Cache', 'yt-weather-shortcode' ); ?>');
					});
				});

				$('#yt-weather-test-api').on('click', function() {
					var $button = $(this);
					var $result = $('#yt-weather-test-api-result');

					$button.prop('disabled', true).text('<?php esc_html_e( 'Testing...', 'yt-weather-shortcode' ); ?>');
					$result.html('');

					$.post(ajaxurl, {
						action: 'yt_weather_test_api',
						nonce: '<?php echo esc_js( wp_create_nonce( 'yt_weather_test_api' ) ); ?>'
					}, function(response) {
						if (response.success) {
							$result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
						} else {
							$result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
						}
						$button.prop('disabled', false).text('<?php esc_html_e( 'Test API Connection', 'yt-weather-shortcode' ); ?>');
					});
				});
			});
			</script>
		</div>
		<?php
	}

	/**
	 * AJAX handler to clear weather cache.
	 *
	 * @return void
	 */
	public function ajax_clear_cache() {
		check_ajax_referer( 'yt_weather_clear_cache', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'yt-weather-shortcode' ) ) );
		}

		$this->cleanup_old_transients( true );

		wp_send_json_success( array( 'message' => __( 'Weather cache cleared successfully.', 'yt-weather-shortcode' ) ) );
	}

	/**
	 * AJAX handler to test API connection.
	 *
	 * @return void
	 */
	public function ajax_test_api() {
		check_ajax_referer( 'yt_weather_test_api', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'yt-weather-shortcode' ) ) );
		}

		$test_city = 'London';
		$coords    = $this->get_coordinates( $test_city );

		if ( ! $coords ) {
			wp_send_json_error( array( 'message' => __( 'Failed to connect to geocoding API.', 'yt-weather-shortcode' ) ) );
		}

		$weather = $this->fetch_weather( $coords['latitude'], $coords['longitude'], 'celsius' );

		if ( ! $weather ) {
			wp_send_json_error( array( 'message' => __( 'Failed to connect to weather API.', 'yt-weather-shortcode' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: City name */
					__( 'API connection successful! Test weather: %s', 'yt-weather-shortcode' ),
					$test_city . ' ' . round( $weather['temperature'] ) . '°C'
				),
			)
		);
	}

	/**
	 * Render weather shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_weather_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'city'  => $this->options['default_city'],
				'unit'  => $this->options['default_unit'],
				'style' => $this->options['widget_style'],
			),
			$atts,
			'weather'
		);

		$city  = sanitize_text_field( $atts['city'] );
		$unit  = in_array( $atts['unit'], array( 'celsius', 'fahrenheit' ), true ) ? $atts['unit'] : 'celsius';
		$style = in_array( $atts['style'], array( 'card', 'minimal', 'detailed' ), true ) ? $atts['style'] : 'card';

		// Get weather data.
		$weather = $this->get_weather_data( $city, $unit );

		if ( ! $weather ) {
			return '<div class="yt-weather-error">' . esc_html__( 'Unable to fetch weather data.', 'yt-weather-shortcode' ) . '</div>';
		}

		// Render based on style.
		return $this->render_weather_widget( $weather, $city, $unit, $style );
	}

	/**
	 * Get weather data with caching.
	 *
	 * @param string $city City name.
	 * @param string $unit Temperature unit.
	 * @return array|false Weather data or false on failure.
	 */
	private function get_weather_data( $city, $unit ) {
		$cache_key = 'yt_weather_' . md5( $city . $unit );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Get coordinates.
		$coords = $this->get_coordinates( $city );

		if ( ! $coords ) {
			return false;
		}

		// Fetch weather.
		$weather = $this->fetch_weather( $coords['latitude'], $coords['longitude'], $unit );

		if ( ! $weather ) {
			return false;
		}

		// Cache the result.
		set_transient( $cache_key, $weather, $this->options['cache_duration'] );

		return $weather;
	}

	/**
	 * Get coordinates for a city using geocoding API.
	 *
	 * @param string $city City name.
	 * @return array|false Array with latitude and longitude or false.
	 */
	private function get_coordinates( $city ) {
		$url = add_query_arg(
			array(
				'name'   => rawurlencode( $city ),
				'count'  => 1,
				'format' => 'json',
			),
			$this->geocoding_api
		);

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['results'][0] ) ) {
			return false;
		}

		return array(
			'latitude'  => $data['results'][0]['latitude'],
			'longitude' => $data['results'][0]['longitude'],
			'name'      => $data['results'][0]['name'],
			'country'   => $data['results'][0]['country'] ?? '',
		);
	}

	/**
	 * Fetch weather data from API.
	 *
	 * @param float  $latitude  Latitude.
	 * @param float  $longitude Longitude.
	 * @param string $unit      Temperature unit.
	 * @return array|false Weather data or false.
	 */
	private function fetch_weather( $latitude, $longitude, $unit ) {
		$temp_unit = 'celsius' === $unit ? 'celsius' : 'fahrenheit';

		$url = add_query_arg(
			array(
				'latitude'         => $latitude,
				'longitude'        => $longitude,
				'current_weather'  => 'true',
				'temperature_unit' => $temp_unit,
			),
			$this->weather_api
		);

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['current_weather'] ) ) {
			return false;
		}

		$current = $data['current_weather'];

		return array(
			'temperature'   => $current['temperature'],
			'windspeed'     => $current['windspeed'],
			'winddirection' => $current['winddirection'],
			'weathercode'   => $current['weathercode'],
			'time'          => $current['time'],
		);
	}

	/**
	 * Render weather widget HTML.
	 *
	 * @param array  $weather Weather data.
	 * @param string $city    City name.
	 * @param string $unit    Temperature unit.
	 * @param string $style   Widget style.
	 * @return string HTML output.
	 */
	private function render_weather_widget( $weather, $city, $unit, $style ) {
		$temp_symbol  = 'celsius' === $unit ? '°C' : '°F';
		$weather_desc = $this->get_weather_description( $weather['weathercode'] );
		$weather_icon = $this->get_weather_icon( $weather['weathercode'] );

		$output = '<div class="yt-weather-widget yt-weather-' . esc_attr( $style ) . '">';

		if ( 'minimal' === $style ) {
			$output .= $this->render_minimal_widget( $weather, $city, $temp_symbol, $weather_icon, $weather_desc );
		} elseif ( 'detailed' === $style ) {
			$output .= $this->render_detailed_widget( $weather, $city, $temp_symbol, $weather_icon, $weather_desc );
		} else {
			$output .= $this->render_card_widget( $weather, $city, $temp_symbol, $weather_icon, $weather_desc );
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Render card style widget.
	 *
	 * @param array  $weather      Weather data.
	 * @param string $city         City name.
	 * @param string $temp_symbol  Temperature symbol.
	 * @param string $weather_icon Weather icon.
	 * @param string $weather_desc Weather description.
	 * @return string HTML output.
	 */
	private function render_card_widget( $weather, $city, $temp_symbol, $weather_icon, $weather_desc ) {
		$output  = '<div class="yt-weather-header">';
		$output .= '<h3 class="yt-weather-city">' . esc_html( $city ) . '</h3>';
		$output .= '</div>';

		$output .= '<div class="yt-weather-body">';

		if ( $this->options['show_icon'] ) {
			$output .= '<div class="yt-weather-icon">' . $weather_icon . '</div>';
		}

		$output .= '<div class="yt-weather-temp">' . round( $weather['temperature'] ) . '<span class="yt-weather-unit">' . esc_html( $temp_symbol ) . '</span></div>';
		$output .= '<div class="yt-weather-desc">' . esc_html( $weather_desc ) . '</div>';

		$output .= '<div class="yt-weather-details">';

		if ( $this->options['show_wind'] ) {
			$output .= '<div class="yt-weather-detail">';
			$output .= '<span class="yt-weather-detail-label">' . esc_html__( 'Wind', 'yt-weather-shortcode' ) . '</span>';
			$output .= '<span class="yt-weather-detail-value">' . round( $weather['windspeed'] ) . ' km/h</span>';
			$output .= '</div>';
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Render minimal style widget.
	 *
	 * @param array  $weather      Weather data.
	 * @param string $city         City name.
	 * @param string $temp_symbol  Temperature symbol.
	 * @param string $weather_icon Weather icon.
	 * @param string $weather_desc Weather description.
	 * @return string HTML output.
	 */
	private function render_minimal_widget( $weather, $city, $temp_symbol, $weather_icon, $weather_desc ) {
		$output  = '<span class="yt-weather-city">' . esc_html( $city ) . '</span>';
		$output .= '<span class="yt-weather-temp">' . round( $weather['temperature'] ) . esc_html( $temp_symbol ) . '</span>';

		if ( $this->options['show_icon'] ) {
			$output .= '<span class="yt-weather-icon">' . $weather_icon . '</span>';
		}

		return $output;
	}

	/**
	 * Render detailed style widget.
	 *
	 * @param array  $weather      Weather data.
	 * @param string $city         City name.
	 * @param string $temp_symbol  Temperature symbol.
	 * @param string $weather_icon Weather icon.
	 * @param string $weather_desc Weather description.
	 * @return string HTML output.
	 */
	private function render_detailed_widget( $weather, $city, $temp_symbol, $weather_icon, $weather_desc ) {
		$output = $this->render_card_widget( $weather, $city, $temp_symbol, $weather_icon, $weather_desc );

		// Add additional details for detailed style.
		$output .= '<div class="yt-weather-extra">';
		$output .= '<div class="yt-weather-detail">';
		$output .= '<span class="yt-weather-detail-label">' . esc_html__( 'Wind Direction', 'yt-weather-shortcode' ) . '</span>';
		$output .= '<span class="yt-weather-detail-value">' . $this->get_wind_direction( $weather['winddirection'] ) . '</span>';
		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Get weather description from weather code.
	 *
	 * @param int $code Weather code.
	 * @return string Weather description.
	 */
	private function get_weather_description( $code ) {
		$descriptions = array(
			0  => __( 'Clear sky', 'yt-weather-shortcode' ),
			1  => __( 'Mainly clear', 'yt-weather-shortcode' ),
			2  => __( 'Partly cloudy', 'yt-weather-shortcode' ),
			3  => __( 'Overcast', 'yt-weather-shortcode' ),
			45 => __( 'Foggy', 'yt-weather-shortcode' ),
			48 => __( 'Depositing rime fog', 'yt-weather-shortcode' ),
			51 => __( 'Light drizzle', 'yt-weather-shortcode' ),
			53 => __( 'Moderate drizzle', 'yt-weather-shortcode' ),
			55 => __( 'Dense drizzle', 'yt-weather-shortcode' ),
			61 => __( 'Slight rain', 'yt-weather-shortcode' ),
			63 => __( 'Moderate rain', 'yt-weather-shortcode' ),
			65 => __( 'Heavy rain', 'yt-weather-shortcode' ),
			71 => __( 'Slight snow', 'yt-weather-shortcode' ),
			73 => __( 'Moderate snow', 'yt-weather-shortcode' ),
			75 => __( 'Heavy snow', 'yt-weather-shortcode' ),
			77 => __( 'Snow grains', 'yt-weather-shortcode' ),
			80 => __( 'Slight rain showers', 'yt-weather-shortcode' ),
			81 => __( 'Moderate rain showers', 'yt-weather-shortcode' ),
			82 => __( 'Violent rain showers', 'yt-weather-shortcode' ),
			85 => __( 'Slight snow showers', 'yt-weather-shortcode' ),
			86 => __( 'Heavy snow showers', 'yt-weather-shortcode' ),
			95 => __( 'Thunderstorm', 'yt-weather-shortcode' ),
			96 => __( 'Thunderstorm with slight hail', 'yt-weather-shortcode' ),
			99 => __( 'Thunderstorm with heavy hail', 'yt-weather-shortcode' ),
		);

		return $descriptions[ $code ] ?? __( 'Unknown', 'yt-weather-shortcode' );
	}

	/**
	 * Get weather icon emoji from weather code.
	 *
	 * @param int $code Weather code.
	 * @return string Weather icon.
	 */
	private function get_weather_icon( $code ) {
		if ( 0 === $code || 1 === $code ) {
			return '☀️';
		} elseif ( 2 === $code || 3 === $code ) {
			return '⛅';
		} elseif ( $code >= 45 && $code <= 48 ) {
			return '🌫️';
		} elseif ( $code >= 51 && $code <= 65 ) {
			return '🌧️';
		} elseif ( $code >= 71 && $code <= 77 ) {
			return '❄️';
		} elseif ( $code >= 80 && $code <= 82 ) {
			return '🌦️';
		} elseif ( $code >= 85 && $code <= 86 ) {
			return '🌨️';
		} elseif ( $code >= 95 && $code <= 99 ) {
			return '⛈️';
		}

		return '🌡️';
	}

	/**
	 * Get wind direction from degrees.
	 *
	 * @param int $degrees Wind direction in degrees.
	 * @return string Wind direction.
	 */
	private function get_wind_direction( $degrees ) {
		$directions = array( 'N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW' );
		$index      = round( $degrees / 45 ) % 8;
		return $directions[ $index ];
	}

	/**
	 * Cleanup old weather transients.
	 *
	 * @param bool $all Delete all weather transients.
	 * @return void
	 */
	public function cleanup_old_transients( $all = false ) {
		global $wpdb;

		if ( $all ) {
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_yt_weather_%' OR option_name LIKE '_transient_timeout_yt_weather_%'" );
		} else {
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_yt_weather_%' AND option_value < UNIX_TIMESTAMP()" );
		}
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Existing action links.
	 * @return array Modified action links.
	 */
	public function add_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=yt-weather-shortcode' ) ),
			esc_html__( 'Settings', 'yt-weather-shortcode' )
		);

		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Plugin activation hook.
	 *
	 * @return void
	 */
	public static function activate() {
		$default_options = array(
			'default_unit'   => 'celsius',
			'cache_duration' => 3600,
			'default_city'   => 'London',
			'show_icon'      => true,
			'show_wind'      => true,
			'show_humidity'  => true,
			'widget_style'   => 'card',
		);

		if ( ! get_option( 'yt_weather_options' ) ) {
			add_option( 'yt_weather_options', $default_options );
		}
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Cleanup transients on deactivation.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_yt_weather_%' OR option_name LIKE '_transient_timeout_yt_weather_%'" );
	}
}

/**
 * Plugin uninstall hook.
 *
 * @return void
 */
function yt_weather_uninstall() {
	delete_option( 'yt_weather_options' );

	// Clean up transients.
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_yt_weather_%' OR option_name LIKE '_transient_timeout_yt_weather_%'" );

	wp_cache_flush();
}

// Register activation hook.
register_activation_hook( __FILE__, array( 'YT_Weather_Shortcode', 'activate' ) );

// Register deactivation hook.
register_deactivation_hook( __FILE__, array( 'YT_Weather_Shortcode', 'deactivate' ) );

// Register uninstall hook.
register_uninstall_hook( __FILE__, 'yt_weather_uninstall' );

// Initialize the plugin.
add_action( 'plugins_loaded', array( 'YT_Weather_Shortcode', 'get_instance' ) );
