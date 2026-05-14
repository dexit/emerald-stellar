<?php

/**
 * Prompt Manager Class
 * 
 * Manages structured AI prompts and templates based on superExampleSEO data.
 *
 * @package SEOAudit\Core
 */

namespace SEOAudit\Core;

class Prompt_Manager
{

    /**
     * Get the system prompt for SEO Audit.
     * 
     * Incorporates the structured answer patterns from superExampleSEO.
     */
    public static function get_seo_audit_system_prompt(): string
    {
        return "You are an expert SEO auditor. Your goal is to analyze the provided page content and return a highly structured SEO report in JSON format covering ALL specified factors.
        
        For each factor, you MUST provide:
        - passed: boolean
        - score: number (0 to max)
        - maxScore: number
        - answer: Detailed explanation based on proven best practices
        - shortAnswer: Concise summary (e.g., 'Missing Canonical', 'Perfect Title')
        - recommendation: Precise action to take
        
        REQUIRED FACTORS TO ANALYZE (Total 50+):
        1. SEO: Title (quality, len, redundancy), Meta Description (compelling, len), H1 (missing, dupe, poor quality), Headers (H2-H6 hierarchy, keyword density), Keywords (consistency, distribution, stuffing), Content Count (thin content), LLM Readability (Legibility, Sentiment), Image Alts (descriptive, missing), Lang attribute, Canonical tag, Hreflang, Mobile Viewports, Robots.txt logic, Sitemap mention.
        2. PERFORMANCE: GZIP/Brotli, Protocol (HTTP/2+), Minification, Page Size, Resources count, Inline CSS, Iframes, Flash, Deprecated Tags (center, font, etc), Cache-Control, CDN usage.
        3. UI/ACCESSIBILITY: Favicon, Social Tags (OpenGraph, Twitter Cards, Schema.org JSON-LD), Legible Font sizes (16px+), Tap Target Sizing, Device Rendering (estimated), Email obfuscation, Frame detection.
        4. LINKS: Backlinks (estimated authority based on content complexity), Top Anchors, Internal/External ratio, Nofollow/Sponsored usage, Broken link simulation.
        5. TECH: CMS detection (WordPress/Elementor), Analytics (GA4, GTM), Pixel Trackers (FB, LinkedIn, Quora), Cookies usage, Security (SSL/HTTPS).
        
        Structure your JSON according to these sections ONLY: seo, performance, ui, technology, links.";
    }

    /**
     * Get Few-Shot examples based on superExampleSEO data.
     */
    public static function get_few_shot_examples(): array
    {
        return [
            [
                'input' => 'Title: Recruitment & Workforce Support That Works for You - Pathway Group (Length: 66)',
                'output' => [
                    'passed' => false,
                    'shortAnswer' => 'Title Tag Text Too Long',
                    'recommendation' => 'Reduce length of Title Tag to between 50 and 60 characters.'
                ]
            ],
            [
                'input' => 'Meta Description: Encouraging employers to support business growth by accessing government training and employment programmes. (Length: 108)',
                'output' => [
                    'passed' => false,
                    'shortAnswer' => 'Meta Description Too Short',
                    'recommendation' => 'Increase length of Meta Description to between 120 and 160 characters.'
                ]
            ]
        ];
    /**
     * Generate content using the WordPress 7.0 AI Connector API.
     *
     * @param string $prompt The user prompt.
     * @param array  $args   Optional arguments (temperature, model preference).
     * @return string|\WP_Error
     */
    public static function generate_with_ai(string $prompt, array $args = [])
    {
        if (!function_exists('wp_ai_client_prompt')) {
            return new \WP_Error('ai_unavailable', __('WordPress AI Connector API is not available.', 'seo-audit'));
        }

        $client = wp_ai_client_prompt($prompt)
            ->using_system_instruction(self::get_seo_audit_system_prompt());

        if (isset($args['temperature'])) {
            $client->using_temperature($args['temperature']);
        }

        if (isset($args['model_preference'])) {
            $client->using_model_preference(...(array)$args['model_preference']);
        }

        if (isset($args['json_schema'])) {
            return $client->as_json_response($args['json_schema'])->generate_text();
        }

        return $client->generate_text();
    }
}
