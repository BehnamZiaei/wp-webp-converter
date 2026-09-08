<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_WebP_Bulk_Converter {
    
    public function __construct() {
        add_action('wp_ajax_wp_webp_get_images', array($this, 'ajax_get_images'));
        add_action('wp_ajax_wp_webp_convert_image', array($this, 'ajax_convert_image'));
    }
    
    /**
     * دریافت لیست تصاویر برای تبدیل
     */
    public function ajax_get_images() {
        check_ajax_referer('wp_webp_bulk_convert', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }
        
        global $wpdb;
        
        // پیدا کردن تمام تصاویر که WebP ندارند
        $query = "
            SELECT p.ID, p.guid, p.post_mime_type
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_has_webp'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type IN ('image/jpeg', 'image/png', 'image/gif')
            AND (pm.meta_value IS NULL OR pm.meta_value = '0' OR pm.meta_value = '')
            ORDER BY p.ID DESC
        ";
        
        $attachments = $wpdb->get_results($query);
        
        $images_to_convert = array();
        
        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            
            if ($file_path && file_exists($file_path)) {
                // چک کنیم WebP وجود نداره
                $webp_path = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $file_path);
                
                if (!file_exists($webp_path)) {
                    $images_to_convert[] = array(
                        'id' => $attachment->ID,
                        'path' => $file_path,
                        'name' => basename($file_path),
                        'mime' => $attachment->post_mime_type,
                        'size' => filesize($file_path)
                    );
                }
            }
        }
        
        wp_send_json_success(array(
            'total' => count($images_to_convert),
            'images' => $images_to_convert
        ));
    }
    
    /**
     * تبدیل یک تصویر
     */
    public function ajax_convert_image() {
        check_ajax_referer('wp_webp_bulk_convert', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }
        
        $image_id = intval($_POST['image_id']);
        $file_path = get_attached_file($image_id);
        
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error('فایل یافت نشد: ' . $file_path);
        }
        
        $settings = get_option('wp_webp_settings');
        $converter = new WP_WebP_Image_Converter();
        
        // تبدیل به WebP
        $result = $converter->convert_to_webp($file_path, $settings['quality']);
        
        if (!$result['success']) {
            wp_send_json_error($result['error'] ?? 'خطا در تبدیل تصویر');
        }
        
        // ذخیره اطلاعات
        update_post_meta($image_id, '_webp_path', $result['webp_path']);
        update_post_meta($image_id, '_has_webp', true);
        update_post_meta($image_id, '_webp_savings', $result['savings']);
        
        if (!empty($result['sizes'])) {
            update_post_meta($image_id, '_webp_sizes', $result['sizes']);
        }
        
        // حذف فایل اصلی در صورت نیاز
        if ($settings['delete_original']) {
            @unlink($file_path);
            
            // به‌روزرسانی متادیتا
            update_attached_file($image_id, $result['webp_path']);
            
            $metadata = wp_get_attachment_metadata($image_id);
            if ($metadata && isset($metadata['file'])) {
                $metadata['file'] = str_replace(
                    basename($file_path),
                    basename($result['webp_path']),
                    $metadata['file']
                );
                wp_update_attachment_metadata($image_id, $metadata);
            }
            
            // به‌روزرسانی MIME type
            wp_update_post(array(
                'ID' => $image_id,
                'post_mime_type' => 'image/webp'
            ));
        }
        
        wp_send_json_success(array(
            'message' => 'تبدیل موفق',
            'original_size' => $converter->format_bytes($result['original_size']),
            'webp_size' => $converter->format_bytes($result['webp_size']),
            'savings' => $result['savings'] . '%',
            'webp_path' => $result['webp_path']
        ));
    }
}

new WP_WebP_Bulk_Converter();