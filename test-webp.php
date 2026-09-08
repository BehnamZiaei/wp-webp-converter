<?php
/**
 * فایل تست - این فایل را در ریشه سایت قرار دهید و از مرورگر باز کنید
 * مثال: https://yoursite.com/test-webp.php
 */

// بارگذاری وردپرس
require_once('wp-load.php');

if (!current_user_can('manage_options')) {
    die('دسترسی غیرمجاز');
}

echo '<h1>🧪 تست تبدیل WebP</h1>';

// 1. بررسی پشتیبانی سرور
echo '<h2>1️⃣ بررسی پشتیبانی سرور</h2>';
if (function_exists('imagewebp')) {
    echo '✅ سرور از WebP پشتیبانی می‌کند<br>';
} else {
    echo '❌ سرور از WebP پشتیبانی نمی‌کند<br>';
}

// 2. بررسی تنظیمات پلاگین
echo '<h2>2️⃣ تنظیمات پلاگین</h2>';
$settings = get_option('wp_webp_settings');
echo '<pre>';
print_r($settings);
echo '</pre>';

// 3. لیست تصاویر بدون WebP
echo '<h2>3️⃣ تصاویر بدون WebP</h2>';
global $wpdb;

$query = "
    SELECT p.ID, p.post_title, p.post_mime_type
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_has_webp'
    WHERE p.post_type = 'attachment'
    AND p.post_mime_type IN ('image/jpeg', 'image/png', 'image/gif')
    AND (pm.meta_value IS NULL OR pm.meta_value = '0' OR pm.meta_value = '')
    LIMIT 10
";

$images = $wpdb->get_results($query);

echo 'تعداد: ' . count($images) . '<br><br>';

foreach ($images as $img) {
    $file_path = get_attached_file($img->ID);
    echo "ID: {$img->ID} | نوع: {$img->post_mime_type}<br>";
    echo "مسیر: {$file_path}<br>";
    echo "وجود فایل: " . (file_exists($file_path) ? '✅' : '❌') . "<br><br>";
}

// 4. تست تبدیل یک تصویر
echo '<h2>4️⃣ تست تبدیل</h2>';
if (!empty($images)) {
    $test_image = $images[0];
    $file_path = get_attached_file($test_image->ID);
    
    if (file_exists($file_path)) {
        require_once(plugin_dir_path(__FILE__) . 'wp-content/plugins/wp-webp-converter/includes/class-image-converter.php');
        
        $converter = new WP_WebP_Image_Converter();
        $result = $converter->convert_to_webp($file_path, 80);
        
        echo '<pre>';
        print_r($result);
        echo '</pre>';
    }
}

echo '<br><a href="' . admin_url('options-general.php?page=wp-webp-converter') . '">← بازگشت به تنظیمات</a>';