<?php
/**
 * Settings page (WooCommerce > LabelFill) with its Pro tab.
 *
 * @package LabelFill
 */

namespace LabelFill;

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
class Settings {

	const OPTION = 'labelfill_settings';
	const PAGE   = 'labelfill';
	const PRO    = 'https://checkout.freemius.com/plugin/44975/plan/79295/';

	/**
	 * Defaults: working without the wizard.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'position'           => 'after',
			'lists'              => 1,
			'sheet_link'         => 1,
			'categories'         => array(),
			'delete_data'        => 0,
			'debug'              => 0,
			'installed'          => 0,
			'wizard'             => 0,
			'pro_note_dismissed' => 0,
		);
	}

	/**
	 * Current settings.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? array_intersect_key( $saved, self::defaults() ) : array() );
	}

	/**
	 * Saves some settings.
	 *
	 * @param array $values Values to change.
	 */
	public static function put( $values ) {
		// Only known keys: nothing can switch off the EPREL source line.
		update_option( self::OPTION, array_merge( self::get(), array_intersect_key( (array) $values, self::defaults() ) ) );
	}

	/**
	 * First activation: note the date and open the wizard once.
	 */
	public static function activate() {
		$s = self::get();
		if ( ! $s['installed'] ) {
			self::put( array( 'installed' => time() ) );
			set_transient( 'labelfill_welcome', 1, 60 );
		}
	}

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_labelfill_settings', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_post_labelfill_dismiss', array( __CLASS__, 'dismiss' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( LABELFILL_FILE ), array( __CLASS__, 'links' ) );
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'labelfill' ) . '</a>' );
		return $links;
	}

	/**
	 * Address of a tab.
	 *
	 * @param string $tab Tab.
	 * @return string
	 */
	public static function url( $tab = '' ) {
		$args = array( 'page' => self::PAGE );
		if ( $tab ) {
			$args['tab'] = $tab;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Menu entry.
	 */
	public static function menu() {
		add_submenu_page( 'woocommerce', 'LabelFill', 'LabelFill', 'manage_woocommerce', self::PAGE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Whether the current screen is one of ours.
	 *
	 * @return bool
	 */
	public static function our_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && ( false !== strpos( $screen->id, 'labelfill' ) || ( 'product' === $screen->post_type && in_array( $screen->base, array( 'edit', 'post' ), true ) ) );
	}

	/**
	 * Admin styles and scripts on our screens and the product screens.
	 */
	public static function assets() {
		if ( ! self::our_screen() ) {
			return;
		}
		wp_enqueue_style( 'labelfill-admin', LABELFILL_URL . 'assets/css/admin.css', array(), LABELFILL_VERSION );
		wp_enqueue_style( 'labelfill-front', LABELFILL_URL . 'assets/css/front.css', array(), LABELFILL_VERSION );
		wp_enqueue_media();
		wp_enqueue_script( 'labelfill-admin', LABELFILL_URL . 'assets/js/admin.js', array(), LABELFILL_VERSION, true );
		wp_enqueue_script( 'labelfill-dialog', LABELFILL_URL . 'assets/js/dialog.js', array(), LABELFILL_VERSION, true );
		wp_localize_script(
			'labelfill-admin',
			'labelfillAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'labelfill' ),
				'i18n'  => array(
					'loading'  => __( 'Looking it up in EPREL…', 'labelfill' ),
					'invalid'  => __( 'This is not a valid EPREL number. Copy the number from the label or the link in the QR code.', 'labelfill' ),
					'failed'   => __( 'Something went wrong. Try again in a moment.', 'labelfill' ),
					'pickFile' => __( 'Choose a file', 'labelfill' ),
					'use'      => __( 'Use this file', 'labelfill' ),
				),
			)
		);
	}

	/**
	 * Notices: products changed in EPREL (products list and our screens), and
	 * one dismissible Pro note for large catalogues (our screens only).
	 */
	public static function notices() {
		if ( ! current_user_can( 'edit_products' ) || ! self::our_screen() ) {
			return;
		}
		$changed = Refresh::count_status( 'changed' );
		if ( $changed ) {
			$link = add_query_arg(
				array(
					'post_type'       => 'product',
					'labelfill_state' => 'changed',
				),
				admin_url( 'edit.php' )
			);
			echo '<div class="notice notice-warning labelfill-notice"><p>';
			printf(
				/* translators: %d: number of products */
				esc_html( _n( 'LabelFill: %d product changed in EPREL.', 'LabelFill: %d products changed in EPREL.', $changed, 'labelfill' ) ),
				(int) $changed
			);
			echo ' <a href="' . esc_url( $link ) . '">' . esc_html__( 'Review them.', 'labelfill' ) . '</a></p></div>';
		}
		$s = self::get();
		if ( class_exists( 'LabelFill\\Pro\\Plugin' ) || ! empty( $s['pro_note_dismissed'] ) || ! $s['installed'] || time() - $s['installed'] < 14 * DAY_IN_SECONDS ) {
			return;
		}
		if ( Refresh::count_numbered() <= 100 ) {
			return;
		}
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=labelfill_dismiss' ), 'labelfill_dismiss' );
		echo '<div class="notice notice-info labelfill-notice"><p>' . esc_html__( 'Your catalogue is large: LabelFill Pro fills it in one go and checks all of it every week.', 'labelfill' ) . ' <a href="' . esc_url( self::url( 'pro' ) ) . '">' . esc_html__( 'See what Pro adds', 'labelfill' ) . '</a> · <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'labelfill' ) . '</a></p></div>';
	}

	/**
	 * Hides the Pro note for good.
	 */
	public static function dismiss() {
		check_admin_referer( 'labelfill_dismiss' );
		if ( current_user_can( 'manage_woocommerce' ) ) {
			self::put( array( 'pro_note_dismissed' => 1 ) );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : self::url() );
		exit;
	}

	/**
	 * Saves the settings form.
	 */
	public static function save() {
		check_admin_referer( 'labelfill_settings' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'labelfill' ) );
		}
		$position = isset( $_POST['position'] ) ? sanitize_key( wp_unslash( $_POST['position'] ) ) : 'after';
		$cats     = isset( $_POST['categories'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['categories'] ) ) : array();
		self::put(
			array(
				'position'    => in_array( $position, array( 'after', 'below' ), true ) ? $position : 'after',
				'lists'       => empty( $_POST['lists'] ) ? 0 : 1,
				'sheet_link'  => empty( $_POST['sheet_link'] ) ? 0 : 1,
				'categories'  => array_values( array_filter( $cats ) ),
				'delete_data' => empty( $_POST['delete_data'] ) ? 0 : 1,
				'debug'       => empty( $_POST['debug'] ) ? 0 : 1,
			)
		);
		do_action( 'labelfill_save_settings' );
		wp_safe_redirect( add_query_arg( 'saved', 1, self::url() ) );
		exit;
	}

	/**
	 * Tabs of the page. Pro adds its own.
	 *
	 * @return array<string,string>
	 */
	public static function tabs() {
		$tabs = array( '' => __( 'Settings', 'labelfill' ) );
		$tabs = apply_filters( 'labelfill_tabs', $tabs );
		if ( ! class_exists( 'LabelFill\\Pro\\Plugin' ) ) {
			$tabs['pro'] = 'Pro';
		}
		return $tabs;
	}

	/**
	 * The page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab choice only.
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$tabs = self::tabs();
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = '';
		}
		echo '<div class="wrap labelfill-wrap"><h1 class="labelfill-title">LabelFill</h1><nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $name ) {
			echo '<a class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( $key ) ) . '">' . esc_html( $name ) . '</a>';
		}
		echo '</nav>';
		if ( 'pro' === $tab ) {
			self::render_pro();
		} elseif ( '' === $tab ) {
			self::render_settings();
		} else {
			do_action( 'labelfill_tab_' . $tab );
		}
		echo '<p class="labelfill-help labelfill-disclaimer">' . esc_html__( 'Data from EPREL, the European Commission’s product registry. LabelFill is not affiliated with, endorsed or sponsored by the European Commission or EPREL.', 'labelfill' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Settings tab.
	 */
	private static function render_settings() {
		$s = self::get();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( ! empty( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'labelfill' ) . '</p></div>';
		}
		$n     = Refresh::count_numbered();
		$batch = Refresh::batch_size( $n );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="labelfill-card">';
		wp_nonce_field( 'labelfill_settings' );
		echo '<input type="hidden" name="action" value="labelfill_settings">';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Where does the arrow go?', 'labelfill' ) . '</th><td><fieldset>';
		foreach ( array(
			'after' => __( 'Next to the price', 'labelfill' ),
			'below' => __( 'Below the price', 'labelfill' ),
		) as $key => $name ) {
			echo '<label class="labelfill-radio"><input type="radio" name="position" value="' . esc_attr( $key ) . '"' . checked( $s['position'], $key, false ) . '> ' . esc_html( $name ) . '</label><br>';
		}
		echo '</fieldset></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Shop and categories', 'labelfill' ) . '</th><td><label><input type="checkbox" name="lists" value="1"' . checked( $s['lists'], 1, false ) . '> ' . esc_html__( 'Also show the arrow in the shop and category pages', 'labelfill' ) . '</label><p class="description">' . esc_html__( 'The law asks for the arrow next to every price, in lists too.', 'labelfill' ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Product information sheet', 'labelfill' ) . '</th><td><label><input type="checkbox" name="sheet_link" value="1"' . checked( $s['sheet_link'], 1, false ) . '> ' . esc_html__( 'Show the sheet link under the price on the product page', 'labelfill' ) . '</label></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Categories with an energy label', 'labelfill' ) . '</th><td>';
		self::category_boxes( $s['categories'] );
		echo '<p class="description">' . esc_html__( 'Used to count the products that still need a number.', 'labelfill' ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Source line', 'labelfill' ) . '</th><td><p>' . esc_html__( 'Source: EPREL, European Commission', 'labelfill' ) . '</p><p class="description">' . esc_html__( 'Always shown under the label, as the EPREL terms ask.', 'labelfill' ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Keeping data current', 'labelfill' ) . '</th><td><p>';
		printf(
			/* translators: 1: products with a number, 2: products checked a day */
			esc_html__( '%1$d products with a number. LabelFill checks %2$d a day against EPREL, so each one is checked at least every 30 days.', 'labelfill' ),
			(int) $n,
			(int) $batch
		);
		echo '</p>';
		if ( $n > 6000 ) {
			echo '<p class="description">' . esc_html__( 'Your catalogue is large: LabelFill Pro checks all of it every week.', 'labelfill' ) . '</p>';
		}
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'On uninstall', 'labelfill' ) . '</th><td><label><input type="checkbox" name="delete_data" value="1"' . checked( $s['delete_data'], 1, false ) . '> ' . esc_html__( 'Delete all LabelFill data when the plugin is deleted', 'labelfill' ) . '</label></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Troubleshooting', 'labelfill' ) . '</th><td><label><input type="checkbox" name="debug" value="1"' . checked( $s['debug'], 1, false ) . '> ' . esc_html__( 'Log unexpected EPREL answers (WooCommerce > Status > Logs)', 'labelfill' ) . '</label></td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Save changes', 'labelfill' ), 'primary labelfill-btn' );
		echo ' <a class="button" href="' . esc_url( Wizard::url() ) . '">' . esc_html__( 'Run the setup wizard again', 'labelfill' ) . '</a>';
		echo '</form>';
	}

	/**
	 * Category check boxes.
	 *
	 * @param int[] $chosen Chosen term IDs.
	 */
	public static function category_boxes( $chosen ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 200,
			)
		);
		if ( is_wp_error( $terms ) || ! $terms ) {
			echo '<p>' . esc_html__( 'No product categories yet.', 'labelfill' ) . '</p>';
			return;
		}
		echo '<div class="labelfill-cats">';
		foreach ( $terms as $term ) {
			echo '<label class="labelfill-chip"><input type="checkbox" name="categories[]" value="' . (int) $term->term_id . '"' . checked( in_array( (int) $term->term_id, array_map( 'intval', (array) $chosen ), true ), true, false ) . '> ' . esc_html( $term->name ) . '</label>';
		}
		echo '</div>';
	}

	/**
	 * Pro tab: what Pro adds, in plain words.
	 */
	private static function render_pro() {
		$lines = array(
			__( 'Fills thousands of products at once, from a field, a link or your supplier\'s CSV.', 'labelfill' ),
			__( 'Checks your whole catalogue every week and emails you only when something changed.', 'labelfill' ),
			__( 'Tells you which products are missing a label, with the share of your catalogue covered.', 'labelfill' ),
			__( 'Keeps labels and sheets on your own site: faster, and no shopper data sent to third parties.', 'labelfill' ),
			__( 'Sheets in each shopper\'s language with WPML, Polylang or TranslatePress.', 'labelfill' ),
			__( 'Arrow and window styles, and fields ready for Google Shopping.', 'labelfill' ),
		);
		echo '<div class="labelfill-card labelfill-pro"><h2>' . esc_html__( 'Your whole catalogue, filled by itself', 'labelfill' ) . '</h2><ul class="labelfill-ticks">';
		foreach ( $lines as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul><p class="labelfill-price">' . esc_html__( 'USD 39 a year for one shop · USD 99 a year for up to 5 shops', 'labelfill' ) . '</p>';
		echo '<p><a class="button button-primary labelfill-btn" href="' . esc_url( self::PRO ) . '" target="_blank" rel="noopener">' . esc_html__( 'Get LabelFill Pro', 'labelfill' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'Secure payment. 14 days to get your money back.', 'labelfill' ) . '</p></div>';
	}
}
