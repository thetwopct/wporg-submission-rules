<?php
/**
 * Plugin Name: Test Plugin for WP.org Submission Rules
 * Description: This file contains deliberate violations to test the sniffs
 * Version: 1.0
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

// VIOLATION: $_POST without nonce check
function bfg_process_form() {
    if (isset($_POST['submit'])) {
        $value = $_POST['value'];
        update_option('my_option', $value);
    }
}

// VIOLATION: $_GET outside of function (performance issue)
if (isset($_GET['debug'])) {
    error_log('Debug mode on');
}

// OK: Proper nonce check
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

// VIOLATION: $_REQUEST without nonce
function handle_request() {
    if (isset($_REQUEST['action'])) {
        do_something($_REQUEST['action']);
    }
}

function do_something($action) {
    // dummy function
}
