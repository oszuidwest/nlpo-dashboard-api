<?php
/**
 * NLPO REST Controller
 *
 * @package NLPO_API
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API controller for NLPO article endpoint.
 *
 * Implements a secured REST API endpoint for retrieving
 * articles according to NLPO specifications, including pageviews from Plausible Analytics.
 */
final class NLPO_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @param NLPO_Plausible_Client $plausible Instance of the Plausible client.
	 */
	public function __construct(
		private readonly NLPO_Plausible_Client $plausible = new NLPO_Plausible_Client(),
	) {
		add_action( 'rest_api_init', $this->register_endpoints( ... ) );
	}

	/**
	 * Registers the REST API endpoint.
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		register_rest_route(
			'zw/v1',
			'/nlpo',
			[
				'methods'             => 'GET',
				'callback'            => $this->get_articles( ... ),
				'permission_callback' => $this->verify_token( ... ),
				'args'                => [
					'from'  => [
						'required'          => false,
						'validate_callback' => $this->validate_date( ... ),
						'description'       => 'Start date in YYYY-MM-DD format.',
					],
					'to'    => [
						'required'          => false,
						'validate_callback' => $this->validate_date( ... ),
						'description'       => 'End date in YYYY-MM-DD format.',
					],
					'token' => [
						'required'          => true,
						'validate_callback' => static fn( mixed $param ): bool => is_string( $param ) && '' !== $param,
						'description'       => 'API authentication token.',
					],
				],
			],
		);
	}

	/**
	 * Verifies the API token.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return true|WP_Error True for valid token, WP_Error for invalid or missing token.
	 */
	public function verify_token( WP_REST_Request $request ): true|WP_Error {
		$token            = $request->get_param( 'token' );
		$configured_token = NLPO_Settings::get( 'api_token' );

		if ( '' === $configured_token ) {
			NLPO_Logger::error( 'API token not configured in the plugin' );

			return new WP_Error(
				'rest_token_not_configured',
				'API token not configured',
				[ 'status' => 500 ],
			);
		}

		if ( $configured_token !== $token ) {
			NLPO_Logger::error( 'Access attempt with invalid token' );

			return new WP_Error(
				'rest_invalid_token',
				'Invalid API token',
				[ 'status' => 401 ],
			);
		}

		return true;
	}

	/**
	 * Validates a date parameter.
	 *
	 * @param mixed $param The date parameter in YYYY-MM-DD format.
	 * @return bool True if the date is valid, otherwise false.
	 */
	public function validate_date( mixed $param ): bool {
		if ( ! is_string( $param ) || '' === $param ) {
			return true;
		}

		$date = DateTime::createFromFormat( 'Y-m-d', $param );
		return $date instanceof DateTime && $param === $date->format( 'Y-m-d' );
	}

	/**
	 * Retrieves articles for the given date range.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response|WP_Error Response with articles or error on problems.
	 */
	public function get_articles( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$from_param = $request->get_param( 'from' );
			$to_param   = $request->get_param( 'to' );
			$from_date  = is_string( $from_param ) && '' !== $from_param ? $from_param : gmdate( 'Y-m-d', strtotime( '-7 days' ) );
			$to_date    = is_string( $to_param ) && '' !== $to_param ? $to_param : gmdate( 'Y-m-d' );

			NLPO_Logger::debug(
				'API request received',
				[
					'from' => $from_date,
					'to'   => $to_date,
				],
			);

			if ( strtotime( $from_date ) > strtotime( $to_date ) ) {
				return new WP_Error(
					'rest_invalid_date_range',
					'From date must be before or equal to to date',
					[ 'status' => 400 ],
				);
			}

			$posts = $this->get_posts( $from_date, $to_date );

			NLPO_Logger::debug( 'Posts retrieved', [ 'count' => count( $posts ) ] );

			$page_data = [];
			foreach ( $posts as $post ) {
				$permalink = get_permalink( $post );
				$path      = is_string( $permalink ) ? wp_parse_url( $permalink, PHP_URL_PATH ) : null;
				$date      = get_the_date( 'Y-m-d', $post );

				if ( is_string( $path ) && '' !== $path && is_string( $date ) ) {
					$page_data[ $post->ID ] = [
						'path' => $path,
						'date' => $date,
					];
				}
			}

			$all_pageviews = $this->plausible->fetch_pageviews_batch( array_values( $page_data ) );

			NLPO_Logger::debug( 'Pageviews fetched', [ 'paths' => count( $all_pageviews ) ] );

			$articles = [];
			foreach ( $posts as $post ) {
				try {
					$path       = $page_data[ $post->ID ]['path'] ?? '';
					$views      = $all_pageviews[ $path ] ?? 0;
					$articles[] = $this->format_article( $post, $views );
				} catch ( Exception $e ) {
					NLPO_Logger::error(
						'Error formatting article: ' . esc_html( $e->getMessage() ),
						[ 'post_id' => $post->ID ],
					);
				}
			}

			NLPO_Logger::debug( 'Response prepared', [ 'articles' => count( $articles ) ] );

			return rest_ensure_response( $articles );

		} catch ( Exception $e ) {
			NLPO_Logger::error( 'Error in get_articles: ' . esc_html( $e->getMessage() ) );

			return new WP_Error(
				'rest_api_error',
				'Internal server error: ' . esc_html( $e->getMessage() ),
				[ 'status' => 500 ],
			);
		}
	}

	/**
	 * Retrieves posts for a date range.
	 *
	 * @param string $from_date Start date.
	 * @param string $to_date End date.
	 * @return WP_Post[] Array of WP_Post objects.
	 */
	private function get_posts( string $from_date, string $to_date ): array {
		$query = new WP_Query(
			[
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'date_query'     => [
					[
						'after'     => $from_date,
						'before'    => $to_date,
						'inclusive' => true,
					],
				],
				'orderby'        => 'date',
				'order'          => 'DESC',
			],
		);

		return $query->posts;
	}

	/**
	 * Formats a post to NLPO article format.
	 *
	 * @param WP_Post $post The WordPress post object.
	 * @param int     $views Number of pageviews.
	 * @return array<string, mixed> Formatted article.
	 * @throws Exception On errors in data retrieval.
	 */
	private function format_article( WP_Post $post, int $views = 0 ): array {
		setup_postdata( $post );

		$permalink = get_permalink( $post );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			throw new Exception( 'Could not retrieve permalink for post ' . esc_html( (string) $post->ID ) );
		}

		$author_id   = (int) $post->post_author;
		$author_name = get_the_author_meta( 'display_name', $author_id );
		$author      = is_string( $author_name ) && '' !== $author_name ? $author_name : 'Unknown';

		$categories = [];
		$terms      = get_the_category( $post->ID );
		if ( is_array( $terms ) ) {
			$categories = wp_list_pluck( $terms, 'name' );
		}

		// Prefer 'regio' taxonomy over default tags for regional grouping.
		$regio_terms = get_the_terms( $post->ID, 'regio' );

		if ( is_array( $regio_terms ) && [] !== $regio_terms ) {
			$tags = wp_list_pluck( $regio_terms, 'name' );
		} else {
			$post_tags = get_the_tags( $post->ID );
			$tags      = is_array( $post_tags ) ? wp_list_pluck( $post_tags, 'name' ) : [];
		}

		$article = [
			'id'            => (string) $post->ID,
			'title'         => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'text'          => wp_strip_all_tags( get_the_content( null, false, $post ) ),
			'url'           => esc_url_raw( $permalink ),
			'date'          => get_the_date( 'c', $post ),
			'author'        => esc_html( $author ),
			'excerpt'       => wp_strip_all_tags( get_the_excerpt( $post ) ),
			'categories'    => array_map( 'esc_html', $categories ),
			'tags'          => array_map( 'esc_html', $tags ),
			'comment_count' => (int) $post->comment_count,
			'views'         => $views,
		];

		wp_reset_postdata();
		return $article;
	}
}
