<?php
/**
 * The setup wizard: categories, arrow position, try one number.
 *
 * @package LabelFill
 */

namespace LabelFill;

defined( 'ABSPATH' ) || exit;

/**
 * Setup wizard.
 */
class Wizard {

	const PAGE = 'labelfill-wizard';

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 61 );
		add_action( 'admin_head', array( __CLASS__, 'hide' ) );
		add_action( 'admin_init', array( __CLASS__, 'welcome' ) );
		add_action( 'admin_post_labelfill_wizard', array( __CLASS__, 'save' ) );
		add_action( 'wp_ajax_labelfill_try', array( __CLASS__, 'ajax_try' ) );
	}

	/**
	 * Address of a step.
	 *
	 * @param int|string $step 1, 2, 3 or 'done'.
	 * @return string
	 */
	public static function url( $step = 1 ) {
		return add_query_arg(
			array(
				'page' => self::PAGE,
				'step' => $step,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * The page, reachable by address only.
	 */
	public static function menu() {
		add_submenu_page( 'woocommerce', __( 'LabelFill setup', 'labelfill' ), __( 'LabelFill setup', 'labelfill' ), 'manage_woocommerce', self::PAGE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Keeps the wizard out of the menu.
	 */
	public static function hide() {
		remove_submenu_page( 'woocommerce', self::PAGE );
	}

	/**
	 * Opens the wizard once, right after the first activation.
	 */
	public static function welcome() {
		if ( ! get_transient( 'labelfill_welcome' ) || wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		delete_transient( 'labelfill_welcome' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checks whether several plugins were activated at once.
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Saves a step and moves on.
	 */
	public static function save() {
		check_admin_referer( 'labelfill_wizard' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'labelfill' ) );
		}
		$step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1;
		if ( 1 === $step ) {
			$cats = isset( $_POST['categories'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['categories'] ) ) : array();
			Settings::put( array( 'categories' => array_values( array_filter( $cats ) ) ) );
		} elseif ( 2 === $step ) {
			$position = isset( $_POST['position'] ) ? sanitize_key( wp_unslash( $_POST['position'] ) ) : 'after';
			Settings::put(
				array(
					'position' => in_array( $position, array( 'after', 'below' ), true ) ? $position : 'after',
					'lists'    => empty( $_POST['lists'] ) ? 0 : 1,
				)
			);
		} else {
			Settings::put( array( 'wizard' => 1 ) );
		}
		wp_safe_redirect( self::url( $step >= 3 ? 'done' : $step + 1 ) );
		exit;
	}

	/**
	 * Step 3: looks a number up without saving anything.
	 */
	public static function ajax_try() {
		check_ajax_referer( 'labelfill', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'labelfill' ) ), 403 );
		}
		$number = isset( $_POST['number'] ) ? Api::parse_number( sanitize_text_field( wp_unslash( $_POST['number'] ) ) ) : '';
		if ( '' === $number ) {
			wp_send_json_error( array( 'message' => __( 'This is not a valid EPREL number. Copy the number from the label or the link in the QR code.', 'labelfill' ) ) );
		}
		$r = Api::fetch( $number, array( Api::lang() ) );
		if ( empty( $r['class'] ) ) {
			$msg = ProductFields::message(
				array(
					'status'  => $r['status'],
					'checked' => time(),
				)
			);
			wp_send_json_error( array( 'message' => $msg ? $msg[1] : __( 'Something went wrong. Try again in a moment.', 'labelfill' ) ) );
		}
		$show = array(
			'class' => $r['class'],
			'scale' => $r['scale'],
			'label' => $r['label'],
			'sheet' => Store::sheet_for( $r['sheets'], Api::lang() ),
			'group' => $r['group'],
			'src'   => 'eprel',
		);
		$html = '<div class="labelfill-shopc"><span class="labelfill-shopc__im"></span><div><b>' . esc_html( trim( $r['supplier'] . ' ' . $r['model'] ) ) . '</b>'
			. '<div class="labelfill-shopc__pr"><span>' . wp_kses_post( wc_price( 99 ) ) . '</span>' . Display::button( $show ) . '</div>'
			. ( $show['sheet'] ? '<a href="' . esc_url( $show['sheet'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Product information sheet', 'labelfill' ) . '</a>' : '' )
			. '</div></div><p class="description">' . esc_html__( 'Tap the arrow to open the label, as your customer will.', 'labelfill' ) . '</p>';
		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * The wizard page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Step choice only.
		$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '1';
		$step = in_array( $step, array( '1', '2', '3', 'done' ), true ) ? $step : '1';
		$s    = Settings::get();
		$num  = 'done' === $step ? 4 : (int) $step;
		echo '<div class="wrap labelfill-wrap labelfill-wizard"><h1 class="labelfill-title">' . esc_html__( 'Get your energy labels in order in two minutes', 'labelfill' ) . '</h1>';
		echo '<ol class="labelfill-steps">';
		foreach ( array( __( 'Categories', 'labelfill' ), __( 'Arrow', 'labelfill' ), __( 'Try it', 'labelfill' ) ) as $i => $name ) {
			$cls = $i + 1 < $num ? 'is-done' : ( $i + 1 === $num ? 'is-on' : '' );
			echo '<li class="' . esc_attr( $cls ) . '"><span>' . (int) ( $i + 1 ) . '</span>' . esc_html( $name ) . '</li>';
		}
		echo '</ol><div class="labelfill-card">';

		if ( 'done' === $step ) {
			echo '<h2>' . esc_html__( 'Done. Open any product and paste its EPREL number.', 'labelfill' ) . '</h2>';
			echo '<p><a class="button button-primary labelfill-btn" href="' . esc_url( admin_url( 'edit.php?post_type=product' ) ) . '">' . esc_html__( 'Go to products', 'labelfill' ) . '</a> <a class="button" href="' . esc_url( Settings::url() ) . '">' . esc_html__( 'Settings', 'labelfill' ) . '</a></p></div></div>';
			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'labelfill_wizard' );
		echo '<input type="hidden" name="action" value="labelfill_wizard"><input type="hidden" name="step" value="' . esc_attr( $step ) . '">';
		if ( '1' === $step ) {
			echo '<h2>' . esc_html__( 'Which categories carry an energy label?', 'labelfill' ) . '</h2><p class="description">' . esc_html__( 'For example: lighting, TVs, appliances, tyres, phones and tablets.', 'labelfill' ) . '</p>';
			Settings::category_boxes( $s['categories'] );
		} elseif ( '2' === $step ) {
			echo '<h2>' . esc_html__( 'Where does the arrow go?', 'labelfill' ) . '</h2><fieldset class="labelfill-choices">';
			foreach ( array(
				'after' => __( 'Next to the price', 'labelfill' ),
				'below' => __( 'Below the price', 'labelfill' ),
			) as $key => $name ) {
				echo '<label class="labelfill-choice"><input type="radio" name="position" value="' . esc_attr( $key ) . '"' . checked( $s['position'], $key, false ) . '><span class="labelfill-choice__demo is-' . esc_attr( $key ) . '"><span>' . wp_kses_post( wc_price( 89 ) ) . '</span>' . wp_kses( Arrow::svg( 'C', 'A_G' ), Arrow::allowed() ) . '</span>' . esc_html( $name ) . '</label>';
			}
			echo '</fieldset><p><label><input type="checkbox" name="lists" value="1"' . checked( $s['lists'], 1, false ) . '> ' . esc_html__( 'Also show it in the shop and category pages', 'labelfill' ) . '</label></p><p class="description">' . esc_html__( 'The law asks for the arrow next to every price, in lists too.', 'labelfill' ) . '</p>';
		} else {
			echo '<h2>' . esc_html__( 'Try it with a real product', 'labelfill' ) . '</h2>';
			echo '<div class="labelfill-row"><label class="screen-reader-text" for="labelfill-try">' . esc_html__( 'EPREL registration number', 'labelfill' ) . '</label><input type="text" class="labelfill-in" id="labelfill-try" placeholder="https://eprel.ec.europa.eu/qr/…" autocomplete="off"><button type="button" class="labelfill-go labelfill-trygo">' . esc_html__( 'See how it looks', 'labelfill' ) . '</button></div>';
			echo '<p class="labelfill-help">' . esc_html__( 'You\'ll find it on the label, next to the QR code, or in the QR code\'s link (eprel.ec.europa.eu/qr/…).', 'labelfill' ) . '</p><div class="labelfill-tryout" aria-live="polite"></div>';
		}
		echo '<p class="labelfill-nav">';
		submit_button( '3' === $step ? __( 'Finish', 'labelfill' ) : __( 'Continue', 'labelfill' ), 'primary labelfill-btn', 'submit', false );
		echo ' <a class="labelfill-skip" href="' . esc_url( Settings::url() ) . '">' . esc_html__( 'Skip the wizard', 'labelfill' ) . '</a></p></form></div></div>';
	}
}
