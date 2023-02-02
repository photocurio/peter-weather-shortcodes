<?php
/**
 * Plugin Name: Peter's Weather Shortcodes
 * Description: Show weather forcast in a sidebar widget. Also displays warning on top of the Posts feed.
 * Version: 0.30
 * Author: Peter Mumford
 *
 * @package peter-weather-shortcodes
 */

declare(strict_types=1);

require 'functions.php';

/**
 * The class that instantiates the plugin
 */
class PeterWeatherShortcodes {

	/**
	 * Construct the plugin: enqueue stylesheet and register two shortcodes.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'weather_add_stylesheet' ) );
		add_shortcode( 'weather-shortcode', array( $this, 'weather_shortcode_func' ) );
		add_shortcode( 'small-craft-advisory', array( $this, 'weather_sca_func' ) );
	}

	/**
	 * Add stylesheet to the page
	 */
	public function weather_add_stylesheet(): void {
		wp_enqueue_style( 'prefix-style', plugins_url( 'css/styles.css', __FILE__ ), false, '0.30' );
	}

	/**
	 * The output for the weather shortcode.
	 *
	 * @param array $atts Attributes passed to the weather shortcode as an array.
	 * These are the query params for the AJAX request for weather data.
	 */
	public function weather_shortcode_func( array $atts ): string {
		$a = shortcode_atts(
			array(
				'lon'          => '',
				'lat'          => '',
				'appid'        => '',
				'locationname' => '',
			),
			$atts
		);

		/**
		 * Check for empty attributes.
		 */
		$null_value = false;
		foreach ( $a as $key => $value ) {
			if ( empty( $value ) ) {
				$null_value = true;
				break;
			}
		}
		if ( $null_value ) {
			return 'Add lat, lon, OpenWeather API key, and location name to weather shortcode';
		}
		$weather_json = json_cached_api_results( null, 300, $a );
		/**
		 * Using an output buffer to assemble the markup doesn't work in a class.
		 * We have to concatenate a string.
		 */
		$output  = '<div class="peter-weather-widget">';
		$output .= '<h3 class="weather-title">Current weather at ' . $a['locationname'] . '</h3>';
		$output .= '<p class="weather-period">updated every 5 minutes</p>';
		$output .= '<img src="' . plugin_dir_url( __FILE__ ) . 'icons/';
		$output .= esc_attr( find_icon( $weather_json->current->weather[0]->id ) );
		$output .= '.png" class="weather-icon current" />';
		$output .= '<div>';
		$output .= '<div class="flex-table"><span class="flex-row header">TEMP</span><span class="flex-row day">';
		$output .= esc_html( round( $weather_json->current->temp ) ) . '&deg;F</span></div>';
		$output .= '<div class="flex-table"><span class="flex-row header">WEATHER</span><span class="flex-row day">';
		$output .= esc_html( $weather_json->current->weather[0]->description ) . '</span></div>';
		$output .= '<div class="flex-table"><span class="flex-row header">WIND</span>';
		$output .= '<span class="flex-row day">' . esc_html( round( $weather_json->current->wind_speed ) ) . ' MPH, ';
		$output .= esc_html( degrees_to_directional( $weather_json->current->wind_deg ) ) . '</span></div></div><hr>';

		foreach ( $weather_json->daily as $day ) {
			$output .= '<div class="day">';
			$output .= '<img src="' . plugin_dir_url( __FILE__ ) . 'icons/' . esc_attr( find_icon( $day->weather[0]->id ) );
			$output .= '.png" class="weather-icon" />';
			$output .= '<h5 class="day-heading">' . date( 'l', $day->dt ) . ' forecast</h5>';
			$output .= '<div class="flex-table"><span class="flex-row header">TEMP</span>';
			$output .= '<span class="flex-row day">';
			$output .= esc_html( round( $day->temp->day ) ) . '&deg;F';
			$output .= '</span></div>';
			$output .= '<div class="flex-table"><span class="flex-row header">WEATHER</span>';
			$output .= '<span class="flex-row day">' . esc_html( $day->weather[0]->description ) . '</span>';
			$output .= '</div><div class="flex-table"><span class="flex-row header">WIND</span>';
			$output .= '<span class="flex-row day">' . esc_html( round( $day->wind_speed ) ) . 'MPH, ';
			$output .= esc_html( degrees_to_directional( $day->wind_deg ) );
			$output .= '</span></div><hr></div>';
		} // end foreach loop
		$output .= '<p class="weather-update">last updated ';
		$output .= wp_date( 'j F Y g:i A', $weather_json->current->dt ) . '</p>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * A sortcode that echos Weather Warnings and Small Craft Advisories.
	 */
	public function weather_sca_func(): string {
		WP_Filesystem();
		global $wp_filesystem;

		$cache_file = dirname( __FILE__ ) . '/api-cache.json';
		if ( ! file_exists( $cache_file ) ) {
			return '';
		}

		$results      = $wp_filesystem->get_contents( $cache_file );
		$json_results = json_decode( $results );

		if ( ! isset( $json_results->alerts ) ) {
			return '';
		}

		foreach ( $json_results->alerts as $alert ) {
			if (
				str_contains( $alert->event, 'Small Craft' ) ||
				str_contains( $alert->event, 'Gale' ) ||
				str_contains( $alert->event, 'Storm' ) ||
				str_contains( $alert->event, 'Hurricane' ) ||
				str_contains( $alert->event, 'Dense Fog' ) ||
				str_contains( $alert->event, 'Thunderstorm' )
			) {
				/**
				 * Use regex to format the Advisory with paragraphs and list items.
				 */
				$description = preg_replace( '/\n/', ' ', $alert->description );
				$description = preg_replace( '/\*/', '</li><li>', $description );

				$output  = '<article class="post-entry warning">';
				$output .= '<h2 class="post-title warning">' . esc_html( $alert->event ) . '</h2>';
				$output .= '<ul><li>' . wp_kses_post( $description ) . '</li></ul>';
				$output .= '</article>';
			} else {
				$output = '';
			}
		}
		return $output;
	}
}

$peter_weather_shortcodes = new PeterWeatherShortcodes();
