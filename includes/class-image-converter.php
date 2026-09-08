<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_WebP_Image_Converter {
    
    /**
     * تبدیل تصویر به WebP
     */
    public function convert_to_webp($source_path, $quality = 80) {
        $result = array(
            'success' => false,
            'webp_path' => null,
            'original_size' => 0,
            'webp_size' => 0,
            'savings' => 0,
            'sizes' => array(),
            'error' => null
        );
        
        // بررسی وجود فایل
        if (!file_exists($source_path)) {
            $result['error'] = 'فایل وجود ندارد';
            return $result;
        }
        
        // بررسی پشتیبانی WebP
        if (!function_exists('imagewebp')) {
            $result['error'] = 'سرور از WebP پشتیبانی نمی‌کند';
            return $result;
        }
        
        // دریافت اطلاعات تصویر
        $image_info = @getimagesize($source_path);
        if (!$image_info) {
            $result['error'] = 'فایل تصویر معتبر نیست';
            return $result;
        }
        
        $mime_type = $image_info['mime'];
        $result['original_size'] = filesize($source_path);
        
        // ایجاد resource تصویر
        $image = $this->create_image_resource($source_path, $mime_type);
        
        if (!$image) {
            $result['error'] = 'خطا در خواندن تصویر';
            return $result;
        }
        
        // مسیر فایل WebP
        $webp_path = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $source_path);
        
        // تبدیل و ذخیره
        $converted = @imagewebp($image, $webp_path, $quality);
        
        // آزادسازی حافظه
        imagedestroy($image);
        
        if (!$converted || !file_exists($webp_path)) {
            $result['error'] = 'خطا در ذخیره فایل WebP';
            return $result;
        }
        
        // محاسبه نتایج
        $result['success'] = true;
        $result['webp_path'] = $webp_path;
        $result['webp_size'] = filesize($webp_path);
        $result['savings'] = $this->calculate_savings($result['original_size'], $result['webp_size']);
        
        // تبدیل سایزهای مختلف
        $result['sizes'] = $this->convert_image_sizes($source_path, $quality);
        
        return $result;
    }
    
    /**
     * ایجاد resource تصویر
     */
    private function create_image_resource($source_path, $mime_type) {
        $image = false;
        
        switch ($mime_type) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($source_path);
                break;
                
            case 'image/png':
                $image = @imagecreatefrompng($source_path);
                if ($image) {
                    // حفظ شفافیت
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                }
                break;
                
            case 'image/gif':
                $image = @imagecreatefromgif($source_path);
                if ($image) {
                    // حفظ شفافیت برای GIF
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                }
                break;
        }
        
        return $image;
    }
    
    /**
     * تبدیل سایزهای مختلف تصویر
     */
    private function convert_image_sizes($original_path, $quality) {
        $sizes = array();
        $upload_dir = wp_upload_dir();
        $file_dir = dirname($original_path);
        $file_name = basename($original_path);
        $file_name_no_ext = pathinfo($file_name, PATHINFO_FILENAME);
        
        // پیدا کردن فایل‌های مرتبط (thumbnails)
        $pattern = $file_dir . '/' . $file_name_no_ext . '-*';
        $related_files = glob($pattern . '.{jpg,jpeg,png,gif}', GLOB_BRACE);
        
        foreach ($related_files as $related_file) {
            if (file_exists($related_file)) {
                $webp_result = $this->convert_to_webp($related_file, $quality);
                if ($webp_result['success']) {
                    $sizes[] = basename($webp_result['webp_path']);
                }
            }
        }
        
        return $sizes;
    }
    
    /**
     * محاسبه میزان صرفه‌جویی
     */
    public function calculate_savings($original_size, $webp_size) {
        if ($original_size == 0) {
            return 0;
        }
        
        $savings = (($original_size - $webp_size) / $original_size) * 100;
        return max(0, round($savings, 2));
    }
    
    /**
     * فرمت‌دهی حجم فایل
     */
    public function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}