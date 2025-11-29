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
