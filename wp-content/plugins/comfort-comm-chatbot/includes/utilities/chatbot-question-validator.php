<?php
/**
 * Question Validator - Ver 2.4.8
 *
 * Validates questions before logging to gap_questions table.
 * Filters out spam, gibberish, off-topic, and low-quality questions.
 *
 * @package chatbot-chatgpt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die();
}

/**
 * Validate a question for quality and relevance
 *
 * @param string $question The question to validate
 * @param float $faq_confidence The FAQ match confidence (0-1)
 * @param array $options Optional settings
 * @return array ['is_valid' => bool, 'reason' => string, 'quality_score' => float]
 */
function chatbot_validate_gap_question($question, $faq_confidence = 0, $options = []) {
    $defaults = [
        'min_length' => 10,           // Minimum characters
        'max_length' => 1000,         // Maximum characters
        'min_words' => 3,             // Minimum words
        'min_quality_score' => 0.3,   // Minimum quality score to accept
        'check_relevance' => true,    // Check topic relevance
        'check_spam' => true,         // Check for spam patterns
        'check_gibberish' => true,    // Check for gibberish
    ];
    $options = array_merge($defaults, $options);

    $result = [
        'is_valid' => true,
        'reason' => 'valid',
        'quality_score' => 1.0,
        'flags' => []
    ];

    $question = trim($question);

    // 1. Basic length checks
    if (strlen($question) < $options['min_length']) {
        return [
            'is_valid' => false,
            'reason' => 'too_short',
            'quality_score' => 0,
            'flags' => ['length']
        ];
    }

    if (strlen($question) > $options['max_length']) {
        return [
            'is_valid' => false,
            'reason' => 'too_long',
            'quality_score' => 0,
            'flags' => ['length']
        ];
    }

    // 2. Word count check
    $words = preg_split('/\s+/', $question);
    $word_count = count($words);
    if ($word_count < $options['min_words']) {
        return [
            'is_valid' => false,
            'reason' => 'too_few_words',
            'quality_score' => 0.1,
            'flags' => ['word_count']
        ];
    }

    // 3. Gibberish detection
    if ($options['check_gibberish']) {
        $gibberish_score = chatbot_detect_gibberish($question);
        if ($gibberish_score > 0.6) {
            return [
                'is_valid' => false,
                'reason' => 'gibberish',
                'quality_score' => 0.1,
                'flags' => ['gibberish'],
                'gibberish_score' => $gibberish_score
            ];
        }
        $result['quality_score'] -= ($gibberish_score * 0.3);
    }

    // 4. Spam pattern detection
    if ($options['check_spam']) {
        $spam_result = chatbot_detect_spam_patterns($question);
        if ($spam_result['is_spam']) {
            return [
                'is_valid' => false,
                'reason' => 'spam',
                'quality_score' => 0,
                'flags' => ['spam'],
                'spam_patterns' => $spam_result['patterns']
            ];
        }
        if (!empty($spam_result['patterns'])) {
            $result['flags'][] = 'suspicious';
            $result['quality_score'] -= 0.2;
        }
    }

    // 5. Question quality scoring
    $quality_factors = chatbot_calculate_question_quality($question);
    $result['quality_score'] = min($result['quality_score'], $quality_factors['score']);
    $result['quality_factors'] = $quality_factors;

    // 6. Check if question is actually a question
    if (!$quality_factors['is_question']) {
        $result['quality_score'] -= 0.2;
        $result['flags'][] = 'not_question';
    }

    // 7. Final validation
    if ($result['quality_score'] < $options['min_quality_score']) {
        $result['is_valid'] = false;
        $result['reason'] = 'low_quality';
    }

    return $result;
}

/**
 * Detect gibberish text
 *
 * Uses multiple heuristics:
 * - Character repetition
 * - Vowel/consonant ratio
 * - Dictionary word ratio
 * - Keyboard mashing patterns
 *
 * @param string $text
 * @return float Score 0-1 (higher = more likely gibberish)
 */
function chatbot_detect_gibberish($text) {
    $text = strtolower(trim($text));
    $score = 0;

    // 1. Check for excessive character repetition (aaaaaaa, !!!!!!)
    if (preg_match('/(.)\1{4,}/', $text)) {
        $score += 0.4;
    }

    // 2. Check vowel/consonant ratio (normal English is ~40% vowels)
    $vowels = preg_match_all('/[aeiou]/i', $text);
    $consonants = preg_match_all('/[bcdfghjklmnpqrstvwxyz]/i', $text);
    $total_letters = $vowels + $consonants;

    if ($total_letters > 5) {
        $vowel_ratio = $vowels / $total_letters;
        if ($vowel_ratio < 0.15 || $vowel_ratio > 0.7) {
            $score += 0.3;
        }
    }

    // 3. Check for keyboard mashing patterns
    $keyboard_patterns = [
        '/asdf/i', '/qwer/i', '/zxcv/i', '/jkl;/i',
        '/[qwerty]{5,}/i', '/[asdfgh]{5,}/i', '/[zxcvbn]{5,}/i',
        '/\d{6,}/',  // Long number sequences
        '/hjkl/i', '/ghjk/i', '/dfgh/i',  // More keyboard rows
    ];
    foreach ($keyboard_patterns as $pattern) {
        if (preg_match($pattern, $text)) {
            $score += 0.3;
        }
    }

    // 3b. Check for explicit test/nonsense indicators
    $test_patterns = [
        '/\brandom\b/i', '/\bnonsense\b/i', '/\btest\b/i', '/\btesting\b/i',
        '/\bfoo\b/i', '/\bbar\b/i', '/\bbaz\b/i', '/\bxyz\b/i',
        '/\blorem\b/i', '/\bipsum\b/i', '/\bblah\b/i', '/\bgarbage\b/i',
        '/\bjunk\b/i', '/\bfake\b/i', '/\bdummy\b/i', '/\bplaceholder\b/i',
    ];
    $test_match_count = 0;
    foreach ($test_patterns as $pattern) {
        if (preg_match($pattern, $text)) {
            $test_match_count++;
        }
    }
    // If 2+ test words found, likely junk
    if ($test_match_count >= 2) {
        $score += 0.5;
    } elseif ($test_match_count === 1) {
        $score += 0.2;
    }

    // 4. Check ratio of real words (simple check)
    $words = preg_split('/\s+/', $text);
    $common_words = ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
        'should', 'may', 'might', 'can', 'to', 'of', 'in', 'for', 'on', 'with',
        'at', 'by', 'from', 'as', 'into', 'through', 'during', 'before', 'after',
        'above', 'below', 'between', 'under', 'again', 'further', 'then', 'once',
        'here', 'there', 'when', 'where', 'why', 'how', 'all', 'each', 'few',
        'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only',
        'own', 'same', 'so', 'than', 'too', 'very', 'just', 'and', 'but', 'if',
        'or', 'because', 'until', 'while', 'what', 'which', 'who', 'whom',
        'this', 'that', 'these', 'those', 'am', 'i', 'you', 'he', 'she', 'it',
        'we', 'they', 'my', 'your', 'his', 'her', 'its', 'our', 'their', 'me',
        'him', 'us', 'them', 'help', 'need', 'want', 'know', 'get', 'make',
        'please', 'thank', 'thanks', 'yes', 'no', 'okay', 'ok'];

    $real_word_count = 0;
    foreach ($words as $word) {
        $clean_word = preg_replace('/[^a-z]/', '', strtolower($word));
        if (strlen($clean_word) >= 2 && (in_array($clean_word, $common_words) || strlen($clean_word) >= 4)) {
            $real_word_count++;
        }
    }

    if (count($words) > 0) {
        $real_word_ratio = $real_word_count / count($words);
        if ($real_word_ratio < 0.3) {
            $score += 0.3;
        }
    }

    // 5. Check for excessive special characters
    $special_char_count = preg_match_all('/[^a-zA-Z0-9\s\?\.\,\!\']/', $text);
    $special_ratio = $special_char_count / max(strlen($text), 1);
    if ($special_ratio > 0.3) {
        $score += 0.2;
    }

    return min($score, 1.0);
}

/**
 * Detect spam patterns
 *
 * @param string $text
 * @return array ['is_spam' => bool, 'patterns' => array]
 */
function chatbot_detect_spam_patterns($text) {
    $text_lower = strtolower($text);
    $found_patterns = [];

    // Spam patterns to check
    $spam_patterns = [
        // URLs and links
        'url' => '/https?:\/\/[^\s]+/i',
        'link_text' => '/click here|visit (my |our )?site|check (this )?out/i',

        // Contact/promotion spam
        'email_spam' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        'phone_spam' => '/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/',
        'promo' => '/\b(buy now|limited offer|act now|free money|winner|congratulations|prize)\b/i',

        // Abuse/harassment
        'profanity' => '/\b(fuck|shit|ass|bitch|damn|crap|bastard|dick|pussy)\b/i',
        'threats' => '/\b(kill|die|hurt|attack|destroy|hack)\s+(you|your|them|this)/i',

        // Test/debug messages
        'test' => '/^(test|testing|hello|hi|hey|asdf|qwer|1234|abc)\s*$/i',

        // Repetitive nonsense
        'repetitive' => '/\b(\w+)\s+\1\s+\1\b/i',  // Same word 3+ times

        // All caps (shouting)
        'all_caps' => '/^[A-Z\s\!\?\.]{20,}$/',
    ];

    foreach ($spam_patterns as $name => $pattern) {
        if (preg_match($pattern, $text)) {
            $found_patterns[] = $name;
        }
    }

    // Determine if it's definitely spam
    $definite_spam = ['profanity', 'threats', 'promo', 'url'];
    $is_spam = !empty(array_intersect($found_patterns, $definite_spam));

    return [
        'is_spam' => $is_spam,
        'patterns' => $found_patterns
    ];
}

/**
 * Calculate question quality score
 *
 * @param string $question
 * @return array ['score' => float, 'is_question' => bool, 'factors' => array]
 */
function chatbot_calculate_question_quality($question) {
    $score = 0.5; // Start at neutral
    $factors = [];

    // 1. Check if it ends with a question mark or contains question words
    $is_question = false;
    $question_words = ['what', 'how', 'why', 'when', 'where', 'who', 'which', 'can', 'could', 'would', 'should', 'is', 'are', 'do', 'does', 'will'];

    if (preg_match('/\?$/', trim($question))) {
        $is_question = true;
        $score += 0.1;
        $factors[] = 'has_question_mark';
    }

    $first_word = strtolower(preg_split('/\s+/', trim($question))[0] ?? '');
    if (in_array($first_word, $question_words)) {
        $is_question = true;
        $score += 0.1;
        $factors[] = 'starts_with_question_word';
    }

    // 2. Check for proper capitalization (first letter)
    if (preg_match('/^[A-Z]/', $question)) {
        $score += 0.05;
        $factors[] = 'proper_capitalization';
    }

    // 3. Check for reasonable word length average
    $words = preg_split('/\s+/', $question);
    $avg_word_length = array_sum(array_map('strlen', $words)) / max(count($words), 1);
    if ($avg_word_length >= 3 && $avg_word_length <= 10) {
        $score += 0.1;
        $factors[] = 'good_word_length';
    }

    // 4. Bonus for longer, more detailed questions
    $word_count = count($words);
    if ($word_count >= 5 && $word_count <= 30) {
        $score += 0.1;
        $factors[] = 'good_length';
    }

    // 5. Check for context keywords that suggest a real question
    $context_keywords = ['help', 'need', 'want', 'looking', 'find', 'know', 'understand',
        'explain', 'tell', 'show', 'problem', 'issue', 'question', 'wondering'];
    foreach ($context_keywords as $keyword) {
        if (stripos($question, $keyword) !== false) {
            $score += 0.05;
            $factors[] = 'has_context_keyword';
            break;
        }
    }

    return [
        'score' => min($score, 1.0),
        'is_question' => $is_question,
        'factors' => $factors
    ];
}

/**
 * Check if question is relevant to the knowledge base using Vector + AI (Option B)
 *
 * Flow:
 * 1. Generate embedding for question
 * 2. Compare to FAQ embeddings (vector similarity)
 * 3. If borderline (0.20-0.35), ask AI to verify
 *
 * @param string $question
 * @return array ['is_relevant' => bool, 'score' => float, 'method' => string, 'reason' => string]
 */
function chatbot_check_topic_relevance_smart($question) {
    $result = [
        'is_relevant' => true,
        'score' => 0.5,
        'method' => 'default',
        'reason' => 'No check performed'
    ];

    // Step 1: Try vector similarity first (FREE with Gemini)
    $vector_result = chatbot_check_relevance_vector($question);

    if ($vector_result !== null) {
        $result['score'] = $vector_result['max_similarity'];
        $result['method'] = 'vector';

        // Clear cases - no AI needed
        if ($vector_result['max_similarity'] >= 0.35) {
            // Clearly relevant
            $result['is_relevant'] = true;
            $result['reason'] = 'High similarity to FAQ: ' . ($vector_result['best_match_question'] ?? 'unknown');
            return $result;
        }

        if ($vector_result['max_similarity'] < 0.20) {
            // Clearly off-topic
            $result['is_relevant'] = false;
            $result['reason'] = 'Very low similarity to all FAQs';
            return $result;
        }

        // Borderline case (0.20 - 0.35) - ask AI to verify
        $result['method'] = 'vector+ai';
        $ai_result = chatbot_verify_relevance_with_ai($question, $vector_result);

        if ($ai_result !== null) {
            $result['is_relevant'] = $ai_result['is_relevant'];
            $result['reason'] = $ai_result['reason'];
            $result['ai_response'] = $ai_result;
        } else {
            // AI failed, use vector result with benefit of doubt
            $result['is_relevant'] = true;
            $result['reason'] = 'Borderline similarity, AI unavailable - keeping';
        }
    }

    return $result;
}

/**
 * Check relevance using vector similarity to FAQs
 *
 * @param string $question
 * @return array|null ['max_similarity' => float, 'best_match_question' => string]
 */
function chatbot_check_relevance_vector($question) {
    // Check if vector functions available
    if (!function_exists('chatbot_vector_generate_embedding')) {
        return null;
    }

    // Generate embedding for the question
    $question_embedding = chatbot_vector_generate_embedding($question);
    if (!$question_embedding) {
        return null;
    }

    // Get FAQ embeddings from database
    $faq_embeddings = chatbot_get_faq_embeddings_cached();
    if (empty($faq_embeddings)) {
        return null;
    }

    // Find max similarity
    $max_similarity = 0;
    $best_match = null;

    foreach ($faq_embeddings as $faq) {
        if (empty($faq['embedding'])) continue;

        $similarity = chatbot_cosine_similarity($question_embedding, $faq['embedding']);
        if ($similarity > $max_similarity) {
            $max_similarity = $similarity;
            $best_match = $faq;
        }
    }

    return [
        'max_similarity' => $max_similarity,
        'best_match_question' => $best_match['question'] ?? null,
        'best_match_id' => $best_match['faq_id'] ?? null
    ];
}

/**
 * Get FAQ embeddings (cached for performance)
 */
function chatbot_get_faq_embeddings_cached() {
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    // Try to get from transient first
    $cached = get_transient('chatbot_faq_embeddings_cache');
    if ($cached !== false) {
        $cache = $cached;
        return $cache;
    }

    // Fetch from database
    if (!function_exists('chatbot_supabase_request')) {
        return [];
    }

    $result = chatbot_supabase_request('chatbot_faqs', 'GET', null, [
        'select' => 'faq_id,question,combined_embedding',
        'limit' => '200'
    ]);

    if (!isset($result['success']) || !$result['success']) {
        return [];
    }

    $faqs = [];
    foreach ($result['data'] as $faq) {
        if (!empty($faq['combined_embedding'])) {
            $faqs[] = [
                'faq_id' => $faq['faq_id'],
                'question' => $faq['question'],
                'embedding' => chatbot_parse_pg_vector($faq['combined_embedding'])
            ];
        }
    }

    // Cache for 1 hour
    set_transient('chatbot_faq_embeddings_cache', $faqs, HOUR_IN_SECONDS);
    $cache = $faqs;

    return $cache;
}

/**
 * Parse PostgreSQL vector string to array
 */
function chatbot_parse_pg_vector($vector_string) {
    if (is_array($vector_string)) {
        return $vector_string;
    }

    // Remove brackets and split
    $vector_string = trim($vector_string, '[]');
    $values = explode(',', $vector_string);

    return array_map('floatval', $values);
}

/**
 * Calculate cosine similarity between two vectors
 */
function chatbot_cosine_similarity($vec1, $vec2) {
    if (count($vec1) !== count($vec2) || count($vec1) === 0) {
        return 0;
    }

    $dot_product = 0;
    $norm1 = 0;
    $norm2 = 0;

    for ($i = 0; $i < count($vec1); $i++) {
        $dot_product += $vec1[$i] * $vec2[$i];
        $norm1 += $vec1[$i] * $vec1[$i];
        $norm2 += $vec2[$i] * $vec2[$i];
    }

    $norm1 = sqrt($norm1);
    $norm2 = sqrt($norm2);

    if ($norm1 == 0 || $norm2 == 0) {
        return 0;
    }

    return $dot_product / ($norm1 * $norm2);
}

/**
 * Verify relevance with AI for borderline cases
 * Only called when vector similarity is between 0.20 - 0.35
 *
 * @param string $question
 * @param array $vector_result
 * @return array|null ['is_relevant' => bool, 'reason' => string]
 */
function chatbot_verify_relevance_with_ai($question, $vector_result) {
    // Get Gemini API key
    $api_key_encrypted = get_option('chatbot_gemini_api_key', '');
    if (empty($api_key_encrypted)) {
        return null;
    }

    if (!function_exists('chatbot_chatgpt_decrypt_api_key')) {
        return null;
    }

    $api_key = chatbot_chatgpt_decrypt_api_key($api_key_encrypted, 'chatbot_gemini_api_key');
    if (empty($api_key)) {
        return null;
    }

    // Get business context from FAQs
    $business_context = chatbot_get_business_context();

    // Build prompt
    $prompt = "You are a relevance classifier. Determine if the following question is related to the business/product or is completely off-topic.

Business Context (based on FAQ topics):
{$business_context}

Question to classify:
\"{$question}\"

Closest FAQ match (similarity: " . round($vector_result['max_similarity'] * 100) . "%):
\"{$vector_result['best_match_question']}\"

Respond with ONLY a JSON object (no markdown, no explanation):
{\"is_relevant\": true/false, \"reason\": \"brief explanation\"}

Rules:
- is_relevant = true if the question is about the business, products, services, or could reasonably be a customer question
- is_relevant = false if it's completely unrelated (weather, sports, personal questions, etc.)
- When in doubt, lean towards true (we don't want to miss valid customer questions)";

    // Call Gemini API (using flash model for speed and cost)
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;

    $body = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => 100
        ]
    ];

    $response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($body),
        'timeout' => 10
    ]);

    if (is_wp_error($response)) {
        error_log('[Chatbot] AI relevance check failed: ' . $response->get_error_message());
        return null;
    }

    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($response_body['candidates'][0]['content']['parts'][0]['text'])) {
        return null;
    }

    $ai_text = $response_body['candidates'][0]['content']['parts'][0]['text'];

    // Parse JSON response
    $ai_text = trim($ai_text);
    $ai_text = preg_replace('/^```json\s*/', '', $ai_text);
    $ai_text = preg_replace('/\s*```$/', '', $ai_text);

    $ai_result = json_decode($ai_text, true);

    if (!isset($ai_result['is_relevant'])) {
        return null;
    }

    return [
        'is_relevant' => (bool)$ai_result['is_relevant'],
        'reason' => $ai_result['reason'] ?? 'AI classification'
    ];
}

/**
 * Get business context from FAQ categories and questions
 */
function chatbot_get_business_context() {
    static $context = null;

    if ($context !== null) {
        return $context;
    }

    $topics = [];
    $sample_questions = [];

    if (function_exists('chatbot_supabase_request')) {
        $result = chatbot_supabase_request('chatbot_faqs', 'GET', null, [
            'select' => 'category,question',
            'limit' => '20'
        ]);

        if (isset($result['success']) && $result['success'] && !empty($result['data'])) {
            foreach ($result['data'] as $faq) {
                if (!empty($faq['category'])) {
                    $topics[] = $faq['category'];
                }
                if (!empty($faq['question'])) {
                    $sample_questions[] = $faq['question'];
                }
            }
        }
    }

    $topics = array_unique($topics);
    $sample_questions = array_slice($sample_questions, 0, 5);

    $context = "Topics: " . implode(', ', $topics) . "\n";
    $context .= "Sample questions: " . implode('; ', $sample_questions);

    return $context;
}

/**
 * Legacy function - Check if question is relevant to the knowledge base topics
 * This uses a simple keyword approach (kept for backwards compatibility)
 *
 * @param string $question
 * @param array $kb_topics Array of topic keywords from the knowledge base
 * @return float Relevance score 0-1
 */
function chatbot_check_topic_relevance($question, $kb_topics = []) {
    // If no topics provided, get from FAQ categories/keywords
    if (empty($kb_topics)) {
        $kb_topics = chatbot_get_knowledge_base_topics();
    }

    if (empty($kb_topics)) {
        // Can't check relevance without topics - assume relevant
        return 0.5;
    }

    $question_lower = strtolower($question);
    $matches = 0;

    foreach ($kb_topics as $topic) {
        if (stripos($question_lower, strtolower($topic)) !== false) {
            $matches++;
        }
    }

    // Calculate relevance score
    if ($matches >= 3) return 1.0;
    if ($matches >= 2) return 0.8;
    if ($matches >= 1) return 0.5;

    return 0.2; // No topic matches - low relevance
}

/**
 * Get topics/keywords from the knowledge base
 *
 * @return array
 */
function chatbot_get_knowledge_base_topics() {
    static $topics = null;

    if ($topics !== null) {
        return $topics;
    }

    $topics = [];

    // Get from FAQs
    if (function_exists('chatbot_supabase_request')) {
        $result = chatbot_supabase_request('chatbot_faqs', 'GET', null, [
            'select' => 'keywords,category',
            'limit' => '100'
        ]);

        if (isset($result['success']) && $result['success'] && !empty($result['data'])) {
            foreach ($result['data'] as $faq) {
                if (!empty($faq['keywords'])) {
                    $keywords = explode(',', $faq['keywords']);
                    $topics = array_merge($topics, array_map('trim', $keywords));
                }
                if (!empty($faq['category'])) {
                    $topics[] = trim($faq['category']);
                }
            }
        }
    }

    // Remove duplicates and empty values
    $topics = array_unique(array_filter($topics));

    return $topics;
}

/**
 * Main function to determine if a question should be logged as a gap question
 *
 * @param string $question
 * @param float $faq_confidence
 * @param array $context Additional context (session_id, user_id, etc.)
 * @return array ['should_log' => bool, 'reason' => string, 'validation' => array]
 */
function chatbot_should_log_gap_question($question, $faq_confidence, $context = []) {
    // 1. Validate question quality
    $validation = chatbot_validate_gap_question($question, $faq_confidence);

    if (!$validation['is_valid']) {
        return [
            'should_log' => false,
            'reason' => $validation['reason'],
            'validation' => $validation
        ];
    }

    // 2. Check confidence threshold
    // Only log questions with low confidence (couldn't find good FAQ match)
    $confidence_threshold = floatval(get_option('chatbot_gap_confidence_threshold', 0.6));
    if ($faq_confidence >= $confidence_threshold) {
        return [
            'should_log' => false,
            'reason' => 'high_confidence_match',
            'validation' => $validation
        ];
    }

    // 3. Check topic relevance using Vector + AI (Option B)
    // Uses FREE Gemini embeddings + cheap AI verification for borderline cases
    $check_relevance = get_option('chatbot_gap_check_relevance', 'yes') === 'yes';
    if ($check_relevance) {
        $relevance_result = chatbot_check_topic_relevance_smart($question);

        if (!$relevance_result['is_relevant']) {
            return [
                'should_log' => false,
                'reason' => 'off_topic',
                'relevance_score' => $relevance_result['score'],
                'relevance_method' => $relevance_result['method'],
                'relevance_reason' => $relevance_result['reason'],
                'validation' => $validation
            ];
        }

        // Add relevance info to validation
        $validation['relevance'] = $relevance_result;
    }

    // 4. Rate limiting - prevent spam from same session
    if (!empty($context['session_id'])) {
        $rate_limited = chatbot_check_gap_rate_limit($context['session_id']);
        if ($rate_limited) {
            return [
                'should_log' => false,
                'reason' => 'rate_limited',
                'validation' => $validation
            ];
        }
    }

    return [
        'should_log' => true,
        'reason' => 'valid_gap_question',
        'validation' => $validation
    ];
}

/**
 * Check if session has exceeded gap question rate limit
 *
 * @param string $session_id
 * @return bool True if rate limited
 */
function chatbot_check_gap_rate_limit($session_id) {
    $transient_key = 'chatbot_gap_rate_' . md5($session_id);
    $count = get_transient($transient_key);

    if ($count === false) {
        // First question in this window
        set_transient($transient_key, 1, 300); // 5 minute window
        return false;
    }

    $max_per_window = intval(get_option('chatbot_gap_rate_limit', 5));

    if ($count >= $max_per_window) {
        return true; // Rate limited
    }

    // Increment count
    set_transient($transient_key, $count + 1, 300);
    return false;
}
