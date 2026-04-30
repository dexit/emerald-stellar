<?php

/**
 * Admin Customizer Experiment
 * Includes tweaks for login logo, custom favicon, notices hiding, title logic, transients, shortlinks and QR codes.
 */

namespace SEOAudit\Experiments\Admin_Customizer;

use SEOAudit\Abstracts\Abstract_Experiment;

class Admin_Customizer extends Abstract_Experiment
{
    protected function load_experiment_metadata(): array
    {
        return [
            'id' => 'admin_customizer',
            'label' => 'Admin Customization & Utilities',
            'description' => 'Advanced WP admin modifications: Custom Logos, Favicons, Post Title Checks, Transients Management, QR Codes, Shortlinks (YOURLS).'
        ];
    }

    public function register(): void
    {
        // Require API Tracking logic
        require_once dirname(__FILE__) . '/API_Tracker_Integration.php';
        $tracker = new API_Tracker_Integration();
        $tracker->register();

        // Custom Login Logo
        add_action('login_enqueue_scripts', [$this, 'custom_login_logo']);
        
        // Custom Admin Logo (in admin bar)
        add_action('admin_head', [$this, 'custom_admin_logo']);

        // Custom Favicon
        add_action('wp_head', [$this, 'custom_favicon']);
        add_action('admin_head', [$this, 'custom_favicon']);

        // Hide Notices (for non-admins)
        add_action('admin_print_scripts', [$this, 'hide_admin_notices']);

        // Title Enhancements
        add_filter('title_save_pre', [$this, 'enforce_title_case']);
        add_filter('wp_insert_post_data', [$this, 'require_post_title'], 10, 2);
        add_action('admin_notices', [$this, 'check_unique_title_notice']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_title_limit_scripts']);
        
        // Shortlinks (YOURLS) and QR Codes
        add_filter('get_shortlink', [$this, 'generate_yourls_shortlink'], 10, 4);
        add_filter('manage_posts_columns', [$this, 'add_qr_code_column']);
        add_action('manage_posts_custom_column', [$this, 'display_qr_code_column'], 10, 2);

        // Utilities (Transients & Logs)
        add_action('admin_menu', [$this, 'add_utility_menus']);

        // AI Chat Assistant
        add_action('admin_footer', [$this, 'render_ai_chat_widget']);

        // Bulk Actions (e.g. Mass SEO Rescan flag)
        add_filter('bulk_actions-edit-post', [$this, 'register_seo_bulk_actions']);
        add_filter('bulk_actions-edit-page', [$this, 'register_seo_bulk_actions']);
        add_filter('handle_bulk_actions-edit-post', [$this, 'handle_seo_bulk_actions'], 10, 3);
        add_filter('handle_bulk_actions-edit-page', [$this, 'handle_seo_bulk_actions'], 10, 3);
        add_action('admin_notices', [$this, 'seo_bulk_action_admin_notice']);
    }

    /**
     * Custom Login Logo
     */
    public function custom_login_logo()
    {
        // Example: If user uploads something, retrieve it from options. For now we use standard WordPress filter.
        $logo_url = get_option('seo_audit_login_logo', '');
        if ($logo_url) {
            echo '<style type="text/css">
                #login h1 a, .login h1 a {
                    background-image: url(' . esc_url($logo_url) . ');
                    height:65px;
                    width:320px;
                    background-size: contain;
                    background-repeat: no-repeat;
                    padding-bottom: 30px;
                }
            </style>';
        }
    }

    /**
     * Custom Admin Logo
     */
    public function custom_admin_logo()
    {
        $admin_logo = get_option('seo_audit_admin_logo', '');
        if ($admin_logo) {
            echo '<style type="text/css">
                #wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon:before {
                    content: "";
                    background-image: url(' . esc_url($admin_logo) . ');
                    background-size: contain;
                    width: 20px;
                    height: 20px;
                    display: inline-block;
                }
            </style>';
        }
    }

    /**
     * Custom Favicon
     */
    public function custom_favicon()
    {
        $favicon = get_option('seo_audit_favicon', '');
        if ($favicon) {
            echo '<link rel="shortcut icon" href="' . esc_url($favicon) . '" />';
        }
    }

    /**
     * Hide Admin Notices
     */
    public function hide_admin_notices()
    {
        if (!current_user_can('manage_options')) {
            echo '<style>.update-nag, .updated, .error, .is-dismissible { display: none !important; }</style>';
        }
    }

    /**
     * Title Enhancements: Case Formatting
     */
    public function enforce_title_case($title)
    {
        if (get_option('seo_audit_enable_title_case', true)) {
            // Capitalize first letter of each word
            return ucwords(strtolower($title));
        }
        return $title;
    }

    /**
     * Require Post Title
     */
    public function require_post_title($data, $postarr)
    {
        if (empty($data['post_title']) && $data['post_status'] !== 'trash' && $data['post_status'] !== 'auto-draft') {
            $data['post_title'] = 'Draft - ' . date('Y-m-d H:i:s');
        }
        return $data;
    }

    /**
     * Unique Title Checker
     */
    public function check_unique_title_notice()
    {
        global $post;
        if (!$post || $post->post_status !== 'publish') return;

        $args = [
            'post_type' => $post->post_type,
            'title' => $post->post_title,
            'post_status' => 'publish',
            'post__not_in' => [$post->ID]
        ];
        
        $query = new \WP_Query($args);
        if ($query->have_posts()) {
            echo '<div class="notice notice-error"><p>' . __('Warning: Another post already uses this exact title. For better SEO, use a unique title.', 'seo-audit') . '</p></div>';
        }
    }

    /**
     * YOURLS Shortlink Generation
     */
    public function generate_yourls_shortlink($shortlink, $id, $context, $allow_slugs)
    {
        $yourls_url = get_option('seo_audit_yourls_url');
        $yourls_sig = get_option('seo_audit_yourls_signature');

        if (!$yourls_url || !$yourls_sig) {
            return $shortlink;
        }

        $permalink = get_permalink($id);
        if (!$permalink) return $shortlink;

        $cached = get_post_meta($id, '_yourls_shortlink', true);
        if ($cached) return $cached;

        $api_url = "{$yourls_url}?signature={$yourls_sig}&action=shorturl&format=json&url=" . urlencode($permalink);
        $response = wp_remote_get($api_url);

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if (isset($data['shorturl'])) {
                update_post_meta($id, '_yourls_shortlink', $data['shorturl']);
                return $data['shorturl'];
            }
        }

        return $shortlink;
    }

    /**
     * Add QR Code Column
     */
    public function add_qr_code_column($columns)
    {
        $columns['qp_qr_code'] = __('QR Code', 'seo-audit');
        return $columns;
    }

    public function display_qr_code_column($column, $post_id)
    {
        if ($column === 'qp_qr_code') {
            $url = get_permalink($post_id);
            $qr_api = 'https://api.qrserver.com/v1/create-qr-code/?size=50x50&data=' . urlencode($url);
            echo '<img src="' . esc_url($qr_api) . '" width="50" height="50" alt="QR Code"/>';
        }
    }

    /**
     * WP Utilities Menu (Transients & Logs)
     */
    public function add_utility_menus()
    {
        add_management_page(
            __('SEO Plugins Transients & Logs', 'seo-audit'),
            __('SEO Utils', 'seo-audit'),
            'manage_options',
            'seo-utils',
            [$this, 'render_utility_page']
        );
    }

    public function render_utility_page()
    {
        // Simple interface to handle transients & view logs
        echo '<div class="wrap"><h1>' . __('SEO Utilities', 'seo-audit') . '</h1>';
        echo '<h2>' . __('Transients', 'seo-audit') . '</h2>';
        echo '<p><button class="button button-primary">' . __('Clear All Expired Transients', 'seo-audit') . '</button></p>';
        echo '<h2>' . __('Debug Logs', 'seo-audit') . '</h2>';
        
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file)) {
            $logs = implode("", array_slice(file($log_file), -30));
            echo '<textarea style="width:100%; height:300px; background:#000; color:#0f0;" readonly>' . esc_textarea($logs) . '</textarea>';
        } else {
            echo '<p>' . __('debug.log not found or empty.', 'seo-audit') . '</p>';
        }
        echo '</div>';
    }

    /**
     * AI Chat Assistant Widget
     */
    public function render_ai_chat_widget()
    {
        // Don't render on entirely blank screens
        if (!is_admin()) return;

        ?>
        <div id="seo-ai-chat-widget" style="position:fixed; bottom:20px; right:20px; width:300px; background:#fff; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.2); z-index:99999; display:none; flex-direction:column; overflow:hidden;">
            <div style="background:#2271b1; color:#fff; padding:12px; font-weight:bold; cursor:pointer; display:flex; justify-content:space-between; align-items:center;" id="seo-ai-chat-header">
                <span>🤖 SEO AI Assistant</span>
                <span id="seo-ai-chat-toggle">▼</span>
            </div>
            <div id="seo-ai-chat-body" style="padding:15px; height:250px; overflow-y:auto; background:#f0f0f1; font-size:13px;">
                <p><strong>AI:</strong> How can I help you with your SEO strategy today? (Powered locally by Gemini Nano)</p>
            </div>
            <div style="padding:10px; border-top:1px solid #ddd; background:#fff; display:flex;">
                <input type="text" id="seo-ai-chat-input" placeholder="Ask about titles, rankings..." style="flex:1; border:1px solid #ccc; border-radius:4px; padding:6px;">
                <button type="button" id="seo-ai-chat-send" class="button button-primary" style="margin-left:5px;">Send</button>
            </div>
        </div>

        <button id="seo-ai-chat-fab" style="position:fixed; bottom:20px; right:20px; border-radius:50%; width:60px; height:60px; background:#2271b1; color:#fff; border:none; box-shadow:0 4px 10px rgba(0,0,0,0.3); z-index:99998; cursor:pointer; font-size:24px; text-align:center; display:flex; align-items:center; justify-content:center;">🤖</button>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const fab = document.getElementById('seo-ai-chat-fab');
                const widget = document.getElementById('seo-ai-chat-widget');
                const header = document.getElementById('seo-ai-chat-header');
                const btnSend = document.getElementById('seo-ai-chat-send');
                const input = document.getElementById('seo-ai-chat-input');
                const body = document.getElementById('seo-ai-chat-body');

                if(!fab || !widget) return;

                const toggleChat = () => {
                    if(widget.style.display === 'none') {
                        widget.style.display = 'flex';
                        input.focus();
                        fab.style.display = 'none';
                    } else {
                        widget.style.display = 'none';
                        fab.style.display = 'flex';
                    }
                };

                fab.addEventListener('click', toggleChat);
                header.addEventListener('click', toggleChat);

                const appendMessage = (sender, text) => {
                    const div = document.createElement('div');
                    div.style.marginBottom = '10px';
                    div.style.background = sender === 'AI' ? '#e5e5e5' : '#d4edda';
                    div.style.padding = '8px';
                    div.style.borderRadius = '5px';
                    div.innerHTML = `<strong>${sender}:</strong> ${text}`;
                    body.appendChild(div);
                    body.scrollTop = body.scrollHeight;
                };

                const handleSend = async () => {
                    const text = input.value.trim();
                    if(!text) return;
                    
                    input.value = '';
                    appendMessage('You', text);

                    if(window.ai && window.ai.createTextSession) {
                        try {
                            const session = await window.ai.createTextSession();
                            const response = await session.prompt("You are a WordPress SEO assistant. Keep answers brief (max 2 sentences). The user asks: " + text);
                            appendMessage('AI', response);
                            session.destroy();
                        } catch(e) {
                            appendMessage('AI', "Local AI is unavailable ("+e.message+"). Please ensure Chrome Dev/Canary is configured for Gemini Nano.");
                        }
                    } else {
                        appendMessage('AI', "Local Web AI (window.ai) is not configured in this browser. You need Chrome's built-in AI enabled via flags.");
                    }
                };

                btnSend.addEventListener('click', handleSend);
                input.addEventListener('keypress', (e) => {
                    if(e.key === 'Enter') handleSend();
                });
            });
        </script>
        <?php
    }

    /**
     * Register Bulk Actions
     */
    public function register_seo_bulk_actions($bulk_actions)
    {
        $bulk_actions['mass_seo_rescan'] = __('Run SEO Rescan (Background)', 'seo-audit');
        return $bulk_actions;
    }

    /**
     * Handle Bulk Action
     */
    public function handle_seo_bulk_actions($redirect_to, $doaction, $post_ids)
    {
        if ($doaction !== 'mass_seo_rescan') {
            return $redirect_to;
        }

        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to perform this action.', 'seo-audit'));
        }

        foreach ($post_ids as $post_id) {
            update_post_meta($post_id, '_seo_audit_force_rescan', time());
        }

        $redirect_to = add_query_arg('mass_seo_rescanned', count($post_ids), $redirect_to);
        return $redirect_to;
    }
    
    /**
     * Notice for Bulk Action
     */
    public function seo_bulk_action_admin_notice()
    {
        if (!empty($_REQUEST['mass_seo_rescanned'])) {
            $rescanned = intval($_REQUEST['mass_seo_rescanned']);
            printf(
                '<div id="message" class="updated notice is-dismissible"><p>' .
                esc_html__('%d posts flagged for priority background SEO rescanning.', 'seo-audit') .
                '</p></div>',
                $rescanned
            );
        }
    }

    /**
     * Enqueue custom admin scripts for title length checking (Pixel logic)
     */
    public function enqueue_title_limit_scripts($hook)
    {
        global $post;

        if (in_array($hook, ['post.php', 'post-new.php'])) {
            wp_enqueue_script(
                'seo-audit-title-limit',
                plugin_dir_url(dirname(__DIR__, 2)) . 'assets/js/seo-audit-title-limit.js',
                ['jquery'],
                '1.0.0',
                true
            );

            $custom_css = "
                .title-length-feedback, .title-length-preview {
                    font-size: 13px;
                    margin-top: 5px;
                    padding: 8px 12px;
                    border-radius: 4px;
                    border: 1px solid #ccc;
                    background-color: #f9f9f9;
                }
                .title-length-feedback.success {
                    color: #155724;
                    background-color: #d4edda;
                    border-color: #c3e6cb;
                }
                .title-length-feedback.warning {
                    color: #856404;
                    background-color: #fff3cd;
                    border-color: #ffeeba;
                }
                .title-length-feedback.error {
                    color: #721c24;
                    background-color: #f8d7da;
                    border-color: #f5c6cb;
                }
                .title-length-preview {
                    margin-top: 10px;
                    background-color: #fff;
                    border-color: #ddd;
                    font-family: Arial, sans-serif;
                    font-size: 20px;
                    color: #1a0dab;
                    line-height: 1.3;
                    max-width: 600px; 
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .title-length-preview strong {
                    display: block;
                    font-size: 12px;
                    color: #70757a;
                    margin-bottom: 4px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .title-length-preview .preview-text {
                    display: block;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
            ";
            wp_add_inline_style('wp-admin', $custom_css);
        }
    }
}
