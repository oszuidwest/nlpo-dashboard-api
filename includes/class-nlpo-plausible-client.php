<?php
/**
 * NLPO Plausible Client
 *
 * @package NLPO_API
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client for Plausible Analytics API integration.
 *
 * Uses WordPress HTTP API with parallel requests via Requests library.
 * All pageviews are cached in a single transient.
 */
final class NLPO_Plausible_Client {

	/**
	 * Cache key for pageviews transient.
	 */
	private const string CACHE_KEY = 'nlpo_all_pageviews';

	/**
	 * Batch size for parallel requests.
	 */
	private const int BATCH_SIZE = 25;

	/**
	 * Fetches pageviews for multiple pages.
	 * Uses cache where possible, fetches missing paths in parallel.
	 *
	 * @param array<int, array{path: string, date: string}> $page_data Array of path and date pairs.
	 * @return array<string, int> Associative array of page_path => pageviews.
	 */
	public function fetch_pageviews_batch( array $page_data ): array {
		if ( '' === NLPO_PLAUSIBLE_TOKEN ) {
			NLPO_Logger::error( 'Plausible API token not configured' );
			return array_fill_keys( array_column( $page_data, 'path' ), 0 );
		}

		$cached        = get_transient( self::CACHE_KEY );
		$all_pageviews = is_array( $cached ) ? $cached : [];

		$uncached = array_filter(
			$page_data,
			static fn( array $item ): bool => ! isset( $all_pageviews[ $item['path'] ] ),
		);

		if ( [] !== $uncached ) {
			$fetched       = $this->fetch_parallel( $uncached );
			$all_pageviews = [ ...$all_pageviews, ...$fetched ];

			set_transient( self::CACHE_KEY, $all_pageviews, NLPO_CACHE_EXPIRATION );
		}

		$results = [];
		foreach ( $page_data as $item ) {
			$results[ $item['path'] ] = $all_pageviews[ $item['path'] ] ?? 0;
		}

		return $results;
	}

	/**
	 * Executes parallel requests in batches.
	 *
	 * @param array<int, array{path: string, date: string}> $page_data Array of path and date pairs.
	 * @return array<string, int> Associative array of page_path => pageviews.
	 */
	private function fetch_parallel( array $page_data ): array {
		$results = [];
		$batches = array_chunk( $page_data, self::BATCH_SIZE );

		foreach ( $batches as $batch ) {
			$batch_results = $this->fetch_batch( $batch );
			$results       = [ ...$results, ...$batch_results ];
		}

		return $results;
	}

	/**
	 * Executes a batch of requests in parallel via WordPress HTTP API.
	 *
	 * @param array<int, array{path: string, date: string}> $page_data Array of path and date pairs.
	 * @return array<string, int> Associative array of page_path => pageviews.
	 */
	private function fetch_batch( array $page_data ): array {
		$requests = [];
		$results  = [];

		foreach ( $page_data as $item ) {
			$path      = $item['path'];
			$from_date = $item['date'];

			$url = sprintf(
				'%s/v1/stats/aggregate?site_id=%s&period=custom&date=%s,%s&filters=event:page==%s&metrics=pageviews',
				rtrim( NLPO_PLAUSIBLE_BASE_URL, '/' ),
				rawurlencode( NLPO_PLAUSIBLE_SITE_ID ),
				$from_date,
				gmdate( 'Y-m-d' ),
				rawurlencode( $path ),
			);

			$requests[ $path ] = [
				'url'     => $url,
				'headers' => [
					'Authorization' => 'Bearer ' . NLPO_PLAUSIBLE_TOKEN,
				],
			];
		}

		// Execute parallel requests via Requests library.
		$responses = \WpOrg\Requests\Requests::request_multiple( $requests, [ 'timeout' => 15 ] );

		foreach ( $responses as $path => $response ) {
			$results[ $path ] = $this->parse_response( $response, $path );
		}

		return $results;
	}

	/**
	 * Parses a Plausible API response and extracts pageviews.
	 *
	 * @param mixed  $response The response object from the request.
	 * @param string $path The page path for error logging.
	 * @return int The number of pageviews, or 0 on failure.
	 */
	private function parse_response( mixed $response, string $path ): int {
		if ( ! is_object( $response ) ) {
			return 0;
		}

		$is_success  = ! empty( $response->success );
		$body        = $response->body ?? '';
		$status_code = $response->status_code ?? 0;

		if ( ! $is_success ) {
			NLPO_Logger::error(
				'Plausible request failed',
				[
					'path'   => $path,
					'status' => $status_code,
				],
			);
			return 0;
		}

		if ( ! json_validate( $body ) ) {
			NLPO_Logger::error(
				'Invalid JSON response from Plausible',
				[ 'path' => $path ],
			);
			return 0;
		}

		$data = json_decode( $body, true );

		return is_array( $data ) && isset( $data['results']['pageviews']['value'] )
			? (int) $data['results']['pageviews']['value']
			: 0;
	}
}
