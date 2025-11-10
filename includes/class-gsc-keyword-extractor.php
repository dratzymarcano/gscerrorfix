<?php
/**
 * Keyword Extractor & Content Optimizer
 * Automatically extracts keywords from content and optimizes for ranking
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSC_Keyword_Extractor {
    
    private $options;
    private $stop_words_de = array('und', 'der', 'die', 'das', 'ist', 'in', 'auf', 'für', 'mit', 'von', 'zu', 'im', 'am', 'den', 'dem', 'des', 'ein', 'eine', 'einen', 'einem', 'eines', 'wird', 'werden', 'wurde', 'wurden', 'sich', 'nicht', 'auch', 'oder', 'aber');
    private $stop_words_en = array('the', 'is', 'at', 'which', 'on', 'a', 'an', 'as', 'are', 'was', 'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'of', 'to', 'in', 'for', 'with', 'from', 'by', 'this', 'that', 'these', 'those');
    
    public function __construct($options) {
        $this->options = $options;
    }
    
    public function extract_from_content($post) {
        $content = $post->post_title . ' ' . $post->post_content . ' ' . $post->post_excerpt;
        
        // Clean content
        $content = strip_tags($content);
        $content = strip_shortcodes($content);
        $content = strtolower($content);
        
        // Tokenize
        $words = str_word_count($content, 1);
        
        // Remove stop words
        $stop_words = array_merge($this->stop_words_en, $this->stop_words_de);
        $words = array_filter($words, function($word) use ($stop_words) {
            return strlen($word) > 3 && !in_array($word, $stop_words);
        });
        
        // Count frequencies
        $word_freq = array_count_values($words);
        arsort($word_freq);
        
        // Extract top keywords
        $keywords = array_slice(array_keys($word_freq), 0, 20);
        
        // Extract phrases (2-3 word combinations)
        $phrases = $this->extract_phrases($content);
        
        // Combine and deduplicate
        $all_keywords = array_unique(array_merge($keywords, $phrases));
        
        return array_slice($all_keywords, 0, 15);
    }
    
    public function extract_from_title($title) {
        $title = strtolower(strip_tags($title));
        $words = str_word_count($title, 1);
        
        $stop_words = array_merge($this->stop_words_en, $this->stop_words_de);
        $keywords = array_filter($words, function($word) use ($stop_words) {
            return strlen($word) > 3 && !in_array($word, $stop_words);
        });
        
        return array_values($keywords);
    }
    
    public function extract_product_keywords($product_id) {
        if (!function_exists('wc_get_product')) {
            return array();
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return array();
        }
        
        $keywords = array();
        
        // Product name
        $name_words = $this->extract_from_title($product->get_name());
        $keywords = array_merge($keywords, $name_words);
        
        // Categories
        $categories = wp_get_post_terms($product_id, 'product_cat');
        foreach ($categories as $cat) {
            $keywords[] = strtolower($cat->name);
        }
        
        // Tags
        $tags = wp_get_post_terms($product_id, 'product_tag');
        foreach ($tags as $tag) {
            $keywords[] = strtolower($tag->name);
        }
        
        // Attributes
        $attributes = $product->get_attributes();
        foreach ($attributes as $attribute) {
            if (is_a($attribute, 'WC_Product_Attribute')) {
                $options = $attribute->get_options();
                foreach ($options as $option) {
                    if (is_string($option)) {
                        $keywords[] = strtolower($option);
                    }
                }
            }
        }
        
        return array_unique($keywords);
    }
    
    public function optimize_meta_with_keywords($post, $keywords) {
        if (empty($keywords)) {
            return;
        }
        
        // Get existing meta
        $meta_title = get_post_meta($post->ID, 'gsc_meta_title', true);
        $meta_desc = get_post_meta($post->ID, 'gsc_meta_description', true);
        
        // Generate optimized meta if empty
        if (empty($meta_title)) {
            $meta_title = $this->generate_optimized_title($post, $keywords);
            update_post_meta($post->ID, 'gsc_meta_title', $meta_title);
        }
        
        if (empty($meta_desc)) {
            $meta_desc = $this->generate_optimized_description($post, $keywords);
            update_post_meta($post->ID, 'gsc_meta_description', $meta_desc);
        }
    }
    
    public function enhance_schema_with_keywords($schema, $keywords) {
        if (empty($keywords) || empty($schema)) {
            return $schema;
        }
        
        // Add keywords to schema
        if (!isset($schema['keywords'])) {
            $schema['keywords'] = implode(', ', array_slice($keywords, 0, 10));
        }
        
        // Enhance product description with keywords
        if (isset($schema['@type']) && $schema['@type'] === 'Product') {
            if (isset($schema['description'])) {
                // Ensure top keywords appear in description
                $description = $schema['description'];
                foreach (array_slice($keywords, 0, 3) as $keyword) {
                    if (stripos($description, $keyword) === false) {
                        $description .= ' ' . ucfirst($keyword) . '.';
                    }
                }
                $schema['description'] = $description;
            }
        }
        
        return $schema;
    }
    
    public function get_related_keywords($keyword) {
        // Get related keywords from WP database
        global $wpdb;
        
        $related = array();
        
        // Search in post titles
        $titles = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT post_title FROM {$wpdb->posts} 
             WHERE post_status = 'publish' 
             AND post_title LIKE %s 
             LIMIT 10",
            '%' . $wpdb->esc_like($keyword) . '%'
        ));
        
        foreach ($titles as $title) {
            $words = $this->extract_from_title($title->post_title);
            $related = array_merge($related, $words);
        }
        
        // Search in taxonomy terms
        $terms = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT name FROM {$wpdb->terms} 
             WHERE name LIKE %s 
             LIMIT 10",
            '%' . $wpdb->esc_like($keyword) . '%'
        ));
        
        foreach ($terms as $term) {
            $related[] = strtolower($term->name);
        }
        
        return array_unique(array_slice($related, 0, 10));
    }
    
    private function extract_phrases($content) {
        $phrases = array();
        
        // Extract 2-word phrases
        preg_match_all('/\b([a-zäöüß]{4,}\s+[a-zäöüß]{4,})\b/iu', $content, $matches);
        if (!empty($matches[1])) {
            $two_word = array_count_values($matches[1]);
            arsort($two_word);
            $phrases = array_merge($phrases, array_slice(array_keys($two_word), 0, 10));
        }
        
        // Extract 3-word phrases
        preg_match_all('/\b([a-zäöüß]{4,}\s+[a-zäöüß]{4,}\s+[a-zäöüß]{4,})\b/iu', $content, $matches);
        if (!empty($matches[1])) {
            $three_word = array_count_values($matches[1]);
            arsort($three_word);
            $phrases = array_merge($phrases, array_slice(array_keys($three_word), 0, 5));
        }
        
        // Remove stop words from phrases
        $stop_words = array_merge($this->stop_words_en, $this->stop_words_de);
        $phrases = array_filter($phrases, function($phrase) use ($stop_words) {
            $words = explode(' ', strtolower($phrase));
            foreach ($words as $word) {
                if (in_array($word, $stop_words)) {
                    return false;
                }
            }
            return true;
        });
        
        return array_values($phrases);
    }
    
    private function generate_optimized_title($post, $keywords) {
        $title = $post->post_title;
        
        // Add primary keyword if not present
        if (!empty($keywords[0]) && stripos($title, $keywords[0]) === false) {
            $title = $keywords[0] . ' - ' . $title;
        }
        
        // Ensure length is optimal (50-60 chars)
        if (strlen($title) > 60) {
            $title = substr($title, 0, 57) . '...';
        }
        
        return $title;
    }
    
    private function generate_optimized_description($post, $keywords) {
        $content = strip_tags(strip_shortcodes($post->post_content));
        $excerpt = wp_trim_words($content, 20, '...');
        
        // Inject top 2 keywords into description
        $description = $excerpt;
        foreach (array_slice($keywords, 0, 2) as $keyword) {
            if (stripos($description, $keyword) === false) {
                $description = ucfirst($keyword) . ': ' . $description;
            }
        }
        
        // Ensure optimal length (150-160 chars)
        if (strlen($description) > 160) {
            $description = substr($description, 0, 157) . '...';
        } elseif (strlen($description) < 120) {
            // Add more keywords
            $description .= ' ' . implode(', ', array_slice($keywords, 0, 5));
            if (strlen($description) > 160) {
                $description = substr($description, 0, 157) . '...';
            }
        }
        
        return $description;
    }
}
