/**
 * Sidebar Meta Box JavaScript
 */
(function($) {
    "use strict";

    $(document).ready(function() {
        const $box = $('#wp-admin-seo-audit-box');
        if (!$box.length) return;

        const postId = seoAuditSidebar.postId;
        const apiUrl = seoAuditSidebar.apiUrl;
        const nonce = seoAuditSidebar.nonce;

        // Save focus keyword
        $('#seo-audit-save-keyword').on('click', function() {
            const $btn = $(this);
            const keyword = $('#seo-audit-focus-keyword').val();

            $btn.prop('disabled', true).text('Saving...');

            $.post(apiUrl, {
                action: 'seo_audit_save_focus_keyword',
                nonce: nonce,
                post_id: postId,
                keyword: keyword
            }, function(resp) {
                $btn.prop('disabled', false);
                if (resp.success) {
                    $btn.text('✓ Saved').addClass('button-primary');
                    setTimeout(() => $btn.text('Save').removeClass('button-primary'), 2000);
                } else {
                    alert(resp.data.message || 'Error saving keyword');
                    $btn.text('Save');
                }
            });
        });

        // Run audit trigger
        $('#run-wp-admin-seo-audit').on('click', function(e) {
            e.preventDefault();
            
            let content = "";
            if (typeof wp !== "undefined" && wp.data && wp.data.select("core/editor")) {
                content = wp.data.select("core/editor").getEditedPostContent();
            } else if (typeof tinyMCE !== "undefined" && tinyMCE.activeEditor) {
                content = tinyMCE.activeEditor.getContent();
            } else {
                content = $("#content").val();
            }

            $(document).trigger("seo:audit:start", {
                content: content,
                title: $("#title").val() || document.title,
                url: seoAuditSidebar.permalink,
                post_id: postId,
                meta_description: ""
            });
        });
    });

})(jQuery);
