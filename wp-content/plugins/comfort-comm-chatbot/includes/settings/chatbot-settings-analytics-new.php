<?php
/**
 * Steve-Bot - Settings - Analytics & Feedback (NEW Merged Version)
 *
 * Merges Analytics and Reporting tabs with consistent styling from Analytics
 *
 * @package chatbot-chatgpt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die();
}

/**
 * Render the new merged Analytics & Feedback page
 */
function chatbot_analytics_new_page() {

    // Handle period filter - check URL parameter first, then transient, then default
    if (isset($_GET['period']) && in_array($_GET['period'], array('Today', 'Week', 'Month', 'Quarter', 'Year'))) {
        $selected_period = sanitize_text_field($_GET['period']);
        set_transient('chatbot_analytics_selected_period', $selected_period, HOUR_IN_SECONDS);
    } else {
        $selected_period = get_transient('chatbot_analytics_selected_period');
        if (!$selected_period) {
            $selected_period = 'Week';
        }
    }

    // Get statistics - use Supabase functions if configured, otherwise fall back to WordPress DB
    $use_supabase = function_exists('chatbot_supabase_is_configured') && chatbot_supabase_is_configured();

    if ($use_supabase) {
        // Use Supabase analytics functions
        $time_based_counts = function_exists('chatbot_supabase_get_time_based_conversation_counts')
            ? chatbot_supabase_get_time_based_conversation_counts($selected_period)
            : array('current' => array('total' => 0, 'unique_visitors' => 0), 'previous' => array('total' => 0, 'unique_visitors' => 0), 'current_period_label' => 'This Period', 'previous_period_label' => 'Last Period');

        $message_stats = function_exists('chatbot_supabase_get_message_statistics')
            ? chatbot_supabase_get_message_statistics($selected_period)
            : array('current' => array('total_messages' => 0, 'visitor_messages' => 0), 'previous' => array('total_messages' => 0, 'visitor_messages' => 0), 'current_period_label' => 'This Period', 'previous_period_label' => 'Last Period');

        $sentiment_stats = function_exists('chatbot_supabase_get_sentiment_statistics')
            ? chatbot_supabase_get_sentiment_statistics($selected_period)
            : array('current' => array('avg_score' => 0, 'positive_percent' => 0), 'previous' => array('avg_score' => 0, 'positive_percent' => 0));
    } else {
        // Fall back to WordPress database functions
        $time_based_counts = function_exists('kognetiks_analytics_get_time_based_conversation_counts')
            ? kognetiks_analytics_get_time_based_conversation_counts($selected_period, 'All')
            : array('current' => array('total' => 0, 'unique_visitors' => 0), 'previous' => array('total' => 0, 'unique_visitors' => 0), 'current_period_label' => 'This Period', 'previous_period_label' => 'Last Period');

        $message_stats = function_exists('kognetiks_analytics_get_message_statistics')
            ? kognetiks_analytics_get_message_statistics($selected_period, 'All')
            : array('current' => array('total_messages' => 0, 'visitor_messages' => 0), 'previous' => array('total_messages' => 0, 'visitor_messages' => 0), 'current_period_label' => 'This Period', 'previous_period_label' => 'Last Period');

        $sentiment_stats = function_exists('kognetiks_analytics_get_sentiment_statistics')
            ? kognetiks_analytics_get_sentiment_statistics($selected_period, 'All')
            : array('current' => array('avg_score' => 0, 'positive_percent' => 0), 'previous' => array('avg_score' => 0, 'positive_percent' => 0));
    }

    // Get CSAT stats from Reporting (keeping for backward compatibility)
    $csat_stats = function_exists('chatbot_chatgpt_get_csat_stats')
        ? chatbot_chatgpt_get_csat_stats()
        : array('csat_score' => 0, 'total_responses' => 0, 'helpful_count' => 0, 'not_helpful_count' => 0, 'target_met' => false);

    // Ver 2.5.0: Get NPS stats (replaces CSAT as primary metric)
    $nps_stats = function_exists('chatbot_supabase_get_nps_stats')
        ? chatbot_supabase_get_nps_stats()
        : array('nps_score' => 0, 'total_responses' => 0, 'promoters' => 0, 'passives' => 0, 'detractors' => 0);

    // Ver 2.5.0: Get deflection rate and KB vs AI usage
    $deflection_stats = function_exists('chatbot_supabase_get_deflection_stats')
        ? chatbot_supabase_get_deflection_stats($selected_period)
        : array('deflection_rate' => 0, 'kb_percentage' => 0, 'ai_percentage' => 0, 'total_questions' => 0);

    // Ver 2.5.0: Get top FAQ questions
    $top_faqs = function_exists('chatbot_supabase_get_top_faqs_with_details')
        ? chatbot_supabase_get_top_faqs_with_details(5)
        : array();

    // Get learning stats from Reporting
    $review_queue = function_exists('chatbot_get_learning_review_queue') ? chatbot_get_learning_review_queue() : array();
    $pending_count = count($review_queue);
    $learning_stats = function_exists('chatbot_get_learning_stats') ? chatbot_get_learning_stats() : array('approved' => 0, 'rejected' => 0, 'pending' => 0, 'rollbacks' => 0);

    // Get recent feedback
    $csat_data = get_option('chatbot_chatgpt_csat_data', array('responses' => array()));
    $responses = array_reverse($csat_data['responses']);
    $recent_responses = array_slice($responses, 0, 10);

    ?>
    <style>
        .analytics-container {
            max-width: 1400px;
            margin-top: 20px;
        }
        .analytics-section {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .analytics-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 14px;
            color: #1d2327;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        .stats-grid-small {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
        }
        .stat-box h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        .comparison-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
        }
        .current-period, .previous-period {
            flex: 1;
            text-align: center;
        }
        .period-label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            color: #2271b1;
        }
        .stat-value.success { color: #10b981; }
        .stat-value.danger { color: #ef4444; }
        .stat-value.warning { color: #f59e0b; }
        .trend-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            min-width: 40px;
            margin: 0 10px;
            flex-direction: column;
        }
        .trend-up {
            color: #28a745;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            margin-bottom: 4px;
        }
        .trend-down {
            color: #dc3545;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            margin-bottom: 4px;
        }
        .percent-change {
            font-size: 14px;
            font-weight: normal;
            margin-top: 2px;
        }
        .trend-up + .percent-change { color: #28a745; }
        .trend-down + .percent-change { color: #dc3545; }
        .section-header {
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e5e5;
            padding-bottom: 10px;
        }
        .section-header h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
            padding: 0;
            color: #1d2327;
            font-size: 1.3em;
        }
        .section-description {
            color: #646970;
            margin: 5px 0 0;
            font-size: 14px;
        }
        .period-filter-form {
            margin-bottom: 20px;
        }
        .period-filter-form select {
            padding: 8px 12px;
            font-size: 14px;
            min-width: 220px;
        }
        .feedback-table {
            width: 100%;
            border-collapse: collapse;
        }
        .feedback-table th,
        .feedback-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }
        .feedback-table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            color: #666;
        }
        .feedback-table tr:hover {
            background: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: 600;
        }
        .status-success { background: #d1fae5; color: #065f46; }
        .status-warning { background: #fef3c7; color: #92400e; }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .info-box h4 {
            margin: 0 0 10px 0;
            color: #1e40af;
        }
        .info-box ol {
            margin: 0;
            padding-left: 20px;
            color: #1e3a8a;
        }
        .info-box li {
            margin-bottom: 5px;
        }
        .csat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
            border-left: 4px solid #2271b1;
        }
        .csat-card.success { border-left-color: #10b981; }
        .csat-card.danger { border-left-color: #ef4444; }
        .csat-card.info { border-left-color: #3b82f6; }
        .csat-card h4 {
            margin: 0 0 8px 0;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        .csat-value {
            font-size: 28px;
            font-weight: bold;
            color: #1d2327;
        }
        .csat-value.success { color: #10b981; }
        .csat-value.danger { color: #ef4444; }
    </style>

    <div class="analytics-container">

        <!-- Period Filter -->
        <div class="period-filter-form">
            <label for="analytics_period" style="font-weight: 600; margin-right: 10px;">Period:</label>
            <select name="analytics_period" id="analytics_period" onchange="window.location.href='<?php echo esc_url(admin_url('admin.php?page=chatbot-chatgpt&tab=analytics_feedback')); ?>&period=' + this.value;">
                <option value="Today" <?php selected($selected_period, 'Today'); ?>>Today vs Yesterday</option>
                <option value="Week" <?php selected($selected_period, 'Week'); ?>>This Week vs Last Week</option>
                <option value="Month" <?php selected($selected_period, 'Month'); ?>>This Month vs Last Month</option>
                <option value="Quarter" <?php selected($selected_period, 'Quarter'); ?>>This Quarter vs Last Quarter</option>
                <option value="Year" <?php selected($selected_period, 'Year'); ?>>This Year vs Last Year</option>
            </select>
        </div>

        <!-- AI Gap Analysis Dashboard - Ver 2.5.1 (Moved to TOP of page) -->
        <div class="section-header">
            <h2>AI Gap Analysis Dashboard</h2>
            <p class="section-description">Identifies questions users ask that your FAQ database can't answer — AI suggests new FAQs</p>
        </div>

        <div class="analytics-section">
            <?php
            // Call the gap analysis callback from reporting, passing the selected period
            if (function_exists('chatbot_chatgpt_gap_analysis_callback')) {
                chatbot_chatgpt_gap_analysis_callback($selected_period);
            } else {
                echo '<p style="color: #6b7280;">Gap analysis module not loaded.</p>';
            }
            ?>
        </div>

        <div style="margin-bottom: 30px;"></div>

        <!-- KEY PERFORMANCE METRICS - Ver 2.5.0 -->
        <div class="section-header">
            <h2>Key Performance Metrics</h2>
            <p class="section-description">At-a-glance view of your chatbot's effectiveness — the metrics that matter most</p>
        </div>

        <div class="analytics-section">
            <div class="stats-grid">
                <!-- Deflection Rate -->
                <div class="stat-box" style="text-align: center; border-left: 4px solid #10b981;">
                    <h3 style="font-size: 12px; text-transform: uppercase; color: #6b7280;">Deflection Rate</h3>
                    <p class="stat-value <?php echo $deflection_stats['deflection_rate'] >= 60 ? 'success' : ($deflection_stats['deflection_rate'] >= 40 ? 'warning' : 'danger'); ?>" style="font-size: 36px;">
                        <?php echo $deflection_stats['deflection_rate']; ?>%
                    </p>
                    <p style="font-size: 12px; color: #6b7280; margin: 5px 0 0 0;">
                        Questions answered from Knowledge Base
                    </p>
                    <p style="font-size: 11px; color: #9ca3af; margin: 3px 0 0 0;">
                        <?php echo number_format($deflection_stats['kb_answered']); ?> of <?php echo number_format($deflection_stats['total_questions']); ?> questions
                    </p>
                </div>

                <!-- KB vs AI Usage Pie Chart -->
                <div class="stat-box">
                    <h3 style="font-size: 12px; text-transform: uppercase; color: #6b7280; margin-bottom: 15px;">Knowledge Base vs AI Fallback</h3>
                    <div style="display: flex; align-items: center; justify-content: space-around;">
                        <div style="position: relative; width: 120px; height: 120px;">
                            <svg viewBox="0 0 36 36" style="width: 100%; height: 100%; transform: rotate(-90deg);">
                                <!-- Background circle -->
                                <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                                <!-- KB portion (green) -->
                                <circle cx="18" cy="18" r="15.9" fill="none" stroke="#10b981" stroke-width="3"
                                    stroke-dasharray="<?php echo $deflection_stats['kb_percentage']; ?> <?php echo 100 - $deflection_stats['kb_percentage']; ?>"
                                    stroke-linecap="round"/>
                            </svg>
                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                                <span style="font-size: 18px; font-weight: bold; color: #10b981;"><?php echo $deflection_stats['kb_percentage']; ?>%</span>
                            </div>
                        </div>
                        <div style="text-align: left;">
                            <div style="margin-bottom: 8px;">
                                <span style="display: inline-block; width: 12px; height: 12px; background: #10b981; border-radius: 2px; margin-right: 8px;"></span>
                                <span style="font-size: 13px;">Knowledge Base: <?php echo $deflection_stats['kb_percentage']; ?>%</span>
                            </div>
                            <div>
                                <span style="display: inline-block; width: 12px; height: 12px; background: #f59e0b; border-radius: 2px; margin-right: 8px;"></span>
                                <span style="font-size: 13px;">AI Fallback: <?php echo $deflection_stats['ai_percentage']; ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top 5 FAQ Questions -->
                <div class="stat-box" style="grid-column: span 2;">
                    <h3 style="font-size: 12px; text-transform: uppercase; color: #6b7280; margin-bottom: 15px;">Top 5 Most Asked Questions</h3>
                    <?php if (empty($top_faqs)): ?>
                        <p style="color: #9ca3af; font-size: 13px;">No FAQ usage data yet. Questions will appear here as users interact with the chatbot.</p>
                    <?php else: ?>
                        <?php
                        $max_hits = !empty($top_faqs) ? max(array_column($top_faqs, 'hit_count')) : 1;
                        foreach ($top_faqs as $index => $faq):
                            $bar_width = $max_hits > 0 ? ($faq['hit_count'] / $max_hits) * 100 : 0;
                            $question_short = strlen($faq['question']) > 60 ? substr($faq['question'], 0, 60) . '...' : $faq['question'];
                        ?>
                        <div style="margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                                <span style="font-size: 13px; color: #374151;" title="<?php echo esc_attr($faq['question']); ?>">
                                    <?php echo ($index + 1) . '. ' . esc_html($question_short); ?>
                                </span>
                                <span style="font-size: 12px; font-weight: 600; color: #2271b1;"><?php echo number_format($faq['hit_count']); ?></span>
                            </div>
                            <div style="background: #e5e7eb; border-radius: 4px; height: 8px; overflow: hidden;">
                                <div style="background: #2271b1; height: 100%; width: <?php echo $bar_width; ?>%; border-radius: 4px;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Conversation Statistics -->
        <div class="section-header">
            <h2>Conversation Statistics</h2>
            <p class="section-description">Key metrics about your chatbot's conversations and user interactions</p>
        </div>

        <div class="analytics-section">
            <h3>Overview</h3>
            <div class="stats-grid">
                <div class="stat-box">
                    <h3>Total Conversations</h3>
                    <div class="comparison-row">
                        <div class="current-period">
                            <span class="period-label"><?php echo esc_html($time_based_counts['current_period_label'] ?? 'This Period'); ?></span>
                            <p class="stat-value"><?php echo number_format($time_based_counts['current']['total'] ?? 0); ?></p>
                        </div>
                        <div class="trend-indicator">
                            <?php
                            $current = $time_based_counts['current']['total'] ?? 0;
                            $previous = $time_based_counts['previous']['total'] ?? 0;
                            if ($current > $previous) {
                                $percent_change = $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;
                                echo '<span class="trend-up">⬆</span><span class="percent-change">+' . number_format($percent_change, 1) . '%</span>';
                            } elseif ($current < $previous) {
                                $percent_change = $previous > 0 ? (($previous - $current) / $previous) * 100 : 0;
                                echo '<span class="trend-down">⬇</span><span class="percent-change">-' . number_format($percent_change, 1) . '%</span>';
                            }
                            ?>
                        </div>
                        <div class="previous-period">
                            <span class="period-label"><?php echo esc_html($time_based_counts['previous_period_label'] ?? 'Last Period'); ?></span>
                            <p class="stat-value"><?php echo number_format($time_based_counts['previous']['total'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>
                <div class="stat-box">
                    <h3>Unique Visitors</h3>
                    <div class="comparison-row">
                        <div class="current-period">
                            <span class="period-label"><?php echo esc_html($time_based_counts['current_period_label'] ?? 'This Period'); ?></span>
                            <p class="stat-value"><?php echo number_format($time_based_counts['current']['unique_visitors'] ?? 0); ?></p>
                        </div>
                        <div class="trend-indicator">
                            <?php
                            $current = $time_based_counts['current']['unique_visitors'] ?? 0;
                            $previous = $time_based_counts['previous']['unique_visitors'] ?? 0;
                            if ($current > $previous) {
                                $percent_change = $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;
                                echo '<span class="trend-up">⬆</span><span class="percent-change">+' . number_format($percent_change, 1) . '%</span>';
                            } elseif ($current < $previous) {
                                $percent_change = $previous > 0 ? (($previous - $current) / $previous) * 100 : 0;
                                echo '<span class="trend-down">⬇</span><span class="percent-change">-' . number_format($percent_change, 1) . '%</span>';
                            }
                            ?>
                        </div>
                        <div class="previous-period">
                            <span class="period-label"><?php echo esc_html($time_based_counts['previous_period_label'] ?? 'Last Period'); ?></span>
                            <p class="stat-value"><?php echo number_format($time_based_counts['previous']['unique_visitors'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Message Statistics -->
        <div class="section-header">
            <h2>Message Statistics</h2>
            <p class="section-description">Breakdown of messages between visitors and chatbot</p>
        </div>

        <div class="analytics-section">
            <div class="stats-grid">
                <div class="stat-box">
                    <h3>Total Messages</h3>
                    <div class="comparison-row">
                        <div class="current-period">
                            <span class="period-label"><?php echo esc_html($message_stats['current_period_label'] ?? 'This Period'); ?></span>
                            <p class="stat-value"><?php echo number_format($message_stats['current']['total_messages'] ?? 0); ?></p>
                        </div>
                        <div class="trend-indicator">
                            <?php
                            $current = $message_stats['current']['total_messages'] ?? 0;
                            $previous = $message_stats['previous']['total_messages'] ?? 0;
                            if ($current > $previous) {
                                $percent_change = $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;
                                echo '<span class="trend-up">⬆</span><span class="percent-change">+' . number_format($percent_change, 1) . '%</span>';
                            } elseif ($current < $previous) {
                                $percent_change = $previous > 0 ? (($previous - $current) / $previous) * 100 : 0;
                                echo '<span class="trend-down">⬇</span><span class="percent-change">-' . number_format($percent_change, 1) . '%</span>';
                            }
                            ?>
                        </div>
                        <div class="previous-period">
                            <span class="period-label"><?php echo esc_html($message_stats['previous_period_label'] ?? 'Last Period'); ?></span>
                            <p class="stat-value"><?php echo number_format($message_stats['previous']['total_messages'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>
                <div class="stat-box">
                    <h3>Visitor Messages</h3>
                    <div class="comparison-row">
                        <div class="current-period">
                            <span class="period-label"><?php echo esc_html($message_stats['current_period_label'] ?? 'This Period'); ?></span>
                            <p class="stat-value"><?php echo number_format($message_stats['current']['visitor_messages'] ?? 0); ?></p>
                        </div>
                        <div class="trend-indicator">
                            <?php
                            $current = $message_stats['current']['visitor_messages'] ?? 0;
                            $previous = $message_stats['previous']['visitor_messages'] ?? 0;
                            if ($current > $previous) {
                                $percent_change = $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;
                                echo '<span class="trend-up">⬆</span><span class="percent-change">+' . number_format($percent_change, 1) . '%</span>';
                            } elseif ($current < $previous) {
                                $percent_change = $previous > 0 ? (($previous - $current) / $previous) * 100 : 0;
                                echo '<span class="trend-down">⬇</span><span class="percent-change">-' . number_format($percent_change, 1) . '%</span>';
                            }
                            ?>
                        </div>
                        <div class="previous-period">
                            <span class="period-label"><?php echo esc_html($message_stats['previous_period_label'] ?? 'Last Period'); ?></span>
                            <p class="stat-value"><?php echo number_format($message_stats['previous']['visitor_messages'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Net Promoter Score (NPS) - Ver 2.5.0 (Replaces CSAT) -->
        <div class="section-header">
            <h2>Net Promoter Score (NPS)</h2>
            <p class="section-description">Industry-standard metric: "How likely are you to recommend this chatbot?" (0-10 scale)</p>
        </div>

        <div class="analytics-section">
            <?php
            // Determine NPS color and status
            $nps_score = $nps_stats['nps_score'];
            if ($nps_score >= 50) {
                $nps_color = 'success';
                $nps_label = 'Excellent';
            } elseif ($nps_score >= 0) {
                $nps_color = 'warning';
                $nps_label = 'Good';
            } else {
                $nps_color = 'danger';
                $nps_label = 'Needs Improvement';
            }
            ?>
            <div class="stats-grid">
                <!-- NPS Score -->
                <div class="stat-box" style="text-align: center; border-left: 4px solid <?php echo $nps_color === 'success' ? '#10b981' : ($nps_color === 'warning' ? '#f59e0b' : '#ef4444'); ?>;">
                    <h3 style="font-size: 12px; text-transform: uppercase; color: #6b7280;">NPS Score</h3>
                    <p class="stat-value <?php echo $nps_color; ?>" style="font-size: 48px;">
                        <?php echo $nps_score >= 0 ? '+' . $nps_score : $nps_score; ?>
                    </p>
                    <p style="font-size: 13px; color: #6b7280; margin: 5px 0 0 0;">
                        <?php echo $nps_label; ?> (<?php echo $nps_stats['total_responses']; ?> responses)
                    </p>
                    <p style="font-size: 11px; color: #9ca3af; margin: 8px 0 0 0;">
                        Scale: -100 to +100 | Above 50 = Excellent
                    </p>
                </div>

                <!-- NPS Breakdown -->
                <div class="stat-box">
                    <h3 style="font-size: 12px; text-transform: uppercase; color: #6b7280; margin-bottom: 15px;">Response Breakdown</h3>

                    <!-- Promoters (9-10) -->
                    <div style="margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="font-size: 13px; color: #10b981; font-weight: 500;">Promoters (9-10)</span>
                            <span style="font-size: 13px; font-weight: 600;"><?php echo $nps_stats['promoters']; ?> (<?php echo $nps_stats['promoter_percent']; ?>%)</span>
                        </div>
                        <div style="background: #e5e7eb; border-radius: 4px; height: 10px; overflow: hidden;">
                            <div style="background: #10b981; height: 100%; width: <?php echo $nps_stats['promoter_percent']; ?>%;"></div>
                        </div>
                    </div>

                    <!-- Passives (7-8) -->
                    <div style="margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="font-size: 13px; color: #6b7280; font-weight: 500;">Passives (7-8)</span>
                            <span style="font-size: 13px; font-weight: 600;"><?php echo $nps_stats['passives']; ?> (<?php echo $nps_stats['passive_percent']; ?>%)</span>
                        </div>
                        <div style="background: #e5e7eb; border-radius: 4px; height: 10px; overflow: hidden;">
                            <div style="background: #9ca3af; height: 100%; width: <?php echo $nps_stats['passive_percent']; ?>%;"></div>
                        </div>
                    </div>

                    <!-- Detractors (0-6) -->
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="font-size: 13px; color: #ef4444; font-weight: 500;">Detractors (0-6)</span>
                            <span style="font-size: 13px; font-weight: 600;"><?php echo $nps_stats['detractors']; ?> (<?php echo $nps_stats['detractor_percent']; ?>%)</span>
                        </div>
                        <div style="background: #e5e7eb; border-radius: 4px; height: 10px; overflow: hidden;">
                            <div style="background: #ef4444; height: 100%; width: <?php echo $nps_stats['detractor_percent']; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- NPS Formula Explanation -->
            <div style="margin-top: 15px; padding: 12px; background: #f0f9ff; border-radius: 6px; border-left: 3px solid #3b82f6;">
                <p style="margin: 0; font-size: 12px; color: #1e40af;">
                    <strong>How NPS is calculated:</strong> % Promoters − % Detractors = NPS Score.
                    Scores above 0 are good, above 50 are excellent.
                </p>
            </div>
        </div>

    </div>
    <?php
}
