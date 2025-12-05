<?php
/**
 * Kognetiks Chatbot - Google Gemini API - Ver 2.3.7
 *
 * This file contains the code accessing Google's Gemini API.
 *
 *
 * @package chatbot-chatgpt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die();
}

// Call the Gemini API
function chatbot_call_gemini_api($api_key, $message, $user_id = null, $page_id = null, $session_id = null, $assistant_id = null, $client_message_id = null) {

    error_log('@@@ CHATBOT: chatbot_call_gemini_api() CALLED with message: ' . $message . ' @@@');
    error_log('CHATBOT: Parameters - user_id: ' . ($user_id ?? 'NULL') . ', page_id: ' . ($page_id ?? 'NULL') . ', session_id: ' . ($session_id ?? 'NULL'));

    global $session_id;
    global $user_id;
    global $page_id;
    global $thread_id;
    global $assistant_id;
    global $learningMessages;
    global $kchat_settings;
    global $additional_instructions;
    global $model;
    global $voice;

    global $errorResponses;

    // Use client_message_id if provided, otherwise generate a unique message UUID for idempotency
    $message_uuid = $client_message_id ? $client_message_id : wp_generate_uuid4();

    // Lock the conversation BEFORE thread resolution to prevent empty-thread vs real-thread lock split
    $conv_lock = 'chatgpt_conv_lock_' . wp_hash($assistant_id . '|' . $user_id . '|' . $page_id . '|' . $session_id);
    $lock_timeout = 60; // 60 seconds timeout

    // Check for duplicate message UUID in conversation log
    $duplicate_key = 'chatgpt_message_uuid_' . $message_uuid;
    if (get_transient($duplicate_key)) {
        return "Error: Duplicate request detected. Please try again.";
    }

    // Lock check removed - main send function handles locking
    set_transient($duplicate_key, true, 300); // 5 minutes to prevent duplicates

    // PRE-PROCESSING RULES - Guaranteed escalations (no AI needed, $0 cost) - Ver 2.3.7
    $message_lower = strtolower($message);

    // GREETING DETECTION - Mark for fresh start (no old conversation context) - Ver 2.4.9
    // When user says hi/hello/hey, they likely want to start a new conversation
    $cleaned_for_greeting = trim(preg_replace('/\s+/', ' ', $message_lower));
    $is_new_conversation_greeting = preg_match('/^(hi|hello|hey|good\s*(morning|afternoon|evening)|greetings)\s*[!.,]?\s*$/i', $cleaned_for_greeting);
    if ($is_new_conversation_greeting) {
        error_log('@@@ CHATBOT: Greeting detected - will skip conversation history for fresh start @@@');
        // Clear transient history so it starts fresh
        delete_transient('chatbot_chatgpt_context_history');
    }

    // OFF-TOPIC FILTER - Block questions unrelated to telecommunications - Ver 2.4.4
    $off_topic_keywords = [
        // Cryptocurrency
        'bitcoin', 'crypto', 'cryptocurrency', 'ethereum', 'blockchain', 'nft', 'dogecoin',
        // Finance (non-telecom)
        'stock', 'stocks', 'forex', 'trading', 'investment', 'invest in',
        // Weather
        'weather', 'forecast', 'temperature', 'rain', 'snow', 'sunny',
        // Sports
        'football', 'basketball', 'baseball', 'soccer', 'nfl', 'nba', 'super bowl', 'world cup',
        // Politics
        'president', 'election', 'vote', 'congress', 'senate', 'political',
        // Religion
        'god', 'jesus', 'allah', 'buddha', 'bible', 'quran', 'church', 'mosque', 'temple',
        // Entertainment
        'movie', 'actor', 'actress', 'celebrity', 'netflix', 'spotify',
        // General knowledge (that's clearly not telecom)
        'recipe', 'cooking', 'restaurant', 'hotel', 'flight', 'vacation',
        // Health
        'doctor', 'hospital', 'medicine', 'sick', 'disease', 'covid',
        // Education (non-telecom)
        'homework', 'essay', 'school assignment', 'university application',
    ];

    foreach ($off_topic_keywords as $keyword) {
        if (strpos($message_lower, $keyword) !== false) {
            error_log('🚫 OFF-TOPIC QUESTION BLOCKED: ' . $message . ' (keyword: ' . $keyword . ')');
            return "I'm here to help with internet, phone, and TV services from Comfort Comm. " .
                   "For questions about other topics, I'd recommend searching online. " .
                   "How can I assist you with your telecommunications needs today? " .
                   "Call us at (347) 519-9999 for personalized help!";
        }
    }

    $escalation_patterns = [
        'billing' => ['billing', 'bill', 'payment', 'pay my', 'invoice', 'charge', 'refund', 'overcharge'],
        'account' => ['account balance', 'my account', 'account number', 'account info', 'account detail'],
        'login' => ['login', 'log in', 'password', 'username', 'forgot password', 'reset password', 'cant access'],
        'cancel' => ['cancel service', 'cancel my', 'terminate service', 'disconnect service'],
    ];

    foreach ($escalation_patterns as $category => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($message_lower, $pattern) !== false) {
                $escalation_response = "For your account's security, I can't access personal billing or account details. " .
                                     "Please call our team at (347) 519-9999 or visit us at 13692 Roosevelt Ave, Flushing NY 11354. " .
                                     "We're here to help!";
                prod_trace('NOTICE', "Pre-processing escalation triggered: $category pattern matched - skipping AI call");
                return [
                    'response' => $escalation_response,
                    'api' => 'pre_processing_escalation',
                    'cost_saved' => true
                ];
            }
        }
    }

    // SMART FAQ SEARCH - Confidence-based routing to minimize AI costs - Ver 2.3.7
    $faq_context = '';
    $skip_ai_call = false;
    $faq_direct_response = '';

    error_log('=== CHATBOT DEBUG: Smart FAQ Search STARTED for message: ' . $message . ' ===');
    prod_trace('NOTICE', 'Smart FAQ Search - Processing message: ' . $message);

    // GENERIC QUESTION DETECTION - Ver 2.4.9
    // These questions are too vague for FAQ matching - let AI handle them naturally
    error_log('@@@ CHATBOT GENERIC DETECTION: Starting check for message: "' . $message . '" @@@');

    $generic_patterns = [
        // Greetings and intros
        '/^(hi|hello|hey|good\s*(morning|afternoon|evening)|greetings)\s*[!.,]?\s*$/i',
        '/^(hi|hello|hey)\s+(there|bot|chatbot|assistant)?\s*[!.,]?\s*$/i',
        // Generic help requests
        '/^(help|help me|i need help|can you help|can you help me)\s*[!?.,]?\s*$/i',
        '/^how can (you|u) help( me)?\s*[!?]?\s*$/i',
        '/^what (can|do) (you|u) do\s*[!?]?\s*$/i',
        '/^what (services|help) (do you|can you) (offer|provide)\s*[!?]?\s*$/i',
        // Conversational follow-ups (too short/vague)
        '/^(why|how|what|when|where|who)\s*[!?]?\s*$/i',
        '/^(why|how come)\s+(that|this|so)\s*[!?]?\s*$/i',
        '/^(tell me more|go on|continue|explain)\s*[!?]?\s*$/i',
        // Single word questions
        '/^(yes|no|ok|okay|sure|thanks|thank you|thx|ty)\s*[!?.,]?\s*$/i',
    ];

    $is_generic_question = false;
    $cleaned_message = trim(preg_replace('/\s+/', ' ', $message_lower));

    error_log('@@@ CHATBOT GENERIC DETECTION: cleaned_message = "' . $cleaned_message . '" @@@');

    foreach ($generic_patterns as $pattern) {
        if (preg_match($pattern, $cleaned_message)) {
            $is_generic_question = true;
            error_log('@@@ CHATBOT GENERIC DETECTION: MATCHED pattern ' . $pattern . ' - SKIPPING FAQ SEARCH @@@');
            prod_trace('NOTICE', 'Generic question detected - using pure AI response');
            break;
        }
    }

    error_log('@@@ CHATBOT GENERIC DETECTION: is_generic_question = ' . ($is_generic_question ? 'TRUE' : 'FALSE') . ' @@@');

    // Also detect very short messages (less than 4 words) that aren't specific
    $word_count = str_word_count($cleaned_message);
    if ($word_count <= 3 && !preg_match('/(spectrum|verizon|optimum|fios|internet|tv|phone|wifi|router|modem|bill|price|cost|speed|mbps|channel)/i', $cleaned_message)) {
        $is_generic_question = true;
        error_log('CHATBOT DEBUG: Short vague message detected - skipping FAQ search: ' . $message);
        prod_trace('NOTICE', 'Short vague message - using pure AI response');
    }

    // Detect contextual follow-up questions that reference previous conversation
    // These should use conversation history, not FAQ search
    $contextual_patterns = [
        '/^why\s+(that|this|them|it|those|him|her|they)\s*[!?]?\s*$/i',
        '/^why\s+\w+\s*[!?]?\s*$/i',  // "why verizon?", "why spectrum?"
        '/^what about\s+/i',
        '/^and\s+(what|how|why|when)/i',
        '/^but\s+(what|how|why|when)/i',
        '/^(so|then)\s+(what|how|why)/i',
    ];

    foreach ($contextual_patterns as $pattern) {
        if (preg_match($pattern, $cleaned_message)) {
            $is_generic_question = true;
            error_log('CHATBOT DEBUG: Contextual follow-up detected - using conversation history: ' . $message);
            prod_trace('NOTICE', 'Contextual follow-up - relying on conversation history');
            break;
        }
    }

    // Use CONTEXT-AWARE vector search for semantic FAQ matching (Ver 2.5.0)
    // This handles follow-up questions like "can you name them?" by enriching with conversation context
    // Skip FAQ search for generic questions
    if (!$is_generic_question) {
        // Prefer context-aware search (handles follow-ups), fallback to regular vector search
        if (function_exists('chatbot_vector_context_aware_search')) {
            error_log('CHATBOT DEBUG: Using CONTEXT-AWARE vector search (Ver 2.5.0)');
            prod_trace('NOTICE', 'Using context-aware vector search for semantic matching');
            $faq_result = chatbot_vector_context_aware_search($message, true, $session_id, $user_id, $page_id);
        } elseif (function_exists('chatbot_vector_faq_search')) {
            error_log('CHATBOT DEBUG: Fallback to regular vector search');
            $faq_result = chatbot_vector_faq_search($message, true, $session_id, $user_id, $page_id);
        } else {
            $faq_result = null;
        }
        error_log('CHATBOT DEBUG: FAQ search result: ' . print_r($faq_result, true));
        prod_trace('NOTICE', 'FAQ search result: ' . print_r($faq_result, true));

        // Log context-aware search details (Ver 2.5.0)
        if (isset($faq_result['is_followup']) && $faq_result['is_followup']) {
            error_log('CHATBOT DEBUG: Follow-up question detected - used_context=' . ($faq_result['used_context'] ? 'YES' : 'NO'));
            prod_trace('NOTICE', 'Follow-up question detected, context enrichment: ' . ($faq_result['used_context'] ? 'applied' : 'not available'));
        }

        if ($faq_result && $faq_result['match'] && !empty($faq_result['match']['answer'])) {
            $confidence = $faq_result['confidence'];
            $score = $faq_result['score'];
            $match_type = $faq_result['match_type'] ?? 'vector';
            $faq_match = $faq_result['match'];

            // Log the FAQ match with confidence
            prod_trace('NOTICE', sprintf(
                'FAQ match found: score=%.2f confidence=%s type=%s question="%s"%s',
                $score, $confidence, $match_type, $message,
                isset($faq_result['used_context']) && $faq_result['used_context'] ? ' (context-enriched)' : ''
            ));

            // TIER 1: Very High Confidence (80%+) - Return FAQ directly, NO AI CALL ($0 cost!)
            if ($confidence === 'very_high') {
                $skip_ai_call = true;
                $faq_direct_response = $faq_match['answer'];
                prod_trace('NOTICE', 'Very high confidence FAQ match - skipping AI call to save cost');
            }
            // TIER 2: High Confidence (60-80%) - Minimal AI processing
            else if ($confidence === 'high') {
                $faq_context = "\n\nUSE THIS FAQ ANSWER: " . $faq_match['answer'] . "\n\n" .
                              "Rephrase it naturally in 1-2 sentences. Be concise.";
            }
            // TIER 3: Medium Confidence (40-60%) - AI with FAQ context
            else if ($confidence === 'medium') {
                $faq_context = "\n\nRELEVANT FAQ:\nQ: " . $faq_match['question'] . "\nA: " . $faq_match['answer'] . "\n\n" .
                              "Use this FAQ as a reference, but ask clarifying questions if the user's intent is unclear.";
            }
            // TIER 4: Very Low Confidence (<50%) - Don't use FAQ, let AI handle naturally
            // These matches are too weak and often wrong - they confuse the AI more than help
            else {
                // Don't add any FAQ context for very low confidence matches
                $faq_context = '';
                error_log('CHATBOT DEBUG: Very low confidence FAQ match (score=' . $score . ') - ignoring FAQ context');
            }
        } else {
            prod_trace('NOTICE', 'No FAQ match found - using full AI processing');
        }
    }

    // If very high confidence FAQ match, return immediately without calling AI
    if ($skip_ai_call && !empty($faq_direct_response)) {
        return [
            'response' => $faq_direct_response,
            'api' => 'faq_direct',
            'cost_saved' => true
        ];
    }

    // Current Page Context - DISABLED for now
    // TODO: Add a setting to enable/disable this feature
    $page_context = '';
    /*
    if (!empty($page_id) && $page_id !== '999999') {
        $current_page = get_post($page_id);
        if ($current_page && $current_page->post_status === 'publish') {
            $page_title = $current_page->post_title;
            $page_url = get_permalink($page_id);

            // Strip HTML tags and shortcodes from page content
            $page_content = wp_strip_all_tags($current_page->post_content);
            $page_content = strip_shortcodes($page_content);

            // Remove extra whitespace
            $page_content = preg_replace('/\s+/', ' ', $page_content);
            $page_content = trim($page_content);

            // Limit to first 800 words to avoid token limits
            $page_content_words = explode(' ', $page_content);
            if (count($page_content_words) > 800) {
                $page_content = implode(' ', array_slice($page_content_words, 0, 800)) . '...';
            }

            // Build page context for Gemini
            if (!empty($page_content)) {
                $page_context = "\n\nCURRENT PAGE CONTEXT:\n" .
                               "The user is currently viewing the page titled: \"$page_title\"\n" .
                               "Page URL: $page_url\n\n" .
                               "Page Content:\n$page_content\n\n" .
                               "When answering questions, you can reference information from this page by saying things like 'On this page...' or 'According to the current page...'. " .
                               "If the user asks what page they're on, tell them they're viewing the \"$page_title\" page.";

                // Log for diagnostics
                prod_trace('NOTICE', 'Page context added for page: ' . $page_title . ' (ID: ' . $page_id . ')');
            }
        }
    }
    */

    // Google Gemini API Documentation
    // https://ai.google.dev/gemini-api/docs

    // Get the saved model from the settings or default to "gemini-1.5-flash"
    $model = esc_attr(get_option('chatbot_gemini_model_choice', 'gemini-1.5-flash'));

    // Build the API URL with API key as query parameter
    $base_url = esc_attr(get_option('chatbot_gemini_base_url', 'https://generativelanguage.googleapis.com/v1beta'));
    $api_url = $base_url . '/models/' . $model . ':generateContent?key=' . $api_key;

    // Max tokens
    $max_tokens = intval(esc_attr(get_option('chatbot_gemini_max_tokens_setting', '2048')));

    // Temperature
    $temperature = floatval(esc_attr(get_option('chatbot_gemini_temperature', '0.7')));

    // Top P
    $top_p = floatval(esc_attr(get_option('chatbot_gemini_top_p', '0.95')));

    // Conversation Context
    $context = esc_attr(get_option('chatbot_gemini_conversation_context', 'You are a versatile, friendly, and helpful assistant designed to support me in a variety of tasks. Respond in plain text without any markdown formatting (no asterisks, underscores, or special characters for bold/italic).'));
    $raw_context = $context;

    // Context History
    $chatgpt_last_response = concatenateHistory('chatbot_chatgpt_context_history');

    // Strip any href links and text from the $chatgpt_last_response
    $chatgpt_last_response = preg_replace('/\[URL:.*?\]/', '', $chatgpt_last_response);

    // Strip any $learningMessages from the $chatgpt_last_response
    if (get_locale() !== "en_US") {
        $localized_learningMessages = get_localized_learningMessages(get_locale(), $learningMessages);
    } else {
        $localized_learningMessages = $learningMessages;
    }
    $chatgpt_last_response = str_replace($localized_learningMessages, '', $chatgpt_last_response);

    // Strip any $errorResponses from the $chatgpt_last_response
    if (get_locale() !== "en_US") {
        $localized_errorResponses = get_localized_errorResponses(get_locale(), $errorResponses);
    } else {
        $localized_errorResponses = $errorResponses;
    }
    $chatgpt_last_response = str_replace($localized_errorResponses, '', $chatgpt_last_response);

    // Knowledge Navigator keyword append for context
    $chatbot_chatgpt_kn_conversation_context = esc_attr(get_option('chatbot_chatgpt_kn_conversation_context', 'Yes'));

    $sys_message = 'We previously have been talking about the following things: ';

    // ENHANCED CONTEXT - Select some context to send with the message
    $use_enhanced_content_search = esc_attr(get_option('chatbot_chatgpt_use_advanced_content_search', 'No'));

    if ($use_enhanced_content_search == 'Yes') {

        $search_results = chatbot_chatgpt_content_search($message);
        If ( !empty ($search_results) ) {
            // Extract relevant content from search results array
            $content_texts = [];
            foreach ($search_results['results'] as $result) {
                if (!empty($result['excerpt'])) {
                    $content_texts[] = $result['excerpt'];
                }
            }
            // Join the content texts and append to context
            if (!empty($content_texts)) {
                $context = ' When answering the prompt, please consider the following information: ' . implode(' ', $content_texts);
            }
        }

    } else {

        // Original Context Instructions - No Enhanced Context
        // Skip conversation history for greetings
        if ($is_new_conversation_greeting) {
            $context = $context . ' ' . $chatbot_chatgpt_kn_conversation_context;
        } else {
            $context = $sys_message . ' ' . $chatgpt_last_response . ' ' . $context . ' ' . $chatbot_chatgpt_kn_conversation_context;
        }

    }

    // Conversation Continuity
    // Skip for greetings - they should start fresh without old context confusing the AI
    $chatbot_chatgpt_conversation_continuation = esc_attr(get_option('chatbot_chatgpt_conversation_continuation', 'On'));

    if ($chatbot_chatgpt_conversation_continuation == 'On' && !$is_new_conversation_greeting) {
        $conversation_history = chatbot_chatgpt_get_converation_history($session_id);
        $context = $conversation_history . ' ' . $context;
    } elseif ($is_new_conversation_greeting) {
        error_log('@@@ CHATBOT: Skipping conversation history for greeting - starting fresh @@@');
    }

    // Check the length of the context and truncate if necessary
    $context_length = intval(strlen($context) / 4); // Assuming 1 token ≈ 4 characters
    $max_context_length = 65536; // Example: 65536 characters ≈ 16384 tokens
    if ($context_length > $max_context_length) {
        // Truncate to the max length
        $truncated_context = substr($context, 0, $max_context_length);
        // Ensure truncation happens at the last complete word
        $truncated_context = preg_replace('/\s+[^\s]*$/', '', $truncated_context);
        // Fallback if regex fails (e.g., no spaces in the string)
        if (empty($truncated_context)) {
            $truncated_context = substr($context, 0, $max_context_length);
        }
        $context = $truncated_context;
    }

    // Build the Gemini API request body
    // Gemini uses a different format than OpenAI

    // Define the header
    $headers = array(
        'Content-Type' => 'application/json'
    );

    // Define the request body for Gemini format
    $body = json_encode(array(
        'contents' => array(
            array(
                'role' => 'user',
                'parts' => array(
                    array(
                        'text' => $context . $page_context . $faq_context . "\n\nUser: " . $message
                    )
                )
            )
        ),
        'generationConfig' => array(
            'temperature' => $temperature,
            'topP' => $top_p,
            'maxOutputTokens' => $max_tokens,
        ),
        'safetySettings' => array(
            array(
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ),
            array(
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ),
            array(
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ),
            array(
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            )
        )
    ));

    $timeout = esc_attr(get_option('chatbot_gemini_timeout_setting', 240));

    // DEBUG: Log the request body to check maxOutputTokens
    error_log('CHATBOT DEBUG: Gemini API request body:');
    error_log($body);
    error_log('CHATBOT DEBUG: max_tokens variable value: ' . $max_tokens);

    // Context History
    addEntry('chatbot_chatgpt_context_history', $message);

    // API Call
    $response = wp_remote_post($api_url, array(
        'headers' => $headers,
        'body' => $body,
        'timeout' => $timeout,
    ));

    // Handle WP Error
    if (is_wp_error($response)) {

        prod_trace( 'ERROR', 'Error: ' . $response->get_error_message());
        return isset($errorResponses['api_error']) ? $errorResponses['api_error'] : 'An API error occurred.';

    }

    // Retrieve and Decode Response
    $response_body = json_decode(wp_remote_retrieve_body($response));

    // DEBUG: Log the full response body to check finishReason
    error_log('CHATBOT DEBUG: Full Gemini API response body:');
    error_log(print_r($response_body, true));

    // Handle API Errors
    if (isset($response_body->error)) {

        // Extract error type and message safely
        $error_code = $response_body->error->code ?? 'Unknown Error Code';
        $error_message = $response_body->error->message ?? 'No additional information.';

        prod_trace( 'ERROR', 'Error: Code: ' . $error_code . ' Message: ' . $error_message);
        return isset($errorResponses['api_error']) ? $errorResponses['api_error'] : 'An error occurred.';

    }

    // Get the user ID and page ID
    if (empty($user_id)) {
        $user_id = get_current_user_id(); // Get current user ID
    }
    if (empty($page_id)) {
        $page_id = get_the_id(); // Get current page ID
        if (empty($page_id)) {
            $page_id = get_the_ID(); // Get the ID of the queried object if $page_id is not set
        }
    }

    // Extract token usage from Gemini response
    $input_tokens = $response_body->usageMetadata->promptTokenCount ?? 0;
    $output_tokens = $response_body->usageMetadata->candidatesTokenCount ?? 0;
    $total_tokens = $response_body->usageMetadata->totalTokenCount ?? 0;

    // Check if the response content is not empty
    if (!empty($response_body->candidates[0]->content->parts[0]->text)) {
        if ($input_tokens > 0) {
            append_message_to_conversation_log($session_id, $user_id, $page_id, 'Prompt Tokens', null, null, null, $input_tokens);
        }

        if ($output_tokens > 0) {
            append_message_to_conversation_log($session_id, $user_id, $page_id, 'Completion Tokens', null, null, null, $output_tokens);
        }

        if ($total_tokens > 0) {
            append_message_to_conversation_log($session_id, $user_id, $page_id, 'Total Tokens', null, null, null, $total_tokens);
        }
    }

    // Access response content properly - Gemini format
    if (isset($response_body->candidates[0]->content->parts[0]->text) && !empty($response_body->candidates[0]->content->parts[0]->text)) {
        $response_text = $response_body->candidates[0]->content->parts[0]->text;

        // DEBUG: Log the full response from Gemini
        error_log('CHATBOT DEBUG: Full Gemini API response text:');
        error_log($response_text);
        error_log('CHATBOT DEBUG: Response length: ' . strlen($response_text) . ' characters');

        addEntry('chatbot_chatgpt_context_history', $response_text);
        return $response_text;
    } else {
        prod_trace( 'WARNING', 'No valid response text found in Gemini API response.');

        $localized_errorResponses = (get_locale() !== "en_US")
            ? get_localized_errorResponses(get_locale(), $errorResponses)
            : $errorResponses;

        return $localized_errorResponses[array_rand($localized_errorResponses)];
    }

}

// Get available Gemini models
function chatbot_gemini_get_models() {

    $api_key = esc_attr(get_option('chatbot_gemini_api_key'));

    // Decrypt the API key
    $api_key = chatbot_chatgpt_decrypt_api_key($api_key);

    if (empty($api_key)) {
        return 'Error: API key is not set.';
    }

    $base_url = esc_attr(get_option('chatbot_gemini_base_url', 'https://generativelanguage.googleapis.com/v1beta'));
    $api_url = $base_url . '/models?key=' . $api_key;

    $response = wp_remote_get($api_url, array(
        'timeout' => 30,
    ));

    if (is_wp_error($response)) {
        return 'Error: ' . $response->get_error_message();
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['error'])) {
        return 'Error: ' . ($body['error']['message'] ?? 'Unknown error');
    }

    $models = array();
    if (isset($body['models']) && is_array($body['models'])) {
        foreach ($body['models'] as $model) {
            // Filter to only include generateContent supported models
            if (isset($model['supportedGenerationMethods']) &&
                in_array('generateContent', $model['supportedGenerationMethods'])) {
                $model_id = str_replace('models/', '', $model['name']);
                $models[] = array(
                    'id' => $model_id,
                    'name' => $model['displayName'] ?? $model_id,
                    'owned_by' => 'google'
                );
            }
        }
    }

    // If no models found, return default list
    if (empty($models)) {
        $models = array(
            array('id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash', 'owned_by' => 'google'),
            array('id' => 'gemini-1.5-flash-8b', 'name' => 'Gemini 1.5 Flash 8B', 'owned_by' => 'google'),
            array('id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro', 'owned_by' => 'google'),
            array('id' => 'gemini-1.0-pro', 'name' => 'Gemini 1.0 Pro', 'owned_by' => 'google'),
        );
    }

    return $models;
}
