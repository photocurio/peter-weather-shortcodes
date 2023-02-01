<?php

/**
 * Map degrees to cardinal direction
 */
function degrees_to_directional(int $deg)
{
	if (!is_numeric($deg)) return;
	else if ($deg < 11) return 'N';
	else if ($deg < 34) return 'NNE';
	else if ($deg < 56) return 'NE';
	else if ($deg < 79) return 'ENE';
	else if ($deg < 101) return 'E';
	else if ($deg < 124) return 'ESE';
	else if ($deg < 146) return 'SE';
	else if ($deg < 169) return 'SSE';
	else if ($deg < 191) return 'S';
	else if ($deg < 214) return 'SSW';
	else if ($deg < 236) return 'SW';
	else if ($deg < 259) return 'WSW';
	else if ($deg < 281) return 'W';
	else if ($deg < 304) return 'WNW';
	else if ($deg < 326) return 'NW';
	else if ($deg < 349) return 'NNW';
	else return 'N';
}

/**
 * Map weather condition codes to 2 digit weaather icon code
 */
function find_icon(int $code)
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
function curl_weather_json(array $a)
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
function json_cached_api_results(string $cache_file = NULL, int $expires = NULL, array $a)
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
