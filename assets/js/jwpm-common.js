jQuery(document).ready(function($) {

    console.log("JWPM Common JS Initialized"); // ڈیبگنگ کے لیے

    /**
     * =================================================================
     * Shared helpers
     * =================================================================
     */

    function hydrateRoot($root, template) {
        $root.html(template);
        $root.data('jwpmHydrated', true);
    }

    function isHydrated($root) {
        return $root.data('jwpmHydrated') === true;
    }

    function isLoaderContent($root) {
        var text = $root.text().trim();
        return text.indexOf('Loading JWPM') !== -1 || text.length === 0;
    }

    /**
     * =================================================================
     * 1. UI RENDERING (یہ وہ حصہ ہے جو "Loading" کو ختم کرے گا)
     * =================================================================
     */

    // ڈیش بورڈ پیج
    var dashboardRoot = $('#jwpm-dashboard-root');
    if (dashboardRoot.length > 0) {
        console.log("Dashboard Root Found - Rendering UI...");
        hydrateRoot(dashboardRoot, `
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
        hydrateRoot(inventoryRoot, '<div class="jwpm-card"><h2>📦 Inventory Module Loaded</h2><p>Data grid یہاں نظر آئے گی۔</p></div>');
    }

    // POS پیج (Placeholder)
    var posRoot = $('#jwpm-pos-root');
    if (posRoot.length > 0) {
        hydrateRoot(posRoot, '<div class="jwpm-card"><h2>🛒 Point of Sale Loaded</h2><p>POS UI یہاں لوڈ ہو گا۔</p></div>');
    }

    // ہر JWPM پیج پر جنرل fallback تاکہ "Loading" کا پیغام ختم ہو جائے
    $('[id^="jwpm-"][id$="-root"]').each(function() {
        var $root = $(this);

        // اگر پہلے ہی (hydrate) ہو چکا ہے تو کچھ نہ کریں
        if (isHydrated($root)) {
            return;
        }

        // اگر کوئی اور (script) پہلے سے (HTML) inject کر چکا ہے، اور وہ صرف "Loading" نہیں
        if (!isLoaderContent($root)) {
            return;
        }

        var slug = $root.attr('id') || '';
        slug = slug.replace(/^jwpm-/, '').replace(/-root$/, '');
        var title = slug ? slug.replace(/-/g, ' ') : 'dashboard';

        // پہلا حرف بڑا کر دیں
        title = title.charAt(0).toUpperCase() + title.slice(1);

        hydrateRoot($root, `
            <div class="jwpm-card">
                <h2>JWPM ${title} ready</h2>
                <p>Assets لوڈ ہو چکے ہیں۔ اگر ڈیٹا غائب ہے تو براہِ مہربانی متعلقہ ماڈیول کی سیٹنگز چیک کریں۔</p>
            </div>
        `);
    });

    /**
     * =================================================================
     * 2. UTILITY FUNCTIONS (آپ کا کوڈ - درستگی کے ساتھ)
     * =================================================================
     */

    // Delete confirmation
    $(document).on('click', '.jwpm-delete-action', function(e) {
        // نوٹ: jwpmCommon ہم نے (assets PHP) میں define کیا تھا
        var confirmMsg = (typeof jwpmCommon !== 'undefined' && jwpmCommon.i18n && jwpmCommon.i18n.confirmDelete)
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
            url: (typeof jwpmCommon !== 'undefined' && jwpmCommon.ajax_url) ? jwpmCommon.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : ''),
            type: 'POST',
            dataType: 'json',
            data: Object.assign({
                action: action,
                nonce: nonce 
            }, data),
            success: function(response) {
                if (response && response.success) {
                    if (typeof success_callback === 'function') {
                        success_callback(response.data);
                    }
                } else {
                    if (typeof error_callback === 'function') {
                        error_callback(response ? response.data : null);
                    } else {
                        var message = (response && response.data && response.data.message)
                            ? response.data.message
                            : 'Unknown error';
                        alert('Error: ' + message);
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
