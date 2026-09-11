=== Test Plugin for WP.org Submission Rules ===
Contributors: thetwopct
Tested up to: 6.8
Stable tag: 1.0
License: GPLv2 or later

Readme for test-plugin.php, used by the ExternalServices.Disclosure sniff.

== Description ==

This file contains deliberate omissions to test the sniffs.

== External services ==

= OpenWeather =
This plugin connects to the OpenWeather API (api.openweathermap.org) to show weather forecasts. The configured location is sent every time the widget is loaded.
[Terms of use](https://openweather.co.uk/storage/app/media/Terms/terms.pdf) | [Privacy policy](https://openweather.co.uk/privacy-policy)

= Mailgun =
Emails are sent through Mailgun. (No terms or privacy links, so the sniff warns.)

== Changelog ==

= 1.0 =
* Initial release.
