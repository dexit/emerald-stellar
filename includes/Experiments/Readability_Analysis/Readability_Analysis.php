<?php

/**
 * Readability Analysis Experiment Implementation
 * 
 * @package SEOAudit\Experiments\Readability_Analysis
 */

namespace SEOAudit\Experiments\Readability_Analysis;

use SEOAudit\Abstracts\Abstract_Experiment;

class Readability_Analysis extends Abstract_Experiment
{
    protected function load_experiment_metadata(): array
    {
        return [
            'id'          => 'readability-analysis',
            'label'       => __('Flesch-Kincaid & Reading Ease', 'seo-audit'),
            'description' => __('Performs advanced content readability scores including word count ratios, transition words, and passive voice checks.', 'seo-audit'),
        ];
    }

    public function register(): void
    {
        // Readability analysis is executed by the central SEO_Audit orchestrator
        // This experiment manages readability-specific UI toggles or Elementor bridges.
        add_filter('seo_audit_enable_readability', '__return_true');
    }
}
