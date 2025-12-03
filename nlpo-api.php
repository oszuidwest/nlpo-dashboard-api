<?php
/**
 * Plugin Name: NLPO API Endpoint
 * Description: Implements a custom API endpoint for delivering articles and traffic for the NLPO Dashboard.
 * Version: 0.1.0
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Author: Streekomroep ZuidWest
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
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

/**
 * Plugin configuration constants.
 * These values should be adjusted for different environments.
 */
define( 'NLPO_PLAUSIBLE_BASE_URL', 'https://plausible.local/api' );
define( 'NLPO_PLAUSIBLE_SITE_ID', 'website.com' );
define( 'NLPO_PLAUSIBLE_TOKEN', 'XXXXXXXX' );
define( 'NLPO_CACHE_EXPIRATION', 7200 ); // Cache duration in seconds.
define( 'NLPO_API_TOKEN', 'XXXXX-XXXXX-XXXXX--XXXXX' ); // This is the token that secures the endpoint so not everyone can access the data.

require_once plugin_dir_path( __FILE__ ) . 'includes/class-nlpo-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-nlpo-plausible-client.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-nlpo-rest-controller.php';

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
