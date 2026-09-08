<?php
/**
 * Plugin Name: WP WebP Converter Pro
 * Plugin URI: https://codebest.ir
 * Description: تبدیل خودکار تصاویر وردپرس به فرمت WebP با قابلیت تبدیل دسته‌جمعی و آمار کامل
 * Version: 1.0.0
 * Author: behnam Zeyiae
 * Author URI: https://codebest.ir
 * Text Domain: wp-webp-converter
 * Domain Path: /languages
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_WEBP_VERSION', '1.0.1');
define('WP_WEBP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_WEBP_PLUGIN_URL', plugin_dir_url(__FILE__));

class WP_WebP_Converter {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once WP_WEBP_PLUGIN_DIR . 'includes/class-image-converter.php';
        require_once WP_WEBP_PLUGIN_DIR . 'includes/class-bulk-converter.php';
        require_once WP_WEBP_PLUGIN_DIR . 'includes/class-statistics.php';
        
        if (is_admin()) {
            require_once WP_WEBP_PLUGIN_DIR . 'admin/admin-settings.php';
        }
    }
    
    private function init_hooks() {
        // هوک‌های تبدیل خودکار - استفاده از چند هوک برای اطمینان
        add_filter('wp_generate_attachment_metadata', array($this, 'convert_on_upload'), 10, 2);
        add_action('add_attachment', array($this, 'convert_after_upload'), 10, 1);
        
        // هوک فیلتر محتوا
        add_filter('the_content', array($this, 'replace_images_in_content'), 999);
        add_filter('post_thumbnail_html', array($this, 'replace_thumbnail'), 999, 5);
        
        // هوک‌های فعال‌سازی
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // بارگذاری assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // اضافه کردن ستون در لیست رسانه‌ها
        add_filter('manage_media_columns', array($this, 'add_webp_column'));
        add_action('manage_media_custom_column', array($this, 'display_webp_column'), 10, 2);
    }
    
    public function activate() {
        $default_options = array(
            'quality' => 80,
            'delete_original' => false,
            'auto_replace_links' => true,
            'convert_on_upload' => true,
        );
        
        if (!get_option('wp_webp_settings')) {
            add_option('wp_webp_settings', $default_options);
        }
        
        // بررسی پشتیبانی سرور
        if (!function_exists('imagewebp')) {
            set_transient('wp_webp_admin_notice', 'سرور شما از تبدیل WebP پشتیبانی نمی‌کند. لطفاً GD Library با پشتیبانی WebP را نصب کنید.', 30);
        }
    }
    
    public function deactivate() {
        delete_transient('wp_webp_admin_notice');
    }
    
    /**
     * تبدیل تصویر بعد از تولید متادیتا
     */
    public function convert_on_upload($metadata, $attachment_id) {
        $settings = get_option('wp_webp_settings');
        
        if (!$settings['convert_on_upload']) {
            return $metadata;
        }
        
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            return $metadata;
        }
        
        // بررسی نوع فایل
        $mime_type = get_post_mime_type($attachment_id);
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        
        if (!in_array($mime_type, $allowed_types)) {
            return $metadata;
        }
        
        // تبدیل به WebP
        $converter = new WP_WebP_Image_Converter();
        $result = $converter->convert_to_webp($file_path, $settings['quality']);
        
        if ($result['success']) {
            // ذخیره اطلاعات WebP در متادیتا
            update_post_meta($attachment_id, '_webp_path', $result['webp_path']);
            update_post_meta($attachment_id, '_has_webp', true);
            update_post_meta($attachment_id, '_webp_savings', $result['savings']);
            
            // اگر تبدیل سایزهای مختلف هم انجام شده
            if (!empty($result['sizes'])) {
                update_post_meta($attachment_id, '_webp_sizes', $result['sizes']);
            }
            
            // حذف فایل اصلی اگر فعال باشد
            if ($settings['delete_original']) {
                $this->delete_original_and_update_metadata($attachment_id, $file_path, $result['webp_path'], $metadata);
            }
        }
        
        return $metadata;
    }
    
    /**
     * تبدیل بعد از آپلود (هوک دوم برای اطمینان)
     */
    public function convert_after_upload($attachment_id) {
        $settings = get_option('wp_webp_settings');
        
        if (!$settings['convert_on_upload']) {
            return;
        }
        
        // چک کنیم که قبلاً تبدیل نشده باشد
        if (get_post_meta($attachment_id, '_has_webp', true)) {
            return;
        }
        
        $file_path = get_attached_file($attachment_id);
        $mime_type = get_post_mime_type($attachment_id);
        
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        
        if (!in_array($mime_type, $allowed_types)) {
            return;
        }
        
        // تبدیل به WebP
        $converter = new WP_WebP_Image_Converter();
        $result = $converter->convert_to_webp($file_path, $settings['quality']);
        
        if ($result['success']) {
            update_post_meta($attachment_id, '_webp_path', $result['webp_path']);
            update_post_meta($attachment_id, '_has_webp', true);
            update_post_meta($attachment_id, '_webp_savings', $result['savings']);
        }
    }
    
    /**
     * حذف فایل اصلی و به‌روزرسانی متادیتا
     */
    private function delete_original_and_update_metadata($attachment_id, $original_path, $webp_path, $metadata) {
        if (@unlink($original_path)) {
            // به‌روزرسانی مسیر فایل
            update_attached_file($attachment_id, $webp_path);
            
            // به‌روزرسانی متادیتا
            if (is_array($metadata) && isset($metadata['file'])) {
                $metadata['file'] = str_replace(
                    basename($original_path),
                    basename($webp_path),
                    $metadata['file']
                );
                wp_update_attachment_metadata($attachment_id, $metadata);
            }
            
            // به‌روزرسانی MIME type
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_mime_type' => 'image/webp'
            ));
        }
    }
    
    /**
     * جایگزینی تصاویر در محتوا
     */
    public function replace_images_in_content($content) {
        $settings = get_option('wp_webp_settings');
        
        if (!$settings['auto_replace_links']) {
            return $content;
        }
        
        // پیدا کردن تمام تگ‌های img
        preg_match_all('/<img[^>]+>/i', $content, $matches);
        
        if (empty($matches[0])) {
            return $content;
        }
        
        foreach ($matches[0] as $img_tag) {
            $new_img_tag = $this->convert_img_tag_to_webp($img_tag);
            if ($new_img_tag !== $img_tag) {
                $content = str_replace($img_tag, $new_img_tag, $content);
            }
        }
        
        return $content;
    }
    
    /**
     * جایگزینی تصویر شاخص
     */
    public function replace_thumbnail($html, $post_id, $post_thumbnail_id, $size, $attr) {
        $settings = get_option('wp_webp_settings');
        
        if (!$settings['auto_replace_links']) {
            return $html;
        }
        
        return $this->convert_img_tag_to_webp($html);
    }
    
    /**
     * تبدیل تگ img به WebP
     */
    private function convert_img_tag_to_webp($img_tag) {
        // استخراج src
        if (!preg_match('/src=["\']([^"\']+)["\']/', $img_tag, $src_match)) {
            return $img_tag;
        }
        
        $img_url = $src_match[1];
        
        // چک کردن اینکه آیا قبلاً WebP است
        if (preg_match('/\.webp$/i', $img_url)) {
            return $img_tag;
        }
        
        // تبدیل URL به مسیر فایل
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $img_url);
        
        // مسیر WebP
        $webp_path = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $file_path);
        
        // اگر فایل WebP وجود دارد
        if (file_exists($webp_path)) {
            $webp_url = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $img_url);
            return str_replace($img_url, $webp_url, $img_tag);
        }
        
        return $img_tag;
    }
    
    /**
     * اضافه کردن ستون WebP
     */
    public function add_webp_column($columns) {
        $columns['webp_status'] = 'WebP';
        return $columns;
    }
    
    /**
     * نمایش وضعیت WebP
     */
    public function display_webp_column($column_name, $attachment_id) {
        if ($column_name === 'webp_status') {
            $mime_type = get_post_mime_type($attachment_id);
            
            if (strpos($mime_type, 'image') === false) {
                echo '—';
                return;
            }
            
            $has_webp = get_post_meta($attachment_id, '_has_webp', true);
            
            if ($has_webp || $mime_type === 'image/webp') {
                $savings = get_post_meta($attachment_id, '_webp_savings', true);
                echo '<span style="color: green;">✓ WebP</span>';
                if ($savings) {
                    echo '<br><small>صرفه‌جویی: ' . round($savings) . '%</small>';
                }
            } else {
                echo '<span style="color: orange;">✗ نیاز به تبدیل</span>';
            }
        }
    }
    
    public function enqueue_admin_assets($hook) {
        if ('settings_page_wp-webp-converter' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'wp-webp-admin-style',
            WP_WEBP_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            WP_WEBP_VERSION
        );
        
        wp_enqueue_script(
            'wp-webp-bulk-convert',
            WP_WEBP_PLUGIN_URL . 'admin/js/bulk-convert.js',
            array('jquery'),
            WP_WEBP_VERSION,
            true
        );
        
        wp_localize_script('wp-webp-bulk-convert', 'wpWebpAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_webp_bulk_convert')
        ));
    }
}

// راه‌اندازی پلاگین
function wp_webp_converter_init() {
    return WP_WebP_Converter::get_instance();
}

add_action('plugins_loaded', 'wp_webp_converter_init');