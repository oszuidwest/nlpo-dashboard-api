<?php
/**
 * NLPO Logger
 *
 * @package NLPO_API
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class for NLPO API.
 *
 * Handles error logging to the PHP error log.
 */
final class NLPO_Logger {

	/**
	 * Log an error message.
	 *
	 * @param string               $message Error message.
	 * @param array<string, mixed> $context Additional context information.
	 * @return void
	 */
	public static function error( string $message, array $context = [] ): void {
		$context_str = [] !== $context ? ' - Context: ' . wp_json_encode( $context ) : '';
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional error logging.
		error_log( '[NLPO API] ' . esc_html( $message ) . $context_str );
	}
}
