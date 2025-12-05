<?php
/**
 * Kognetiks Chatbot - Settings - Reporting
 *
 * This file contains the code for the Chatbot settings page.
 * It handles the reporting settings and other parameters.
 *
 *
 * @package chatbot-chatgpt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die();
}

// Register Reporting settings - Ver 2.0.7
function chatbot_chatgpt_reporting_settings_init() {

    // Register settings for Reporting
    register_setting('chatbot_chatgpt_reporting', 'chatbot_chatgpt_reporting_period');
    register_setting('chatbot_chatgpt_reporting', 'chatbot_chatgpt_enable_conversation_logging');
    register_setting('chatbot_chatgpt_reporting', 'chatbot_chatgpt_conversation_log_days_to_keep');

    // Reporting Overview Section
    add_settings_section(
        'chatbot_chatgpt_reporting_overview_section',
        'Reporting Overview',
        'chatbot_chatgpt_reporting_overview_section_callback',
        'chatbot_chatgpt_reporting_overview'
    );

    // Reporting Settings Section
    add_settings_section(
        'chatbot_chatgpt_reporting_section',
        'Reporting Settings',
        'chatbot_chatgpt_reporting_section_callback',
        'chatbot_chatgpt_reporting'
    );

    // Reporting Settings Field - Reporting Period
    add_settings_field(
        'chatbot_chatgpt_reporting_period',
        'Reporting Period',
        'chatbot_chatgpt_reporting_period_callback',
        'chatbot_chatgpt_reporting',
        'chatbot_chatgpt_reporting_section'
    );

    // Reporting Settings Field - Enable Conversation Logging
    add_settings_field(
        'chatbot_chatgpt_enable_conversation_logging',
        'Enable Conversation Logging',
        'chatbot_chatgpt_enable_conversation_logging_callback',
        'chatbot_chatgpt_reporting',
        'chatbot_chatgpt_reporting_section'
    );

    // Reporting Settings Field - Conversation Log Days to Keep
    add_settings_field(
        'chatbot_chatgpt_conversation_log_days_to_keep',
        'Conversation Log Days to Keep',
        'chatbot_chatgpt_conversation_log_days_to_keep_callback',
        'chatbot_chatgpt_reporting',
        'chatbot_chatgpt_reporting_section'
    );

    // Conversation Data Section
    add_settings_section(
        'chatbot_chatgpt_conversation_reporting_section',
        'Conversation Data',
        'chatbot_chatgpt_conversation_reporting_section_callback',
        'chatbot_chatgpt_conversation_reporting'
    );

    add_settings_field(
        'chatbot_chatgpt_conversation_reporting_field',
        'Conversation Data',
        'chatbot_chatgpt_conversation_reporting_callback',
        'chatbot_chatgpt_reporting',
        'chatbot_chatgpt_conversation_reporting_section'
    );

    // Interaction Data Section
    add_settings_section(
        'chatbot_chatgpt_interaction_reporting_section',
        'Interaction Data',
        'chatbot_chatgpt_interaction_reporting_section_callback',
        'chatbot_chatgpt_interaction_reporting'
    );

    add_settings_field(
        'chatbot_chatgpt_interaction_reporting_field',
        'Interaction Data',
        'chatbot_chatgpt_interaction_reporting_callback',
        'chatbot_chatgpt_reporting',
        'chatbot_chatgpt_interaction_reporting_section'
    );

    // // Token Data Section
    add_settings_section(
        'chatbot_chatgpt_token_reporting_section',
        'Token Data',
        'chatbot_chatgpt_token_reporting_section_callback',
        'chatbot_chatgpt_token_reporting'
    );

    add_settings_field(
        'chatbot_chatgpt_token_reporting_field',
        'Token Data',
        'chatbot_chatgpt_token_reporting_callback',
        'chatbot_chatgpt_reporting',
        'chatbot_chatgpt_token_reporting_section'
    );

    // Gap Analysis Section - Ver 2.4.2
    add_settings_section(
        'chatbot_chatgpt_gap_analysis_section',
        'Gap Analysis',
        'chatbot_chatgpt_gap_analysis_section_callback',
        'chatbot_chatgpt_gap_analysis'
    );

    add_settings_field(
        'chatbot_chatgpt_gap_analysis_field',
        '',
        'chatbot_chatgpt_gap_analysis_callback',
        'chatbot_chatgpt_gap_analysis',
        'chatbot_chatgpt_gap_analysis_section'
    );

    // Learning Dashboard Section
    add_settings_section(
        'chatbot_chatgpt_learning_dashboard_section',
        'Learning Dashboard',
        'chatbot_chatgpt_learning_dashboard_section_callback',
        'chatbot_chatgpt_learning_dashboard'
    );

    add_settings_field(
        'chatbot_chatgpt_learning_dashboard_field',
        '',
        'chatbot_chatgpt_learning_dashboard_callback',
        'chatbot_chatgpt_learning_dashboard',
        'chatbot_chatgpt_learning_dashboard_section'
    );

}
add_action('admin_init', 'chatbot_chatgpt_reporting_settings_init');

// Reporting section callback - Ver 1.6.3
function chatbot_chatgpt_reporting_overview_section_callback($args) {
    // Get CSAT stats
    $csat_stats = chatbot_chatgpt_get_csat_stats();
    $csat_score = $csat_stats['csat_score'];
    $total = $csat_stats['total_responses'];
    $helpful = $csat_stats['helpful_count'];
    $not_helpful = $csat_stats['not_helpful_count'];
    $target_met = $csat_stats['target_met'];

    $score_color = $target_met ? '#10b981' : '#ef4444'; // Green if >70%, red otherwise
    $status_text = $target_met ? 'Target Met (>70%)' : 'Below Target (<70%)';
    $status_color = $target_met ? '#10b981' : '#f59e0b';
    ?>
    <div>
        <!-- CSAT Metrics Dashboard -->
        <div style="background-color: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin-top: 0; color: #1e293b;">CSAT (Customer Satisfaction) Metrics</h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                <!-- CSAT Score -->
                <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid <?php echo $score_color; ?>;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">CSAT Score</div>
                    <div style="font-size: 32px; font-weight: bold; color: <?php echo $score_color; ?>;"><?php echo $csat_score; ?>%</div>
                </div>

                <!-- Total Responses -->
                <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #3b82f6;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Total Responses</div>
                    <div style="font-size: 32px; font-weight: bold; color: #1e293b;"><?php echo $total; ?></div>
                </div>

                <!-- Helpful -->
                <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #10b981;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Helpful</div>
                    <div style="font-size: 32px; font-weight: bold; color: #10b981;"><?php echo $helpful; ?></div>
                </div>

                <!-- Not Helpful -->
                <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #ef4444;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Not Helpful</div>
                    <div style="font-size: 32px; font-weight: bold; color: #ef4444;"><?php echo $not_helpful; ?></div>
                </div>
            </div>

            <!-- Status Badge -->
            <div style="background-color: <?php echo $status_color; ?>15; border: 1px solid <?php echo $status_color; ?>; border-radius: 4px; padding: 10px; text-align: center;">
                <span style="color: <?php echo $status_color; ?>; font-weight: 600;"><?php echo $status_text; ?></span>
            </div>

            <p style="margin-top: 15px; margin-bottom: 0; font-size: 12px; color: #64748b;">
                <b>P0 Success Metric:</b> CSAT Score >70% |
                <b>Calculation:</b> (Helpful / Total) × 100 = (<?php echo $helpful; ?> / <?php echo $total; ?>) × 100
            </p>
        </div>

        <?php
        // Display recent CSAT feedback with Q&A details
        $csat_data = get_option('chatbot_chatgpt_csat_data', array('responses' => array()));
        $responses = array_reverse($csat_data['responses']); // Most recent first
        $recent_responses = array_slice($responses, 0, 20); // Limit to 20 most recent

        if (!empty($recent_responses)) {
        ?>
        <!-- Recent CSAT Feedback Table -->
        <div style="background-color: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin-top: 0; color: #1e293b;">Recent Feedback Details</h3>
            <p style="font-size: 12px; color: #64748b; margin-bottom: 15px;">Showing the most recent <?php echo count($recent_responses); ?> CSAT responses with question and answer details</p>

            <table class="widefat striped" style="border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f1f5f9;">
                        <th style="padding: 10px; width: 100px;">Date/Time</th>
                        <th style="padding: 10px; width: 60px;">Feedback</th>
                        <th style="padding: 10px; width: 90px;">Confidence</th>
                        <th style="padding: 10px;">Question Asked</th>
                        <th style="padding: 10px;">Answer Given</th>
                        <th style="padding: 10px; width: 200px;">User Comment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_responses as $response) :
                        $feedback_icon = $response['feedback'] === 'yes' ? '+' : '-';
                        $feedback_color = $response['feedback'] === 'yes' ? '#10b981' : '#ef4444';
                        $question = isset($response['question']) ? esc_html($response['question']) : 'N/A';
                        $answer = isset($response['answer']) ? esc_html($response['answer']) : 'N/A';
                        $comment = isset($response['comment']) && !empty($response['comment']) ? esc_html($response['comment']) : '';
                        $confidence = isset($response['confidence_score']) ? $response['confidence_score'] : 'unknown';

                        // Map confidence to display format and color
                        $confidence_map = [
                            'very_high' => ['label' => 'Very High', 'color' => '#10b981'],
                            'high' => ['label' => 'High', 'color' => '#3b82f6'],
                            'medium' => ['label' => 'Medium', 'color' => '#f59e0b'],
                            'low' => ['label' => 'Low', 'color' => '#ef4444'],
                            'unknown' => ['label' => '—', 'color' => '#94a3b8']
                        ];
                        $conf_display = $confidence_map[$confidence] ?? $confidence_map['unknown'];

                        // Truncate long text for display
                        $question_display = strlen($question) > 100 ? substr($question, 0, 100) . '...' : $question;
                        $answer_display = strlen($answer) > 150 ? substr($answer, 0, 150) . '...' : $answer;
                        $comment_display = strlen($comment) > 100 ? substr($comment, 0, 100) . '...' : $comment;
                    ?>
                    <tr>
                        <td style="padding: 8px; font-size: 11px;">
                            <?php echo date('m/d H:i', strtotime($response['timestamp'])); ?>
                        </td>
                        <td style="padding: 8px; text-align: center;">
                            <span style="font-size: 20px; color: <?php echo $feedback_color; ?>;"><?php echo $feedback_icon; ?></span>
                        </td>
                        <td style="padding: 8px; font-size: 11px; text-align: center;">
                            <span style="display: inline-block; padding: 4px 8px; background-color: <?php echo $conf_display['color']; ?>20; color: <?php echo $conf_display['color']; ?>; border-radius: 4px; font-weight: 600;">
                                <?php echo $conf_display['label']; ?>
                            </span>
                        </td>
                        <td style="padding: 8px; font-size: 12px; max-width: 250px;">
                            <?php echo $question_display; ?>
                        </td>
                        <td style="padding: 8px; font-size: 12px; max-width: 300px;">
                            <?php echo $answer_display; ?>
                        </td>
                        <td style="padding: 8px; font-size: 12px; max-width: 200px; font-style: italic; color: #64748b;">
                            <?php echo $comment ? $comment_display : '—'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php } ?>

        <!-- AI-Powered Feedback Analysis -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #111827;">AI-Powered Feedback Analysis</h3>
            <p style="margin: 0 0 15px 0; font-size: 13px; color: #6b7280;">
                Analyze thumbs-down feedback to automatically generate FAQ improvement suggestions based on selected time period.
            </p>

            <!-- Time Period Selector -->
            <div style="margin-bottom: 15px;">
                <label for="feedback-period" style="font-weight: 600; margin-right: 10px;">Analysis Period:</label>
                <select id="feedback-period" style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                    <option value="weekly">Weekly (Last 7 days)</option>
                    <option value="monthly">Monthly (Last 30 days)</option>
                    <option value="quarterly">Quarterly (Last 90 days)</option>
                    <option value="yearly">Yearly (Last 365 days)</option>
                    <option value="all">All Time</option>
                </select>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="chatbotAnalyzeFeedback()" class="button button-primary" style="font-size: 15px; padding: 10px 30px; height: auto;">
                    Analyze Feedback
                </button>
                <button type="button" onclick="chatbotClearFeedback()" class="button" style="font-size: 15px; padding: 10px 20px; height: auto; background: #ef4444; color: white; border-color: #dc2626;">
                    Clear Feedback Data
                </button>
            </div>

            <div id="chatbot-feedback-analysis-results" style="margin-top: 20px;"></div>
        </div>

        <script>
        // Global modal function for branded dialogs
        function chatbotShowModal(options) {
            const overlay = document.createElement('div');
            overlay.id = 'chatbot-modal-overlay';
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:100000;display:flex;align-items:center;justify-content:center;';

            const borderColor = options.isError ? '#dc3232' : '#2271b1';
            const modal = document.createElement('div');
            modal.style.cssText = 'background:white;border-radius:4px;box-shadow:0 3px 6px rgba(0,0,0,0.3);max-width:400px;width:90%;border-top:4px solid ' + borderColor + ';';

            modal.innerHTML = `
                <div style="padding:20px 20px 0;">
                    <h2 style="margin:0 0 12px;font-size:18px;font-weight:600;color:#1d2327;">${options.title}</h2>
                    <p style="margin:0;color:#50575e;font-size:14px;line-height:1.5;">${options.message}</p>
                </div>
                <div style="padding:16px 20px;display:flex;justify-content:flex-end;gap:10px;margin-top:20px;border-top:1px solid #dcdcde;">
                    ${options.hideCancel ? '' : `<button id="chatbot-modal-cancel" class="button" style="padding:6px 12px;">${options.cancelText || 'Cancel'}</button>`}
                    <button id="chatbot-modal-confirm" class="button button-primary" style="padding:6px 12px;">${options.confirmText || 'OK'}</button>
                </div>
            `;

            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            document.getElementById('chatbot-modal-confirm').onclick = function() {
                document.body.removeChild(overlay);
                if (options.onConfirm) options.onConfirm();
            };

            const cancelBtn = document.getElementById('chatbot-modal-cancel');
            if (cancelBtn) {
                cancelBtn.onclick = function() {
                    document.body.removeChild(overlay);
                    if (options.onCancel) options.onCancel();
                };
            }

            overlay.onclick = function(e) {
                if (e.target === overlay && !options.hideCancel) {
                    document.body.removeChild(overlay);
                    if (options.onCancel) options.onCancel();
                }
            };
        }

        function chatbotAnalyzeFeedback() {
            const btn = event.target;
            const originalText = btn.textContent;
            const resultsDiv = document.getElementById('chatbot-feedback-analysis-results');
            const period = document.getElementById('feedback-period').value;

            btn.disabled = true;
            btn.textContent = 'Analyzing feedback...';
            resultsDiv.innerHTML = '';

            jQuery.post(ajaxurl, {
                action: 'chatbot_analyze_feedback',
                period: period,
                nonce: '<?php echo wp_create_nonce('chatbot_feedback_analysis'); ?>'
            }, function(response) {
                btn.disabled = false;
                btn.textContent = originalText;

                if (response.success) {
                    resultsDiv.innerHTML = response.data.html;
                } else {
                    resultsDiv.innerHTML = '<div style="background: #fee2e2; border: 1px solid #fecaca; padding: 15px; border-radius: 6px; color: #991b1b;">Error: ' + (response.data || 'Unknown error') + '</div>';
                }
            }).fail(function() {
                btn.disabled = false;
                btn.textContent = originalText;
                resultsDiv.innerHTML = '<div style="background: #fee2e2; border: 1px solid #fecaca; padding: 15px; border-radius: 6px; color: #991b1b;">Request failed. Please try again.</div>';
            });
        }

        function chatbotClearFeedback() {
            chatbotShowModal({
                title: 'Clear All Feedback',
                message: 'Are you sure you want to clear ALL feedback data? This cannot be undone!',
                isError: true,
                confirmText: 'Yes, Clear All',
                onConfirm: function() {
                    const btn = document.querySelector('[onclick*="chatbotClearFeedback"]');
                    const originalText = btn.textContent;
                    btn.disabled = true;
                    btn.textContent = 'Clearing...';

                    jQuery.post(ajaxurl, {
                        action: 'chatbot_clear_feedback',
                        nonce: '<?php echo wp_create_nonce('chatbot_clear_feedback'); ?>'
                    }, function(response) {
                        btn.disabled = false;
                        btn.textContent = originalText;

                        if (response.success) {
                            chatbotShowModal({
                                title: 'Success',
                                message: 'Feedback data cleared successfully!',
                                hideCancel: true,
                                onConfirm: function() { location.reload(); }
                            });
                        } else {
                            chatbotShowModal({
                                title: 'Error',
                                message: response.data || 'Unknown error',
                                isError: true,
                                hideCancel: true
                            });
                        }
                    }).fail(function() {
                        btn.disabled = false;
                        btn.textContent = originalText;
                        chatbotShowModal({
                            title: 'Error',
                            message: 'Request failed. Please try again.',
                            isError: true,
                            hideCancel: true
                        });
                    });
                }
            });
        }

        function chatbotAddFAQ(suggestion) {
            chatbotShowModal({
                title: 'Add New FAQ',
                message: 'Add this new FAQ to the knowledge base?',
                confirmText: 'Yes, Add FAQ',
                onConfirm: function() {
                    const faq = suggestion.suggested_faq;
                    jQuery.post(ajaxurl, {
                        action: 'chatbot_add_faq',
                        faq_data: faq,
                        nonce: '<?php echo wp_create_nonce('chatbot_faq_management'); ?>'
                    }, function(response) {
                        if (response.success) {
                            chatbotShowModal({
                                title: 'Success',
                                message: 'FAQ added successfully! ID: ' + response.data.faq_id,
                                hideCancel: true
                            });
                        } else {
                            chatbotShowModal({
                                title: 'Error',
                                message: response.data || 'Unknown error',
                                isError: true,
                                hideCancel: true
                            });
                        }
                    }).fail(function() {
                        chatbotShowModal({
                            title: 'Error',
                            message: 'Request failed. Please try again.',
                            isError: true,
                            hideCancel: true
                        });
                    });
                }
            });
        }

        </script>

        <p>Use these setting to select the reporting period for Visitor and User Interactions.</p>
        <p>Please review the section <b>Conversation Logging Overview</b> on the <a href="?page=chatbot-chatgpt&tab=support&dir=support&file=conversation-logging-and-history.md">Support</a> tab of this plugin for more details.</p>
        <p><b><i>Don't forget to click </i><code>Save Settings</code><i> to save any changes your might make.</i></b></p>
        <p style="background-color: #e0f7fa; padding: 10px;"><b>For an explanation on how to use the Reporting and additional documentation please click <a href="?page=chatbot-chatgpt&tab=support&dir=reporting&file=reporting.md">here</a>.</b></p>
    </div>
    <?php
}

function chatbot_chatgpt_reporting_section_callback($args) {
    ?>
    <div>
        <p>Use these settings to select the reporting period for Visitor and User Interactions.</p>
        <p>You will need to Enable Conversation Logging if you want to record chatbot interactions. By default, conversation logging is initially turned <b>Off</b>.</p>
        <p>Conversation Log Days to Keep sets the number of days to keep the conversation log data in the database.</p>
    </div>
    <?php
}

function chatbot_chatgpt_conversation_reporting_section_callback($args) {
    ?>
    <div>
        <p>Conversation items stored in your DB total <b><?php echo chatbot_chatgpt_count_conversations(); ?></b> rows (includes both Visitor and User input and chatbot responses).</p>
        <p>Conversation items stored take up <b><?php echo chatbot_chatgpt_size_conversations(); ?> MB</b> in your database.</p>
        <p>Use the button (below) to retrieve the conversation data and download as a CSV file.</p>
        <?php
            if (is_admin()) {
                $header = " ";
                $header .= '<a class="button button-primary" href="' . esc_url(admin_url('admin-post.php?action=chatbot_chatgpt_download_conversation_data')) . '">Download Conversation Data</a>';
                echo $header;
            }
        ?>
    </div>
    <?php
}

function chatbot_chatgpt_interaction_reporting_section_callback($args) {
    ?>
    <div>
        <!-- TEMPORARILY REMOVED AS SOME USERS ARE EXPERIENCING ISSUES WITH THE CHARTS - Ver 1.7.8 -->
        <!-- <p><?php echo do_shortcode('[chatbot_simple_chart from_database="true"]'); ?></p> -->
        <p><?php echo chatbot_chatgpt_interactions_table() ?></p>
        <p>Use the button (below) to retrieve the interactions data and download as a CSV file.</p>
        <?php
            if (is_admin()) {
                $header = " ";
                $header .= '<a class="button button-primary" href="' . esc_url(admin_url('admin-post.php?action=chatbot_chatgpt_download_interactions_data')) . '">Download Interaction Data</a>';
                echo $header;
            }
        ?>
    </div>
    <?php
}

function chatbot_chatgpt_token_reporting_section_callback($args) {
    ?>
    <div>
        <p><?php echo chatbot_chatgpt_total_tokens() ?></p>
        <p>Use the button (below) to retrieve the interactions data and download as a CSV file.</p>
        <?php
            if (is_admin()) {
                $header = " ";
                $header .= '<a class="button button-primary" href="' . esc_url(admin_url('admin-post.php?action=chatbot_chatgpt_download_token_usage_data')) . '">Download Token Usage Data</a>';
                echo $header;
            }
        ?>
    </div>
    <?php
}

function chatbot_chatgpt_reporting_settings_callback($args){
    ?>
    <div>
        <h3>Reporting Settings</h3>
    </div>
    <?php
}

// Knowledge Navigator Analysis section callback - Ver 1.6.2
function chatbot_chatgpt_reporting_period_callback($args) {
    // Get the saved chatbot_chatgpt_reporting_period value or default to "Daily"
    $output_choice = esc_attr(get_option('chatbot_chatgpt_reporting_period', 'Daily'));
    // DIAG - Log the output choice
    // back_trace( 'NOTICE', 'chatbot_chatgpt_reporting_period' . $output_choice);
    ?>
    <select id="chatbot_chatgpt_reporting_period" name="chatbot_chatgpt_reporting_period">
        <option value="<?php echo esc_attr( 'Daily' ); ?>" <?php selected( $output_choice, 'Daily' ); ?>><?php echo esc_html( 'Daily' ); ?></option>
        <!-- <option value="<?php echo esc_attr( 'Weekly' ); ?>" <?php selected( $output_choice, 'Weekly' ); ?>><?php echo esc_html( 'Weekly' ); ?></option> -->
        <option value="<?php echo esc_attr( 'Monthly' ); ?>" <?php selected( $output_choice, 'Monthly' ); ?>><?php echo esc_html( 'Monthly' ); ?></option>
        <option value="<?php echo esc_attr( 'Yearly' ); ?>" <?php selected( $output_choice, 'Yearly' ); ?>><?php echo esc_html( 'Yearly' ); ?></option>
    </select>
    <?php
}

// Conversation Logging - Ver 1.7.6
function  chatbot_chatgpt_enable_conversation_logging_callback($args) {
    // Get the saved chatbot_chatgpt_enable_conversation_logging value or default to "Off"
    $output_choice = esc_attr(get_option('chatbot_chatgpt_enable_conversation_logging', 'Off'));
    // DIAG - Log the output choice
    // back_trace( 'NOTICE', 'chatbot_chatgpt_enable_conversation_logging' . $output_choice);
    ?>
    <select id="chatbot_chatgpt_enable_conversation_logging" name="chatbot_chatgpt_enable_conversation_logging">
        <option value="<?php echo esc_attr( 'On' ); ?>" <?php selected( $output_choice, 'On' ); ?>><?php echo esc_html( 'On' ); ?></option>
        <option value="<?php echo esc_attr( 'Off' ); ?>" <?php selected( $output_choice, 'Off' ); ?>><?php echo esc_html( 'Off' ); ?></option>
    </select>
    <?php
}

// Conversation log retention period - Ver 1.7.6
function chatbot_chatgpt_conversation_log_days_to_keep_callback($args) {
    // Get the saved chatbot_chatgpt_conversation_log_days_to_keep value or default to "30"
    $output_choice = esc_attr(get_option('chatbot_chatgpt_conversation_log_days_to_keep', '30'));
    // DIAG - Log the output choice
    // back_trace( 'NOTICE', 'chatbot_chatgpt_conversation_log_days_to_keep' . $output_choice);
    ?>
    <select id="chatbot_chatgpt_conversation_log_days_to_keep" name="chatbot_chatgpt_conversation_log_days_to_keep">
        <option value="<?php echo esc_attr( '1' ); ?>" <?php selected( $output_choice, '7' ); ?>><?php echo esc_html( '1' ); ?></option>
        <option value="<?php echo esc_attr( '7' ); ?>" <?php selected( $output_choice, '7' ); ?>><?php echo esc_html( '7' ); ?></option>
        <option value="<?php echo esc_attr( '30' ); ?>" <?php selected( $output_choice, '30' ); ?>><?php echo esc_html( '30' ); ?></option>
        <option value="<?php echo esc_attr( '60' ); ?>" <?php selected( $output_choice, '60' ); ?>><?php echo esc_html( '60' ); ?></option>
        <option value="<?php echo esc_attr( '90' ); ?>" <?php selected( $output_choice, '90' ); ?>><?php echo esc_html( '90' ); ?></option>
        <option value="<?php echo esc_attr( '180' ); ?>" <?php selected( $output_choice, '180' ); ?>><?php echo esc_html( '180' ); ?></option>
        <option value="<?php echo esc_attr( '365' ); ?>" <?php selected( $output_choice, '365' ); ?>><?php echo esc_html( '365' ); ?></option>
    </select>
    <?php
}

// Chatbot Simple Chart - Ver 1.6.3
function generate_gd_bar_chart($labels, $data, $colors, $name) {
    // Create an image
    $width = 500;
    $height = 300;
    $image = imagecreatetruecolor($width, $height);

    // Allocate colors
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $light_blue = imagecolorallocate($image, 173, 216, 230); // Light Blue color

    // Fill the background
    imagefill($image, 0, 0, $white);

    // Add title
    $title = "Visitor Interactions";
    $font = 5;
    $title_x = ($width - imagefontwidth($font) * strlen($title)) / 2;
    $title_y = 5;
    imagestring($image, $font, $title_x, $title_y, $title, $black);

    // Calculate number of bars and bar width
    $bar_count = count($data);
    // $bar_width = (int)($width / ($bar_count * 2));
    $bar_width = round($width / ($bar_count * 2));

    // Offset for the chart
    $offset_x = 25;
    $offset_y = 25;
    $top_padding = 5;

    // Bottom line
    imageline($image, 0, $height - $offset_y, $width, $height - $offset_y, $black);

    // Font size for data and labels
    $font_size = 8;

    // Draw bars
    $chart_title_height = 30; // adjust this to the height of your chart title
    for ($i = 0; $i < $bar_count; $i++) {
        $bar_height = (int)(($data[$i] * ($height - $offset_y - $top_padding - $chart_title_height)) / max($data));
        $x1 = $i * $bar_width * 2 + $offset_x;
        $y1 = $height - $bar_height - $offset_y + $top_padding;
        $x2 = ($i * $bar_width * 2) + $bar_width + $offset_x;
        $y2 = $height - $offset_y;

        // Draw a bar
        imagefilledrectangle($image, $x1, $y1, $x2, $y2, $light_blue);

        // Draw data and labels
        $center_x = $x1 + ($bar_width / 2);
        $data_value_x = $center_x - (imagefontwidth($font_size) * strlen($data[$i]) / 2);
        $data_value_y = $y1 - 15;
        $data_value_y = max($data_value_y, 0);

        // Draw a bar
        imagefilledrectangle($image, $x1, $y1, $x2, $y2, $light_blue);

        // Draw data and labels
        $center_x = round($x1 + ($bar_width / 2));

        $data_value_x = $center_x - (imagefontwidth(round($font_size)) * strlen($data[$i]) / 2);
        $label_x = $center_x - (imagefontwidth(round($font_size)) * strlen($labels[$i]) / 2);

        $data_value_y = $y1 - 5; // Moves the counts up or down
        $data_value_y = max($data_value_y, 0);

        // Fix: Explicitly cast to int
        $data_value_x = (int)($data_value_x);
        $data_value_y = (int)($data_value_y);

        // https://fonts.google.com/specimen/Roboto - Ver 1.6.7
        $fontFile = plugin_dir_path(__FILE__) . 'assets/fonts/roboto/Roboto-Black.ttf';

        imagettftext($image, $font_size, 0, $data_value_x, $data_value_y, $black, $fontFile, $data[$i]);

        $label_x = $center_x - ($font_size * strlen($labels[$i]) / 2) + 7; // Moves the dates left or right
        $label_y = $height - $offset_y + 15; // Moves the dates up or down

        imagettftext($image, $font_size, 0, $label_x, $label_y, $black, $fontFile, $labels[$i]);

    }

    // Save the image
    $img_path = plugin_dir_path(__FILE__) . 'assets/images/' . $name . '.png';
    imagepng($image, $img_path);

    // Free memory
    imagedestroy($image);

    return $img_path;
}


// Chatbot Charts - Ver 1.6.3
function chatbot_chatgpt_simple_chart_shortcode_function( $atts ) {

    // Check is GD Library is installed - Ver 1.6.3
    if (!extension_loaded('gd')) {
        // GD Library is installed and loaded
        // DIAG - Log the output choice
        // back_trace( 'NOTICE', 'GD Library is installed and loaded.');
        chatbot_chatgpt_general_admin_notice('Chatbot requires the GD Library to function correctly, but it is not installed or enabled on your server. Please install or enable the GD Library.');
        // DIAG - Log the output choice
        // back_trace( 'NOTICE', 'GD Library is not installed! No chart will be displayed.');
        // Disable the shortcode functionality
        return;
    }

    // Retrieve the reporting period
    $reporting_period = esc_attr(get_option('chatbot_chatgpt_reporting_period'));

    // Parsing shortcode attributes
    $a = shortcode_atts( array(
        'name' => 'visitorsChart_' . rand(100, 999),
        'type' => 'bar',
        'labels' => 'label',
        ), $atts );

    // Updated Ver 2.4.8: Uses Supabase for interaction data
    if(isset($atts['from_database']) && $atts['from_database'] == 'true') {

        // Get the reporting period from the options
        $reporting_period = esc_attr(get_option('chatbot_chatgpt_reporting_period'));

        // Calculate the start date based on the reporting period
        if($reporting_period === 'Daily') {
            $start_date = date('Y-m-d', strtotime("-7 days"));
        } elseif($reporting_period === 'Monthly') {
            $start_date = date('Y-m-01', strtotime("-3 months"));
        } else {
            $start_date = date('Y-01-01', strtotime("-3 years"));
        }
        $end_date = date('Y-m-d');

        // Get data from Supabase
        if (function_exists('chatbot_supabase_get_interaction_counts')) {
            $results = chatbot_supabase_get_interaction_counts($start_date, $end_date);

            if(!empty($results)) {
                $labels = [];
                $data = [];
                foreach ($results as $result) {
                    // Format the date based on reporting period
                    if($reporting_period === 'Daily') {
                        $labels[] = date('m-d', strtotime($result['date']));
                    } elseif($reporting_period === 'Monthly') {
                        $labels[] = date('Y-m', strtotime($result['date']));
                    } else {
                        $labels[] = date('Y', strtotime($result['date']));
                    }
                    $data[] = $result['count'];
                }

                $a['labels'] = $labels;
                $atts['data'] = $data;
            }
        }
    }

    if (empty( $a['labels']) || empty($atts['data'])) {
        // return '<p>You need to specify both the labels and data for the chart to work.</p>';
        return '<p>No data to chart at this time. Plesae visit again later.</p>';
    }

    // Generate the chart
    $img_path = generate_gd_bar_chart($a['labels'], $atts['data'], $atts['color'] ?? null, $a['name']);
    $img_url = plugin_dir_url(__FILE__) . 'assets/images/' . $a['name'] . '.png';

    wp_schedule_single_event(time() + 60, 'chatbot_chatgpt_delete_chart', array($img_path)); // 60 seconds delay

    return '<img src="' . $img_url . '" alt="Bar Chart">';
}
// TEMPORARILY REMOVED AS SOME USERS ARE EXPERIENCING ISSUES WITH THE CHARTS - Ver 1.7.8
// Add shortcode
// add_shortcode('chatbot_chatgpt_simple_chart', 'chatbot_chatgpt_simple_chart_shortcode_function');
// add_shortcode('chatbot_simple_chart', 'chatbot_chatgpt_simple_chart_shortcode_function');


// Clean up ../image subdirectory - Ver 1.6.3
function chatbot_chatgpt_delete_chart() {
    $img_dir_path = plugin_dir_path(__FILE__) . 'assets/images/'; // Replace with your actual directory path
    $png_files = glob($img_dir_path . '*.png'); // Search for .png files in the directory

    foreach ($png_files as $png_file) {
        unlink($png_file); // Delete each .png file
    }
}
add_action('chatbot_chatgpt_delete_chart', 'chatbot_chatgpt_delete_chart');

// Return Interactions data in a table - Ver 1.7.8
// Updated Ver 2.4.8: Uses Supabase only
function chatbot_chatgpt_interactions_table() {

    // Use Supabase for interaction data
    if (function_exists('chatbot_supabase_get_interaction_counts')) {
        // Calculate date range (last 30 days)
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $interactions = chatbot_supabase_get_interaction_counts($start_date, $end_date);
        if (!empty($interactions)) {
            $output = '<table class="widefat striped">';
            $output .= '<thead><tr><th>Date</th><th>Count</th></tr></thead><tbody>';
            foreach ($interactions as $row) {
                $output .= '<tr><td>' . esc_html($row['date']) . '</td><td>' . esc_html($row['count']) . '</td></tr>';
            }
            $output .= '</tbody></table>';
            return $output;
        }
    }
    return '<p>No interaction data available.</p>';

}

// Count the number of conversations stored - Ver 1.7.6
// Updated Ver 2.4.8: Uses Supabase only
function chatbot_chatgpt_count_conversations() {

    // Use Supabase for conversation count
    if (function_exists('chatbot_supabase_get_recent_conversations')) {
        $conversations = chatbot_supabase_get_recent_conversations(365, 10000);
        return is_array($conversations) ? count($conversations) : 0;
    }
    return 0;

}

// Calculated size of the conversations stored - Ver 1.7.6
// Updated Ver 2.4.8: Uses Supabase only
function chatbot_chatgpt_size_conversations() {

    // Supabase doesn't expose table size easily - return N/A
    return 'N/A (Supabase)';

}

// Total Prompt Tokens, Completion Tokens, and Total Tokens - Ver 1.8.5
// Updated Ver 2.4.8: Uses Supabase only
function chatbot_chatgpt_total_tokens() {

    // Token usage tracking is stored in Supabase
    return '<p>Token usage tracking is stored in Supabase.</p>';

}

function chatbot_chatgpt_download_interactions_data() {

    // Export data from the chatbot_chatgpt_interactions table to a csv file
    chatbot_chatgpt_export_data('chatbot_chatgpt_interactions', 'Chatbot-ChatGPT-Interactions');

}

function chatbot_chatgpt_download_conversation_data() {

    // Export data from the chatbot_chatgpt_conversation_log table to a csv file
    chatbot_chatgpt_export_data('chatbot_chatgpt_conversation_log', 'Chatbot-ChatGPT-Conversation Logs');
    
}

function chatbot_chatgpt_download_token_usage_data() {

    // Export data from the chatbot_chatgpt_conversation_log table to a csv file
    chatbot_chatgpt_export_data('chatbot_chatgpt_conversation_log', 'Chatbot-ChatGPT-Token Usage');

}

// Download the conversation data - Ver 1.7.6
// Updated Ver 2.4.8: Uses Supabase for data export
function chatbot_chatgpt_export_data( $t_table_name, $t_file_name ) {

    global $chatbot_chatgpt_plugin_dir_path;

    // Export data from Supabase
    $results = array();

    if ($t_table_name === 'chatbot_chatgpt_conversation_log') {
        // Get conversations from Supabase
        if (function_exists('chatbot_supabase_get_recent_conversations')) {
            $conversations = chatbot_supabase_get_recent_conversations(365, 10000);
            if (!empty($conversations)) {
                // Filter for token usage if needed
                if ($t_file_name === 'Chatbot-ChatGPT-Token Usage') {
                    foreach ($conversations as $conv) {
                        if (in_array($conv['user_type'], ['Prompt Tokens', 'Completion Tokens', 'Total Tokens'])) {
                            $results[] = array(
                                'id' => $conv['id'],
                                'session_id' => $conv['session_id'],
                                'user_id' => $conv['user_id'],
                                'interaction_time' => $conv['interaction_time'],
                                'user_type' => $conv['user_type'],
                                'message_text' => $conv['message_text']
                            );
                        }
                    }
                } else {
                    $results = $conversations;
                }
            }
        }
    } elseif ($t_table_name === 'chatbot_chatgpt_interactions') {
        // Get interactions from Supabase
        if (function_exists('chatbot_supabase_get_interaction_counts')) {
            $start_date = date('Y-m-d', strtotime('-365 days'));
            $end_date = date('Y-m-d');
            $results = chatbot_supabase_get_interaction_counts($start_date, $end_date);
        }
    }

    // Check for empty results
    if (empty($results)) {
        $message = __( 'No data in the file. Please enable conversation and interaction logging if currently off.', 'chatbot-chatgpt' );
        set_transient('chatbot_chatgpt_admin_error', $message, 60); // Expires in 60 seconds
        wp_safe_redirect(admin_url('options-general.php?page=chatbot-chatgpt&tab=reporting')); // Redirect to your settings page
        exit;
    }

    // Ask user where to save the file
    $filename = $t_file_name . '-' . date('Y-m-d') . '.csv';
    // Replace spaces with - in the filename
    $filename = str_replace(' ', '-', $filename);
    $results_dir_path = $chatbot_chatgpt_plugin_dir_path . 'results/';

    // Ensure the directory exists or attempt to create it
    if (!create_directory_and_index_file($results_dir_path)) {
        // Error handling, e.g., log the error or handle the failure appropriately
        // back_trace( 'ERROR', 'Failed to create directory.');
        return;
    }

    $results_csv_file = $results_dir_path . $filename;
    
    // Open file for writing
    $file = fopen($results_csv_file, 'w');

    // Check if file opened successfully
    if ($file === false) {
        $message = __( 'Error opening file for writing. Please try again.', 'chatbot-chatgpt' );
        set_transient('chatbot_chatgpt_admin_error', $message, 60); // Expires in 60 seconds
        wp_safe_redirect(admin_url('options-general.php?page=chatbot-chatgpt&tab=reporting')); // Redirect to your settings page
        exit;
    }

    // Write headers to file
    if (isset($results[0]) && is_array($results[0])) {
        $keys = array_keys($results[0]);
        fputcsv($file, $keys);
    } else {
        $class = 'notice notice-error';
        $message = __( 'Chatbot No data in the file. Please enable conversation logging if currently off.', 'chatbot-chatgpt' );
        // printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
        chatbot_chatgpt_general_admin_notice($message);
        return;
    }

    // Write results to file
    foreach ($results as $result) {
        $result = array_map(function($value) {
            return $value !== null ? mb_convert_encoding($value, 'UTF-8', 'auto') : '';
        }, $result);
        fputcsv($file, $result);
    }

    // Close the file
    fclose($file);

    // Exit early if the file doesn't exist
    if (!file_exists($results_csv_file)) {
        $class = 'notice notice-error';
        $message = __( 'File not found!' . $results_csv_file, 'chatbot-chatgpt' );
        // printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
        chatbot_chatgpt_general_admin_notice($message);
        return;
    }

    // DIAG - Diagnostics - Ver 2.0.2.1
    // back_trace( 'NOTICE', 'File path: ' . $results_csv_file);

    if (!file_exists($results_csv_file)) {
        // back_trace( 'ERROR', 'File does not exist: ' . $results_csv_file);
        return;
    }
    
    if (!is_readable($results_csv_file)) {
        // back_trace( 'ERROR', 'File is not readable ' . $results_csv_file);
        return;
    }
    
    $csv_data = file_get_contents(realpath($results_csv_file));
    if ($csv_data === false) {
        $class = 'notice notice-error';
        $message = __( 'Error reading file', 'chatbot-chatgpt' );
        chatbot_chatgpt_general_admin_notice($message);
        return;
    }
    
    if (!is_writable($results_csv_file)) {
        // back_trace( 'ERROR', 'File is not writable: ' . $results_csv_file);
        return;
    }  
    
    // Deliver the file for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=' . $filename);
    echo $csv_data;

    // Delete the file
    unlink($results_csv_file);
    exit;

}
add_action('admin_post_chatbot_chatgpt_download_conversation_data', 'chatbot_chatgpt_download_conversation_data');
add_action('admin_post_chatbot_chatgpt_download_interactions_data', 'chatbot_chatgpt_download_interactions_data');
add_action('admin_post_chatbot_chatgpt_download_token_usage_data', 'chatbot_chatgpt_download_token_usage_data');

// Gap Analysis Section Callback - Ver 2.4.2
function chatbot_chatgpt_gap_analysis_section_callback($args) {
    ?>
    <p>Gap Analysis identifies questions that users ask but are not well-answered by the FAQ database. Use this data to improve your FAQ coverage.</p>
    <?php
}

// Gap Analysis Callback - Ver 2.4.2
function chatbot_chatgpt_gap_analysis_callback($selected_period = null) {
    error_log('🔍 GAP ANALYSIS CALLBACK CALLED');

    // Use the main analytics period filter (passed from analytics page)
    if (!$selected_period) {
        $selected_period = get_transient('chatbot_analytics_selected_period');
        if (!$selected_period) {
            $selected_period = 'Week';
        }
    }

    // Map period names to days
    $period_to_days = [
        'Today' => 1,
        'Week' => 7,
        'Month' => 30,
        'Quarter' => 90,
        'Year' => 365
    ];
    $days = $period_to_days[$selected_period] ?? 7;

    // Get gap analysis data
    $data = chatbot_get_gap_analysis_data($days);

    error_log('Gap data received: ' . print_r($data, true));

    $total_gaps = $data['total_gaps'];
    $unresolved_gaps = $data['unresolved_gaps'];
    $unclustered_gaps = $data['unclustered_gaps'] ?? $unresolved_gaps;
    $active_clusters = $data['active_clusters'];
    $top_individual_gaps = $data['top_individual_gaps'];

    error_log("Total gaps: $total_gaps, Clusters: " . count($active_clusters));

    ?>
    <div>
        <!-- How it works -->
        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 12px 0; font-size: 15px; color: #374151;">How Gap Analysis Works</h3>
            <ol style="margin: 0; padding-left: 20px; font-size: 13px; color: #4b5563; line-height: 1.8;">
                <li>Users ask questions that the chatbot can't answer confidently (< 60% match)</li>
                <li>These questions are logged as "gap questions"</li>
                <li>AI analyzes gap questions and groups similar ones into clusters</li>
                <li>For each cluster, AI suggests either improving an existing FAQ or creating a new one</li>
                <li>You review and edit the suggestions, then add to knowledge base with one click</li>
            </ol>

            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                <h4 style="margin: 0 0 8px 0; font-size: 13px; color: #374151;">Analysis Options:</h4>
                <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #4b5563; line-height: 1.6;">
                    <li><strong>Manual:</strong> Click "Run Analysis Now" anytime</li>
                    <li><strong>Auto:</strong> Enable auto-analysis to run automatically when 30+ questions accumulate</li>
                </ul>
            </div>
        </div>

        <!-- Run Analysis Button -->
        <?php $auto_analysis_enabled = get_option('chatbot_gap_auto_analysis_enabled', 'off'); ?>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div style="font-size: 12px; color: #6b7280;">
                <span>Gap questions: <strong><?php echo $unclustered_gaps; ?></strong> waiting</span>
                <span style="margin: 0 10px;">|</span>
                <span>Auto-analysis:
                    <label style="cursor: pointer; margin-left: 5px;">
                        <input type="checkbox" id="auto_analysis_toggle" <?php echo $auto_analysis_enabled === 'on' ? 'checked' : ''; ?> style="cursor: pointer;">
                        <strong id="auto_analysis_status" style="color: <?php echo $auto_analysis_enabled === 'on' ? '#10b981' : '#6b7280'; ?>;">
                            <?php echo $auto_analysis_enabled === 'on' ? 'ON' : 'OFF'; ?>
                        </strong>
                    </label>
                    <span style="font-size: 11px; color: #9ca3af;">(runs when 30+ questions)</span>
                </span>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 12px; color: #6b7280;">Manually trigger AI analysis</span>
                <button type="button" id="run_gap_analysis_now" class="button button-primary" style="padding: 6px 12px; font-size: 13px;">
                    Run Analysis Now
                </button>
            </div>
        </div>

        <?php if (!empty($active_clusters)) : ?>
        <!-- AI-Suggested FAQ Additions -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0; font-size: 16px; color: #111827;">AI-Suggested FAQ Additions (<?php echo count($active_clusters); ?>)</h3>
                <!-- Ver 2.5.0: Pagination controls -->
                <div id="gap-cluster-pagination" style="display: flex; align-items: center; gap: 8px;">
                    <button type="button" id="gap-cluster-prev" class="button button-small" disabled>&laquo; Prev</button>
                    <span id="gap-cluster-page-info" style="font-size: 12px; color: #6b7280; min-width: 80px; text-align: center;">Page 1 of 1</span>
                    <button type="button" id="gap-cluster-next" class="button button-small">Next &raquo;</button>
                </div>
            </div>
            <p style="margin: 0 0 20px 0; font-size: 13px; color: #6b7280; padding: 12px; background-color: #f9fafb; border-left: 3px solid #3b82f6; border-radius: 4px;">
                <b>Human Review Required:</b> AI has analyzed similar questions and suggested FAQ entries below. Review and manually add to your knowledge base.
            </p>

            <div id="gap-cluster-container">
            <?php $cluster_index = 0; foreach ($active_clusters as $cluster) : $cluster_index++;
                // Handle both JSON strings and arrays (Supabase returns arrays directly)
                $suggested_faq = is_string($cluster['suggested_faq']) ? json_decode($cluster['suggested_faq'], true) : $cluster['suggested_faq'];
                $sample_questions = is_string($cluster['sample_questions']) ? json_decode($cluster['sample_questions'], true) : $cluster['sample_questions'];
                $sample_questions = is_array($sample_questions) ? $sample_questions : [];
                // Ver 2.5.0: Parse sample_contexts for follow-up questions
                $sample_contexts = isset($cluster['sample_contexts']) ?
                    (is_string($cluster['sample_contexts']) ? json_decode($cluster['sample_contexts'], true) : $cluster['sample_contexts']) : [];
                $sample_contexts = is_array($sample_contexts) ? $sample_contexts : [];
                $priority_label = $cluster['priority_score'] >= 100 ? 'High' : ($cluster['priority_score'] >= 50 ? 'Medium' : 'Low');
                $action_type = $cluster['action_type'] ?? 'create';
                $is_improve = ($action_type === 'improve');
                $border_color = $is_improve ? '#f59e0b' : '#3b82f6';
                $action_label = $is_improve ? 'Improve Existing FAQ' : 'Create New FAQ';
            ?>
            <div class="gap-cluster-item" data-cluster-index="<?php echo $cluster_index; ?>" style="background: #f9fafb; border-left: 4px solid <?php echo $border_color; ?>; border: 1px solid #d1d5db; border-radius: 6px; padding: 16px; margin-bottom: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                    <div style="flex: 1;">
                        <div style="font-size: 10px; font-weight: 700; color: <?php echo $border_color; ?>; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <?php echo $action_label; ?>
                        </div>
                        <h4 style="margin: 0 0 4px 0; color: #111827; font-size: 15px;"><?php echo esc_html($cluster['cluster_name']); ?></h4>
                        <p style="margin: 0; font-size: 12px; color: #6b7280;"><?php echo esc_html($cluster['cluster_description']); ?></p>
                    </div>
                    <div style="margin-left: 15px;">
                        <span style="background-color: #fff; border: 1px solid #d1d5db; padding: 4px 10px; border-radius: 4px; font-size: 11px; color: #374151;">
                            <?php echo $priority_label; ?> Priority • Asked <?php echo $cluster['question_count']; ?>x
                        </span>
                    </div>
                </div>

                <!-- Sample Questions (show 1 with context if available, otherwise show up to 3) -->
                <div style="margin-bottom: 12px;">
                    <div style="font-size: 11px; font-weight: 600; color: #6b7280; margin-bottom: 6px;">Sample Questions:</div>
                    <?php
                    // Find a question with context to highlight, or show first few without
                    $question_with_context_idx = null;
                    foreach ($sample_contexts as $idx => $ctx) {
                        if (!empty($ctx)) {
                            $question_with_context_idx = $idx;
                            break;
                        }
                    }
                    ?>
                    <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #374151; line-height: 1.5;">
                        <?php if ($question_with_context_idx !== null && isset($sample_questions[$question_with_context_idx])) :
                            // Show the question with context
                            $question = $sample_questions[$question_with_context_idx];
                            $context = $sample_contexts[$question_with_context_idx];
                        ?>
                        <li style="margin-bottom: 6px;">
                            <?php echo esc_html($question); ?>
                            <div style="font-size: 11px; color: #9ca3af; margin-top: 4px; padding: 6px 10px; background: #f3f4f6; border-radius: 4px;">
                                <span style="font-weight: 500; color: #6b7280;">Previous conversation:</span><br>
                                <?php echo esc_html(substr($context, 0, 200)); ?><?php echo strlen($context) > 200 ? '...' : ''; ?>
                            </div>
                        </li>
                        <?php else :
                            // No context available, show up to 3 questions
                            foreach (array_slice($sample_questions, 0, 3) as $question) : ?>
                        <li style="margin-bottom: 3px;"><?php echo esc_html($question); ?></li>
                        <?php endforeach;
                        endif; ?>
                    </ul>
                </div>

                <?php if ($is_improve) :
                    // Get existing FAQ details and suggested new answer
                    $existing_faq = $suggested_faq;
                    $suggested_answer = $cluster['suggested_answer'] ?? '';
                    $existing_faq_id = $cluster['existing_faq_id'] ?? '';
                ?>
                <!-- Improve Existing FAQ -->
                <div style="background: #fffbeb; padding: 12px; border-radius: 4px; margin-bottom: 12px; border: 1px solid #f59e0b;">
                    <!-- Question (read-only) -->
                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 11px; font-weight: 600; color: #92400e; margin-bottom: 4px;">QUESTION (FAQ ID: <?php echo esc_html($existing_faq_id); ?>)</div>
                        <div style="font-size: 14px; color: #111827; font-weight: 500;">
                            <?php echo esc_html($existing_faq['question'] ?? 'N/A'); ?>
                        </div>
                    </div>

                    <!-- Current Answer (read-only) -->
                    <div style="background: white; padding: 12px; border-radius: 4px; margin-bottom: 12px; border-left: 3px solid #9ca3af;">
                        <div style="font-size: 11px; font-weight: 600; color: #6b7280; margin-bottom: 6px;">CURRENT ANSWER</div>
                        <div style="font-size: 13px; color: #374151; line-height: 1.5;">
                            <?php echo esc_html($existing_faq['answer'] ?? 'N/A'); ?>
                        </div>
                    </div>

                    <!-- Editable New Answer -->
                    <div style="background: #fef3c7; padding: 12px; border-radius: 4px; border-left: 3px solid #f59e0b;">
                        <div style="font-size: 11px; font-weight: 600; color: #92400e; margin-bottom: 6px;">NEW ANSWER (editable)</div>
                        <textarea id="improve_answer_<?php echo $cluster['id']; ?>"
                            style="width: 100%; min-height: 100px; padding: 10px; border: 1px solid #d97706; border-radius: 4px; font-size: 13px; line-height: 1.5; resize: vertical;"
                        ><?php echo esc_textarea($suggested_answer ?: $existing_faq['answer'] ?? ''); ?></textarea>
                    </div>

                    <!-- Apply Button -->
                    <div style="margin-top: 12px; text-align: right;">
                        <button type="button" onclick="chatbotApplyImprovedFaq(<?php echo $cluster['id']; ?>, '<?php echo esc_js($existing_faq_id); ?>')"
                            class="button button-primary" style="background: #f59e0b; border-color: #d97706;">
                            Apply to Knowledge Base
                        </button>
                    </div>
                </div>
                <?php else : ?>
                <!-- Create New FAQ -->
                <div style="background: #ecfdf5; padding: 12px; border-radius: 4px; margin-bottom: 12px; border: 1px solid #10b981;">
                    <!-- Category (editable) -->
                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 11px; font-weight: 600; color: #059669; margin-bottom: 4px;">CATEGORY (editable)</div>
                        <input type="text" id="new_faq_category_<?php echo $cluster['id']; ?>"
                            value="<?php echo esc_attr($suggested_faq['category'] ?? ''); ?>"
                            placeholder="e.g., Services, Pricing, Support"
                            style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #10b981; border-radius: 4px; font-size: 13px;">
                    </div>

                    <!-- Question (editable) -->
                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 11px; font-weight: 600; color: #059669; margin-bottom: 4px;">QUESTION (editable)</div>
                        <input type="text" id="new_faq_question_<?php echo $cluster['id']; ?>"
                            value="<?php echo esc_attr($suggested_faq['question'] ?? ''); ?>"
                            placeholder="Enter the FAQ question"
                            style="width: 100%; padding: 8px; border: 1px solid #10b981; border-radius: 4px; font-size: 14px; font-weight: 500;">
                    </div>

                    <!-- Answer (editable) -->
                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 11px; font-weight: 600; color: #059669; margin-bottom: 4px;">ANSWER (editable)</div>
                        <textarea id="new_faq_answer_<?php echo $cluster['id']; ?>"
                            placeholder="Enter the FAQ answer"
                            style="width: 100%; min-height: 100px; padding: 10px; border: 1px solid #10b981; border-radius: 4px; font-size: 13px; line-height: 1.5; resize: vertical;"
                        ><?php echo esc_textarea($suggested_faq['answer'] ?? ''); ?></textarea>
                    </div>

                    <!-- Add Button -->
                    <div style="text-align: right;">
                        <button type="button" onclick="chatbotAddNewFaq(<?php echo $cluster['id']; ?>)"
                            class="button button-primary" style="background: #10b981; border-color: #059669;">
                            Add to Knowledge Base
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Dismiss Button -->
                <div style="text-align: right; margin-top: 10px;">
                    <button type="button" onclick="chatbotDismissCluster(<?php echo $cluster['id']; ?>)" class="button" style="color: #6b7280;">
                        Dismiss
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            </div><!-- end gap-cluster-container -->

            <!-- Ver 2.5.0: Gap cluster pagination JavaScript -->
            <script>
            (function() {
                const perPage = 5;
                let currentPage = 1;
                const items = document.querySelectorAll('.gap-cluster-item');
                const totalItems = items.length;
                const totalPages = Math.ceil(totalItems / perPage);

                function updateGapPagination() {
                    // Hide all items
                    items.forEach(item => item.style.display = 'none');

                    // Show items for current page
                    const startIdx = (currentPage - 1) * perPage;
                    const endIdx = Math.min(startIdx + perPage, totalItems);

                    for (let i = startIdx; i < endIdx; i++) {
                        items[i].style.display = 'block';
                    }

                    // Update page info
                    document.getElementById('gap-cluster-page-info').textContent = 'Page ' + currentPage + ' of ' + totalPages;

                    // Update button states
                    document.getElementById('gap-cluster-prev').disabled = currentPage <= 1;
                    document.getElementById('gap-cluster-next').disabled = currentPage >= totalPages;
                }

                document.getElementById('gap-cluster-prev').addEventListener('click', function() {
                    if (currentPage > 1) {
                        currentPage--;
                        updateGapPagination();
                    }
                });

                document.getElementById('gap-cluster-next').addEventListener('click', function() {
                    if (currentPage < totalPages) {
                        currentPage++;
                        updateGapPagination();
                    }
                });

                // Initialize
                if (totalItems > 0) {
                    updateGapPagination();
                }
            })();
            </script>
        </div>
        <?php endif; ?>


    </div>

    <script>
    // Modal function for branded dialogs (gap analysis section)
    function chatbotShowModal(options) {
        const overlay = document.createElement('div');
        overlay.id = 'chatbot-modal-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:100000;display:flex;align-items:center;justify-content:center;';

        const borderColor = options.isError ? '#dc3232' : '#2271b1';
        const modal = document.createElement('div');
        modal.style.cssText = 'background:white;border-radius:4px;box-shadow:0 3px 6px rgba(0,0,0,0.3);max-width:400px;width:90%;border-top:4px solid ' + borderColor + ';';

        modal.innerHTML = `
            <div style="padding:20px 20px 0;">
                <h2 style="margin:0 0 12px;font-size:18px;font-weight:600;color:#1d2327;">${options.title}</h2>
                <p style="margin:0;color:#50575e;font-size:14px;line-height:1.5;">${options.message}</p>
            </div>
            <div style="padding:16px 20px;display:flex;justify-content:flex-end;gap:10px;margin-top:20px;border-top:1px solid #dcdcde;">
                ${options.hideCancel ? '' : `<button id="chatbot-modal-cancel" class="button" style="padding:6px 12px;">${options.cancelText || 'Cancel'}</button>`}
                <button id="chatbot-modal-confirm" class="button button-primary" style="padding:6px 12px;">${options.confirmText || 'OK'}</button>
            </div>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        document.getElementById('chatbot-modal-confirm').onclick = function() {
            document.body.removeChild(overlay);
            if (options.onConfirm) options.onConfirm();
        };

        const cancelBtn = document.getElementById('chatbot-modal-cancel');
        if (cancelBtn) {
            cancelBtn.onclick = function() {
                document.body.removeChild(overlay);
                if (options.onCancel) options.onCancel();
            };
        }

        overlay.onclick = function(e) {
            if (e.target === overlay && !options.hideCancel) {
                document.body.removeChild(overlay);
                if (options.onCancel) options.onCancel();
            }
        };
    }

    function chatbotResolveCluster(clusterId) {
        chatbotShowModal({
            title: 'Mark as Resolved',
            message: 'Mark this cluster as resolved (FAQ created)?',
            confirmText: 'Yes, Resolve',
            onConfirm: function() {
                jQuery.post(ajaxurl, {
                    action: 'chatbot_resolve_cluster',
                    cluster_id: clusterId,
                    nonce: '<?php echo wp_create_nonce('chatbot_gap_analysis'); ?>'
                }, function(response) {
                    if (response.success) {
                        chatbotShowModal({
                            title: 'Success',
                            message: 'Cluster marked as resolved!',
                            hideCancel: true,
                            onConfirm: function() {
                                location.reload();
                            }
                        });
                    } else {
                        chatbotShowModal({
                            title: 'Error',
                            message: response.data || 'Unknown error',
                            isError: true,
                            hideCancel: true
                        });
                    }
                });
            }
        });
    }

    function chatbotDismissCluster(clusterId) {
        chatbotShowModal({
            title: 'Dismiss Cluster',
            message: 'Dismiss this cluster? It will be removed from the queue.',
            confirmText: 'Yes, Dismiss',
            onConfirm: function() {
                jQuery.post(ajaxurl, {
                    action: 'chatbot_dismiss_cluster',
                    cluster_id: clusterId,
                    nonce: '<?php echo wp_create_nonce('chatbot_gap_analysis'); ?>'
                }, function(response) {
                    if (response.success) {
                        chatbotShowModal({
                            title: 'Success',
                            message: 'Cluster dismissed!',
                            hideCancel: true,
                            onConfirm: function() {
                                location.reload();
                            }
                        });
                    } else {
                        chatbotShowModal({
                            title: 'Error',
                            message: response.data || 'Unknown error',
                            isError: true,
                            hideCancel: true
                        });
                    }
                });
            }
        });
    }

    // Apply improved answer to existing FAQ
    function chatbotApplyImprovedFaq(clusterId, faqId) {
        const newAnswer = document.getElementById('improve_answer_' + clusterId).value.trim();

        if (!newAnswer) {
            chatbotShowModal({
                title: 'Missing Field',
                message: 'Please enter an answer.',
                isError: true,
                hideCancel: true
            });
            return;
        }

        chatbotShowModal({
            title: 'Update FAQ',
            message: 'Update FAQ ' + faqId + ' with this new answer?',
            confirmText: 'Yes, Update',
            onConfirm: function() {
                jQuery.post(ajaxurl, {
                    action: 'chatbot_apply_improved_faq',
                    cluster_id: clusterId,
                    faq_id: faqId,
                    new_answer: newAnswer,
                    nonce: '<?php echo wp_create_nonce('chatbot_gap_analysis'); ?>'
                }, function(response) {
                    if (response.success) {
                        chatbotShowModal({
                            title: 'Success',
                            message: 'FAQ updated successfully!',
                            hideCancel: true,
                            onConfirm: function() {
                                location.reload();
                            }
                        });
                    } else {
                        chatbotShowModal({
                            title: 'Error',
                            message: response.data || 'Unknown error',
                            isError: true,
                            hideCancel: true
                        });
                    }
                });
            }
        });
    }

    // Add new FAQ to knowledge base
    function chatbotAddNewFaq(clusterId) {
        const category = document.getElementById('new_faq_category_' + clusterId).value.trim();
        const question = document.getElementById('new_faq_question_' + clusterId).value.trim();
        const answer = document.getElementById('new_faq_answer_' + clusterId).value.trim();

        if (!question) {
            chatbotShowModal({
                title: 'Missing Field',
                message: 'Please enter a question.',
                isError: true,
                hideCancel: true
            });
            return;
        }
        if (!answer) {
            chatbotShowModal({
                title: 'Missing Field',
                message: 'Please enter an answer.',
                isError: true,
                hideCancel: true
            });
            return;
        }
        if (!category) {
            chatbotShowModal({
                title: 'Missing Field',
                message: 'Please enter a category.',
                isError: true,
                hideCancel: true
            });
            return;
        }

        chatbotShowModal({
            title: 'Add New FAQ',
            message: 'Add this new FAQ to the knowledge base?',
            confirmText: 'Yes, Add FAQ',
            onConfirm: function() {
                jQuery.post(ajaxurl, {
                    action: 'chatbot_add_new_faq',
                    cluster_id: clusterId,
                    category: category,
                    question: question,
                    answer: answer,
                    nonce: '<?php echo wp_create_nonce('chatbot_gap_analysis'); ?>'
                }, function(response) {
                    if (response.success) {
                        chatbotShowModal({
                            title: 'Success',
                            message: 'FAQ added successfully! ID: ' + (response.data.faq_id || 'new'),
                            hideCancel: true,
                            onConfirm: function() {
                                location.reload();
                            }
                        });
                    } else {
                        chatbotShowModal({
                            title: 'Error',
                            message: response.data || 'Unknown error',
                            isError: true,
                            hideCancel: true
                        });
                    }
                });
            }
        });
    }

    // Handle Run Analysis Now button
    jQuery(document).ready(function($) {
        $('#run_gap_analysis_now').on('click', function() {
            const $btn = $(this);
            const originalText = $btn.text();

            // Show WordPress-style modal
            chatbotShowModal({
                title: 'Run AI Gap Analysis',
                message: 'This will analyze all unresolved gap questions and generate FAQ suggestions. This may take 30-60 seconds.',
                confirmText: 'Run Analysis',
                cancelText: 'Cancel',
                onConfirm: function() {
                    $btn.prop('disabled', true).text('Running...');

                    $.post(ajaxurl, {
                        action: 'chatbot_run_gap_analysis_manual',
                        nonce: '<?php echo wp_create_nonce('chatbot_gap_analysis'); ?>'
                    }, function(response) {
                        if (response.success) {
                            chatbotShowModal({
                                title: 'Analysis Complete',
                                message: response.data.message || 'FAQ suggestions have been generated.',
                                confirmText: 'OK',
                                hideCancel: true,
                                onConfirm: function() {
                                    location.reload();
                                }
                            });
                        } else {
                            chatbotShowModal({
                                title: 'Analysis Failed',
                                message: response.data || 'An error occurred during analysis.',
                                confirmText: 'OK',
                                hideCancel: true,
                                isError: true,
                                onConfirm: function() { $btn.prop('disabled', false).text(originalText); }
                            });
                        }
                    }).fail(function() {
                        chatbotShowModal({
                            title: 'Request Failed',
                            message: 'Could not connect to server. Please try again.',
                            confirmText: 'OK',
                            hideCancel: true,
                            isError: true,
                            onConfirm: function() { $btn.prop('disabled', false).text(originalText); }
                        });
                    });
                }
            });
        });

        // Handle Auto-Analysis Toggle - saves immediately, no save button needed
        $('#auto_analysis_toggle').on('change', function() {
            const isEnabled = $(this).is(':checked') ? 'on' : 'off';
            const $status = $('#auto_analysis_status');

            $.post(ajaxurl, {
                action: 'chatbot_toggle_auto_analysis',
                enabled: isEnabled,
                nonce: '<?php echo wp_create_nonce('chatbot_gap_analysis'); ?>'
            }, function(response) {
                if (response.success) {
                    $status.text(isEnabled === 'on' ? 'ON' : 'OFF');
                    $status.css('color', isEnabled === 'on' ? '#10b981' : '#6b7280');
                } else {
                    alert('Error: ' + (response.data || 'Failed to save setting'));
                    // Revert checkbox
                    $('#auto_analysis_toggle').prop('checked', isEnabled !== 'on');
                }
            });
        });
    });
    </script>
    <?php
}

// Learning Dashboard Section - Semi-Automated Learning with Human Review
function chatbot_chatgpt_learning_dashboard_section_callback($args) {
    ?>
    <p>The Learning Dashboard provides safeguards for semi-automated FAQ improvements based on user feedback.</p>
    <?php
}

// Learning Dashboard Callback
function chatbot_chatgpt_learning_dashboard_callback() {
    // Fixed settings (always on with human approval)
    $negative_threshold = 5; // Fixed: 5 negatives before flagging
    $rate_limit_per_session = 3; // Fixed: 3 per session
    $confidence_floor = 50; // Fixed: 50% minimum

    // Get pending review queue
    $review_queue = chatbot_get_learning_review_queue();
    $pending_count = count($review_queue);

    // Get learning stats
    $learning_stats = chatbot_get_learning_stats();

    ?>
    <div>
        <!-- Header -->
        <h2 style="margin: 0 0 10px 0; color: #1e293b;">Learning Dashboard</h2>
        <p style="margin: 0 0 20px 0; color: #64748b; font-size: 14px;">
            FAQ improvements based on user feedback — all changes require human approval
        </p>

        <!-- Status Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <!-- Learning Status -->
            <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #10b981;">
                <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Learning Mode</div>
                <div style="font-size: 18px; font-weight: bold; color: #10b981;">
                    Active (Human Approval)
                </div>
            </div>

            <!-- Pending Reviews -->
            <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid <?php echo $pending_count > 0 ? '#f59e0b' : '#10b981'; ?>;">
                <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Pending Reviews</div>
                <div style="font-size: 32px; font-weight: bold; color: <?php echo $pending_count > 0 ? '#f59e0b' : '#10b981'; ?>;">
                    <?php echo $pending_count; ?>
                </div>
            </div>

            <!-- Threshold -->
            <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #3b82f6;">
                <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Flagging Threshold</div>
                <div style="font-size: 32px; font-weight: bold; color: #3b82f6;"><?php echo $negative_threshold; ?></div>
            </div>

            <!-- Stats -->
            <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #8b5cf6;">
                <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Reviewed (Total)</div>
                <div style="font-size: 32px; font-weight: bold; color: #8b5cf6;"><?php echo $learning_stats['approved'] + $learning_stats['rejected']; ?></div>
            </div>
        </div>

        <!-- How It Works -->
        <div style="background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #1e40af;">How It Works</h3>
            <ol style="margin: 0; padding-left: 20px; font-size: 13px; color: #1e3a8a; line-height: 1.8;">
                <li><strong>User gives feedback</strong> — Thumbs up/down on chatbot responses</li>
                <li><strong>Threshold reached</strong> — FAQ flagged after <?php echo $negative_threshold; ?>+ negative ratings</li>
                <li><strong>Appears here</strong> — Flagged FAQs show in the Human Review Queue below</li>
                <li><strong>You decide</strong> — Review, then Approve (mark resolved) or Dismiss</li>
            </ol>
            <p style="margin: 12px 0 0 0; font-size: 12px; color: #6b7280; font-style: italic;">
                Rate limit: <?php echo $rate_limit_per_session; ?> feedback per session • Confidence floor: <?php echo $confidence_floor; ?>%
            </p>
        </div>

        <!-- Human Review Queue -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0; font-size: 16px; color: #111827;">
                    Human Review Queue
                    <?php if ($pending_count > 0) : ?>
                    <span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-left: 8px;">
                        <?php echo $pending_count; ?> pending
                    </span>
                    <?php endif; ?>
                </h3>
                <button type="button" onclick="chatbotRefreshReviewQueue()" class="button" style="font-size: 12px;">
                    Refresh
                </button>
            </div>

            <?php if (empty($review_queue)) : ?>
            <div style="text-align: center; padding: 40px 20px; color: #64748b;">
                <div style="font-size: 48px; margin-bottom: 10px;"></div>
                <p style="margin: 0; font-size: 14px;">No FAQs pending review</p>
                <p style="margin: 5px 0 0 0; font-size: 12px;">FAQs will appear here when they reach the negative feedback threshold</p>
            </div>
            <?php else : ?>
            <table class="widefat striped" style="border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f1f5f9;">
                        <th style="padding: 12px; width: 60px;">FAQ ID</th>
                        <th style="padding: 12px;">Question</th>
                        <th style="padding: 12px; width: 80px; text-align: center;">Count</th>
                        <th style="padding: 12px; width: 100px; text-align: center;">Confidence</th>
                        <th style="padding: 12px; width: 120px; text-align: center;">Suggested</th>
                        <th style="padding: 12px; width: 150px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="learning-review-queue-body">
                    <?php foreach ($review_queue as $item) :
                        $confidence_color = $item['current_confidence'] >= 70 ? '#10b981' : ($item['current_confidence'] >= 50 ? '#f59e0b' : '#ef4444');
                    ?>
                    <tr data-item-id="<?php echo $item['id']; ?>">
                        <td style="padding: 12px; font-family: monospace; font-size: 12px;">
                            <?php echo esc_html($item['faq_id']); ?>
                        </td>
                        <td style="padding: 12px; font-size: 13px;">
                            <div style="font-weight: 600; margin-bottom: 4px;"><?php echo esc_html(substr($item['question'], 0, 80)); ?><?php echo strlen($item['question']) > 80 ? '...' : ''; ?></div>
                            <div style="font-size: 11px; color: #6b7280;">
                                <?php echo esc_html(substr($item['current_answer'], 0, 100)); ?><?php echo strlen($item['current_answer']) > 100 ? '...' : ''; ?>
                            </div>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <span style="background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 14px;">
                                <?php echo $item['negative_count']; ?>
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <span style="background: <?php echo $confidence_color; ?>20; color: <?php echo $confidence_color; ?>; padding: 4px 10px; border-radius: 4px; font-weight: 600;">
                                <?php echo $item['current_confidence']; ?>%
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: center; font-size: 12px; color: #374151;">
                            <?php echo esc_html($item['suggestion_type']); ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <button type="button" onclick="chatbotViewReviewItem(<?php echo $item['id']; ?>)" class="button" style="font-size: 11px; padding: 4px 8px;">
                                View
                            </button>
                            <button type="button" onclick="chatbotResolveReviewItem(<?php echo $item['id']; ?>)" class="button button-primary" style="font-size: 11px; padding: 4px 8px;">
                                Approve
                            </button>
                            <button type="button" onclick="chatbotDismissReviewItem(<?php echo $item['id']; ?>)" class="button" style="font-size: 11px; padding: 4px 8px;">
                                Dismiss
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Rollback Section -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #111827;">Rollback & Recovery</h3>
            <p style="margin: 0 0 15px 0; font-size: 13px; color: #6b7280;">
                If learning produces poor results, you can rollback recent changes or regenerate embeddings.
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <!-- Recent Changes -->
                <div style="background: #f9fafb; padding: 15px; border-radius: 6px; border: 1px solid #e5e7eb;">
                    <h4 style="margin: 0 0 10px 0; font-size: 14px; color: #374151;">Recent Learning Changes</h4>
                    <?php
                    $recent_changes = chatbot_get_recent_learning_changes(5);
                    if (empty($recent_changes)) :
                    ?>
                    <p style="margin: 0; font-size: 12px; color: #9ca3af; font-style: italic;">No recent changes</p>
                    <?php else : ?>
                    <ul style="margin: 0; padding-left: 18px; font-size: 12px; color: #374151; line-height: 1.8;">
                        <?php foreach ($recent_changes as $change) : ?>
                        <li>
                            <strong><?php echo esc_html($change['action']); ?></strong> -
                            FAQ #<?php echo esc_html($change['faq_id']); ?>
                            <span style="color: #9ca3af;">(<?php echo esc_html($change['date']); ?>)</span>
                            <?php if ($change['can_rollback']) : ?>
                            <button type="button" onclick="chatbotRollbackChange(<?php echo $change['id']; ?>)" style="font-size: 10px; padding: 1px 6px; margin-left: 5px; cursor: pointer;">Undo</button>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>

                <!-- Emergency Actions -->
                <div style="background: #fef2f2; padding: 15px; border-radius: 6px; border: 1px solid #fecaca;">
                    <h4 style="margin: 0 0 10px 0; font-size: 14px; color: #991b1b;">🚨 Emergency Actions</h4>
                    <p style="margin: 0 0 12px 0; font-size: 12px; color: #7f1d1d;">
                        Use these if learning has caused significant issues.
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <button type="button" onclick="chatbotResetAllLearning()" class="button" style="background: #fca5a5; border-color: #f87171; color: #7f1d1d; font-size: 12px;">
                            Reset All Learning Data
                        </button>
                        <button type="button" onclick="chatbotRegenerateEmbeddings()" class="button" style="background: #fcd34d; border-color: #fbbf24; color: #78350f; font-size: 12px;">
                            Regenerate All Embeddings
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Learning Stats -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px;">
            <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #111827;">Learning Statistics</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                <div style="text-align: center; padding: 15px; background: #f9fafb; border-radius: 6px;">
                    <div style="font-size: 28px; font-weight: bold; color: #10b981;"><?php echo $learning_stats['approved']; ?></div>
                    <div style="font-size: 12px; color: #6b7280;">Approved</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f9fafb; border-radius: 6px;">
                    <div style="font-size: 28px; font-weight: bold; color: #ef4444;"><?php echo $learning_stats['rejected']; ?></div>
                    <div style="font-size: 12px; color: #6b7280;">Rejected</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f9fafb; border-radius: 6px;">
                    <div style="font-size: 28px; font-weight: bold; color: #f59e0b;"><?php echo $learning_stats['pending']; ?></div>
                    <div style="font-size: 12px; color: #6b7280;">Pending</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f9fafb; border-radius: 6px;">
                    <div style="font-size: 28px; font-weight: bold; color: #6b7280;"><?php echo $learning_stats['rollbacks']; ?></div>
                    <div style="font-size: 12px; color: #6b7280;">Rollbacks</div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Modal function for branded dialogs (learning dashboard section)
    function chatbotShowModal(options) {
        const overlay = document.createElement('div');
        overlay.id = 'chatbot-modal-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:100000;display:flex;align-items:center;justify-content:center;';

        const borderColor = options.isError ? '#dc3232' : '#2271b1';
        const modal = document.createElement('div');
        modal.style.cssText = 'background:white;border-radius:4px;box-shadow:0 3px 6px rgba(0,0,0,0.3);max-width:400px;width:90%;border-top:4px solid ' + borderColor + ';';

        modal.innerHTML = `
            <div style="padding:20px 20px 0;">
                <h2 style="margin:0 0 12px;font-size:18px;font-weight:600;color:#1d2327;">${options.title}</h2>
                <p style="margin:0;color:#50575e;font-size:14px;line-height:1.5;">${options.message}</p>
            </div>
            <div style="padding:16px 20px;display:flex;justify-content:flex-end;gap:10px;margin-top:20px;border-top:1px solid #dcdcde;">
                ${options.hideCancel ? '' : `<button id="chatbot-modal-cancel" class="button" style="padding:6px 12px;">${options.cancelText || 'Cancel'}</button>`}
                <button id="chatbot-modal-confirm" class="button button-primary" style="padding:6px 12px;">${options.confirmText || 'OK'}</button>
            </div>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        document.getElementById('chatbot-modal-confirm').onclick = function() {
            document.body.removeChild(overlay);
            if (options.onConfirm) options.onConfirm();
        };

        const cancelBtn = document.getElementById('chatbot-modal-cancel');
        if (cancelBtn) {
            cancelBtn.onclick = function() {
                document.body.removeChild(overlay);
                if (options.onCancel) options.onCancel();
            };
        }

        overlay.onclick = function(e) {
            if (e.target === overlay && !options.hideCancel) {
                document.body.removeChild(overlay);
                if (options.onCancel) options.onCancel();
            }
        };
    }

    function chatbotRefreshReviewQueue() {
        location.reload();
    }

    function chatbotViewReviewItem(itemId) {
        jQuery.post(ajaxurl, {
            action: 'chatbot_get_review_item_details',
            item_id: itemId,
            nonce: '<?php echo wp_create_nonce('chatbot_learning_dashboard'); ?>'
        }, function(response) {
            if (response.success) {
                const item = response.data;
                let msg = '<strong>FAQ ID:</strong> ' + item.faq_id + '<br><br>';
                msg += '<strong>Question:</strong><br>' + item.question + '<br><br>';
                msg += '<strong>Current Answer:</strong><br>' + item.current_answer + '<br><br>';
                msg += '<strong>Negative Count:</strong> ' + item.negative_count + '<br>';
                msg += '<strong>Current Confidence:</strong> ' + item.current_confidence + '%<br><br>';
                if (item.user_comments && item.user_comments.length > 0) {
                    msg += '<strong>User Comments:</strong><br>';
                    item.user_comments.forEach((c, i) => {
                        msg += (i+1) + '. "' + c + '"<br>';
                    });
                }
                msg += '<br><strong>Suggestion:</strong> ' + item.suggestion_type;
                chatbotShowModal({
                    title: 'Review Item Details',
                    message: msg,
                    hideCancel: true
                });
            } else {
                chatbotShowModal({
                    title: 'Error',
                    message: 'Error loading details',
                    isError: true,
                    hideCancel: true
                });
            }
        });
    }

    function chatbotResolveReviewItem(itemId) {
        chatbotShowModal({
            title: 'Mark as Resolved',
            message: 'Mark this item as resolved? This confirms you have reviewed and addressed the feedback.',
            confirmText: 'Yes, Resolve',
            onConfirm: function() {
                jQuery.post(ajaxurl, {
                    action: 'chatbot_resolve_review_item',
                    item_id: itemId,
                    nonce: '<?php echo wp_create_nonce('chatbot_learning_dashboard'); ?>'
                }, function(response) {
                    if (response.success) {
                        jQuery('tr[data-item-id="' + itemId + '"]').fadeOut(300, function() {
                            jQuery(this).remove();
                            if (jQuery('#learning-review-queue-body tr').length === 0) {
                                location.reload();
                            }
                        });
                    } else {
                        chatbotShowModal({
                            title: 'Error',
                            message: response.data || 'Unknown error',
                            isError: true,
                            hideCancel: true
                        });
                    }
                });
            }
        });
    }

    function chatbotDismissReviewItem(itemId) {
        chatbotShowModal({
            title: 'Dismiss Item',
            message: 'Dismiss this item? It will be removed from the queue without action.',
            confirmText: 'Yes, Dismiss',
            onConfirm: function() {
                jQuery.post(ajaxurl, {
                    action: 'chatbot_dismiss_review_item',
                    item_id: itemId,
                    nonce: '<?php echo wp_create_nonce('chatbot_learning_dashboard'); ?>'
                }, function(response) {
                    if (response.success) {
                        jQuery('tr[data-item-id="' + itemId + '"]').fadeOut(300, function() {
                            jQuery(this).remove();
                            if (jQuery('#learning-review-queue-body tr').length === 0) {
                                location.reload();
                            }
                        });
                    } else {
                        chatbotShowModal({
                            title: 'Error',
                            message: response.data || 'Unknown error',
                            isError: true,
                            hideCancel: true
                        });
                    }
                });
            }
        });
    }

    function chatbotRollbackChange(changeId) {
        chatbotShowModal({
            title: 'Rollback Change',
            message: 'Rollback this change? This will undo the learning modification.',
            confirmText: 'Yes, Rollback',
            onConfirm: function() {
                jQuery.post(ajaxurl, {
                    action: 'chatbot_rollback_learning_change',
                    change_id: changeId,
                    nonce: '<?php echo wp_create_nonce('chatbot_learning_dashboard'); ?>'
                }, function(response) {
                    if (response.success) {
                        chatbotShowModal({
                            title: 'Success',
                            message: 'Change rolled back successfully!',
                            hideCancel: true,
                            onConfirm: function() { location.reload(); }
                        });
                    } else {
                        chatbotShowModal({
                            title: 'Error',
                            message: response.data || 'Unknown error',
                            isError: true,
                            hideCancel: true
                        });
                    }
                });
            }
        });
    }

    function chatbotResetAllLearning() {
        chatbotShowModal({
            title: 'Reset All Learning Data',
            message: '<strong style="color:#dc3232;">WARNING:</strong> This will reset ALL learning data!<br><br>This includes:<br>• All pending review items<br>• Learning history<br>• Feedback associations<br><br>This cannot be undone.',
            isError: true,
            confirmText: 'Continue',
            onConfirm: function() {
                chatbotShowModal({
                    title: 'Final Confirmation',
                    message: 'Are you absolutely sure? This action cannot be reversed.',
                    isError: true,
                    confirmText: 'Yes, Reset Everything',
                    onConfirm: function() {
                        jQuery.post(ajaxurl, {
                            action: 'chatbot_reset_all_learning',
                            nonce: '<?php echo wp_create_nonce('chatbot_learning_dashboard'); ?>'
                        }, function(response) {
                            if (response.success) {
                                chatbotShowModal({
                                    title: 'Success',
                                    message: 'All learning data has been reset.',
                                    hideCancel: true,
                                    onConfirm: function() { location.reload(); }
                                });
                            } else {
                                chatbotShowModal({
                                    title: 'Error',
                                    message: response.data || 'Unknown error',
                                    isError: true,
                                    hideCancel: true
                                });
                            }
                        });
                    }
                });
            }
        });
    }

    function chatbotRegenerateEmbeddings() {
        chatbotShowModal({
            title: 'Regenerate Embeddings',
            message: 'Regenerate all FAQ embeddings?<br><br>This will:<br>• Re-generate vector embeddings for all FAQs<br>• May take several minutes<br>• Temporarily affect search accuracy',
            confirmText: 'Yes, Regenerate',
            onConfirm: function() {
                const btn = document.querySelector('[onclick*="chatbotRegenerateEmbeddings"]');
                btn.disabled = true;
                btn.textContent = 'Regenerating...';

                jQuery.post(ajaxurl, {
                    action: 'chatbot_regenerate_embeddings',
                    nonce: '<?php echo wp_create_nonce('chatbot_learning_dashboard'); ?>'
                }, function(response) {
                    btn.disabled = false;
                    btn.textContent = 'Regenerate All Embeddings';

                    if (response.success) {
                        chatbotShowModal({
                            title: 'Success',
                            message: 'Embeddings regenerated successfully!<br><br>Processed: ' + response.data.processed + ' FAQs',
                            hideCancel: true
                        });
                    } else {
                        chatbotShowModal({
                            title: 'Error',
                            message: response.data || 'Unknown error',
                            isError: true,
                            hideCancel: true
                        });
                    }
                }).fail(function() {
                    btn.disabled = false;
                    btn.textContent = 'Regenerate All Embeddings';
                    chatbotShowModal({
                        title: 'Error',
                        message: 'Request failed. Please try again.',
                        isError: true,
                        hideCancel: true
                    });
                });
            }
        });
    }
    </script>
    <?php
}

// Helper function to get learning review queue
function chatbot_get_learning_review_queue() {
    $review_data = get_option('chatbot_learning_review_queue', array());

    // Filter to only pending items
    $pending = array_filter($review_data, function($item) {
        return isset($item['status']) && $item['status'] === 'pending';
    });

    // Sort by negative count descending
    usort($pending, function($a, $b) {
        return ($b['negative_count'] ?? 0) - ($a['negative_count'] ?? 0);
    });

    return array_slice($pending, 0, 20); // Limit to 20 items
}

// Helper function to get learning stats
function chatbot_get_learning_stats() {
    $review_data = get_option('chatbot_learning_review_queue', array());
    $history = get_option('chatbot_learning_history', array());

    $stats = array(
        'approved' => 0,
        'rejected' => 0,
        'pending' => 0,
        'rollbacks' => 0
    );

    foreach ($review_data as $item) {
        $status = $item['status'] ?? 'pending';
        if ($status === 'pending') $stats['pending']++;
        elseif ($status === 'approved') $stats['approved']++;
        elseif ($status === 'rejected') $stats['rejected']++;
    }

    foreach ($history as $entry) {
        if (isset($entry['action']) && $entry['action'] === 'rollback') {
            $stats['rollbacks']++;
        }
    }

    return $stats;
}

// Helper function to get recent learning changes
function chatbot_get_recent_learning_changes($limit = 5) {
    $history = get_option('chatbot_learning_history', array());

    // Sort by date descending
    usort($history, function($a, $b) {
        return strtotime($b['date'] ?? '2000-01-01') - strtotime($a['date'] ?? '2000-01-01');
    });

    return array_slice($history, 0, $limit);
}

// AJAX handler for saving learning settings
add_action('wp_ajax_chatbot_save_learning_settings', 'chatbot_ajax_save_learning_settings');
function chatbot_ajax_save_learning_settings() {
    check_ajax_referer('chatbot_learning_dashboard', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }

    update_option('chatbot_learning_enabled', sanitize_text_field($_POST['learning_enabled']));
    update_option('chatbot_confidence_floor', intval($_POST['confidence_floor']));
    update_option('chatbot_negative_threshold', intval($_POST['negative_threshold']));
    update_option('chatbot_rate_limit_per_session', intval($_POST['rate_limit_per_session']));

    wp_send_json_success('Settings saved');
}

// AJAX handler for getting review item details
add_action('wp_ajax_chatbot_get_review_item_details', 'chatbot_ajax_get_review_item_details');
function chatbot_ajax_get_review_item_details() {
    check_ajax_referer('chatbot_learning_dashboard', 'nonce');

    $item_id = intval($_POST['item_id']);
    $review_data = get_option('chatbot_learning_review_queue', array());

    foreach ($review_data as $item) {
        if (isset($item['id']) && $item['id'] === $item_id) {
            wp_send_json_success($item);
            return;
        }
    }

    wp_send_json_error('Item not found');
}

// AJAX handler for resolving review item
add_action('wp_ajax_chatbot_resolve_review_item', 'chatbot_ajax_resolve_review_item');
function chatbot_ajax_resolve_review_item() {
    check_ajax_referer('chatbot_learning_dashboard', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }

    $item_id = intval($_POST['item_id']);
    $review_data = get_option('chatbot_learning_review_queue', array());

    foreach ($review_data as &$item) {
        if (isset($item['id']) && $item['id'] === $item_id) {
            $item['status'] = 'approved';
            $item['resolved_at'] = current_time('mysql');

            // Add to history
            $history = get_option('chatbot_learning_history', array());
            $history[] = array(
                'id' => count($history) + 1,
                'action' => 'approved',
                'faq_id' => $item['faq_id'],
                'date' => current_time('Y-m-d H:i'),
                'can_rollback' => false
            );
            update_option('chatbot_learning_history', $history);
            break;
        }
    }

    update_option('chatbot_learning_review_queue', $review_data);
    wp_send_json_success('Item resolved');
}

// AJAX handler for dismissing review item
add_action('wp_ajax_chatbot_dismiss_review_item', 'chatbot_ajax_dismiss_review_item');
function chatbot_ajax_dismiss_review_item() {
    check_ajax_referer('chatbot_learning_dashboard', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }

    $item_id = intval($_POST['item_id']);
    $review_data = get_option('chatbot_learning_review_queue', array());

    foreach ($review_data as &$item) {
        if (isset($item['id']) && $item['id'] === $item_id) {
            $item['status'] = 'rejected';
            $item['dismissed_at'] = current_time('mysql');
            break;
        }
    }

    update_option('chatbot_learning_review_queue', $review_data);
    wp_send_json_success('Item dismissed');
}

// AJAX handler for rollback
add_action('wp_ajax_chatbot_rollback_learning_change', 'chatbot_ajax_rollback_learning_change');
function chatbot_ajax_rollback_learning_change() {
    check_ajax_referer('chatbot_learning_dashboard', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }

    $change_id = intval($_POST['change_id']);
    $history = get_option('chatbot_learning_history', array());

    // Find and mark as rolled back
    foreach ($history as &$entry) {
        if (isset($entry['id']) && $entry['id'] === $change_id) {
            $entry['rolled_back'] = true;
            $entry['can_rollback'] = false;

            // Add rollback entry
            $history[] = array(
                'id' => count($history) + 1,
                'action' => 'rollback',
                'faq_id' => $entry['faq_id'],
                'date' => current_time('Y-m-d H:i'),
                'can_rollback' => false
            );
            break;
        }
    }

    update_option('chatbot_learning_history', $history);
    wp_send_json_success('Change rolled back');
}

// AJAX handler for reset all learning
add_action('wp_ajax_chatbot_reset_all_learning', 'chatbot_ajax_reset_all_learning');
function chatbot_ajax_reset_all_learning() {
    check_ajax_referer('chatbot_learning_dashboard', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }

    // Reset all learning data
    delete_option('chatbot_learning_review_queue');
    delete_option('chatbot_learning_history');

    // Add reset entry to new history
    update_option('chatbot_learning_history', array(
        array(
            'id' => 1,
            'action' => 'full_reset',
            'faq_id' => 'all',
            'date' => current_time('Y-m-d H:i'),
            'can_rollback' => false
        )
    ));

    wp_send_json_success('All learning data reset');
}

// AJAX handler for regenerating embeddings
add_action('wp_ajax_chatbot_regenerate_embeddings', 'chatbot_ajax_regenerate_embeddings');
function chatbot_ajax_regenerate_embeddings() {
    check_ajax_referer('chatbot_learning_dashboard', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }

    // Get all FAQs and regenerate embeddings
    if (function_exists('chatbot_faq_load') && function_exists('chatbot_faq_update')) {
        $faqs = chatbot_faq_load();
        $processed = 0;

        foreach ($faqs as $faq) {
            // Re-save each FAQ to trigger embedding regeneration
            $result = chatbot_faq_update($faq['id'], array(
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'keywords' => $faq['keywords'] ?? '',
                'category' => $faq['category'] ?? 'General'
            ));

            if ($result['success']) {
                $processed++;
            }
        }

        wp_send_json_success(array('processed' => $processed));
    } else {
        wp_send_json_error('FAQ functions not available');
    }
}

// Function to display the reporting message - Ver 1.7.9
function chatbot_chatgpt_admin_notice() {
    $message = get_transient('chatbot_chatgpt_admin_error');
    if (!empty($message)) {
        printf('<div class="%1$s"><p><b>Chatbot: </b>%2$s</p></div>', 'notice notice-error is-dismissible', $message);
        delete_transient('chatbot_chatgpt_admin_error'); // Clear the transient after displaying the message
    }
}
add_action('admin_notices', 'chatbot_chatgpt_admin_notice');

// =============================================================================
// DATA RETENTION SETTINGS - Ver 2.5.0
// =============================================================================

/**
 * Register data retention settings
 */
function chatbot_register_data_retention_settings() {
    register_setting('chatbot_chatgpt_reporting', 'chatbot_retention_conversations');
    register_setting('chatbot_chatgpt_reporting', 'chatbot_retention_interactions');
    register_setting('chatbot_chatgpt_reporting', 'chatbot_retention_gap_questions');
    register_setting('chatbot_chatgpt_reporting', 'chatbot_retention_gap_clusters');

    add_settings_section(
        'chatbot_data_retention_section',
        'Data Retention & Cleanup',
        'chatbot_data_retention_section_callback',
        'chatbot_chatgpt_reporting'
    );

    add_settings_field(
        'chatbot_retention_conversations',
        'Conversation Logs',
        'chatbot_retention_conversations_callback',
        'chatbot_chatgpt_reporting',
        'chatbot_data_retention_section'
    );

    add_settings_field(
        'chatbot_retention_interactions',
        'Daily Interactions',
        'chatbot_retention_interactions_callback',
        'chatbot_chatgpt_reporting',
        'chatbot_data_retention_section'
    );

    add_settings_field(
        'chatbot_retention_gap_questions',
        'Gap Questions (after clustered)',
        'chatbot_retention_gap_questions_callback',
        'chatbot_chatgpt_reporting',
        'chatbot_data_retention_section'
    );

    add_settings_field(
        'chatbot_retention_gap_clusters',
        'Gap Clusters (after resolved)',
        'chatbot_retention_gap_clusters_callback',
        'chatbot_chatgpt_reporting',
        'chatbot_data_retention_section'
    );
}
add_action('admin_init', 'chatbot_register_data_retention_settings');

/**
 * Data retention section callback
 */
function chatbot_data_retention_section_callback() {
    // Get current stats
    $stats = [];
    if (function_exists('chatbot_get_data_retention_stats')) {
        $stats = chatbot_get_data_retention_stats();
    }
    ?>
    <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 15px 0; color: #374151;">Current Database Usage</h3>
        <p style="margin: 0 0 15px 0; color: #6b7280; font-size: 13px;">
            Automatic cleanup runs daily at 3 AM. Tables marked as "Keep Forever" are never automatically deleted.
        </p>

        <?php if (!empty($stats) && !isset($stats['error'])): ?>
        <table class="widefat" style="border-collapse: collapse; margin-bottom: 15px;">
            <thead>
                <tr style="background: #f1f5f9;">
                    <th style="padding: 10px; text-align: left;">Table</th>
                    <th style="padding: 10px; text-align: right;">Records</th>
                    <th style="padding: 10px; text-align: left;">Retention</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $retention_info = [
                    'chatbot_conversations' => ['Auto-delete', get_option('chatbot_retention_conversations', 90) . ' days'],
                    'chatbot_interactions' => ['Auto-delete', get_option('chatbot_retention_interactions', 365) . ' days'],
                    'chatbot_gap_questions' => ['Auto-delete (clustered)', get_option('chatbot_retention_gap_questions', 30) . ' days'],
                    'chatbot_gap_clusters' => ['Auto-delete (resolved)', get_option('chatbot_retention_gap_clusters', 90) . ' days'],
                    'chatbot_faqs' => ['Keep Forever', '-'],
                    'chatbot_faq_usage' => ['Keep Forever', '-'],
                    'chatbot_assistants' => ['Keep Forever', '-']
                ];
                foreach ($stats as $table => $info):
                    $ret = $retention_info[$table] ?? ['Unknown', '-'];
                    $color = $ret[0] === 'Keep Forever' ? '#10b981' : '#3b82f6';
                ?>
                <tr>
                    <td style="padding: 10px;"><?php echo esc_html($info['label']); ?></td>
                    <td style="padding: 10px; text-align: right; font-weight: 600;">
                        <?php echo number_format($info['count']); ?>
                    </td>
                    <td style="padding: 10px;">
                        <span style="color: <?php echo $color; ?>; font-size: 12px;">
                            <?php echo esc_html($ret[0]); ?>
                            <?php if ($ret[1] !== '-'): ?>
                                (<?php echo esc_html($ret[1]); ?>)
                            <?php endif; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color: #ef4444;">Could not fetch database statistics. <?php echo isset($stats['error']) ? esc_html($stats['error']) : ''; ?></p>
        <?php endif; ?>

        <div style="display: flex; gap: 10px; align-items: center;">
            <button type="button" id="run-cleanup-btn" class="button button-secondary" onclick="chatbotRunCleanup()">
                Run Cleanup Now
            </button>
            <span id="cleanup-status" style="font-size: 12px; color: #6b7280;"></span>
        </div>

        <script>
        function chatbotRunCleanup() {
            const btn = document.getElementById('run-cleanup-btn');
            const status = document.getElementById('cleanup-status');
            btn.disabled = true;
            btn.textContent = 'Running...';
            status.textContent = '';

            jQuery.post(ajaxurl, {
                action: 'chatbot_run_cleanup',
                nonce: '<?php echo wp_create_nonce('chatbot_settings_nonce'); ?>'
            }, function(response) {
                btn.disabled = false;
                btn.textContent = 'Run Cleanup Now';

                if (response.success) {
                    const r = response.data.results;
                    status.innerHTML = '<span style="color: #10b981;">Deleted: ' +
                        (r.conversations || 0) + ' conversations, ' +
                        (r.interactions || 0) + ' interactions, ' +
                        (r.gap_questions || 0) + ' gap questions, ' +
                        (r.gap_clusters || 0) + ' clusters</span>';
                    // Refresh after 2 seconds to update counts
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    status.innerHTML = '<span style="color: #ef4444;">Error: ' + (response.data?.message || 'Unknown error') + '</span>';
                }
            }).fail(function() {
                btn.disabled = false;
                btn.textContent = 'Run Cleanup Now';
                status.innerHTML = '<span style="color: #ef4444;">Request failed</span>';
            });
        }
        </script>
    </div>

    <p style="margin-bottom: 15px; color: #4b5563;">
        Configure how long to keep data before automatic deletion. Set to <strong>0</strong> to disable auto-deletion for that table.
    </p>
    <?php
}

/**
 * Conversation retention callback
 */
function chatbot_retention_conversations_callback() {
    $value = get_option('chatbot_retention_conversations', 90);
    ?>
    <select name="chatbot_retention_conversations">
        <option value="0" <?php selected($value, 0); ?>>Never delete (not recommended)</option>
        <option value="30" <?php selected($value, 30); ?>>30 days</option>
        <option value="60" <?php selected($value, 60); ?>>60 days</option>
        <option value="90" <?php selected($value, 90); ?>>90 days (default)</option>
        <option value="180" <?php selected($value, 180); ?>>180 days</option>
        <option value="365" <?php selected($value, 365); ?>>1 year</option>
    </select>
    <p class="description">Conversation logs grow fastest. 90 days is recommended for most sites.</p>
    <?php
}

/**
 * Interactions retention callback
 */
function chatbot_retention_interactions_callback() {
    $value = get_option('chatbot_retention_interactions', 365);
    ?>
    <select name="chatbot_retention_interactions">
        <option value="0" <?php selected($value, 0); ?>>Never delete</option>
        <option value="90" <?php selected($value, 90); ?>>90 days</option>
        <option value="180" <?php selected($value, 180); ?>>180 days</option>
        <option value="365" <?php selected($value, 365); ?>>1 year (default)</option>
        <option value="730" <?php selected($value, 730); ?>>2 years</option>
    </select>
    <p class="description">Daily interaction counts. Low volume, safe to keep longer.</p>
    <?php
}

/**
 * Gap questions retention callback
 */
function chatbot_retention_gap_questions_callback() {
    $value = get_option('chatbot_retention_gap_questions', 30);
    ?>
    <select name="chatbot_retention_gap_questions">
        <option value="0" <?php selected($value, 0); ?>>Never delete</option>
        <option value="7" <?php selected($value, 7); ?>>7 days</option>
        <option value="14" <?php selected($value, 14); ?>>14 days</option>
        <option value="30" <?php selected($value, 30); ?>>30 days (default)</option>
        <option value="60" <?php selected($value, 60); ?>>60 days</option>
        <option value="90" <?php selected($value, 90); ?>>90 days</option>
    </select>
    <p class="description">Only deletes questions that have been clustered. Unclustered questions are kept.</p>
    <?php
}

/**
 * Gap clusters retention callback
 */
function chatbot_retention_gap_clusters_callback() {
    $value = get_option('chatbot_retention_gap_clusters', 90);
    ?>
    <select name="chatbot_retention_gap_clusters">
        <option value="0" <?php selected($value, 0); ?>>Never delete</option>
        <option value="30" <?php selected($value, 30); ?>>30 days</option>
        <option value="60" <?php selected($value, 60); ?>>60 days</option>
        <option value="90" <?php selected($value, 90); ?>>90 days (default)</option>
        <option value="180" <?php selected($value, 180); ?>>180 days</option>
    </select>
    <p class="description">Only deletes clusters that are resolved (FAQ created or dismissed). Active clusters are kept.</p>
    <?php
}
