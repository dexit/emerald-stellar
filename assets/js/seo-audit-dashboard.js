/**
 * SEO Audit Dashboard JavaScript
 */
(function($) {
    "use strict";

    $(document).ready(function() {
        const apiUrl = seoAuditDashboard.apiUrl;
        const nonce = seoAuditDashboard.nonce;

        // Handle CSV Export
        $('#seo-export-csv-btn').on('click', function(e) {
            const $btn = $(this);
            $btn.css('opacity', '0.5');
            setTimeout(() => $btn.css('opacity', '1'), 2000);
        });

        // Handle Queue All for Rescan
        $('#seo-queue-all-btn').on('click', function() {
            if (!confirm('Are you sure you want to queue all published posts and pages for SEO rescan? This may take some time to process in the background.')) {
                return;
            }

            const $btn = $(this);
            const originalText = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Processing...');

            $.post(apiUrl, {
                action: 'seo_audit_queue_all',
                nonce: nonce
            }, function(resp) {
                $btn.prop('disabled', false).html(originalText);
                if (resp.success) {
                    alert(resp.data.message);
                    window.location.reload();
                } else {
                    alert(resp.data.message || 'Error queuing rescans');
                }
            });
        });
    });

})(jQuery);
