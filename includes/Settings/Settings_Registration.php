<?php

/**
 * Settings Registration
 *
 * Registers the WordPress Settings API groups and defaults for SEO Audit.
 *
 * @package SEOAudit\Settings
 */

namespace SEOAudit\Settings;

use SEOAudit\Core\Experiment_Registry;

class Settings_Registration
{
    private Experiment_Registry $registry;

    public function __construct(Experiment_Registry $registry)
    {
        $this->registry = $registry;
    }

    public function init(): void
    {
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings(): void
    {
        // ── Global Experiments Toggle ──────────────────────────────────────
        register_setting('seo_audit_settings', 'seo_audit_experiments_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);

        // ── API Keys ───────────────────────────────────────────────────────
        register_setting('seo_audit_settings', 'seo_audit_google_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        // ── Branding ──────────────────────────────────────────────────────
        foreach (['seo_audit_login_logo', 'seo_audit_admin_logo', 'seo_audit_favicon'] as $key) {
            register_setting('seo_audit_settings', $key, [
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            ]);
        }

        // ── YOURLS ────────────────────────────────────────────────────────
        register_setting('seo_audit_settings', 'seo_audit_yourls_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ]);
        register_setting('seo_audit_settings', 'seo_audit_yourls_signature', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        // ── Title Case ────────────────────────────────────────────────────
        register_setting('seo_audit_settings', 'seo_audit_enable_title_case', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ]);

        // ── Per-Experiment Toggles ────────────────────────────────────────
        foreach ($this->registry->get_all() as $experiment) {
            register_setting('seo_audit_settings', 'seo_audit_experiment_' . $experiment->get_id() . '_enabled', [
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => true,
            ]);
        }
    }
}
