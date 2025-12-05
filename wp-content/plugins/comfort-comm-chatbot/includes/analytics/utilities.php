<?php
/**
 * Kognetiks Analytics - Utilities - Ver 1.0.0
 *
 * This file contains the code for the Kognetiks Analytics utilities.
 * 
 * 
 * 
 * @package kognetiks-analytics
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die();
}

// Ver 2.5.1: Stub function for removed sentiment analysis feature
// The scoring functions still exist in analytics-settings.php but this one was in sentiment-analysis.php
if (!function_exists('kognetiks_analytics_score_conversations_without_sentiment_score')) {
    function kognetiks_analytics_score_conversations_without_sentiment_score() { return; }
}

// Get conversation log data from Supabase
function kognetiks_analytics_get_chatbot_chatgpt_conversation_log_data() {

    if (function_exists('chatbot_supabase_get_recent_conversations')) {
        $results = chatbot_supabase_get_recent_conversations(365, 10000);
        if (!empty($results)) {
            return $results;
        }
    }
    return "No data found";

}

// Count conversation log data from Supabase
function kognetiks_analytics_count_chatbot_chatgpt_conversation_log_data() {

    if (function_exists('chatbot_supabase_get_recent_conversations')) {
        $results = chatbot_supabase_get_recent_conversations(365, 10000);
        return is_array($results) ? count($results) : 0;
    }
    return 0;

}

// Compute the total tokens
// Updated Ver 2.4.8: Token tracking stored in Supabase - returns placeholder
function kognetiks_analytics_total_tokens() {

    // Token tracking data is stored in Supabase
    return array(
        'total_tokens' => 0,
        'total_tokens_per_prompt' => 0,
        'prompt_tokens_total' => 0,
        'completion_tokens_total' => 0,
        'total_tokens_total' => 0,
        'visitor_count' => 0,
        'chatbot_count' => 0,
        'average_tokens_per_prompt' => 0,
        'average_tokens_per_chatbot' => 0,
        'average_tokens_per_visitor' => 0,
        'session_id_count' => 0,
        'average_tokens_per_session' => 0,
        'average_tokens_per_chatbot_per_session' => 0,
        'average_tokens_per_visitor_per_session' => 0
    );

}

// Compute time-based conversation counts from Supabase
function kognetiks_analytics_get_time_based_conversation_counts($period = 'Today', $user_type = 'All') {

    if (function_exists('chatbot_supabase_get_time_based_conversation_counts')) {
        return chatbot_supabase_get_time_based_conversation_counts($period);
    }

    // Return empty data if Supabase function not available
    return array(
        'current' => array('total' => 0, 'unique_visitors' => 0),
        'previous' => array('total' => 0, 'unique_visitors' => 0),
        'current_period_label' => 'This Period',
        'previous_period_label' => 'Last Period'
    );

}

// Compute message statistics from Supabase
function kognetiks_analytics_get_message_statistics($period = 'Today', $user_type = 'All') {

    if (function_exists('chatbot_supabase_get_message_statistics')) {
        return chatbot_supabase_get_message_statistics($period);
    }

    // Return empty data if Supabase function not available
    return array(
        'current' => array('total_messages' => 0, 'visitor_messages' => 0, 'chatbot_messages' => 0),
        'previous' => array('total_messages' => 0, 'visitor_messages' => 0, 'chatbot_messages' => 0),
        'current_period_label' => 'This Period',
        'previous_period_label' => 'Last Period'
    );

}

// Compute session statistics
// Updated Ver 2.4.8: Uses Supabase - returns placeholder
function kognetiks_analytics_get_session_statistics($period = 'Today', $user_type = 'All') {

    $period_labels = array(
        'Today' => array('current' => 'Today', 'previous' => 'Yesterday'),
        'Week' => array('current' => 'This Week', 'previous' => 'Last Week'),
        'Month' => array('current' => 'This Month', 'previous' => 'Last Month'),
        'Quarter' => array('current' => 'This Quarter', 'previous' => 'Last Quarter'),
        'Year' => array('current' => 'This Year', 'previous' => 'Last Year')
    );

    $labels = $period_labels[$period] ?? $period_labels['Today'];

    return array(
        'current' => array('avg_duration' => 0),
        'previous' => array('avg_duration' => 0),
        'current_period_label' => $labels['current'],
        'previous_period_label' => $labels['previous']
    );

}

// Compute token statistics with period comparison
// Updated Ver 2.4.8: Uses Supabase - returns placeholder
function kognetiks_analytics_get_token_statistics($period = 'Today', $user_type = 'All') {

    $period_labels = array(
        'Today' => array('current' => 'Today', 'previous' => 'Yesterday'),
        'Week' => array('current' => 'This Week', 'previous' => 'Last Week'),
        'Month' => array('current' => 'This Month', 'previous' => 'Last Month'),
        'Quarter' => array('current' => 'This Quarter', 'previous' => 'Last Quarter'),
        'Year' => array('current' => 'This Year', 'previous' => 'Last Year')
    );

    $labels = $period_labels[$period] ?? $period_labels['Today'];

    return array(
        'current' => array('total_tokens' => 0),
        'previous' => array('total_tokens' => 0),
        'current_period_label' => $labels['current'],
        'previous_period_label' => $labels['previous']
    );

}

// Compute visitor statistics
// Updated Ver 2.4.8: Uses Supabase - returns placeholder
function kognetiks_analytics_get_visitor_statistics($period = 'Today', $user_type = 'All') {

    $period_labels = array(
        'Today' => array('current' => 'Today', 'previous' => 'Yesterday'),
        'Week' => array('current' => 'This Week', 'previous' => 'Last Week'),
        'Month' => array('current' => 'This Month', 'previous' => 'Last Month'),
        'Quarter' => array('current' => 'This Quarter', 'previous' => 'Last Quarter'),
        'Year' => array('current' => 'This Year', 'previous' => 'Last Year')
    );

    $labels = $period_labels[$period] ?? $period_labels['Today'];

    return array(
        'current' => array(
            'total_visitors' => 0,
            'new_visitors' => 0,
            'returning_visitors' => 0
        ),
        'previous' => array(
            'total_visitors' => 0,
            'new_visitors' => 0,
            'returning_visitors' => 0
        ),
        'current_period_label' => $labels['current'],
        'previous_period_label' => $labels['previous']
    );

}

// Compute engagement statistics
// Updated Ver 2.4.8: Uses Supabase - returns placeholder
function kognetiks_analytics_get_engagement_statistics($period = 'Today', $user_type = 'All') {

    $period_labels = array(
        'Today' => array('current' => 'Today', 'previous' => 'Yesterday'),
        'Week' => array('current' => 'This Week', 'previous' => 'Last Week'),
        'Month' => array('current' => 'This Month', 'previous' => 'Last Month'),
        'Quarter' => array('current' => 'This Quarter', 'previous' => 'Last Quarter'),
        'Year' => array('current' => 'This Year', 'previous' => 'Last Year')
    );

    $labels = $period_labels[$period] ?? $period_labels['Today'];

    return array(
        'current' => array(
            'high_engagement_rate' => 0,
            'avg_messages_before_dropoff' => 0
        ),
        'previous' => array(
            'high_engagement_rate' => 0,
            'avg_messages_before_dropoff' => 0
        ),
        'current_period_label' => $labels['current'],
        'previous_period_label' => $labels['previous']
    );

}

// Compute sentiment statistics from Supabase
function kognetiks_analytics_get_sentiment_statistics($period = 'Today', $user_type = 'All') {

    if (function_exists('chatbot_supabase_get_sentiment_statistics')) {
        return chatbot_supabase_get_sentiment_statistics($period);
    }

    // Return empty data if Supabase function not available
    return array(
        'current' => array('avg_score' => 0, 'positive_percent' => 0),
        'previous' => array('avg_score' => 0, 'positive_percent' => 0),
        'current_period_label' => 'This Period',
        'previous_period_label' => 'Last Period'
    );

}

// Helper function to calculate the statistics median
function kognetiks_analytics_median_computation($numbers) {

    sort($numbers);
    $count = count($numbers);
    $middle = floor($count / 2);
    
    if ($count % 2 == 0) {
        return ($numbers[$middle - 1] + $numbers[$middle]) / 2;
    } else {
        return $numbers[$middle];
    }

}
