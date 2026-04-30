<?php

/**
 * Experiment Loader Class
 * 
 * Handles discovery and initialization of SEO experiments.
 *
 * @package SEOAudit\Core
 */

namespace SEOAudit\Core;

class Experiment_Loader
{
    /**
     * @var Experiment_Registry
     */
    private Experiment_Registry $registry;

    /**
     * Constructor.
     *
     * @param Experiment_Registry $registry The experiment registry.
     */
    public function __construct(Experiment_Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Registers default experiments.
     */
    public function register_default_experiments(): void
    {
        $default_experiments = [
            \SEOAudit\Experiments\SEO_Audit\SEO_Audit::class,
            \SEOAudit\Experiments\Readability_Analysis\Readability_Analysis::class,
            \SEOAudit\Experiments\Keyword_Check\Keyword_Check::class,
            \SEOAudit\Experiments\Media_Optimizer\Media_Optimizer::class,
            \SEOAudit\Experiments\Admin_Customizer\Admin_Customizer::class,
        ];

        foreach ($default_experiments as $experiment_class) {
            if (class_exists($experiment_class)) {
                $this->registry->register(new $experiment_class());
            }
        }
    }

    /**
     * Initializes all registered experiments.
     */
    public function initialize_experiments(): void
    {
        foreach ($this->registry->get_all() as $experiment) {
            if ($experiment->is_enabled()) {
                $experiment->register();
            }
        }
    }
}
