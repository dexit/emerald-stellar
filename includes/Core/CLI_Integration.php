<?php

/**
 * WP-CLI Integration
 *
 * Provides command-line interfaces for running mass audits 
 * and managing SEO configurations.
 *
 * @package SEOAudit\Core
 */

namespace SEOAudit\Core;

if (!defined('WP_CLI')) {
    return;
}

class CLI_Integration
{
    /**
     * Run SEO Audit for all published posts and pages.
     *
     * ## OPTIONS
     *
     * [--force]
     * : Force rescan even if an audit already exists.
     *
     * [--post_type=<type>]
     * : Limit audit to specific post type. Default is 'post,page'.
     *
     * ## EXAMPLES
     *
     *     wp seo-audit run --force
     *     wp seo-audit run --post_type=product
     */
    public function run($args, $assoc_args)
    {
        $force = isset($assoc_args['force']);
        $post_types = isset($assoc_args['post_type']) ? explode(',', $assoc_args['post_type']) : ['post', 'page'];

        \WP_CLI::log('Starting mass SEO audit...');

        $query_args = [
            'post_type'   => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields'      => 'ids',
        ];

        if (!$force) {
            $query_args['meta_query'] = [
                [
                    'key'     => '_seo_audit_score',
                    'compare' => 'NOT EXISTS',
                ],
            ];
        }

        $post_ids = get_posts($query_args);
        $count = count($post_ids);

        if ($count === 0) {
            \WP_CLI::success('All content already audited.');
            return;
        }

        $progress = \WP_CLI\Utils\make_progress_bar("Auditing {$count} posts", $count);
        $audit_service = new \SEOAudit\Services\Audit_Service();

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                $progress->tick();
                continue;
            }

            $results = $audit_service->perform_full_audit($post->post_content, get_permalink($post_id));
            
            // Persist
            $seo_checks    = $results['seo'] ?? [];
            $passed_count  = count(array_filter($seo_checks, fn($c) => !empty($c['passed'])));
            $total_checks  = max(1, count($seo_checks));
            $seo_score     = round(($passed_count / $total_checks) * 100);

            $ease          = $results['readability']['flesch_reading_ease'] ?? 0;
            if ($ease >= 70)      $readability_label = 'Easy';
            elseif ($ease >= 50)  $readability_label = 'Moderate';
            else                  $readability_label = 'Difficult';

            update_post_meta($post_id, '_seo_audit_score',       $seo_score);
            update_post_meta($post_id, '_seo_audit_readability',  $readability_label);
            update_post_meta($post_id, '_seo_audit_last_run',     current_time('mysql'));
            update_post_meta($post_id, '_seo_audit_word_count',   $results['readability']['word_count'] ?? 0);

            $progress->tick();
        }

        $progress->finish();
        \WP_CLI::success("Mass audit completed for {$count} posts.");
    }
}

\WP_CLI::add_command('seo-audit', CLI_Integration::class);
