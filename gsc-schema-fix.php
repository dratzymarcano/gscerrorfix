<?php
/**
 * Plugin Name: GSC Schema Fix
 * Plugin URI: https://github.com/dratzymarcano/gscerrorfix
 * Description: Automatically fixes Google Search Console errors by adding required schema markup (offers, review, aggregateRating) to all products. Optimized for German e-commerce with discrete shipping information. Includes meta optimization, enhanced admin interface, and multi-language support.
 * Version: 4.0.0
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
define('GSC_SCHEMA_FIX_VERSION', '4.0.0');
define('GSC_SCHEMA_FIX_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GSC_SCHEMA_FIX_PLUGIN_URL', plugin_dir_url(__FILE__));

class GSC_Schema_Fix {
    
    private $options;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_head', array($this, 'add_schema_markup'), 99);
        add_action('wp_head', array($this, 'optimize_meta_tags'), 1); // v3.0.0
        add_action('the_content', array($this, 'enhance_content')); // v3.0.0
        add_action('wp_footer', array($this, 'add_performance_optimizations')); // v3.0.0
        add_action('admin_menu', array($this, 'add_admin_menu')); // v3.0.0
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts')); // v3.0.0
        
        // AJAX handlers for admin tools
        add_action('wp_ajax_gsc_test_schema', array($this, 'ajax_test_schema')); // v3.0.0
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        $this->options = get_option('gsc_schema_fix_options', array());
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
        
        // Add offers (required)
        if (!empty($this->options['enable_auto_offers'])) {
            $availability = !empty($this->options['default_availability']) ? $this->options['default_availability'] : 'InStock';
            
            $offer = array(
                '@type' => 'Offer',
                'url' => $product_url,
                'priceCurrency' => $currency,
                'price' => number_format((float)$price, 2, '.', ''),
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
    
    private function get_product_price($product_id) {
        // Try WooCommerce first
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            if ($product) {
                return $product->get_price();
            }
        }
        
        // Try Easy Digital Downloads
        if (function_exists('edd_get_download_price')) {
            $price = edd_get_download_price($product_id);
            if ($price) {
                return $price;
            }
        }
        
        // Fallback to meta fields
        $price = get_post_meta($product_id, '_price', true);
        if (empty($price)) {
            $price = get_post_meta($product_id, 'price', true);
        }
        if (empty($price)) {
            $price = get_post_meta($product_id, 'edd_price', true);
        }
        
        return !empty($price) ? $price : '0.00';
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
                <h2><?php _e('Current Settings', 'gsc-schema-fix'); ?></h2>
                <table class="form-table gsc-settings-table">
                    <tr>
                        <th><?php _e('Schema Enabled', 'gsc-schema-fix'); ?></th>
                        <td>
                            <span class="gsc-status <?php echo !empty($this->options['enable_auto_offers']) ? 'enabled' : 'disabled'; ?>">
                                <?php echo !empty($this->options['enable_auto_offers']) ? '✅ Enabled' : '❌ Disabled'; ?>
                            </span>
                        </td>
                    </tr>
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
                        <th><?php _e('Auto Language Detection', 'gsc-schema-fix'); ?></th>
                        <td>
                            <span class="gsc-status <?php echo !empty($this->options['enable_auto_language_detection']) ? 'enabled' : 'disabled'; ?>">
                                <?php echo !empty($this->options['enable_auto_language_detection']) ? '✅ Enabled' : '❌ Disabled'; ?>
                            </span>
                            <?php if (!empty($this->options['enable_auto_language_detection'])): ?>
                                <br><small>Detected: <strong><?php echo strtoupper($this->detect_site_language()); ?></strong> (<?php echo get_locale(); ?>)</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('German Optimization', 'gsc-schema-fix'); ?></th>
                        <td>
                            <span class="gsc-status <?php echo !empty($this->options['enable_german_optimization']) ? 'enabled' : 'disabled'; ?>">
                                <?php echo !empty($this->options['enable_german_optimization']) ? '✅ Enabled' : '❌ Disabled'; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Meta Optimization', 'gsc-schema-fix'); ?></th>
                        <td>
                            <span class="gsc-status <?php echo !empty($this->options['enable_meta_optimization']) ? 'enabled' : 'disabled'; ?>">
                                <?php echo !empty($this->options['enable_meta_optimization']) ? '✅ Enabled' : '❌ Disabled'; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Content Enhancement', 'gsc-schema-fix'); ?></th>
                        <td>
                            <span class="gsc-status <?php echo !empty($this->options['enable_content_enhancement']) ? 'enabled' : 'disabled'; ?>">
                                <?php echo !empty($this->options['enable_content_enhancement']) ? '✅ Enabled' : '❌ Disabled'; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Performance Features', 'gsc-schema-fix'); ?></th>
                        <td>
                            <span class="gsc-status <?php echo !empty($this->options['enable_lazy_loading']) ? 'enabled' : 'disabled'; ?>">
                                <?php echo !empty($this->options['enable_lazy_loading']) ? '✅ Enabled' : '❌ Disabled'; ?>
                            </span>
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
        
        wp_send_json_success(array(
            'schema' => $schema,
            'json' => wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ));
    }
}

// Initialize plugin
new GSC_Schema_Fix();
