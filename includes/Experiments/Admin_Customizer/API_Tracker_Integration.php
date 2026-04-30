<?php

/**
 * External API Manager & Settings Panel
 * 
 * Provides integrations with Google Analytics, GSC, CrUX, Bing, and MS Clarity.
 *
 * @package SEOAudit\Experiments\Admin_Customizer
 */

namespace SEOAudit\Experiments\Admin_Customizer;

class API_Tracker_Integration
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('wp_head', [$this, 'inject_tracking_scripts']);
    }

    public function register_settings_page(): void
    {
        add_menu_page(
            __('SEO APIs & Tracking', 'seo-audit'),
            __('SEO Tracking', 'seo-audit'),
            'manage_options',
            'seo-audit-tracking-settings',
            [$this, 'render_settings_page'],
            'dashicons-chart-pie',
            80
        );

        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings(): void
    {
        register_setting('seo_audit_tracking', 'seo_audit_ga_id', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('seo_audit_tracking', 'seo_audit_clarity_id', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('seo_audit_tracking', 'seo_audit_bing_verification', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('seo_audit_tracking', 'seo_audit_gsc_verification', ['sanitize_callback' => 'sanitize_text_field']);
        
        // Settings Section
        add_settings_section('seo_audit_tracking_main', __('External API Identifiers', 'seo-audit'), null, 'seo-audit-tracking-settings');

        add_settings_field('seo_audit_ga_id', __('Google Analytics Measurement ID', 'seo-audit'), [$this, 'render_input_field'], 'seo-audit-tracking-settings', 'seo_audit_tracking_main', ['name' => 'seo_audit_ga_id', 'placeholder' => 'G-ABC...']);
        add_settings_field('seo_audit_clarity_id', __('Microsoft Clarity Project ID', 'seo-audit'), [$this, 'render_input_field'], 'seo-audit-tracking-settings', 'seo_audit_tracking_main', ['name' => 'seo_audit_clarity_id']);
        add_settings_field('seo_audit_gsc_verification', __('Google Search Console Tag', 'seo-audit'), [$this, 'render_input_field'], 'seo-audit-tracking-settings', 'seo_audit_tracking_main', ['name' => 'seo_audit_gsc_verification']);
        add_settings_field('seo_audit_bing_verification', __('Bing Webmaster Tools Tag', 'seo-audit'), [$this, 'render_input_field'], 'seo-audit-tracking-settings', 'seo_audit_tracking_main', ['name' => 'seo_audit_bing_verification']);
    }

    public function render_input_field(array $args): void
    {
        $value = get_option($args['name'], '');
        $placeholder = $args['placeholder'] ?? '';
        echo '<input type="text" name="' . esc_attr($args['name']) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '" />';
    }

    public function render_settings_page(): void
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('SEO Audit API Tracking Integrations', 'seo-audit'); ?></h1>
            <p><?php esc_html_e('Integrate directly with Google Analytics, Search Console, Bing Webmaster tools, and Microsoft Clarity dynamically.', 'seo-audit'); ?></p>
            <form method="post" action="options.php">
                <?php
                settings_fields('seo_audit_tracking');
                do_settings_sections('seo-audit-tracking-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function inject_tracking_scripts(): void
    {
        // 1. Google Analytics
        $ga_id = get_option('seo_audit_ga_id', '');
        if (!empty($ga_id)) {
            echo "<!-- Google tag (gtag.js) -->\n";
            echo "<script async src='https://www.googletagmanager.com/gtag/js?id=" . esc_attr($ga_id) . "'></script>\n";
            echo "<script>\n";
            echo "  window.dataLayer = window.dataLayer || [];\n";
            echo "  function gtag(){dataLayer.push(arguments);}\n";
            echo "  gtag('js', new Date());\n";
            echo "  gtag('config', '" . esc_attr($ga_id) . "');\n";
            echo "</script>\n";
        }

        // 2. Microsoft Clarity
        $clarity_id = get_option('seo_audit_clarity_id', '');
        if (!empty($clarity_id)) {
            echo "<!-- Microsoft Clarity -->\n";
            echo "<script type='text/javascript'>\n";
            echo "    (function(c,l,a,r,i,t,y){\n";
            echo "        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};\n";
            echo "        t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;\n";
            echo "        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);\n";
            echo "    })(window, document, 'clarity', 'script', '" . esc_attr($clarity_id) . "');\n";
            echo "</script>\n";
        }

        // 3. Webmaster Verifications (GSC & Bing)
        $gsc = get_option('seo_audit_gsc_verification', '');
        if (!empty($gsc)) {
            echo '<meta name="google-site-verification" content="' . esc_attr($gsc) . '" />' . "\n";
        }

        $bing = get_option('seo_audit_bing_verification', '');
        if (!empty($bing)) {
            echo '<meta name="msvalidate.01" content="' . esc_attr($bing) . '" />' . "\n";
        }
    }
}
