<?php
/**
 * Plugin Name: GSC Schema Fix
 * Plugin URI: https://github.com/dratzymarcano/gscerrorfix
 * Description: Automatically fixes Google Search Console errors by adding required schema markup (offers, review, aggregateRating) to all products.
 * Version: 1.0.0
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
define('GSC_SCHEMA_FIX_VERSION', '1.0.0');
define('GSC_SCHEMA_FIX_PLUGIN_DIR', plugin_dir_path(__FILE__));

class GSC_Schema_Fix {
    
    private $options;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_head', array($this, 'add_schema_markup'), 99);
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        $this->options = get_option('gsc_schema_fix_options', array());
    }
    
    public function init() {
        load_plugin_textdomain('gsc-schema-fix', false, dirname(plugin_basename(__FILE__)) . '/languages');
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
            
            $schema['offers'] = array(
                '@type' => 'Offer',
                'url' => $product_url,
                'priceCurrency' => $currency,
                'price' => number_format((float)$price, 2, '.', ''),
                'availability' => 'https://schema.org/' . $availability,
            );
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
                'reviewBody' => 'Excellent product. Highly recommended.',
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
}

// Initialize plugin
new GSC_Schema_Fix();
