<?php
/*
Plugin Name: Peter's Weather Shortcodes
Description: Show weather details in a widget
Version: 0.21
*/

require 'functions.php';

class PeterWeatherShortcodes
{
	public function __construct()
	{
		add_action('wp_enqueue_scripts', array($this, 'weather_add_stylesheet'));
		add_shortcode('weather-shortcode', array($this, 'weather_shortcode_func'));
		add_shortcode('small-craft-advisory', array($this, 'weather_sca_func'));
	}

	/**  
	 * Add stylesheet to the page
	 */
	public function weather_add_stylesheet(): void
	{
		wp_enqueue_style('prefix-style', plugins_url('css/styles.css', __FILE__), false, '0.21');
	}

	/**
	 * The output for the weather shortcode.
	 */
	public function weather_shortcode_func($atts): string
	{
		$a = shortcode_atts(array(
			'lon' => '',
			'lat' => '',
			'appid' => '',
			'locationname' => ''
		), $atts);

		/**
		 * Check for empty attributes.
		 */
		$nullValue = false;
		foreach ($a as $key => $value) {
			if (empty($value)) {
				$nullValue = true;
				break;
			}
		}
		if ($nullValue) return 'Add lat, lon, OpenWeather API key, and location name to weather shortcode';
		$weatherJson = json_cached_api_results(NULL, 300, $a);

		$output  = '<div class="peter-weather-widget">';
		$output .= '<h3 class="weather-title">Current weather at ' . $a['locationname'] . '</h3>';
		$output .= '<p class="weather-period">updated every 5 minutes</p>';
		$output .= '<img src="' . plugin_dir_url(__FILE__) . 'icons/' . esc_attr(find_icon($weatherJson->current->weather[0]->id)) . '.png" class="weather-icon current" />';
		$output .= '<div>';
		$output .= '<div class="flex-table"><span class="flex-row header">TEMP</span><span class="flex-row day">';
		$output .= esc_html(round($weatherJson->current->temp)) . '&deg;F</span></div>';
		$output .= '<div class="flex-table"><span class="flex-row header">WEATHER</span><span class="flex-row day">';
		$output .= esc_html($weatherJson->current->weather[0]->description) . '</span></div>';
		$output .= '<div class="flex-table"><span class="flex-row header">WIND</span>';
		$output .= '<span class="flex-row day">' . esc_html(round($weatherJson->current->wind_speed)) . ' MPH,';
		$output .= esc_html(degrees_to_directional($weatherJson->current->wind_deg)) . '</span></div></div><hr>';

		foreach ($weatherJson->daily as $day) {
			$output .= '<div class="day">';
			$output .= '<img src="' . plugin_dir_url(__FILE__) . 'icons/' . esc_attr(find_icon($day->weather[0]->id)) . '.png" class="weather-icon" />';
			$output .= '<h5 class="day-heading">' . date("l", $day->dt) . ' forecast</h5>';
			$output .= '<div class="flex-table"><span class="flex-row header">TEMP</span>';
			$output .= '<span class="flex-row day">';
			$output .= esc_html(round($day->temp->day)) . '&deg;F';
			$output .= '</span></div>';
			$output .= '<div class="flex-table"><span class="flex-row header">WEATHER</span>';
			$output .= '<span class="flex-row day">' . esc_html($day->weather[0]->description) . '</span>';
			$output .= '</div><div class="flex-table"><span class="flex-row header">WIND</span>';
			$output .= '<span class="flex-row day">' . esc_html(round($day->wind_speed)) . 'MPH,';
			$output .= esc_html(degrees_to_directional($day->wind_deg));
			$output .= '</span></div><hr></div>';
		} // end foreach loop 
		$output .= '</div>';

		return $output;
	}

	public function weather_sca_func(): string
	{
		$cache_file = dirname(__FILE__) . '/api-cache.json';
		if (!file_exists($cache_file)) {
			return "";
		}

		$results = file_get_contents($cache_file);
		$json_results = json_decode($results);

		if (!isset($json_results->alerts)) {
			return "";
		}

		foreach ($json_results->alerts as $alert) {
			if ($alert->event === 'Small Craft Advisory' || $alert->event === 'Gale Watch') {
				/**
				 * Use regex to format the Advisory with paragraphs and bullet points.
				 */
				$description = preg_replace('/\n/', ' ', $alert->description);
				$description = preg_replace('/\*/', '</li><li>', $description);

				$output  = '<article class="post-entry warning">';
				$output .= '<h2 class="post-title warning">' . esc_html($alert->event) . '</h2>';
				$output .= '<ul><li>' . wp_kses($description, array('li' => array())) . '</li></ul>';
				$output .= '</article>';
			} else {
				$output = '';
			}
		}
		return $output;
	}
}

$peterWeatherShortcodes = new PeterWeatherShortcodes();
