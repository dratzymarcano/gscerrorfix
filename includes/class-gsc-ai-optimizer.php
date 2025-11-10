<?php
/**
 * AI Optimizer Class
 * AI-powered content analysis and optimization
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSC_AI_Optimizer {
    private $options;
    
    public function __construct($options) {
        $this->options = $options;
    }
    
    public function calculate_seo_score($post) {
        $score = 0;
        $max_score = 100;
        
        // Content length (0-20 points)
        $content = strip_tags($post->post_content);
        $word_count = str_word_count($content);
        if ($word_count >= 300) {
            $score += 20;
        } elseif ($word_count >= 150) {
            $score += 15;
        } elseif ($word_count >= 100) {
            $score += 10;
        }
        
        // Title optimization (0-15 points)
        $title_length = strlen($post->post_title);
        if ($title_length >= 30 && $title_length <= 60) {
            $score += 15;
        } elseif ($title_length >= 20 && $title_length <= 70) {
            $score += 10;
        }
        
        // Meta description (0-15 points)
        $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        if (!empty($meta_desc)) {
            $meta_length = strlen($meta_desc);
            if ($meta_length >= 120 && $meta_length <= 160) {
                $score += 15;
            } elseif ($meta_length >= 100 && $meta_length <= 180) {
                $score += 10;
            }
        }
        
        // Image optimization (0-10 points)
        if (has_post_thumbnail($post->ID)) {
            $score += 10;
        }
        
        // Internal links (0-10 points)
        $internal_links = substr_count($content, get_home_url());
        if ($internal_links >= 3) {
            $score += 10;
        } elseif ($internal_links >= 1) {
            $score += 5;
        }
        
        // Keyword presence in title (0-10 points)
        $keywords = $this->extract_keywords($post);
        $title_lower = strtolower($post->post_title);
        $keyword_in_title = false;
        foreach ($keywords as $keyword) {
            if (strpos($title_lower, strtolower($keyword)) !== false) {
                $keyword_in_title = true;
                break;
            }
        }
        if ($keyword_in_title) {
            $score += 10;
        }
        
        // Readability (0-10 points)
        $readability_score = $this->calculate_readability($post);
        $score += min(10, $readability_score / 10);
        
        // Schema markup (0-10 points)
        $has_schema = get_post_meta($post->ID, 'gsc_schema_valid', true);
        if ($has_schema === '1') {
            $score += 10;
        }
        
        // Store the score
        update_post_meta($post->ID, 'gsc_seo_score', $score);
        
        return round($score, 2);
    }
    
    public function calculate_readability($post) {
        $content = strip_tags($post->post_content);
        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $words = str_word_count($content);
        
        if (count($sentences) == 0) return 0;
        
        $avg_sentence_length = $words / count($sentences);
        
        // Simple readability score (0-100)
        // Lower avg sentence length = better readability
        if ($avg_sentence_length <= 15) {
            $readability = 100;
        } elseif ($avg_sentence_length <= 20) {
            $readability = 80;
        } elseif ($avg_sentence_length <= 25) {
            $readability = 60;
        } elseif ($avg_sentence_length <= 30) {
            $readability = 40;
        } else {
            $readability = 20;
        }
        
        return $readability;
    }
    
    public function analyze_keywords($post) {
        $content = strtolower(strip_tags($post->post_content));
        $title = strtolower($post->post_title);
        $all_text = $title . ' ' . $content;
        
        // Remove common German words
        $stop_words = array('der', 'die', 'das', 'und', 'ist', 'im', 'in', 'von', 'zu', 'mit', 'auf', 'für', 'ein', 'eine');
        
        // Extract words
        $words = str_word_count($all_text, 1);
        $word_freq = array();
        
        foreach ($words as $word) {
            if (strlen($word) > 3 && !in_array($word, $stop_words)) {
                if (!isset($word_freq[$word])) {
                    $word_freq[$word] = 0;
                }
                $word_freq[$word]++;
            }
        }
        
        arsort($word_freq);
        $top_keywords = array_slice($word_freq, 0, 10, true);
        
        return array(
            'keywords' => $top_keywords,
            'total_words' => count($words),
            'unique_words' => count($word_freq)
        );
    }
    
    public function get_content_suggestions($post) {
        $suggestions = array();
        $content = strip_tags($post->post_content);
        $word_count = str_word_count($content);
        
        // Content length suggestions
        if ($word_count < 150) {
            $suggestions[] = array(
                'type' => 'warning',
                'message' => 'Content is too short. Add at least ' . (150 - $word_count) . ' more words for better SEO.',
                'priority' => 'high'
            );
        } elseif ($word_count < 300) {
            $suggestions[] = array(
                'type' => 'info',
                'message' => 'Consider adding more detailed information (aim for 300+ words).',
                'priority' => 'medium'
            );
        } else {
            $suggestions[] = array(
                'type' => 'success',
                'message' => 'Content length is good for SEO.',
                'priority' => 'low'
            );
        }
        
        // Image suggestions
        if (!has_post_thumbnail($post->ID)) {
            $suggestions[] = array(
                'type' => 'warning',
                'message' => 'Add a featured image to improve engagement and SEO.',
                'priority' => 'high'
            );
        }
        
        // Meta description
        $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        if (empty($meta_desc)) {
            $suggestions[] = array(
                'type' => 'warning',
                'message' => 'Add a meta description to improve click-through rates.',
                'priority' => 'high'
            );
        }
        
        // Internal linking
        $internal_links = substr_count($content, get_home_url());
        if ($internal_links < 2) {
            $suggestions[] = array(
                'type' => 'info',
                'message' => 'Add internal links to related products to improve site structure.',
                'priority' => 'medium'
            );
        }
        
        // Product-specific suggestions
        if ($post->post_type === 'product') {
            $price = get_post_meta($post->ID, '_price', true);
            if (empty($price)) {
                $suggestions[] = array(
                    'type' => 'error',
                    'message' => 'Product price is missing. This is required for proper schema markup.',
                    'priority' => 'critical'
                );
            }
        }
        
        return $suggestions;
    }
    
    public function compare_with_competitors($post) {
        // Placeholder for competitor comparison
        // In production, this would analyze competitor content
        
        return array(
            'competitor_avg_word_count' => 450,
            'your_word_count' => str_word_count(strip_tags($post->post_content)),
            'competitor_avg_seo_score' => 75,
            'your_seo_score' => $this->calculate_seo_score($post),
            'recommendations' => array(
                'Competitors average 450 words - consider adding more content',
                'Add more product details and specifications',
                'Include customer testimonials or reviews'
            )
        );
    }
    
    private function extract_keywords($post) {
        $title = strtolower($post->post_title);
        $words = explode(' ', $title);
        
        // Filter out common words
        $keywords = array_filter($words, function($word) {
            return strlen($word) > 3;
        });
        
        return array_values($keywords);
    }
}
