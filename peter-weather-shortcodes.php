<?php
/*
Plugin Name: Peter's Weather Shortcodes
Description: Show weather details in a widget
Version: 0.21
*/

require 'functions.php';

add_action('wp_enqueue_scripts', 'peter_add_stylesheet');
add_shortcode('weather-shortcode', 'weather_shortcode_func');
add_shortcode('small-craft-advisory', 'small_craft_advisory_shortcode_func');

/** * Add stylesheet to the page*/
function peter_add_stylesheet()
{
	wp_enqueue_style('prefix-style', plugins_url('css/styles.css', __FILE__), false, '0.21');
}


function weather_shortcode_func($atts)
{
	$a = shortcode_atts(array(
		'lon' => '',
		'lat' => '',
		'appid' => '',
		'locationname' => ''
	), $atts);

	$nullValue = false;
	foreach ($a as $key => $value) {
		if (empty($value)) {
			$nullValue = true;
			break;
		}
	}
	if ($nullValue) {
		return 'Add lat, lon, and OpenWeather API key to weather shortcode';
	}
	$weatherJson = json_cached_api_results(NULL, 300, $a);
	ob_start();
?>
	<div class='peter-weather-widget'>
		<h3 class='weather-title'>Current weather at <?= $a['locationname'] ?></h3>
		<p class="weather-period">updated every 5 minutes</p>
		<img src="<?= plugin_dir_url(__FILE__) ?>icons/<?= esc_attr(find_icon($weatherJson->current->weather[0]->id)) ?>.png" class="weather-icon current" />
		<div>
			<div class='flex-table'><span class='flex-row header'>TEMP</span><span class='flex-row day'>
					<?= esc_html(round($weatherJson->current->temp)) ?>&deg;F</span></div>
			<div class='flex-table'><span class='flex-row header'>WEATHER</span><span class='flex-row day'>
					<?= esc_html($weatherJson->current->weather[0]->description) ?></span></div>
			<div class='flex-table'>
				<span class='flex-row header'>WIND</span>
				<span class='flex-row day'>
					<?= esc_html(round($weatherJson->current->wind_speed)) ?> MPH,
					<?= esc_html(degrees_to_directional($weatherJson->current->wind_deg)) ?>
				</span>
			</div>
		</div>
		<hr>
		<?php foreach ($weatherJson->daily as $day) { ?>
			<div class="day">
				<img src="<?= plugin_dir_url(__FILE__) ?>icons/<?= esc_attr(find_icon($day->weather[0]->id)) ?>.png" class="weather-icon" />
				<h5 class="day-heading"><?= date('l', $day->dt) ?> forecast</h5>
				<div class="flex-table"><span class="flex-row header">TEMP</span>
					<span class="flex-row day">
						<?= esc_html(round($day->temp->day)) ?>&deg;F
					</span>
				</div>
				<div class="flex-table"><span class="flex-row header">WEATHER</span>
					<span class="flex-row day">
						<?= esc_html($day->weather[0]->description) ?></span>
				</div>
				<div class="flex-table"><span class="flex-row header">WIND</span>
					<span class="flex-row day">
						<?= esc_html(round($day->wind_speed)) ?> MPH,
						<?= esc_html(degrees_to_directional($day->wind_deg)) ?>
					</span>
				</div>
				<hr>
			</div>
		<?php } // end foreach loop 
		?>
	</div>

	<?php return ob_get_clean();
}


function small_craft_advisory_shortcode_func()
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
		if ($alert->event !== 'Small Craft Advisory') {
			continue;
		}
		/**
		 * Use regex to format the Advisory with paragraphs and bullet points.
		 */
		$description = preg_replace('/\n/', ' ', $alert->description);
		$description = preg_replace('/\*/', '</p><p>&bull; ', $description);

		ob_start(); ?>
		<article class='post-entry warning'>
			<h2 class='post-title warning'><?= esc_html($alert->event) ?></h2>
			<p><?= wp_kses($description, array('p' => array())) ?></p>
		</article>
<?php
	}
	return ob_get_clean();
}
