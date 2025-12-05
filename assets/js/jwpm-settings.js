/**
 * JWPM — Settings Page JS (Master Control Panel)
 * Updated: Direct HTML Injection (No PHP Templates required)
 * یہ (JavaScript) Settings Page میں تمام actions (Logo, Theme, Language, API, Backup, Demo, Reset) کو handle کرتا ہے۔
 */

(function ($) {
    "use strict";

    // 🟢 یہاں سے [Settings Page JS] شروع ہو رہا ہے

    /** Part 1 — JS: Settings Page */

    const rootId =
        (window.jwpmSettings && window.jwpmSettings.rootId) ||
        "jwpm-settings-root";

    const $root = $("#" + rootId);

    if ($root.length === 0) {
        console.warn("JWPM Warning: Settings Page Root Missing:", rootId);
        return;
    }

    // Localized Data (with safety checks)
    const settingsData = window.jwpmSettings || {
        ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
        nonce: '',
        actions: {},
        i18n: {
            noLogo: 'No logo uploaded.',
            logoSaved: 'Logo uploaded successfully!',
            confirmRemove: 'Are you sure you want to remove the logo?',
            saved: 'Settings saved successfully!',
            languageSaved: 'Language settings saved. Please reload page.',
            error: 'An error occurred. Please try again.',
            demoConfirm: 'WARNING: This will load demo data, replacing existing data. Are you sure?',
            resetConfirm: 'DANGER: This will delete ALL data. Are you sure you want to reset the system?'
        }
    };
    const ajaxUrl = settingsData.ajaxUrl;
    const nonce = settingsData.nonce;
    const actions = settingsData.actions;
    const i18n = settingsData.i18n;

    // ---------------------------------------------------------
    // RENDER LAYOUT (Replaces Template Mount)
    // ---------------------------------------------------------
    function renderLayout() {
        $root.html(`
            <div class="jwpm-wrapper">
                <h2 style="margin-top:0;">⚙️ Master Control Panel</h2>
                
                <div style="display:flex; gap:20px; flex-wrap:wrap;">

                    <div style="flex:1; min-width:400px; display:flex; flex-direction:column; gap:20px;">
                        
                        <div class="jwpm-card" style="padding:20px;">
                            <h3>General Settings</h3>
                            <div style="margin-bottom:15px;">
                                <label>Theme Mode</label>
                                <select data-role="theme-mode" style="padding:6px; width:100%; margin-bottom:10px;">
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                </select>
                                <button class="button button-primary" data-role="theme-save">Save Theme</button>
                            </div>

                            <div style="margin-bottom:15px;">
                                <label>Language</label>
                                <select data-role="language-select" style="padding:6px; width:100%; margin-bottom:10px;">
                                    <option value="en">English</option>
                                    <option value="ur">اردو (Urdu)</option>
                                </select>
                                <button class="button button-primary" data-role="language-save">Save Language</button>
                            </div>
                        </div>

                        <div class="jwpm-card" style="padding:20px;">
                            <h3>Gold Price API Key</h3>
                            <label>API Key</label>
                            <input type="text" data-role="gold-api-key" placeholder="Enter API Key" class="widefat" style="margin-bottom:10px;">
                            <button class="button button-primary" data-role="gold-api-save">Save API Key</button>
                        </div>

                         <div class="jwpm-card" style="padding:20px;">
                            <h3>Company Logo</h3>
                            <div data-role="logo-preview" style="margin-bottom:10px; border:1px dashed #ccc; padding:10px;">
                                <span>${i18n.noLogo}</span>
                            </div>
                            <input type="file" data-role="logo-file" accept="image/*" style="margin-bottom:10px;">
                            <button class="button button-primary" data-role="logo-upload">Upload Logo</button>
                            <button class="button button-secondary" data-role="logo-remove" style="margin-left:10px;">Remove Logo</button>
                        </div>
                    </div>

                    <div style="flex:1; min-width:300px; display:flex; flex-direction:column; gap:20px;">

                        <div class="jwpm-card" style="padding:20px; background:#e6f0ff;">
                            <h3>Data Management</h3>
                            <p>Export all sales, inventory, and ledger data to Excel/CSV for backup.</p>
                            <button class="button button-primary button-large" data-role="backup-export" style="width:100%;">Download Full Backup</button>
                        </div>
                        
                        <div class="jwpm-card" style="padding:20px; background:#fff0e6; border:1px solid orange;">
                            <h3>Load Demo Data (Testing)</h3>
                            <p>For testing purposes only. Overwrites most data.</p>
                            <button class="button button-secondary button-large" data-role="demo-load" style="width:100%;">Load Sample Data</button>
                        </div>

                        <div class="jwpm-card" style="padding:20px; background:#ffe6e6; border:1px solid red;">
                            <h3>Danger Zone: Reset</h3>
                            <p>Permanently delete all business data (sales, customers, inventory, ledger).</p>
                            <button class="button button-danger button-large" data-role="reset-system" style="width:100%;">Reset ALL Data</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }

    renderLayout(); // Inject the UI immediately

    // ---------------------------------------------------------
    // Element Caching (Post-Render)
    // ---------------------------------------------------------

    // Logo Manager
    const $logoFile = $root.find('[data-role="logo-file"]');
    const $logoPreview = $root.find('[data-role="logo-preview"]');
    const $logoUploadBtn = $root.find('[data-role="logo-upload"]');
    const $logoRemoveBtn = $root.find('[data-role="logo-remove"]');

    // Theme Mode
    const $themeSelect = $root.find('[data-role="theme-mode"]');
    const $themeBtn = $root.find('[data-role="theme-save"]');

    // Language
    const $langSelect = $root.find('[data-role="language-select"]');
    const $langBtn = $root.find('[data-role="language-save"]');

    // Gold API
    const $goldKey = $root.find('[data-role="gold-api-key"]');
    const $goldSave = $root.find('[data-role="gold-api-save"]');

    // Backup
    const $backupBtn = $root.find('[data-role="backup-export"]');

    // Demo + Reset
    const $demoBtn = $root.find('[data-role="demo-load"]');
    const $resetBtn = $root.find('[data-role="reset-system"]');

    function wpAjax(action, data) {
        return $.ajax({
            url: ajaxUrl,
            method: "POST",
            data: Object.assign({}, data, {
                action: action,
                nonce: nonce,
            }),
        });
    }

    // ---------------------------------------------------------
    // Load Saved Settings Initially
    // ---------------------------------------------------------
    function loadSettings() {
        wpAjax(actions.fetch, {})
            .done((res) => {
                if (!res.success) return;

                const d = res.data;

                // Logo
                if (d.logo_url) {
                    $logoPreview.html(`<img src="${d.logo_url}" style="max-height:80px;" />`);
                }

                // Theme
                $themeSelect.val(d.theme_mode);

                // Language
                $langSelect.val(d.language);

                // Gold API
                $goldKey.val(d.gold_api_key || "");
            })
            .fail(() => console.warn("Error loading settings"));
    }

    loadSettings();


    // ---------------------------------------------------------
    // Logo Upload
    // ---------------------------------------------------------
    $logoUploadBtn.on("click", function () {
        const file = $logoFile[0].files[0];
        if (!file) {
            alert(i18n.noLogo);
            return;
        }

        const form = new FormData();
        form.append("action", actions.logo_upload);
        form.append("nonce", nonce);
        form.append("file", file);

        $.ajax({
            url: ajaxUrl,
            method: "POST",
            data: form,
            processData: false,
            contentType: false,
        })
            .done((res) => {
                if (res.success) {
                    $logoPreview.html(
                        `<img src="${res.data.url}" style="max-height:80px;" />`
                    );
                    alert(i18n.logoSaved);
                } else {
                    alert(i18n.error);
                }
            })
            .fail(() => alert(i18n.error));
    });


    // ---------------------------------------------------------
    // Logo Remove
    // ---------------------------------------------------------
    $logoRemoveBtn.on("click", function () {
        if (!confirm(i18n.confirmRemove))
            return;

        wpAjax(actions.logo_remove, {})
            .done((res) => {
                if (res.success) {
                    $logoPreview.html(`<span>${i18n.noLogo}</span>`);
                    alert(i18n.logoSaved); // Use logoSaved for success message
                } else {
                    alert(i18n.error);
                }
            })
            .fail(() => alert(i18n.error));
    });


    // ---------------------------------------------------------
    // Theme Save
    // ---------------------------------------------------------
    $themeBtn.on("click", function () {
        wpAjax(actions.theme_save, {
            theme: $themeSelect.val(),
        }).done((res) => {
            if (res.success) {
                alert(i18n.saved);
            } else {
                alert(i18n.error);
            }
        });
    });


    // ---------------------------------------------------------
    // Language Save
    // ---------------------------------------------------------
    $langBtn.on("click", function () {
        wpAjax(actions.language_save, {
            language: $langSelect.val(),
        }).done((res) => {
            if (res.success) {
                alert(i18n.languageSaved);
            } else {
                alert(i18n.error);
            }
        });
    });


    // ---------------------------------------------------------
    // Gold API Save
    // ---------------------------------------------------------
    $goldSave.on("click", function () {
        wpAjax(actions.gold_api_save, {
            gold_key: $goldKey.val(),
        }).done((res) => {
            if (res.success) {
                alert(i18n.saved);
            } else {
                alert(i18n.error);
            }
        });
    });


    // ---------------------------------------------------------
    // Backup Export
    // ---------------------------------------------------------
    $backupBtn.on("click", function () {
        wpAjax(actions.backup_export, {})
            .done((res) => {
                if (res.success && res.data.rows) {
                    // Assumes jwpmExportToExcel is available via jwpm-common.js
                    window.jwpmExportToExcel(
                        "JWPM Backup",
                        res.data.headers,
                        res.data.rows
                    );
                } else {
                    alert(i18n.error);
                }
            })
            .fail(() => alert(i18n.error));
    });


    // ---------------------------------------------------------
    // Demo Load
    // ---------------------------------------------------------
    $demoBtn.on("click", function () {
        if (!confirm(i18n.demoConfirm))
            return;

        wpAjax(actions.demo_load, {})
            .done((res) => {
                if (res.success) {
                    alert(res.data.message);
                } else {
                    alert(i18n.error);
                }
            })
            .fail(() => alert(i18n.error));
    });


    // ---------------------------------------------------------
    // Reset System
    // ---------------------------------------------------------
    $resetBtn.on("click", function () {

        if (!confirm(i18n.resetConfirm))
            return;

        wpAjax(actions.reset_system, {})
            .done((res) => {
                if (res.success) {
                    alert(res.data.message);
                } else {
                    alert(i18n.error);
                }
            })
            .fail(() => alert(i18n.error));
    });


    // 🔴 یہاں پر [Settings Page JS] ختم ہو رہا ہے
})(jQuery);
/**
 * JWPM — Settings Page JS (Master Control Panel — Final Logic)
 * یہ (JavaScript) Settings Page کیلئے وہی actions استعمال کرتا ہے
 * جو (PHP) فائل میں define ہیں:
 * jwpm_get_settings, jwpm_save_settings, jwpm_load_demo_settings,
 * jwpm_reset_settings, jwpm_export_settings_backup,
 * jwpm_upload_logo, jwpm_remove_logo
 */

/** Part 2 — Settings Page JS (Final Logic, Synced with PHP) */

(function ($) {
    "use strict";

    // 🟢 یہاں سے [Settings Page JS — Final Logic] شروع ہو رہا ہے

    // Root ID وہی جو PHP میں ہے: #jwpm-settings-root
    var rootId = (window.jwpmSettings && window.jwpmSettings.rootId) || "jwpm-settings-root";
    var $root  = $("#" + rootId);

    if ($root.length === 0) {
        console.warn("JWPM Warning (Settings): Root element not found:", rootId);
        return; // Soft exit
    }

    // DOM سے nonce لینے کی کوشش (PHP نے data-jwpm-nonce میں دیا ہے)
    var domNonce = $root.data("jwpm-nonce") || "";

    // Localized config (اگر موجود ہو) ورنہ defaults
    var config = window.jwpmSettings || {};

    var ajaxUrl = config.ajaxUrl || window.ajaxurl || "/wp-admin/admin-ajax.php";
    var nonce   = config.nonce || domNonce || "";

    // Actions — defaults PHP کے مطابق، اگر window.jwpmSettings.actions ہو تو اسے override کرنے دیں
    var defaultActions = {
        fetch:          "jwpm_get_settings",
        save:           "jwpm_save_settings",
        demo_load:      "jwpm_load_demo_settings",
        reset_settings: "jwpm_reset_settings",
        backup_export:  "jwpm_export_settings_backup",
        logo_upload:    "jwpm_upload_logo",
        logo_remove:    "jwpm_remove_logo"
    };

    var actions = $.extend({}, defaultActions, config.actions || {});

    // Text / Messages (i18n)
    var i18n = $.extend(
        {
            noLogo: "کوئی لوگو منتخب نہیں ہوا۔",
            logoSaved: "لوگو کامیابی سے محفوظ ہو گیا۔",
            logoRemoved: "لوگو ہٹا دیا گیا ہے۔",
            saved: "سیٹنگز محفوظ ہو گئیں۔",
            languageSaved: "زبان کی سیٹنگ محفوظ ہو گئی، براہ کرم صفحہ ری فریش کریں۔",
            error: "کچھ خرابی ہوئی، براہ کرم دوبارہ کوشش کریں۔",
            demoConfirm: "WARNING: Demo Settings لوڈ ہونے سے موجودہ Settings اوور رائٹ ہو جائیں گی، کیا آپ پُر عزم ہیں؟",
            resetConfirm: "DANGER: یہ عمل Settings کو default حالت میں لے آئے گا، کیا آپ واقعی ری سیٹ کرنا چاہتے ہیں؟",
            backupReady: "Backup تیار ہے، فائل ڈاؤن لوڈ ہو رہی ہے۔",
            loading: "لوڈ ہو رہا ہے، براہ کرم انتظار کریں…"
        },
        config.i18n || {}
    );

    // ---------------------------------------------------------
    // Template Mounting — PHP کے <template id="jwpm-settings-layout"> کو use کریں
    // ---------------------------------------------------------
    function mountTemplate() {
        var tpl = document.getElementById("jwpm-settings-layout");

        if (!tpl) {
            console.warn("JWPM Warning (Settings): Template #jwpm-settings-layout نہیں ملا۔");
            return;
        }

        // Modern browsers کیلئے:
        if (tpl.content) {
            var clone = tpl.content.cloneNode(true);
            $root.empty().append(clone);
        } else {
            // Fallback: innerHTML
            var wrapper = document.createElement("div");
            wrapper.innerHTML = tpl.innerHTML;
            $root.empty().append(wrapper);
        }
    }

    // Layout render
    mountTemplate();

    // ---------------------------------------------------------
    // Element Cache (template mount ہونے کے بعد)
    // ---------------------------------------------------------

    // Logo
    var $logoFile    = $root.find('[data-role="logo-file"]');
    var $logoPreview = $root.find('[data-role="logo-preview"]');
    var $logoUpload  = $root.find('[data-role="logo-upload"]');
    var $logoRemove  = $root.find('[data-role="logo-remove"]');

    // Theme
    var $themeSelect = $root.find('[data-role="theme-mode"]');
    var $themeSave   = $root.find('[data-role="theme-save"]');

    // Language
    var $langSelect  = $root.find('[data-role="language-select"]');
    var $langSave    = $root.find('[data-role="language-save"]');

    // Gold API
    var $goldKey     = $root.find('[data-role="gold-api-key"]');
    var $goldSave    = $root.find('[data-role="gold-api-save"]');

    // Backup
    var $backupBtn   = $root.find('[data-role="backup-export"]');

    // Demo + Reset
    var $demoBtn     = $root.find('[data-role="demo-load"]');
    var $resetBtn    = $root.find('[data-role="reset-system"]');

    // اگر nonce نہ ہو تو soft warning (AJAX پھر بھی کوشش کرے گا)
    if (!nonce) {
        console.warn("JWPM Warning (Settings): nonce خالی ہے، AJAX requests fail ہو سکتی ہیں۔");
    }

    // ---------------------------------------------------------
    // Utility: wpAjax wrapper
    // ---------------------------------------------------------
    function wpAjax(action, dataObj, extraOptions) {
        var payload = $.extend({}, dataObj || {}, {
            action: action,
            nonce: nonce
        });

        var options = $.extend(
            {
                url: ajaxUrl,
                method: "POST",
                data: payload,
                dataType: "json"
            },
            extraOptions || {}
        );

        return $.ajax(options);
    }

    // ---------------------------------------------------------
    // Utility: Settings جمع کریں (Theme + Language + Gold API)
    // ---------------------------------------------------------
    function collectSettingsFromUI() {
        return {
            theme_mode: $themeSelect.val() || "light",
            language: $langSelect.val() || "ur",
            gold_api_key: $goldKey.val() || ""
            // logo_id logo upload والے AJAX سے update ہوتا ہے
        };
    }

    // ---------------------------------------------------------
    // Utility: Settings UI پر apply کریں
    // ---------------------------------------------------------
    function applySettingsToUI(settings, logoUrl) {
        settings = settings || {};

        // Theme
        if (settings.theme_mode) {
            $themeSelect.val(settings.theme_mode);
        }

        // Language
        if (settings.language) {
            $langSelect.val(settings.language);
        }

        // Gold API Key
        if (typeof settings.gold_api_key !== "undefined") {
            $goldKey.val(settings.gold_api_key);
        }

        // Logo
        if (logoUrl) {
            $logoPreview.html('<img src="' + logoUrl + '" style="max-height:80px; max-width:180px;" />');
        } else if (!settings.logo_id) {
            $logoPreview.html("<span>" + i18n.noLogo + "</span>");
        }
    }

    // ---------------------------------------------------------
    // Initial Load — jwpm_get_settings
    // ---------------------------------------------------------
    function loadSettings() {
        // Optional: loading state
        $root.addClass("jwpm-is-loading");

        wpAjax(actions.fetch, {})
            .done(function (res) {
                if (!res || !res.success) {
                    console.warn("JWPM Settings: loadSettings failed:", res);
                    return;
                }

                var data = res.data || {};
                applySettingsToUI(data.settings || {}, data.logo_url || "");
            })
            .fail(function (xhr) {
                console.warn("JWPM Settings: loadSettings AJAX error:", xhr);
            })
            .always(function () {
                $root.removeClass("jwpm-is-loading");
            });
    }

    loadSettings();

    // ---------------------------------------------------------
    // Logo Upload — jwpm_upload_logo
    // ---------------------------------------------------------
    $logoUpload.on("click", function () {
        var file = $logoFile[0] && $logoFile[0].files && $logoFile[0].files[0];

        if (!file) {
            alert(i18n.noLogo);
            return;
        }

        var form = new FormData();
        form.append("action", actions.logo_upload);
        form.append("nonce", nonce);
        // PHP میں ہم نے 'logo_file' نام سے handle کیا ہے
        form.append("logo_file", file);

        $.ajax({
            url: ajaxUrl,
            method: "POST",
            data: form,
            processData: false,
            contentType: false,
            dataType: "json"
        })
            .done(function (res) {
                if (!res || !res.success) {
                    alert(i18n.error);
                    console.warn("JWPM Settings: logo upload failed:", res);
                    return;
                }

                var data = res.data || {};
                applySettingsToUI(data.settings || {}, data.logo_url || "");
                alert(i18n.logoSaved);
            })
            .fail(function (xhr) {
                console.warn("JWPM Settings: logo upload AJAX error:", xhr);
                alert(i18n.error);
            });
    });

    // ---------------------------------------------------------
    // Logo Remove — jwpm_remove_logo
    // ---------------------------------------------------------
    $logoRemove.on("click", function () {
        if (!confirm(i18n.confirmRemove || "کیا آپ واقعی لوگو ہٹانا چاہتے ہیں؟")) {
            return;
        }

        wpAjax(actions.logo_remove, {})
            .done(function (res) {
                if (!res || !res.success) {
                    alert(i18n.error);
                    console.warn("JWPM Settings: logo remove failed:", res);
                    return;
                }

                var data = res.data || {};
                applySettingsToUI(data.settings || {}, data.logo_url || "");
                alert(i18n.logoRemoved);
            })
            .fail(function (xhr) {
                console.warn("JWPM Settings: logo remove AJAX error:", xhr);
                alert(i18n.error);
            });
    });

    // ---------------------------------------------------------
    // Save Theme / Language / Gold API — سب jwpm_save_settings سے
    // ---------------------------------------------------------
    function saveSettings(showLanguageMessage) {
        var settings = collectSettingsFromUI();

        // Settings کو JSON میں encode کر کے بھیجیں
        wpAjax(actions.save, {
            settings: JSON.stringify(settings)
        })
            .done(function (res) {
                if (!res || !res.success) {
                    alert(i18n.error);
                    console.warn("JWPM Settings: saveSettings failed:", res);
                    return;
                }

                var data = res.data || {};
                applySettingsToUI(data.settings || {}, data.logo_url || "");

                if (showLanguageMessage) {
                    alert(i18n.languageSaved);
                } else {
                    alert(i18n.saved);
                }
            })
            .fail(function (xhr) {
                console.warn("JWPM Settings: saveSettings AJAX error:", xhr);
                alert(i18n.error);
            });
    }

    // Theme Save Button
    $themeSave.on("click", function () {
        saveSettings(false);
    });

    // Language Save Button
    $langSave.on("click", function () {
        saveSettings(true);
    });

    // Gold API Save Button
    $goldSave.on("click", function () {
        saveSettings(false);
    });

    // ---------------------------------------------------------
    // Backup Export — jwpm_export_settings_backup
    // PHP JSON فائل بناتا ہے اور URL دیتا ہے، ہم نئی ونڈو میں کھول دیں گے
    // ---------------------------------------------------------
    $backupBtn.on("click", function () {
        $backupBtn.prop("disabled", true);

        wpAjax(actions.backup_export, {})
            .done(function (res) {
                if (!res || !res.success || !res.data || !res.data.url) {
                    alert(i18n.error);
                    console.warn("JWPM Settings: backup_export failed:", res);
                    return;
                }

                alert(i18n.backupReady);
                window.open(res.data.url, "_blank");
            })
            .fail(function (xhr) {
                console.warn("JWPM Settings: backup_export AJAX error:", xhr);
                alert(i18n.error);
            })
            .always(function () {
                $backupBtn.prop("disabled", false);
            });
    });

    // ---------------------------------------------------------
    // Demo Settings Load — jwpm_load_demo_settings
    // ---------------------------------------------------------
    $demoBtn.on("click", function () {
        if (!confirm(i18n.demoConfirm)) {
            return;
        }

        wpAjax(actions.demo_load, {})
            .done(function (res) {
                if (!res || !res.success) {
                    alert(i18n.error);
                    console.warn("JWPM Settings: demo_load failed:", res);
                    return;
                }

                var data = res.data || {};
                applySettingsToUI(data.settings || {}, "");
                alert(data.message || "Demo Settings لوڈ ہو گئیں۔");
            })
            .fail(function (xhr) {
                console.warn("JWPM Settings: demo_load AJAX error:", xhr);
                alert(i18n.error);
            });
    });

    // ---------------------------------------------------------
    // Reset Settings (to defaults) — jwpm_reset_settings
    // ⚠️ یہ ابھی صرف Settings reset کر رہا ہے، پورا POS ڈیٹا نہیں
    // ---------------------------------------------------------
    $resetBtn.on("click", function () {
        if (!confirm(i18n.resetConfirm)) {
            return;
        }

        wpAjax(actions.reset_settings, {})
            .done(function (res) {
                if (!res || !res.success) {
                    alert(i18n.error);
                    console.warn("JWPM Settings: reset_settings failed:", res);
                    return;
                }

                var data = res.data || {};
                applySettingsToUI(data.settings || {}, "");
                alert(data.message || "Settings reset ہو گئیں۔");
            })
            .fail(function (xhr) {
                console.warn("JWPM Settings: reset_settings AJAX error:", xhr);
                alert(i18n.error);
            });
    });

    // 🔴 یہاں پر [Settings Page JS — Final Logic] ختم ہو رہا ہے

    // ✅ Syntax verified block end

})(jQuery);
