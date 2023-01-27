<?php

/**
 * Map degrees to cardinal direction
 */
function degrees_to_directional($deg)
{
	if (!is_numeric($deg)) return;
	else if ($deg < 11.25) return 'N';
	else if ($deg < 33.75) return 'NNE';
	else if ($deg < 56.25) return 'NE';
	else if ($deg < 78.75) return 'ENE';
	else if ($deg < 101.25) return 'E';
	else if ($deg < 123.75) return 'ESE';
	else if ($deg < 146.25) return 'SE';
	else if ($deg < 168.75) return 'SSE';
	else if ($deg < 191.25) return 'S';
	else if ($deg < 213.75) return 'SSW';
	else if ($deg < 236.25) return 'SW';
	else if ($deg < 258.75) return 'WSW';
	else if ($deg < 281.25) return 'W';
	else if ($deg < 303.75) return 'WNW';
	else if ($deg < 326.25) return 'NW';
	else if ($deg < 348.75) return 'NNW';
	else return 'N';
}

/**
 * Map weather condition codes to 2 digit weaather icon code
 */
function find_icon($code)
{
	$code = intval($code);
	if (!is_numeric($code)) return '01';
	else if ($code === 511) return '13';
	else if ($code === 800) return '01';
	else if ($code < 299) 	return '11';
	else if ($code < 522)	return '09';
	else if ($code < 599) 	return '10';
	else if ($code < 699) 	return '13';
	else if ($code < 799) 	return '50';
	else if ($code < 803) 	return '02';
	else return '05';
}

/**
 * Get the weather data from the OpenWeather Onecall service.
 */
function curl_weather_json($a)
{
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

/**
 * API Request Caching
 *
 * Use server-side caching to store API request's as JSON at a set 
 * interval, rather than each pageload.
 * 
 * @arg Argument description and usage info
 */
function json_cached_api_results($cache_file = NULL, $expires = NULL, $a)
{
	if (!$cache_file) $cache_file = dirname(__FILE__) . '/api-cache.json';
	if (!$expires) $expires = time() - 180; // 3 minutes

	if (!file_exists($cache_file)) die("Cache file is missing: $cache_file");

	// Check that the file is older than the expire time and that it's not empty
	if (filectime($cache_file) < $expires || file_get_contents($cache_file)  == '') {

		// File is too old, refresh cache
		$api_results = curl_weather_json($a);
		$json_results = json_encode($api_results);

		// Remove cache file on error to avoid writing wrong xml
		if ($api_results && $json_results)
			file_put_contents($cache_file, $json_results);
		else
			unlink($cache_file);
	} else {
		// Fetch cache
		$json_results = file_get_contents($cache_file);
	}

	return json_decode($json_results);
}
