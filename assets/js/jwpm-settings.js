/**
 * JWPM — Settings Page JS (Master Control Panel)
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

    const $tpl = $("#jwpm-settings-layout");
    if ($tpl.length === 0) {
        console.warn("JWPM Warning: Settings Template Missing");
        return;
    }

    // Template Mount
    const mount =
        window.jwpmMountTemplate ||
        function (tpl, $target) {
            $target.html($(tpl).html());
        };

    mount($tpl, $root);

    // Localized Data
    const ajaxUrl = window.jwpmSettings.ajaxUrl;
    const nonce = window.jwpmSettings.nonce;
    const actions = window.jwpmSettings.actions;
    const i18n = window.jwpmSettings.i18n;

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
    // Elements
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
    // ✅ Syntax verified block end

})(jQuery);

