<?php

/**
 * Keyword Density and SEO Keyword Checker
 * 
 * Based on SEO Keyword Checker plugin implementation
 * Analyzes keyword usage across title, meta, headings, and content
 *
 * @package SEOAudit
 */

namespace SEOAudit\Audits;

class KeywordAuditor
{

    /**
     * Calculate SEO score for a specific keyword
     *
     * @param int $post_id Post ID
     * @param string $keyword Focus keyword
     * @param bool $return_breakdown Return detailed breakdown
     * @return array|int
     */
    public function calculate_keyword_score($post_id, $keyword, $return_breakdown = false)
    {
        if (!$keyword) {
            return $return_breakdown ? [
                'total' => 0,
                'title' => 0,
                'meta' => 0,
                'h1' => 0,
                'h2' => 0,
                'h3' => 0,
                'body' => 0,
                'url' => 0,
                'density' => 0,
                'first_paragraph' => 0
            ] : 0;
        }

        $post = get_post($post_id);
        $content = $post->post_content;

        $score = 0;
        $breakdown = [
            'title' => 0,
            'meta' => 0,
            'h1' => 0,
            'h2' => 0,
            'h3' => 0,
            'h4' => 0,
            'h5' => 0,
            'h6' => 0,
            'body' => 0,
            'url' => 0,
            'density' => 0,
            'first_paragraph' => 0,
            'img_alt_title' => 0,
            'svg_usage' => 0,
            'links_href_title' => 0,
            'aria_attributes' => 0,
            'target_attributes' => 0
        ];

        $keyword_lower = strtolower($keyword);

        // Title Match (20 points)
        $title = $this->get_seo_title($post_id, $post);
        if (stripos($title, $keyword) !== false) {
            $breakdown['title'] = 20;
            $score += 20;
        }

        // Meta Description Match (15 points)
        $meta_desc = $this->get_meta_description($post_id);
        if ($meta_desc && stripos($meta_desc, $keyword) !== false) {
            $breakdown['meta'] = 15;
            $score += 15;
        }

        // URL/Slug Match (10 points)
        $slug = $post->post_name;
        if (stripos($slug, str_replace(' ', '-', $keyword_lower)) !== false) {
            $breakdown['url'] = 10;
            $score += 10;
        }

        // H1 Match (15 points)
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $content, $matches)) {
            if (stripos($matches[1], $keyword) !== false) {
                $breakdown['h1'] = 15;
                $score += 15;
            }
        }

        // H2 Match (10 points)
        if (preg_match_all('/<h2[^>]*>(.*?)<\/h2>/i', $content, $h2_matches)) {
            foreach ($h2_matches[1] as $h2) {
                if (stripos($h2, $keyword) !== false) {
                    $breakdown['h2'] = 10;
                    $score += 10;
                    break;
                }
            }
        }

        // H3 Match (5 points)
        if (preg_match_all('/<h3[^>]*>(.*?)<\/h3>/i', $content, $h3_matches)) {
            foreach ($h3_matches[1] as $h3) {
                if (stripos($h3, $keyword) !== false) {
                    $breakdown['h3'] = 5;
                    $score += 5;
                    break;
                }
            }
        }

        // H4 Match (3 points)
        if (preg_match_all('/<h4[^>]*>(.*?)<\/h4>/i', $content, $h4_matches)) {
            foreach ($h4_matches[1] as $h4) {
                if (stripos($h4, $keyword) !== false) {
                    $breakdown['h4'] = 3;
                    $score += 3;
                    break;
                }
            }
        }

        // H5 Match (2 points)
        if (preg_match_all('/<h5[^>]*>(.*?)<\/h5>/i', $content, $h5_matches)) {
            foreach ($h5_matches[1] as $h5) {
                if (stripos($h5, $keyword) !== false) {
                    $breakdown['h5'] = 2;
                    $score += 2;
                    break;
                }
            }
        }

        // H6 Match (1 point)
        if (preg_match_all('/<h6[^>]*>(.*?)<\/h6>/i', $content, $h6_matches)) {
            foreach ($h6_matches[1] as $h6) {
                if (stripos($h6, $keyword) !== false) {
                    $breakdown['h6'] = 1;
                    $score += 1;
                    break;
                }
            }
        }

        // First Paragraph Match (10 points)
        $plain_text = wp_strip_all_tags($content);
        $paragraphs = preg_split('/\n\n+/', $plain_text);
        if (!empty($paragraphs[0]) && stripos($paragraphs[0], $keyword) !== false) {
            $breakdown['first_paragraph'] = 10;
            $score += 10;
        }

        // Body Content Match (10 points)
        if (stripos($plain_text, $keyword) !== false) {
            $breakdown['body'] = 10;
            $score += 10;
        }

        // Image Alt/Title Match (5 points)
        if (preg_match_all('/<img[^>]+(?:alt|title)=["\']([^"\']*)["\'][^>]*>/i', $content, $img_matches)) {
            foreach ($img_matches[1] as $img_attr) {
                if (stripos($img_attr, $keyword) !== false) {
                    $breakdown['img_alt_title'] = 5;
                    $score += 5;
                    break;
                }
            }
        }

        // SVG Usage Match (5 points)
        if (preg_match('/<svg[^>]*>.*?<\/svg>/is', $content, $svg_matches)) {
            if (stripos($svg_matches[0], $keyword) !== false) {
                $breakdown['svg_usage'] = 5;
                $score += 5;
            }
        }

        // Links href/title Match (5 points)
        if (preg_match_all('/<a[^>]+(?:href|title)=["\']([^"\']*)["\'][^>]*>/i', $content, $link_matches)) {
            foreach ($link_matches[1] as $link_attr) {
                if (stripos($link_attr, $keyword) !== false) {
                    $breakdown['links_href_title'] = 5;
                    $score += 5;
                    break;
                }
            }
        }

        // ARIA Attributes Match (5 points)
        // matches aria-label, aria-describedby or any aria-* containing the keyword
        if (preg_match_all('/aria-[a-z]+=["\']([^"\']*)["\']/i', $content, $aria_matches)) {
            foreach ($aria_matches[1] as $aria_val) {
                if (stripos($aria_val, $keyword) !== false) {
                    $breakdown['aria_attributes'] = 5;
                    $score += 5;
                    break;
                }
            }
        }

        // Target Attributes Match (5 points)
        // checks if any target attribute's value or surrounding tag logic contains keyword (less common, but requested)
        if (preg_match_all('/target=["\']([^"\']*)["\']/i', $content, $target_matches)) {
            foreach ($target_matches[1] as $target_val) {
                if (stripos($target_val, $keyword) !== false) {
                    $breakdown['target_attributes'] = 5;
                    $score += 5;
                    break;
                }
            }
        }

        // Keyword Density (5 points for optimal 1-3%)
        $density = $this->calculate_keyword_density($plain_text, $keyword);
        if ($density >= 1 && $density <= 3) {
            $breakdown['density'] = 5;
            $score += 5;
        }

        $breakdown['total'] = $score;
        $breakdown['keyword_density_percent'] = $density;
        $breakdown['keyword_count'] = $this->count_keyword_occurrences($plain_text, $keyword);

        return $return_breakdown ? $breakdown : $score;
    }

    /**
     * Calculate keyword density
     *
     * @param string $text Content text
     * @param string $keyword Keyword to check
     * @return float Density percentage
     */
    public function calculate_keyword_density($text, $keyword)
    {
        $total_words = str_word_count($text);
        if ($total_words == 0) return 0;

        $keyword_count = $this->count_keyword_occurrences($text, $keyword);
        return round(($keyword_count / $total_words) * 100, 2);
    }

    /**
     * Count keyword occurrences
     *
     * @param string $text Content text
     * @param string $keyword Keyword to count
     * @return int Number of occurrences
     */
    public function count_keyword_occurrences($text, $keyword)
    {
        return substr_count(strtolower($text), strtolower($keyword));
    }

    /**
     * Get SEO title (supports Yoast, Rank Math, All in One SEO)
     */
    private function get_seo_title($post_id, $post)
    {
        $title = get_the_title($post_id);

        // Yoast SEO
        if (class_exists('WPSEO_Meta') && function_exists('wpseo_replace_vars')) {
            $yoast_template = \WPSEO_Meta::get_value('title', $post_id);
            if ($yoast_template) {
                return wpseo_replace_vars($yoast_template, $post);
            }
        }

        // Rank Math
        $rankmath_title = get_post_meta($post_id, 'rank_math_title', true);
        if ($rankmath_title) {
            return $rankmath_title;
        }

        // All in One SEO
        $aioseo_title = get_post_meta($post_id, '_aioseo_title', true);
        if ($aioseo_title) {
            return $aioseo_title;
        }

        return $title;
    }

    /**
     * Get meta description (supports Yoast, Rank Math, All in One SEO)
     */
    private function get_meta_description($post_id)
    {
        // Yoast SEO
        if (class_exists('WPSEO_Meta')) {
            $desc = \WPSEO_Meta::get_value('metadesc', $post_id);
            if ($desc) return $desc;
        }

        // Rank Math
        $rankmath_desc = get_post_meta($post_id, 'rank_math_description', true);
        if ($rankmath_desc) return $rankmath_desc;

        // All in One SEO
        $aioseo_desc = get_post_meta($post_id, '_aioseo_description', true);
        if ($aioseo_desc) return $aioseo_desc;

        return '';
    }

    /**
     * Analyze keyword distribution across content
     */
    public function analyze_keyword_distribution($post_id, $keyword)
    {
        $post = get_post($post_id);
        $content = wp_strip_all_tags($post->post_content);

        // Split content into sections
        $total_length = strlen($content);
        $section_size = ceil($total_length / 4);

        $distribution = [
            'first_quarter' => 0,
            'second_quarter' => 0,
            'third_quarter' => 0,
            'fourth_quarter' => 0
        ];

        $sections = [
            'first_quarter' => substr($content, 0, $section_size),
            'second_quarter' => substr($content, $section_size, $section_size),
            'third_quarter' => substr($content, $section_size * 2, $section_size),
            'fourth_quarter' => substr($content, $section_size * 3)
        ];

        foreach ($sections as $key => $section) {
            $distribution[$key] = $this->count_keyword_occurrences($section, $keyword);
        }

        return [
            'distribution' => $distribution,
            'is_well_distributed' => $this->is_keyword_well_distributed($distribution),
            'recommendation' => $this->get_distribution_recommendation($distribution)
        ];
    }

    /**
     * Check if keyword is well distributed
     */
    private function is_keyword_well_distributed($distribution)
    {
        $values = array_values($distribution);
        $total = array_sum($values);

        if ($total == 0) return false;

        // Check if each quarter has at least some keyword presence
        $quarters_with_keyword = count(array_filter($values, function ($v) {
            return $v > 0;
        }));

        return $quarters_with_keyword >= 3; // At least 3 out of 4 quarters
    }

    /**
     * Get distribution recommendation
     */
    private function get_distribution_recommendation($distribution)
    {
        if ($this->is_keyword_well_distributed($distribution)) {
            return 'Keyword is well distributed throughout the content.';
        }

        $values = array_values($distribution);
        $quarters_with_keyword = count(array_filter($values, function ($v) {
            return $v > 0;
        }));

        if ($quarters_with_keyword == 0) {
            return 'Keyword not found in content. Add your focus keyword throughout the text.';
        } elseif ($quarters_with_keyword == 1) {
            return 'Keyword appears in only one section. Distribute it more evenly throughout the content.';
        } else {
            return 'Keyword distribution could be improved. Try to include it in more sections of your content.';
        }
    }

    /**
     * Get keyword variations and LSI keywords
     */
    public function suggest_keyword_variations($keyword)
    {
        $variations = [];

        // Plurals
        $variations[] = $keyword . 's';
        $variations[] = $keyword . 'es';

        // Common variations
        $words = explode(' ', $keyword);
        if (count($words) > 1) {
            // Reverse word order
            $variations[] = implode(' ', array_reverse($words));

            // Partial matches
            foreach ($words as $word) {
                if (strlen($word) > 3) {
                    $variations[] = $word;
                }
            }
        }

        return array_unique($variations);
    }

    /**
     * Analyze all keywords in content
     */
    public function extract_top_keywords($content, $limit = 10)
    {
        $text = wp_strip_all_tags($content);
        $text = strtolower($text);

        // Remove common stop words
        $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'what', 'which', 'who', 'when', 'where', 'why', 'how'];

        $words = str_word_count($text, 1);
        $words = array_filter($words, function ($word) use ($stop_words) {
            return !in_array($word, $stop_words) && strlen($word) > 3;
        });

        $word_count = array_count_values($words);
        arsort($word_count);

        return array_slice($word_count, 0, $limit);
    }
}
