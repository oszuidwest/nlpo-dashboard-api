<?php
/**
 * Plugin Name: NLPO API Endpoint
 * Description: Implementeert een custom API endpoint voor het aanleveren van artikelen en traffic voor het NLPO Dashboard
 * Version: 0.0.5
 * Author: Streekomroep ZuidWest
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * 
 * Endpoint: /wp-json/zw/v1/nlpo
 * Parameters:
 * - from: Startdatum (YYYY-MM-DD)
 * - to: Einddatum (YYYY-MM-DD)
 * - token: API authenticatie token (verplicht)
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin configuratie constanten
 * Deze waardes dienen aangepast te worden voor verschillende omgevingen
 */
define('NLPO_PLAUSIBLE_BASE_URL', 'https://stats.zuidwesttv.nl/api');
define('NLPO_PLAUSIBLE_SITE_ID', 'zuidwestupdate.nl');
define('NLPO_PLAUSIBLE_TOKEN', 'PLAUSIBLE-API-KEY-HIER');
define('NLPO_CACHE_EXPIRATION', 3600); // Cacheduur in seconden
define('NLPO_API_TOKEN', 'HELE-VEILIGE-TOKEN-HIER'); // Dit is de token die het endpoint beveiligt

/**
 * Helper functie voor het loggen van fouten naar de PHP error log
 * 
 * @param string $message Foutmelding
 * @param array $context Extra context informatie
 */
function nlpo_log_error($message, $context = []) {
    $context_str = !empty($context) ? ' - Context: ' . wp_json_encode($context) : '';
    error_log('[NLPO API] ' . esc_html($message) . $context_str);
}

/**
 * Service klasse voor Plausible Analytics integratie
 * 
 * Deze klasse handelt alle interacties met de Plausible Analytics API af,
 * inclusief caching van resultaten voor betere performance.
 */
class NLPO_Analytics_Service {
    /**
     * Haalt pageviews op voor een specifieke pagina
     * 
     * @param string $page_path Het pad van de pagina
     * @return int Aantal pageviews
     * @throws Exception bij API fouten
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
            nlpo_log_error(sprintf(
                'Plausible Analytics fout voor %s: %s',
                esc_html($page_path),
                esc_html($e->getMessage())
            ));
            throw $e;
        }
    }
    
    /**
     * Maakt een API request naar Plausible Analytics
     * 
     * @param string $page_path Het pad van de pagina
     * @return array De API response
     * @throws Exception bij API fouten
     */
    private function make_api_request($page_path) {
        if (empty(NLPO_PLAUSIBLE_TOKEN)) {
            throw new Exception('Plausible API token niet geconfigureerd');
        }
        
        $url = sprintf(
            "%s/v1/stats/aggregate?site_id=%s&period=custom&date=%s,%s&filters=event:page==%s&metrics=pageviews",
            esc_url_raw(NLPO_PLAUSIBLE_BASE_URL),
            urlencode(NLPO_PLAUSIBLE_SITE_ID),
            urlencode('2020-01-01'),
            urlencode(date('Y-m-d')),
            urlencode($page_path)
        );
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . NLPO_PLAUSIBLE_TOKEN
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Plausible API request mislukt: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']) ? $error_data['error'] : 'Onbekende fout';
            throw new Exception(sprintf(
                'Plausible API gaf %d: %s', 
                intval($response_code),
                esc_html($error_message)
            ));
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
            throw new Exception('Lege response van Plausible API');
        }
        
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Ongeldige JSON response van Plausible API: ' . json_last_error_msg());
        }
        
        if (!isset($data['results']['pageviews']['value'])) {
            throw new Exception('Onverwacht response formaat van Plausible API');
        }
        
        return (int) $data['results']['pageviews']['value'];
    }
}

/**
 * Main klasse voor de NLPO API functionaliteit
 * 
 * Deze klasse implementeert een beveiligde REST API endpoint voor het ophalen van
 * artikelen volgens NLPO specificaties, inclusief pageviews uit Plausible Analytics.
 */
class NLPO_API {
    /** @var NLPO_Analytics_Service Instance van de analytics service */
    private $analytics;
    
    /**
     * Constructor: initialiseert de plugin en registreert de nodige hooks
     */
    public function __construct() {
        $this->analytics = new NLPO_Analytics_Service();
        add_action('rest_api_init', [$this, 'register_endpoints']);
    }
    
    /**
     * Registreert het REST API endpoint met token authenticatie
     */
    public function register_endpoints() {
        register_rest_route('zw/v1', '/nlpo', [
            'methods' => 'GET',
            'callback' => [$this, 'get_articles'],
            'permission_callback' => [$this, 'verify_token'],
            'args' => [
                'from' => [
                    'required' => false,
                    'validate_callback' => [$this, 'validate_date'],
                    'description' => 'Startdatum in YYYY-MM-DD formaat'
                ],
                'to' => [
                    'required' => false,
                    'validate_callback' => [$this, 'validate_date'],
                    'description' => 'Einddatum in YYYY-MM-DD formaat'
                ],
                'token' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    },
                    'description' => 'API authenticatie token'
                ]
            ]
        ]);
    }

    /**
     * Verifieert de API token voor toegangscontrole
     * 
     * @param WP_REST_Request $request Het REST API request object
     * @return bool|WP_Error True bij geldige token, WP_Error bij ongeldige of ontbrekende token
     */
    public function verify_token($request) {
        $token = $request->get_param('token');
        
        if (!defined('NLPO_API_TOKEN') || empty(NLPO_API_TOKEN)) {
            nlpo_log_error('API token niet geconfigureerd in de plugin');
            
            return new WP_Error(
                'rest_token_not_configured',
                'API token niet geconfigureerd',
                ['status' => 500]
            );
        }

        if ($token !== NLPO_API_TOKEN) {
            nlpo_log_error('Toegangspoging met ongeldige token');
            
            return new WP_Error(
                'rest_invalid_token',
                'Ongeldige API token',
                ['status' => 401]
            );
        }
        
        return true;
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
            
            // Valideer dat from_date voor to_date ligt
            if (strtotime($from_date) > strtotime($to_date)) {
                return new WP_Error(
                    'rest_invalid_date_range',
                    'From datum moet voor of gelijk zijn aan to datum',
                    ['status' => 400]
                );
            }
            
            // Haal posts op en formatteer naar artikelen
            $posts = $this->get_posts($from_date, $to_date);
            $articles = [];
            
            foreach ($posts as $post) {
                try {
                    $articles[] = $this->format_article($post);
                } catch (Exception $e) {
                    nlpo_log_error('Fout bij formatteren artikel: ' . esc_html($e->getMessage()), [
                        'post_id' => $post->ID
                    ]);
                    // Ga door met de volgende post, sla deze over
                }
            }
            
            return rest_ensure_response($articles);
            
        } catch (Exception $e) {
            nlpo_log_error('Fout in get_articles: ' . esc_html($e->getMessage()));
            
            return new WP_Error(
                'rest_api_error',
                'Interne server fout: ' . esc_html($e->getMessage()),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Haalt WordPress posts op voor het gegeven datumbereik
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
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        
        return $query->posts;
    }
    
    /**
     * Formatteert een WordPress post naar het NLPO artikel formaat
     * 
     * @param WP_Post $post Het WordPress post object
     * @return array Geformatteerd artikel volgens NLPO specificaties
     * @throws Exception bij fouten in data ophalen
     */
    private function format_article($post) {
        if (!($post instanceof WP_Post)) {
            throw new Exception('Ongeldig post object');
        }
        
        setup_postdata($post);
        
        $permalink = get_permalink($post);
        if (empty($permalink)) {
            throw new Exception('Kon permalink niet ophalen voor post ' . $post->ID);
        }
        
        $url_path = parse_url($permalink, PHP_URL_PATH);
        
        // Veilig ophalen van auteur
        $author_id = $post->post_author;
        $author_name = get_the_author_meta('display_name', $author_id);
        $author = !empty($author_name) ? $author_name : 'Onbekend';
        
        // Categorieën veilig ophalen
        $categories = [];
        $terms = get_the_category($post->ID);
        if (!is_wp_error($terms) && is_array($terms)) {
            $categories = wp_list_pluck($terms, 'name');
        }
        
        // Controleer of 'regio' taxonomie bestaat voor deze post
        $regio_terms = get_the_terms($post->ID, 'regio');
        
        // Als de regio taxonomie bestaat en geldig is, gebruik die
        if (!is_wp_error($regio_terms) && is_array($regio_terms) && !empty($regio_terms)) {
            $tags = wp_list_pluck($regio_terms, 'name');
        } 
        // Anders gebruik de standaard post tags
        else {
            $post_tags = get_the_tags($post->ID);
            $tags = (!is_wp_error($post_tags) && is_array($post_tags)) 
                ? wp_list_pluck($post_tags, 'name') 
                : [];
        }
        
        // Haal pageviews op met foutafhandeling
        try {
            $views = $this->analytics->get_pageviews($url_path);
        } catch (Exception $e) {
            nlpo_log_error('Kon pageviews niet ophalen: ' . esc_html($e->getMessage()), [
                'post_id' => $post->ID,
                'url_path' => esc_html($url_path)
            ]);
            $views = 0;
        }
        
        $article = [
            'id' => strval($post->ID),
            'title' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
            'text' => wp_strip_all_tags(get_the_content(null, false, $post)),
            'url' => esc_url_raw($permalink),
            'date' => get_the_date('c', $post),
            'author' => esc_html($author),
            'excerpt' => wp_strip_all_tags(get_the_excerpt($post)),
            'categories' => array_map('esc_html', $categories),
            'tags' => array_map('esc_html', $tags),
            'comment_count' => (int) $post->comment_count,
            'views' => $views
        ];
        
        wp_reset_postdata();
        return $article;
    }
}

// Initialiseer de plugin
new NLPO_API();

/**
 * Plugin activatie hook: ververst de rewrite rules
 */
register_activation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

/**
 * Plugin deactivatie hook: ververst de rewrite rules
 */
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});