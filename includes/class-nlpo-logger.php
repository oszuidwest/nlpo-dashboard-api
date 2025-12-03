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
 * Handles error and debug logging to the PHP error log.
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
		self::log( 'ERROR', $message, $context );
	}

	/**
	 * Log a debug message (only when debug mode is enabled).
	 *
	 * @param string               $message Debug message.
	 * @param array<string, mixed> $context Additional context information.
	 * @return void
	 */
	public static function debug( string $message, array $context = [] ): void {
		if ( ! NLPO_Settings::get( 'debug_mode' ) ) {
			return;
		}

		self::log( 'DEBUG', $message, $context );
	}

	/**
	 * Write a log entry.
	 *
	 * @param string               $level   Log level (ERROR, DEBUG).
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context information.
	 * @return void
	 */
	private static function log( string $level, string $message, array $context = [] ): void {
		$context_str = [] !== $context ? ' - Context: ' . wp_json_encode( $context ) : '';
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional error logging.
		error_log( '[NLPO API] [' . $level . '] ' . esc_html( $message ) . $context_str );
	}
}
