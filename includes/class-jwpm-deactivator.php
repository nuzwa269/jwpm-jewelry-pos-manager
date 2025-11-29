<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    JWPM
 * @subpackage JWPM/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWPM_Deactivator {

	/**
	 * پلگ ان غیر فعال ہونے پر یہ میتھڈ چلے گا
	 *
	 * - Rewrite rules کو فلش کرتا ہے (Permalinks کی درستگی کے لیے)
	 */
	public static function deactivate() {
		// Rewrite rules کو فلش کریں تاکہ 404 ایررز نہ آئیں
		flush_rewrite_rules();

		// اگر آپ مستقبل میں کوئی کیش یا عارضی ڈیٹا صاف کرنا چاہیں تو یہاں کوڈ لکھیں
		// delete_transient('jwpm_some_cache');
	}
}
