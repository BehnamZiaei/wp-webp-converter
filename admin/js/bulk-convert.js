jQuery(document).ready(function($) {
    let imagesToConvert = [];
    let currentIndex = 0;
    let successCount = 0;
    let errorCount = 0;
    
    $('#wp-webp-start-bulk').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).html('⏳ در حال دریافت لیست تصاویر...');
        
        // ریست کردن متغیرها
        currentIndex = 0;
        successCount = 0;
        errorCount = 0;
        $('#wp-webp-progress-details').empty();
        
        // دریافت لیست تصاویر
        $.ajax({
            url: wpWebpAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_webp_get_images',
                nonce: wpWebpAjax.nonce
            },
            success: function(response) {
                console.log('پاسخ سرور:', response);
                
                if (response.success) {
                    imagesToConvert = response.data.images;
                    
                    if (imagesToConvert.length === 0) {
                        alert('✅ تمام تصاویر قبلاً به WebP تبدیل شده‌اند!');
                        $button.prop('disabled', false).html('🚀 شروع تبدیل دسته‌جمعی');
                        return;
                    }
                    
                    console.log('تعداد تصاویر برای تبدیل:', imagesToConvert.length);
                    $('#wp-webp-progress-container').slideDown();
                    convertNextImage();
                } else {
                    alert('خطا: ' + (response.data || 'خطای ناشناخته'));
                    $button.prop('disabled', false).html('🚀 شروع تبدیل دسته‌جمعی');
                }
            },
            error: function(xhr, status, error) {
                console.error('خطا در AJAX:', status, error);
                alert('خطا در برقراری ارتباط با سرور: ' + error);
                $button.prop('disabled', false).html('🚀 شروع تبدیل دسته‌جمعی');
            }
        });
    });
    
    function convertNextImage() {
        if (currentIndex >= imagesToConvert.length) {
            // تمام تصاویر تبدیل شدند
            const totalProcessed = successCount + errorCount;
            $('#wp-webp-progress-text').html(
                `✅ پردازش کامل شد!<br>` +
                `موفق: <span style="color: green;">${successCount}</span> | ` +
                `ناموفق: <span style="color: red;">${errorCount}</span>`
            );
            $('#wp-webp-progress-percentage').text('100%');
            $('#wp-webp-progress-bar').css('width', '100%');
            $('#wp-webp-start-bulk').prop('disabled', false).html('🚀 شروع تبدیل دسته‌جمعی');
            
            // رفرش صفحه بعد از 3 ثانیه
            setTimeout(function() {
                location.reload();
            }, 3000);
            return;
        }
        
        const image = imagesToConvert[currentIndex];
        const percentage = Math.round(((currentIndex + 1) / imagesToConvert.length) * 100);
        
        // به‌روزرسانی نوار پیشرفت
        $('#wp-webp-progress-bar').css('width', percentage + '%');
        $('#wp-webp-progress-percentage').text(percentage + '%');
        $('#wp-webp-progress-text').html(
            `در حال تبدیل ${currentIndex + 1} از ${imagesToConvert.length}<br>` +
            `<small>${image.name}</small>`
        );
        
        console.log('در حال تبدیل:', image.name, 'ID:', image.id);
        
        // تبدیل تصویر
        $.ajax({
            url: wpWebpAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_webp_convert_image',
                nonce: wpWebpAjax.nonce,
                image_id: image.id
            },
            success: function(response) {
                console.log('پاسخ تبدیل:', response);
                
                if (response.success) {
                    successCount++;
                    const details = `
                        <div class="conversion-result success">
                            <strong>✅ ${image.name}</strong><br>
                            <small>
                                حجم اصلی: ${response.data.original_size} → 
                                WebP: ${response.data.webp_size} 
                                (صرفه‌جویی: ${response.data.savings})
                            </small>
                        </div>
                    `;
                    $('#wp-webp-progress-details').prepend(details);
                } else {
                    errorCount++;
                    const details = `
                        <div class="conversion-result error">
                            <strong>❌ ${image.name}</strong><br>
                            <small>خطا: ${response.data || 'خطای ناشناخته'}</small>
                        </div>
                    `;
                    $('#wp-webp-progress-details').prepend(details);
                }
                
                currentIndex++;
                // تاخیر کوتاه برای جلوگیری از فشار به سرور
                setTimeout(convertNextImage, 500);
            },
            error: function(xhr, status, error) {
                console.error('خطا در تبدیل:', error);
                errorCount++;
                const details = `
                    <div class="conversion-result error">
                        <strong>❌ ${image.name}</strong><br>
                        <small>خطا در برقراری ارتباط: ${error}</small>
                    </div>
                `;
                $('#wp-webp-progress-details').prepend(details);
                
                currentIndex++;
                setTimeout(convertNextImage, 500);
            }
        });
    }
});