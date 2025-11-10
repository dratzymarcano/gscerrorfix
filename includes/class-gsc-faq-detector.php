<?php
/**
 * FAQ Page Detector & Schema Generator
 * Intelligently detects FAQ pages and generates proper FAQ schema
 */

if (!defined('ABSPATH')) {
    exit;
}

class GSC_FAQ_Detector {
    
    private $options;
    private $faq_indicators = array(
        'faq', 'frequently asked questions', 'häufige fragen', 'faqs',
        'questions', 'q&a', 'q & a', 'help', 'hilfe', 'support'
    );
    
    public function __construct($options) {
        $this->options = $options;
    }
    
    public function is_faq_page($post) {
        // Check post title
        $title = strtolower($post->post_title);
        foreach ($this->faq_indicators as $indicator) {
            if (strpos($title, $indicator) !== false) {
                return true;
            }
        }
        
        // Check post slug
        $slug = strtolower($post->post_name);
        foreach ($this->faq_indicators as $indicator) {
            if (strpos($slug, $indicator) !== false) {
                return true;
            }
        }
        
        // Check content for FAQ patterns
        $content = strtolower($post->post_content);
        $question_count = $this->count_question_patterns($content);
        
        // If 3+ questions found, likely an FAQ page
        if ($question_count >= 3) {
            return true;
        }
        
        return false;
    }
    
    public function has_existing_faq_schema($post) {
        // Check if FAQ schema already exists in content
        $content = $post->post_content;
        
        if (strpos($content, '"@type":"FAQPage"') !== false || 
            strpos($content, '"@type": "FAQPage"') !== false) {
            return true;
        }
        
        // Check post meta
        $existing_schema = get_post_meta($post->ID, 'gsc_faq_schema_added', true);
        if ($existing_schema) {
            return true;
        }
        
        return false;
    }
    
    public function extract_faqs_from_content($post) {
        $content = $post->post_content;
        $faqs = array();
        
        // Method 1: Extract from HTML headings + paragraphs
        $faqs = array_merge($faqs, $this->extract_from_headings($content));
        
        // Method 2: Extract from accordion/toggle patterns
        $faqs = array_merge($faqs, $this->extract_from_accordions($content));
        
        // Method 3: Extract from Q&A patterns
        $faqs = array_merge($faqs, $this->extract_from_qa_patterns($content));
        
        // Method 4: Extract from shortcodes
        $faqs = array_merge($faqs, $this->extract_from_shortcodes($content));
        
        // Remove duplicates and format for schema
        $faqs = $this->format_for_schema($faqs);
        
        return $faqs;
    }
    
    private function count_question_patterns($content) {
        $question_markers = array('?', 'wie', 'was', 'wer', 'wo', 'wann', 'warum', 'how', 'what', 'who', 'where', 'when', 'why');
        $count = 0;
        
        // Count question marks
        $count += substr_count($content, '?');
        
        // Count question words at start of sentences
        foreach ($question_markers as $marker) {
            $pattern = '/\b' . preg_quote($marker, '/') . '\b/i';
            preg_match_all($pattern, $content, $matches);
            $count += count($matches[0]);
        }
        
        return $count;
    }
    
    private function extract_from_headings($content) {
        $faqs = array();
        
        // Remove shortcodes first
        $content = strip_shortcodes($content);
        
        // Parse HTML
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Find all headings (h2, h3, h4)
        $headings = array();
        foreach (array('h2', 'h3', 'h4') as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            foreach ($elements as $element) {
                $headings[] = $element;
            }
        }
        
        foreach ($headings as $i => $heading) {
            $question = trim($heading->textContent);
            
            // Skip if doesn't look like a question
            if (empty($question) || strlen($question) < 10) {
                continue;
            }
            
            // Get next element as answer
            $answer = $this->get_next_element_text($heading);
            
            if (!empty($answer) && strlen($answer) > 20) {
                $faqs[] = array(
                    'question' => $question,
                    'answer' => $answer
                );
            }
        }
        
        return $faqs;
    }
    
    private function extract_from_accordions($content) {
        $faqs = array();
        
        // Common accordion patterns
        $patterns = array(
            '/<div[^>]*class="[^"]*accordion[^"]*"[^>]*>(.*?)<\/div>/is',
            '/<details[^>]*>(.*?)<\/details>/is',
        );
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            foreach ($matches[1] as $accordion_content) {
                // Extract question from summary/header
                if (preg_match('/<summary[^>]*>(.*?)<\/summary>/is', $accordion_content, $question_match)) {
                    $question = strip_tags($question_match[1]);
                    
                    // Get answer (everything after summary)
                    $answer = preg_replace('/<summary[^>]*>.*?<\/summary>/is', '', $accordion_content);
                    $answer = wp_trim_words(strip_tags($answer), 100, '...');
                    
                    if (!empty($question) && !empty($answer)) {
                        $faqs[] = array(
                            'question' => trim($question),
                            'answer' => trim($answer)
                        );
                    }
                }
            }
        }
        
        return $faqs;
    }
    
    private function extract_from_qa_patterns($content) {
        $faqs = array();
        
        // Match Q: ... A: ... patterns
        preg_match_all('/(?:Q:|Question:|Frage:)\s*(.*?)\s*(?:A:|Answer:|Antwort:)\s*(.*?)(?=Q:|Question:|Frage:|$)/is', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            if (isset($match[1]) && isset($match[2])) {
                $faqs[] = array(
                    'question' => trim(strip_tags($match[1])),
                    'answer' => trim(strip_tags($match[2]))
                );
            }
        }
        
        return $faqs;
    }
    
    private function extract_from_shortcodes($content) {
        $faqs = array();
        
        // Common FAQ shortcode patterns
        if (preg_match_all('/\[faq[^\]]*\](.*?)\[\/faq\]/is', $content, $matches)) {
            foreach ($matches[1] as $faq_content) {
                // Extract question and answer
                if (preg_match('/question="([^"]+)".*?answer="([^"]+)"/is', $faq_content, $qa)) {
                    $faqs[] = array(
                        'question' => $qa[1],
                        'answer' => $qa[2]
                    );
                }
            }
        }
        
        return $faqs;
    }
    
    private function get_next_element_text($element) {
        $next = $element->nextSibling;
        $text = '';
        
        while ($next && empty($text)) {
            if ($next->nodeType === XML_ELEMENT_NODE) {
                $text = trim($next->textContent);
                break;
            }
            $next = $next->nextSibling;
        }
        
        return wp_trim_words($text, 100, '...');
    }
    
    private function format_for_schema($faqs) {
        $formatted = array();
        $seen = array();
        
        foreach ($faqs as $faq) {
            if (empty($faq['question']) || empty($faq['answer'])) {
                continue;
            }
            
            // Remove duplicates
            $key = md5($faq['question']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            
            $formatted[] = array(
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text' => $faq['answer']
                )
            );
        }
        
        return $formatted;
    }
}
