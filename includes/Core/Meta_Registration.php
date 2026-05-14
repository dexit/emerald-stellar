<?php

/**
 * Meta Registration for RTC Compatibility
 *
 * Registers post meta keys with REST API support to enable
 * WordPress 7.0 Real-Time Collaboration (RTC) features.
 *
 * @package SEOAudit\Core
 */

namespace SEOAudit\Core;

class Meta_Registration
{
    public function init(): void
    {
        add_action('init', [$this, 'register_meta']);
    }

    public function register_meta(): void
    {
        $post_types = get_post_types(['public' => true]);

        foreach ($post_types as $post_type) {
            // SEO Score
            register_post_meta($post_type, '_seo_audit_score', [
                'type'              => 'integer',
                'single'            => true,
                'show_in_rest'      => true,
                'auth_callback'     => function () {
                    return current_user_can('edit_posts');
                },
                'sanitize_callback' => 'absint',
            ]);

            // Focus Keyword
            register_post_meta($post_type, '_seo_audit_focus_keyword', [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'auth_callback'     => function () {
                    return current_user_can('edit_posts');
                },
                'sanitize_callback' => 'sanitize_text_field',
            ]);

            // Readability Label (Easy, Moderate, Difficult)
            register_post_meta($post_type, '_seo_audit_readability', [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'auth_callback'     => function () {
                    return current_user_can('edit_posts');
                },
                'sanitize_callback' => 'sanitize_text_field',
            ]);

            // Last Run Timestamp
            register_post_meta($post_type, '_seo_audit_last_run', [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'auth_callback'     => function () {
                    return current_user_can('edit_posts');
                },
                'sanitize_callback' => 'sanitize_text_field',
            ]);

            // Word Count
            register_post_meta($post_type, '_seo_audit_word_count', [
                'type'              => 'integer',
                'single'            => true,
                'show_in_rest'      => true,
                'auth_callback'     => function () {
                    return current_user_can('edit_posts');
                },
                'sanitize_callback' => 'absint',
            ]);

            // Force Rescan Flag
            register_post_meta($post_type, '_seo_audit_force_rescan', [
                'type'              => 'boolean',
                'single'            => true,
                'show_in_rest'      => true,
                'auth_callback'     => function () {
                    return current_user_can('edit_posts');
                },
                'sanitize_callback' => 'rest_sanitize_boolean',
            ]);
        }
    }
}
