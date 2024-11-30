<?php
/**
 * Plugin Name: NLPO API Endpoint
 * Description: Implementeert een custom API endpoint voor artikelen volgens NLPO specificaties met Plausible Analytics integratie
 * Version: 0.0.1
 * Author: Raymon Mens
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

class NLPO_API {
    private $plausible_base_url;
    private $plausible_site_id;
    private $plausible_token;
    private $cache_expiration = 3600; // 1 hour in seconds

    /**
     * Initialize the plugin
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_endpoints'));
        
        // Plausible Analytics configuration
        $this->plausible_base_url = 'https://stats.zuidwesttv.nl/api';
        $this->plausible_site_id = 'zuidwestupdate.nl';
        $this->plausible_token = 'aaa'; // Replace with your actual token
    }

    /**
     * Register the API endpoints
     */
    public function register_endpoints() {
        register_rest_route('zw/v1', '/nlpo', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_articles'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * Fetch pageviews for a specific page from Plausible Analytics
     * Returns null if anything goes wrong
     */
    private function get_plausible_pageviews($page_path) {
        try {
            $cache_key = 'plausible_pageviews_' . md5($page_path);
            $cached_data = get_transient($cache_key);

            if ($cached_data !== false) {
                return $cached_data;
            }

            $start_date = '2020-01-01';
            $end_date = date('Y-m-d');
            
            $url = sprintf(
                "%s/v1/stats/aggregate?site_id=%s&period=custom&date=%s,%s&filters=event:page==%s&metrics=pageviews",
                $this->plausible_base_url,
                $this->plausible_site_id,
                $start_date,
                $end_date,
                urlencode($page_path)
            );

            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->plausible_token
                ),
                'timeout' => 15
            ));

            if (is_wp_error($response)) {
                error_log('Plausible API Error: ' . $response->get_error_message());
                return null;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                error_log('Plausible API Error: Unexpected response code ' . $response_code);
                error_log('Response body: ' . wp_remote_retrieve_body($response));
                return null;
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                error_log('Plausible API Error: Empty response body');
                return null;
            }

            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Plausible API Error: Invalid JSON response - ' . json_last_error_msg());
                return null;
            }

            // Cache the results
            $pageviews = isset($data['results']['pageviews']['value']) ? $data['results']['pageviews']['value'] : 0;
            set_transient($cache_key, $pageviews, $this->cache_expiration);

            return $pageviews;

        } catch (Exception $e) {
            error_log('Plausible API Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get articles based on the request parameters
     */
    public function get_articles($request) {
        // Get date parameters for articles
        $from_date = $request->get_param('from');
        $to_date = $request->get_param('to');

        // If no dates specified, default to last 7 days for articles
        if (!$from_date) {
            $from_date = date('Y-m-d', strtotime('-7 days'));
        }
        if (!$to_date) {
            $to_date = date('Y-m-d');
        }

        // Setup WP_Query arguments
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'date_query' => array(
                array(
                    'after' => $from_date,
                    'before' => $to_date,
                    'inclusive' => true,
                ),
            ),
        );

        // Get posts
        $query = new WP_Query($args);
        $articles = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                
                // Get basic post data
                $post_id = get_the_ID();
                
                // Get categories
                $categories = array();
                $cats = get_the_category($post_id);
                foreach ($cats as $cat) {
                    $categories[] = $cat->name;
                }

                // Get regio terms and use them as tags
                $tags = array();
                $regio_terms = get_the_terms($post_id, 'regio');
                if (!is_wp_error($regio_terms) && $regio_terms) {
                    foreach ($regio_terms as $term) {
                        $tags[] = $term->name;
                    }
                }

                // Get the URL path from permalink
                $url_path = parse_url(get_permalink(), PHP_URL_PATH);
                
                // Get pageviews for this specific page
                $views = $this->get_plausible_pageviews($url_path);
                
                // Return -1 if there was an error fetching pageviews
                $views = $views === null ? -1 : $views;

                // Build article array
                $article = array(
                    'id' => strval($post_id),
                    'title' => html_entity_decode(get_the_title(), ENT_QUOTES, 'UTF-8'),
                    'text' => wp_strip_all_tags(get_the_content()),
                    'url' => get_permalink(),
                    'date' => get_the_date('c'),
                    'author' => get_the_author(),
                    'excerpt' => wp_strip_all_tags(get_the_excerpt()),
                    'categories' => $categories,
                    'tags' => $tags,
                    'comment_count' => 0,
                    'views' => $views
                );

                $articles[] = $article;
            }
        }

        wp_reset_postdata();

        return new WP_REST_Response($articles, 200);
    }
}

// Initialize the plugin
new NLPO_API();

// Activation hook
register_activation_hook(__FILE__, 'nlpo_api_activate');
function nlpo_api_activate() {
    // Flush rewrite rules on activation
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'nlpo_api_deactivate');
function nlpo_api_deactivate() {
    // Flush rewrite rules on deactivation
    flush_rewrite_rules();
}