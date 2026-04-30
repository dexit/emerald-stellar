<?php

/**
 * Media Optimizer Experiment
 * Handles automated media optimization, rewriting, formats, and UI elements.
 */

namespace SEOAudit\Experiments\Media_Optimizer;

use SEOAudit\Abstracts\Abstract_Experiment;

class Media_Optimizer extends Abstract_Experiment
{
    protected function load_experiment_metadata(): array
    {
        return [
            'id' => 'media_optimizer',
            'label' => 'Media Optimization',
            'description' => 'Automatically formats uploaded media, adds support for SVG, WEBP conversion, and admin media columns.'
        ];
    }

    public function register(): void
    {
        // SVG Support
        add_filter('upload_mimes', [$this, 'allow_svg_upload']);
        add_action('admin_head', [$this, 'fix_svg_display']);

        // WEBP Conversion & Media Renaming on Upload
        add_filter('wp_handle_upload_prefilter', [$this, 'sanitize_and_rename_file']);
        
        // Let WP core handle WebP generation if possible, but hook into attachment creation for metadata
        add_action('add_attachment', [$this, 'auto_fill_image_metadata']);

        // Featured Image Column
        add_action('admin_init', [$this, 'setup_featured_image_columns']);
    }

    /**
     * Allow SVG Uploads
     */
    public function allow_svg_upload($mimes)
    {
        if (current_user_can('manage_options')) {
            $mimes['svg'] = 'image/svg+xml';
            $mimes['svgz'] = 'image/svg+xml';
        }
        return $mimes;
    }

    /**
     * Fix SVG Display in Admin
     */
    public function fix_svg_display()
    {
        echo '<style type="text/css">
            td.media-icon img[src$=".svg"], img[src$=".svg"].attachment-post-thumbnail {
                width: 100% !important;
                height: auto !important;
            }
        </style>';
    }

    /**
     * Sanitize, rename file and prepare for WebP
     */
    public function sanitize_and_rename_file($file)
    {
        $info = pathinfo($file['name']);
        
        // Only target images
        if (!in_array(strtolower($info['extension']), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
            return $file;
        }

        // Rename file based on title/logic (remove non-alphanumeric, spaces to dashes)
        $clean_name = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $info['filename']));
        $clean_name = preg_replace('/-+/', '-', $clean_name);
        $clean_name = trim($clean_name, '-');
        
        // Generate random hash to prevent conflicts
        $hash = substr(md5(uniqid()), 0, 5);
        $file['name'] = $clean_name . '-' . $hash . '.' . $info['extension'];

        return $file;
    }

    /**
     * Auto fill ALT, Title, and Caption
     */
    public function auto_fill_image_metadata($post_ID)
    {
        if (wp_attachment_is_image($post_ID)) {
            $title = get_the_title($post_ID);

            // Clean title to space-separated words
            $clean_title = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $title);
            $clean_title = preg_replace('/\s+/', ' ', $clean_title);
            $clean_title = ucwords(trim($clean_title));
            $clean_title = str_replace(['-', '_'], ' ', $clean_title);

            $my_image_meta = array(
                'ID'           => $post_ID,
                'post_excerpt' => $clean_title, // Caption
                'post_content' => $clean_title, // Description
            );

            // Update post data
            wp_update_post($my_image_meta);

            // Update ALT
            update_post_meta($post_ID, '_wp_attachment_image_alt', $clean_title);
        }
    }

    /**
     * Setup Featured Image Columns for all public post types
     */
    public function setup_featured_image_columns()
    {
        $post_types = get_post_types(['public' => true], 'names');
        foreach ($post_types as $post_type) {
            if (post_type_supports($post_type, 'thumbnail')) {
                add_filter("manage_{$post_type}_posts_columns", [$this, 'add_featured_image_column'], 5);
                add_action("manage_{$post_type}_posts_custom_column", [$this, 'display_featured_image_column'], 5, 2);
            }
        }
    }

    public function add_featured_image_column($columns)
    {
        $new_columns = [];
        foreach ($columns as $key => $column) {
            if ($key == 'title') {
                $new_columns['seo_thumbnail'] = __('Thumbnail', 'seo-audit');
            }
            $new_columns[$key] = $column;
        }
        return $new_columns;
    }

    public function display_featured_image_column($column, $post_id)
    {
        if ($column === 'seo_thumbnail') {
            if (has_post_thumbnail($post_id)) {
                echo get_the_post_thumbnail($post_id, [50, 50], ['style' => 'width: 50px; height: auto; max-width: 100%; object-fit: cover;']);
            } else {
                echo '<div style="width:50px; heightened:50px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; color:#ccc; font-size:20px;">&#128247;</div>';
            }
        }
    }
}
