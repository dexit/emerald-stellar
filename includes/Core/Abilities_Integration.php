<?php

/**
 * Abilities API Integration
 *
 * Registers SEO Audit functions as standardized "Abilities" 
 * for WordPress 7.0+ AI Agent discovery and execution.
 *
 * @package SEOAudit\Core
 */

namespace SEOAudit\Core;

class Abilities_Integration
{
    public function init(): void
    {
        // Register category
        add_action('wp_abilities_api_categories_init', [$this, 'register_category']);
        
        // Register individual abilities
        add_action('wp_abilities_api_init', [$this, 'register_abilities']);
    }

    public function register_category(): void
    {
        if (!function_exists('wp_register_ability_category')) {
            return;
        }

        wp_register_ability_category('seo-optimization', [
            'label'       => __('SEO & Optimization', 'seo-audit'),
            'description' => __('Abilities for analyzing and improving site SEO', 'seo-audit'),
        ]);
    }

    public function register_abilities(): void
    {
        if (!function_exists('wp_register_ability')) {
            return;
        }

        // Ability: Run Full SEO Audit
        wp_register_ability('seo-audit/run-full', [
            'label'       => __('Run Full SEO Audit', 'seo-audit'),
            'description' => __('Performs a comprehensive SEO audit of a specific post or content string.', 'seo-audit'),
            'category'    => 'seo-optimization',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer', 'description' => 'The ID of the post to audit'],
                    'content' => ['type' => 'string', 'description' => 'Optional raw content to audit instead of post content'],
                    'url'     => ['type' => 'string', 'description' => 'Optional URL for external checks'],
                ],
                'required'   => ['post_id']
            ],
            'output_schema' => [
                'type'       => 'object',
                'properties' => [
                    'score'       => ['type' => 'integer'],
                    'readability' => ['type' => 'string'],
                    'suggestions' => ['type' => 'array', 'items' => ['type' => 'string']],
                ]
            ],
            'execute_callback'   => [$this, 'handle_run_audit_ability'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);
    }

    /**
     * Handler for the "Run Audit" ability
     */
    public function handle_run_audit_ability(array $input): array
    {
        $post_id = isset($input['post_id']) ? absint($input['post_id']) : 0;
        $content = isset($input['content']) ? wp_kses_post($input['content']) : '';
        $url     = isset($input['url'])     ? esc_url($input['url']) : '';

        if (!$post_id && empty($content)) {
            return [
                'error' => __('Missing required post_id or content.', 'seo-audit')
            ];
        }

        // If post_id provided but no content, fetch it
        if ($post_id && empty($content)) {
            $post = get_post($post_id);
            if ($post) {
                $content = $post->post_content;
                if (empty($url)) {
                    $url = get_permalink($post_id);
                }
            }
        }

        // Execute the core audit logic using the service
        $audit_service = new \SEOAudit\Services\Audit_Service();
        $results = $audit_service->perform_full_audit($content, $url);
        
        $seo_checks    = $results['seo'] ?? [];
        $passed_count  = count(array_filter($seo_checks, fn($c) => !empty($c['passed'])));
        $total_checks  = max(1, count($seo_checks));
        $seo_score     = round(($passed_count / $total_checks) * 100);

        return [
            'score'       => $seo_score,
            'readability' => $results['readability']['flesch_reading_ease'] ?? 0,
            'suggestions' => array_column(array_filter($seo_checks, fn($c) => empty($c['passed'])), 'message'),
        ];
    }
}
