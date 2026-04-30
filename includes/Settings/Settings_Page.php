<?php

/**
 * Settings Page
 *
 * Renders the full plugin settings admin page under Settings > SEO Audit.
 *
 * @package SEOAudit\Settings
 */

namespace SEOAudit\Settings;

use SEOAudit\Core\Experiment_Registry;

class Settings_Page
{
    private Experiment_Registry $registry;

    public function __construct(Experiment_Registry $registry)
    {
        $this->registry = $registry;
    }

    public function init(): void
    {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_settings_page(): void
    {
        add_submenu_page(
            'seo-audit-dashboard',
            __('SEO Audit Settings', 'seo-audit'),
            __('Settings', 'seo-audit'),
            'manage_options',
            'seo-audit-settings',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_assets($hook): void
    {
        if ($hook !== 'seo-audit_page_seo-audit-settings') {
            return;
        }
        wp_enqueue_media();
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>⚙️ <?php esc_html_e('SEO Audit Settings', 'seo-audit'); ?></h1>

            <?php settings_errors('seo_audit_settings'); ?>

            <form method="post" action="options.php">
                <?php settings_fields('seo_audit_settings'); ?>

                <!-- ── GLOBAL SWITCH ────────────────────────────────────── -->
                <h2 class="nav-tab-wrapper" style="border-bottom:none;margin-bottom:20px;">
                    <?php esc_html_e('Plugin Configuration', 'seo-audit'); ?>
                </h2>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Experiments', 'seo-audit'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="seo_audit_experiments_enabled" value="1"
                                    <?php checked(get_option('seo_audit_experiments_enabled', false)); ?>>
                                <?php esc_html_e('Activate all registered SEO experiments', 'seo-audit'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Google PageSpeed API Key', 'seo-audit'); ?></th>
                        <td>
                            <input type="text" class="regular-text"
                                   name="seo_audit_google_api_key"
                                   value="<?php echo esc_attr(get_option('seo_audit_google_api_key', '')); ?>"
                                   placeholder="AIza...">
                            <p class="description">
                                <?php esc_html_e('Required for PageSpeed Insights & CrUX field data. Get yours at console.cloud.google.com.', 'seo-audit'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Title Case Enforcement', 'seo-audit'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="seo_audit_enable_title_case" value="1"
                                    <?php checked(get_option('seo_audit_enable_title_case', true)); ?>>
                                <?php esc_html_e('Auto-capitalize post titles on save', 'seo-audit'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <!-- ── BRANDING ─────────────────────────────────────────── -->
                <h2><?php esc_html_e('Branding', 'seo-audit'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Login Page Logo URL', 'seo-audit'); ?></th>
                        <td>
                            <input type="url" class="regular-text" name="seo_audit_login_logo"
                                   value="<?php echo esc_attr(get_option('seo_audit_login_logo', '')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Admin Bar Logo URL', 'seo-audit'); ?></th>
                        <td>
                            <input type="url" class="regular-text" name="seo_audit_admin_logo"
                                   value="<?php echo esc_attr(get_option('seo_audit_admin_logo', '')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Custom Favicon URL', 'seo-audit'); ?></th>
                        <td>
                            <input type="url" class="regular-text" name="seo_audit_favicon"
                                   value="<?php echo esc_attr(get_option('seo_audit_favicon', '')); ?>">
                        </td>
                    </tr>
                </table>

                <!-- ── YOURLS ───────────────────────────────────────────── -->
                <h2><?php esc_html_e('YOURLS Short Links', 'seo-audit'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('YOURLS API URL', 'seo-audit'); ?></th>
                        <td>
                            <input type="url" class="regular-text" name="seo_audit_yourls_url"
                                   value="<?php echo esc_attr(get_option('seo_audit_yourls_url', '')); ?>"
                                   placeholder="https://your.domain/yourls-api.php">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('YOURLS Signature Token', 'seo-audit'); ?></th>
                        <td>
                            <input type="text" class="regular-text" name="seo_audit_yourls_signature"
                                   value="<?php echo esc_attr(get_option('seo_audit_yourls_signature', '')); ?>">
                        </td>
                    </tr>
                </table>

                <!-- ── EXPERIMENT TOGGLES ────────────────────────────────── -->
                <h2><?php esc_html_e('Experiment Modules', 'seo-audit'); ?></h2>
                <p><?php esc_html_e('Toggle individual experiment modules independently. The "Enable Experiments" master switch must also be on.', 'seo-audit'); ?></p>
                <table class="form-table" role="presentation">
                    <?php foreach ($this->registry->get_all() as $experiment) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($experiment->get_label()); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="seo_audit_experiment_<?php echo esc_attr($experiment->get_id()); ?>_enabled"
                                           value="1"
                                        <?php checked(get_option('seo_audit_experiment_' . $experiment->get_id() . '_enabled', true)); ?>>
                                    <?php echo esc_html($experiment->get_description()); ?>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
