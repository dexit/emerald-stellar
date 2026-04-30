<?php

/**
 * Elementor SEO Audit Controller
 * 
 * Based on AI Auto Content Generator for Elementor implementation
 * Uses Chrome's built-in AI (Gemini Nano) for local AI processing
 *
 * @package SEOAudit
 */

namespace SEOAudit\Elementor;

use Elementor\Controls_Manager;

if (!class_exists('SEO_Audit_Controllers')) {
    class SEO_Audit_Controllers
    {
        private static $instance = null;

        /**
         * Singleton pattern
         */
        public static function get_instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Constructor - Hook into Elementor
         */
        public function __construct()
        {
            // Add SEO audit button to text editor widget
            add_action('elementor/element/text-editor/section_editor/after_section_start', [$this, 'register_seo_audit_button'], 10, 2);

            // Add SEO audit button to heading widget
            add_action('elementor/element/heading/section_title/after_section_start', [$this, 'register_seo_audit_button'], 10, 2);

            // Add SEO audit panel to all widgets
            add_action('elementor/element/common/_section_style/after_section_end', [$this, 'register_seo_audit_panel'], 10, 2);

            // Enqueue scripts and styles for Elementor editor
            add_action('elementor/preview/enqueue_scripts', [$this, 'enqueue_editor_scripts']);
            add_action('elementor/preview/enqueue_styles', [$this, 'enqueue_editor_styles']);

            // AJAX handlers for SEO audit
            add_action('wp_ajax_seo_audit_analyze_content', [$this, 'ajax_analyze_content']);
            add_action('wp_ajax_seo_audit_get_recommendations', [$this, 'ajax_get_recommendations']);
        }

        /**
         * Register SEO Audit button in Elementor controls
         */
        public function register_seo_audit_button($element)
        {
            $element->add_control(
                'seo_audit_analyze',
                [
                    'type' => Controls_Manager::BUTTON,
                    'label' => '',
                    'separator' => 'before',
                    'show_label' => false,
                    'text' => sprintf(
                        '%s <i class="eicon-search seo-audit-icon" aria-hidden="true"></i>',
                        esc_html__('SEO Audit with AI', 'seo-audit')
                    ),
                    'button_type' => 'default',
                    'event' => 'seo:audit:analyze'
                ]
            );
        }

        /**
         * Register SEO Audit panel for all widgets
         */
        public function register_seo_audit_panel($element)
        {
            $element->start_controls_section(
                'section_seo_audit',
                [
                    'label' => esc_html__('SEO Audit', 'seo-audit'),
                    'tab' => Controls_Manager::TAB_ADVANCED,
                ]
            );

            $element->add_control(
                'seo_score_display',
                [
                    'type' => Controls_Manager::RAW_HTML,
                    'raw' => '<div id="seo-score-widget" class="seo-score-widget">
                        <div class="seo-score-header">
                            <h4>SEO Score</h4>
                            <span class="seo-score-value">--</span>
                        </div>
                        <div class="seo-metrics">
                            <div class="metric">
                                <span class="metric-label">Readability:</span>
                                <span class="metric-value" data-metric="readability">--</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Keyword Density:</span>
                                <span class="metric-value" data-metric="keyword">--</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Content Length:</span>
                                <span class="metric-value" data-metric="length">--</span>
                            </div>
                        </div>
                        <button class="seo-audit-btn" onclick="triggerSEOAudit()">
                            <i class="eicon-ai"></i> Analyze with AI
                        </button>
                    </div>',
                ]
            );

            $element->end_controls_section();
        }

        /**
         * Enqueue JavaScript for Elementor editor
         */
        public function enqueue_editor_scripts()
        {
            wp_enqueue_script(
                'seo-audit-elementor',
                plugin_dir_url(__FILE__) . '../assets/js/seo-audit-elementor.js',
                ['jquery', 'wp-i18n'],
                '1.0.0',
                true
            );

            // SweetAlert2 for beautiful modals
            wp_enqueue_script(
                'seo-audit-sweetalert',
                'https://cdn.jsdelivr.net/npm/sweetalert2@11',
                [],
                '11.0.0',
                false
            );

            // Localize script with AJAX URL and nonce
            wp_localize_script('seo-audit-elementor', 'seoAuditData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('seo_audit_nonce'),
                'pluginUrl' => plugin_dir_url(__FILE__)
            ]);
        }

        /**
         * Enqueue CSS for Elementor editor
         */
        public function enqueue_editor_styles()
        {
            wp_enqueue_style(
                'seo-audit-elementor',
                plugin_dir_url(__FILE__) . '../assets/css/seo-audit-elementor.css',
                [],
                '1.0.0',
                'all'
            );
        }

        /**
         * AJAX: Analyze content
         */
        public function ajax_analyze_content()
        {
            check_ajax_referer('seo_audit_nonce', 'nonce');

            $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

            if (empty($content)) {
                wp_send_json_error(['message' => 'No content provided']);
            }

            // Use our readability auditor
            $readability_auditor = new \SEOAudit\Audits\ReadabilityAuditor();
            $results = $readability_auditor->analyze_content($content);

            wp_send_json_success($results);
        }

        /**
         * AJAX: Get AI-powered recommendations
         */
        public function ajax_get_recommendations()
        {
            check_ajax_referer('seo_audit_nonce', 'nonce');

            $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
            $audit_results = isset($_POST['audit_results']) ? json_decode(stripslashes($_POST['audit_results']), true) : [];

            if (empty($content)) {
                wp_send_json_error(['message' => 'No content provided']);
            }

            // Generate recommendations based on audit results
            $recommendations = $this->generate_recommendations($audit_results);

            wp_send_json_success(['recommendations' => $recommendations]);
        }

        /**
         * Generate SEO recommendations
         */
        private function generate_recommendations($audit_results)
        {
            $recommendations = [];

            // Readability recommendations
            if (isset($audit_results['flesch_reading_ease']['score'])) {
                $score = $audit_results['flesch_reading_ease']['score'];
                if ($score < 60) {
                    $recommendations[] = [
                        'type' => 'readability',
                        'severity' => 'high',
                        'message' => 'Content is difficult to read. Use shorter sentences and simpler words.',
                        'action' => 'Simplify language'
                    ];
                }
            }

            // Word count recommendations
            if (isset($audit_results['word_count'])) {
                $word_count = $audit_results['word_count'];
                if ($word_count < 300) {
                    $recommendations[] = [
                        'type' => 'content_length',
                        'severity' => 'medium',
                        'message' => "Content is too short ({$word_count} words). Aim for at least 300 words.",
                        'action' => 'Expand content'
                    ];
                }
            }

            // Sentence length recommendations
            if (isset($audit_results['avg_words_per_sentence']['average'])) {
                $avg = $audit_results['avg_words_per_sentence']['average'];
                if ($avg > 20) {
                    $recommendations[] = [
                        'type' => 'sentence_length',
                        'severity' => 'medium',
                        'message' => "Sentences are too long (avg {$avg} words). Break them into shorter sentences.",
                        'action' => 'Shorten sentences'
                    ];
                }
            }

            return $recommendations;
        }
    }
}

// Initialize the controller
SEO_Audit_Controllers::get_instance();
