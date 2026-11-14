<?php
/**
 * Shop side: the arrow next to every price, the sheet link and the label window.
 * Page views never call EPREL; the shopper's browser loads the label only when opened.
 *
 * @package LabelFill
 */

namespace LabelFill;

defined( 'ABSPATH' ) || exit;

/**
 * Display.
 */
class Display {

	/**
	 * Whether an arrow is on this page.
	 *
	 * @var bool
	 */
	private static $used = false;

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 50, 2 );
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'variation_data' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
		add_action( 'wp_footer', array( __CLASS__, 'dialog' ) );
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'prime' ) );
	}

	/**
	 * Registers the front files; they load only on pages with an arrow.
	 */
	public static function register() {
		wp_register_style( 'labelfill-front', LABELFILL_URL . 'assets/css/front.css', array(), LABELFILL_VERSION );
		wp_register_script( 'labelfill-dialog', LABELFILL_URL . 'assets/js/dialog.js', array(), LABELFILL_VERSION, true );
		wp_register_script( 'labelfill-arrow', LABELFILL_URL . 'assets/js/arrow.js', array(), LABELFILL_VERSION, true );
	}

	/**
	 * Loads label meta for a whole shop page in one query.
	 */
	public static function prime() {
		global $wp_query;
		if ( ! empty( $wp_query->posts ) ) {
			update_meta_cache( 'post', wp_list_pluck( $wp_query->posts, 'ID' ) );
		}
	}

	/**
	 * Whether a product is the one this product page is about.
	 *
	 * @param \WC_Product $product Product.
	 * @return bool
	 */
	public static function is_main( $product ) {
		return function_exists( 'is_product' ) && is_product() && (int) get_queried_object_id() === (int) $product->get_id();
	}

	/**
	 * Data to show for a product. A variable product without its own uses its first variation with data.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	public static function data_for( $product ) {
		$show = Store::shown( $product->get_id() );
		if ( ! $show && $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child ) {
				$show = Store::shown( $child );
				if ( $show ) {
					break;
				}
			}
		}
		return $show;
	}

	/**
	 * The arrow button.
	 *
	 * @param array  $show  Data from Store::shown().
	 * @param string $extra Extra class.
	 * @return string
	 */
	public static function button( $show, $extra = '' ) {
		if ( '' === Arrow::svg( $show['class'], $show['scale'] ) ) {
			return '';
		}
		/**
		 * The arrow drawing. Any replacement must keep the legal colour, the white letter and the range.
		 *
		 * @param string $svg   SVG.
		 * @param array  $show  Data from Store::shown().
		 * @param string $extra Extra class.
		 */
		$svg = (string) apply_filters( 'labelfill_arrow_svg', Arrow::svg( $show['class'], $show['scale'] ), $show, $extra );
		/**
		 * Extra classes on the arrow button.
		 *
		 * @param string $extra Classes.
		 * @param array  $show  Data from Store::shown().
		 */
		$extra = (string) apply_filters( 'labelfill_arrow_class', $extra, $show );
		self::enqueue();
		$tyre = '';
		if ( 'tyres' === $show['group'] ) {
			/* translators: %s: fuel efficiency class */
			$tyre = sprintf( __( 'Fuel efficiency class %s', 'labelfill' ), $show['class'] );
		}
		return '<button type="button" class="labelfill-arrow ' . esc_attr( $extra ) . '" aria-haspopup="dialog" aria-label="' . esc_attr( Arrow::label( $show['class'], $show['scale'] ) ) . '"'
			. ' data-lf-label="' . esc_url( $show['label'] ) . '" data-lf-sheet="' . esc_url( $show['sheet'] ) . '" data-lf-extra="' . esc_attr( $tyre ) . '" data-lf-src="' . esc_attr( isset( $show['src'] ) ? $show['src'] : 'eprel' ) . '">'
			. $svg . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput -- Built from escaped parts.
	}

	/**
	 * Adds the arrow after the price, in classic and block themes alike.
	 *
	 * @param string      $html    Price HTML.
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public static function price_html( $html, $product ) {
		// Storefront's sticky bar filters the price through wp_kses_post, which would leave only a stray sheet link.
		if ( ( is_admin() && ! wp_doing_ajax() ) || doing_action( 'storefront_after_footer' ) || '' === $html || false !== strpos( $html, 'labelfill-arrow' ) || ! $product instanceof \WC_Product || $product->is_type( 'variation' ) ) {
			return $html;
		}
		$s    = Settings::get();
		$main = self::is_main( $product );
		if ( ! $main && empty( $s['lists'] ) ) {
			return $html;
		}
		$show = self::data_for( $product );
		if ( ! $show ) {
			return $html;
		}
		$button = self::button( $show, $main ? 'labelfill-main' : '' );
		if ( '' === $button ) {
			return $html;
		}
		$out = $html . ( 'below' === $s['position'] ? '<span class="labelfill-row">' . $button . '</span>' : ' ' . $button );
		if ( $main && ! empty( $s['sheet_link'] ) && '' !== $show['sheet'] ) {
			$out .= '<span class="labelfill-sheetline"><a class="labelfill-sheetlink" href="' . esc_url( $show['sheet'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Product information sheet', 'labelfill' ) . '</a></span>';
		}
		return $out;
	}

	/**
	 * Each variation carries its own arrow; the page swaps it in when chosen.
	 *
	 * @param array                 $data      Variation data.
	 * @param \WC_Product           $parent_product Parent product.
	 * @param \WC_Product_Variation $variation Variation.
	 * @return array
	 */
	public static function variation_data( $data, $parent_product, $variation ) {
		$show = Store::shown( $variation->get_id() );
		if ( $show ) {
			$data['labelfill'] = array(
				'button' => self::button( $show, 'labelfill-main' ),
				'sheet'  => $show['sheet'],
			);
		}
		return $data;
	}

	/**
	 * Loads the front files.
	 */
	private static function enqueue() {
		self::$used = true;
		if ( ! wp_style_is( 'labelfill-front', 'registered' ) ) {
			self::register();
		}
		wp_enqueue_style( 'labelfill-front' );
		wp_enqueue_script( 'labelfill-dialog' );
		wp_enqueue_script( 'labelfill-arrow' );
	}

	/**
	 * The label window, once per page with an arrow.
	 */
	public static function dialog() {
		if ( ! self::$used ) {
			return;
		}
		include LABELFILL_DIR . 'templates/dialog.php';
	}
}
