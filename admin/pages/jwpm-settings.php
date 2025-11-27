<?php
/**
 * JWPM — Settings Page (Master Control Panel)
 * یہ (PHP) فائل پورے Plugin کی Settings کا HTML Structure, Template اور Menu Registration رکھتی ہے۔
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 🟢 یہاں سے [Settings Page] شروع ہو رہا ہے

/** Part 1 — Settings Page Registration */

/**
 * Register Settings Page under main menu
 */
function jwpm_register_settings_page() {

    $parent_slug = 'jwpm-pos-manager';

    add_submenu_page(
        $parent_slug,
        __( 'Settings', 'jwpm' ),
        __( 'Settings', 'jwpm' ),
        'jwpm_owner', // Only highest role
        'jwpm-settings',
        'jwpm_render_settings_page',
        200
    );
}
add_action( 'admin_menu', 'jwpm_register_settings_page' );



/**
 * Render the Settings Page
 */
function jwpm_render_settings_page() {

    if ( ! current_user_can( 'jwpm_owner' ) ) {
        wp_die( __( 'آپ کو Settings تک رسائی کی اجازت نہیں۔', 'jwpm' ) );
    }

    $nonce = wp_create_nonce( 'jwpm_settings_nonce' );
    ?>

    <div class="wrap jwpm-admin-page jwpm-settings-page">

        <h1 class="jwpm-page-title">
            <?php esc_html_e( 'JWPM Settings', 'jwpm' ); ?>
        </h1>

        <div
            id="jwpm-settings-root"
            data-jwpm-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-jwpm-page="jwpm-settings"
            data-jwpm-module="settings"
        ></div>



        <!-- ================================================= -->
        <!-- Settings Template (Logo / Theme / Language / API) -->
        <!-- ================================================= -->

        <template id="jwpm-settings-layout">

            <section class="jwpm-settings-wrapper">

                <!-- ================================================= -->
                <!-- SECTION: LOGO MANAGER                             -->
                <!-- ================================================= -->
                <div class="jwpm-settings-section">
                    <h2><?php esc_html_e( 'Logo Manager', 'jwpm' ); ?></h2>
                    <p><?php esc_html_e( 'اپنے POS اور Reports کیلئے کمپنی لوگو اپلوڈ کریں۔', 'jwpm' ); ?></p>

                    <div class="jwpm-logo-preview" data-role="logo-preview">
                        <span><?php esc_html_e( 'No Logo Selected', 'jwpm' ); ?></span>
                    </div>

                    <input type="file" accept="image/*" data-role="logo-file" />

                    <div class="jwpm-settings-actions">
                        <button class="button" data-role="logo-upload"><?php esc_html_e( 'Upload Logo', 'jwpm' ); ?></button>
                        <button class="button" data-role="logo-remove"><?php esc_html_e( 'Remove Logo', 'jwpm' ); ?></button>
                    </div>
                </div>


                <!-- ================================================= -->
                <!-- SECTION: THEME MODE                                -->
                <!-- ================================================= -->
                <div class="jwpm-settings-section">
                    <h2><?php esc_html_e( 'Theme Mode', 'jwpm' ); ?></h2>
                    <p><?php esc_html_e( 'Light یا Dark Mode منتخب کریں۔', 'jwpm' ); ?></p>

                    <select class="jwpm-select" data-role="theme-mode">
                        <option value="light"><?php esc_html_e( 'Light Mode', 'jwpm' ); ?></option>
                        <option value="dark"><?php esc_html_e( 'Dark Mode', 'jwpm' ); ?></option>
                    </select>

                    <button class="button" data-role="theme-save">
                        <?php esc_html_e( 'Save Theme', 'jwpm' ); ?>
                    </button>
                </div>


                <!-- ================================================= -->
                <!-- SECTION: LANGUAGE SETTINGS                        -->
                <!-- ================================================= -->
                <div class="jwpm-settings-section">
                    <h2><?php esc_html_e( 'Language', 'jwpm' ); ?></h2>
                    <p><?php esc_html_e( 'زبان منتخب کریں۔', 'jwpm' ); ?></p>

                    <select class="jwpm-select" data-role="language-select">
                        <option value="ur"><?php esc_html_e( 'Urdu', 'jwpm' ); ?></option>
                        <option value="en"><?php esc_html_e( 'English', 'jwpm' ); ?></option>
                    </select>

                    <button class="button" data-role="language-save">
                        <?php esc_html_e( 'Save Language', 'jwpm' ); ?>
                    </button>
                </div>


                <!-- ================================================= -->
                <!-- SECTION: GOLD RATE API SETTINGS                  -->
                <!-- ================================================= -->
                <div class="jwpm-settings-section">
                    <h2><?php esc_html_e( 'Gold Rate API', 'jwpm' ); ?></h2>
                    <p><?php esc_html_e( 'Gold API Key درج کریں تاکہ POS خودکار طور پر ریٹس لے سکے۔', 'jwpm' ); ?></p>

                    <input type="text" class="jwpm-input" data-role="gold-api-key"
                        placeholder="<?php esc_attr_e( 'Enter Gold API Key', 'jwpm' ); ?>" />

                    <button class="button" data-role="gold-api-save">
                        <?php esc_html_e( 'Save API Key', 'jwpm' ); ?>
                    </button>
                </div>


                <!-- ================================================= -->
                <!-- SECTION: BACKUP / EXPORT SETTINGS                 -->
                <!-- ================================================= -->
                <div class="jwpm-settings-section">
                    <h2><?php esc_html_e( 'Backup & Export', 'jwpm' ); ?></h2>
                    <p><?php esc_html_e( 'اپنا مکمل ڈیٹا Excel میں ایکسپورٹ یا بیک اپ بنائیں۔', 'jwpm' ); ?></p>

                    <button class="button" data-role="backup-export">
                        <?php esc_html_e( 'Export Complete Backup', 'jwpm' ); ?>
                    </button>
                </div>


                <!-- ================================================= -->
                <!-- SECTION: RESET & DEMO DATA                        -->
                <!-- ================================================= -->
                <div class="jwpm-settings-section jwpm-danger-zone">
                    <h2><?php esc_html_e( 'Reset / Demo Data', 'jwpm' ); ?></h2>
                    <p><?php esc_html_e( 'پورے سسٹم کا ڈیٹا صاف کریں یا Demo Data شامل کریں۔', 'jwpm' ); ?></p>

                    <button class="button button-primary" data-role="demo-load">
                        <?php esc_html_e( 'Load Demo Data', 'jwpm' ); ?>
                    </button>

                    <button class="button button-danger" data-role="reset-system">
                        <?php esc_html_e( 'Reset All Data', 'jwpm' ); ?>
                    </button>
                </div>

            </section>
        </template>

    </div>

    <?php
}

// 🔴 یہاں پر [Settings Page] ختم ہو رہا ہے

// ✅ Syntax verified block end
