<?php
/**
 * PHPStan bootstrap file
 *
 * @package NLPO_API
 */

define( 'NLPO_PLAUSIBLE_BASE_URL', 'https://example.com/api' );
define( 'NLPO_PLAUSIBLE_SITE_ID', 'example.com' );
define( 'NLPO_PLAUSIBLE_TOKEN', 'test-token' );
define( 'NLPO_CACHE_EXPIRATION', 3600 );
define( 'NLPO_API_TOKEN', 'test-api-token' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Core WordPress constant.
}
