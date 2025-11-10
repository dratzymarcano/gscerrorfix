<?php
/**
 * Schema Validator Class
 * Real-time schema validation and testing
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSC_Schema_Validator {
    private $options;
    
    public function __construct($options) {
        $this->options = $options;
    }
    
    public function validate_schema($schema) {
        $errors = array();
        $warnings = array();
        $passed = array();
        
        // Check required Product schema fields
        if (isset($schema['@type']) && $schema['@type'] === 'Product') {
            // Required fields check
            if (!isset($schema['name']) || empty($schema['name'])) {
                $errors[] = 'Missing required field: name';
            } else {
                $passed[] = 'Product name present';
            }
            
            if (!isset($schema['image']) || empty($schema['image'])) {
                $errors[] = 'Missing required field: image';
            } else {
                $passed[] = 'Product image present';
            }
            
            // Check for at least one of: offers, review, aggregateRating
            $has_offers = isset($schema['offers']) && !empty($schema['offers']);
            $has_review = isset($schema['review']) && !empty($schema['review']);
            $has_rating = isset($schema['aggregateRating']) && !empty($schema['aggregateRating']);
            
            if (!$has_offers && !$has_review && !$has_rating) {
                $errors[] = 'Must have at least one of: offers, review, or aggregateRating';
            } else {
                if ($has_offers) $passed[] = 'Offers schema present';
                if ($has_review) $passed[] = 'Review schema present';
                if ($has_rating) $passed[] = 'AggregateRating schema present';
            }
            
            // Validate offers if present
            if ($has_offers) {
                $offer_validation = $this->validate_offers($schema['offers']);
                $errors = array_merge($errors, $offer_validation['errors']);
                $warnings = array_merge($warnings, $offer_validation['warnings']);
                $passed = array_merge($passed, $offer_validation['passed']);
            }
            
            // Validate review if present
            if ($has_review) {
                $review_validation = $this->validate_review($schema['review']);
                $errors = array_merge($errors, $review_validation['errors']);
                $warnings = array_merge($warnings, $review_validation['warnings']);
                $passed = array_merge($passed, $review_validation['passed']);
            }
            
            // Validate aggregateRating if present
            if ($has_rating) {
                $rating_validation = $this->validate_aggregate_rating($schema['aggregateRating']);
                $errors = array_merge($errors, $rating_validation['errors']);
                $warnings = array_merge($warnings, $rating_validation['warnings']);
                $passed = array_merge($passed, $rating_validation['passed']);
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'passed' => $passed,
            'score' => $this->calculate_validation_score($errors, $warnings, $passed)
        );
    }
    
    public function validate_all_products() {
        $products = get_posts(array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $results = array(
            'total' => count($products),
            'valid' => 0,
            'invalid' => 0,
            'warnings' => 0,
            'products' => array()
        );
        
        foreach ($products as $product) {
            $schema = $this->generate_product_schema($product);
            $validation = $this->validate_schema($schema);
            
            if ($validation['valid']) {
                $results['valid']++;
            } else {
                $results['invalid']++;
            }
            
            if (!empty($validation['warnings'])) {
                $results['warnings']++;
            }
            
            $results['products'][] = array(
                'id' => $product->ID,
                'title' => $product->post_title,
                'validation' => $validation
            );
            
            // Store validation result
            update_post_meta($product->ID, 'gsc_schema_valid', $validation['valid'] ? '1' : '0');
            update_post_meta($product->ID, 'gsc_schema_errors', wp_json_encode($validation['errors']));
            update_post_meta($product->ID, 'gsc_validation_score', $validation['score']);
        }
        
        return $results;
    }
    
    private function validate_offers($offers) {
        $errors = array();
        $warnings = array();
        $passed = array();
        
        if (!isset($offers['price'])) {
            $errors[] = 'Offers missing required field: price';
        } else {
            $passed[] = 'Offer price present';
        }
        
        if (!isset($offers['priceCurrency'])) {
            $errors[] = 'Offers missing required field: priceCurrency';
        } else {
            $passed[] = 'Offer currency present';
        }
        
        if (!isset($offers['availability'])) {
            $warnings[] = 'Offers missing recommended field: availability';
        } else {
            $passed[] = 'Offer availability present';
        }
        
        if (!isset($offers['url'])) {
            $warnings[] = 'Offers missing recommended field: url';
        } else {
            $passed[] = 'Offer URL present';
        }
        
        return array('errors' => $errors, 'warnings' => $warnings, 'passed' => $passed);
    }
    
    private function validate_review($review) {
        $errors = array();
        $warnings = array();
        $passed = array();
        
        if (!isset($review['author'])) {
            $errors[] = 'Review missing required field: author';
        } else {
            $passed[] = 'Review author present';
        }
        
        if (!isset($review['reviewRating'])) {
            $errors[] = 'Review missing required field: reviewRating';
        } else {
            $passed[] = 'Review rating present';
        }
        
        if (!isset($review['reviewBody']) && !isset($review['description'])) {
            $warnings[] = 'Review missing recommended field: reviewBody';
        } else {
            $passed[] = 'Review body present';
        }
        
        return array('errors' => $errors, 'warnings' => $warnings, 'passed' => $passed);
    }
    
    private function validate_aggregate_rating($rating) {
        $errors = array();
        $warnings = array();
        $passed = array();
        
        if (!isset($rating['ratingValue'])) {
            $errors[] = 'AggregateRating missing required field: ratingValue';
        } else {
            $passed[] = 'Rating value present';
        }
        
        if (!isset($rating['reviewCount']) && !isset($rating['ratingCount'])) {
            $errors[] = 'AggregateRating missing required field: reviewCount or ratingCount';
        } else {
            $passed[] = 'Rating count present';
        }
        
        if (!isset($rating['bestRating'])) {
            $warnings[] = 'AggregateRating missing recommended field: bestRating';
        } else {
            $passed[] = 'Best rating present';
        }
        
        return array('errors' => $errors, 'warnings' => $warnings, 'passed' => $passed);
    }
    
    private function calculate_validation_score($errors, $warnings, $passed) {
        $total_checks = count($errors) + count($warnings) + count($passed);
        if ($total_checks === 0) return 0;
        
        $score = (count($passed) * 100) - (count($errors) * 20) - (count($warnings) * 5);
        return max(0, min(100, $score));
    }
    
    private function generate_product_schema($product) {
        // This would use the main plugin's schema generation
        // Placeholder for now
        return array('@type' => 'Product');
    }
}
