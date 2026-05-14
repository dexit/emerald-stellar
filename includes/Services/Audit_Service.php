<?php

/**
 * Audit Service
 *
 * Centralized service for orchestrating SEO audits across different data sources.
 *
 * @package SEOAudit\Services
 */

namespace SEOAudit\Services;

use SEOAudit\Audits\KeywordAuditor;
use SEOAudit\Audits\EnhancedReadabilityAuditor;
use SEOAudit\Audits\PageSpeedAuditor;
use SEOAudit\SEOAuditCore;
use Symfony\Component\DomCrawler\Crawler;

class Audit_Service
{
    /**
     * Performs a full audit on content.
     *
     * @param string $content The HTML content to audit.
     * @param string $url The URL of the page (optional).
     * @param array $extra Optional extra data like title and meta description.
     * @return array
     */
    public function perform_full_audit(string $content, string $url = '', array $extra = []): array
    {
        $results = [];

        // 1. Traditional SEO checks
        $results['seo'] = $this->perform_traditional_checks($content, $url, $extra);

        // 2. Readability Analysis
        $readability_auditor = new EnhancedReadabilityAuditor();
        $results['readability'] = $readability_auditor->analyze_content($content);

        // 3. PageSpeed Insights (if URL provided)
        if (!empty($url)) {
            $ps_auditor = new PageSpeedAuditor();
            $ps_results = $ps_auditor->analyze_url($url);
            if ($ps_results) {
                $results['ui']['pageInsights'] = $ps_results;
            }
        }

        return $results;
    }

    /**
     * Internal orchestration for technical and on-page checks.
     */
    private function perform_traditional_checks(string $content, string $url, array $extra): array
    {
        $audit = [];
        $crawler = new Crawler($content);
        $seo_core = new SEOAuditCore();

        // URL Checks
        if (!empty($url)) {
            $audit['urlLowercase'] = $seo_core->url_uppercase($url);
            $audit['urlUnderscores'] = $seo_core->url_underscores($url);
            $audit['urlLength'] = $seo_core->url_over_115_characters($url);
            $audit['urlParameters'] = $seo_core->url_parameters($url);
            $audit['canonicalCheck'] = $seo_core->canonicals_missing($url);
        }

        // Tag Checks
        $audit['langCheck'] = [
            'passed' => $crawler->filter('html[lang]')->count() > 0,
            'shortAnswer' => 'Language Attribute',
            'recommendation' => 'The <html> tag should have a lang attribute.'
        ];
        
        $audit['hasMobileViewports'] = [
            'passed' => $crawler->filter('meta[name="viewport"]')->count() > 0,
            'shortAnswer' => 'Mobile Viewport',
            'recommendation' => 'Add a viewport meta tag for mobile responsiveness.'
        ];

        // Code Quality
        $deprecated = ['center', 'font', 'strike', 'u', 'big', 'basefont'];
        $found_deprecated = [];
        foreach ($deprecated as $tag) {
            if ($crawler->filter($tag)->count() > 0) $found_deprecated[] = $tag;
        }
        $audit['deprecatedTags'] = [
            'passed' => empty($found_deprecated),
            'shortAnswer' => empty($found_deprecated) ? 'No Deprecated Tags' : 'Deprecated Tags Found',
            'data' => $found_deprecated
        ];

        // Content Checks
        $audit['h1Count'] = [
            'passed' => $crawler->filter('h1')->count() === 1,
            'shortAnswer' => 'H1 Check',
            'recommendation' => 'Each page should have exactly one H1 tag.'
        ];

        return $audit;
    }
}
