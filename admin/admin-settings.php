<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_WebP_Admin_Settings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_settings_page() {
        add_options_page(
            'تنظیمات WebP Converter',
            'WebP Converter',
            'manage_options',
            'wp-webp-converter',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('wp_webp_settings_group', 'wp_webp_settings', array($this, 'sanitize_settings'));
    }
    
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['quality'] = absint($input['quality']);
        if ($sanitized['quality'] < 0 || $sanitized['quality'] > 100) {
            $sanitized['quality'] = 80;
        }
        
        $sanitized['delete_original'] = isset($input['delete_original']) ? true : false;
        $sanitized['auto_replace_links'] = isset($input['auto_replace_links']) ? true : false;
        $sanitized['convert_on_upload'] = isset($input['convert_on_upload']) ? true : false;
        
        return $sanitized;
    }
    
    public function render_settings_page() {
        $settings = get_option('wp_webp_settings');
        $statistics = new WP_WebP_Statistics();
        $stats = $statistics->get_image_statistics();
        $savings = $statistics->calculate_potential_savings();
        $converter = new WP_WebP_Image_Converter();
        
        ?>
        <div class="wrap wp-webp-wrap">
            <h1>🖼️ تنظیمات تبدیل تصاویر به WebP</h1>
            
            <!-- بخش آمار -->
            <div class="wp-webp-stats-section">
                <h2>📊 آمار تصاویر</h2>
                <div class="wp-webp-stats-grid">
                    <?php foreach (array('jpeg', 'png', 'gif', 'webp') as $format): ?>
                        <div class="stat-card">
                            <div class="stat-icon"><?php echo strtoupper($format); ?></div>
                            <div class="stat-number"><?php echo number_format($stats[$format]['count']); ?></div>
                            <div class="stat-label">فایل <?php echo strtoupper($format); ?></div>
                            <div class="stat-size"><?php echo $converter->format_bytes($stats[$format]['size']); ?></div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="stat-card stat-card-total">
                        <div class="stat-icon">💾</div>
                        <div class="stat-number"><?php echo number_format($stats['total']['count']); ?></div>
                        <div class="stat-label">مجموع تصاویر</div>
                        <div class="stat-size"><?php echo $converter->format_bytes($stats['total']['size']); ?></div>
                    </div>
                    
                    <div class="stat-card stat-card-savings">
                        <div class="stat-icon">⚡</div>
                        <div class="stat-number"><?php echo $converter->format_bytes($savings['potential_savings']); ?></div>
                        <div class="stat-label">صرفه‌جویی بالقوه</div>
                        <div class="stat-size">~<?php echo round($savings['percentage']); ?>% کاهش حجم</div>
                    </div>
                </div>
            </div>
            
            <!-- بخش تبدیل دسته‌جمعی -->
            <div class="wp-webp-bulk-section">
                <h2>🔄 تبدیل دسته‌جمعی</h2>
                <p>تبدیل تمام تصاویر موجود در کتابخانه رسانه به فرمت WebP</p>
                
                <button type="button" id="wp-webp-start-bulk" class="button button-primary button-hero">
                    🚀 شروع تبدیل دسته‌جمعی
                </button>
                
                <div id="wp-webp-progress-container" style="display: none;">
                    <div class="progress-info">
                        <span id="wp-webp-progress-text">در حال پردازش...</span>
                        <span id="wp-webp-progress-percentage">0%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-bar-fill" id="wp-webp-progress-bar"></div>
                    </div>
                    <div id="wp-webp-progress-details"></div>
                </div>
            </div>
            
            <!-- فرم تنظیمات -->
            <form method="post" action="options.php" class="wp-webp-settings-form">
                <?php settings_fields('wp_webp_settings_group'); ?>
                
                <h2>⚙️ تنظیمات کلی</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="quality">کیفیت تصویر WebP</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="quality" 
                                   name="wp_webp_settings[quality]" 
                                   value="<?php echo esc_attr($settings['quality']); ?>" 
                                   min="0" 
                                   max="100" 
                                   class="small-text">
                            <span class="quality-display"><?php echo $settings['quality']; ?>%</span>
                            <p class="description">عدد بین 0 تا 100 (توصیه: 80-85 برای بهترین کیفیت/حجم)</p>
                            <input type="range" 
                                   id="quality-slider" 
                                   min="0" 
                                   max="100" 
                                   value="<?php echo esc_attr($settings['quality']); ?>" 
                                   class="quality-slider">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">تبدیل خودکار</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="wp_webp_settings[convert_on_upload]" 
                                       value="1" 
                                       <?php checked($settings['convert_on_upload'], true); ?>>
                                تبدیل خودکار تصاویر هنگام آپلود
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">حذف فایل اصلی</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="wp_webp_settings[delete_original]" 
                                       value="1" 
                                       <?php checked($settings['delete_original'], true); ?>>
                                حذف فایل اصلی بعد از تبدیل موفق
                            </label>
                            <p class="description">⚠️ توجه: بعد از حذف، فایل اصلی قابل بازیابی نیست!</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">جایگزینی خودکار</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="wp_webp_settings[auto_replace_links]" 
                                       value="1" 
                                       <?php checked($settings['auto_replace_links'], true); ?>>
                                جایگزینی خودکار لینک تصاویر در محتوا با نسخه WebP
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('💾 ذخیره تنظیمات'); ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // اسلایدر کیفیت
            $('#quality-slider').on('input', function() {
                var value = $(this).val();
                $('#quality').val(value);
                $('.quality-display').text(value + '%');
            });
            
            $('#quality').on('input', function() {
                var value = $(this).val();
                $('#quality-slider').val(value);
                $('.quality-display').text(value + '%');
            });
        });
        </script>
        <?php
    }
}

new WP_WebP_Admin_Settings();