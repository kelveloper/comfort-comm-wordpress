<?php
/**
 * Steve-Bot - Registration - Knowledge Base Settings - Ver 2.0.0
 *
 * This file contains the code for the Chatbot settings page.
 * It handles the registration of settings and other parameters.
 * 
 *
 * @package chatbot-chatgpt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die();
}

// Register Knowledge Base settings
function chatbot_chatgpt_kn_settings_init() {

    // Knowledge Base Tab

    // Knowledge Base Settings and Schedule - Ver 2.0.0
    add_settings_section(
        'chatbot_chatgpt_knowledge_navigator_settings_section',
        'Knowledge Base',
        'chatbot_chatgpt_knowledge_navigator_section_callback',
        'chatbot_chatgpt_knowledge_navigator'
    );

    // Knowledge Base Status
    add_settings_section(
        'chatbot_chatgpt_kn_status_section',
        'Knowledge Base Status',
        'chatbot_chatgpt_kn_status_section_callback',
        'chatbot_chatgpt_kn_status'
    );

    // Knowledge Base Settings and Schedule - Ver 2.0.0
    register_setting('chatbot_chatgpt_knowledge_navigator', 'chatbot_chatgpt_kn_schedule');
    register_setting('chatbot_chatgpt_knowledge_navigator', 'chatbot_chatgpt_kn_maximum_top_words');
    register_setting('chatbot_chatgpt_knowledge_navigator', 'chatbot_chatgpt_kn_tuning_percentage');

    add_settings_section(
        'chatbot_chatgpt_kn_scheduling_section',
        'Knowledge Base Scheduling',
        'chatbot_chatgpt_kn_settings_section_callback',
        'chatbot_chatgpt_kn_scheduling'
    );

    add_settings_field(
        'chatbot_chatgpt_kn_schedule',
        'Select Run Schedule',
        'chatbot_chatgpt_kn_schedule_callback',
        'chatbot_chatgpt_kn_scheduling',
        'chatbot_chatgpt_kn_scheduling_section'
    );

    add_settings_field(
        'chatbot_chatgpt_kn_maximum_top_words',
        'Maximum Top Words',
        'chatbot_chatgpt_kn_maximum_top_words_callback',
        'chatbot_chatgpt_kn_scheduling',
        'chatbot_chatgpt_kn_scheduling_section'
    );

    add_settings_field(
        'chatbot_chatgpt_kn_tuning_percentage',
        'Tuning Percentage',
        'chatbot_chatgpt_kn_tuning_percentage_callback',
        'chatbot_chatgpt_kn_scheduling',
        'chatbot_chatgpt_kn_scheduling_section'
    );

    // Knowledge Base Inclusion/Exclusion Settings - Ver 2.0.0
    // Register settings for dynamic post types
    add_settings_section(
        'chatbot_chatgpt_kn_include_exclude_section',
        'Knowledge Base Include/Exclude Settings',
        'chatbot_chatgpt_kn_include_exclude_section_callback',
        'chatbot_chatgpt_kn_include_exclude'
    );

    // Register settings for comments separately since it's not a post type
    register_setting(
        'chatbot_chatgpt_knowledge_navigator',
        'chatbot_chatgpt_kn_include_comments',
        [
            'type' => 'string',
            'default' => 'No',
            'sanitize_callback' => 'sanitize_text_field'
        ]
    );

    // Register dynamic post type settings and fields
    $published_types = chatbot_chatgpt_kn_get_published_post_types();
    foreach ($published_types as $post_type => $label) {

        // Register the setting
        $plural_type = $post_type === 'reference' ? 'references' : $post_type . 's';
        $option_name = 'chatbot_chatgpt_kn_include_' . $plural_type;

        register_setting(
            'chatbot_chatgpt_knowledge_navigator',
            $option_name
        );

        // Add the settings field
        add_settings_field(
            // 'chatbot_chatgpt_kn_include_' . $plural_type,
            $option_name,
            'Include ' . ucfirst($label),
            'chatbot_chatgpt_kn_include_post_type_callback',
            'chatbot_chatgpt_kn_include_exclude',
            'chatbot_chatgpt_kn_include_exclude_section',
            ['option_name' => $option_name]
        );

    }

    // Add comments field
    add_settings_field(
        'chatbot_chatgpt_kn_include_comments',
        'Include Approved Comments',
        'chatbot_chatgpt_kn_include_comments_callback',
        'chatbot_chatgpt_kn_include_exclude',
        'chatbot_chatgpt_kn_include_exclude_section'
    );

    // Knowledge Base Enhanced Responses - Ver 2.0.0
    register_setting('chatbot_chatgpt_knowledge_navigator', 'chatbot_chatgpt_suppress_learnings');
    register_setting('chatbot_chatgpt_knowledge_navigator', 'chatbot_chatgpt_custom_learnings_message');
    register_setting('chatbot_chatgpt_knowledge_navigator', 'chatbot_chatgpt_enhanced_response_limit');
    register_setting('chatbot_chatgpt_knowledge_navigator', 'chatbot_chatgpt_enhanced_response_include_excerpts');

    add_settings_section(
        'chatbot_chatgpt_kn_enhanced_response_section',
        'Knowledge Base Enhanced Response Settings',
        'chatbot_chatgpt_kn_enhanced_response_section_callback',
        'chatbot_chatgpt_kn_enhanced_response'
    );

    add_settings_field(
        'chatbot_chatgpt_suppress_learnings',
        'Suppress Learnings Messages',
        'chatbot_chatgpt_suppress_learnings_callback',
        'chatbot_chatgpt_kn_enhanced_response',
        'chatbot_chatgpt_kn_enhanced_response_section'
    );

    add_settings_field(
        'chatbot_chatgpt_custom_learnings_message',
        'Custom Learnings Message',
        'chatbot_chatgpt_custom_learnings_message_callback',
        'chatbot_chatgpt_kn_enhanced_response',
        'chatbot_chatgpt_kn_enhanced_response_section'
    );

    add_settings_field(
        'chatbot_chatgpt_enhanced_response_limit',
        'Enhanced Response Limit',
        'chatbot_chatgpt_enhanced_response_limit_callback',
        'chatbot_chatgpt_kn_enhanced_response',
        'chatbot_chatgpt_kn_enhanced_response_section'
    );

    add_settings_field(
        'chatbot_chatgpt_enhanced_response_include_excerpts',
        'Include Post/Page Excerpts',
        'chatbot_chatgpt_enhanced_response_include_excerpts_callback',
        'chatbot_chatgpt_kn_enhanced_response',
        'chatbot_chatgpt_kn_enhanced_response_section'
    );

    // Analysis Tab

    // Knowledge Base Analysis settings tab - Ver 1.6.1
    register_setting('chatbot_chatgpt_kn_analysis', 'chatbot_chatgpt_kn_analysis_output');

    add_settings_section(
        'chatbot_chatgpt_kn_analysis_section',
        'Knowledge Base Analysis',
        'chatbot_chatgpt_kn_analysis_section_callback',
        'chatbot_chatgpt_kn_analysis'
    );

    add_settings_field(
        'chatbot_chatgpt_kn_analysis_output',
        'Output Format',
        'chatbot_chatgpt_kn_analysis_output_callback',
        'chatbot_chatgpt_kn_analysis',
        'chatbot_chatgpt_kn_analysis_section'
    );

    // FAQ Import Section - Ver 2.3.7
    add_settings_section(
        'chatbot_chatgpt_faq_import_section',
        'FAQ Import',
        'chatbot_chatgpt_faq_import_section_callback',
        'chatbot_chatgpt_faq_import'
    );

}
add_action('admin_init', 'chatbot_chatgpt_kn_settings_init');

// FAQ Import Section Callback - Ver 2.3.7
function chatbot_chatgpt_faq_import_section_callback() {
    // Get FAQ count
    $faq_count = function_exists('chatbot_faq_get_count') ? chatbot_faq_get_count() : 0;

    // Display any import messages from transient
    $import_message = get_transient('chatbot_faq_import_message');
    if ($import_message) {
        delete_transient('chatbot_faq_import_message');
        $class = $import_message['type'] === 'success' ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($import_message['message']) . '</p></div>';
    }

    // Check if Supabase is configured
    $supabase_configured = defined('CHATBOT_PG_HOST') && defined('CHATBOT_SUPABASE_ANON_KEY');
    ?>
    </form><!-- Close parent settings form to prevent nesting -->

    <div class="wrap">
        <p>Manage your FAQ entries. The chatbot uses semantic vector search to match questions naturally.</p>

        <?php if ($supabase_configured): ?>
        <div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
            <strong>Vector Database:</strong> Connected to Supabase
            <span style="color: #0c5460;"> - Using AI-powered semantic search</span>
        </div>
        <?php else: ?>
        <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
            <strong>Vector Database:</strong> Not configured
            <span style="color: #721c24;"> - Add CHATBOT_PG_HOST and CHATBOT_SUPABASE_ANON_KEY to wp-config.php</span>
        </div>
        <?php endif; ?>

        <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <strong>Current FAQ Entries:</strong> <?php echo esc_html($faq_count); ?>
            <?php if ($faq_count > 0): ?>
                <span style="color: #155724;"> - Ready to use</span>
            <?php else: ?>
                <span style="color: #856404;"> - Add FAQs to get started</span>
            <?php endif; ?>
        </div>

        <button type="button" id="chatbot-faq-add-btn" class="button button-primary" style="margin-bottom: 20px;">
            Add New FAQ
        </button>

        <?php
        // Show existing FAQs if any
        if ($faq_count > 0 && function_exists('chatbot_faq_get_all')) {
            $faqs = chatbot_faq_get_all();
            if (!empty($faqs)) {
                // Get unique categories for filter
                $categories = array_unique(array_filter(array_map(function($f) { return $f->category; }, $faqs)));
                sort($categories);
                ?>

                <!-- Category Filter & Pagination Controls (Ver 2.5.0) -->
                <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <label for="chatbot-faq-filter"><strong>Filter by Category:</strong></label>
                        <select id="chatbot-faq-filter" style="margin-left: 10px;">
                            <option value="">All Categories (<?php echo count($faqs); ?>)</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span id="chatbot-faq-filter-count" style="margin-left: 10px; color: #666;"></span>
                    </div>
                    <div id="chatbot-faq-pagination" style="display: flex; align-items: center; gap: 10px;">
                        <label for="chatbot-faq-per-page"><strong>Per page:</strong></label>
                        <select id="chatbot-faq-per-page" style="width: 70px;">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                            <option value="all">All</option>
                        </select>
                        <button type="button" id="chatbot-faq-prev" class="button button-small" disabled>&laquo; Prev</button>
                        <span id="chatbot-faq-page-info" style="min-width: 100px; text-align: center;">Page 1 of 1</span>
                        <button type="button" id="chatbot-faq-next" class="button button-small">Next &raquo;</button>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped" id="chatbot-faq-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 30%;">Question</th>
                            <th style="width: 35%;">Answer</th>
                            <th style="width: 12%;">Category</th>
                            <th style="width: 18%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $faq_number = 1;
                        foreach ($faqs as $faq) :
                        ?>
                        <tr data-faq-id="<?php echo esc_attr($faq->id); ?>" data-faq-number="<?php echo $faq_number; ?>" data-category="<?php echo esc_attr($faq->category); ?>">
                            <td><strong><?php echo $faq_number; ?></strong></td>
                            <td><?php echo esc_html($faq->question); ?></td>
                            <td><?php echo esc_html(substr($faq->answer, 0, 150)); ?><?php echo strlen($faq->answer) > 150 ? '...' : ''; ?></td>
                            <td><span style="background: #e0e0e0; padding: 2px 8px; border-radius: 3px; font-size: 12px;"><?php echo esc_html($faq->category); ?></span></td>
                            <td>
                                <button type="button" class="button button-small chatbot-faq-edit-btn" data-faq-id="<?php echo esc_attr($faq->id); ?>" data-faq-number="<?php echo $faq_number; ?>">
                                    Edit
                                </button>
                                <button type="button" class="button button-small chatbot-faq-delete-btn" data-faq-id="<?php echo esc_attr($faq->id); ?>" style="color: #a00;">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <?php
                        $faq_number++;
                        endforeach;
                        ?>
                    </tbody>
                </table>

                <!-- Ver 2.5.0: Bottom pagination controls -->
                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 15px; padding: 10px 0; border-top: 1px solid #e2e8f0;">
                    <label for="chatbot-faq-per-page-bottom"><strong>Per page:</strong></label>
                    <select id="chatbot-faq-per-page-bottom" style="width: 70px;">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                        <option value="all">All</option>
                    </select>
                    <button type="button" id="chatbot-faq-prev-bottom" class="button button-small" disabled>&laquo; Prev</button>
                    <span id="chatbot-faq-page-info-bottom" style="min-width: 100px; text-align: center;">Page 1 of 1</span>
                    <button type="button" id="chatbot-faq-next-bottom" class="button button-small">Next &raquo;</button>
                </div>

                <script>
                // Category filter and pagination functionality (Ver 2.5.0)
                jQuery(document).ready(function($) {
                    let currentPage = 1;
                    let perPage = 50;
                    let filteredRows = [];

                    function getFilteredRows() {
                        const category = $('#chatbot-faq-filter').val();
                        filteredRows = [];
                        $('#chatbot-faq-table tbody tr').each(function() {
                            const rowCategory = $(this).data('category');
                            if (!category || rowCategory === category) {
                                filteredRows.push($(this));
                            }
                        });
                        return filteredRows;
                    }

                    function updatePagination() {
                        const rows = getFilteredRows();
                        const totalRows = rows.length;
                        const totalPages = perPage === 'all' ? 1 : Math.ceil(totalRows / perPage);

                        // Clamp current page
                        if (currentPage > totalPages) currentPage = totalPages;
                        if (currentPage < 1) currentPage = 1;

                        // Hide all rows first
                        $('#chatbot-faq-table tbody tr').hide();

                        // Show rows for current page
                        const startIdx = perPage === 'all' ? 0 : (currentPage - 1) * perPage;
                        const endIdx = perPage === 'all' ? totalRows : Math.min(startIdx + perPage, totalRows);

                        for (let i = startIdx; i < endIdx; i++) {
                            rows[i].show();
                        }

                        // Update page info (both top and bottom)
                        let pageText = '';
                        if (totalRows === 0) {
                            pageText = 'No results';
                        } else if (perPage === 'all') {
                            pageText = 'Showing all ' + totalRows;
                        } else {
                            pageText = 'Page ' + currentPage + ' of ' + totalPages;
                        }
                        $('#chatbot-faq-page-info, #chatbot-faq-page-info-bottom').text(pageText);

                        // Update button states (both top and bottom)
                        $('#chatbot-faq-prev, #chatbot-faq-prev-bottom').prop('disabled', currentPage <= 1 || perPage === 'all');
                        $('#chatbot-faq-next, #chatbot-faq-next-bottom').prop('disabled', currentPage >= totalPages || perPage === 'all');

                        // Sync per-page dropdowns
                        $('#chatbot-faq-per-page, #chatbot-faq-per-page-bottom').val(perPage === 'all' ? 'all' : perPage);

                        // Update filter count
                        const filterVal = $('#chatbot-faq-filter').val();
                        if (filterVal) {
                            $('#chatbot-faq-filter-count').text('Showing ' + totalRows + ' FAQs');
                        } else {
                            $('#chatbot-faq-filter-count').text('');
                        }
                    }

                    // Category filter change
                    $('#chatbot-faq-filter').on('change', function() {
                        currentPage = 1;
                        updatePagination();
                    });

                    // Per page change (both top and bottom)
                    $('#chatbot-faq-per-page, #chatbot-faq-per-page-bottom').on('change', function() {
                        perPage = $(this).val() === 'all' ? 'all' : parseInt($(this).val());
                        currentPage = 1;
                        updatePagination();
                    });

                    // Previous page (both top and bottom)
                    $('#chatbot-faq-prev, #chatbot-faq-prev-bottom').on('click', function() {
                        if (currentPage > 1) {
                            currentPage--;
                            updatePagination();
                        }
                    });

                    // Next page (both top and bottom)
                    $('#chatbot-faq-next, #chatbot-faq-next-bottom').on('click', function() {
                        const rows = getFilteredRows();
                        const totalPages = Math.ceil(rows.length / perPage);
                        if (currentPage < totalPages) {
                            currentPage++;
                            updatePagination();
                        }
                    });

                    // Initialize pagination
                    updatePagination();
                });
                </script>
                <?php
            }
        }
        ?>

        <!-- FAQ Modal -->
        <div id="chatbot-faq-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
            <div style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 5px;">
                <span id="chatbot-faq-modal-close" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                <h2 id="chatbot-faq-modal-title">Add FAQ</h2>
                <form id="chatbot-faq-form">
                    <input type="hidden" id="chatbot-faq-id" value="">
                    <p>
                        <label for="chatbot-faq-question"><strong>Question:</strong> <span style="color: #dc3232;">*</span></label><br>
                        <textarea id="chatbot-faq-question" style="width: 100%; height: 80px;" required></textarea>
                    </p>
                    <p>
                        <label for="chatbot-faq-answer"><strong>Answer:</strong> <span style="color: #dc3232;">*</span></label><br>
                        <textarea id="chatbot-faq-answer" style="width: 100%; height: 120px;" required></textarea>
                    </p>
                    <p>
                        <label for="chatbot-faq-category-select"><strong>Category:</strong> <span style="color: #dc3232;">*</span></label><br>
                        <select id="chatbot-faq-category-select" style="width: 100%;" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                            <?php endforeach; ?>
                            <option value="__new__">+ Add New Category...</option>
                        </select>
                        <input type="text" id="chatbot-faq-category-new" style="width: 100%; margin-top: 8px; display: none;" placeholder="Enter new category name">
                        <input type="hidden" id="chatbot-faq-category" value="">
                    </p>
                    <p>
                        <button type="submit" class="button button-primary" id="chatbot-faq-save-btn">Save FAQ</button>
                        <button type="button" id="chatbot-faq-modal-cancel" class="button">Cancel</button>
                        <span id="chatbot-faq-saving" style="display: none; margin-left: 10px;">
                            <span class="spinner is-active" style="float: none; margin: 0;"></span>
                            Generating embedding...
                        </span>
                    </p>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            const modal = $('#chatbot-faq-modal');
            const modalTitle = $('#chatbot-faq-modal-title');
            const form = $('#chatbot-faq-form');
            const faqId = $('#chatbot-faq-id');
            const question = $('#chatbot-faq-question');
            const answer = $('#chatbot-faq-answer');
            const category = $('#chatbot-faq-category');
            const categorySelect = $('#chatbot-faq-category-select');
            const categoryNew = $('#chatbot-faq-category-new');

            // Handle category dropdown change
            categorySelect.on('change', function() {
                const val = $(this).val();
                if (val === '__new__') {
                    categoryNew.show().focus();
                    category.val('');
                } else {
                    categoryNew.hide().val('');
                    category.val(val);
                }
            });

            // Update hidden field when typing new category
            categoryNew.on('input', function() {
                category.val($(this).val());
            });

            // Helper to set category value in form
            function setCategoryValue(catValue) {
                // Check if category exists in dropdown
                const optionExists = categorySelect.find('option[value="' + catValue + '"]').length > 0;
                if (catValue && optionExists) {
                    categorySelect.val(catValue);
                    categoryNew.hide().val('');
                } else if (catValue) {
                    // New category not in list - show as custom
                    categorySelect.val('__new__');
                    categoryNew.show().val(catValue);
                } else {
                    categorySelect.val('');
                    categoryNew.hide().val('');
                }
                category.val(catValue);
            }

            // Open modal for adding
            $('#chatbot-faq-add-btn').on('click', function() {
                modalTitle.text('Add New FAQ');
                faqId.val('');
                question.val('');
                answer.val('');
                setCategoryValue('');
                modal.show();
            });

            // Open modal for editing
            $(document).on('click', '.chatbot-faq-edit-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const id = $(this).data('faq-id');
                const btn = $(this);
                modalTitle.text('Edit FAQ');

                // Show loading state
                btn.prop('disabled', true).text('Loading...');

                // Get FAQ data via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'chatbot_faq_get',
                        nonce: '<?php echo wp_create_nonce('chatbot_faq_manage'); ?>',
                        id: id
                    },
                    success: function(response) {
                        btn.prop('disabled', false).text('Edit');
                        if (response.success && response.data.faq) {
                            faqId.val(response.data.faq.id);
                            question.val(response.data.faq.question);
                            answer.val(response.data.faq.answer);
                            setCategoryValue(response.data.faq.category);
                            modal.show();
                        } else {
                            alert('Failed to load FAQ: ' + (response.data.message || 'Unknown error'));
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).text('Edit');
                        alert('Failed to load FAQ');
                    }
                });
            });

            // Close modal
            $('#chatbot-faq-modal-close, #chatbot-faq-modal-cancel').on('click', function() {
                modal.hide();
            });

            // Submit form
            form.on('submit', function(e) {
                e.preventDefault();

                // Validate category - required field
                if (categorySelect.val() === '__new__' && !categoryNew.val().trim()) {
                    alert('Please enter a category name.');
                    categoryNew.focus();
                    return;
                }
                if (!categorySelect.val() || (categorySelect.val() === '__new__' && !category.val().trim())) {
                    alert('Please select or enter a category.');
                    categorySelect.focus();
                    return;
                }

                submitFaq(false);
            });

            // Function to submit FAQ with optional force flag
            function submitFaq(forceAdd) {
                const id = faqId.val();
                const action = id ? 'chatbot_faq_update' : 'chatbot_faq_add';

                const data = {
                    action: action,
                    nonce: '<?php echo wp_create_nonce('chatbot_faq_manage'); ?>',
                    question: question.val(),
                    answer: answer.val(),
                    category: category.val().trim()
                };

                if (id) {
                    data.id = id;
                }

                // Add force flag if user confirmed duplicate
                if (forceAdd) {
                    data.force_add = '1';
                }

                // Show loading spinner
                $('#chatbot-faq-save-btn').prop('disabled', true);
                $('#chatbot-faq-saving').show();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: data,
                    timeout: 60000, // 60 second timeout for embedding generation
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            // Check if it's a duplicate warning
                            if (response.data && response.data.duplicate) {
                                if (confirm(response.data.message + '\n\nDo you want to add it anyway?')) {
                                    // Retry with force flag
                                    submitFaq(true);
                                    return;
                                }
                            } else {
                                alert('Error: ' + (response.data.message || 'Unknown error'));
                            }
                            $('#chatbot-faq-save-btn').prop('disabled', false);
                            $('#chatbot-faq-saving').hide();
                        }
                    },
                    error: function() {
                        alert('Failed to save FAQ. This may be due to a timeout - embedding generation can take a few seconds.');
                        $('#chatbot-faq-save-btn').prop('disabled', false);
                        $('#chatbot-faq-saving').hide();
                    }
                });
            }

            // Delete FAQ
            $(document).on('click', '.chatbot-faq-delete-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const id = $(this).data('faq-id');

                if (!confirm('Are you sure you want to delete this FAQ?')) {
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'chatbot_faq_delete',
                        nonce: '<?php echo wp_create_nonce('chatbot_faq_manage'); ?>',
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + (response.data.message || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('Failed to delete FAQ');
                    }
                });
            });
        });
        </script>
    </div>

    <!-- Reopen parent settings form -->
    <form method="post" action="options.php">
    <?php settings_fields('chatbot_chatgpt_knowledge_navigator'); ?>
    <?php
}
