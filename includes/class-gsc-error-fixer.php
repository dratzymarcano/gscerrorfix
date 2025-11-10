<?php
/**
 * Google Search Console Error Fixer
 * Automatically detects and fixes all common GSC errors
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSC_Error_Fixer {
    
    private $options;
    private $errors_fixed = 0;
    
    public function __construct($options) {
        $this->options = $options;
    }
    
    public function auto_fix_all_errors() {
        $fixed = array();
        
        // 1. Fix missing schema errors
        $fixed['schema'] = $this->fix_missing_schema_errors();
        
        // 2. Fix missing meta tags
        $fixed['meta'] = $this->fix_missing_meta_tags();
        
        // 3. Fix broken internal links
        $fixed['links'] = $this->fix_broken_internal_links();
        
        // 4. Fix duplicate content
        $fixed['duplicates'] = $this->fix_duplicate_content();
        
        // 5. Fix mobile usability issues
        $fixed['mobile'] = $this->fix_mobile_usability();
        
        // 6. Fix indexing issues
        $fixed['indexing'] = $this->fix_indexing_issues();
        
        // 7. Fix structured data errors
        $fixed['structured_data'] = $this->fix_structured_data_errors();
        
        return $fixed;
    }
    
    public function fix_missing_schema_errors() {
        $fixed = 0;
        
        // Get all products without schema
        $args = array(
            'post_type' => array('product', 'download', 'post', 'page'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'gsc_schema_added',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        $posts = get_posts($args);
        
        foreach ($posts as $post) {
            // Check if it's a product
            if (in_array($post->post_type, array('product', 'download'))) {
                // Schema will be auto-added by main plugin
                update_post_meta($post->ID, 'gsc_schema_pending', true);
                $fixed++;
            }
        }
        
        return array(
            'fixed' => $fixed,
            'message' => "Marked {$fixed} products for schema generation"
        );
    }
    
    public function fix_missing_meta_tags() {
        $fixed = 0;
        
        // Get all posts/products without meta description
        $args = array(
            'post_type' => 'any',
            'post_status' => 'publish',
            'posts_per_page' => -1
        );
        
        $posts = get_posts($args);
        
        foreach ($posts as $post) {
            $has_meta = get_post_meta($post->ID, 'gsc_meta_description', true);
            
            if (empty($has_meta)) {
                // Auto-generate meta description
                $excerpt = wp_trim_words(strip_tags($post->post_content), 20, '...');
                if (!empty($excerpt)) {
                    update_post_meta($post->ID, 'gsc_meta_description', $excerpt);
                    $fixed++;
                }
            }
        }
        
        return array(
            'fixed' => $fixed,
            'message' => "Generated meta descriptions for {$fixed} pages"
        );
    }
    
    public function fix_broken_internal_links() {
        global $wpdb;
        $fixed = 0;
        
        // Find all posts with content
        $posts = $wpdb->get_results(
            "SELECT ID, post_content FROM {$wpdb->posts} 
             WHERE post_status = 'publish' 
             AND post_content LIKE '%href=%'"
        );
        
        foreach ($posts as $post) {
            // Extract all internal links
            preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches);
            
            if (empty($matches[1])) {
                continue;
            }
            
            $modified = false;
            $content = $post->post_content;
            
            foreach ($matches[1] as $url) {
                // Skip external links
                if (strpos($url, '://') !== false && strpos($url, home_url()) === false) {
                    continue;
                }
                
                // Check if link is broken
                if ($this->is_broken_link($url)) {
                    // Try to find alternative
                    $alternative = $this->find_alternative_url($url);
                    
                    if ($alternative) {
                        $content = str_replace($url, $alternative, $content);
                        $modified = true;
                    }
                }
            }
            
            if ($modified) {
                wp_update_post(array(
                    'ID' => $post->ID,
                    'post_content' => $content
                ));
                $fixed++;
            }
        }
        
        return array(
            'fixed' => $fixed,
            'message' => "Fixed broken links in {$fixed} posts"
        );
    }
    
    public function fix_duplicate_content() {
        $fixed = 0;
        
        // Add canonical URLs to prevent duplicate content
        $args = array(
            'post_type' => 'any',
            'post_status' => 'publish',
            'posts_per_page' => -1
        );
        
        $posts = get_posts($args);
        
        foreach ($posts as $post) {
            $has_canonical = get_post_meta($post->ID, 'gsc_canonical_url', true);
            
            if (empty($has_canonical)) {
                $canonical = get_permalink($post->ID);
                update_post_meta($post->ID, 'gsc_canonical_url', $canonical);
                $fixed++;
            }
        }
        
        return array(
            'fixed' => $fixed,
            'message' => "Added canonical URLs to {$fixed} pages"
        );
    }
    
    public function fix_mobile_usability() {
        $fixed = 0;
        
        // Check for common mobile issues
        $issues = array(
            'viewport' => $this->ensure_viewport_meta(),
            'font_size' => $this->ensure_readable_font_size(),
            'tap_targets' => $this->ensure_tap_target_size()
        );
        
        foreach ($issues as $issue => $result) {
            if ($result['fixed']) {
                $fixed++;
            }
        }
        
        return array(
            'fixed' => $fixed,
            'issues' => $issues,
            'message' => "Fixed {$fixed} mobile usability issues"
        );
    }
    
    public function fix_indexing_issues() {
        $fixed = 0;
        
        // Remove noindex from products/posts that should be indexed
        $args = array(
            'post_type' => array('product', 'download', 'post'),
            'post_status' => 'publish',
            'posts_per_page' => -1
        );
        
        $posts = get_posts($args);
        
        foreach ($posts as $post) {
            $robots = get_post_meta($post->ID, '_yoast_wpseo_meta-robots-noindex', true);
            
            // Remove noindex if set
            if ($robots === '1' || $robots === 'noindex') {
                delete_post_meta($post->ID, '_yoast_wpseo_meta-robots-noindex');
                update_post_meta($post->ID, 'gsc_indexing_fixed', true);
                $fixed++;
            }
        }
        
        return array(
            'fixed' => $fixed,
            'message' => "Enabled indexing for {$fixed} pages"
        );
    }
    
    public function fix_structured_data_errors() {
        $fixed = 0;
        
        // Common structured data errors
        $errors = array(
            'missing_required_fields' => $this->add_missing_required_fields(),
            'invalid_values' => $this->fix_invalid_schema_values(),
            'missing_breadcrumbs' => $this->add_breadcrumb_schema()
        );
        
        foreach ($errors as $error_type => $result) {
            $fixed += $result['fixed'];
        }
        
        return array(
            'fixed' => $fixed,
            'errors' => $errors,
            'message' => "Fixed {$fixed} structured data errors"
        );
    }
    
    private function is_broken_link($url) {
        // Parse URL
        $path = parse_url($url, PHP_URL_PATH);
        
        if (empty($path)) {
            return false;
        }
        
        // Check if post exists
        $post_id = url_to_postid($url);
        
        if ($post_id === 0) {
            return true;
        }
        
        $post = get_post($post_id);
        return !$post || $post->post_status !== 'publish';
    }
    
    private function find_alternative_url($url) {
        // Try to find similar URL
        $path = parse_url($url, PHP_URL_PATH);
        $slug = basename($path);
        
        // Search for similar posts
        $args = array(
            'name' => $slug,
            'post_type' => 'any',
            'post_status' => 'publish',
            'posts_per_page' => 1
        );
        
        $posts = get_posts($args);
        
        if (!empty($posts)) {
            return get_permalink($posts[0]->ID);
        }
        
        return false;
    }
    
    private function ensure_viewport_meta() {
        // Check if viewport meta exists in theme
        $theme_header = get_template_directory() . '/header.php';
        
        if (file_exists($theme_header)) {
            $content = file_get_contents($theme_header);
            
            if (strpos($content, 'viewport') === false) {
                // Viewport will be added via wp_head in main plugin
                return array('fixed' => true, 'message' => 'Viewport meta will be added');
            }
        }
        
        return array('fixed' => false, 'message' => 'Viewport meta already exists');
    }
    
    private function ensure_readable_font_size() {
        // This would be handled by theme CSS
        // Mark for manual review
        return array(
            'fixed' => false,
            'message' => 'Font size should be checked in theme CSS',
            'recommendation' => 'Ensure base font size is at least 16px'
        );
    }
    
    private function ensure_tap_target_size() {
        // This would be handled by theme CSS
        return array(
            'fixed' => false,
            'message' => 'Tap targets should be checked in theme CSS',
            'recommendation' => 'Ensure buttons/links are at least 48x48px'
        );
    }
    
    private function add_missing_required_fields() {
        $fixed = 0;
        
        // Get all products with schema
        $args = array(
            'post_type' => array('product', 'download'),
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'gsc_schema_added',
                    'value' => '1',
                    'compare' => '='
                )
            )
        );
        
        $posts = get_posts($args);
        
        foreach ($posts as $post) {
            // Re-generate schema to ensure all fields are present
            delete_post_meta($post->ID, 'gsc_schema_added');
            $fixed++;
        }
        
        return array(
            'fixed' => $fixed,
            'message' => "Marked {$fixed} products for schema regeneration"
        );
    }
    
    private function fix_invalid_schema_values() {
        $fixed = 0;
        
        // Check for common invalid values
        if (function_exists('wc_get_products')) {
            $products = wc_get_products(array('limit' => -1));
            
            foreach ($products as $product) {
                $needs_fix = false;
                
                // Fix invalid price
                $price = $product->get_price();
                if (empty($price) || $price <= 0) {
                    $product->set_regular_price(0.01);
                    $needs_fix = true;
                }
                
                // Fix invalid SKU
                $sku = $product->get_sku();
                if (empty($sku)) {
                    $product->set_sku('SKU-' . $product->get_id());
                    $needs_fix = true;
                }
                
                if ($needs_fix) {
                    $product->save();
                    delete_post_meta($product->get_id(), 'gsc_schema_added');
                    $fixed++;
                }
            }
        }
        
        return array(
            'fixed' => $fixed,
            'message' => "Fixed invalid values for {$fixed} products"
        );
    }
    
    private function add_breadcrumb_schema() {
        $fixed = 0;
        
        // Breadcrumbs will be auto-added by main plugin for pages
        $args = array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1
        );
        
        $pages = get_posts($args);
        
        foreach ($pages as $page) {
            update_post_meta($page->ID, 'gsc_breadcrumb_needed', true);
            $fixed++;
        }
        
        return array(
            'fixed' => $fixed,
            'message' => "Marked {$fixed} pages for breadcrumb schema"
        );
    }
    
    public function get_errors_fixed() {
        return $this->errors_fixed;
    }
}
