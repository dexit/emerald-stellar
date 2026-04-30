<?php

/**
 * Experiment Registry Class
 * 
 * Manages the registration and retrieval of SEO experiments.
 *
 * @package SEOAudit\Core
 */

namespace SEOAudit\Core;

use SEOAudit\Contracts\Experiment;

class Experiment_Registry
{
    /**
     * @var array<string, Experiment>
     */
    private array $experiments = [];

    /**
     * Registers an experiment.
     *
     * @param Experiment $experiment The experiment instance.
     */
    public function register(Experiment $experiment): void
    {
        $this->experiments[$experiment->get_id()] = $experiment;
    }

    /**
     * Gets all registered experiments.
     *
     * @return array<string, Experiment>
     */
    public function get_all(): array
    {
        return $this->experiments;
    }

    /**
     * Gets a specific experiment by ID.
     *
     * @param string $id Experiment identifier.
     * @return Experiment|null
     */
    public function get(string $id): ?Experiment
    {
        return $this->experiments[$id] ?? null;
    }
}
