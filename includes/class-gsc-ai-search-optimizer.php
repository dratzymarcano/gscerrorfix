<?php
/**
 * AI Search Optimizer
 * Optimizes content for Google AI Overview, Bing Chat, and other AI-powered search
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSC_AI_Search_Optimizer {
    
    private $options;
    
    public function __construct($options) {
        $this->options = $options;
    }
    
    public function optimize_for_ai_search($post, $keywords = array()) {
        $optimizations = array();
        
        // 1. Structured content optimization
        $optimizations['structured_data'] = $this->ensure_structured_data($post);
        
        // 2. Featured snippet optimization
        $optimizations['featured_snippet'] = $this->optimize_for_featured_snippets($post, $keywords);
        
        // 3. Question-answer format
        $optimizations['qa_format'] = $this->add_qa_format($post);
        
        // 4. Concise summaries for AI
        $optimizations['ai_summary'] = $this->generate_ai_summary($post, $keywords);
        
        // 5. Entity optimization
        $optimizations['entities'] = $this->optimize_entities($post);
        
        // 6. Conversational query optimization
        $optimizations['conversational'] = $this->optimize_for_conversational_queries($post, $keywords);
        
        return $optimizations;
    }
    
    private function ensure_structured_data($post) {
        // Ensure all critical structured data exists
        $has_schema = get_post_meta($post->ID, 'gsc_schema_added', true);
        
        return array(
            'has_product_schema' => $has_schema ? true : false,
            'has_organization' => true, // Added by main plugin
            'has_breadcrumbs' => $this->has_breadcrumb_schema($post),
            'recommendation' => !$has_schema ? 'Add complete product schema with offers, reviews, and ratings' : 'Schema complete'
        );
    }
    
    private function optimize_for_featured_snippets($post, $keywords) {
        $content = $post->post_content;
        $optimizations = array();
        
        // Check for list format (good for snippets)
        $has_lists = preg_match('/<ul>|<ol>|<li>/i', $content);
        $optimizations['has_lists'] = $has_lists;
        
        // Check for definition paragraphs
        $has_definitions = $this->has_definition_format($content, $keywords);
        $optimizations['has_definitions'] = $has_definitions;
        
        // Check for table data
        $has_tables = preg_match('/<table>/i', $content);
        $optimizations['has_tables'] = $has_tables;
        
        // Recommendation
        if (!$has_lists && !$has_tables) {
            $optimizations['recommendation'] = 'Add bulleted lists or tables to increase featured snippet chances';
        } else {
            $optimizations['recommendation'] = 'Good structure for featured snippets';
        }
        
        return $optimizations;
    }
    
    private function add_qa_format($post) {
        // Check if content already has Q&A format
        $content = $post->post_content;
        $has_qa = preg_match('/Q:|Question:|Frage:|A:|Answer:|Antwort:/i', $content);
        
        if ($has_qa) {
            return array(
                'has_qa_format' => true,
                'recommendation' => 'Q&A format detected - good for AI search'
            );
        }
        
        // Extract potential questions from headings
        $questions = array();
        if (preg_match_all('/<h[2-4][^>]*>(.*?)<\/h[2-4]>/is', $content, $matches)) {
            foreach ($matches[1] as $heading) {
                $heading = strip_tags($heading);
                if (preg_match('/\?|wie|was|warum|how|what|why|which/i', $heading)) {
                    $questions[] = $heading;
                }
            }
        }
        
        return array(
            'has_qa_format' => false,
            'potential_questions' => count($questions),
            'questions' => array_slice($questions, 0, 5),
            'recommendation' => count($questions) > 0 ? 
                'Consider adding Q&A format with ' . count($questions) . ' detected questions' : 
                'Add Q&A format to improve AI search visibility'
        );
    }
    
    private function generate_ai_summary($post, $keywords) {
        $content = strip_tags(strip_shortcodes($post->post_content));
        
        // Generate concise summary (ideal for AI overview)
        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        
        // Get first 2-3 most relevant sentences
        $summary_sentences = array();
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence) || strlen($sentence) < 20) {
                continue;
            }
            
            // Prioritize sentences with keywords
            $relevance_score = 0;
            foreach ($keywords as $keyword) {
                if (stripos($sentence, $keyword) !== false) {
                    $relevance_score++;
                }
            }
            
            $summary_sentences[] = array(
                'text' => $sentence,
                'score' => $relevance_score
            );
        }
        
        // Sort by relevance
        usort($summary_sentences, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Take top 3
        $summary = '';
        for ($i = 0; $i < min(3, count($summary_sentences)); $i++) {
            $summary .= $summary_sentences[$i]['text'] . '. ';
        }
        
        // Save as meta for AI search engines
        if (!empty($summary)) {
            update_post_meta($post->ID, 'gsc_ai_summary', trim($summary));
        }
        
        return array(
            'summary' => trim($summary),
            'length' => strlen($summary),
            'keyword_density' => $this->calculate_keyword_density($summary, $keywords),
            'recommendation' => strlen($summary) < 100 ? 'Expand summary to 150-200 chars' : 'AI-ready summary generated'
        );
    }
    
    private function optimize_entities($post) {
        // Extract named entities for better AI understanding
        $content = strip_tags($post->post_content);
        
        $entities = array(
            'brands' => $this->extract_brands($content),
            'locations' => $this->extract_locations($content),
            'products' => $this->extract_product_mentions($content),
            'organizations' => $this->extract_organizations($content)
        );
        
        // Add entity markup
        $entity_count = 0;
        foreach ($entities as $type => $items) {
            $entity_count += count($items);
        }
        
        return array(
            'entities_found' => $entity_count,
            'entities' => $entities,
            'recommendation' => $entity_count > 5 ? 'Good entity coverage for AI' : 'Add more specific brand/product mentions'
        );
    }
    
    private function optimize_for_conversational_queries($post, $keywords) {
        // Optimize for how people ask AI assistants
        $conversational_patterns = array(
            'what is', 'how to', 'why is', 'where can', 'when should',
            'was ist', 'wie kann', 'warum ist', 'wo kann', 'wann sollte'
        );
        
        $content = strtolower($post->post_content);
        $matches = 0;
        
        foreach ($conversational_patterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                $matches++;
            }
        }
        
        // Generate conversational alternatives
        $suggestions = array();
        foreach (array_slice($keywords, 0, 3) as $keyword) {
            $suggestions[] = "What is {$keyword}?";
            $suggestions[] = "How to use {$keyword}?";
            $suggestions[] = "Why choose {$keyword}?";
        }
        
        return array(
            'conversational_matches' => $matches,
            'score' => $matches > 3 ? 'Good' : 'Needs improvement',
            'suggestions' => array_slice($suggestions, 0, 5),
            'recommendation' => $matches > 3 ? 
                'Content is conversational-query friendly' : 
                'Add more conversational question formats'
        );
    }
    
    private function has_definition_format($content, $keywords) {
        // Check for definition patterns
        $patterns = array(
            '/\b(is|are|means?|refers? to|defined as)\b/i',
            '/\b(ist|sind|bedeutet|bezieht sich auf)\b/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function has_breadcrumb_schema($post) {
        // Check if breadcrumb schema exists
        $content = $post->post_content;
        return strpos($content, '"@type":"BreadcrumbList"') !== false || 
               strpos($content, '"@type": "BreadcrumbList"') !== false;
    }
    
    private function calculate_keyword_density($text, $keywords) {
        $word_count = str_word_count($text);
        if ($word_count === 0) {
            return 0;
        }
        
        $keyword_count = 0;
        foreach ($keywords as $keyword) {
            $keyword_count += substr_count(strtolower($text), strtolower($keyword));
        }
        
        return round(($keyword_count / $word_count) * 100, 2);
    }
    
    private function extract_brands($content) {
        // Common brand indicators (capitalized words, trademarks)
        preg_match_all('/\b([A-ZÄÖÜ][a-zäöüß]+(?:\s+[A-ZÄÖÜ][a-zäöüß]+)?)\b/', $content, $matches);
        
        if (empty($matches[1])) {
            return array();
        }
        
        // Filter common words
        $brands = array_filter($matches[1], function($word) {
            return !in_array(strtolower($word), array('the', 'this', 'that', 'and', 'or', 'but', 'der', 'die', 'das'));
        });
        
        return array_unique(array_slice($brands, 0, 10));
    }
    
    private function extract_locations($content) {
        // Common location patterns
        $locations = array();
        
        // Cities/countries (capitalized)
        preg_match_all('/\b(Deutschland|Germany|Berlin|München|Hamburg|Österreich|Austria|Schweiz|Switzerland)\b/i', $content, $matches);
        if (!empty($matches[1])) {
            $locations = array_merge($locations, $matches[1]);
        }
        
        return array_unique($locations);
    }
    
    private function extract_product_mentions($content) {
        // Extract product-related terms
        preg_match_all('/\b([A-ZÄÖÜ][a-zäöüß]+\s+(?:Pro|Plus|Max|Premium|Edition|Series|Model))\b/', $content, $matches);
        
        return !empty($matches[1]) ? array_unique(array_slice($matches[1], 0, 10)) : array();
    }
    
    private function extract_organizations($content) {
        // Organization patterns (GmbH, AG, Inc, LLC, etc.)
        preg_match_all('/\b([A-ZÄÖÜ][a-zäöüß]+(?:\s+[A-ZÄÖÜ][a-zäöüß]+)*\s+(?:GmbH|AG|Inc\.|LLC|Ltd\.|Co\.))\b/', $content, $matches);
        
        return !empty($matches[1]) ? array_unique($matches[1]) : array();
    }
}
