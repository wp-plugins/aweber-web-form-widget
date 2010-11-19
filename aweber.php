<?php
/*
Plugin Name: AWeber Web Forms
Plugin URI: http://labs.aweber.com
Description: Adds your AWeber Web Form to your sidebar
Version: 1.0.1
Author: AWeber Communications, Inc.
Author URI: http://labs.aweber.com
License: MIT
*/

if (function_exists('register_activation_hook')) {
    if (!function_exists('aweber_web_forms_activate')) {
        function aweber_web_forms_activate() {
            if (version_compare(phpversion(), '5.2', '<')) {
                trigger_error('', E_USER_ERROR);
            }
        }
    }

    register_activation_hook(__FILE__, 'aweber_web_forms_activate');
}

if (isset($_GET['action']) and $_GET['action'] == 'error_scrape') {
    die('Sorry, AWeber Web Forms requires PHP 5.2 or higher. Please deactivate AWeber Web Forms.');
}

// Initialize plugin.
if (!class_exists('AWeberWebformPlugin')) {
    require_once(dirname(__FILE__) . '/php/aweber_api/aweber_api.php');
    require_once(dirname(__FILE__) . '/php/aweber_webform_plugin.php');
    $aweber_webform_plugin = new AWeberWebformPlugin();
}

// Initialize admin panel.
if (!function_exists('AWeberFormsWidgetController_ap')) {
    function AWeberFormsWidgetController_ap() {
        global $aweber_webform_plugin;
        if (!isset($aweber_webform_plugin)) {
            return;
        }
        if (function_exists('add_options_page')) {
            add_options_page('AWeber Web Form', 'AWeber Web Form', 'manage_options', basename(__FILE__), array(&$aweber_webform_plugin, 'printAdminPage'));
        }

    }
}
if (!function_exists('AWeberRegisterSettings')) {

    function AWeberAuthMessage() {
        global $aweber_webform_plugin;
        echo $aweber_webform_plugin->messages['auth_required'];
    }

    function AWeberRegisterSettings() {
        if (is_admin()) {
            global $aweber_webform_plugin;
            register_setting($aweber_webform_plugin->oauthOptionsName, 'aweber_webform_oauth_id');
            register_setting($aweber_webform_plugin->oauthOptionsName, 'aweber_webform_oauth_removed');

            $pluginAdminOptions = get_option($aweber_webform_plugin->adminOptionsName);
            if ($pluginAdminOptions['access_key'] == '') {
                add_action('admin_notices', 'AWeberAuthMessage');
                return;
            }
        }
    }
}
// Initialize widget.
if (!function_exists('AWeberFormsWidgetController_widget')) {
    function AWeberFormsWidgetController_widget() {
        global $aweber_webform_plugin;
        if (!isset($aweber_webform_plugin)) {
            return;
        }

        if (function_exists('wp_register_sidebar_widget') and function_exists('wp_register_widget_control')) {
            wp_register_sidebar_widget($aweber_webform_plugin->widgetOptionsName, __('AWeber Web Form'), array(&$aweber_webform_plugin, 'printWidget'));
            wp_register_widget_control($aweber_webform_plugin->widgetOptionsName, __('AWeber Web Form'), array(&$aweber_webform_plugin, 'printWidgetControl'));
        }
    }
}

// Actions and filters.
if (isset($aweber_webform_plugin)) {
    // Actions
    add_action('aweber/aweber.php',  array(&$aweber_webform_plugin, 'init'));
    add_action('admin_menu', 'AWeberFormsWidgetController_ap');
    add_action('admin_init', 'AWeberRegisterSettings');
    add_action('plugins_loaded', 'AWeberFormsWidgetController_widget');
    add_action('admin_print_scripts', array(&$aweber_webform_plugin, 'addHeaderCode'));
    add_action('wp_ajax_get_widget_control', array(&$aweber_webform_plugin, 'printWidgetControlAjax'));

    // Filters
}
?>
