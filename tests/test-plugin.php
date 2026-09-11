<?php
/**
 * Plugin Name: Test Plugin for WP.org Submission Rules
 * Description: This file contains deliberate violations to test the sniffs
 * Version: 1.0
 * Tested up to: 6.8
 */

// VIOLATION: Short prefix (3 characters) in define
define('BFG_VERSION', '1.0.0');

// VIOLATION: Short prefix (3 characters) in class name
class BFG_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
    }

    public function add_menu() {
        // OK - this is fine
    }
}

// VIOLATION: Reserved prefix wp_
function wp_custom_function() {
    return 'This uses wp_ prefix';
}

// VIOLATION: Short prefix in function
function bfg_init() {
    // Some initialization
}

// VIOLATION: Function wrapped in function_exists (anti-pattern)
if (!function_exists('my_helper_function')) {
    function my_helper_function() {
        return 'helper';
    }
}

// Not checked here: $_POST without a nonce check (see WPCS WordPress.Security.NonceVerification)
function bfg_process_form() {
    if (isset($_POST['submit'])) {
        $value = $_POST['value'];
        update_option('my_option', $value);
    }
}

// Not checked here: $_GET used outside of a function
if (isset($_GET['debug'])) {
    error_log('Debug mode on');
}

// OK: Nonce check before processing form data
function properly_save_post() {
    if (!isset($_POST['my_nonce']) || !wp_verify_nonce($_POST['my_nonce'], 'my_action')) {
        return;
    }

    if (isset($_POST['data'])) {
        update_option('my_option', $_POST['data']);
    }
}

// VIOLATION: Global variable with short prefix
global $bfg_options;

// OK: Good prefix length (longer than 4 characters)
function bettfigr_save_post() {
    // Good prefix
}

class BETTFIGR_Admin {
    // Good prefix
}

define('BETTFIGR_PLUGIN_DIR', __DIR__);

// VIOLATION: Inline script tag
function add_inline_script() {
    echo '<script>alert("Hello");</script>';
}

// VIOLATION: Inline style tag
function add_inline_style() {
    echo '<style>.my-class { color: red; }</style>';
}

// VIOLATION: Translation function with variable
function translate_dynamic() {
    $text = 'Hello World';
    return __($text, 'text-domain');
}

// OK: Translation function with string literal
function translate_properly() {
    return __('Hello World', 'text-domain');
}

// VIOLATION: define with short prefix and underscore
define('ABC_CONSTANT', 'value');

// VIOLATION: Reserved prefix (single underscore)
function _helper_function() {
    return 'This starts with underscore';
}

// OK: Namespaced code (should not trigger short prefix warnings)
namespace MyPluginNamespace;

class Settings {
    // This is OK because it's namespaced
}

// Not checked here: $_REQUEST without a nonce check (see WPCS WordPress.Security.NonceVerification)
function handle_request() {
    if (isset($_REQUEST['action'])) {
        do_something($_REQUEST['action']);
    }
}

function do_something($action) {
    // dummy function
}

// External services, checked against tests/readme.txt
class WeatherClient {
    // OK: documented in readme.txt with terms and privacy links
    const WEATHER_ENDPOINT = 'https://api.openweathermap.org/data/2.5/weather';

    // VIOLATION: substack.com is not documented in readme.txt
    const SIGNUP_ENDPOINT = 'https://substack.com/api/v1/reader/signup/pub';

    // WARNING: mailgun.net is documented in readme.txt, but without terms and privacy links
    const MAIL_ENDPOINT = 'https://api.mailgun.net/v3/messages';

    // OK: WordPress.org does not need documenting
    const PLUGINS_API = 'https://api.wordpress.org/plugins/info/1.2/';

    public function fetch() {
        return wp_remote_get(self::WEATHER_ENDPOINT);
    }
}

// Race conditions on transients
class SpamGuard {
    // WARNING: read-increment-write on a transient is not atomic
    public function rate_limit($signal_key) {
        $count = (int) get_transient($signal_key);
        if ($count >= 5) {
            return false;
        }
        set_transient($signal_key, $count + 1, 10 * MINUTE_IN_SECONDS);
        return true;
    }

    // WARNING: check-then-set lock on a transient is not atomic
    public function lock($duplicate_key) {
        if (get_transient($duplicate_key)) {
            return false;
        }
        set_transient($duplicate_key, 1, MINUTE_IN_SECONDS);
        return true;
    }

    // OK: cache refill, a race here is harmless
    public function cached_data() {
        $data = get_transient('myplugin_data');
        if (false === $data) {
            $data = array('fresh' => true);
            set_transient('myplugin_data', $data, HOUR_IN_SECONDS);
        }
        return $data;
    }
}
