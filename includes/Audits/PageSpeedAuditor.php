<?php

namespace SEOAudit\Audits;

class PageSpeedAuditor
{
    public function analyze_url($url)
    {
        $api_key = get_option('seo_audit_google_api_key', '');
        if (empty($api_key)) {
            return ['error' => 'No PageSpeed API key configured.'];
        }

        // Fetch performance, accessibility, best-practices, seo
        $api_url = "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=" . urlencode($url) . "&key=" . $api_key . "&strategy=mobile&category=performance&category=accessibility&category=best-practices&category=seo";
        $response = wp_remote_get($api_url, ['timeout' => 30]);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['lighthouseResult'])) {
            return ['error' => 'Invalid Lighthouse response.'];
        }

        $audits = $data['lighthouseResult']['audits'] ?? [];
        $categories = $data['lighthouseResult']['categories'] ?? [];
        
        // CrUX (Chrome User Experience Report) Real-World Data
        $crux = $data['loadingExperience']['metrics'] ?? [];

        $insights = [
            'metrics' => [
                'fcp' => $audits['first-contentful-paint']['displayValue'] ?? 'N/A',
                'lcp' => $audits['largest-contentful-paint']['displayValue'] ?? 'N/A',
                'cls' => $audits['cumulative-layout-shift']['displayValue'] ?? 'N/A',
                'tbt' => $audits['total-blocking-time']['displayValue'] ?? 'N/A',
                'si'  => $audits['speed-index']['displayValue'] ?? 'N/A',
            ],
            'crux_real_world' => [
                'fcp' => isset($crux['FIRST_CONTENTFUL_PAINT_MS']) ? $crux['FIRST_CONTENTFUL_PAINT_MS']['category'] : 'N/A',
                'lcp' => isset($crux['LARGEST_CONTENTFUL_PAINT_MS']) ? $crux['LARGEST_CONTENTFUL_PAINT_MS']['category'] : 'N/A',
                'cls' => isset($crux['CUMULATIVE_LAYOUT_SHIFT_SCORE']) ? $crux['CUMULATIVE_LAYOUT_SHIFT_SCORE']['category'] : 'N/A',
                'fid' => isset($crux['FIRST_INPUT_DELAY_MS']) ? $crux['FIRST_INPUT_DELAY_MS']['category'] : 'N/A',
                'inp' => isset($crux['INTERACTION_TO_NEXT_PAINT']) ? $crux['INTERACTION_TO_NEXT_PAINT']['category'] : 'N/A',
            ],
            'scores' => [
                'performance' => isset($categories['performance']['score']) ? $categories['performance']['score'] * 100 : 0,
                'accessibility' => isset($categories['accessibility']['score']) ? $categories['accessibility']['score'] * 100 : 0,
                'best_practices' => isset($categories['best-practices']['score']) ? $categories['best-practices']['score'] * 100 : 0,
                'seo' => isset($categories['seo']['score']) ? $categories['seo']['score'] * 100 : 0,
            ],
            'diagnostics' => []
        ];

        // Gather diagnostic warnings/errors (scores < 1 implies issue)
        foreach ($audits as $auditData) {
            if (isset($auditData['score']) && $auditData['score'] < 1 && !empty($auditData['details']['type']) && $auditData['details']['type'] !== 'opportunity') {
                $insights['diagnostics'][] = [
                    'id' => $auditData['id'],
                    'title' => $auditData['title'],
                    'description' => $auditData['description'],
                    'displayValue' => $auditData['displayValue'] ?? ''
                ];
            }
        }

        return $insights;
    }
}
