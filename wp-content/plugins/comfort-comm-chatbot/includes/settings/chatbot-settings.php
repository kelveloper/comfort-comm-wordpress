<?php
/**
 * Kognetiks Chatbot - Settings Page
 *
 * This file contains the code for the Chatbot settings page.
 * It allows users to configure the bot name, start status, and greetings.
 * 
 *
 * @package chatbot-chatgpt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die();
}

// Set up the Chatbot Main Menu Page - Ver 1.9.0
// function chatbot_chatgpt_menu_page() {

//     add_menu_page(
//         'Chatbot Settings',                     // Page title
//         'Kognetiks Chatbot',                    // Menu title
//         'manage_options',                       // Capability
//         'chatbot-chatgpt',                      // Menu slug
//         'chatbot_chatgpt_settings_page',        // Callback function
//         'dashicons-format-chat'                 // Icon URL (optional)
//     );

// }
// add_action('admin_menu', 'chatbot_chatgpt_menu_page');

// Settings page HTML - Ver 1.3.0
function chatbot_chatgpt_settings_page() {
    
    if (!current_user_can('manage_options')) {
        return;
    }

    global $chatbot_chatgpt_plugin_version;

    global $kchat_settings;

    $kchat_settings['chatbot_chatgpt_version'] = $chatbot_chatgpt_plugin_version;
    $kchat_settings_json = wp_json_encode($kchat_settings);
    $escaped_kchat_settings_json = esc_js($kchat_settings_json);   
    wp_add_inline_script('chatbot-chatgpt-local', 'if (typeof kchat_settings === "undefined") { var kchat_settings = ' . $escaped_kchat_settings_json . '; } else { kchat_settings = ' . $escaped_kchat_settings_json . '; }', 'before');
    
    // Localize the settings - Added back in for Ver 1.8.5
    chatbot_chatgpt_localize();

    $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'setup';
   
    if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
        add_settings_error('chatbot_chatgpt_messages', 'chatbot_chatgpt_message', 'Settings Saved', 'updated');
        settings_errors('chatbot_chatgpt_messages');
    }

    // Check reminderCount in local storage - Ver 1.8.1
    $reminderCount = intval(esc_attr(get_option('chatbot_chatgpt_reminder_count', 0)));
    if ($reminderCount % 100 === 0 && $reminderCount <= 500) {
        $message = 'If you and your visitors are enjoying having this chatbot on your site, please take a moment to <a href="https://wordpress.org/support/plugin/chatbot-chatgpt/reviews/" target="_blank">rate and review this plugin</a>. Thank you!';
        chatbot_chatgpt_general_admin_notice($message);
    }
    // Add 1 to reminderCount and update localStorage
    if ($reminderCount < 501) {
        $reminderCount++;
        update_option('chatbot_chatgpt_reminder_count', $reminderCount);
    }

    // Check if the user wants to reset the appearance settings to default - Ver 1.8.1
    $chatbot_chatgpt_appearance_reset = esc_attr(get_option('chatbot_chatgpt_appearance_reset', 'No'));
    // DIAG - Diagnostics
    // back_trace( 'NOTICE', '$chatbot_chatgpt_appearance_reset: ' . $chatbot_chatgpt_appearance_reset);
    if ( $chatbot_chatgpt_appearance_reset == 'Yes' ) {
        chatbot_chatgpt_appearance_restore_default_settings();
    }

    // DIAG - Diagnostics
    // back_trace( 'NOTICE', 'chatbot_chatgpt_settings_page() - $active_tab: ' . $active_tab );
    // back_trace( 'NOTICE', 'Current Page: ' . $_GET['page']);
    // back_trace( 'NOTICE', 'Current Tab: ' . ($_GET['tab'] ?? 'No Tab Set'));
    // back_trace( 'NOTICE', 'chatbot_ai_platform_choice: ' . esc_attr(get_option('chatbot_ai_platform_choice', 'OpenAI')));

    ?>
    <div id="chatbot-chatgpt-settings" class="wrap">
        <h1><span class="dashicons dashicons-format-chat" style="font-size: 25px;"></span> Steve-Bot</h1>

       <script>
            window.onload = function() {
                // Assign the function to the window object to make it globally accessible
                window.selectIcon = function(id) {
                    let chatbot_chatgpt_Element = document.getElementById('chatbot_chatgpt_avatar_icon_setting');
                    if(chatbot_chatgpt_Element) {
                        // Clear border from previously selected icon
                        let previousIconId = chatbot_chatgpt_Element.value;
                        let previousIcon = document.getElementById(previousIconId);
                        if(previousIcon) previousIcon.style.border = "none";  // Change "" to "none"

                        // Set border for new selected icon
                        let selectedIcon = document.getElementById(id);
                        if(selectedIcon) selectedIcon.style.border = "2px solid red";

                        // Set selected icon value in hidden input
                        chatbot_chatgpt_Element.value = id;

                        // Save selected icon in local storage
                        localStorage.setItem('chatbot_chatgpt_avatar_icon_setting', id);
                    }
                }

                // If no icon has been selected, select the first one by default
                let iconFromStorage = localStorage.getItem('chatbot_chatgpt_avatar_icon_setting');
                let chatbot_chatgpt_Element = document.getElementById('chatbot_chatgpt_avatar_icon_setting');
                if(chatbot_chatgpt_Element) {
                    if (iconFromStorage) {
                        window.selectIcon(iconFromStorage);
                    } else if (chatbot_chatgpt_Element.value === '') {
                        window.selectIcon('icon-001.png');
                    }
                }
            }
       </script>

       <h2 class="nav-tab-wrapper">
            <a href="?page=chatbot-chatgpt&tab=setup" class="nav-tab <?php echo $active_tab == 'setup' || $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">Setup</a>
            <a href="?page=chatbot-chatgpt&tab=kn_acquire" class="nav-tab <?php echo $active_tab == 'kn_acquire' ? 'nav-tab-active' : ''; ?>">Knowledge Base</a>
            <a href="?page=chatbot-chatgpt&tab=analytics_feedback" class="nav-tab <?php echo $active_tab == 'analytics_feedback' ? 'nav-tab-active' : ''; ?>">Analytics & Feedback</a>
            <a href="?page=chatbot-chatgpt&tab=tools" class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>">Tools</a>
            <a href="?page=chatbot-chatgpt&tab=diagnostics" class="nav-tab <?php echo $active_tab == 'diagnostics' ? 'nav-tab-active' : ''; ?>">Messages</a>
            <a href="?page=chatbot-chatgpt&tab=support" class="nav-tab <?php echo $active_tab == 'support' ? 'nav-tab-active' : ''; ?>">Support</a>
       </h2>

       <form id="chatgpt-settings-form" action="options.php" method="post">
            <?php

            $chatbot_ai_platform_choice = esc_attr(get_option('chatbot_ai_platform_choice', 'OpenAI'));

            if ($active_tab == 'setup' || $active_tab == 'general') {

                settings_fields('chatbot_chatgpt_setup');

                // Render the unified Setup page
                chatbot_setup_page_content();

            } elseif ($active_tab == 'api_chatgpt' && $chatbot_ai_platform_choice == 'OpenAI') {

                settings_fields('chatbot_chatgpt_api_chatgpt');

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_model_settings_general');
                echo '</div>';

                // API Settings - Ver 1.9.5
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_api_chatgpt_general');
                echo '</div>';

                // ChatGPT API Settings - Ver 1.9.5
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_api_chatgpt_chat');
                echo '</div>';

                // Voice Settings - Ver 1.9.5
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_api_chatgpt_voice');
                echo '</div>';

                // Whisper Settings - Ver 2.0.1
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_api_chatgpt_whisper');
                echo '</div>';

                // Image Settings - Ver 1.9.5
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_api_chatgpt_image');
                echo '</div>';

                // Advanced Settings - Ver 1.9.5
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_api_chatgpt_advanced');
                echo '</div>';

            } elseif ($active_tab == 'gpt_assistants' && $chatbot_ai_platform_choice == 'OpenAI') {

                settings_fields('chatbot_chatgpt_custom_gpts');

                // Manage Assistants - Ver 2.0.4
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_assistant_settings');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                // do_settings_sections('chatbot_chatgpt_assistants_management');
                display_chatbot_chatgpt_assistants_table();
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_gpt_assistants_settings');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_additional_assistant_settings');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_remote_widget_settings');
                echo '</div>';

            } elseif ($active_tab == 'api_azure' && $chatbot_ai_platform_choice == 'Azure OpenAI') {

                settings_fields('chatbot_azure_api_model');

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_azure_model_settings_general');
                echo '</div>';

                // API Settings - Ver 1.9.5
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_azure_api_general');
                echo '</div>';

                // ChatGPT API Settings - Ver 1.9.5
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_azure_api_chat');
                echo '</div>';

                // Voice Settings - Ver 1.9.5
                // echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                // do_settings_sections('chatbot_azure_api_voice');
                // echo '</div>';

                // Whisper Settings - Ver 2.0.1
                // echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                // do_settings_sections('chatbot_azure_api_whisper');
                // echo '</div>';

                // Image Settings - Ver 1.9.5
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_azure_api_image');
                echo '</div>';

                // Advanced Settings - Ver 1.9.5
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_azure_api_advanced');
                echo '</div>';

            } elseif ($active_tab == 'gpt_azure_assistants' && $chatbot_ai_platform_choice == 'Azure OpenAI') {

                settings_fields('chatbot_azure_custom_gpts');

                // Manage Assistants - Ver 2.0.4
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_azure_assistant_settings');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                // do_settings_sections('chatbot_azure_assistants_management');
                display_chatbot_azure_assistants_table();
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_azure_gpt_assistants_settings');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_azure_additional_assistant_settings');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_azure_remote_widget_settings');
                echo '</div>';

            } elseif ($active_tab == 'api_nvidia' && $chatbot_ai_platform_choice == 'NVIDIA') {

                settings_fields('chatbot_nvidia_api_model');

                // NVIDIA API Settings - Ver 2.1.8

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_nvidia_model_settings_general');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_nvidia_api_model_general');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_nvidia_api_model_chat_settings');
                echo '</div>';

                // Advanced Settings
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_nvidia_api_model_advanced');
                echo '</div>';
            
            } elseif ($active_tab == 'api_anthropic' && $chatbot_ai_platform_choice == 'Anthropic') {

                settings_fields('chatbot_anthropic_api_model');

                // NVIDIA API Settings - Ver 2.1.8

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_anthropic_model_settings_general');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_anthropic_api_model_general');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_anthropic_api_model_chat_settings');
                echo '</div>';

                // Advanced Settings
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_anthropic_api_model_advanced');
                echo '</div>';

            } elseif ($active_tab == 'api_deepseek' && $chatbot_ai_platform_choice == 'DeepSeek') {

                settings_fields('chatbot_deepseek_api_model');

                // NVIDIA API Settings - Ver 2.1.8

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_deepseek_model_settings_general');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_deepseek_api_model_general');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_deepseek_api_model_chat_settings');
                echo '</div>';

                // Advanced Settings
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_deepseek_api_model_advanced');
                echo '</div>';

            } elseif ($active_tab == 'api_gemini' && $chatbot_ai_platform_choice == 'Gemini') {

                settings_fields('chatbot_gemini_api_model');

                // Gemini API Settings - Ver 2.3.7

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_gemini_model_settings_general');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_gemini_api_model_general');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_gemini_api_model_chat_settings');
                echo '</div>';

                // Advanced Settings
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_gemini_api_model_advanced');
                echo '</div>';

            } elseif ($active_tab == 'api_mistral' && $chatbot_ai_platform_choice == 'Mistral') {

                settings_fields('chatbot_mistral_api_model');

                // Mistral API Settings

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_mistral_model_settings_general');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_mistral_api_model_general');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_mistral_api_model_chat_settings');
                echo '</div>';

                // Advanced Settings
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_mistral_api_model_advanced');
                echo '</div>';

            } elseif ($active_tab == 'mistral_agent' && $chatbot_ai_platform_choice == 'Mistral') {

                settings_fields('chatbot_mistral_agents');

                // Manage Agents - Ver 2.3.0
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_mistral_agent_settings');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                display_chatbot_mistral_assistants_table();
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_mistral_agents_settings');
                echo '</div>';

                // NO ADVANCED SETTINGS FOR MISTRAL AGENTS = Ver 2.3.0
                // echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                // do_settings_sections('chatbot_mistral_additional_assistant_settings');
                // echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_mistral_remote_widget_settings');
                echo '</div>';

            } elseif ($active_tab == 'api_local' && $chatbot_ai_platform_choice == 'Local Server') {

                settings_fields('chatbot_local_api_model');

                // NVIDIA API Settings - Ver 2.1.8

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_local_model_settings_general');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_local_api_model_general');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_local_api_model_chat_settings');
                echo '</div>';

                // Advanced Settings
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_local_api_model_advanced');
                echo '</div>';

            } elseif ($active_tab == 'api_markov' && $chatbot_ai_platform_choice == 'Markov Chain') {

                settings_fields('chatbot_markov_chain_api_model');

                // Markov Chain Settings - Ver 2.1.6

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_markov_chain_model_settings_general');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_markov_chain_api_model_general');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_markov_chain_status');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_markov_chain_advanced_settings');
                echo '</div>';

            } elseif ($active_tab == 'api_transformer' && $chatbot_ai_platform_choice == 'Transformer') {

                settings_fields('chatbot_transformer_model_api_model');

                // Transformer Settings - Ver 2.2.0

                // Transformer Model Settings - Ver 2.2.0
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_transformer_model_settings_general');
                echo '</div>';

                // Transformer API Settings - Ver 2.2.0
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_transformer_model_api_model_general');
                echo '</div>';

                // Transformer Chat Settings - Ver 2.2.1
                // echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                // do_settings_sections('chatbot_transformer_model_status');
                // echo '</div>';

                // Transformer Advanced Settings - Ver 2.2.0
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_transformer_model_advanced_settings');
                echo '</div>';

            } elseif ($active_tab == 'kn_acquire') {

                settings_fields('chatbot_chatgpt_knowledge_navigator');

                // FAQ Vector System Introduction
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_knowledge_navigator');
                echo '</div>';

                // FAQ Import/Management
                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_faq_import');
                echo '</div>';

            } elseif ($active_tab == 'diagnostics') { // AKA Messages tab

                settings_fields('chatbot_chatgpt_diagnostics');

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_diagnostics_overview');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_diagnostics_system_settings');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_diagnostics_api_status');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_diagnostics');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_advanced');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_beta_features');
                echo '</div>';

            } elseif ($active_tab == 'analytics_feedback') {

                // Load the Analytics & Feedback page
                if (function_exists('chatbot_analytics_new_page')) {
                    chatbot_analytics_new_page();
                } else {
                    echo '<div class="notice notice-error" style="padding: 20px; margin: 20px 0;">';
                    echo '<h2 style="margin-top: 0;">⚠️ Analytics & Feedback Not Available</h2>';
                    echo '<p>The analytics page is not properly loaded.</p>';
                    echo '</div>';
                }

            } elseif ($active_tab == 'tools') {

                settings_fields('chatbot_chatgpt_tools');

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_tools_overview');
                echo '</div>';

                // echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                // do_settings_sections('chatbot_chatgpt_tools');
                // echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_tools_exporter_button');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_manage_error_logs');
                echo '</div>';

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_manage_widget_logs');
                echo '</div>';

                // echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                // do_settings_sections('chatbot_chatgpt_shortcode_tools');
                // echo '</div>';

                // echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                // do_settings_sections('chatbot_chatgpt_capability_tools');
                // echo '</div>';

            // Database tab removed - merged into Setup tab

            } elseif ($active_tab == 'support') {

                settings_fields('chatbot_chatgpt_support');

                echo '<div style="background-color: #f9f9f9; padding: 20px; margin-top: 10px; border: 1px solid #ccc;">';
                do_settings_sections('chatbot_chatgpt_support');
                echo '</div>';

            }

            // Only show submit button for tabs that have settings to save
            // Exclude: setup (has its own), analytics_feedback (read-only dashboard)
            if ($active_tab !== 'setup' && $active_tab !== 'general' && $active_tab !== 'analytics_feedback') {
                submit_button('Save Settings');
            }
            ?>
       </form>
    </div>
    <!-- Added closing tags for body and html - Ver 1.4.1 -->
    </body>
    </html>
    <?php
}
