<?php
/*
Plugin Name: Weather Shortcode Plugin
Description: Show weather details in a widget
Version: 0.17
*/

add_action('wp_enqueue_scripts', 'peter_add_stylesheet');
add_shortcode('weather-shortcode', 'weather_shortcode_func');

/** * Add stylesheet to the page*/
function peter_add_stylesheet()
{
	wp_enqueue_style('prefix-style', plugins_url('css/styles.css', __FILE__), false, '0.17');
}

function degrees_to_directional($deg)
{
	if (!is_numeric($deg)) {
		return;
	} else if ($deg < 11.25) {
		return 'N';
	} else if ($deg < 33.75) {
		return 'NNE';
	} else if ($deg < 56.25) {
		return 'NE';
	} else if ($deg < 78.75) {
		return 'ENE';
	} else if ($deg < 101.25) {
		return 'E';
	} else if ($deg < 123.75) {
		return 'ESE';
	} else if ($deg < 146.25) {
		return 'SE';
	} else if ($deg < 168.75) {
		return 'SSE';
	} else if ($deg < 191.25) {
		return 'S';
	} else if ($deg < 213.75) {
		return 'SSW';
	} else if ($deg < 236.25) {
		return 'SW';
	} else if ($deg < 258.75) {
		return 'WSW';
	} else if ($deg < 281.25) {
		return 'W';
	} else if ($deg < 303.75) {
		return 'WNW';
	} else if ($deg < 326.25) {
		return 'NW';
	} else if ($deg < 348.75) {
		return 'NNW';
	} else {
		return 'N';
	}
}

/**
 * API Request Caching
 *
 *  Use server-side caching to store API request's as JSON at a set 
 *  interval, rather than each pageload.
 * 
 * @arg Argument description and usage info
 */
function json_cached_api_results($cache_file = NULL, $expires = NULL, $a)
{
	global $request_type, $purge_cache, $limit_reached, $request_limit;

	if (!$cache_file) $cache_file = dirname(__FILE__) . '/api-cache.json';
	if (!$expires) $expires = time() - 180 * 60; // 3 hours

	if (!file_exists($cache_file)) die("Cache file is missing: $cache_file");

	// Check that the file is older than the expire time and that it's not empty
	if (filectime($cache_file) < $expires || file_get_contents($cache_file)  == '' || $purge_cache && intval($_SESSION['views']) <= $request_limit) {

		// File is too old, refresh cache
		$api_results = curl_weather_json($a);
		$json_results = json_encode($api_results);

		// Remove cache file on error to avoid writing wrong xml
		if ($api_results && $json_results)
			file_put_contents($cache_file, $json_results);
		else
			unlink($cache_file);
	} else {
		// Check for the number of purge cache requests to avoid abuse
		if (intval($_SESSION['views']) >= $request_limit)
			$limit_reached = " <span class='error'>Request limit reached ($request_limit). Please try purging the cache later.</span>";
		// Fetch cache
		$json_results = file_get_contents($cache_file);
		$request_type = 'JSON';
	}

	return json_decode($json_results);
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
	$weatherJson = json_cached_api_results(NULL, NULL, $a);

	$output = "<div class='peter-weather-widget'>";
	$output .= "<h3 class='weather-title'>Current weather at " . $a['locationname'] . "</h3>";
	$output .= "<img src='" . plugin_dir_url(__FILE__) . "icons/" .
		$weatherJson->current->weather[0]->icon . "@2x.png' class='weather-icon current' />";
	$output .= "<div>";
	$output .= "<div class='flex-table'><span class='flex-row header'>TEMP</span><span class='flex-row day'>" .
		round($weatherJson->current->temp) . "&deg;F</span></div>";
	$output .= "<div class='flex-table'><span class='flex-row header'>WEATHER</span><span class='flex-row day'>" .
		$weatherJson->current->weather[0]->description . "</span></div>";
	$output .= "<div class='flex-table'><span class='flex-row header'>WIND</span><span class='flex-row day'>" .
		round($weatherJson->current->wind_speed) .
		" MPH, " . degrees_to_directional($weatherJson->current->wind_deg) . "</span></div>";
	$output .= "</div>";


	$output .= "<hr>";

	foreach ($weatherJson->daily as $day) {
		$output .= "<div class='day'>";
		$output .= "<img src='" . plugin_dir_url(__FILE__) . "icons/" .
			$day->weather[0]->icon . "@2x.png' class='weather-icon' />";
		$output .= "<h5 class='day-heading'>" . date('l', $day->dt) . " forecast</h5>";
		$output .= "<div class='flex-table'><span class='flex-row header'>TEMP</span><span class='flex-row day'>" .
			round($day->temp->day) . "&deg;F</span></div>";
		$output .= "<div class='flex-table'><span class='flex-row header'>WEATHER</span><span class='flex-row day'>" .
			$day->weather[0]->description . "</span></div>";
		$output .= "<div class='flex-table'><span class='flex-row header'>WIND</span><span class='flex-row day'>" .
			round($day->wind_speed) .
			" MPH, " . degrees_to_directional($day->wind_deg) . "</span></div>";
		$output .= "<hr></div>";
	}
	$output .= "</div>";

	return $output;
}

function curl_weather_json($a)
{
	// This is where you run the code and display the output
	$curl = curl_init();
	$url = "https://api.openweathermap.org/data/3.0/onecall?units=imperial&lat=" .
		$a['lat'] . "&lon=" . $a['lon'] . "&appid=" . $a['appid'] .
		"&exclude=minutely,hourly";
	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "GET"
	));
	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);

	if ($err) {
		return "cURL Error #:" . $err;
	} else {
		// The API returns data in JSON format, so convert that to a data object.
		return json_decode($response);
	}
}
