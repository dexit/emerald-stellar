<?php

/**
 * Abstract Experiment Base Class
 * 
 * Based on WordPress AI Experiments architecture.
 *
 * @package SEOAudit\Abstracts
 */

namespace SEOAudit\Abstracts;

use SEOAudit\Contracts\Experiment;

abstract class Abstract_Experiment implements Experiment
{
    protected string $id;
    protected string $label;
    protected string $description;
    private ?bool $enabled_cache = null;

    final public function __construct()
    {
        $metadata = $this->load_experiment_metadata();
        $this->id = $metadata['id'];
        $this->label = $metadata['label'];
        $this->description = $metadata['description'];
    }

    abstract protected function load_experiment_metadata(): array;

    public function get_id(): string
    {
        return $this->id;
    }

    public function get_label(): string
    {
        return $this->label;
    }

    public function get_description(): string
    {
        return $this->description;
    }

    final public function is_enabled(): bool
    {
        if (null !== $this->enabled_cache) {
            return $this->enabled_cache;
        }

        // Check global setting and individual setting
        $global_enabled = (bool) get_option('seo_audit_experiments_enabled', false);
        if (!$global_enabled) {
            $this->enabled_cache = false;
            return false;
        }

        $experiment_enabled = (bool) get_option("seo_audit_experiment_{$this->id}_enabled", true);
        $this->enabled_cache = $experiment_enabled;

        return $this->enabled_cache;
    }

    abstract public function register(): void;
}
