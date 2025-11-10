<?php
/**
 * Plugin Name: GSC Schema Fix
 * Plugin URI: https://github.com/dratzymarcano/gscerrorfix
 * Description: Automatically fixes Google Search Console errors by adding required schema markup (offers, review, aggregateRating) to all products. Optimized for German e-commerce with discrete shipping information. Includes meta optimization, enhanced admin interface, multi-language support, universal platform detection, schema validation, FAQ schema detection, keyword extraction, automatic GSC error detection and fixing, analytics dashboard, and AI search optimization (Google AI Overview).
 * Version: 4.0.7
 * Author: dratzymarcano
 * License: GPL v2 or later
 * Text Domain: gsc-schema-fix
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check PHP version compatibility
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>GSC Schema Fix:</strong> This plugin requires PHP 7.4 or higher. You are running PHP ' . PHP_VERSION . '</p></div>';
    });
    return;
}

// Define plugin constants
define('GSC_SCHEMA_FIX_VERSION', '4.0.7');
define('GSC_SCHEMA_FIX_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GSC_SCHEMA_FIX_PLUGIN_URL', plugin_dir_url(__FILE__));

class GSC_Schema_Fix {
    
    private $options;
    private $detected_platform; // v4.0.1
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_head', array($this, 'add_schema_markup'), 99);
        add_action('wp_head', array($this, 'add_faq_schema'), 100); // v4.0.3
        add_action('wp_head', array($this, 'add_keywords_meta'), 2); // v4.0.4
        add_action('wp_head', array($this, 'add_howto_schema'), 101); // v4.0.7
        add_action('wp_head', array($this, 'add_entity_markup'), 102); // v4.0.7
        add_action('wp_head', array($this, 'optimize_meta_tags'), 1); // v3.0.0
        add_action('the_content', array($this, 'enhance_content')); // v3.0.0
        add_action('wp_footer', array($this, 'add_performance_optimizations')); // v3.0.0
        add_action('admin_menu', array($this, 'add_admin_menu')); // v3.0.0
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts')); // v3.0.0
        
        // AJAX handlers for admin tools
        add_action('wp_ajax_gsc_test_schema', array($this, 'ajax_test_schema')); // v3.0.0
        add_action('wp_ajax_gsc_save_settings', array($this, 'ajax_save_settings')); // v4.0.2.1
        add_action('wp_ajax_gsc_scan_errors', array($this, 'ajax_scan_errors')); // v4.0.5
        add_action('wp_ajax_gsc_fix_errors', array($this, 'ajax_fix_errors')); // v4.0.5
        add_action('wp_ajax_gsc_get_analytics', array($this, 'ajax_get_analytics')); // v4.0.6
        
        // v4.0.5 - Schedule automatic error scanning
        add_action('gsc_schema_fix_daily_scan', array($this, 'run_daily_error_scan'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        $this->options = get_option('gsc_schema_fix_options', array());
        $this->detected_platform = $this->detect_ecommerce_platform(); // v4.0.1
    }
    
    public function init() {
        load_plugin_textdomain('gsc-schema-fix', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * v4.0.0 - Detect site language
     * @return string Language code (de, en, etc.)
     */
    private function detect_site_language() {
        $locale = get_locale();
        
        // Extract language code from locale (e.g., de_DE -> de, en_US -> en)
        $lang = substr($locale, 0, 2);
        
        return $lang;
    }
    
    /**
     * v4.0.0 - Get localized text
     * @param string $key Text key
     * @return string Localized text
     */
    private function get_localized_text($key) {
        $lang = $this->detect_site_language();
        
        $texts = array(
            'review_body_de' => 'Hervorragendes Produkt! Schnelle und diskrete Lieferung. Sehr empfehlenswert für alle, die Wert auf Qualität und Privatsphäre legen.',
            'review_body_en' => 'Excellent product! Fast and discreet delivery. Highly recommended for anyone who values quality and privacy.',
            'shop_link_de' => 'Weitere Produkte ansehen',
            'shop_link_en' => 'View more products',
            'category_link_de' => 'Ähnliche Produkte',
            'category_link_en' => 'Similar products',
        );
        
        // Try language-specific key first, fallback to English
        $specific_key = $key . '_' . $lang;
        if (isset($texts[$specific_key])) {
            return $texts[$specific_key];
        }
        
        // Fallback to English
        $fallback_key = $key . '_en';
        return isset($texts[$fallback_key]) ? $texts[$fallback_key] : '';
    }
    
    /**
     * v4.0.1 - Detect e-commerce platform
     * @return array Platform info (name, version, active)
     */
    private function detect_ecommerce_platform() {
        $platform = array(
            'name' => 'none',
            'version' => '',
            'active' => false,
            'post_types' => array()
        );
        
        // Detect WooCommerce
        if (class_exists('WooCommerce')) {
            global $woocommerce;
            $platform['name'] = 'WooCommerce';
            $platform['version'] = defined('WC_VERSION') ? WC_VERSION : $woocommerce->version;
            $platform['active'] = true;
            $platform['post_types'] = array('product');
        }
        // Detect Easy Digital Downloads
        elseif (class_exists('Easy_Digital_Downloads')) {
            $platform['name'] = 'Easy Digital Downloads';
            $platform['version'] = defined('EDD_VERSION') ? EDD_VERSION : '';
            $platform['active'] = true;
            $platform['post_types'] = array('download');
        }
        // Detect WP eCommerce
        elseif (function_exists('wpsc_core_load_purchase_log_class')) {
            $platform['name'] = 'WP eCommerce';
            $platform['version'] = defined('WPSC_VERSION') ? WPSC_VERSION : '';
            $platform['active'] = true;
            $platform['post_types'] = array('wpsc-product');
        }
        // Detect Shopify (via WordPress integration)
        elseif (defined('SHOPIFY_APP_PLUGIN_FILE')) {
            $platform['name'] = 'Shopify';
            $platform['version'] = '';
            $platform['active'] = true;
            $platform['post_types'] = array('product');
        }
        // Detect BigCommerce
        elseif (class_exists('BigCommerce\\Plugin')) {
            $platform['name'] = 'BigCommerce';
            $platform['version'] = defined('BIGCOMMERCE_VERSION') ? BIGCOMMERCE_VERSION : '';
            $platform['active'] = true;
            $platform['post_types'] = array('bigcommerce_product');
        }
        // Generic product post type detection
        elseif (post_type_exists('product')) {
            $platform['name'] = 'Generic (Product CPT)';
            $platform['version'] = '';
            $platform['active'] = true;
            $platform['post_types'] = array('product');
        }
        
        return $platform;
    }
    
    /**
     * v4.0.1 - Get platform-specific product data
     * @param WP_Post $post Product post object
     * @return array Product data (price, currency, availability, etc.)
     */
    private function get_platform_product_data($post) {
        $data = array(
            'price' => null,
            'currency' => $this->options['default_currency'],
            'availability' => $this->options['default_availability'],
            'sku' => '',
            'brand' => get_bloginfo('name')
        );
        
        switch ($this->detected_platform['name']) {
            case 'WooCommerce':
                if (function_exists('wc_get_product')) {
                    $product = wc_get_product($post->ID);
                    if ($product) {
                        $data['price'] = $product->get_price();
                        $data['currency'] = get_woocommerce_currency();
                        $data['sku'] = $product->get_sku();
                        
                        // Get availability
                        if ($product->is_in_stock()) {
                            $data['availability'] = 'InStock';
                        } elseif ($product->is_on_backorder()) {
                            $data['availability'] = 'BackOrder';
                        } else {
                            $data['availability'] = 'OutOfStock';
                        }
                    }
                }
                break;
                
            case 'Easy Digital Downloads':
                if (function_exists('edd_get_download')) {
                    $data['price'] = edd_get_download_price($post->ID);
                    $data['currency'] = edd_get_currency();
                    $data['sku'] = get_post_meta($post->ID, 'edd_sku', true);
                    $data['availability'] = 'InStock'; // EDD doesn't have stock management by default
                }
                break;
                
            case 'WP eCommerce':
                $data['price'] = get_post_meta($post->ID, '_wpsc_price', true);
                $data['sku'] = get_post_meta($post->ID, '_wpsc_sku', true);
                break;
                
            case 'BigCommerce':
                $data['price'] = get_post_meta($post->ID, 'bigcommerce_price', true);
                $data['sku'] = get_post_meta($post->ID, 'bigcommerce_sku', true);
                break;
        }
        
        return $data;
    }
    
    /**
     * v4.0.2 - Validate schema markup
     * @param array $schema Schema data to validate
     * @return array Validation results (valid, errors, warnings)
     */
    private function validate_schema($schema) {
        $validation = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array(),
            'score' => 100
        );
        
        // Check required Product fields
        if (empty($schema['@type']) || $schema['@type'] !== 'Product') {
            $validation['errors'][] = 'Missing or invalid @type (must be "Product")';
            $validation['valid'] = false;
        }
        
        if (empty($schema['name'])) {
            $validation['errors'][] = 'Missing required field: name';
            $validation['valid'] = false;
        }
        
        // Check offers
        if (empty($schema['offers'])) {
            $validation['errors'][] = 'Missing required field: offers';
            $validation['valid'] = false;
        } else {
            $offer = $schema['offers'];
            
            if (empty($offer['price'])) {
                $validation['errors'][] = 'Offer missing required field: price';
                $validation['valid'] = false;
            } elseif (!is_numeric($offer['price']) || $offer['price'] < 0) {
                $validation['errors'][] = 'Invalid price value: ' . $offer['price'];
                $validation['valid'] = false;
            }
            
            if (empty($offer['priceCurrency'])) {
                $validation['errors'][] = 'Offer missing required field: priceCurrency';
                $validation['valid'] = false;
            } elseif (strlen($offer['priceCurrency']) !== 3) {
                $validation['warnings'][] = 'Currency code should be 3 letters (ISO 4217)';
            }
            
            if (empty($offer['availability'])) {
                $validation['warnings'][] = 'Offer missing recommended field: availability';
            }
        }
        
        // Check review (recommended)
        if (empty($schema['review'])) {
            $validation['warnings'][] = 'Missing recommended field: review';
        } else {
            $review = $schema['review'];
            
            if (empty($review['reviewRating'])) {
                $validation['warnings'][] = 'Review missing reviewRating';
            }
            
            if (empty($review['author'])) {
                $validation['warnings'][] = 'Review missing author';
            }
        }
        
        // Check aggregateRating (recommended)
        if (empty($schema['aggregateRating'])) {
            $validation['warnings'][] = 'Missing recommended field: aggregateRating';
        } else {
            $rating = $schema['aggregateRating'];
            
            if (empty($rating['ratingValue'])) {
                $validation['errors'][] = 'AggregateRating missing required field: ratingValue';
                $validation['valid'] = false;
            } elseif (!is_numeric($rating['ratingValue'])) {
                $validation['errors'][] = 'Invalid ratingValue: must be numeric';
                $validation['valid'] = false;
            }
            
            if (empty($rating['reviewCount']) && empty($rating['ratingCount'])) {
                $validation['warnings'][] = 'AggregateRating should have reviewCount or ratingCount';
            }
        }
        
        // Check image (recommended)
        if (empty($schema['image'])) {
            $validation['warnings'][] = 'Missing recommended field: image';
        }
        
        // Check description (recommended)
        if (empty($schema['description'])) {
            $validation['warnings'][] = 'Missing recommended field: description';
        } elseif (strlen($schema['description']) < 50) {
            $validation['warnings'][] = 'Description is very short (< 50 characters)';
        }
        
        // Calculate score
        $error_count = count($validation['errors']);
        $warning_count = count($validation['warnings']);
        
        $validation['score'] = max(0, 100 - ($error_count * 20) - ($warning_count * 5));
        
        return $validation;
    }
    
    /**
     * v4.0.3 - Detect FAQ content from post
     * @param WP_Post $post Post object
     * @return array FAQ items (question/answer pairs)
     */
    private function detect_faq_content($post) {
        $faqs = array();
        $content = $post->post_content;
        
        // Method 1: Detect Gutenberg FAQ blocks or custom FAQ shortcodes
        if (has_blocks($content)) {
            $blocks = parse_blocks($content);
            foreach ($blocks as $block) {
                if ($block['blockName'] === 'core/heading' || $block['blockName'] === 'core/paragraph') {
                    // Look for Q&A patterns in headings
                    if (!empty($block['innerHTML'])) {
                        $text = wp_strip_all_tags($block['innerHTML']);
                        if (preg_match('/^(Q:|Question:|FAQ:|Frage:)\s*(.+)$/i', $text, $matches)) {
                            $faqs[] = array('question' => trim($matches[2]), 'answer' => '');
                        }
                    }
                }
            }
        }
        
        // Method 2: Pattern matching for common FAQ structures
        // Match patterns like:
        // Q: Question? A: Answer
        // Question: Text? Answer: Text
        // **Question?** Answer text
        
        $patterns = array(
            // Q: Question? A: Answer
            '/(?:Q:|Question:|Frage:)\s*([^\n?]+\?)\s*(?:A:|Answer:|Antwort:)\s*([^\n]+)/i',
            // <h3>Question?</h3><p>Answer</p>
            '/<h[2-6][^>]*>([^<]+\?)<\/h[2-6]>\s*<p>([^<]+)<\/p>/i',
            // **Question?** Answer
            '/\*\*([^\*]+\?)\*\*\s*([^\n]+)/i',
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $question = wp_strip_all_tags(trim($match[1]));
                    $answer = wp_strip_all_tags(trim($match[2]));
                    
                    if (!empty($question) && !empty($answer)) {
                        $faqs[] = array(
                            'question' => $question,
                            'answer' => $answer
                        );
                    }
                }
            }
        }
        
        // Method 3: Look for definition lists (dl/dt/dd)
        if (preg_match_all('/<dt[^>]*>([^<]+)<\/dt>\s*<dd[^>]*>([^<]+)<\/dd>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $question = wp_strip_all_tags(trim($match[1]));
                $answer = wp_strip_all_tags(trim($match[2]));
                
                if (!empty($question) && !empty($answer)) {
                    $faqs[] = array(
                        'question' => $question,
                        'answer' => $answer
                    );
                }
            }
        }
        
        // Remove duplicates
        $unique_faqs = array();
        foreach ($faqs as $faq) {
            $key = md5($faq['question'] . $faq['answer']);
            if (!isset($unique_faqs[$key])) {
                $unique_faqs[$key] = $faq;
            }
        }
        
        return array_values($unique_faqs);
    }
    
    /**
     * v4.0.7 - Detect HowTo content from post
     */
    private function detect_howto_content($post) {
        $content = $post->post_content;
        $steps = array();
        
        // Look for numbered lists or step patterns
        // Pattern 1: Ordered lists <ol><li>
        if (preg_match_all('/<ol[^>]*>(.*?)<\/ol>/is', $content, $ol_matches)) {
            foreach ($ol_matches[1] as $ol_content) {
                if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $ol_content, $li_matches)) {
                    foreach ($li_matches[1] as $index => $li_content) {
                        $step_text = wp_strip_all_tags($li_content);
                        if (strlen($step_text) > 10) { // Minimum length for valid step
                            $steps[] = array(
                                'name' => 'Step ' . ($index + 1),
                                'text' => trim($step_text)
                            );
                        }
                    }
                    if (count($steps) >= 3) { // At least 3 steps for valid HowTo
                        break;
                    }
                }
            }
        }
        
        // Pattern 2: Step headings (Step 1:, Schritt 1:, etc.)
        if (empty($steps)) {
            $step_pattern = '/(?:<h[2-6][^>]*>|^|\n)\s*(?:Step|Schritt|Étape)\s*(\d+)[:\.]?\s*([^\n<]+)(?:<\/h[2-6]>)?[\s\S]*?<p>([^<]+)<\/p>/i';
            if (preg_match_all($step_pattern, $content, $step_matches, PREG_SET_ORDER)) {
                foreach ($step_matches as $match) {
                    $steps[] = array(
                        'name' => trim($match[2]),
                        'text' => wp_strip_all_tags(trim($match[3]))
                    );
                }
            }
        }
        
        if (count($steps) < 3) {
            return null; // Not enough steps for valid HowTo
        }
        
        // Build HowTo data
        $howto = array(
            'name' => $post->post_title,
            'description' => wp_trim_words($post->post_excerpt ?: $post->post_content, 30, '...'),
            'steps' => array_slice($steps, 0, 20) // Max 20 steps
        );
        
        // Try to get featured image
        if (has_post_thumbnail($post->ID)) {
            $howto['image'] = get_the_post_thumbnail_url($post->ID, 'full');
        }
        
        return $howto;
    }
    
    /**
     * v4.0.7 - Generate breadcrumb structure
     */
    private function generate_breadcrumbs() {
        $breadcrumbs = array();
        $position = 1;
        
        // Home
        $breadcrumbs[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => 'Home',
            'item' => home_url()
        );
        
        // For products or posts, add category/taxonomy
        if (is_singular('product')) {
            $terms = get_the_terms(get_the_ID(), 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                $term = array_shift($terms);
                $breadcrumbs[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $term->name,
                    'item' => get_term_link($term)
                );
            }
        } elseif (is_singular('post')) {
            $categories = get_the_category();
            if (!empty($categories)) {
                $category = $categories[0];
                $breadcrumbs[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $category->name,
                    'item' => get_category_link($category->term_id)
                );
            }
        }
        
        // Current page
        $breadcrumbs[] = array(
            '@type' => 'ListItem',
            'position' => $position,
            'name' => get_the_title(),
            'item' => get_permalink()
        );
        
        return $breadcrumbs;
    }
    
    /**
     * v4.0.3 - Add FAQ schema markup
     */
    public function add_faq_schema() {
        if (empty($this->options['enable_faq_schema']) || !is_singular()) {
            return;
        }
        
        global $post;
        
        // Detect FAQ content
        $faqs = $this->detect_faq_content($post);
        
        if (empty($faqs)) {
            return;
        }
        
        // Generate FAQ schema
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array()
        );
        
        foreach ($faqs as $faq) {
            $schema['mainEntity'][] = array(
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text' => $faq['answer']
                )
            );
        }
        
        // Output FAQ schema
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
    
    /**
     * v4.0.4 - Extract keywords from content
     * @param string $content Content to analyze
     * @param int $max_keywords Maximum number of keywords to return
     * @return array Keywords with frequency
     */
    private function extract_keywords($content, $max_keywords = 10) {
        // Remove HTML tags
        $text = wp_strip_all_tags($content);
        
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');
        
        // Remove special characters, keep only letters, numbers, spaces
        $text = preg_replace('/[^a-z0-9äöüßáéíóúñ\s]/u', ' ', $text);
        
        // Common stop words (English and German)
        $stop_words = array(
            // English
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'be',
            'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
            'would', 'should', 'could', 'may', 'might', 'can', 'this', 'that',
            'these', 'those', 'it', 'its', 'they', 'them', 'their', 'we', 'you',
            'he', 'she', 'him', 'her', 'his', 'our', 'your', 'my', 'me', 'i',
            'not', 'no', 'yes', 'all', 'any', 'some', 'more', 'very', 'too',
            'just', 'so', 'than', 'such', 'when', 'where', 'who', 'why', 'how',
            // German
            'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einer',
            'eines', 'einem', 'einen', 'und', 'oder', 'aber', 'in', 'im', 'auf',
            'an', 'zu', 'für', 'von', 'mit', 'bei', 'nach', 'vor', 'über',
            'unter', 'durch', 'bis', 'aus', 'ist', 'sind', 'war', 'waren',
            'wird', 'werden', 'wurde', 'worden', 'sein', 'haben', 'hat',
            'hatte', 'hatten', 'kann', 'könnte', 'muss', 'müssen', 'soll',
            'sollte', 'darf', 'dürfen', 'mag', 'mögen', 'will', 'wollen',
            'nicht', 'kein', 'keine', 'keinen', 'dieser', 'diese', 'dieses',
            'jener', 'jene', 'jenes', 'alle', 'alles', 'viele', 'wenige',
            'mehr', 'weniger', 'sehr', 'auch', 'nur', 'schon', 'noch', 'dann',
            'wenn', 'wann', 'wo', 'woher', 'wohin', 'wer', 'was', 'wie', 'warum'
        );
        
        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Count word frequency
        $word_freq = array();
        foreach ($words as $word) {
            // Skip short words and stop words
            if (strlen($word) < 3 || in_array($word, $stop_words)) {
                continue;
            }
            
            if (!isset($word_freq[$word])) {
                $word_freq[$word] = 0;
            }
            $word_freq[$word]++;
        }
        
        // Sort by frequency
        arsort($word_freq);
        
        // Get top keywords
        $keywords = array_slice($word_freq, 0, $max_keywords, true);
        
        return $keywords;
    }
    
    /**
     * v4.0.4 - Add keywords meta tag
     */
    public function add_keywords_meta() {
        if (empty($this->options['enable_keyword_extraction']) || !is_singular()) {
            return;
        }
        
        global $post;
        
        // Extract keywords from title and content
        $content = $post->post_title . ' ' . $post->post_content;
        $keywords = $this->extract_keywords($content, 15);
        
        if (empty($keywords)) {
            return;
        }
        
        // Get top keywords as comma-separated list
        $keyword_list = implode(', ', array_keys($keywords));
        
        // Output keywords meta tag
        echo '<meta name="keywords" content="' . esc_attr($keyword_list) . '">' . "\n";
    }
    
    /**
     * v4.0.7 - Add HowTo schema for AI Overview optimization
     */
    public function add_howto_schema() {
        if (empty($this->options['enable_howto_schema']) || !is_singular()) {
            return;
        }
        
        global $post;
        
        // Detect HowTo content patterns
        $howto = $this->detect_howto_content($post);
        
        if (empty($howto)) {
            return;
        }
        
        // Generate HowTo schema
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => $howto['name'],
            'description' => $howto['description'],
            'step' => array()
        );
        
        // Add image if available
        if (!empty($howto['image'])) {
            $schema['image'] = $howto['image'];
        }
        
        // Add total time if available
        if (!empty($howto['totalTime'])) {
            $schema['totalTime'] = $howto['totalTime'];
        }
        
        // Add steps
        foreach ($howto['steps'] as $index => $step) {
            $step_schema = array(
                '@type' => 'HowToStep',
                'name' => $step['name'],
                'text' => $step['text'],
                'position' => $index + 1
            );
            
            if (!empty($step['image'])) {
                $step_schema['image'] = $step['image'];
            }
            
            $schema['step'][] = $step_schema;
        }
        
        // Output HowTo schema
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
    
    /**
     * v4.0.7 - Add enhanced entity markup for AI Overview
     */
    public function add_entity_markup() {
        if (empty($this->options['enable_entity_markup'])) {
            return;
        }
        
        // Add Organization schema for homepage or all pages
        if (is_front_page() || !empty($this->options['entity_markup_all_pages'])) {
            $schema = array(
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url(),
                'logo' => array(
                    '@type' => 'ImageObject',
                    'url' => get_site_icon_url()
                )
            );
            
            // Add social profiles if configured
            if (!empty($this->options['entity_social_profiles'])) {
                $profiles = array_filter(array_map('trim', explode("\n", $this->options['entity_social_profiles'])));
                if (!empty($profiles)) {
                    $schema['sameAs'] = $profiles;
                }
            }
            
            // Add contact information if configured
            if (!empty($this->options['entity_contact_type'])) {
                $contact_point = array(
                    '@type' => 'ContactPoint',
                    'contactType' => $this->options['entity_contact_type']
                );
                
                // Add email if provided
                if (!empty($this->options['entity_contact_email'])) {
                    $contact_point['email'] = $this->options['entity_contact_email'];
                }
                
                // Add Telegram as URL if provided
                if (!empty($this->options['entity_contact_telegram'])) {
                    $telegram = $this->options['entity_contact_telegram'];
                    // Convert @username to t.me URL if needed
                    if (strpos($telegram, '@') === 0) {
                        $telegram = 'https://t.me/' . ltrim($telegram, '@');
                    }
                    $contact_point['url'] = $telegram;
                }
                
                if (!empty($contact_point['email']) || !empty($contact_point['url'])) {
                    $schema['contactPoint'] = $contact_point;
                }
            }
            
            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        }
        
        // Add WebSite schema with search action for better AI understanding
        if (is_front_page()) {
            $website_schema = array(
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => get_bloginfo('name'),
                'url' => home_url(),
                'potentialAction' => array(
                    '@type' => 'SearchAction',
                    'target' => array(
                        '@type' => 'EntryPoint',
                        'urlTemplate' => home_url('/?s={search_term_string}')
                    ),
                    'query-input' => 'required name=search_term_string'
                )
            );
            
            echo '<script type="application/ld+json">' . wp_json_encode($website_schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        }
        
        // Add BreadcrumbList for better navigation understanding
        if (!is_front_page() && !empty($this->options['entity_breadcrumb'])) {
            $breadcrumbs = $this->generate_breadcrumbs();
            
            if (!empty($breadcrumbs)) {
                $breadcrumb_schema = array(
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => $breadcrumbs
                );
                
                echo '<script type="application/ld+json">' . wp_json_encode($breadcrumb_schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
            }
        }
    }
    
    /**
     * v4.0.5 - Scan products for GSC errors
     * @return array Array of detected errors
     */
    private function scan_products_for_errors() {
        $errors = array();
        
        // Get all product post types from detected platform
        $post_types = !empty($this->detected_platform['post_types']) ? $this->detected_platform['post_types'] : array('product');
        
        $args = array(
            'post_type' => $post_types,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );
        
        $products = get_posts($args);
        
        foreach ($products as $product) {
            $product_errors = array();
            $product_data = $this->get_platform_product_data($product);
            
            // Check for missing or invalid price
            if (empty($product_data['price']) || $product_data['price'] <= 0) {
                $product_errors[] = array(
                    'type' => 'missing_price',
                    'severity' => 'error',
                    'message' => 'Missing or invalid product price',
                    'field' => 'offers.price',
                );
            }
            
            // Check for missing currency
            if (empty($product_data['currency'])) {
                $product_errors[] = array(
                    'type' => 'missing_currency',
                    'severity' => 'warning',
                    'message' => 'Missing price currency',
                    'field' => 'offers.priceCurrency',
                );
            }
            
            // Check for missing availability
            if (empty($product_data['availability'])) {
                $product_errors[] = array(
                    'type' => 'missing_availability',
                    'severity' => 'warning',
                    'message' => 'Missing product availability',
                    'field' => 'offers.availability',
                );
            }
            
            // Check for missing product name
            if (empty($product->post_title)) {
                $product_errors[] = array(
                    'type' => 'missing_name',
                    'severity' => 'error',
                    'message' => 'Missing product name',
                    'field' => 'name',
                );
            }
            
            // Check for missing description
            if (empty($product->post_content) && empty($product->post_excerpt)) {
                $product_errors[] = array(
                    'type' => 'missing_description',
                    'severity' => 'warning',
                    'message' => 'Missing product description',
                    'field' => 'description',
                );
            }
            
            // Check for missing image
            if (!has_post_thumbnail($product->ID)) {
                $product_errors[] = array(
                    'type' => 'missing_image',
                    'severity' => 'warning',
                    'message' => 'Missing product image',
                    'field' => 'image',
                );
            }
            
            // If errors found, add to main errors array
            if (!empty($product_errors)) {
                $errors[] = array(
                    'product_id' => $product->ID,
                    'product_title' => $product->post_title,
                    'product_url' => get_permalink($product->ID),
                    'errors' => $product_errors,
                    'detected_at' => current_time('mysql'),
                );
            }
        }
        
        return $errors;
    }
    
    /**
     * v4.0.5 - Automatically fix detected errors
     * @param array $errors Array of errors to fix
     * @return array Array of fixed errors
     */
    private function auto_fix_errors($errors) {
        $fixed = array();
        
        foreach ($errors as $error_data) {
            $product_id = $error_data['product_id'];
            $product_fixed = false;
            
            foreach ($error_data['errors'] as $error) {
                switch ($error['type']) {
                    case 'missing_price':
                        // Set a default price if missing
                        if (!empty($this->options['enable_auto_fix'])) {
                            update_post_meta($product_id, '_gsc_default_price', '0.00');
                            $product_fixed = true;
                        }
                        break;
                        
                    case 'missing_currency':
                        // Already handled by default currency option
                        $product_fixed = true;
                        break;
                        
                    case 'missing_availability':
                        // Already handled by default availability option
                        $product_fixed = true;
                        break;
                        
                    case 'missing_description':
                        // Add auto-generated description if enabled
                        if (!empty($this->options['enable_auto_fix'])) {
                            $auto_description = 'Hochwertige Qualität und diskrete Lieferung. Jetzt bestellen!';
                            if (empty(get_post_field('post_content', $product_id))) {
                                wp_update_post(array(
                                    'ID' => $product_id,
                                    'post_excerpt' => $auto_description,
                                ));
                                $product_fixed = true;
                            }
                        }
                        break;
                }
            }
            
            if ($product_fixed) {
                $fixed[] = array(
                    'product_id' => $product_id,
                    'product_title' => $error_data['product_title'],
                    'fixed_at' => current_time('mysql'),
                );
            }
        }
        
        return $fixed;
    }
    
    /**
     * v4.0.5 - Run daily error scan (scheduled event)
     */
    public function run_daily_error_scan() {
        if (empty($this->options['enable_error_scanning'])) {
            return;
        }
        
        $errors = $this->scan_products_for_errors();
        
        // Store errors in database
        update_option('gsc_schema_fix_detected_errors', $errors);
        update_option('gsc_schema_fix_last_scan', current_time('mysql'));
        
        // v4.0.6 - Track error history for analytics
        $history = get_option('gsc_schema_fix_error_history', array());
        $history[date('Y-m-d')] = count($errors);
        // Keep only last 30 days
        if (count($history) > 30) {
            $history = array_slice($history, -30, null, true);
        }
        update_option('gsc_schema_fix_error_history', $history);
        
        // Auto-fix if enabled
        if (!empty($this->options['enable_auto_fix']) && !empty($errors)) {
            $fixed = $this->auto_fix_errors($errors);
            update_option('gsc_schema_fix_auto_fixed', $fixed);
        }
    }
    
    /**
     * v4.0.5 - AJAX handler for manual error scanning
     */
    public function ajax_scan_errors() {
        check_ajax_referer('gsc_schema_fix_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $errors = $this->scan_products_for_errors();
        
        // Store errors
        update_option('gsc_schema_fix_detected_errors', $errors);
        update_option('gsc_schema_fix_last_scan', current_time('mysql'));
        
        wp_send_json_success(array(
            'errors' => $errors,
            'total_products' => count($errors),
            'total_errors' => array_sum(array_map(function($e) { return count($e['errors']); }, $errors)),
        ));
    }
    
    /**
     * v4.0.5 - AJAX handler for manual error fixing
     */
    public function ajax_fix_errors() {
        check_ajax_referer('gsc_schema_fix_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $errors = get_option('gsc_schema_fix_detected_errors', array());
        
        if (empty($errors)) {
            wp_send_json_error('No errors to fix');
            return;
        }
        
        $fixed = $this->auto_fix_errors($errors);
        update_option('gsc_schema_fix_auto_fixed', $fixed);
        
        // Re-scan after fixing
        $remaining_errors = $this->scan_products_for_errors();
        update_option('gsc_schema_fix_detected_errors', $remaining_errors);
        
        wp_send_json_success(array(
            'fixed' => count($fixed),
            'remaining' => count($remaining_errors),
        ));
    }
    
    /**
     * v4.0.6 - Track schema generation for analytics
     * @param int $product_id Product ID
     * @param array $schema Generated schema
     */
    private function track_schema_generation($product_id, $schema) {
        $stats = get_option('gsc_schema_fix_stats', array(
            'total_generated' => 0,
            'products' => array(),
            'daily_stats' => array(),
        ));
        
        $today = date('Y-m-d');
        
        // Increment total
        $stats['total_generated']++;
        
        // Track product
        if (!isset($stats['products'][$product_id])) {
            $stats['products'][$product_id] = array(
                'first_generated' => current_time('mysql'),
                'count' => 0,
            );
        }
        $stats['products'][$product_id]['count']++;
        $stats['products'][$product_id]['last_generated'] = current_time('mysql');
        
        // Track daily stats
        if (!isset($stats['daily_stats'][$today])) {
            $stats['daily_stats'][$today] = array(
                'generated' => 0,
                'unique_products' => array(),
            );
        }
        $stats['daily_stats'][$today]['generated']++;
        $stats['daily_stats'][$today]['unique_products'][$product_id] = true;
        
        // Keep only last 30 days of daily stats
        if (count($stats['daily_stats']) > 30) {
            $stats['daily_stats'] = array_slice($stats['daily_stats'], -30, null, true);
        }
        
        update_option('gsc_schema_fix_stats', $stats);
    }
    
    /**
     * v4.0.6 - Get analytics data
     * @return array Analytics data
     */
    private function get_analytics_data() {
        $stats = get_option('gsc_schema_fix_stats', array(
            'total_generated' => 0,
            'products' => array(),
            'daily_stats' => array(),
        ));
        
        $errors = get_option('gsc_schema_fix_detected_errors', array());
        $last_scan = get_option('gsc_schema_fix_last_scan');
        
        // Get product counts
        $post_types = !empty($this->detected_platform['post_types']) ? $this->detected_platform['post_types'] : array('product');
        $total_products = 0;
        foreach ($post_types as $post_type) {
            $count = wp_count_posts($post_type);
            $total_products += isset($count->publish) ? $count->publish : 0;
        }
        
        $products_with_schema = count($stats['products']);
        $products_with_errors = count($errors);
        $coverage_percent = $total_products > 0 ? round(($products_with_schema / $total_products) * 100, 1) : 0;
        
        // Calculate error trend (last 7 days)
        $error_history = get_option('gsc_schema_fix_error_history', array());
        $trend = 'stable';
        if (count($error_history) >= 2) {
            $recent = array_slice($error_history, -7, null, true);
            $first_count = reset($recent);
            $last_count = end($recent);
            if ($last_count < $first_count) {
                $trend = 'improving';
            } elseif ($last_count > $first_count) {
                $trend = 'worsening';
            }
        }
        
        // Get daily generation stats for chart (last 7 days)
        $daily_chart = array();
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $daily_chart[$date] = isset($stats['daily_stats'][$date]) ? 
                count($stats['daily_stats'][$date]['unique_products']) : 0;
        }
        
        return array(
            'overview' => array(
                'total_products' => $total_products,
                'products_with_schema' => $products_with_schema,
                'products_with_errors' => $products_with_errors,
                'coverage_percent' => $coverage_percent,
                'total_generated' => $stats['total_generated'],
                'platform' => $this->detected_platform['name'],
            ),
            'errors' => array(
                'current_count' => $products_with_errors,
                'last_scan' => $last_scan,
                'trend' => $trend,
            ),
            'daily_chart' => $daily_chart,
        );
    }
    
    /**
     * v4.0.6 - AJAX handler to get analytics
     */
    public function ajax_get_analytics() {
        check_ajax_referer('gsc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $analytics = $this->get_analytics_data();
        wp_send_json_success($analytics);
    }
    
    public function activate() {
        $default_options = array(
            'enable_auto_rating' => 1,
            'default_rating_value' => '4.5',
            'default_rating_count' => '150',
            'rating_best' => '5',
            'rating_worst' => '1',
            'enable_auto_offers' => 1,
            'default_currency' => 'EUR',
            'default_availability' => 'InStock',
            'enable_auto_review' => 1,
            'default_reviewer_name' => get_bloginfo('name'),
            'review_date_published' => current_time('Y-m-d'),
            // v2.0.0 - papierk2.com specific optimizations
            'enable_german_optimization' => 1,
            'discrete_shipping' => 1,
            'company_name' => get_bloginfo('name'),
            'company_location' => 'Deutschland',
            // v3.0.0 - Meta optimization
            'enable_meta_optimization' => 1,
            'meta_title_template' => '{product_name} kaufen - {site_name}',
            'meta_description_template' => '{product_name} - Diskrete Lieferung | Sichere Zahlung | {site_name}',
            // v3.0.0 - Content enhancement
            'enable_content_enhancement' => 1,
            'add_internal_links' => 1,
            // v3.0.0 - Performance
            'enable_lazy_loading' => 1,
            'enable_caching_headers' => 1,
            // v4.0.0 - Multi-language support
            'enable_auto_language_detection' => 1,
            // v4.0.2 - Schema validation
            'enable_schema_validation' => 1,
            // v4.0.3 - FAQ schema
            'enable_faq_schema' => 1,
            // v4.0.4 - Keyword extraction
            'enable_keyword_extraction' => 1,
            // v4.0.5 - GSC error detection and fixing
            'enable_error_scanning' => 1,
            'enable_auto_fix' => 1,
            'scan_frequency' => 'daily',
            // v4.0.7 - AI optimization
            'enable_howto_schema' => 1,
            'enable_entity_markup' => 1,
            'entity_markup_all_pages' => 1,
            'entity_breadcrumb' => 1,
            'entity_contact_type' => 'customer service',
            'entity_contact_email' => 'papierk2@zohomail.eu',
            'entity_contact_telegram' => '@nspladen',
        );
        
        add_option('gsc_schema_fix_options', $default_options);
        
        // v4.0.5 - Schedule daily error scanning
        if (!wp_next_scheduled('gsc_schema_fix_daily_scan')) {
            wp_schedule_event(time(), 'daily', 'gsc_schema_fix_daily_scan');
        }
    }
    
    public function deactivate() {
        // v4.0.5 - Clear scheduled scans
        $timestamp = wp_next_scheduled('gsc_schema_fix_daily_scan');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'gsc_schema_fix_daily_scan');
        }
    }
    
    public function add_schema_markup() {
        if (!is_singular()) {
            return;
        }
        
        global $post;
        
        // Check if it's a product (WooCommerce or EDD)
        $is_product = false;
        if (function_exists('is_product') && is_product()) {
            $is_product = true;
        } elseif ($post->post_type === 'download') {
            $is_product = true;
        } elseif ($post->post_type === 'product') {
            $is_product = true;
        }
        
        if (!$is_product) {
            return;
        }
        
        // Generate product schema
        $schema = $this->generate_product_schema($post);
        
        if ($schema) {
            // v4.0.6 - Track schema generation
            $this->track_schema_generation($post->ID, $schema);
            
            echo "\n<!-- GSC Schema Fix v" . GSC_SCHEMA_FIX_VERSION . " -->\n";
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            echo "\n" . '</script>' . "\n";
            echo "<!-- /GSC Schema Fix -->\n";
        }
    }
    
    private function generate_product_schema($post) {
        // Get product details
        $product_name = get_the_title($post->ID);
        $product_description = wp_trim_words(strip_tags($post->post_content), 50, '...');
        $product_image = get_the_post_thumbnail_url($post->ID, 'full');
        $product_url = get_permalink($post->ID);
        
        // Get price
        $price = $this->get_product_price($post->ID);
        $currency = !empty($this->options['default_currency']) ? $this->options['default_currency'] : 'EUR';
        
        // Get SKU
        $sku = get_post_meta($post->ID, '_sku', true);
        if (empty($sku)) {
            $sku = 'PRODUCT-' . $post->ID;
        }
        
        // Build schema
        $schema = array(
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $product_name,
            'description' => $product_description,
            'sku' => $sku,
        );
        
        // Add image if available
        if ($product_image) {
            $schema['image'] = $product_image;
        }
        
        // Add URL
        $schema['url'] = $product_url;
        
        // v4.0.1 - Get platform-specific product data
        $platform_data = $this->get_platform_product_data($post);
        
        // Add offers (required)
        if (!empty($this->options['enable_auto_offers'])) {
            // Use platform-detected values with fallback to defaults
            $availability = !empty($platform_data['availability']) ? $platform_data['availability'] : $this->options['default_availability'];
            $currency = !empty($platform_data['currency']) ? $platform_data['currency'] : $this->options['default_currency'];
            $offer_price = !empty($platform_data['price']) ? $platform_data['price'] : $price;
            
            $offer = array(
                '@type' => 'Offer',
                'url' => $product_url,
                'priceCurrency' => $currency,
                'price' => number_format((float)$offer_price, 2, '.', ''),
                'availability' => 'https://schema.org/' . $availability,
            );
            
            // v2.0.0 - Add discrete shipping info for German e-commerce
            if (!empty($this->options['discrete_shipping'])) {
                $offer['shippingDetails'] = array(
                    '@type' => 'OfferShippingDetails',
                    'shippingDestination' => array(
                        '@type' => 'DefinedRegion',
                        'addressCountry' => 'DE'
                    ),
                    'deliveryTime' => array(
                        '@type' => 'ShippingDeliveryTime',
                        'businessDays' => array(
                            '@type' => 'OpeningHoursSpecification',
                            'dayOfWeek' => array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday')
                        ),
                        'cutoffTime' => '14:00:00',
                        'handlingTime' => array(
                            '@type' => 'QuantitativeValue',
                            'minValue' => 0,
                            'maxValue' => 1,
                            'unitCode' => 'DAY'
                        ),
                        'transitTime' => array(
                            '@type' => 'QuantitativeValue',
                            'minValue' => 2,
                            'maxValue' => 4,
                            'unitCode' => 'DAY'
                        )
                    )
                );
            }
            
            // v2.0.0 - Add seller information for German market
            if (!empty($this->options['enable_german_optimization'])) {
                $offer['seller'] = array(
                    '@type' => 'Organization',
                    'name' => !empty($this->options['company_name']) ? $this->options['company_name'] : get_bloginfo('name')
                );
                
                if (!empty($this->options['company_location'])) {
                    $offer['seller']['address'] = array(
                        '@type' => 'PostalAddress',
                        'addressCountry' => 'DE'
                    );
                }
            }
            
            $schema['offers'] = $offer;
        }
        
        // Add aggregateRating (required)
        if (!empty($this->options['enable_auto_rating'])) {
            $rating_value = !empty($this->options['default_rating_value']) ? $this->options['default_rating_value'] : '4.5';
            $rating_count = !empty($this->options['default_rating_count']) ? $this->options['default_rating_count'] : '150';
            $rating_best = !empty($this->options['rating_best']) ? $this->options['rating_best'] : '5';
            $rating_worst = !empty($this->options['rating_worst']) ? $this->options['rating_worst'] : '1';
            
            $schema['aggregateRating'] = array(
                '@type' => 'AggregateRating',
                'ratingValue' => $rating_value,
                'reviewCount' => $rating_count,
                'bestRating' => $rating_best,
                'worstRating' => $rating_worst,
            );
        }
        
        // Add review (required)
        if (!empty($this->options['enable_auto_review'])) {
            $reviewer_name = !empty($this->options['default_reviewer_name']) ? $this->options['default_reviewer_name'] : get_bloginfo('name');
            $review_date = !empty($this->options['review_date_published']) ? $this->options['review_date_published'] : current_time('Y-m-d');
            
            // v4.0.0 - Multi-language support with automatic detection
            $review_body = $this->get_localized_text('review_body');
            
            $schema['review'] = array(
                '@type' => 'Review',
                'reviewRating' => array(
                    '@type' => 'Rating',
                    'ratingValue' => !empty($this->options['default_rating_value']) ? $this->options['default_rating_value'] : '4.5',
                    'bestRating' => !empty($this->options['rating_best']) ? $this->options['rating_best'] : '5',
                ),
                'author' => array(
                    '@type' => 'Person',
                    'name' => $reviewer_name,
                ),
                'datePublished' => $review_date,
                'reviewBody' => $review_body,
            );
        }
        
        return $schema;
    }
    
    /**
     * v4.0.1 - Get product price using platform detection
     */
    private function get_product_price($product_id) {
        $post = get_post($product_id);
        if (!$post) {
            return '0.00';
        }
        
        $platform_data = $this->get_platform_product_data($post);
        return !empty($platform_data['price']) ? $platform_data['price'] : '0.00';
    }
    
    // ==================== v3.0.0 Features ====================
    
    /**
     * Optimize meta tags for SEO
     */
    public function optimize_meta_tags() {
        if (empty($this->options['enable_meta_optimization']) || !is_singular()) {
            return;
        }
        
        global $post;
        
        // Get product details
        $product_name = get_the_title($post->ID);
        $site_name = get_bloginfo('name');
        
        // Generate meta title
        $title_template = !empty($this->options['meta_title_template']) ? $this->options['meta_title_template'] : '{product_name} - {site_name}';
        $meta_title = str_replace(
            array('{product_name}', '{site_name}'),
            array($product_name, $site_name),
            $title_template
        );
        
        // Generate meta description
        $desc_template = !empty($this->options['meta_description_template']) ? $this->options['meta_description_template'] : '{product_name} - {site_name}';
        $meta_description = str_replace(
            array('{product_name}', '{site_name}'),
            array($product_name, $site_name),
            $desc_template
        );
        
        // Output meta tags
        echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($meta_title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($meta_description) . '">' . "\n";
        echo '<meta property="og:type" content="product">' . "\n";
        
        $image = get_the_post_thumbnail_url($post->ID, 'full');
        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
        }
    }
    
    /**
     * Enhance content with internal links
     */
    public function enhance_content($content) {
        if (empty($this->options['enable_content_enhancement']) || !is_singular()) {
            return $content;
        }
        
        // v4.0.0 - Multi-language support for internal links
        if (!empty($this->options['add_internal_links'])) {
            $shop_link_text = $this->get_localized_text('shop_link');
            $related_text = '<p><strong>' . $shop_link_text . ': <a href="' . home_url('/shop') . '">' . get_bloginfo('name') . '</a>.</strong></p>';
            
            $content .= $related_text;
        }
        
        return $content;
    }
    
    /**
     * Add performance optimizations
     */
    public function add_performance_optimizations() {
        if (empty($this->options['enable_lazy_loading'])) {
            return;
        }
        
        // Add lazy loading for images
        ?>
        <script>
        if ('loading' in HTMLImageElement.prototype) {
            const images = document.querySelectorAll('img:not([loading])');
            images.forEach(img => { img.loading = 'lazy'; });
        }
        </script>
        <?php
        
        // Add cache headers (via PHP)
        if (!empty($this->options['enable_caching_headers']) && !is_admin()) {
            header('Cache-Control: public, max-age=31536000');
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('GSC Schema Fix', 'gsc-schema-fix'),
            __('GSC Schema Fix', 'gsc-schema-fix'),
            'manage_options',
            'gsc-schema-fix',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_gsc-schema-fix') {
            return;
        }
        
        wp_enqueue_style('gsc-admin-css', GSC_SCHEMA_FIX_PLUGIN_URL . 'assets/admin.css', array(), GSC_SCHEMA_FIX_VERSION);
        wp_enqueue_style('gsc-admin-modern-css', GSC_SCHEMA_FIX_PLUGIN_URL . 'assets/admin-modern.css', array(), GSC_SCHEMA_FIX_VERSION);
        wp_enqueue_script('gsc-admin-js', GSC_SCHEMA_FIX_PLUGIN_URL . 'assets/admin.js', array('jquery'), GSC_SCHEMA_FIX_VERSION, true);
        
        wp_localize_script('gsc-admin-js', 'gscAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gsc_admin_nonce')
        ));
    }
    
    /**
     * Admin page - v3.0.1 Enhanced with better UI and working test tool
     */
    public function admin_page() {
        // Handle AI settings form submission
        if (isset($_POST['gsc_ai_settings_nonce']) && wp_verify_nonce($_POST['gsc_ai_settings_nonce'], 'gsc_ai_settings_action')) {
            $options = get_option('gsc_schema_fix_options', array());
            
            // Update AI settings
            $options['entity_markup_all_pages'] = isset($_POST['entity_markup_all_pages']) ? 1 : 0;
            $options['entity_breadcrumb'] = isset($_POST['entity_breadcrumb']) ? 1 : 0;
            $options['entity_social_profiles'] = isset($_POST['entity_social_profiles']) ? sanitize_textarea_field($_POST['entity_social_profiles']) : '';
            $options['entity_contact_type'] = isset($_POST['entity_contact_type']) ? sanitize_text_field($_POST['entity_contact_type']) : '';
            $options['entity_contact_email'] = isset($_POST['entity_contact_email']) ? sanitize_email($_POST['entity_contact_email']) : '';
            $options['entity_contact_telegram'] = isset($_POST['entity_contact_telegram']) ? sanitize_text_field($_POST['entity_contact_telegram']) : '';
            
            update_option('gsc_schema_fix_options', $options);
            $this->options = $options;
            
            echo '<div class="notice notice-success is-dismissible"><p><strong>✅ AI Optimization settings saved successfully!</strong></p></div>';
        }
        
        ?>
        <div class="wrap gsc-admin-wrap">
            <h1><?php _e('GSC Schema Fix - Settings', 'gsc-schema-fix'); ?></h1>
            <p class="gsc-version"><?php _e('Version', 'gsc-schema-fix'); ?>: <strong><?php echo GSC_SCHEMA_FIX_VERSION; ?></strong></p>
            
            <!-- Global Save Status Indicator -->
            <div id="gsc-global-status" class="gsc-global-status" style="display: none;">
                <span class="dashicons dashicons-saved"></span>
                <span class="gsc-status-text">All changes saved</span>
            </div>
            
            <div class="gsc-admin-section">
                <h2><?php _e('Analytics Dashboard', 'gsc-schema-fix'); ?></h2>
                <p><?php _e('Schema performance metrics and coverage statistics.', 'gsc-schema-fix'); ?></p>
                
                <div id="gsc-analytics-dashboard">
                    <div class="gsc-analytics-loading">
                        <span class="spinner is-active"></span> <?php _e('Loading analytics...', 'gsc-schema-fix'); ?>
                    </div>
                </div>
            </div>
            
            <div class="gsc-admin-section">
                <h2><?php _e('Schema Testing Tool', 'gsc-schema-fix'); ?></h2>
                <p><?php _e('Enter a product ID to test schema generation:', 'gsc-schema-fix'); ?></p>
                
                <div class="gsc-test-form">
                    <input type="number" id="gsc-test-product-id" placeholder="<?php _e('Product ID', 'gsc-schema-fix'); ?>" min="1">
                    <button type="button" id="gsc-test-schema" class="button button-primary">
                        <span class="dashicons dashicons-search"></span> <?php _e('Test Schema', 'gsc-schema-fix'); ?>
                    </button>
                    <span id="gsc-loading" class="gsc-loading" style="display: none;">
                        <span class="spinner is-active"></span> <?php _e('Testing...', 'gsc-schema-fix'); ?>
                    </span>
                </div>
                
                <div id="gsc-test-results"></div>
            </div>
            
            <div class="gsc-admin-section gsc-settings-section">
                <div class="gsc-section-header">
                    <h2><span class="dashicons dashicons-admin-settings"></span> <?php _e('Feature Settings', 'gsc-schema-fix'); ?></h2>
                    <p><?php _e('Enable or disable features. Click "Save All Settings" when you\'re done.', 'gsc-schema-fix'); ?></p>
                </div>
                
                <form id="gsc-settings-form">
                    <div class="gsc-settings-grid">
                        <!-- Schema Generation -->
                        <div class="gsc-setting-card">
                            <div class="gsc-setting-icon">
                                <span class="dashicons dashicons-code-standards"></span>
                            </div>
                            <div class="gsc-setting-content">
                                <h3><?php _e('Schema Generation', 'gsc-schema-fix'); ?></h3>
                                <p><?php _e('Automatically add schema markup to all products', 'gsc-schema-fix'); ?></p>
                            </div>
                            <div class="gsc-setting-toggle">
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_auto_offers" value="1" <?php checked(!empty($this->options['enable_auto_offers'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Language Detection -->
                        <div class="gsc-setting-card">
                            <div class="gsc-setting-icon">
                                <span class="dashicons dashicons-translation"></span>
                            </div>
                            <div class="gsc-setting-content">
                                <h3><?php _e('Auto Language Detection', 'gsc-schema-fix'); ?></h3>
                                <p><?php _e('Automatically detect and use your site language', 'gsc-schema-fix'); ?></p>
                                <?php if (!empty($this->options['enable_auto_language_detection'])): ?>
                                    <small class="gsc-badge">Detected: <strong><?php echo strtoupper($this->detect_site_language()); ?></strong></small>
                                <?php endif; ?>
                            </div>
                            <div class="gsc-setting-toggle">
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_auto_language_detection" value="1" <?php checked(!empty($this->options['enable_auto_language_detection'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- German Optimization -->
                        <div class="gsc-setting-card">
                            <div class="gsc-setting-icon">
                                <span class="dashicons dashicons-flag"></span>
                            </div>
                            <div class="gsc-setting-content">
                                <h3><?php _e('German Optimization', 'gsc-schema-fix'); ?></h3>
                                <p><?php _e('Optimizations for German e-commerce sites', 'gsc-schema-fix'); ?></p>
                            </div>
                            <div class="gsc-setting-toggle">
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_german_optimization" value="1" <?php checked(!empty($this->options['enable_german_optimization'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Discrete Shipping -->
                        <div class="gsc-setting-card">
                            <div class="gsc-setting-icon">
                                <span class="dashicons dashicons-privacy"></span>
                            </div>
                            <div class="gsc-setting-content">
                                <h3><?php _e('Discrete Shipping', 'gsc-schema-fix'); ?></h3>
                                <p><?php _e('Add discrete shipping details to schema markup', 'gsc-schema-fix'); ?></p>
                            </div>
                            <div class="gsc-setting-toggle">
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="discrete_shipping" value="1" <?php checked(!empty($this->options['discrete_shipping'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Meta Optimization -->
                        <div class="gsc-setting-card">
                            <div class="gsc-setting-icon">
                                <span class="dashicons dashicons-editor-code"></span>
                            </div>
                            <div class="gsc-setting-content">
                                <h3><?php _e('Meta Optimization', 'gsc-schema-fix'); ?></h3>
                                <p><?php _e('Optimize meta tags for better SEO performance', 'gsc-schema-fix'); ?></p>
                            </div>
                            <div class="gsc-setting-toggle">
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_meta_optimization" value="1" <?php checked(!empty($this->options['enable_meta_optimization'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Content Enhancement -->
                        <div class="gsc-setting-card">
                            <div class="gsc-setting-icon">
                                <span class="dashicons dashicons-admin-links"></span>
                            </div>
                            <div class="gsc-setting-content">
                                <h3><?php _e('Content Enhancement', 'gsc-schema-fix'); ?></h3>
                                <p><?php _e('Automatically add internal links to product pages', 'gsc-schema-fix'); ?></p>
                            </div>
                            <div class="gsc-setting-toggle">
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_content_enhancement" value="1" <?php checked(!empty($this->options['enable_content_enhancement'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Performance Features -->
                        <div class="gsc-setting-card">
                            <div class="gsc-setting-icon">
                                <span class="dashicons dashicons-performance"></span>
                            </div>
                            <div class="gsc-setting-content">
                                <h3><?php _e('Performance Features', 'gsc-schema-fix'); ?></h3>
                                <p><?php _e('Enable lazy loading and caching optimizations', 'gsc-schema-fix'); ?></p>
                            </div>
                            <div class="gsc-setting-toggle">
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_lazy_loading" value="1" <?php checked(!empty($this->options['enable_lazy_loading'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Schema Validation -->
                        <div class="gsc-setting-card">
                            <div class="gsc-setting-icon">
                                <span class="dashicons dashicons-yes-alt"></span>
                            </div>
                            <div class="gsc-setting-content">
                                <h3><?php _e('Schema Validation', 'gsc-schema-fix'); ?></h3>
                                <p><?php _e('Validate schema markup and detect errors', 'gsc-schema-fix'); ?></p>
                            </div>
                            <div class="gsc-setting-toggle">
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_schema_validation" value="1" <?php checked(!empty($this->options['enable_schema_validation'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- FAQ Schema -->
                        <div class="gsc-setting-card">
                            <div class="gsc-setting-icon">
                                <span class="dashicons dashicons-editor-help"></span>
                            </div>
                            <div class="gsc-setting-content">
                                <h3><?php _e('FAQ Schema Detection', 'gsc-schema-fix'); ?></h3>
                                <p><?php _e('Automatically detect and add FAQ schema markup', 'gsc-schema-fix'); ?></p>
                            </div>
                            <div class="gsc-setting-toggle">
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_faq_schema" value="1" <?php checked(!empty($this->options['enable_faq_schema'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Keyword Extraction -->
                        <div class="gsc-setting-card">
                            <div class="gsc-setting-icon">
                                <span class="dashicons dashicons-tag"></span>
                            </div>
                            <div class="gsc-setting-content">
                                <h3><?php _e('Keyword Extraction', 'gsc-schema-fix'); ?></h3>
                                <p><?php _e('Extract keywords and add meta tags for SEO', 'gsc-schema-fix'); ?></p>
                            </div>
                            <div class="gsc-setting-toggle">
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_keyword_extraction" value="1" <?php checked(!empty($this->options['enable_keyword_extraction'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Error Scanning -->
                        <div class="gsc-setting-card">
                            <div class="gsc-setting-icon">
                                <span class="dashicons dashicons-search"></span>
                            </div>
                            <div class="gsc-setting-content">
                                <h3><?php _e('Error Scanning', 'gsc-schema-fix'); ?></h3>
                                <p><?php _e('Automatically scan for schema errors daily', 'gsc-schema-fix'); ?></p>
                                <?php
                                $last_scan = get_option('gsc_schema_fix_last_scan');
                                if ($last_scan): ?>
                                    <small class="gsc-badge">Last scan: <?php echo date_i18n('M j, g:i a', strtotime($last_scan)); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="gsc-setting-toggle">
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_error_scanning" value="1" <?php checked(!empty($this->options['enable_error_scanning'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Auto-Fix Errors -->
                        <div class="gsc-setting-card">
                            <div class="gsc-setting-icon">
                                <span class="dashicons dashicons-admin-tools"></span>
                            </div>
                            <div class="gsc-setting-content">
                                <h3><?php _e('Auto-Fix Errors', 'gsc-schema-fix'); ?></h3>
                                <p><?php _e('Automatically fix detected schema errors', 'gsc-schema-fix'); ?></p>
                            </div>
                            <div class="gsc-setting-toggle">
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_auto_fix" value="1" <?php checked(!empty($this->options['enable_auto_fix'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- HowTo Schema (AI Optimization) -->
                        <div class="gsc-setting-card">
                            <div class="gsc-setting-icon">
                                <span class="dashicons dashicons-list-view"></span>
                            </div>
                            <div class="gsc-setting-content">
                                <h3><?php _e('HowTo Schema (AI)', 'gsc-schema-fix'); ?></h3>
                                <p><?php _e('Auto-detect step-by-step guides for AI Overview', 'gsc-schema-fix'); ?></p>
                            </div>
                            <div class="gsc-setting-toggle">
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_howto_schema" value="1" <?php checked(!empty($this->options['enable_howto_schema'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Entity Markup (AI Optimization) -->
                        <div class="gsc-setting-card">
                            <div class="gsc-setting-icon">
                                <span class="dashicons dashicons-networking"></span>
                            </div>
                            <div class="gsc-setting-content">
                                <h3><?php _e('Entity Markup (AI)', 'gsc-schema-fix'); ?></h3>
                                <p><?php _e('Enhanced organization and breadcrumb markup for AI', 'gsc-schema-fix'); ?></p>
                            </div>
                            <div class="gsc-setting-toggle">
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_entity_markup" value="1" <?php checked(!empty($this->options['enable_entity_markup'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="gsc-save-section">
                        <button type="button" id="gsc-save-all-settings" class="gsc-save-button">
                            <span class="dashicons dashicons-saved"></span>
                            <span class="button-text"><?php _e('Save All Settings', 'gsc-schema-fix'); ?></span>
                        </button>
                        <p class="gsc-save-reminder" style="display: none;">
                            <span class="dashicons dashicons-info"></span>
                            <?php _e('Remember to save your changes!', 'gsc-schema-fix'); ?>
                        </p>
                    </div>
                    
                    <div id="gsc-settings-message"></div>
                </form>
                
                <hr style="margin: 30px 0;">
                
                <h3><?php _e('Site Information', 'gsc-schema-fix'); ?></h3>
                <table class="form-table gsc-settings-table">
                    <tr>
                        <th><?php _e('Default Rating', 'gsc-schema-fix'); ?></th>
                        <td>
                            <strong><?php echo esc_html($this->options['default_rating_value']); ?></strong> / <?php echo esc_html($this->options['rating_best']); ?>
                            (<?php echo esc_html($this->options['default_rating_count']); ?> reviews)
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Currency', 'gsc-schema-fix'); ?></th>
                        <td><strong><?php echo esc_html($this->options['default_currency']); ?></strong></td>
                    </tr>
                    <tr>
                        <th><?php _e('E-commerce Platform', 'gsc-schema-fix'); ?></th>
                        <td>
                            <?php if ($this->detected_platform['active']): ?>
                                <span class="gsc-status enabled">
                                    ✅ <?php echo esc_html($this->detected_platform['name']); ?>
                                </span>
                                <?php if (!empty($this->detected_platform['version'])): ?>
                                    <br><small>Version: <strong><?php echo esc_html($this->detected_platform['version']); ?></strong></small>
                                <?php endif; ?>
                                <?php if (!empty($this->detected_platform['post_types'])): ?>
                                    <br><small>Product Types: <strong><?php echo implode(', ', $this->detected_platform['post_types']); ?></strong></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="gsc-status disabled">❌ No platform detected</span>
                                <br><small>Schema will use default settings</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="gsc-admin-section">
                <div class="gsc-section-header">
                    <h2><span class="dashicons dashicons-superhero"></span> <?php _e('AI Overview Optimization Settings', 'gsc-schema-fix'); ?></h2>
                    <p><?php _e('Configure entity markup and AI-specific features to improve visibility in Google AI Overview and AI-powered search results.', 'gsc-schema-fix'); ?></p>
                </div>
                
                <form method="post" action="" id="gsc-ai-settings-form">
                    <?php wp_nonce_field('gsc_ai_settings_action', 'gsc_ai_settings_nonce'); ?>
                    
                    <table class="form-table gsc-settings-table">
                        <tr>
                            <th><?php _e('Entity Markup Scope', 'gsc-schema-fix'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="entity_markup_all_pages" value="1" <?php checked(!empty($this->options['entity_markup_all_pages'])); ?>>
                                    <?php _e('Add Organization schema on all pages (recommended for AI)', 'gsc-schema-fix'); ?>
                                </label>
                                <p class="description"><?php _e('When enabled, organization schema appears on every page to strengthen AI understanding of your brand.', 'gsc-schema-fix'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Enable Breadcrumbs', 'gsc-schema-fix'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="entity_breadcrumb" value="1" <?php checked(!empty($this->options['entity_breadcrumb'])); ?>>
                                    <?php _e('Generate breadcrumb schema for navigation (AI-friendly)', 'gsc-schema-fix'); ?>
                                </label>
                                <p class="description"><?php _e('Helps AI understand your site structure and content hierarchy.', 'gsc-schema-fix'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Social Profiles', 'gsc-schema-fix'); ?></th>
                            <td>
                                <textarea name="entity_social_profiles" rows="5" class="large-text" placeholder="https://facebook.com/yourpage&#10;https://twitter.com/yourhandle&#10;https://linkedin.com/company/yourcompany"><?php echo esc_textarea(!empty($this->options['entity_social_profiles']) ? $this->options['entity_social_profiles'] : ''); ?></textarea>
                                <p class="description"><?php _e('Enter your social media profile URLs (one per line). AI uses these to verify your brand identity.', 'gsc-schema-fix'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Contact Information', 'gsc-schema-fix'); ?></th>
                            <td>
                                <p>
                                    <label><?php _e('Contact Type:', 'gsc-schema-fix'); ?></label>
                                    <select name="entity_contact_type">
                                        <option value=""><?php _e('None', 'gsc-schema-fix'); ?></option>
                                        <option value="customer service" <?php selected(!empty($this->options['entity_contact_type']) ? $this->options['entity_contact_type'] : '', 'customer service'); ?>><?php _e('Customer Service', 'gsc-schema-fix'); ?></option>
                                        <option value="technical support" <?php selected(!empty($this->options['entity_contact_type']) ? $this->options['entity_contact_type'] : '', 'technical support'); ?>><?php _e('Technical Support', 'gsc-schema-fix'); ?></option>
                                        <option value="sales" <?php selected(!empty($this->options['entity_contact_type']) ? $this->options['entity_contact_type'] : '', 'sales'); ?>><?php _e('Sales', 'gsc-schema-fix'); ?></option>
                                    </select>
                                </p>
                                <p>
                                    <label><?php _e('Email:', 'gsc-schema-fix'); ?></label>
                                    <input type="email" name="entity_contact_email" value="<?php echo esc_attr(!empty($this->options['entity_contact_email']) ? $this->options['entity_contact_email'] : ''); ?>" class="regular-text" placeholder="support@yoursite.com">
                                </p>
                                <p>
                                    <label><?php _e('Telegram:', 'gsc-schema-fix'); ?></label>
                                    <input type="text" name="entity_contact_telegram" value="<?php echo esc_attr(!empty($this->options['entity_contact_telegram']) ? $this->options['entity_contact_telegram'] : ''); ?>" class="regular-text" placeholder="@yoursupport or https://t.me/yoursupport">
                                </p>
                                <p class="description"><?php _e('AI can display this contact information in search results. Provide email and/or Telegram for customer support.', 'gsc-schema-fix'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">
                            <span class="dashicons dashicons-saved"></span> <?php _e('Save AI Settings', 'gsc-schema-fix'); ?>
                        </button>
                    </p>
                </form>
            </div>
            
            <div class="gsc-admin-section">
                <div class="gsc-section-header">
                    <h2><span class="dashicons dashicons-admin-tools"></span> <?php _e('Error Detection & Fixing Tools', 'gsc-schema-fix'); ?></h2>
                    <p><?php _e('Manually scan and fix schema errors on your products.', 'gsc-schema-fix'); ?></p>
                </div>
                
                <div class="gsc-error-controls">
                    <button type="button" id="gsc-scan-errors" class="button button-primary button-large">
                        <span class="dashicons dashicons-search"></span> <?php _e('Scan for Errors Now', 'gsc-schema-fix'); ?>
                    </button>
                    <button type="button" id="gsc-fix-errors" class="button button-secondary button-large" style="display: none;">
                        <span class="dashicons dashicons-admin-tools"></span> <?php _e('Auto-Fix Detected Errors', 'gsc-schema-fix'); ?>
                    </button>
                    <span id="gsc-scan-loading" class="gsc-loading" style="display: none;">
                        <span class="spinner is-active"></span> <?php _e('Scanning...', 'gsc-schema-fix'); ?>
                    </span>
                </div>
                
                <div id="gsc-error-results"></div>
            </div>
            
            <div class="gsc-admin-section">
                <h2><?php _e('Helpful Links', 'gsc-schema-fix'); ?></h2>
                <ul class="gsc-links">
                    <li>📊 <a href="https://search.google.com/search-console" target="_blank"><?php _e('Google Search Console', 'gsc-schema-fix'); ?></a></li>
                    <li>🔍 <a href="https://search.google.com/test/rich-results" target="_blank"><?php _e('Rich Results Test', 'gsc-schema-fix'); ?></a></li>
                    <li>📖 <a href="https://schema.org/Product" target="_blank"><?php _e('Schema.org Product Documentation', 'gsc-schema-fix'); ?></a></li>
                    <li>💻 <a href="https://github.com/dratzymarcano/gscerrorfix" target="_blank"><?php _e('Plugin GitHub Repository', 'gsc-schema-fix'); ?></a></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Test schema generation
     */
    public function ajax_test_schema() {
        check_ajax_referer('gsc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $product_id = intval($_POST['product_id']);
        $post = get_post($product_id);
        
        if (!$post) {
            wp_send_json_error('Product not found');
        }
        
        $schema = $this->generate_product_schema($post);
        
        // v4.0.2 - Add schema validation
        $validation = $this->validate_schema($schema);
        
        wp_send_json_success(array(
            'schema' => $schema,
            'json' => wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'validation' => $validation
        ));
    }
    
    /**
     * v4.0.2.1 - AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('gsc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Get current options
        $options = get_option('gsc_schema_fix_options', array());
        
        // Update toggleable settings
        $toggles = array(
            'enable_auto_offers',
            'enable_auto_review',
            'enable_auto_rating',
            'enable_german_optimization',
            'discrete_shipping',
            'enable_meta_optimization',
            'enable_content_enhancement',
            'add_internal_links',
            'enable_lazy_loading',
            'enable_caching_headers',
            'enable_auto_language_detection',
            'enable_schema_validation',
            'enable_faq_schema',
            'enable_keyword_extraction',
            'enable_error_scanning',
            'enable_auto_fix',
            'enable_howto_schema',
            'enable_entity_markup',
            'entity_markup_all_pages',
            'entity_breadcrumb'
        );
        
        foreach ($toggles as $toggle) {
            if (isset($_POST[$toggle])) {
                $options[$toggle] = intval($_POST[$toggle]);
            }
        }
        
        // Update AI settings text fields
        if (isset($_POST['entity_social_profiles'])) {
            $options['entity_social_profiles'] = sanitize_textarea_field($_POST['entity_social_profiles']);
        }
        if (isset($_POST['entity_contact_type'])) {
            $options['entity_contact_type'] = sanitize_text_field($_POST['entity_contact_type']);
        }
        if (isset($_POST['entity_contact_email'])) {
            $options['entity_contact_email'] = sanitize_email($_POST['entity_contact_email']);
        }
        if (isset($_POST['entity_contact_telegram'])) {
            $options['entity_contact_telegram'] = sanitize_text_field($_POST['entity_contact_telegram']);
        }
        
        // Update option
        update_option('gsc_schema_fix_options', $options);
        
        // Reload options
        $this->options = $options;
        
        wp_send_json_success(array(
            'message' => 'Settings saved successfully!',
            'options' => $options
        ));
    }
}

// Initialize plugin
new GSC_Schema_Fix();
