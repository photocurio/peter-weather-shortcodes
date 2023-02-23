<?php

require_once ABSPATH . 'wp-admin/includes/file.php';

/**
 * The class that instantiates the plugin.
 */
class PeterWeatherShortcodes
{
    /**
     * Construct the plugin: enqueue stylesheet and register two shortcodes.
     */
    public function __construct()
    {
        WP_Filesystem();
        add_action('wp_enqueue_scripts', array( $this, 'weatherAddStylesheet' ));
        add_shortcode('weather-shortcode', array( $this, 'weatherShortcodeFunc' ));
        add_shortcode('small-craft-advisory', array( $this, 'weatherWarningFunc' ));
    }

    /**
     * Add stylesheet to the page.
     */
    public function weatherAddStylesheet(): void
    {
        wp_enqueue_style('peter-weather-style', plugins_url('css/styles.css', __FILE__), false, '0.4');
    }

    /**
     * The output for the weather shortcode.
     */
    public function weatherShortcodeFunc(array $atts): string
    {
        $params = shortcode_atts(
            array(
                'lon'          => '',
                'lat'          => '',
                'appid'        => '',
                'locationname' => '',
            ),
            $atts
        );

        // Check for empty attributes in $params.
        if (in_array('', $params, true)) {
            return 'Add lat, lon, appid (OpenWeather API key), and locationname to weather shortcode';
        }

        $weather_json = $this->jsonCachedApiResults($params);

        if (! $weather_json) {
            return 'Something went wrong: we could not fetch the weather data.';
        }

        $current = $weather_json->current;
        // An output buffer doesn't work here. We have to concatenate a string.
        // First, setup the current weather state.
        $output  = '<div class="peter-weather-widget">';
        $output .= '<h3 class="weather-title">Current weather at ' . $params['locationname'] . '</h3>';
        $output .= '<p class="weather-period">updated every 5 minutes</p>';
        $output .= '<img src="' . plugin_dir_url(__FILE__) . 'icons/';
        $output .= esc_attr($this->findIcon($current->weather[0]->id));
        $output .= '.png" class="weather-icon current" />';
        $output .= '<div>';
        $output .= '<div class="flex-table"><span class="flex-row header">TEMP</span><span class="flex-row day">';
        $output .= esc_html(round($current->temp)) . '&deg;F</span></div>';
        $output .= '<div class="flex-table"><span class="flex-row header">WEATHER</span><span class="flex-row day">';
        $output .= esc_html($current->weather[0]->description) . '</span></div>';
        $output .= '<div class="flex-table"><span class="flex-row header">WIND</span>';
        $output .= '<span class="flex-row day">' . esc_html(round($current->wind_speed)) . ' MPH, ';
        $output .= esc_html($this->degreesToDirectional($current->wind_deg)) . '</span></div></div><hr>';
        // Second, loop through the forecasts and add them to the output.
        foreach ($weather_json->daily as $key => $day) {
            $output .= '<div class="day">';
            $output .= '<img src="' . plugin_dir_url(__FILE__) . 'icons/';
            $output .= esc_attr($this->findIcon($day->weather[0]->id));
            $output .= '.png" class="weather-icon" alt="' . $day->weather[0]->description . '" />';
            $output .= '<h5 class="day-heading">' . wp_date('l', $day->dt) . ' forecast</h5>';
            $output .= '<div class="flex-table"><span class="flex-row header">TEMP</span>';
            $output .= '<span class="flex-row day">';
            $output .= esc_html(round($day->temp->day)) . '&deg;F';
            $output .= '</span></div>';
            $output .= '<div class="flex-table"><span class="flex-row header">WEATHER</span>';
            $output .= '<span class="flex-row day">' . esc_html($day->weather[0]->description) . '</span>';
            $output .= '</div><div class="flex-table"><span class="flex-row header">WIND</span>';
            $output .= '<span class="flex-row day">' . esc_html(round($day->wind_speed)) . 'MPH, ';
            $output .= esc_html($this->degreesToDirectional($day->wind_deg));
            $output .= '</span></div><hr></div>';
        } // End the foreach loop.
        // Add the updated time.
        $output .= '<p class="weather-update">updated ';
        $output .= esc_html(wp_date('j F Y g:i A', $current->dt)) . '</p>';
        $output .= '</div>';

        return $output;
    }

    /**
     * A sortcode that echos Weather Warnings and Small Craft Advisories.
     */
    public function weatherWarningFunc(): string
    {
        global $wp_filesystem;

        $cache_file = dirname(__FILE__) . '/api-cache.json';

        if (! $wp_filesystem->exists($cache_file)) {
            return '';
        }

        $results      = $wp_filesystem->get_contents($cache_file);
        $json_results = json_decode($results);

        if (! isset($json_results->alerts)) {
            return '';
        }
        foreach ($json_results->alerts as $alert) {
            if (
                str_contains($alert->event, 'Small Craft') ||
                str_contains($alert->event, 'Gale') ||
                str_contains($alert->event, 'Storm') ||
                str_contains($alert->event, 'Hurricane') ||
                str_contains($alert->event, 'Dense Fog') ||
                str_contains($alert->event, 'Thunderstorm')
            ) {
                // Use regex to format the Advisory with list items.
                $description = preg_replace('/\n/', ' ', $alert->description);
                $description = preg_replace('/\*/', '</li><li>', $description);

                // concatenate the output.
                $output  = '<article class="post-entry warning">';
                $output .= '<h2 class="post-title warning">' . esc_html($alert->event) . '</h2>';
                $output .= '<ul><li>' . wp_kses_post($description) . '</li></ul>';
                $output .= '</article>';
                // The data sometimes includes duplicate or redundant alerts,
                // so just output the first match.
                // There might be a better way to handle this.
                return $output;
            }
        }
        return '';
    }

    /**
     * Map degrees to cardinal direction
     */
    private function degreesToDirectional(int $deg): string
    {
        if (! is_numeric($deg)) {
            return '';
        } elseif ($deg < 11) {
            return 'N';
        } elseif ($deg < 34) {
            return 'NNE';
        } elseif ($deg < 56) {
            return 'NE';
        } elseif ($deg < 79) {
            return 'ENE';
        } elseif ($deg < 101) {
            return 'E';
        } elseif ($deg < 124) {
            return 'ESE';
        } elseif ($deg < 146) {
            return 'SE';
        } elseif ($deg < 169) {
            return 'SSE';
        } elseif ($deg < 191) {
            return 'S';
        } elseif ($deg < 214) {
            return 'SSW';
        } elseif ($deg < 236) {
            return 'SW';
        } elseif ($deg < 259) {
            return 'WSW';
        } elseif ($deg < 281) {
            return 'W';
        } elseif ($deg < 304) {
            return 'WNW';
        } elseif ($deg < 326) {
            return 'NW';
        } elseif ($deg < 349) {
            return 'NNW';
        } else {
            return 'N';
        }
    }

    /**
     * Map weather condition codes to 2 digit weaather icon code
     */
    private function findIcon(int $weather_code): string
    {
        if (! is_numeric($weather_code)) {
            return '01';
        } elseif (511 === $weather_code) {
            return '13';
        } elseif (800 === $weather_code) {
            return '01';
        } elseif ($weather_code < 299) {
            return '11';
        } elseif ($weather_code < 502) {
            return '09';
        } elseif ($weather_code < 599) {
            return '10';
        } elseif ($weather_code < 699) {
            return '13';
        } elseif ($weather_code < 799) {
            return '50';
        } elseif ($weather_code < 803) {
            return '02';
        } else {
            return '05';
        }
    }

    /**
     * API Request Caching
     *
     * Use server-side caching to store API requests rather than request for each page view.
     */
    private function jsonCachedApiResults(array $params): object
    {
        global $wp_filesystem;

        $cache_file  = dirname(__FILE__) . '/api-cache.json';
        $mtime       = $wp_filesystem->mtime($cache_file);
        $expire_time = time() - 300; // 5 minutes.

        if (! $wp_filesystem->exists($cache_file)) {
            die(esc_html("Cache file is missing: $cache_file"));
        }

        // Check that the file is older than the expire time and that it's not empty.
        if ($mtime < $expire_time || $wp_filesystem->get_contents($cache_file) === '') {
            // File is too old, or empty. Refresh cache.
            $url         = 'https://api.openweathermap.org/data/3.0/onecall';
            $url        .= '?&exclude=minutely,hourly&units=imperial&lat=';
            $url        .= $params['lat'] . '&lon=' . $params['lon'] . '&appid=' . $params['appid'];
            $api_results = wp_remote_get($url);

            // Wipe cache file on error to avoid writing bad data.
            if ($api_results && isset($api_results['body'])) {
                $json_data = $api_results['body'];
                $wp_filesystem->put_contents($cache_file, $json_data);
                return json_decode($json_data);
            } else {
                $wp_filesystem->put_contents($cache_file, '');
                return 'There was an error getting the weather data';
            }
        } else {
            // Fetch cache.
            $json_results = $wp_filesystem->get_contents($cache_file);
        }

        return json_decode($json_results);
    }
}
