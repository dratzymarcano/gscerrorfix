<?php
/**
 * Analytics Dashboard Class
 * Provides comprehensive SEO analytics and reporting
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSC_Analytics_Dashboard {
    private $options;
    
    public function __construct($options) {
        $this->options = $options;
    }
    
    public function get_overview() {
        global $wpdb;
        
        $total_products = wp_count_posts('product')->publish;
        $optimized_products = $this->count_optimized_products();
        $schema_errors = $this->count_schema_errors();
        $avg_seo_score = $this->calculate_average_seo_score();
        
        return array(
            'total_products' => $total_products,
            'optimized_products' => $optimized_products,
            'optimization_percentage' => $total_products > 0 ? round(($optimized_products / $total_products) * 100, 2) : 0,
            'schema_errors' => $schema_errors,
            'avg_seo_score' => $avg_seo_score,
            'health_status' => $this->get_health_status($schema_errors, $avg_seo_score)
        );
    }
    
    public function get_top_products($limit = 10) {
        $products = get_posts(array(
            'post_type' => 'product',
            'posts_per_page' => $limit,
            'orderby' => 'meta_value_num',
            'meta_key' => 'gsc_seo_score',
            'order' => 'DESC'
        ));
        
        $top_products = array();
        foreach ($products as $product) {
            $top_products[] = array(
                'id' => $product->ID,
                'title' => $product->post_title,
                'seo_score' => get_post_meta($product->ID, 'gsc_seo_score', true),
                'schema_valid' => get_post_meta($product->ID, 'gsc_schema_valid', true),
                'last_optimized' => get_post_meta($product->ID, 'gsc_last_optimized', true)
            );
        }
        
        return $top_products;
    }
    
    public function get_schema_health() {
        $total = wp_count_posts('product')->publish;
        $valid = $this->count_valid_schema();
        $warnings = $this->count_schema_warnings();
        $errors = $this->count_schema_errors();
        
        return array(
            'total_products' => $total,
            'valid_schema' => $valid,
            'warnings' => $warnings,
            'errors' => $errors,
            'health_percentage' => $total > 0 ? round(($valid / $total) * 100, 2) : 0
        );
    }
    
    public function get_performance_metrics() {
        return array(
            'avg_page_speed' => $this->get_average_page_speed(),
            'mobile_friendly' => $this->check_mobile_friendliness(),
            'core_web_vitals' => $this->get_core_web_vitals(),
            'cache_hit_rate' => $this->get_cache_hit_rate()
        );
    }
    
    public function get_ranking_changes() {
        global $wpdb;
        $table = $wpdb->prefix . 'gsc_rankings';
        
        $results = $wpdb->get_results("
            SELECT keyword, current_rank, previous_rank, change_direction
            FROM $table
            WHERE date_recorded >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY ABS(current_rank - previous_rank) DESC
            LIMIT 20
        ");
        
        return $results ?: array();
    }
    
    public function get_recent_errors() {
        global $wpdb;
        $table = $wpdb->prefix . 'gsc_optimizations';
        
        $errors = $wpdb->get_results("
            SELECT post_id, optimization_type, optimization_data
            FROM $table
            WHERE optimization_type = 'error'
            ORDER BY created_at DESC
            LIMIT 10
        ");
        
        return $errors ?: array();
    }
    
    public function get_weekly_stats() {
        return array(
            'optimizations_performed' => $this->count_weekly_optimizations(),
            'schema_fixes' => $this->count_weekly_schema_fixes(),
            'content_enhanced' => $this->count_weekly_content_enhancements(),
            'performance_improvements' => $this->count_weekly_performance_improvements()
        );
    }
    
    private function count_optimized_products() {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id)
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'gsc_optimized'
            AND meta_value = '1'
        ");
        
        return intval($count);
    }
    
    private function count_schema_errors() {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'gsc_schema_errors'
            AND meta_value != ''
        ");
        
        return intval($count);
    }
    
    private function count_valid_schema() {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'gsc_schema_valid'
            AND meta_value = '1'
        ");
        
        return intval($count);
    }
    
    private function count_schema_warnings() {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'gsc_schema_warnings'
            AND meta_value != ''
        ");
        
        return intval($count);
    }
    
    private function calculate_average_seo_score() {
        global $wpdb;
        
        $avg = $wpdb->get_var("
            SELECT AVG(CAST(meta_value AS DECIMAL(10,2)))
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'gsc_seo_score'
        ");
        
        return round(floatval($avg), 2);
    }
    
    private function get_health_status($errors, $score) {
        if ($errors > 10 || $score < 50) {
            return 'critical';
        } elseif ($errors > 5 || $score < 70) {
            return 'warning';
        } else {
            return 'excellent';
        }
    }
    
    private function get_average_page_speed() {
        return 2.5; // Placeholder - integrate with actual speed testing
    }
    
    private function check_mobile_friendliness() {
        return 95; // Placeholder - integrate with mobile testing
    }
    
    private function get_core_web_vitals() {
        return array(
            'lcp' => 2.1,
            'fid' => 85,
            'cls' => 0.08
        );
    }
    
    private function get_cache_hit_rate() {
        return 87; // Placeholder
    }
    
    private function count_weekly_optimizations() {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}gsc_optimizations
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        return intval($count);
    }
    
    private function count_weekly_schema_fixes() {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}gsc_optimizations
            WHERE optimization_type = 'schema_fix'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        return intval($count);
    }
    
    private function count_weekly_content_enhancements() {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}gsc_optimizations
            WHERE optimization_type = 'content_enhancement'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        return intval($count);
    }
    
    private function count_weekly_performance_improvements() {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}gsc_optimizations
            WHERE optimization_type = 'performance'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        return intval($count);
    }
}
