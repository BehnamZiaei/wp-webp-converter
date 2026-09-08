<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_WebP_Statistics {
    
    /**
     * دریافت آمار تصاویر
     */
    public function get_image_statistics() {
        global $wpdb;
        
        $stats = array();
        $formats = array('jpeg', 'jpg', 'png', 'gif', 'webp');
        $total_size = 0;
        $total_count = 0;
        
        foreach ($formats as $format) {
            $mime_type = 'image/' . $format;
            if ($format === 'jpg') {
                $mime_type = 'image/jpeg';
            }
            
            // تعداد فایل‌ها
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type = %s",
                $mime_type
            ));
            
            // محاسبه حجم کل
            $attachments = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type = %s",
                $mime_type
            ));
            
            $size = 0;
            foreach ($attachments as $attachment_id) {
                $file_path = get_attached_file($attachment_id);
                if (file_exists($file_path)) {
                    $size += filesize($file_path);
                }
            }
            
            $stats[$format] = array(
                'count' => intval($count),
                'size' => $size
            );
            
            $total_size += $size;
            $total_count += intval($count);
        }
        
        $stats['total'] = array(
            'count' => $total_count,
            'size' => $total_size
        );
        
        return $stats;
    }
    
    /**
     * محاسبه صرفه‌جویی بالقوه
     */
    public function calculate_potential_savings() {
        $stats = $this->get_image_statistics();
        
        // فرض: تبدیل به WebP معمولاً 25-35% صرفه‌جویی دارد
        $avg_savings = 0.30;
        
        $non_webp_size = 0;
        foreach (array('jpeg', 'jpg', 'png', 'gif') as $format) {
            $non_webp_size += $stats[$format]['size'];
        }
        
        $potential_savings = $non_webp_size * $avg_savings;
        
        return array(
            'current_size' => $non_webp_size,
            'potential_savings' => $potential_savings,
            'percentage' => $avg_savings * 100
        );
    }
}