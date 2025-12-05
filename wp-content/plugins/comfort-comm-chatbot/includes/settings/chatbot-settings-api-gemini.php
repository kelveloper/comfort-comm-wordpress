<?php
/**
 * Kognetiks Chatbot - Settings - API/Gemini Page
 *
 * This file contains the code for the Gemini settings page.
 * It allows users to configure the API key and other parameters
 * required to access the Google Gemini API from their own account.
 *
 * @package chatbot-chatgpt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die();
}

// API/Gemini settings section callback
function chatbot_gemini_model_settings_section_callback($args) {
    ?>
    <p>Configure the default settings for the Chatbot plugin to use Google Gemini for chat generation. Start by adding your API key then selecting your choices below.</p>
    <p>More information about Gemini models and their capability can be found at <a href="https://ai.google.dev/gemini-api/docs/models/gemini" target="_blank">https://ai.google.dev/gemini-api/docs/models/gemini</a>.</p>
    <p><b><i>Don't forget to click </i><code>Save Settings</code><i> to save any changes your might make.</i></b></p>
    <p style="background-color: #e0f7fa; padding: 10px;"><b>For an explanation of the API/Gemini settings and additional documentation please click <a href="?page=chatbot-chatgpt&tab=support&dir=api-gemini-settings&file=api-gemini-model-settings.md">here</a>.</b></p>
    <?php
}

function chatbot_gemini_api_model_general_section_callback($args) {
    ?>
    <p>Configure the settings for the plugin by adding your API key. This plugin requires an API key from Google AI Studio to function. You can obtain an API key by signing up at <a href="https://aistudio.google.com/app/apikey" target="_blank">https://aistudio.google.com/app/apikey</a>.</p>
    <?php
}

// API key field callback
function chatbot_gemini_api_key_callback($args) {
    $api_key = esc_attr(get_option('chatbot_gemini_api_key'));
    // Decrypt the API key
    $api_key = chatbot_chatgpt_decrypt_api_key($api_key);
    ?>
    <input type="password" id="chatbot_gemini_api_key" name="chatbot_gemini_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off">
    <?php
}

function chatbot_gemini_api_model_chat_settings_section_callback($args) {
    ?>
    <p>Configure the settings for the plugin when using chat models. Depending on the Gemini model you choose, the maximum tokens may vary. The default is 1024. For more information about the models and parameters, please see <a href="https://ai.google.dev/gemini-api/docs/models/gemini" target="_blank">https://ai.google.dev/gemini-api/docs/models/gemini</a>. Enter a conversation context to help the model understand the conversation. See the default for ideas. Some example shortcodes include:</p>
    <ul style="list-style-type: disc; list-style-position: inside; padding-left: 1em;">
        <li><code>&#91;chatbot&#93;</code> - Default chat model, style is floating</li>
        <li><code>&#91;chatbot style="floating" model="gemini-1.5-flash"&#93;</code> - Style is floating, specific model</li>
        <li><code>&#91;chatbot style="embedded" model="gemini-1.5-pro"&#93;</code> - Style is embedded, pro model</li>
    </ul>
    <?php
}

// Gemini Model Settings Callback
function chatbot_gemini_chat_model_choice_callback($args) {

    $model_choice = esc_attr(get_option('chatbot_gemini_model_choice', 'gemini-1.5-flash'));

    // Fetch models from the API
    $models = chatbot_gemini_get_models();

    // Check for errors
    if (is_string($models) && strpos($models, 'Error:') === 0) {
        // If there's an error, display the hardcoded list
        ?>
        <select id="chatbot_gemini_model_choice" name="chatbot_gemini_model_choice">
            <option value="gemini-1.5-flash" <?php selected( $model_choice, 'gemini-1.5-flash' ); ?>>gemini-1.5-flash</option>
            <option value="gemini-1.5-flash-8b" <?php selected( $model_choice, 'gemini-1.5-flash-8b' ); ?>>gemini-1.5-flash-8b</option>
            <option value="gemini-1.5-pro" <?php selected( $model_choice, 'gemini-1.5-pro' ); ?>>gemini-1.5-pro</option>
            <option value="gemini-1.0-pro" <?php selected( $model_choice, 'gemini-1.0-pro' ); ?>>gemini-1.0-pro</option>
        </select>
        <?php
    } else {
        // If models are fetched successfully, display them dynamically
        ?>
        <select id="chatbot_gemini_model_choice" name="chatbot_gemini_model_choice">
            <?php foreach ($models as $model): ?>
                <option value="<?php echo esc_attr($model['id']); ?>" <?php selected($model_choice, $model['id']); ?>><?php echo esc_html($model['id']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

}

// Max Tokens choice
function chatbot_gemini_max_tokens_setting_callback($args) {

    // Get the saved chatbot_gemini_max_tokens_setting or default to 2048
    $max_tokens = esc_attr(get_option('chatbot_gemini_max_tokens_setting', '2048'));
    // Allow for a range of tokens between 100 and 10000 in 100-step increments
    ?>
    <select id="chatbot_gemini_max_tokens_setting" name="chatbot_gemini_max_tokens_setting">
        <?php
        for ($i=100; $i<=10000; $i+=100) {
            echo '<option value="' . esc_attr($i) . '" ' . selected($max_tokens, (string)$i, false) . '>' . esc_html($i) . '</option>';
        }
        ?>
    </select>
    <?php

}

// Conversation Context
function chatbot_gemini_conversation_context_callback($args) {

    // Get the value of the setting we've registered with register_setting()
    $chatbot_gemini_conversation_context = esc_attr(get_option('chatbot_gemini_conversation_context'));

    // Check if the option has been set, if not, use a default value
    if (empty($chatbot_gemini_conversation_context)) {
        $chatbot_gemini_conversation_context = "You are a versatile, friendly, and helpful assistant designed to support me in a variety of tasks that responds in Markdown.";
        // Save the default value into the option
        update_option('chatbot_gemini_conversation_context', $chatbot_gemini_conversation_context);
    }

    ?>
    <!-- Define the textarea field. -->
    <textarea id='chatbot_gemini_conversation_context' name='chatbot_gemini_conversation_context' rows='5' cols='50' maxlength='12500'><?php echo esc_html(stripslashes($chatbot_gemini_conversation_context)); ?></textarea>
    <?php

}

// Set chatbot_gemini_temperature
function chatbot_gemini_temperature_callback($args) {

    $temperature = esc_attr(get_option('chatbot_gemini_temperature', 0.7));
    ?>
    <select id="chatbot_gemini_temperature" name="chatbot_gemini_temperature">
        <?php
        for ($i = 0.00; $i <= 2.01; $i += 0.01) {
            echo '<option value="' . $i . '" ' . selected($temperature, (string)$i) . '>' . esc_html(number_format($i, 2)) . '</option>';
        }
        ?>
    </select>
    <?php

}

// Set chatbot_gemini_top_p
function chatbot_gemini_top_p_callback($args) {

    $top_p = esc_attr(get_option('chatbot_gemini_top_p', 0.95));
    ?>
    <select id="chatbot_gemini_top_p" name="chatbot_gemini_top_p">
        <?php
        for ($i = 0.01; $i <= 1.01; $i += 0.01) {
            echo '<option value="' . $i . '" ' . selected($top_p, (string)$i) . '>' . esc_html(number_format($i, 2)) . '</option>';
        }
        ?>
    </select>
    <?php

}

// API Advanced settings section callback
function chatbot_gemini_api_model_advanced_section_callback($args) {

    ?>
    <p><strong>CAUTION</strong>: Configure the advanced settings for the plugin. Enter the base URL for the Gemini API. The default is <code>https://generativelanguage.googleapis.com/v1beta</code>.</p>
    <?php

}

// Base URL for the Gemini API
function chatbot_gemini_base_url_callback($args) {

    $chatbot_gemini_base_url = esc_attr(get_option('chatbot_gemini_base_url', 'https://generativelanguage.googleapis.com/v1beta'));
    ?>
    <input type="text" id="chatbot_gemini_base_url" name="chatbot_gemini_base_url" value="<?php echo esc_attr( $chatbot_gemini_base_url ); ?>" class="regular-text">
    <?php

}

// Timeout Settings Callback
function chatbot_gemini_timeout_setting_callback($args) {

    // Get the saved chatbot_gemini_timeout value or default to 240
    $timeout = esc_attr(get_option('chatbot_gemini_timeout_setting', 240));

    // Allow for a range of tokens between 5 and 500 in 5-step increments
    ?>
    <select id="chatbot_gemini_timeout_setting" name="chatbot_gemini_timeout_setting">
        <?php
        for ($i=5; $i<=500; $i+=5) {
            echo '<option value="' . esc_attr($i) . '" ' . selected($timeout, (string)$i, false) . '>' . esc_html($i) . '</option>';
        }
        ?>
    </select>
    <?php

}

// Register API settings
function chatbot_gemini_api_settings_init() {

    add_settings_section(
        'chatbot_gemini_settings_section',
        'API/Gemini Settings',
        'chatbot_gemini_model_settings_section_callback',
        'chatbot_gemini_model_settings_general'
    );

    // API/Gemini settings tab
    register_setting('chatbot_gemini_api_model', 'chatbot_gemini_api_enabled');
    register_setting('chatbot_gemini_api_model', 'chatbot_gemini_api_key', 'chatbot_chatgpt_sanitize_api_key');
    register_setting('chatbot_gemini_api_model', 'chatbot_gemini_max_tokens_setting');
    register_setting('chatbot_gemini_api_model', 'chatbot_gemini_conversation_context');
    register_setting('chatbot_gemini_api_model', 'chatbot_gemini_temperature');
    register_setting('chatbot_gemini_api_model', 'chatbot_gemini_top_p');

    add_settings_section(
        'chatbot_gemini_api_model_general_section',
        'Gemini API Settings',
        'chatbot_gemini_api_model_general_section_callback',
        'chatbot_gemini_api_model_general'
    );

    add_settings_field(
        'chatbot_gemini_api_key',
        'Gemini API Key',
        'chatbot_gemini_api_key_callback',
        'chatbot_gemini_api_model_general',
        'chatbot_gemini_api_model_general_section'
    );

    register_setting(
        'chatbot_gemini_api_model',
        'chatbot_gemini_model_choice',
                array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        )
    );

    register_setting(
        'chatbot_gemini_api_model',
        'chatbot_gemini_max_tokens_setting',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        )
    );

    add_settings_section(
        'chatbot_gemini_api_model_chat_settings_section',
        'Chat Settings',
        'chatbot_gemini_api_model_chat_settings_section_callback',
        'chatbot_gemini_api_model_chat_settings'
    );

    add_settings_field(
        'chatbot_gemini_model_choice',
        'Gemini Model Choice',
        'chatbot_gemini_chat_model_choice_callback',
        'chatbot_gemini_api_model_chat_settings',
        'chatbot_gemini_api_model_chat_settings_section'
    );

    // Setting to adjust in small increments the number of Max Tokens
    add_settings_field(
        'chatbot_gemini_max_tokens_setting',
        'Maximum Tokens Setting',
        'chatbot_gemini_max_tokens_setting_callback',
        'chatbot_gemini_api_model_chat_settings',
        'chatbot_gemini_api_model_chat_settings_section'
    );

    // Setting to adjust the system prompt
    add_settings_field(
        'chatbot_gemini_conversation_context',
        'System Prompt',
        'chatbot_gemini_conversation_context_callback',
        'chatbot_gemini_api_model_chat_settings',
        'chatbot_gemini_api_model_chat_settings_section'
    );

    // Temperature
    add_settings_field(
        'chatbot_gemini_temperature',
        'Temperature',
        'chatbot_gemini_temperature_callback',
        'chatbot_gemini_api_model_chat_settings',
        'chatbot_gemini_api_model_chat_settings_section'
    );

    // Top P
    add_settings_field(
        'chatbot_gemini_top_p',
        'Top P',
        'chatbot_gemini_top_p_callback',
        'chatbot_gemini_api_model_chat_settings',
        'chatbot_gemini_api_model_chat_settings_section'
    );

    // Advanced Model Settings
    register_setting('chatbot_gemini_api_model', 'chatbot_gemini_base_url');
    register_setting('chatbot_gemini_api_model', 'chatbot_gemini_timeout_setting');

    add_settings_section(
        'chatbot_gemini_api_model_advanced_section',
        'Advanced API Settings',
        'chatbot_gemini_api_model_advanced_section_callback',
        'chatbot_gemini_api_model_advanced'
    );

    // Set the base URL for the API
    add_settings_field(
        'chatbot_gemini_base_url',
        'Base URL for API',
        'chatbot_gemini_base_url_callback',
        'chatbot_gemini_api_model_advanced',
        'chatbot_gemini_api_model_advanced_section'
    );

    // Timeout setting
    add_settings_field(
        'chatbot_gemini_timeout_setting',
        'Timeout Setting (in seconds)',
        'chatbot_gemini_timeout_setting_callback',
        'chatbot_gemini_api_model_advanced',
        'chatbot_gemini_api_model_advanced_section'
    );
}
add_action('admin_init', 'chatbot_gemini_api_settings_init');
