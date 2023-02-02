<?php

declare(strict_types=1);

/**
 * Map degrees to cardinal direction
 *
 * @param int $deg A compass bearing 0-365.
 */
function degrees_to_directional( int $deg ): string {
	if ( ! is_numeric( $deg ) ) {
		return '';
	} elseif ( $deg < 11 ) {
		return 'N';
	} elseif ( $deg < 34 ) {
		return 'NNE';
	} elseif ( $deg < 56 ) {
		return 'NE';
	} elseif ( $deg < 79 ) {
		return 'ENE';
	} elseif ( $deg < 101 ) {
		return 'E';
	} elseif ( $deg < 124 ) {
		return 'ESE';
	} elseif ( $deg < 146 ) {
		return 'SE';
	} elseif ( $deg < 169 ) {
		return 'SSE';
	} elseif ( $deg < 191 ) {
		return 'S';
	} elseif ( $deg < 214 ) {
		return 'SSW';
	} elseif ( $deg < 236 ) {
		return 'SW';
	} elseif ( $deg < 259 ) {
		return 'WSW';
	} elseif ( $deg < 281 ) {
		return 'W';
	} elseif ( $deg < 304 ) {
		return 'WNW';
	} elseif ( $deg < 326 ) {
		return 'NW';
	} elseif ( $deg < 349 ) {
		return 'NNW';
	} else {
		return 'N';
	}
}

/**
 * Map weather condition codes to 2 digit weaather icon code
 *
 * @param int $weather_code numerical code that refers to the generalized weather condition.
 */
function find_icon( int $weather_code ): string {
	if ( ! is_numeric( $weather_code ) ) {
		return '01';
	} elseif ( 511 === $weather_code ) {
		return '13';
	} elseif ( 800 === $weather_code ) {
		return '01';
	} elseif ( $weather_code < 299 ) {
		return '11';
	} elseif ( $weather_code < 522 ) {
		return '09';
	} elseif ( $weather_code < 599 ) {
		return '10';
	} elseif ( $weather_code < 699 ) {
		return '13';
	} elseif ( $weather_code < 799 ) {
		return '50';
	} elseif ( $weather_code < 803 ) {
		return '02';
	} else {
		return '05';
	}
}

/**
 * API Request Caching
 *
 * Use server-side caching to store API requests
 * rather than request for each page view.
 *
 * @param string $cache_file the files that holds the cached data.
 * @param int    $expires number of seconds that the cache is valid.
 * @param array  $params array of params to pass to the data endpoint. These params come from the shortcode args.
 */
function json_cached_api_results( string $cache_file = null, int $expires = null, array $params ): object {
	if ( ! $cache_file ) {
		$cache_file = dirname( __FILE__ ) . '/api-cache.json';
	}
	if ( ! $expires ) {
		$expires = time() - 180; // 3 minutes.
	}

	if ( ! file_exists( $cache_file ) ) {
		die( esc_html( "Cache file is missing: $cache_file" ) );
	}

	// Check that the file is older than the expire time and that it's not empty.
	if ( filectime( $cache_file ) < $expires || file_get_contents( $cache_file ) === '' ) {
		// File is too old, refresh cache.
		$url         = 'https://api.openweathermap.org/data/3.0/onecall?units=imperial&lat=';
		$url        .= $params['lat'] . '&lon=' . $params['lon'] . '&appid=' . $params['appid'] . '&exclude=minutely,hourly';
		$api_results = wp_remote_get( $url );
		$json_data   = $api_results['body'];

		// Remove cache file on error to avoid writing bad data.
		if ( $api_results && isset( $api_results['body'] ) ) {
			file_put_contents( $cache_file, $json_data );
			return json_decode( $json_data );
		} else {
			unlink( $cache_file );
		}
		return 'There was an error getting the weather data';
	} else {
		// Fetch cache.
		$json_results = file_get_contents( $cache_file );
	}

	return json_decode( $json_results );
}
