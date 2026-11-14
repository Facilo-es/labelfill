<?php
/**
 * Plugin Name:          LabelFill – EU Energy Label (EPREL) for WooCommerce
 * Plugin URI:           https://github.com/Facilo-es/labelfill
 * Description:          Paste the EPREL number and LabelFill shows the legal energy class arrow next to the price, the official label and the product information sheet, fetched from the EU registry and kept up to date.
 * Version:              1.0.0
 * Requires at least:    6.4
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Facilo
 * Author URI:           https://facilo.es
 * License:              GPLv2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          labelfill
 * WC requires at least: 8.0
 * WC tested up to:      10.3
 *
 * @package LabelFill
 */

defined( 'ABSPATH' ) || exit;

define( 'LABELFILL_VERSION', '1.0.0' );
define( 'LABELFILL_FILE', __FILE__ );
define( 'LABELFILL_DIR', plugin_dir_path( __FILE__ ) );
define( 'LABELFILL_URL', plugin_dir_url( __FILE__ ) );

foreach ( array( 'settings', 'api', 'store', 'arrow', 'display', 'product-fields', 'admin-list', 'refresh', 'wizard' ) as $labelfill_file ) {
	require_once LABELFILL_DIR . 'includes/class-' . $labelfill_file . '.php';
}
unset( $labelfill_file );

register_activation_hook( __FILE__, array( 'LabelFill\\Settings', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LabelFill\\Refresh', 'unschedule' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		LabelFill\Display::boot();
		LabelFill\Refresh::boot();
		if ( is_admin() ) {
			LabelFill\Settings::boot();
			LabelFill\ProductFields::boot();
			LabelFill\AdminList::boot();
			LabelFill\Wizard::boot();
		}
	},
	5
);
