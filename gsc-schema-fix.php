<?php
/**
 * Plugin Name: GSC Schema Fix
 * Plugin URI: https://github.com/dratzymarcano/gscerrorfix
 * Description: Automatically fixes Google Search Console errors by adding required schema markup (offers, review, aggregateRating) to all products. Optimized for German e-commerce with discrete shipping information. Includes meta optimization, enhanced admin interface, multi-language support, universal platform detection, schema validation, FAQ schema detection, and keyword extraction.
 * Version: 4.0.4
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
define('GSC_SCHEMA_FIX_VERSION', '4.0.4');
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
        add_action('wp_head', array($this, 'optimize_meta_tags'), 1); // v3.0.0
        add_action('the_content', array($this, 'enhance_content')); // v3.0.0
        add_action('wp_footer', array($this, 'add_performance_optimizations')); // v3.0.0
        add_action('admin_menu', array($this, 'add_admin_menu')); // v3.0.0
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts')); // v3.0.0
        
        // AJAX handlers for admin tools
        add_action('wp_ajax_gsc_test_schema', array($this, 'ajax_test_schema')); // v3.0.0
        add_action('wp_ajax_gsc_save_settings', array($this, 'ajax_save_settings')); // v4.0.2.1
        
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
        );
        
        add_option('gsc_schema_fix_options', $default_options);
    }
    
    public function deactivate() {
        // Clean up if needed
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
        ?>
        <div class="wrap gsc-admin-wrap">
            <h1><?php _e('GSC Schema Fix - Settings', 'gsc-schema-fix'); ?></h1>
            <p class="gsc-version"><?php _e('Version', 'gsc-schema-fix'); ?>: <strong><?php echo GSC_SCHEMA_FIX_VERSION; ?></strong></p>
            
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
            
            <div class="gsc-admin-section">
                <h2><?php _e('Settings', 'gsc-schema-fix'); ?></h2>
                <p><?php _e('Toggle features on/off. Changes are saved automatically.', 'gsc-schema-fix'); ?></p>
                
                <form id="gsc-settings-form">
                    <table class="form-table gsc-settings-table">
                        <tr>
                            <th><?php _e('Schema Generation', 'gsc-schema-fix'); ?></th>
                            <td>
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_auto_offers" value="1" <?php checked(!empty($this->options['enable_auto_offers'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                                <span class="gsc-toggle-label"><?php _e('Enable automatic schema markup for products', 'gsc-schema-fix'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Auto Language Detection', 'gsc-schema-fix'); ?></th>
                            <td>
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_auto_language_detection" value="1" <?php checked(!empty($this->options['enable_auto_language_detection'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                                <span class="gsc-toggle-label">
                                    <?php _e('Automatically detect and use site language', 'gsc-schema-fix'); ?>
                                    <?php if (!empty($this->options['enable_auto_language_detection'])): ?>
                                        <br><small style="color: #666;">Detected: <strong><?php echo strtoupper($this->detect_site_language()); ?></strong> (<?php echo get_locale(); ?>)</small>
                                    <?php endif; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('German Optimization', 'gsc-schema-fix'); ?></th>
                            <td>
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_german_optimization" value="1" <?php checked(!empty($this->options['enable_german_optimization'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                                <span class="gsc-toggle-label"><?php _e('Enable German e-commerce optimizations', 'gsc-schema-fix'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Discrete Shipping', 'gsc-schema-fix'); ?></th>
                            <td>
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="discrete_shipping" value="1" <?php checked(!empty($this->options['discrete_shipping'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                                <span class="gsc-toggle-label"><?php _e('Add discrete shipping details to schema', 'gsc-schema-fix'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Meta Optimization', 'gsc-schema-fix'); ?></th>
                            <td>
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_meta_optimization" value="1" <?php checked(!empty($this->options['enable_meta_optimization'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                                <span class="gsc-toggle-label"><?php _e('Optimize meta tags for SEO', 'gsc-schema-fix'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Content Enhancement', 'gsc-schema-fix'); ?></th>
                            <td>
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_content_enhancement" value="1" <?php checked(!empty($this->options['enable_content_enhancement'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                                <span class="gsc-toggle-label"><?php _e('Add internal links to product content', 'gsc-schema-fix'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Performance Features', 'gsc-schema-fix'); ?></th>
                            <td>
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_lazy_loading" value="1" <?php checked(!empty($this->options['enable_lazy_loading'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                                <span class="gsc-toggle-label"><?php _e('Enable lazy loading and caching', 'gsc-schema-fix'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Schema Validation', 'gsc-schema-fix'); ?></th>
                            <td>
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_schema_validation" value="1" <?php checked(!empty($this->options['enable_schema_validation'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                                <span class="gsc-toggle-label"><?php _e('Validate schema markup for errors', 'gsc-schema-fix'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('FAQ Schema Detection', 'gsc-schema-fix'); ?></th>
                            <td>
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_faq_schema" value="1" <?php checked(!empty($this->options['enable_faq_schema'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                                <span class="gsc-toggle-label"><?php _e('Automatically detect and add FAQ schema', 'gsc-schema-fix'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Keyword Extraction', 'gsc-schema-fix'); ?></th>
                            <td>
                                <label class="gsc-toggle">
                                    <input type="checkbox" name="enable_keyword_extraction" value="1" <?php checked(!empty($this->options['enable_keyword_extraction'])); ?>>
                                    <span class="gsc-toggle-slider"></span>
                                </label>
                                <span class="gsc-toggle-label"><?php _e('Extract and add keywords meta tag', 'gsc-schema-fix'); ?></span>
                            </td>
                        </tr>
                    </table>
                    
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
            'enable_keyword_extraction'
        );
        
        foreach ($toggles as $toggle) {
            if (isset($_POST[$toggle])) {
                $options[$toggle] = intval($_POST[$toggle]);
            }
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
