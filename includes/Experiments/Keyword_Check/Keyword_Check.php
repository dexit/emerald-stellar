<?php

/**
 * Keyword Check Experiment Implementation
 * 
 * @package SEOAudit\Experiments\Keyword_Check
 */

namespace SEOAudit\Experiments\Keyword_Check;

use SEOAudit\Abstracts\Abstract_Experiment;

class Keyword_Check extends Abstract_Experiment
{
    protected function load_experiment_metadata(): array
    {
        return [
            'id'          => 'keyword-check',
            'label'       => __('Keyword Density & Prominence', 'seo-audit'),
            'description' => __('Analyzes the content for target keyword density, distribution, and headings (H1-H6) mapping.', 'seo-audit'),
        ];
    }

    public function register(): void
    {
        // Keyword checking logic is heavily utilized via the central SEO_Audit orchestrator 
        // to minimize redundant DOM traversal on the frontend. 
        // This experiment manages keyword-specific settings.
        add_filter('seo_audit_enable_keyword_check', '__return_true');
    }
}
