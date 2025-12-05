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
<?php
/** Part 2 — Settings Page Server Logic & AJAX
 * یہ حصہ (Settings) کو محفوظ / لوڈ، لوگو، Demo Settings، Reset اور Backup
 * کیلئے ضروری (PHP + AJAX) لاجک مہیا کرے گا۔
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Default Settings
 * Theme, Language, API Key, Logo ID وغیرہ کی بنیادی values
 */
function jwpm_settings_get_defaults() {
    return array(
        'theme_mode'   => 'light', // light | dark
        'language'     => 'ur',    // ur | en
        'gold_api_key' => '',
        'logo_id'      => 0,
    );
}

/**
 * تمام Settings لوڈ کریں (saved + defaults merge ہو کر)
 */
function jwpm_settings_get_all() {

    $defaults = jwpm_settings_get_defaults();
    $saved    = get_option( 'jwpm_settings', array() );

    if ( ! is_array( $saved ) ) {
        $saved = array();
    }

    return array_merge( $defaults, $saved );
}

/**
 * Settings کو صاف / محفوظ شکل میں تبدیل کریں
 */
function jwpm_settings_sanitize( $data ) {

    $clean = array();

    // Theme Mode
    if ( isset( $data['theme_mode'] ) ) {
        $mode = $data['theme_mode'];

        if ( 'dark' === $mode ) {
            $clean['theme_mode'] = 'dark';
        } else {
            $clean['theme_mode'] = 'light';
        }
    }

    // Language
    if ( isset( $data['language'] ) ) {
        $lang = $data['language'];
        $clean['language'] = ( 'en' === $lang ) ? 'en' : 'ur';
    }

    // Gold API Key
    if ( isset( $data['gold_api_key'] ) ) {
        $clean['gold_api_key'] = sanitize_text_field( $data['gold_api_key'] );
    }

    // Logo Attachment ID
    if ( isset( $data['logo_id'] ) ) {
        $clean['logo_id'] = absint( $data['logo_id'] );
    }

    return $clean;
}

/**
 * Settings اپڈیٹ کریں (موجودہ + نئی values merge ہو کر save ہوں گی)
 */
function jwpm_settings_update( $data ) {

    $current   = jwpm_settings_get_all();
    $sanitized = jwpm_settings_sanitize( $data );
    $merged    = array_merge( $current, $sanitized );

    update_option( 'jwpm_settings', $merged );

    return $merged;
}

/**
 * Common Security Check (Settings کے تمام AJAX کیلئے)
 * - Role: jwpm_owner
 * - Nonce: jwpm_settings_nonce
 */
function jwpm_settings_verify_request() {

    if ( ! current_user_can( 'jwpm_owner' ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'آپ کو اس کارروائی کی اجازت نہیں۔', 'jwpm' ),
            ),
            403
        );
    }

    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

    if ( ! wp_verify_nonce( $nonce, 'jwpm_settings_nonce' ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'سیکیورٹی جانچ ناکام ہوگئی، براہ کرم صفحہ ریفریش کریں۔', 'jwpm' ),
            ),
            400
        );
    }
}

/**
 * (AJAX) — موجودہ Settings لوڈ کریں
 * action: jwpm_get_settings
 */
function jwpm_ajax_get_settings() {

    jwpm_settings_verify_request();

    $settings = jwpm_settings_get_all();

    // لوگو کا URL ساتھ بھیج دیں (اگر موجود ہو)
    $logo_url = '';
    if ( ! empty( $settings['logo_id'] ) ) {
        $logo_src = wp_get_attachment_image_src( $settings['logo_id'], 'medium' );
        if ( $logo_src ) {
            $logo_url = $logo_src[0];
        }
    }

    wp_send_json_success(
        array(
            'settings' => $settings,
            'logo_url' => $logo_url,
            'message'  => __( 'Settings کامیابی سے لوڈ ہو گئیں۔', 'jwpm' ),
        )
    );
}
add_action( 'wp_ajax_jwpm_get_settings', 'jwpm_ajax_get_settings' );

/**
 * (AJAX) — Settings محفوظ کریں
 * action: jwpm_save_settings
 *
 * JS سے settings ایک (JSON) آبجیکٹ کی صورت میں آئیں گی۔
 */
function jwpm_ajax_save_settings() {

    jwpm_settings_verify_request();

    $raw_settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

    if ( is_string( $raw_settings ) ) {
        $decoded = json_decode( $raw_settings, true );
        if ( is_array( $decoded ) ) {
            $raw_settings = $decoded;
        } else {
            $raw_settings = array();
        }
    }

    if ( ! is_array( $raw_settings ) ) {
        $raw_settings = array();
    }

    $updated = jwpm_settings_update( $raw_settings );

    wp_send_json_success(
        array(
            'settings' => $updated,
            'message'  => __( 'Settings کامیابی سے محفوظ ہو گئیں۔', 'jwpm' ),
        )
    );
}
add_action( 'wp_ajax_jwpm_save_settings', 'jwpm_ajax_save_settings' );

/**
 * (AJAX) — Demo Settings لوڈ کریں
 * action: jwpm_load_demo_settings
 */
function jwpm_ajax_load_demo_settings() {

    jwpm_settings_verify_request();

    $demo = jwpm_settings_get_defaults();

    // Demo کیلئے تھوڑا سا بامعنی ڈیٹا رکھ دیں
    $demo['theme_mode']   = 'dark';
    $demo['language']     = 'ur';
    $demo['gold_api_key'] = 'DEMO-GOLD-API-KEY';

    update_option( 'jwpm_settings', $demo );

    wp_send_json_success(
        array(
            'settings' => $demo,
            'message'  => __( 'Demo Settings لوڈ ہو گئیں۔', 'jwpm' ),
        )
    );
}
add_action( 'wp_ajax_jwpm_load_demo_settings', 'jwpm_ajax_load_demo_settings' );

/**
 * (AJAX) — Settings Reset کریں (صرف Settings, مکمل POS ڈیٹا نہیں)
 * action: jwpm_reset_settings
 *
 * نوٹ: یہاں صرف Settings reset ہو رہی ہیں، اگر آپ پورا POS ڈیٹا بھی
 * reset کرنا چاہیں تو وہ الگ (PHP) لاجک اور (SQL) tables کے مطابق ہوگا۔
 */
function jwpm_ajax_reset_settings() {

    jwpm_settings_verify_request();

    $defaults = jwpm_settings_get_defaults();
    update_option( 'jwpm_settings', $defaults );

    wp_send_json_success(
        array(
            'settings' => $defaults,
            'message'  => __( 'Settings default حالت میں reset ہو گئیں۔', 'jwpm' ),
        )
    );
}
add_action( 'wp_ajax_jwpm_reset_settings', 'jwpm_ajax_reset_settings' );

/**
 * (AJAX) — Settings Backup / Export (JSON فائل)
 * action: jwpm_export_settings_backup
 *
 * Backup فائل (wp-content/uploads/jwpm-backups/) میں بنائی جائے گی،
 * اور JS کو اس کا ڈاؤن لوڈ (URL) واپس ملے گا۔
 */
function jwpm_ajax_export_settings_backup() {

    jwpm_settings_verify_request();

    $settings = jwpm_settings_get_all();

    $payload = array(
        'generated_at' => current_time( 'mysql' ),
        'plugin'       => 'jwpm-jewelry-pos-manager',
        'type'         => 'settings_backup',
        'settings'     => $settings,
    );

    $upload_dir = wp_upload_dir();

    if ( ! empty( $upload_dir['error'] ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'Backup فولڈر تک رسائی نہیں ہو سکی۔', 'jwpm' ),
            )
        );
    }

    $dir = trailingslashit( $upload_dir['basedir'] ) . 'jwpm-backups/';

    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
    }

    $filename = 'jwpm-settings-backup-' . gmdate( 'Ymd-His' ) . '.json';
    $path     = $dir . $filename;

    $written = file_put_contents( $path, wp_json_encode( $payload ) );

    if ( ! $written ) {
        wp_send_json_error(
            array(
                'message' => __( 'Backup فائل نہیں بن سکی۔', 'jwpm' ),
            )
        );
    }

    $url = trailingslashit( $upload_dir['baseurl'] ) . 'jwpm-backups/' . $filename;

    wp_send_json_success(
        array(
            'url'     => esc_url_raw( $url ),
            'message' => __( 'Settings backup تیار ہے، ڈاؤن لوڈ کے لئے لنک استعمال کریں۔', 'jwpm' ),
        )
    );
}
add_action( 'wp_ajax_jwpm_export_settings_backup', 'jwpm_ajax_export_settings_backup' );

/**
 * (AJAX) — Logo Upload
 * action: jwpm_upload_logo
 *
 * JS کو (FormData) کے ذریعے `logo_file` کے نام سے فائل بھیجنی ہوگی۔
 */
function jwpm_ajax_upload_logo() {

    jwpm_settings_verify_request();

    if ( empty( $_FILES['logo_file'] ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'کوئی لوگو فائل موصول نہیں ہوئی۔', 'jwpm' ),
            ),
            400
        );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_handle_upload( 'logo_file', 0 );

    if ( is_wp_error( $attachment_id ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'لوگو اپلوڈ ناکام رہا۔', 'jwpm' ),
            ),
            400
        );
    }

    $settings = jwpm_settings_update(
        array(
            'logo_id' => $attachment_id,
        )
    );

    $logo_src = wp_get_attachment_image_src( $attachment_id, 'medium' );
    $logo_url = $logo_src ? $logo_src[0] : '';

    wp_send_json_success(
        array(
            'settings' => $settings,
            'logo_url' => $logo_url,
            'message'  => __( 'لوگو کامیابی سے اپلوڈ اور محفوظ ہو گیا۔', 'jwpm' ),
        )
    );
}
add_action( 'wp_ajax_jwpm_upload_logo', 'jwpm_ajax_upload_logo' );

/**
 * (AJAX) — Logo Remove
 * action: jwpm_remove_logo
 *
 * یہاں صرف Settings سے لوگو ہٹایا جا رہا ہے، میڈیا لائبریری سے تصویر delete نہیں ہوگی۔
 */
function jwpm_ajax_remove_logo() {

    jwpm_settings_verify_request();

    $settings = jwpm_settings_update(
        array(
            'logo_id' => 0,
        )
    );

    wp_send_json_success(
        array(
            'settings' => $settings,
            'logo_url' => '',
            'message'  => __( 'لوگو ہٹا دیا گیا ہے۔', 'jwpm' ),
        )
    );
}
add_action( 'wp_ajax_jwpm_remove_logo', 'jwpm_ajax_remove_logo' );

// 🔴 یہاں پر [Settings Page Server Logic & AJAX] ختم ہو رہا ہے

// ✅ Syntax verified block end
