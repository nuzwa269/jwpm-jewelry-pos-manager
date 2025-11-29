<?php
/**
 * JWPM_Loader
 *
 * یہ کلاس پلگ ان کے تمام ایکشنز (Actions) اور فلٹرز (Filters) کو رجسٹر کرنے کی ذمہ دار ہے۔
 * یہ ورڈپریس اور آپ کے پلگ ان کے درمیان ایک 'آرکیسٹریٹرز' کا کام کرتی ہے۔
 *
 * @package    JWPM
 * @subpackage JWPM/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWPM_Loader {

	/**
	 * ایکشنز کی فہرست (array) جو ورڈپریس کے ساتھ رجسٹر ہوں گی۔
	 *
	 * @var array
	 */
	protected $actions;

	/**
	 * فلٹرز کی فہرست (array) جو ورڈپریس کے ساتھ رجسٹر ہوں گی۔
	 *
	 * @var array
	 */
	protected $filters;

	/**
	 * کلاس کنسٹرکٹر
	 *
	 * ایکشنز اور فلٹرز کی arrays کو خالی (initialize) کرتا ہے۔
	 */
	public function __construct() {
		$this->actions = array();
		$this->filters = array();
	}

	/**
	 * ورڈپریس ایکشن (Action) شامل کریں۔
	 *
	 * @param string $hook          ایکشن کا نام (جیسے 'init', 'admin_menu').
	 * @param object $component     وہ کلاس آبجیکٹ جس میں فنکشن موجود ہے۔
	 * @param string $callback      فنکشن کا نام جو چلانا ہے۔
	 * @param int    $priority      (Optional) ترجیح۔ Default is 10.
	 * @param int    $accepted_args (Optional) آرگومینٹس کی تعداد۔ Default is 1.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * ورڈپریس فلٹر (Filter) شامل کریں۔
	 *
	 * @param string $hook          فلٹر کا نام (جیسے 'the_content').
	 * @param object $component     وہ کلاس آبجیکٹ جس میں فنکشن موجود ہے۔
	 * @param string $callback      فنکشن کا نام جو چلانا ہے۔
	 * @param int    $priority      (Optional) ترجیح۔ Default is 10.
	 * @param int    $accepted_args (Optional) آرگومینٹس کی تعداد۔ Default is 1.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * ایکشنز اور فلٹرز کو array میں رجسٹر کرنے کا مشترکہ فنکشن۔
	 *
	 * @param array  $hooks         موجودہ ہکس کی لسٹ۔
	 * @param string $hook          ہک کا نام۔
	 * @param object $component     کلاس کا انسٹینس۔
	 * @param string $callback      میتھڈ کا نام۔
	 * @param int    $priority      ترجیح۔
	 * @param int    $accepted_args آرگومینٹس۔
	 * @return array اپڈیٹ شدہ ہکس۔
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * تمام رجسٹرڈ فلٹرز اور ایکشنز کو چلائیں (Execute)۔
	 *
	 * یہ فنکشن آخر میں کال کیا جاتا ہے تاکہ تمام جمع شدہ ہکس
	 * کو ورڈپریس کے add_action اور add_filter فنکشنز میں بھیجا جائے۔
	 */
	public function run() {

		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

	}

}
