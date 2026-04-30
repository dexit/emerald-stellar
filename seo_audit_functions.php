<?php

/**
 * SEO Audit Core Functions - Enhanced with Proven Libraries
 *
 * This file provides comprehensive SEO audit functionality using established libraries
 * and WordPress best practices instead of reinventing the wheel.
 *
 * @package SEOAudit
 */

namespace SEOAudit;

use SEOAudit\Audits\EnhancedReadabilityAuditor;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client;

class SEOAuditCore
{

    /**
     * @var ReadabilityAuditor
     */
    private $readability_auditor;

    /**
     * @var Client HTTP client for external requests
     */
    private $http_client;

    /**
     * @var Crawler DOM crawler for HTML analysis
     */
    private $crawler;

    public function __construct()
    {
        $this->readability_auditor = new EnhancedReadabilityAuditor();
        $this->http_client = new Client(['timeout' => 10]);
        $this->crawler = new Crawler();
    }
    
    // ============================================
    // URL & CANONICAL CHECKS
    // ============================================

    /**
     * Check for uppercase characters in URL
     * Best practice: URLs should be lowercase
     */
    public function url_uppercase($url)
    {
        $has_uppercase = preg_match('/[A-Z]/', $url);

        return [
            'url' => $url,
            'has_uppercase' => $has_uppercase,
            'status' => $has_uppercase ? 'warning' : 'pass',
            'message' => $has_uppercase ? 'URL contains uppercase characters' : 'URL is properly lowercase',
            'recommendation' => $has_uppercase ? 'Convert URL to lowercase: ' . strtolower($url) : null
        ];
    }

    /**
     * Check if URL has canonical tag
     */
    public function canonicals_missing($url)
    {
        $html = $this->fetch_url_content($url);
        if (!$html) {
            return ['error' => 'Failed to fetch URL'];
        }

        $this->crawler->clear();
        $this->crawler->addHtmlContent($html);

        $canonical = $this->crawler->filterXPath('//link[@rel="canonical"]')->count() > 0;

        return [
            'url' => $url,
            'has_canonical' => $canonical,
            'status' => $canonical ? 'pass' : 'warning',
            'message' => $canonical ? 'Canonical tag present' : 'Missing canonical tag',
            'recommendation' => !$canonical ? 'Add canonical tag to prevent duplicate content issues' : null
        ];
    }

    /**
     * Check for URL parameters (can cause duplicate content)
     */
    public function url_parameters($url)
    {
        $parsed = parse_url($url);
        $has_params = isset($parsed['query']) && !empty($parsed['query']);

        return [
            'url' => $url,
            'has_parameters' => $has_params,
            'parameters' => $has_params ? $parsed['query'] : null,
            'status' => $has_params ? 'warning' : 'pass',
            'recommendation' => $has_params ? 'Consider using canonical tags or URL rewriting' : null
        ];
    }

    /**
     * Check for underscores in URL (Google prefers hyphens)
     */
    public function url_underscores($url)
    {
        $has_underscores = strpos($url, '_') !== false;

        return [
            'url' => $url,
            'has_underscores' => $has_underscores,
            'status' => $has_underscores ? 'warning' : 'pass',
            'recommendation' => $has_underscores ? 'Use hyphens instead of underscores in URLs' : null
        ];
    }

    /**
     * Check URL length (Google truncates at ~115 characters in SERPs)
     */
    public function url_over_115_characters($url)
    {
        $length = strlen($url);

        return [
            'url' => $url,
            'length' => $length,
            'status' => $length > 115 ? 'warning' : 'pass',
            'message' => $length > 115 ? "URL is {$length} characters (exceeds 115)" : 'URL length is good',
            'recommendation' => $length > 115 ? 'Shorten URL for better display in search results' : null
        ];
    }
    
    // ============================================
    // CONTENT ANALYSIS
    // ============================================

    /**
     * Check for Lorem Ipsum placeholder text
     */
    public function content_lorem_ipsum_placeholder($content)
    {
        $lorem_patterns = [
            '/lorem\s+ipsum/i',
            '/dolor\s+sit\s+amet/i',
            '/consectetur\s+adipiscing/i'
        ];

        $found = false;
        $matches = [];

        foreach ($lorem_patterns as $pattern) {
            if (preg_match($pattern, $content, $match)) {
                $found = true;
                $matches[] = $match[0];
            }
        }

        return [
            'has_placeholder' => $found,
            'matches' => $matches,
            'status' => $found ? 'error' : 'pass',
            'message' => $found ? 'Placeholder text detected' : 'No placeholder text found',
            'recommendation' => $found ? 'Replace placeholder text with actual content' : null
        ];
    }

    /**
     * Analyze content readability using proven algorithms
     */
    public function content_readability_difficult($content, $post_id = null)
    {
        return $this->readability_auditor->analyze_content($content, $post_id);
    }

    /**
     * Check for spelling errors using WordPress built-in or external API
     */
    public function content_spelling_errors($content)
    {
        // Strip HTML tags
        $text = \wp_strip_all_tags($content);

        // Use pspell if available (built-in PHP)
        if (function_exists('pspell_new')) {
            $pspell = pspell_new('en');
            $words = str_word_count($text, 1);
            $errors = [];

            foreach ($words as $word) {
                if (!pspell_check($pspell, $word)) {
                    $suggestions = pspell_suggest($pspell, $word);
                    $errors[] = [
                        'word' => $word,
                        'suggestions' => array_slice($suggestions, 0, 3)
                    ];
                }
            }

            return [
                'error_count' => count($errors),
                'errors' => array_slice($errors, 0, 10), // Limit to first 10
                'status' => count($errors) > 0 ? 'warning' : 'pass',
                'recommendation' => 'Review and correct spelling errors'
            ];
        }

        // Fallback: Recommend LanguageTool API integration
        return [
            'message' => 'Install pspell or configure LanguageTool API for spell checking',
            'status' => 'info'
        ];
    }
    
    // ============================================
    // HEADING ANALYSIS (H1-H6)
    // ============================================

    /**
     * Check for missing H1 tag
     */
    public function h1_missing($post_id)
    {
        $post = \get_post($post_id);
        if (!$post) return ['error' => 'Post not found'];

        $this->crawler->clear();
        $this->crawler->addHtmlContent($post->post_content);

        $h1_count = $this->crawler->filter('h1')->count();

        return [
            'post_id' => $post_id,
            'h1_count' => $h1_count,
            'status' => $h1_count === 0 ? 'error' : 'pass',
            'message' => $h1_count === 0 ? 'No H1 tag found' : "Found {$h1_count} H1 tag(s)",
            'recommendation' => $h1_count === 0 ? 'Add exactly one H1 tag to the page' : null
        ];
    }

    /**
     * Check for multiple H1 tags (SEO best practice: one H1 per page)
     */
    public function h1_multiple($post_id)
    {
        $post = get_post($post_id);
        if (!$post) return ['error' => 'Post not found'];

        $this->crawler->clear();
        $this->crawler->addHtmlContent($post->post_content);

        $h1_tags = $this->crawler->filter('h1');
        $h1_count = $h1_tags->count();
        $h1_texts = [];

        $h1_tags->each(function ($node) use (&$h1_texts) {
            $h1_texts[] = trim($node->text());
        });

        return [
            'post_id' => $post_id,
            'h1_count' => $h1_count,
            'h1_texts' => $h1_texts,
            'status' => $h1_count > 1 ? 'warning' : 'pass',
            'message' => $h1_count > 1 ? "Multiple H1 tags found ({$h1_count})" : 'Single H1 tag (good)',
            'recommendation' => $h1_count > 1 ? 'Use only one H1 tag per page' : null
        ];
    }

    /**
     * Check H1 length (should be under 70 characters)
     */
    public function h1_over_70_characters($post_id)
    {
        $post = get_post($post_id);
        if (!$post) return ['error' => 'Post not found'];

        $this->crawler->clear();
        $this->crawler->addHtmlContent($post->post_content);

        $h1 = $this->crawler->filter('h1')->first();

        if ($h1->count() === 0) {
            return ['error' => 'No H1 tag found'];
        }

        $h1_text = trim($h1->text());
        $length = strlen($h1_text);

        return [
            'post_id' => $post_id,
            'h1_text' => $h1_text,
            'length' => $length,
            'status' => $length > 70 ? 'warning' : 'pass',
            'message' => $length > 70 ? "H1 is {$length} characters (exceeds 70)" : 'H1 length is good',
            'recommendation' => $length > 70 ? 'Shorten H1 to under 70 characters' : null
        ];
    }

    /**
     * Check for non-sequential heading structure (e.g., H1 -> H3, skipping H2)
     */
    public function h1_nonsequential($post_id)
    {
        $post = get_post($post_id);
        if (!$post) return ['error' => 'Post not found'];

        $this->crawler->clear();
        $this->crawler->addHtmlContent($post->post_content);

        $headings = [];
        for ($i = 1; $i <= 6; $i++) {
            $this->crawler->filter("h{$i}")->each(function ($node) use (&$headings, $i) {
                $headings[] = [
                    'level' => $i,
                    'text' => trim($node->text())
                ];
            });
        }

        // Check for gaps in heading hierarchy
        $issues = [];
        $prev_level = 0;

        foreach ($headings as $heading) {
            if ($heading['level'] > $prev_level + 1) {
                $issues[] = "Skipped from H{$prev_level} to H{$heading['level']}";
            }
            $prev_level = $heading['level'];
        }

        return [
            'post_id' => $post_id,
            'headings' => $headings,
            'issues' => $issues,
            'status' => count($issues) > 0 ? 'warning' : 'pass',
            'recommendation' => count($issues) > 0 ? 'Maintain sequential heading hierarchy' : null
        ];
    }
    
    // ============================================
    // IMAGE OPTIMIZATION
    // ============================================

    /**
     * Check for images missing alt text
     */
    public function images_missing_alt_text($image_id = null)
    {
        if ($image_id) {
            $alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);

            return [
                'image_id' => $image_id,
                'has_alt' => !empty($alt),
                'alt_text' => $alt,
                'status' => empty($alt) ? 'warning' : 'pass',
                'recommendation' => empty($alt) ? 'Add descriptive alt text for accessibility and SEO' : null
            ];
        }

        // Get all images without alt text
        $images = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '='
                ]
            ]
        ]);

        return [
            'total_missing' => count($images),
            'image_ids' => wp_list_pluck($images, 'ID'),
            'status' => count($images) > 0 ? 'warning' : 'pass',
            'recommendation' => 'Add alt text to all images'
        ];
    }

    /**
     * Check for images over 100KB
     */
    public function images_over_100_kb($image_id = null)
    {
        if ($image_id) {
            $file_path = get_attached_file($image_id);

            if (!file_exists($file_path)) {
                return ['error' => 'Image file not found'];
            }

            $size_bytes = filesize($file_path);
            $size_kb = $size_bytes / 1024;

            return [
                'image_id' => $image_id,
                'size_kb' => round($size_kb, 2),
                'size_mb' => round($size_kb / 1024, 2),
                'status' => $size_kb > 100 ? 'warning' : 'pass',
                'recommendation' => $size_kb > 100 ? 'Optimize image to reduce file size' : null
            ];
        }

        // Find all large images
        $images = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1
        ]);

        $large_images = [];
        foreach ($images as $image) {
            $file_path = get_attached_file($image->ID);
            if (file_exists($file_path)) {
                $size_kb = filesize($file_path) / 1024;
                if ($size_kb > 100) {
                    $large_images[] = [
                        'id' => $image->ID,
                        'size_kb' => round($size_kb, 2)
                    ];
                }
            }
        }

        return [
            'total_large_images' => count($large_images),
            'images' => $large_images,
            'status' => count($large_images) > 0 ? 'warning' : 'pass'
        ];
    }
    
    // ============================================
    // META DATA VALIDATION
    // ============================================

    /**
     * Check for missing meta description
     */
    public function meta_description_missing($post_id)
    {
        // Check Yoast SEO meta
        $yoast_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);

        // Check Rank Math meta
        $rankmath_desc = get_post_meta($post_id, 'rank_math_description', true);

        // Check All in One SEO
        $aioseo_desc = get_post_meta($post_id, '_aioseo_description', true);

        $has_meta = !empty($yoast_desc) || !empty($rankmath_desc) || !empty($aioseo_desc);

        return [
            'post_id' => $post_id,
            'has_meta_description' => $has_meta,
            'meta_description' => $yoast_desc ?: $rankmath_desc ?: $aioseo_desc,
            'status' => $has_meta ? 'pass' : 'warning',
            'recommendation' => !$has_meta ? 'Add a meta description (155-160 characters)' : null
        ];
    }

    /**
     * Check meta description length (should be 155-160 characters)
     */
    public function meta_description_over_155_characters($post_id)
    {
        $desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true)
            ?: get_post_meta($post_id, 'rank_math_description', true)
            ?: get_post_meta($post_id, '_aioseo_description', true);

        if (empty($desc)) {
            return ['error' => 'No meta description found'];
        }

        $length = strlen($desc);

        return [
            'post_id' => $post_id,
            'meta_description' => $desc,
            'length' => $length,
            'status' => $length > 160 ? 'warning' : ($length < 120 ? 'warning' : 'pass'),
            'message' => $length > 160 ? "Too long ({$length} chars)" : ($length < 120 ? "Too short ({$length} chars)" : 'Good length'),
            'recommendation' => $length > 160 ? 'Shorten to 155-160 characters' : ($length < 120 ? 'Expand to 155-160 characters' : null)
        ];
    }
    
    // ============================================
    // HELPER FUNCTIONS
    // ============================================

    /**
     * Fetch URL content using WordPress HTTP API
     */
    private function fetch_url_content($url)
    {
        $response = wp_remote_get($url, ['timeout' => 10]);

        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_body($response);
    }
}
