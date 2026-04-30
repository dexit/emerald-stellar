<?php

/**
 * SEO Audit Plugin Bootstrap
 * 
 * Based on WordPress AI Experiments plugin architecture
 * Implements experiment framework with built-in AI support
 *
 * @package SEOAudit
 * @wordpress-plugin
 * Plugin Name:       SEO Audit with AI
 * Plugin URI:        https://github.com/yourusername/seo-audit-ai
 * Description:       Comprehensive SEO audit system with AI-powered recommendations using Chrome's built-in AI and external providers.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL-2.0-or-later
 * Text Domain:       seo-audit
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('SEO_AUDIT_VERSION')) {
    define('SEO_AUDIT_VERSION', '1.0.0');
}
if (!defined('SEO_AUDIT_DIR')) {
    define('SEO_AUDIT_DIR', plugin_dir_path(__FILE__));
}
if (!defined('SEO_AUDIT_URL')) {
    define('SEO_AUDIT_URL', plugin_dir_url(__FILE__));
}
if (!defined('SEO_AUDIT_PLUGIN_FILE')) {
    define('SEO_AUDIT_PLUGIN_FILE', __FILE__);
}
if (!defined('SEO_AUDIT_MIN_PHP_VERSION')) {
    define('SEO_AUDIT_MIN_PHP_VERSION', '7.4');
}
if (!defined('SEO_AUDIT_MIN_WP_VERSION')) {
    define('SEO_AUDIT_MIN_WP_VERSION', '6.4');
}

/**
 * Check PHP version
 */
function seo_audit_check_php_version()
{
    if (version_compare(phpversion(), SEO_AUDIT_MIN_PHP_VERSION, '<')) {
        add_action('admin_notices', function () {
?>
            <div class="notice notice-error">
                <p>
                    <?php
                    printf(
                        esc_html__('SEO Audit plugin requires PHP version %1$s or higher. You are running PHP version %2$s.', 'seo-audit'),
                        SEO_AUDIT_MIN_PHP_VERSION,
                        PHP_VERSION
                    );
                    ?>
                </p>
            </div>
        <?php
        });
        return false;
    }
    return true;
}

/**
 * Check WordPress version
 */
function seo_audit_check_wp_version()
{
    global $wp_version;
    if (version_compare($wp_version, SEO_AUDIT_MIN_WP_VERSION, '<')) {
        add_action('admin_notices', function () {
            global $wp_version;
        ?>
            <div class="notice notice-error">
                <p>
                    <?php
                    printf(
                        esc_html__('SEO Audit plugin requires WordPress version %1$s or higher. You are running WordPress version %2$s.', 'seo-audit'),
                        SEO_AUDIT_MIN_WP_VERSION,
                        $wp_version
                    );
                    ?>
                </p>
            </div>
        <?php
        });
        return false;
    }
    return true;
}

/**
 * Check for Composer autoload
 */
function seo_audit_check_composer()
{
    if (!file_exists(SEO_AUDIT_DIR . 'vendor/autoload.php')) {
        add_action('admin_notices', function () {
        ?>
            <div class="notice notice-error">
                <p>
                    <?php
                    printf(
                        esc_html__('Your installation of the SEO Audit plugin is incomplete. Please run %s.', 'seo-audit'),
                        '<code>composer install</code>'
                    );
                    ?>
                </p>
            </div>
<?php
        });
        return false;
    }
    return true;
}

/**
 * Add plugin action links
 */
function seo_audit_plugin_action_links($links)
{
    $settings_link = sprintf(
        '<a href="%1$s">%2$s</a>',
        admin_url('options-general.php?page=seo-audit-settings'),
        esc_html__('Settings', 'seo-audit')
    );

    array_unshift($links, $settings_link);

    return $links;
}

/**
 * Load the plugin
 */
function seo_audit_load()
{
    static $loaded = false;

    // Prevent loading twice
    if ($loaded) {
        return;
    }

    // Check version requirements
    if (!seo_audit_check_php_version() || !seo_audit_check_wp_version()) {
        return;
    }

    // Load Composer autoloader
    if (seo_audit_check_composer()) {
        require_once SEO_AUDIT_DIR . 'vendor/autoload.php';
    } else {
        return;
    }

    $loaded = true;

    // Add plugin action links
    add_filter('plugin_action_links_' . plugin_basename(SEO_AUDIT_PLUGIN_FILE), 'seo_audit_plugin_action_links');

    // Initialize on init hook
    add_action('init', 'seo_audit_initialize');
}

/**
 * Initialize the plugin
 */
function seo_audit_initialize()
{
    try {
        // Initialize experiment registry
        $registry = new \SEOAudit\Core\Experiment_Registry();
        $loader = new \SEOAudit\Core\Experiment_Loader($registry);

        // Register default experiments
        $loader->register_default_experiments();
        $loader->initialize_experiments();

        // Initialize settings
        $settings_registration = new \SEOAudit\Settings\Settings_Registration($registry);
        $settings_registration->init();

        // Initialize admin settings page
        if (is_admin()) {
            $settings_page = new \SEOAudit\Settings\Settings_Page($registry);
            $settings_page->init();

            // Initialize admin dashboard
            $admin_dashboard = new \SEOAudit\Admin\Admin_Dashboard();
            $admin_dashboard->init();
        }

        // Initialize Elementor integration if Elementor is active
        if (did_action('elementor/loaded')) {
            require_once SEO_AUDIT_DIR . 'elementor/class-seo-audit-controller.php';
        }
    } catch (\Throwable $t) {
        _doing_it_wrong(
            'seo_audit_initialize',
            sprintf(
                esc_html__('SEO Audit plugin initialization failed: %s', 'seo-audit'),
                esc_html($t->getMessage())
            ),
            '1.0.0'
        );
    }
}

// Hook into plugins_loaded
add_action('plugins_loaded', 'seo_audit_load');

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function () {
    // Set default options
    add_option('seo_audit_experiments_enabled', false);
    add_option('seo_audit_install_date', current_time('mysql'));
    add_option('seo_audit_version', SEO_AUDIT_VERSION);

    // Flush rewrite rules
    flush_rewrite_rules();
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function () {
    // Flush rewrite rules
    flush_rewrite_rules();
});
