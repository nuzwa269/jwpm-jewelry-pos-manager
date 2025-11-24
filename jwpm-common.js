/* === JWPM Jewelry POS Manager - Common JavaScript === */

jQuery(document).ready(function($) {
    
    // ایک عام فنکشن جو confirm ڈائیلاگ دکھاتا ہے
    // مثال کے طور پر، ڈیلیٹ بٹن پر کلک کرنے پر
    $('.jwpm-delete-action').on('click', function(e) {
        if (!confirm(jwpm_common_vars.confirm_message)) {
            e.preventDefault();
        }
    });

    // AJAX ریکوئسٹس کو بھیجنے کے لیے ایک عام فنکشن
    function jwpm_send_ajax_request(action, data, success_callback, error_callback) {
        $.ajax({
            url: ajaxurl, // ورڈپریس میں یہ variable خود متعین ہوتا ہے
            type: 'POST',
            data: {
                action: action,
                nonce: jwpm_common_vars.nonce, // Nonce سیکیورٹی کے لیے
                ...data // دوسرا ڈیٹا
            },
            success: function(response) {
                if (response.success) {
                    if (typeof success_callback === 'function') {
                        success_callback(response.data);
                    }
                } else {
                    if (typeof error_callback === 'function') {
                        error_callback(response.data);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                alert('An unexpected error occurred. Please check the console.');
            }
        });
    }

    // آپ اپنے کوڈ میں jwpm_send_ajax_request فنکشن کو استعمال کر سکتے ہیں

});
