<?php
/**
 * Kognetiks Chatbot - Gap Question Analysis - Ver 2.4.2
 *
 * This file contains AI-powered analysis of gap questions
 * to identify patterns and suggest FAQ additions.
 *
 * @package chatbot-chatgpt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die();
}

/**
 * Run AI clustering analysis on gap questions
 * This function is called by the weekly cron job
 * Updated Ver 2.4.8: Uses Supabase only
 * Updated Ver 2.5.0: Added batch loop to process ALL questions (scalability fix)
 */
function chatbot_run_gap_analysis($force = false, $single_batch = false) {

    $start_time = microtime(true);
    error_log('[Chatbot Gap Analysis] ========== STARTING ==========');

    // Use Supabase functions for gap analysis
    if (!function_exists('chatbot_supabase_get_gap_questions')) {
        error_log('[Chatbot Gap Analysis] ERROR: Supabase gap functions not available');
        return false;
    }

    // Configuration
    $batch_size = 30;      // Questions per AI call (to avoid token limits)
    $max_batches = $single_batch ? 1 : 10; // Single batch for manual testing, 10 for auto
    $delay_between_batches = 2; // Seconds between API calls (rate limiting)
    $min_questions_to_run = 30; // Minimum questions needed to start analysis (for auto only)

    // Check if we have enough questions (unless forced via manual button)
    if (!$force) {
        $question_count = function_exists('chatbot_supabase_get_gap_questions_count')
            ? chatbot_supabase_get_gap_questions_count(false, false)
            : 0;

        if ($question_count < $min_questions_to_run) {
            error_log("[Chatbot Gap Analysis] Only {$question_count} questions waiting. Need {$min_questions_to_run}+ to run. Skipping.");
            return false;
        }
        error_log("[Chatbot Gap Analysis] {$question_count} questions waiting. Proceeding with analysis.");
    } else {
        $mode = $single_batch ? 'single batch (30 max)' : 'all batches';
        error_log("[Chatbot Gap Analysis] Manual run - mode: {$mode}");
    }

    $total_questions_processed = 0;
    $total_clusters_created = 0;
    $batch_number = 0;

    // Process questions in batches until none remain (or just 1 batch if single_batch mode)
    while ($batch_number < $max_batches) {
        $batch_number++;

        $fetch_start = microtime(true);
        $questions = chatbot_supabase_get_gap_questions($batch_size, false);
        $fetch_time = round(microtime(true) - $fetch_start, 2);

        if (empty($questions)) {
            if ($batch_number === 1) {
                error_log('[Chatbot Gap Analysis] No unresolved gap questions to analyze');
                return false;
            }
            error_log("[Chatbot Gap Analysis] Batch {$batch_number}: No more questions to process");
            break;
        }

        $question_count = count($questions);
        error_log("[Chatbot Gap Analysis] Batch {$batch_number}: Fetched {$question_count} questions in {$fetch_time}s");

        // Group questions by similarity using AI
        $ai_start = microtime(true);
        error_log("[Chatbot Gap Analysis] Batch {$batch_number}: Sending to AI for clustering...");
        $clusters = chatbot_cluster_questions_with_ai($questions);
        $ai_time = round(microtime(true) - $ai_start, 2);
        error_log("[Chatbot Gap Analysis] Batch {$batch_number}: AI clustering completed in {$ai_time}s");

        if (empty($clusters)) {
            error_log("[Chatbot Gap Analysis] Batch {$batch_number}: No clusters generated");
            // Continue to next batch - there might be more questions
            if ($question_count < $batch_size) {
                break; // Last batch was partial, we're done
            }
            sleep($delay_between_batches);
            continue;
        }

        error_log("[Chatbot Gap Analysis] Batch {$batch_number}: AI generated " . count($clusters) . " clusters");

        // Save clusters to Supabase
        foreach ($clusters as $cluster) {
            $cluster_id = chatbot_save_gap_cluster($cluster);

            // Mark questions as clustered in Supabase
            if ($cluster_id && !empty($cluster['question_ids'])) {
                chatbot_supabase_mark_questions_clustered($cluster['question_ids'], $cluster_id);
            }

            $total_clusters_created++;
        }

        $total_questions_processed += $question_count;

        // If we got fewer than batch_size, we've processed everything
        if ($question_count < $batch_size) {
            error_log("[Chatbot Gap Analysis] Batch {$batch_number}: Last batch (partial), finishing");
            break;
        }

        // Rate limiting between batches
        if ($batch_number < $max_batches) {
            error_log("[Chatbot Gap Analysis] Waiting {$delay_between_batches}s before next batch...");
            sleep($delay_between_batches);
        }
    }

    $total_time = round(microtime(true) - $start_time, 2);
    error_log("[Chatbot Gap Analysis] ========== FINISHED in {$total_time}s ==========");
    error_log("[Chatbot Gap Analysis] Summary: {$batch_number} batches, {$total_questions_processed} questions -> {$total_clusters_created} clusters");

    return $total_clusters_created > 0;
}

/**
 * Save a gap cluster to Supabase (create or update)
 * Ver 2.5.0: Extracted for cleaner batch processing
 */
function chatbot_save_gap_cluster($cluster) {
    // Calculate priority score based on question count and recency
    $priority = chatbot_calculate_cluster_priority($cluster);

    // Check if cluster exists by name
    $existing_cluster = chatbot_supabase_get_gap_cluster_by_name($cluster['name']);

    if ($existing_cluster) {
        // Update existing cluster
        chatbot_supabase_update_gap_cluster($existing_cluster['id'], [
            'question_count' => $cluster['count'],
            'sample_questions' => $cluster['sample_questions'],
            'sample_contexts' => $cluster['sample_contexts'] ?? [],
            'suggested_faq' => $cluster['suggested_faq'] ?? [],
            'priority_score' => $priority
        ]);

        return $existing_cluster['id'];
    }

    // Insert new cluster
    $action_type = $cluster['action_type'] ?? 'create';
    $insert_data = [
        'cluster_name' => $cluster['name'],
        'cluster_description' => $cluster['description'] ?? '',
        'question_count' => $cluster['count'],
        'sample_questions' => $cluster['sample_questions'],
        'sample_contexts' => $cluster['sample_contexts'] ?? [],
        'action_type' => $action_type,
        'priority_score' => $priority,
        'status' => 'new'
    ];

    // Add action-specific fields
    if ($action_type === 'improve') {
        $existing_faq_id = $cluster['existing_faq_id'] ?? '';
        $faq_details = chatbot_get_faq_by_id($existing_faq_id);

        $insert_data['existing_faq_id'] = $existing_faq_id;
        $insert_data['suggested_answer'] = $cluster['suggested_answer'] ?? '';
        $insert_data['suggested_faq'] = $faq_details;
    } else {
        $insert_data['suggested_faq'] = $cluster['suggested_faq'] ?? [];
        $insert_data['existing_faq_id'] = '';
        $insert_data['suggested_answer'] = '';
    }

    $result = chatbot_supabase_create_gap_cluster($insert_data);
    return $result ? $result['id'] ?? $result : false;
}

/**
 * Analyze recent conversations with AI
 * Updated Ver 2.4.8: Uses Supabase only
 */
function chatbot_analyze_recent_conversations($conversations) {

    // For each conversation, get response and calculate confidence
    $analyzed = [];
    foreach ($conversations as $conv) {
        $question = $conv['message_text'];

        // Get chatbot response from Supabase (next message in same session)
        $session_convs = chatbot_supabase_get_conversations($conv['session_id'] ?? '', 100);
        $response_text = 'No response';

        // Find the chatbot response after this visitor message
        $found_visitor = false;
        foreach ($session_convs as $sc) {
            if ($found_visitor && isset($sc['user_type']) && $sc['user_type'] === 'Chatbot') {
                $response_text = $sc['message_text'] ?? 'No response';
                break;
            }
            if (isset($sc['id']) && $sc['id'] == ($conv['id'] ?? '')) {
                $found_visitor = true;
            }
        }

        // Calculate FAQ confidence
        $faq_result = chatbot_find_best_faq_match($question);

        $analyzed[] = [
            'id' => $conv['id'] ?? '',
            'question' => $question,
            'answer' => $response_text,
            'confidence' => $faq_result['confidence'],
            'matched_faq_id' => $faq_result['faq_id'] ?? null,
            'time' => $conv['interaction_time'] ?? ''
        ];
    }

    // Send to AI for suggestions
    $ai_suggestions = chatbot_get_ai_suggestions_from_conversations($analyzed);

    return [
        'conversations' => $analyzed,
        'suggestions' => $ai_suggestions
    ];
}

/**
 * Get AI suggestions based on analyzed conversations
 */
function chatbot_get_ai_suggestions_from_conversations($conversations) {
    $api_key_encrypted = get_option('chatbot_gemini_api_key', '');

    if (empty($api_key_encrypted)) {
        error_log('[Chatbot] Gemini API key not set. Please add it in Settings > API/Model > Gemini');
        return [];
    }

    // Decrypt the API key
    $api_key = chatbot_chatgpt_decrypt_api_key($api_key_encrypted, 'chatbot_gemini_api_key');

    error_log('🔑 Gemini API key check:');
    error_log('🔑 Encrypted key length: ' . strlen($api_key_encrypted));
    error_log('🔑 Decrypted key length: ' . strlen($api_key));
    error_log('🔑 Decrypted key preview: ' . substr($api_key, 0, 10) . '...');

    if (empty($api_key)) {
        error_log('[Chatbot] Failed to decrypt Gemini API key');
        return [];
    }

    // Load existing FAQs (lightweight - ID, question, keywords only)
    $existing_faqs = chatbot_load_existing_faqs();
    $faq_summary = "Existing FAQs (" . count($existing_faqs) . " total):\n";
    foreach ($existing_faqs as $faq) {
        $faq_summary .= $faq['id'] . ': ' . $faq['question'] . ' [' . substr($faq['keywords'] ?? '', 0, 50) . "]\n";
    }

    // Format conversations for AI
    $conv_text = '';
    foreach ($conversations as $idx => $conv) {
        $conf_percent = round($conv['confidence'] * 100);
        $conv_text .= ($idx + 1) . ". Question: " . $conv['question'] . "\n";
        $conv_text .= "   Answer Given: " . substr($conv['answer'], 0, 200) . "...\n";
        $conv_text .= "   Confidence Score: {$conf_percent}%\n";
        if (!empty($conv['matched_faq_id'])) {
            $conv_text .= "   Matched FAQ: " . $conv['matched_faq_id'] . "\n";
        }
        $conv_text .= "\n";
    }

    $prompt = "You are analyzing recent chatbot conversations to improve the FAQ knowledge base.

EXISTING FAQ DATABASE:
$faq_summary

RECENT CONVERSATIONS:
$conv_text

For each conversation, analyze:
1. If confidence < 70%: Determine if you should IMPROVE an existing FAQ or CREATE a new one
2. If confidence >= 70%: Suggest if keywords could still be improved

Respond with a JSON array of suggestions:
[
  {
    \"conversation_number\": 1,
    \"action_type\": \"improve\" or \"create\",
    \"existing_faq_id\": \"cc002\" (only if improve),
    \"suggested_faq\": {
      \"question\": \"...\",
      \"answer\": \"...\",
      \"keywords\": \"...\"
    } (only if create),
    \"reasoning\": \"Why this suggestion will help\"
  }
]

Only suggest improvements for conversations where the FAQ system could perform better.";

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

    // Use direct cURL for better timeout control (wp_remote_post has 30s low-speed limit)
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 8192
            ]
        ]),
        CURLOPT_TIMEOUT => 120,        // Total timeout: 2 minutes
        CURLOPT_CONNECTTIMEOUT => 30,  // Connection timeout: 30s
        CURLOPT_LOW_SPEED_LIMIT => 100, // Allow slow responses (100 bytes/sec minimum)
        CURLOPT_LOW_SPEED_TIME => 60,  // For up to 60 seconds
    ]);

    $response_body = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log('[Chatbot] Gemini API error: ' . $curl_error);
        return [];
    }

    $body = json_decode($response_body, true);
    if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
        error_log('[Chatbot] Unexpected Gemini API response');
        error_log('[Chatbot] Full API response: ' . print_r($body, true));
        return [];
    }

    $ai_response = $body['candidates'][0]['content']['parts'][0]['text'];
    $ai_response = preg_replace('/```json\n?/', '', $ai_response);
    $ai_response = preg_replace('/```\n?/', '', $ai_response);
    $ai_response = trim($ai_response);

    $suggestions = json_decode($ai_response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[Chatbot] Failed to parse AI suggestions: ' . json_last_error_msg());
        return [];
    }

    // Convert to cluster format
    $clusters = [];
    foreach ($suggestions as $idx => $sugg) {
        $conv = $conversations[$sugg['conversation_number'] - 1] ?? null;
        if (!$conv) continue;

        $cluster = [
            'cluster_name' => 'Conversation ' . $sugg['conversation_number'] . ': ' . substr($conv['question'], 0, 50),
            'cluster_description' => $sugg['reasoning'] ?? '',
            'question_count' => 1,
            'sample_questions' => [$conv['question']],
            'action_type' => $sugg['action_type'] ?? 'create',
            'priority_score' => (1 - $conv['confidence']) * 100
        ];

        if ($cluster['action_type'] === 'improve') {
            $existing_faq_id = $sugg['existing_faq_id'] ?? '';
            $faq_details = chatbot_get_faq_by_id($existing_faq_id);

            $cluster['existing_faq_id'] = $existing_faq_id;
            $cluster['suggested_faq'] = $faq_details;
        } else {
            $cluster['suggested_faq'] = $sugg['suggested_faq'] ?? [];
        }

        $clusters[] = $cluster;
    }

    return $clusters;
}

/**
 * Find best FAQ match for a question
 */
function chatbot_find_best_faq_match($question) {
    $faqs = chatbot_load_existing_faqs();
    $best_match = ['confidence' => 0, 'faq_id' => null, 'answer' => ''];

    $question_lower = strtolower($question);
    $question_words = array_filter(explode(' ', $question_lower));

    foreach ($faqs as $faq) {
        $keywords = strtolower($faq['keywords'] ?? '');
        $faq_question = strtolower($faq['question'] ?? '');

        $keyword_list = array_filter(explode(' ', str_replace(',', ' ', $keywords)));

        $matches = 0;
        foreach ($question_words as $word) {
            if (strlen($word) < 3) continue;

            if (strpos($faq_question, $word) !== false) {
                $matches += 2;
            }

            foreach ($keyword_list as $keyword) {
                if (strlen($keyword) < 3) continue;
                if (strpos($word, $keyword) !== false || strpos($keyword, $word) !== false) {
                    $matches++;
                }
            }
        }

        $confidence = min(1.0, $matches / max(3, count($question_words)));

        if ($confidence > $best_match['confidence']) {
            $best_match = [
                'confidence' => $confidence,
                'faq_id' => $faq['id'] ?? null,
                'answer' => $faq['answer'] ?? ''
            ];
        }
    }

    return $best_match;
}

/**
 * Get FAQ details by ID from JSON file
 */
function chatbot_get_faq_by_id($faq_id) {
    $faqs = chatbot_load_existing_faqs();

    foreach ($faqs as $faq) {
        if (isset($faq['id']) && $faq['id'] === $faq_id) {
            return [
                'id' => $faq['id'],
                'question' => $faq['question'] ?? '',
                'answer' => $faq['answer'] ?? '',
                'keywords' => $faq['keywords'] ?? ''
            ];
        }
    }

    return [
        'id' => $faq_id,
        'question' => 'FAQ not found',
        'answer' => '',
        'keywords' => ''
    ];
}

/**
 * Load existing FAQ database from Supabase
 */
function chatbot_load_existing_faqs() {
    if (function_exists('chatbot_faq_load')) {
        return chatbot_faq_load();
    }

    error_log('[Chatbot Gap Analysis] chatbot_faq_load() not available');
    return [];
}

/**
 * Format FAQs for AI context (concise format)
 * Includes category information for new FAQ suggestions
 */
function chatbot_format_faqs_for_ai($faqs) {
    if (empty($faqs)) {
        return "No existing FAQs found.\n\nExisting Categories: None";
    }

    // Collect unique categories
    $categories = [];
    $formatted = '';

    foreach ($faqs as $faq) {
        $id = $faq['id'] ?? 'unknown';
        $question = $faq['question'] ?? '';
        $category = $faq['category'] ?? '';
        $answer_preview = substr($faq['answer'] ?? '', 0, 100);

        $formatted .= "ID: $id | Category: $category | Q: $question | A: $answer_preview...\n";

        // Collect unique categories
        if (!empty($category) && !in_array($category, $categories)) {
            $categories[] = $category;
        }
    }

    // Add category list at the end
    $category_list = empty($categories) ? 'None' : implode(', ', $categories);
    $formatted .= "\nExisting Categories: $category_list";

    return $formatted;
}

/**
 * Use Gemini AI to cluster similar questions and generate FAQ suggestions
 */
function chatbot_cluster_questions_with_ai($questions) {
    // Get Gemini API key
    $api_key_encrypted = get_option('chatbot_gemini_api_key', '');

    if (empty($api_key_encrypted)) {
        error_log('[Chatbot] Gemini API key not set. Cannot run gap analysis.');
        return [];
    }

    // Decrypt the API key
    $api_key = chatbot_chatgpt_decrypt_api_key($api_key_encrypted, 'chatbot_gemini_api_key');

    if (empty($api_key)) {
        error_log('[Chatbot] Failed to decrypt Gemini API key.');
        return [];
    }

    // Load existing FAQ database
    $existing_faqs = chatbot_load_existing_faqs();
    $faqs_context = chatbot_format_faqs_for_ai($existing_faqs);

    // Prepare questions list for AI
    $questions_text = '';
    foreach ($questions as $idx => $q) {
        $questions_text .= ($idx + 1) . ". " . $q['question_text'] . "\n";
    }

    // Build AI prompt
    $prompt = "You are analyzing customer questions that were not answered well by an FAQ system (using vector/semantic search). Your job is to:

1. Review the EXISTING FAQ database
2. Group similar questions into clusters (themes)
3. For each cluster, decide:
   - IMPROVE existing FAQ: If an FAQ exists but the answer could be better for these questions
   - CREATE new FAQ: If no relevant FAQ exists OR if modifying an existing FAQ would confuse other unrelated questions

IMPORTANT DECISION RULE:
- Only choose 'improve' if the suggested new answer would STILL correctly answer the original question AND all other questions that currently match that FAQ
- If the change would make the FAQ worse for other questions, choose 'create' instead
- Vector search uses semantic similarity, so keyword changes don't help - only answer quality matters

EXISTING FAQ DATABASE:
$faqs_context

CUSTOMER QUESTIONS (not answered well):
$questions_text

Respond with a JSON array. Each cluster must have:
- name: Short cluster name
- description: What customers are asking
- question_numbers: Array of question numbers [1, 5, 8]
- action_type: \"improve\" or \"create\"

If action_type is \"improve\":
- existing_faq_id: The FAQ ID to improve
- suggested_answer: A GENUINELY IMPROVED answer that is DIFFERENT and BETTER than the current answer. The new answer must:
  * Address the specific gaps that caused customers to ask these questions
  * Add more detail, clarity, or information that was missing
  * Be more helpful and comprehensive
  * NEVER just copy the existing answer - always enhance it meaningfully
  * If you cannot improve the answer, use 'create' instead to make a new FAQ

If action_type is \"create\":
- suggested_faq: Object with 'question', 'answer', and 'category'
- For category: Check existing FAQ categories and use one that fits. Only create a new category if none of the existing ones are appropriate.

Rules:
- Only create clusters with 2 or more similar questions. Single questions that don't match others should be ignored.
- Prefer 'create' if the existing answer is already good but just doesn't cover this specific topic
- CRITICAL: For 'improve' action, the suggested_answer MUST be different from the current answer. If you cannot think of a meaningful improvement, choose 'create' instead.
- Respond ONLY with valid JSON, no markdown

Example:
[
  {
    \"name\": \"Store Hours\",
    \"description\": \"Questions about when we close\",
    \"question_numbers\": [1, 3],
    \"action_type\": \"improve\",
    \"existing_faq_id\": \"cc002\",
    \"suggested_answer\": \"We are open Monday-Saturday 9am-7pm and Sunday 10am-5pm. We close at 7pm on weekdays and 5pm on Sundays. Holiday hours may vary - please call ahead on major holidays.\"
  },
  {
    \"name\": \"Router Compatibility\",
    \"description\": \"Using own equipment\",
    \"question_numbers\": [2, 5],
    \"action_type\": \"create\",
    \"suggested_faq\": {
      \"question\": \"Can I use my own router?\",
      \"answer\": \"Yes, you can use your own router. We support most standard routers and modems.\",
      \"category\": \"Equipment\"
    }
  }
]";

    // Call Gemini API with direct cURL for better timeout control
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

    $body = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 16384, // Increased to handle detailed cluster responses
            'topP' => 0.95,
            'topK' => 40
        ]
    ]);

    // Use direct cURL for better timeout control (wp_remote_post has 30s low-speed limit)
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 120,        // Total timeout: 2 minutes
        CURLOPT_CONNECTTIMEOUT => 30,  // Connection timeout: 30s
        CURLOPT_LOW_SPEED_LIMIT => 100, // Allow slow responses (100 bytes/sec minimum)
        CURLOPT_LOW_SPEED_TIME => 60,  // For up to 60 seconds
    ]);

    $curl_response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log('[Chatbot] Gemini API error: ' . $curl_error);
        return [];
    }

    $response_body = json_decode($curl_response, true);

    // Check for finish reason issues
    $finish_reason = $response_body['candidates'][0]['finishReason'] ?? 'UNKNOWN';
    if ($finish_reason === 'MAX_TOKENS') {
        error_log('[Chatbot Gap Analysis] ERROR: Response cut off - MAX_TOKENS reached');
        error_log('[Chatbot Gap Analysis] Try reducing the number of questions or increasing maxOutputTokens');
    }

    if (!isset($response_body['candidates'][0]['content']['parts'][0]['text'])) {
        error_log('[Chatbot Gap Analysis] ERROR: No text in API response');
        error_log('[Chatbot Gap Analysis] Finish reason: ' . $finish_reason);
        error_log('[Chatbot Gap Analysis] Full response: ' . print_r($response_body, true));
        return [];
    }

    $ai_response = $response_body['candidates'][0]['content']['parts'][0]['text'];

    // Parse JSON response (strip markdown code blocks if present)
    $ai_response = preg_replace('/```json\n?/', '', $ai_response);
    $ai_response = preg_replace('/```\n?/', '', $ai_response);
    $ai_response = trim($ai_response);

    $clusters_data = json_decode($ai_response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[Chatbot] Failed to parse AI response as JSON: ' . json_last_error_msg());
        error_log('[Chatbot] AI Response: ' . $ai_response);
        return [];
    }

    // Convert to internal format
    $clusters = [];
    foreach ($clusters_data as $cluster_data) {
        $question_ids = [];
        $sample_questions = [];
        $sample_contexts = []; // Ver 2.5.0: Track conversation context for follow-up questions

        // Map question numbers back to question IDs
        foreach ($cluster_data['question_numbers'] as $num) {
            $idx = $num - 1;
            if (isset($questions[$idx])) {
                $question_ids[] = $questions[$idx]['id'];
                $sample_questions[] = $questions[$idx]['question_text'];
                // Include conversation context if available (for follow-up questions)
                $sample_contexts[] = $questions[$idx]['conversation_context'] ?? null;
            }
        }

        if (count($question_ids) >= 2) {
            $cluster = [
                'name' => $cluster_data['name'],
                'description' => $cluster_data['description'],
                'count' => count($question_ids),
                'question_ids' => $question_ids,
                'sample_questions' => array_slice($sample_questions, 0, 5), // Keep up to 5 samples
                'sample_contexts' => array_slice($sample_contexts, 0, 5), // Ver 2.5.0: Keep matching contexts
                'action_type' => $cluster_data['action_type'] ?? 'create'
            ];

            // Add action-specific data
            if ($cluster['action_type'] === 'improve') {
                $cluster['existing_faq_id'] = $cluster_data['existing_faq_id'] ?? '';
                $cluster['suggested_answer'] = $cluster_data['suggested_answer'] ?? '';
            } else {
                $cluster['suggested_faq'] = $cluster_data['suggested_faq'] ?? [
                    'question' => '',
                    'answer' => ''
                ];
            }

            $clusters[] = $cluster;
        }
    }

    return $clusters;
}

/**
 * Calculate priority score for a cluster
 * Higher score = more important to address
 */
function chatbot_calculate_cluster_priority($cluster) {
    $question_count = intval($cluster['count']);

    // Base score on question frequency
    $score = $question_count * 10;

    // Boost for high-frequency clusters
    if ($question_count >= 10) {
        $score *= 1.5;
    } else if ($question_count >= 5) {
        $score *= 1.2;
    }

    return round($score, 2);
}

/**
 * Get gap analysis dashboard data
 * Updated Ver 2.4.8: Uses Supabase only
 * Updated Ver 2.5.0: Optimized to use count queries instead of loading all data (scalability)
 */
function chatbot_get_gap_analysis_data($days = 7) {

    // Use efficient count function instead of loading all data
    // This scales much better when there are 1000+ gap questions
    $unclustered_gaps = 0;
    if (function_exists('chatbot_supabase_get_gap_questions_count')) {
        $unclustered_gaps = chatbot_supabase_get_gap_questions_count(false, false);
    }

    // Get only active clusters (limit 10 for dashboard display)
    $all_clusters = chatbot_supabase_get_gap_clusters(null, 50);
    $active_clusters = array_filter($all_clusters, function($c) {
        return in_array($c['status'] ?? '', ['new', 'reviewed']);
    });
    $active_clusters = array_slice(array_values($active_clusters), 0, 10);

    // For the "top gaps" display, only fetch a small sample (not all 1000)
    // This is just for UI display, not for analysis
    $sample_gaps = chatbot_supabase_get_gap_questions(50, false, false);

    // Group by question text and count
    $question_counts = [];
    foreach ($sample_gaps as $g) {
        $text = $g['question_text'] ?? '';
        if (!isset($question_counts[$text])) {
            $question_counts[$text] = 0;
        }
        $question_counts[$text]++;
    }
    arsort($question_counts);

    $top_gaps = [];
    $i = 0;
    foreach ($question_counts as $text => $count) {
        if ($i >= 10) break;
        $top_gaps[] = ['question_text' => $text, 'count' => $count];
        $i++;
    }

    return [
        'total_gaps' => intval($unclustered_gaps), // Same as unclustered for dashboard
        'unresolved_gaps' => intval($unclustered_gaps),
        'unclustered_gaps' => intval($unclustered_gaps),
        'active_clusters' => $active_clusters,
        'top_individual_gaps' => $top_gaps
    ];
}

/**
 * Mark a cluster as resolved (FAQ created)
 * Updated Ver 2.4.8: Uses Supabase only
 */
function chatbot_resolve_gap_cluster($cluster_id) {

    // Update cluster status in Supabase
    $result = chatbot_supabase_update_gap_cluster_status($cluster_id, 'faq_created');

    if (!$result) {
        return false;
    }

    // Mark all questions in this cluster as resolved
    $all_questions = chatbot_supabase_get_gap_questions(1000, true);
    foreach ($all_questions as $q) {
        if (isset($q['cluster_id']) && intval($q['cluster_id']) === intval($cluster_id)) {
            chatbot_supabase_resolve_gap_question($q['id']);
        }
    }

    return true;
}

/**
 * Dismiss a cluster
 * Updated Ver 2.4.8: Uses Supabase only
 */
function chatbot_dismiss_gap_cluster($cluster_id) {

    return chatbot_supabase_update_gap_cluster_status($cluster_id, 'dismissed');
}

// Hook gap analysis to cron event
add_action('chatbot_gap_analysis_event', 'chatbot_run_gap_analysis');

// AJAX: Resolve cluster
function chatbot_ajax_resolve_cluster() {
    check_ajax_referer('chatbot_gap_analysis', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $cluster_id = intval($_POST['cluster_id'] ?? 0);

    if ($cluster_id <= 0) {
        wp_send_json_error('Invalid cluster ID');
    }

    $result = chatbot_resolve_gap_cluster($cluster_id);

    if ($result) {
        wp_send_json_success(['message' => 'Cluster resolved successfully']);
    } else {
        wp_send_json_error('Failed to resolve cluster');
    }
}
add_action('wp_ajax_chatbot_resolve_cluster', 'chatbot_ajax_resolve_cluster');

// AJAX: Dismiss cluster
function chatbot_ajax_dismiss_cluster() {
    check_ajax_referer('chatbot_gap_analysis', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $cluster_id = intval($_POST['cluster_id'] ?? 0);

    if ($cluster_id <= 0) {
        wp_send_json_error('Invalid cluster ID');
    }

    $result = chatbot_dismiss_gap_cluster($cluster_id);

    if ($result) {
        wp_send_json_success(['message' => 'Cluster dismissed successfully']);
    } else {
        wp_send_json_error('Failed to dismiss cluster');
    }
}
add_action('wp_ajax_chatbot_dismiss_cluster', 'chatbot_ajax_dismiss_cluster');

// AJAX: Run gap analysis manually
function chatbot_ajax_run_gap_analysis_manual() {
    check_ajax_referer('chatbot_gap_analysis', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    // Manual button = force run, single batch (30 questions max for testing)
    $result = chatbot_run_gap_analysis(true, true);

    if ($result) {
        // Count how many clusters were created/updated from Supabase
        $summary = chatbot_supabase_get_gap_clusters_summary();
        $cluster_count = ($summary['new'] ?? 0) + ($summary['reviewed'] ?? 0);

        wp_send_json_success([
            'message' => 'Gap analysis completed successfully',
            'clusters' => intval($cluster_count)
        ]);
    } else {
        wp_send_json_error('No gap questions to analyze or analysis failed');
    }
}
add_action('wp_ajax_chatbot_run_gap_analysis_manual', 'chatbot_ajax_run_gap_analysis_manual');

// AJAX: Apply improved answer to existing FAQ
function chatbot_ajax_apply_improved_faq() {
    check_ajax_referer('chatbot_gap_analysis', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $cluster_id = intval($_POST['cluster_id'] ?? 0);
    $faq_id = sanitize_text_field($_POST['faq_id'] ?? '');
    $new_answer = sanitize_textarea_field($_POST['new_answer'] ?? '');

    if (empty($faq_id) || empty($new_answer)) {
        wp_send_json_error('Missing FAQ ID or answer');
    }

    // Get existing FAQ to preserve question and category
    $existing_faq = null;
    if (function_exists('chatbot_supabase_request')) {
        $result = chatbot_supabase_request('chatbot_faqs', 'GET', null, ['faq_id' => 'eq.' . $faq_id]);
        if ($result['success'] && !empty($result['data'])) {
            $existing_faq = $result['data'][0];
        }
    }

    if (!$existing_faq) {
        wp_send_json_error('FAQ not found: ' . $faq_id);
    }

    // Use the proper chatbot_faq_update function which handles embeddings
    if (function_exists('chatbot_faq_update')) {
        $result = chatbot_faq_update(
            $faq_id,
            $existing_faq['question'],
            $new_answer,
            $existing_faq['category'] ?? ''
        );

        if (!$result['success']) {
            wp_send_json_error('Failed to update FAQ: ' . ($result['message'] ?? 'Unknown error'));
        }

        // Mark cluster as resolved
        if ($cluster_id > 0) {
            chatbot_resolve_gap_cluster($cluster_id);
        }

        wp_send_json_success([
            'message' => 'FAQ updated successfully with new embedding',
            'faq_id' => $faq_id
        ]);
    } else {
        wp_send_json_error('FAQ update function not available');
    }
}
add_action('wp_ajax_chatbot_apply_improved_faq', 'chatbot_ajax_apply_improved_faq');

// AJAX: Add new FAQ to knowledge base
function chatbot_ajax_add_new_faq() {
    check_ajax_referer('chatbot_gap_analysis', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $cluster_id = intval($_POST['cluster_id'] ?? 0);
    $category = sanitize_text_field($_POST['category'] ?? '');
    $question = sanitize_text_field($_POST['question'] ?? '');
    $answer = sanitize_textarea_field($_POST['answer'] ?? '');

    if (empty($question) || empty($answer) || empty($category)) {
        wp_send_json_error('Missing required fields (category, question, answer)');
    }

    // Use the proper chatbot_faq_add function which handles embeddings and duplicate detection
    if (function_exists('chatbot_faq_add')) {
        // Force add = true to skip duplicate check (admin reviewed and approved)
        $result = chatbot_faq_add($question, $answer, $category, true);

        if (!$result['success']) {
            wp_send_json_error('Failed to add FAQ: ' . ($result['message'] ?? 'Unknown error'));
        }

        $new_id = $result['faq_id'] ?? 'unknown';

        // Mark cluster as resolved
        if ($cluster_id > 0) {
            chatbot_resolve_gap_cluster($cluster_id);
        }

        wp_send_json_success([
            'message' => 'FAQ added successfully with embeddings',
            'faq_id' => $new_id
        ]);
    } else {
        wp_send_json_error('FAQ add function not available');
    }
}
add_action('wp_ajax_chatbot_add_new_faq', 'chatbot_ajax_add_new_faq');

// AJAX: Generate mock data for testing
// Updated Ver 2.4.8: Uses Supabase only
function chatbot_ajax_generate_mock_gap_data() {
    check_ajax_referer('chatbot_gap_analysis', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    // Create mock gap questions
    $mock_questions = [
        ["Can I use my own router?", 0.25], ["Do you provide equipment?", 0.30],
        ["What routers work with your service?", 0.22], ["Can I bring my own modem?", 0.28],
        ["Do you offer senior discounts?", 0.15], ["Any discounts for students?", 0.18],
        ["How long does installation take?", 0.38], ["Installation time?", 0.35],
        ["Do I need a contract?", 0.28], ["Can I cancel anytime?", 0.32],
        ["Internet and phone bundle?", 0.42], ["Bundle deals available?", 0.45],
    ];

    $inserted = 0;
    foreach ($mock_questions as $index => $q) {
        $days_ago = rand(0, 6);
        $result = chatbot_supabase_log_gap_question(
            $q[0],
            'mock_' . $index,
            rand(1, 5),
            rand(1, 3),
            $q[1],
            null
        );
        if ($result) $inserted++;
    }

    // Create mock clusters
    $mock_clusters = [
        [
            'cluster_name' => 'Router & Equipment',
            'cluster_description' => 'Questions about using own equipment',
            'question_count' => 4,
            'sample_questions' => ["Can I use my own router?", "Do you provide equipment?"],
            'suggested_faq' => ['question' => 'Can I use my own router?', 'answer' => 'Yes! You can use your own equipment. We recommend DOCSIS 3.1 modems for best speeds.'],
            'priority_score' => 80,
            'status' => 'new'
        ],
        [
            'cluster_name' => 'Senior Discounts',
            'cluster_description' => 'Questions about senior pricing',
            'question_count' => 2,
            'sample_questions' => ["Do you offer senior discounts?", "Any discounts for students?"],
            'suggested_faq' => ['question' => 'Do you offer senior discounts?', 'answer' => 'Yes! We offer up to 15% off for seniors 65+ and students with valid ID.'],
            'priority_score' => 40,
            'status' => 'new'
        ]
    ];

    $cluster_inserted = 0;
    foreach ($mock_clusters as $c) {
        $result = chatbot_supabase_create_gap_cluster($c);
        if ($result) $cluster_inserted++;
    }

    wp_send_json_success([
        'message' => 'Mock data generated',
        'questions' => $inserted,
        'clusters' => $cluster_inserted
    ]);
}
add_action('wp_ajax_chatbot_generate_mock_gap_data', 'chatbot_ajax_generate_mock_gap_data');

// AJAX: Save analysis frequency - Ver 2.4.2
function chatbot_ajax_save_analysis_frequency() {
    check_ajax_referer('chatbot_gap_analysis', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : '';

    if (!in_array($frequency, ['weekly', 'monthly', 'yearly'])) {
        wp_send_json_error('Invalid frequency value');
    }

    update_option('chatbot_gap_analysis_frequency', $frequency);

    error_log("💾 Gap analysis frequency updated to: $frequency");

    wp_send_json_success([
        'message' => 'Frequency updated',
        'frequency' => $frequency
    ]);
}
add_action('wp_ajax_chatbot_save_analysis_frequency', 'chatbot_ajax_save_analysis_frequency');

// AJAX: Toggle auto-analysis on/off
function chatbot_ajax_toggle_auto_analysis() {
    check_ajax_referer('chatbot_gap_analysis', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $enabled = isset($_POST['enabled']) ? sanitize_text_field($_POST['enabled']) : 'off';

    if (!in_array($enabled, ['on', 'off'])) {
        wp_send_json_error('Invalid value');
    }

    update_option('chatbot_gap_auto_analysis_enabled', $enabled);

    error_log("[Chatbot Gap Analysis] Auto-analysis " . ($enabled === 'on' ? 'ENABLED' : 'DISABLED'));

    wp_send_json_success([
        'message' => 'Setting saved',
        'enabled' => $enabled
    ]);
}
add_action('wp_ajax_chatbot_toggle_auto_analysis', 'chatbot_ajax_toggle_auto_analysis');

// AJAX: Analyze Last 4 Conversations - Ver 2.4.2
function chatbot_ajax_analyze_last_10_gaps() {
    check_ajax_referer('chatbot_gap_analysis', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    // Use Supabase for conversations
    if (function_exists('chatbot_supabase_get_recent_conversations')) {
        $all_conversations = chatbot_supabase_get_recent_conversations(30, 1000);
        // Filter for visitor messages only
        $conversations = array();
        foreach ($all_conversations as $conv) {
            if (isset($conv['user_type']) && $conv['user_type'] === 'Visitor') {
                $conversations[] = $conv;
            }
        }
        // Get last 4
        $conversations = array_slice($conversations, 0, 4);
        error_log("🔍 Found " . count($conversations) . " conversations (Supabase)");
    } else {
        wp_send_json_error('Supabase conversation functions not available');
        return;
    }

    if (empty($conversations)) {
        wp_send_json_error('No recent conversations found. Conversation logging may not be enabled. Please check Analytics > Conversation Logging settings.');
    }

    error_log("📊 Analyzing last " . count($conversations) . " conversations with AI");

    // Analyze each conversation
    $analyzed_data = chatbot_analyze_recent_conversations($conversations);

    if (empty($analyzed_data['suggestions'])) {
        wp_send_json_error('AI analysis returned no suggestions');
    }

    $clusters = $analyzed_data['suggestions'];

    // Save clusters to Supabase
    $saved_count = 0;

    foreach ($clusters as $cluster) {
        $cluster_id = chatbot_supabase_create_gap_cluster([
            'cluster_name' => $cluster['cluster_name'],
            'cluster_description' => $cluster['cluster_description'],
            'question_count' => $cluster['question_count'],
            'sample_questions' => $cluster['sample_questions'],
            'suggested_faq' => $cluster['suggested_faq'],
            'priority_score' => $cluster['priority_score'],
            'status' => 'new'
        ]);

        if ($cluster_id) {
            $saved_count++;

            // Mark questions as clustered
            if (!empty($cluster['question_ids'])) {
                chatbot_supabase_mark_questions_clustered($cluster['question_ids'], $cluster_id);
            }
        }
    }

    error_log("✓ Saved $saved_count clusters from AI analysis");

    // Track API usage
    $api_usage = get_option('chatbot_gap_analysis_api_usage', array(
        'total_calls' => 0,
        'this_week' => 0,
        'this_month' => 0,
        'last_reset_week' => date('W'),
        'last_reset_month' => date('m'),
        'last_analysis_date' => null
    ));

    $api_usage['total_calls']++;
    $api_usage['this_week']++;
    $api_usage['this_month']++;
    $api_usage['last_analysis_date'] = current_time('mysql');

    update_option('chatbot_gap_analysis_api_usage', $api_usage);

    error_log("📊 API Usage tracked: Total={$api_usage['total_calls']}, Week={$api_usage['this_week']}, Month={$api_usage['this_month']}");

    wp_send_json_success([
        'message' => 'Successfully analyzed ' . count($analyzed_data['conversations']) . ' conversations',
        'conversations' => $analyzed_data['conversations'],
        'suggestions' => $saved_count
    ]);
}
add_action('wp_ajax_chatbot_analyze_last_10_gaps', 'chatbot_ajax_analyze_last_10_gaps');
