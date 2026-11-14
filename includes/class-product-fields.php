<?php
/**
 * The "Energy label" box on the product edit screen, the variation field and the fill requests.
 *
 * @package LabelFill
 */

namespace LabelFill;

defined( 'ABSPATH' ) || exit;

/**
 * Product edit screen.
 */
class ProductFields {

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'add_box' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ), 20 );
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'variation_field' ), 20, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation' ), 20, 2 );
		add_action( 'wp_ajax_labelfill_fill', array( __CLASS__, 'ajax_fill' ) );
		add_action( 'wp_ajax_labelfill_reviewed', array( __CLASS__, 'ajax_reviewed' ) );
		add_action( 'admin_notices', array( __CLASS__, 'invalid_notice' ) );
		add_action( 'admin_footer', array( __CLASS__, 'dialog' ) );
	}

	/**
	 * The label pop-up, so the preview arrow opens it as the shopper sees it.
	 */
	public static function dialog() {
		if ( Settings::our_screen() ) {
			include LABELFILL_DIR . 'templates/dialog.php';
		}
	}

	/**
	 * Adds the box.
	 */
	public static function add_box() {
		add_meta_box( 'labelfill', __( 'Energy label', 'labelfill' ), array( __CLASS__, 'render' ), 'product', 'normal', 'high' );
	}

	/**
	 * Names of the EPREL product groups people meet most.
	 *
	 * @param string $group EPREL group.
	 * @return string
	 */
	public static function group_name( $group ) {
		$names = array(
			'lightsources'                               => __( 'Light sources', 'labelfill' ),
			'electronicdisplays'                         => __( 'Electronic displays', 'labelfill' ),
			'washingmachines2019'                        => __( 'Washing machines', 'labelfill' ),
			'washerdriers2019'                           => __( 'Washer-dryers', 'labelfill' ),
			'dishwashers2019'                            => __( 'Dishwashers', 'labelfill' ),
			'refrigeratingappliances2019'                => __( 'Fridges and freezers', 'labelfill' ),
			'refrigeratingappliancesdirectsalesfunction' => __( 'Fridges with a direct sales function', 'labelfill' ),
			'tyres'                                      => __( 'Tyres', 'labelfill' ),
			'tumbledriers'                               => __( 'Tumble dryers', 'labelfill' ),
			'ovens'                                      => __( 'Ovens', 'labelfill' ),
			'rangehoods'                                 => __( 'Range hoods', 'labelfill' ),
			'airconditioners'                            => __( 'Air conditioners', 'labelfill' ),
			'smartphonestablets20231669'                 => __( 'Smartphones and tablets', 'labelfill' ),
			'winestoragecabinets'                        => __( 'Wine storage cabinets', 'labelfill' ),
		);
		return isset( $names[ $group ] ) ? $names[ $group ] : $group;
	}

	/**
	 * Short status names, used in the box and in the product list.
	 *
	 * @param array $d Raw data.
	 * @return array Tone ('ok', 'bad', 'warn', 'off') and text.
	 */
	public static function pill( $d ) {
		if ( '' === $d['reg'] && 'manual' !== $d['status'] ) {
			return array( 'off', __( 'No number', 'labelfill' ) );
		}
		$map = array(
			'ok'         => array( 'ok', __( 'Up to date', 'labelfill' ) ),
			'not_found'  => array( 'bad', __( 'Not found', 'labelfill' ) ),
			'unverified' => array( 'warn', __( 'Supplier not verified', 'labelfill' ) ),
			'blocked'    => array( 'bad', __( 'Withdrawn', 'labelfill' ) ),
			'error'      => array( 'warn', __( 'Error', 'labelfill' ) ),
			'manual'     => array( 'ok', __( 'Manual', 'labelfill' ) ),
			'changed'    => array( 'warn', __( 'Changed', 'labelfill' ) ),
		);
		return isset( $map[ $d['status'] ] ) ? $map[ $d['status'] ] : array( 'off', __( 'Not checked yet', 'labelfill' ) );
	}

	/**
	 * The status message under the field.
	 *
	 * @param array $d Raw data.
	 * @return array Tone and text, or empty.
	 */
	public static function message( $d ) {
		$date = $d['checked'] ? wp_date( get_option( 'date_format' ), $d['checked'] ) : '';
		switch ( $d['status'] ) {
			case 'ok':
				/* translators: %s: date of the last check */
				return array( 'ok', sprintf( __( 'EPREL data up to date. Last checked: %s.', 'labelfill' ), $date ) );
			case 'not_found':
				return array( 'bad', __( 'EPREL has no product with that number. Check the number or ask your supplier for it.', 'labelfill' ) );
			case 'unverified':
				return array( 'warn', __( 'The supplier of this model hasn\'t verified their identity in EPREL yet, so the record doesn\'t show its data. Fill it in by hand or ask your supplier to complete the verification.', 'labelfill' ) );
			case 'blocked':
				return array( 'bad', __( 'EPREL has blocked this model. Check with your supplier before you keep selling it with this label.', 'labelfill' ) );
			case 'error':
				return array( 'warn', __( 'EPREL isn\'t answering right now. We\'ve saved your number and will try again later. Your previous data is still shown.', 'labelfill' ) );
		}
		return array();
	}

	/**
	 * One line per change found by the refresh.
	 *
	 * @param array $d Raw data.
	 * @return string[]
	 */
	public static function change_lines( $d ) {
		$lines = array();
		if ( 'changed' !== $d['status'] || empty( $d['changes']['list'] ) ) {
			return $lines;
		}
		$names = array(
			'class' => __( 'the class', 'labelfill' ),
			'scale' => __( 'the scale', 'labelfill' ),
			'group' => __( 'the product group', 'labelfill' ),
			'label' => __( 'the label', 'labelfill' ),
		);
		$date  = wp_date( get_option( 'date_format' ), (int) $d['changes']['date'] );
		foreach ( $d['changes']['list'] as $change ) {
			$old = 'label' === $change['field'] ? __( 'the previous file', 'labelfill' ) : $change['old'];
			$new = 'label' === $change['field'] ? __( 'a new file', 'labelfill' ) : $change['new'];
			/* translators: 1: date, 2: what changed, 3: old value, 4: new value */
			$lines[] = sprintf( __( 'EPREL changed this product on %1$s: %2$s went from %3$s to %4$s.', 'labelfill' ), $date, isset( $names[ $change['field'] ] ) ? $names[ $change['field'] ] : $change['field'], $old, $new );
		}
		return $lines;
	}

	/**
	 * The part of the box that changes after a fill: pill, message, data and the shopper preview.
	 *
	 * @param int   $id    Product ID.
	 * @param array $d     Raw data.
	 * @param array $eprel EPREL answer kept aside for a manual product, if any.
	 * @return array 'pill', 'left' (messages and data) and 'right' (shopper preview) HTML.
	 */
	public static function state( $id, $d, $eprel = array() ) {
		$product = wc_get_product( $id );
		$pill    = self::pill( $d );
		$msg     = self::message( $d );
		$shown   = Store::shown( $id );
		$out     = array( 'pill' => '<span class="labelfill-pill is-' . esc_attr( $pill[0] ) . '">' . esc_html( $pill[1] ) . '</span>' );

		$html = '<div class="labelfill-msgs" aria-live="polite">';
		if ( ! empty( $shown['class'] ) && 'eprel' === $shown['src'] && in_array( $d['status'], array( 'ok', 'changed' ), true ) ) {
			$html .= '<div class="labelfill-msg is-ok"><b>' . esc_html__( 'Check that the model matches your product.', 'labelfill' ) . '</b> ' . esc_html( 'ok' === $d['status'] && $msg ? $msg[1] : '' ) . '</div>';
		} elseif ( $msg ) {
			$html .= '<div class="labelfill-msg is-' . esc_attr( $msg[0] ) . '">' . esc_html( $msg[1] ) . '</div>';
		}
		$lines = self::change_lines( $d );
		if ( $lines ) {
			$html .= '<div class="labelfill-msg is-warn"><ul class="labelfill-changes">';
			foreach ( $lines as $line ) {
				$html .= '<li>' . esc_html( $line ) . '</li>';
			}
			$html .= '</ul><button type="button" class="button labelfill-reviewed" data-lf-id="' . esc_attr( $id ) . '">' . esc_html__( 'Mark as reviewed', 'labelfill' ) . '</button></div>';
		}
		if ( $eprel && isset( $eprel['class'] ) ) {
			/* translators: 1: energy class found in EPREL, 2: supplier, 3: model */
			$found = sprintf( __( 'EPREL has this number as class %1$s (%2$s %3$s). This product was filled in by hand. Replace your data with EPREL\'s?', 'labelfill' ), $eprel['class'], $eprel['supplier'], $eprel['model'] );
			$html .= '<div class="labelfill-msg is-warn">' . esc_html( $found ) . ' <button type="button" class="button labelfill-replace">' . esc_html__( 'Replace with EPREL data', 'labelfill' ) . '</button></div>';
		}
		$html .= '</div>';

		if ( ! empty( $shown['class'] ) ) {
			$sheet_lang = '';
			if ( 'eprel' === $shown['src'] && $d['sheets'] ) {
				$lang       = Api::lang();
				$sheet_lang = isset( $d['sheets'][ $lang ] ) ? $lang : ( isset( $d['sheets']['EN'] ) ? 'EN' : key( $d['sheets'] ) );
			}
			$scale = Arrow::classes( $shown['scale'] );
			$rows  = array(
				__( 'Class', 'labelfill' )    => Arrow::svg( $shown['class'], $shown['scale'] ),
				/* translators: 1: best class, 2: worst class */
				__( 'Scale', 'labelfill' )    => esc_html( $scale ? sprintf( __( '%1$s to %2$s', 'labelfill' ), $scale[0], end( $scale ) ) : '' ),
				__( 'Group', 'labelfill' )    => esc_html( 'eprel' === $shown['src'] ? self::group_name( $d['group'] ) : __( 'Entered by hand', 'labelfill' ) ),
				__( 'Supplier', 'labelfill' ) => esc_html( 'eprel' === $shown['src'] ? $d['supplier'] : '' ),
				__( 'Model', 'labelfill' )    => esc_html( 'eprel' === $shown['src'] ? $d['model'] : '' ),
				__( 'Sheet', 'labelfill' )    => $shown['sheet'] ? esc_html( $sheet_lang ? $sheet_lang : __( 'Yes', 'labelfill' ) ) . ' &#10003;' : esc_html__( 'None', 'labelfill' ),
			);
			$html .= '<dl class="labelfill-data">';
			foreach ( $rows as $dt => $dd ) {
				if ( '' !== $dd ) {
					$html .= '<div><dt>' . esc_html( $dt ) . '</dt><dd>' . $dd . '</dd></div>';
				}
			}
			$html .= '</dl>';
		}

		$out['left'] = $html;

		// What the shopper sees.
		$html = '<div class="labelfill-preview"><div class="labelfill-preview__k"><span>' . esc_html__( 'What your customer sees', 'labelfill' ) . '</span><span>' . esc_html__( 'Product page and shop', 'labelfill' ) . '</span></div>';
		if ( ! empty( $shown['class'] ) ) {
			$price = $product && '' !== $product->get_price() ? wc_price( wc_get_price_to_display( $product ) ) : '';
			$html .= '<div class="labelfill-shopc">' . ( $product && $product->get_image_id() ? wp_get_attachment_image( $product->get_image_id(), 'thumbnail', false, array( 'class' => 'labelfill-shopc__im' ) ) : '<span class="labelfill-shopc__im"></span>' );
			$html .= '<div><b>' . esc_html( $product ? $product->get_name() : '' ) . '</b><div class="labelfill-shopc__pr"><span>' . wp_kses_post( $price ) . '</span>' . Display::button( $shown ) . '</div>';
			$html .= $shown['sheet'] ? '<a href="' . esc_url( $shown['sheet'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Product information sheet', 'labelfill' ) . '</a>' : '';
			$html .= '</div></div>';
			if ( $shown['label'] ) {
				$html .= '<div class="labelfill-labw"><img src="' . esc_url( $shown['label'] ) . '" alt="' . esc_attr__( 'Energy label', 'labelfill' ) . '" loading="lazy"><p><b>' . esc_html__( 'Official label', 'labelfill' ) . '</b>' . esc_html( 'eprel' === $shown['src'] ? __( 'Opens when the customer taps the arrow. It comes from EPREL, so it\'s always the current one.', 'labelfill' ) : __( 'Opens when the customer taps the arrow.', 'labelfill' ) ) . '</p></div>';
			}
		} else {
			$html .= '<div class="labelfill-ghost">' . esc_html__( 'Paste the number and you\'ll see the arrow next to the price here.', 'labelfill' ) . '</div>';
		}
		$html        .= '</div>';
		$out['right'] = $html;
		return $out;
	}

	/**
	 * Prints the box.
	 *
	 * @param \WP_Post $post Product post.
	 */
	public static function render( $post ) {
		$d      = Store::raw( $post->ID );
		$manual = 'manual' === $d['status'];
		$m      = wp_parse_args(
			$d['manual'],
			array(
				'class' => '',
				'scale' => 'A_G',
				'label' => 0,
				'sheet' => 0,
			)
		);
		$state  = self::state( $post->ID, $d );
		$scales = array();
		foreach ( Arrow::scales() as $key => $name ) {
			$scales[ $key ] = Arrow::classes( $key );
		}
		wp_nonce_field( 'labelfill_box', 'labelfill_box_nonce' );
		?>
		<div class="labelfill-box<?php echo $manual ? ' is-manual' : ''; ?>" data-lf-id="<?php echo esc_attr( $post->ID ); ?>">
			<div class="labelfill-l">
				<div class="labelfill-head">
					<label class="labelfill-t" for="labelfill-reg"><?php esc_html_e( 'EPREL registration number', 'labelfill' ); ?></label>
					<span class="labelfill-pillw"><?php echo wp_kses( $state['pill'], Arrow::allowed() ); ?></span>
				</div>
				<div class="labelfill-row">
					<input type="text" class="labelfill-in" id="labelfill-reg" name="labelfill_reg" value="<?php echo esc_attr( $d['reg'] ); ?>" autocomplete="off" placeholder="https://eprel.ec.europa.eu/qr/…">
					<button type="button" class="labelfill-go"><?php esc_html_e( 'Fill from EPREL', 'labelfill' ); ?></button>
				</div>
				<p class="labelfill-help"><?php esc_html_e( 'You\'ll find it on the label, next to the QR code, or in the QR code\'s link (eprel.ec.europa.eu/qr/…).', 'labelfill' ); ?></p>
				<div class="labelfill-state">
					<?php echo wp_kses( $state['left'], Arrow::allowed() ); ?>
				</div>
				<label class="labelfill-manual-toggle">
					<input type="checkbox" name="labelfill_manual" value="1" role="switch" <?php checked( $manual ); ?>>
					<span class="labelfill-sw" aria-hidden="true"></span>
					<?php esc_html_e( 'Fill in by hand (products that aren\'t in EPREL)', 'labelfill' ); ?>
				</label>
				<div class="labelfill-manual" data-lf-scales="<?php echo esc_attr( wp_json_encode( $scales ) ); ?>"<?php echo $manual ? '' : ' hidden'; ?>>
					<p>
						<label for="labelfill-m-scale"><?php esc_html_e( 'Scale', 'labelfill' ); ?></label>
						<select id="labelfill-m-scale" name="labelfill_m_scale">
							<?php foreach ( Arrow::scales() as $key => $name ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $m['scale'], $key ); ?>><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="labelfill-m-class"><?php esc_html_e( 'Class', 'labelfill' ); ?></label>
						<select id="labelfill-m-class" name="labelfill_m_class" data-lf-class="<?php echo esc_attr( $m['class'] ); ?>">
							<option value=""><?php esc_html_e( 'Choose…', 'labelfill' ); ?></option>
							<?php foreach ( Arrow::classes( $m['scale'] ) as $class ) : ?>
								<option value="<?php echo esc_attr( $class ); ?>" <?php selected( $m['class'], $class ); ?>><?php echo esc_html( $class ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<?php
					self::file_field( 'label', __( 'Label image (optional)', 'labelfill' ), $m['label'] );
					self::file_field( 'sheet', __( 'Product information sheet, PDF (optional)', 'labelfill' ), $m['sheet'] );
					?>
				</div>
			</div>
			<div class="labelfill-r">
				<?php echo wp_kses( $state['right'], Arrow::allowed() ); ?>
			</div>
		</div>
		<?php
		/**
		 * After the LabelFill box on the product page.
		 *
		 * @param int $id Product ID.
		 */
		do_action( 'labelfill_box_end', $post->ID );
	}

	/**
	 * A media library picker.
	 *
	 * @param string $kind  'label' or 'sheet'.
	 * @param string $title Field label.
	 * @param int    $value Attachment ID.
	 */
	private static function file_field( $kind, $title, $value ) {
		$name = $value ? basename( (string) get_attached_file( $value ) ) : '';
		?>
		<p class="labelfill-file">
			<span class="labelfill-file__t"><?php echo esc_html( $title ); ?></span>
			<input type="hidden" name="labelfill_m_<?php echo esc_attr( $kind ); ?>" value="<?php echo esc_attr( $value ? $value : '' ); ?>">
			<button type="button" class="button labelfill-pick" data-lf-kind="<?php echo esc_attr( $kind ); ?>"><?php esc_html_e( 'Choose a file', 'labelfill' ); ?></button>
			<span class="labelfill-file__name"><?php echo esc_html( $name ); ?></span>
			<button type="button" class="button-link labelfill-unpick"<?php echo $value ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'labelfill' ); ?></button>
		</p>
		<?php
	}

	/**
	 * Saves the box with the product, and fills a new number that has no data yet.
	 *
	 * @param int $id Product ID.
	 */
	public static function save( $id ) {
		if ( ! isset( $_POST['labelfill_box_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['labelfill_box_nonce'] ) ), 'labelfill_box' ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		$input  = isset( $_POST['labelfill_reg'] ) ? sanitize_text_field( wp_unslash( $_POST['labelfill_reg'] ) ) : '';
		$number = Api::parse_number( $input );
		if ( '' !== $input && '' === $number ) {
			set_transient( 'labelfill_invalid_' . get_current_user_id(), $input, 60 );
		} else {
			Store::set_number( $id, $number );
		}
		if ( ! empty( $_POST['labelfill_manual'] ) ) {
			Store::set_manual(
				$id,
				array(
					'class' => isset( $_POST['labelfill_m_class'] ) ? sanitize_text_field( wp_unslash( $_POST['labelfill_m_class'] ) ) : '',
					'scale' => isset( $_POST['labelfill_m_scale'] ) ? sanitize_text_field( wp_unslash( $_POST['labelfill_m_scale'] ) ) : '',
					'label' => isset( $_POST['labelfill_m_label'] ) ? absint( $_POST['labelfill_m_label'] ) : 0,
					'sheet' => isset( $_POST['labelfill_m_sheet'] ) ? absint( $_POST['labelfill_m_sheet'] ) : 0,
				)
			);
			return;
		}
		Store::unset_manual( $id );
		self::fill_if_new( $id );
	}

	/**
	 * Fills a number that has never been checked.
	 *
	 * @param int $id Product or variation ID.
	 */
	public static function fill_if_new( $id ) {
		$d = Store::raw( $id );
		if ( '' !== $d['reg'] && '' === $d['class'] && in_array( $d['status'], array( '', 'error' ), true ) ) {
			Store::fill( $id );
		}
	}

	/**
	 * Says that a pasted number was refused on save.
	 */
	public static function invalid_notice() {
		$key   = 'labelfill_invalid_' . get_current_user_id();
		$input = get_transient( $key );
		if ( false === $input ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-error"><p>' . esc_html__( 'LabelFill: this is not a valid EPREL number. Copy the number from the label or the link in the QR code.', 'labelfill' ) . ' <code>' . esc_html( $input ) . '</code></p></div>';
	}

	/**
	 * The compact field in each variation.
	 *
	 * @param int      $loop      Variation index.
	 * @param array    $data      Variation data.
	 * @param \WP_Post $variation Variation post.
	 */
	public static function variation_field( $loop, $data, $variation ) {
		$d     = Store::raw( $variation->ID );
		$shown = '' !== $d['reg'] ? Store::shown( $variation->ID ) : array();
		$pill  = self::pill( $d );
		?>
		<div class="form-row form-row-full labelfill-var">
			<label for="labelfill-var-<?php echo esc_attr( $loop ); ?>"><?php esc_html_e( 'EPREL registration number', 'labelfill' ); ?></label>
			<input type="text" id="labelfill-var-<?php echo esc_attr( $loop ); ?>" name="labelfill_var_reg[<?php echo esc_attr( $loop ); ?>]" value="<?php echo esc_attr( $d['reg'] ); ?>" placeholder="<?php esc_attr_e( 'Empty: uses the main product\'s number', 'labelfill' ); ?>" autocomplete="off">
			<?php if ( '' !== $d['reg'] ) : ?>
				<span class="labelfill-var__state">
					<?php echo ! empty( $shown['class'] ) ? wp_kses( Arrow::svg( $shown['class'], $shown['scale'] ), Arrow::allowed() ) : ''; ?>
					<span class="labelfill-pill is-<?php echo esc_attr( $pill[0] ); ?>"><?php echo esc_html( $pill[1] ); ?></span>
					<?php echo esc_html( 'eprel' === ( isset( $shown['src'] ) ? $shown['src'] : '' ) ? trim( $d['supplier'] . ' ' . $d['model'] ) : '' ); ?>
				</span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Saves a variation's number and fills it if new.
	 *
	 * @param int $id   Variation ID.
	 * @param int $loop Variation index.
	 */
	public static function save_variation( $id, $loop ) {
		check_ajax_referer( 'save-variations', 'security' );
		if ( ! isset( $_POST['labelfill_var_reg'][ $loop ] ) ) {
			return;
		}
		$input  = sanitize_text_field( wp_unslash( $_POST['labelfill_var_reg'][ $loop ] ) );
		$number = Api::parse_number( $input );
		if ( '' !== $input && '' === $number ) {
			return;
		}
		Store::set_number( $id, $number );
		self::fill_if_new( $id );
	}

	/**
	 * Checks the request and returns the product ID.
	 *
	 * @return int
	 */
	private static function ajax_product() {
		check_ajax_referer( 'labelfill', 'nonce' );
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id || ! current_user_can( 'edit_products' ) || ! current_user_can( 'edit_post', $id ) || ! in_array( get_post_type( $id ), array( 'product', 'product_variation' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'labelfill' ) ), 403 );
		}
		return $id;
	}

	/**
	 * "Fill from EPREL", in the box and in the product list.
	 */
	public static function ajax_fill() {
		$id = self::ajax_product();
		// The nonce was checked in ajax_product().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['number'] ) ) {
			$number = Api::parse_number( sanitize_text_field( wp_unslash( $_POST['number'] ) ) );
			if ( '' === $number ) {
				wp_send_json_error( array( 'message' => __( 'This is not a valid EPREL number. Copy the number from the label or the link in the QR code.', 'labelfill' ) ) );
			}
			Store::set_number( $id, $number );
		}
		$replace = ! empty( $_POST['replace'] );
		$where   = isset( $_POST['where'] ) ? sanitize_key( wp_unslash( $_POST['where'] ) ) : 'box';
		// phpcs:enable
		$d     = Store::fill( $id, false, $replace );
		$eprel = isset( $d['eprel'] ) ? $d['eprel'] : array();
		$d     = Store::raw( $id );
		$out   = 'list' === $where ? array( 'html' => AdminList::cell( $id ) ) : self::state( $id, $d, $eprel );
		wp_send_json_success(
			array_merge(
				$out,
				array(
					'number' => $d['reg'],
					'status' => $d['status'],
				)
			)
		);
	}

	/**
	 * "Mark as reviewed" on a changed product.
	 */
	public static function ajax_reviewed() {
		$id = self::ajax_product();
		Store::reviewed( $id );
		wp_send_json_success( self::state( $id, Store::raw( $id ) ) );
	}
}
