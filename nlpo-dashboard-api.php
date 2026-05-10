<?php
/**
 * Plugin Name: NLPO API Endpoint
 * Description: Implements a custom API endpoint for delivering articles and traffic for the NLPO Dashboard.
 * Version: 0.1.1
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Author: Streekomroep ZuidWest
 * Author URI: https://www.zuidwesttv.nl
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nlpo-dashboard-api
 *
 * Endpoint: /wp-json/zw/v1/nlpo
 * Parameters:
 * - from: Start date (YYYY-MM-DD).
 * - to: End date (YYYY-MM-DD).
 * - token: API authentication token (required).
 *
 * @package NLPO_API
 */

// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-nlpo-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-nlpo-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-nlpo-plausible-client.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-nlpo-rest-controller.php';

new NLPO_Settings();
new NLPO_REST_Controller();

register_activation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);
