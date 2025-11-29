jQuery(document).ready(function($) {

    console.log("JWPM Common JS Initialized"); // ڈیبگنگ کے لیے

    /**
     * =================================================================
     * 1. UI RENDERING (یہ وہ حصہ ہے جو "Loading" کو ختم کرے گا)
     * =================================================================
     */

    // ڈیش بورڈ پیج
    var dashboardRoot = $('#jwpm-dashboard-root');
    if (dashboardRoot.length > 0) {
        console.log("Dashboard Root Found - Rendering UI...");
        dashboardRoot.html(`
            <div class="jwpm-card">
                <h2>👋 خوش آمدید! JWPM Dashboard</h2>
                <p>سسٹم کامیابی سے انسٹال ہو چکا ہے۔ اب آپ نیچے دیے گئے ماڈیولز استعمال کر سکتے ہیں۔</p>
                <hr>
                <div style="display: flex; gap: 20px; margin-top: 20px;">
                    <a href="admin.php?page=jwpm-pos" class="button button-primary button-hero">🛒 Point of Sale کھولیں</a>
                    <a href="admin.php?page=jwpm-inventory" class="button button-secondary button-hero">📦 Inventory چیک کریں</a>
                </div>
            </div>
        `);
    }

    // انوینٹری پیج (Placeholder)
    var inventoryRoot = $('#jwpm-inventory-root');
    if (inventoryRoot.length > 0) {
        // نوٹ: اگر inventory.js موجود ہے تو وہ اسے اوور رائٹ کر دے گا، یہ صرف بیک اپ ہے۔
        inventoryRoot.html('<div class="jwpm-card"><h2>📦 Inventory Module Loaded</h2><p>Data grid will appear here.</p></div>');
    }

    // POS پیج (Placeholder)
    var posRoot = $('#jwpm-pos-root');
    if (posRoot.length > 0) {
        posRoot.html('<div class="jwpm-card"><h2>🛒 Point of Sale Loaded</h2><p>POS UI will appear here.</p></div>');
    }


    /**
     * =================================================================
     * 2. UTILITY FUNCTIONS (آپ کا کوڈ - درستگی کے ساتھ)
     * =================================================================
     */

    // Delete confirmation
    $(document).on('click', '.jwpm-delete-action', function(e) {
        // نوٹ: jwpmCommon ہم نے assets php میں define کیا تھا
        var confirmMsg = (typeof jwpmCommon !== 'undefined' && jwpmCommon.i18n.confirmDelete) 
                         ? jwpmCommon.i18n.confirmDelete 
                         : 'Are you sure?';
        
        if (!confirm(confirmMsg)) {
            e.preventDefault();
        }
    });

    // AJAX Request Helper (گلوبل سکوپ میں تاکہ باقی فائلز استعمال کر سکیں)
    window.jwpm_send_ajax_request = function(action, data, success_callback, error_callback) {
        
        // Nonce چیک کریں
        var nonce = (typeof jwpmCommon !== 'undefined') ? jwpmCommon.nonce_common : '';

        $.ajax({
            url: (typeof jwpmCommon !== 'undefined') ? jwpmCommon.ajax_url : ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: Object.assign({
                action: action,
                nonce: nonce 
            }, data),
            success: function(response) {
                if (response.success) {
                    if (typeof success_callback === 'function') {
                        success_callback(response.data);
                    }
                } else {
                    if (typeof error_callback === 'function') {
                        error_callback(response.data);
                    } else {
                        alert('Error: ' + (response.data.message || 'Unknown error'));
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                alert('An unexpected error occurred. Please check the console.');
            }
        });
    };

});
