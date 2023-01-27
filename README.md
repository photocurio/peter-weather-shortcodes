# Weather Shortcodes for WordPress

This WordPress plugin registers two shortcodes to display weather information on a WordPress site. The weather information is designed with boaters and sailers in mind, and shows wind velocity and direction. Small Craft Advisories are also displayed.

It requires a OpenWeather account, and the OpenWeather API key needs to be is in the appid parameter of the shortcode. 
The first shortcode displays current weather information, updated every 5 minutes, and forecasts for the week. It is designed to display in a sidebar. This shortcode is: `[weather-shortcode appid=openweather-api-key lat=42.5349984 lon=-70.8720089 locationname='Salem Willows']`

The second shortcode will display a Small Craft Advisory on the top of a posts page, if such an advisory is in effect. It requires the Weather Shortcode to be active for it to work. The shortcode is: `[small-craft-advisory]`. This does not need an API key, as long as the Weather Shortcode is active somewhere on the site.
