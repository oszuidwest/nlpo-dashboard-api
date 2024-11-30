<?php
/**
 * Plugin Name: NLPO API Endpoint
 * Description: Implementeert een custom API endpoint voor artikelen volgens NLPO specificaties met Plausible Analytics integratie
 * Version: 0.0.2
 * Author: Raymon Mens
 * 
 * Endpoint: /wp-json/zw/v1/nlpo
 * Parameters:
 * - from: Start datum (YYYY-MM-DD)
 * - to: Eind datum (YYYY-MM-DD)
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin configuratie
 * Pas deze waardes aan voor verschillende omgevingen
 */
define('NLPO_PLAUSIBLE_BASE_URL', 'https://stats.zuidwesttv.nl/api');
define('NLPO_PLAUSIBLE_SITE_ID', 'zuidwestupdate.nl');
define('NLPO_PLAUSIBLE_TOKEN', 'aaa'); // Vervang door echte token
define('NLPO_CACHE_EXPIRATION', 3600); // Cacheduur in seconden

/**
 * Mainw API functionaliteit
 * Handelt de registratie van endpoints en artikelverwerking af
 */
class NLPO_API {
    /** @var NLPO_Analytics_Service */
    private $analytics;
    
    /**
     * Initialiseert de plugin en registreert de nodige hooks
     */
    public function __construct() {
        $this->analytics = new NLPO_Analytics_Service();
        add_action('rest_api_init', [$this, 'register_endpoints']);
    }
    
    /**
     * Registreert het REST API endpoint
     */
    public function register_endpoints() {
        register_rest_route('zw/v1', '/nlpo', [
            'methods' => 'GET',
            'callback' => [$this, 'get_articles'],
            'permission_callback' => '__return_true',
            'args' => [
                'from' => [
                    'validate_callback' => [$this, 'validate_date']
                ],
                'to' => [
                    'validate_callback' => [$this, 'validate_date']
                ]
            ]
        ]);
    }
    
    /**
     * Valideert dat de gegeven parameter een geldige datum is
     * 
     * @param string $param De datum parameter in YYYY-MM-DD formaat
     * @return bool True als de datum geldig is, anders false
     */
    public function validate_date($param) {
        if (empty($param)) {
            return true;
        }
        
        $d = DateTime::createFromFormat('Y-m-d', $param);
        return $d && $d->format('Y-m-d') === $param;
    }
    
    /**
     * Haalt artikelen op gebaseerd op de request parameters
     * 
     * @param WP_REST_Request $request Het REST API request object
     * @return WP_REST_Response|WP_Error Response met artikelen of error bij problemen
     */
    public function get_articles($request) {
        try {
            $from_date = $request->get_param('from') ?: date('Y-m-d', strtotime('-7 days'));
            $to_date = $request->get_param('to') ?: date('Y-m-d');
            
            $posts = $this->get_posts($from_date, $to_date);
            $articles = array_map([$this, 'format_article'], $posts);
            
            return new WP_REST_Response($articles, 200);
            
        } catch (Exception $e) {
            return new WP_Error(
                'nlpo_api_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Haalt posts op voor het gegeven start- en einddatum
     * 
     * @param string $from_date Start datum
     * @param string $to_date Eind datum
     * @return array Array van WP_Post objecten
     */
    private function get_posts($from_date, $to_date) {
        $query = new WP_Query([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'date_query' => [
                [
                    'after' => $from_date,
                    'before' => $to_date,
                    'inclusive' => true,
                ],
            ],
        ]);
        
        return $query->posts;
    }
    
    /**
     * Schrijf een WordPress post om naar het NLPO artikel formaat
     * 
     * @param WP_Post $post Het post object
     * @return array Geformatteerd artikel
     */
    private function format_article($post) {
        setup_postdata($post);
        
        $url_path = parse_url(get_permalink($post), PHP_URL_PATH);
        $views = $this->analytics->get_pageviews($url_path);
        
        $article = [
            'id' => strval($post->ID),
            'title' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
            'text' => wp_strip_all_tags(get_the_content(null, false, $post)),
            'url' => get_permalink($post),
            'date' => get_the_date('c', $post),
            'author' => get_the_author_meta('display_name', $post->post_author),
            'excerpt' => wp_strip_all_tags(get_the_excerpt($post)),
            'categories' => wp_list_pluck(get_the_category($post->ID), 'name'),
            'tags' => wp_list_pluck(get_the_terms($post->ID, 'regio') ?: [], 'name'),
            'comment_count' => 0,
            'views' => $views
        ];
        
        wp_reset_postdata();
        return $article;
    }
}

/**
 * Service klasse voor Plausible Analytics integratie
 * Handelt de API calls en caching af
 */
class NLPO_Analytics_Service {
    /**
     * Haalt pageviews op voor een specifieke pagina
     * 
     * @param string $page_path Het pad van de pagina
     * @return int Aantal pageviews, of -1 bij een fout
     */
    public function get_pageviews($page_path) {
        try {
            $cache_key = 'plausible_pageviews_' . md5($page_path);
            $cached_data = get_transient($cache_key);
            
            if ($cached_data !== false) {
                return $cached_data;
            }
            
            $response = $this->make_api_request($page_path);
            $pageviews = $this->process_response($response);
            
            set_transient($cache_key, $pageviews, NLPO_CACHE_EXPIRATION);
            return $pageviews;
            
        } catch (Exception $e) {
            error_log(sprintf(
                '[NLPO API] Plausible Analytics error for %s: %s',
                $page_path,
                $e->getMessage()
            ));
            return -1;
        }
    }
    
    /**
     * Maakt een API request naar Plausible
     * 
     * @param string $page_path Het pad van de pagina
     * @return array|WP_Error Response van de API
     * @throws Exception bij API fouten
     */
    private function make_api_request($page_path) {
        if (empty(NLPO_PLAUSIBLE_TOKEN)) {
            throw new Exception('Plausible API token not configured');
        }
        
        $url = sprintf(
            "%s/v1/stats/aggregate?site_id=%s&period=custom&date=%s,%s&filters=event:page==%s&metrics=pageviews",
            NLPO_PLAUSIBLE_BASE_URL,
            NLPO_PLAUSIBLE_SITE_ID,
            '2020-01-01',
            date('Y-m-d'),
            urlencode($page_path)
        );
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . NLPO_PLAUSIBLE_TOKEN
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            throw new Exception("Unexpected response code: $response_code");
        }
        
        return $response;
    }
    
    /**
     * Verwerkt de API response en haalt de pageviews eruit
     * 
     * @param array $response De API response
     * @return int Aantal pageviews
     * @throws Exception bij ongeldige response
     */
    private function process_response($response) {
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            throw new Exception('Empty response body');
        }
        
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        return $data['results']['pageviews']['value'] ?? 0;
    }
}

// Draai de plugin
new NLPO_API();

// Activation/deactivation hooks
register_activation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});