<?php

/**
 * Experiment Contract
 * 
 * @package SEOAudit\Contracts
 */

namespace SEOAudit\Contracts;

interface Experiment
{
    /**
     * Gets the experiment ID.
     */
    public function get_id(): string;

    /**
     * Gets the experiment label.
     */
    public function get_label(): string;

    /**
     * Gets the experiment description.
     */
    public function get_description(): string;

    /**
     * Checks if experiment is enabled.
     */
    public function is_enabled(): bool;

    /**
     * Registers the experiment functionality.
     */
    public function register(): void;
}
