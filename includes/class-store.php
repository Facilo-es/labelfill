<?php
/**
 * Label data on products and variations: read, fill from EPREL, detect changes.
 *
 * @package LabelFill
 */

namespace LabelFill;

defined( 'ABSPATH' ) || exit;

/**
 * Store.
 */
class Store {

	const STATUSES = array( 'ok', 'not_found', 'unverified', 'blocked', 'error', 'manual', 'changed' );

	/**
	 * Raw label data of one product or variation.
	 *
	 * @param int $id Product or variation ID.
	 * @return array
	 */
	public static function raw( $id ) {
		$sheets = json_decode( (string) get_post_meta( $id, '_labelfill_sheets', true ), true );
		$manual = json_decode( (string) get_post_meta( $id, '_labelfill_manual', true ), true );
		$change = json_decode( (string) get_post_meta( $id, '_labelfill_changes', true ), true );
		return array(
			'reg'      => (string) get_post_meta( $id, '_labelfill_reg', true ),
			'group'    => (string) get_post_meta( $id, '_labelfill_group', true ),
			'class'    => (string) get_post_meta( $id, '_labelfill_class', true ),
			'scale'    => (string) get_post_meta( $id, '_labelfill_scale', true ),
			'supplier' => (string) get_post_meta( $id, '_labelfill_supplier', true ),
			'model'    => (string) get_post_meta( $id, '_labelfill_model', true ),
			'label'    => (string) get_post_meta( $id, '_labelfill_label_url', true ),
			'sheets'   => is_array( $sheets ) ? $sheets : array(),
			'status'   => (string) get_post_meta( $id, '_labelfill_status', true ),
			'checked'  => (int) get_post_meta( $id, '_labelfill_checked', true ),
			'version'  => (int) get_post_meta( $id, '_labelfill_version', true ),
			'hash'     => (string) get_post_meta( $id, '_labelfill_hash', true ),
			'manual'   => is_array( $manual ) ? $manual : array(),
			'changes'  => is_array( $change ) ? $change : array(),
			'wet'      => (string) get_post_meta( $id, '_labelfill_wet', true ),
			'noise'    => (string) get_post_meta( $id, '_labelfill_noise', true ),
		);
	}

	/**
	 * What the shopper sees for a product: class, scale, label and sheet addresses.
	 * A variation without its own data uses its parent's. Empty when there's nothing to show.
	 *
	 * @param int $id Product or variation ID.
	 * @return array
	 */
	public static function shown( $id ) {
		$d = self::raw( $id );
		if ( 'manual' === $d['status'] && $d['manual'] ) {
			$m    = $d['manual'];
			$show = array(
				'class' => Arrow::clean_class( isset( $m['class'] ) ? $m['class'] : '' ),
				'scale' => Arrow::clean_scale( isset( $m['scale'] ) ? $m['scale'] : '' ),
				'label' => empty( $m['label'] ) ? '' : (string) wp_get_attachment_url( (int) $m['label'] ),
				'sheet' => empty( $m['sheet'] ) ? '' : (string) wp_get_attachment_url( (int) $m['sheet'] ),
				'group' => '',
				'src'   => 'shop',
			);
		} elseif ( '' !== $d['class'] && '' !== $d['scale'] ) {
			$show = array(
				'class' => $d['class'],
				'scale' => $d['scale'],
				'label' => $d['label'],
				'sheet' => self::sheet_for( $d['sheets'], Api::lang() ),
				'group' => $d['group'],
				'src'   => 'eprel',
			);
		} else {
			$show = array();
		}
		$show = apply_filters( 'labelfill_shown', $show, $id, $d );
		if ( ! $show || '' === Arrow::colour( $show['class'], $show['scale'] ) ) {
			$parent = wp_get_post_parent_id( $id );
			if ( $parent && 'product_variation' === get_post_type( $id ) ) {
				return self::shown( $parent );
			}
			return array();
		}
		return $show;
	}

	/**
	 * Sheet in a language, then English, then any.
	 *
	 * @param array  $sheets Language => address.
	 * @param string $lang   EPREL language code.
	 * @return string
	 */
	public static function sheet_for( $sheets, $lang ) {
		if ( ! empty( $sheets[ $lang ] ) ) {
			return $sheets[ $lang ];
		}
		if ( ! empty( $sheets['EN'] ) ) {
			return $sheets['EN'];
		}
		return $sheets ? (string) reset( $sheets ) : '';
	}

	/**
	 * Fingerprint of what matters for a change alert.
	 *
	 * @param array $d Data.
	 * @return string
	 */
	public static function hash( $d ) {
		return sha1( implode( '|', array( $d['group'], $d['class'], $d['scale'], $d['label'], $d['status'] ) ) );
	}

	/**
	 * Saves the number alone. A new number clears the old EPREL data.
	 *
	 * @param int    $id     Product or variation ID.
	 * @param string $number EPREL number, digits.
	 * @return bool Whether it changed.
	 */
	public static function set_number( $id, $number ) {
		$number = (string) $number;
		if ( '' !== $number ) {
			$number = Api::parse_number( $number );
			if ( '' === $number ) {
				return false;
			}
		}
		$old = (string) get_post_meta( $id, '_labelfill_reg', true );
		if ( $old === $number ) {
			return false;
		}
		foreach ( array( 'group', 'class', 'scale', 'supplier', 'model', 'label_url', 'sheets', 'checked', 'version', 'hash', 'changes', 'wet', 'noise' ) as $key ) {
			delete_post_meta( $id, '_labelfill_' . $key );
		}
		if ( '' === $number ) {
			delete_post_meta( $id, '_labelfill_reg' );
			if ( 'manual' !== get_post_meta( $id, '_labelfill_status', true ) ) {
				delete_post_meta( $id, '_labelfill_status' );
			}
		} else {
			update_post_meta( $id, '_labelfill_reg', $number );
			if ( 'manual' !== get_post_meta( $id, '_labelfill_status', true ) ) {
				delete_post_meta( $id, '_labelfill_status' );
			}
		}
		do_action( 'labelfill_number_changed', $id, $number, $old );
		return true;
	}

	/**
	 * Fills or refreshes one product from EPREL.
	 *
	 * @param int  $id      Product or variation ID.
	 * @param bool $refresh True for the background refresh, which flags changes.
	 * @param bool $replace True to replace data entered by hand.
	 * @return array The data after the fill, with 'status'.
	 */
	public static function fill( $id, $refresh = false, $replace = false ) {
		$old = self::raw( $id );
		if ( '' === $old['reg'] ) {
			return $old;
		}
		$manual = 'manual' === $old['status'];
		$langs  = array_unique( array_merge( array( Api::lang() ), array_keys( $old['sheets'] ), (array) apply_filters( 'labelfill_sheet_languages', array() ) ) );
		$new    = Api::fetch( $old['reg'], $langs );
		update_post_meta( $id, '_labelfill_checked', time() );

		if ( $manual && ! $replace ) {
			$old['eprel'] = $new;
			return $old;
		}
		if ( ! isset( $new['class'] ) ) {
			// No data: keep what's stored, only the status changes.
			update_post_meta( $id, '_labelfill_status', $new['status'] );
			if ( 'error' !== $new['status'] ) {
				update_post_meta( $id, '_labelfill_hash', self::hash( array_merge( $old, array( 'status' => $new['status'] ) ) ) );
			}
			do_action( 'labelfill_filled', $id, $old, $new, $refresh );
			return array_merge(
				$old,
				array(
					'status'  => $new['status'],
					'checked' => time(),
				)
			);
		}
		if ( 'error' === $new['status'] && '' !== $old['class'] && ! $manual ) {
			update_post_meta( $id, '_labelfill_status', 'error' );
			do_action( 'labelfill_filled', $id, $old, $new, $refresh );
			return array_merge( $old, array( 'status' => 'error' ) );
		}
		if ( $old['version'] && $new['version'] && $new['version'] < $old['version'] ) {
			// An older version than the one stored: keep the newest.
			update_post_meta( $id, '_labelfill_status', 'ok' === $old['status'] || 'changed' === $old['status'] ? $old['status'] : 'ok' );
			return $old;
		}

		$status  = $new['status'];
		$hash    = self::hash( $new );
		$changes = array();
		if ( $refresh && '' !== $old['class'] ) {
			foreach ( array( 'class', 'scale', 'group', 'label' ) as $field ) {
				if ( $old[ $field ] !== $new[ $field ] && '' !== $old[ $field ] ) {
					$changes[] = array(
						'field' => $field,
						'old'   => $old[ $field ],
						'new'   => $new[ $field ],
					);
				}
			}
		}
		if ( 'ok' === $status && ( $changes || ( $refresh && 'changed' === $old['status'] ) ) ) {
			$status = 'changed';
		}

		update_post_meta( $id, '_labelfill_group', $new['group'] );
		update_post_meta( $id, '_labelfill_class', $new['class'] );
		update_post_meta( $id, '_labelfill_scale', $new['scale'] );
		update_post_meta( $id, '_labelfill_supplier', $new['supplier'] );
		update_post_meta( $id, '_labelfill_model', $new['model'] );
		update_post_meta( $id, '_labelfill_label_url', $new['label'] );
		update_post_meta( $id, '_labelfill_sheets', wp_json_encode( array_merge( $old['sheets'], $new['sheets'] ) ) );
		update_post_meta( $id, '_labelfill_version', $new['version'] );
		update_post_meta( $id, '_labelfill_hash', $hash );
		update_post_meta( $id, '_labelfill_status', $status );
		foreach ( array( 'wet', 'noise' ) as $key ) {
			if ( ! empty( $new[ $key ] ) ) {
				update_post_meta( $id, '_labelfill_' . $key, $new[ $key ] );
			}
		}
		if ( $changes ) {
			update_post_meta(
				$id,
				'_labelfill_changes',
				wp_json_encode(
					array(
						'date' => time(),
						'list' => $changes,
					)
				)
			);
		} elseif ( 'changed' !== $status ) {
			delete_post_meta( $id, '_labelfill_changes' );
		}
		if ( $manual ) {
			delete_post_meta( $id, '_labelfill_manual' );
		}
		do_action( 'labelfill_filled', $id, $old, $new, $refresh );
		return self::raw( $id );
	}

	/**
	 * Marks a changed product as reviewed.
	 *
	 * @param int $id Product or variation ID.
	 */
	public static function reviewed( $id ) {
		if ( 'changed' === get_post_meta( $id, '_labelfill_status', true ) ) {
			update_post_meta( $id, '_labelfill_status', 'ok' );
			delete_post_meta( $id, '_labelfill_changes' );
			update_post_meta( $id, '_labelfill_hash', self::hash( self::raw( $id ) ) );
		}
	}

	/**
	 * Saves data entered by hand.
	 *
	 * @param int   $id     Product or variation ID.
	 * @param array $manual class, scale, label (attachment ID), sheet (attachment ID).
	 */
	public static function set_manual( $id, $manual ) {
		$clean = array(
			'class' => Arrow::clean_class( isset( $manual['class'] ) ? $manual['class'] : '' ),
			'scale' => Arrow::clean_scale( isset( $manual['scale'] ) ? $manual['scale'] : '' ),
			'label' => isset( $manual['label'] ) ? absint( $manual['label'] ) : 0,
			'sheet' => isset( $manual['sheet'] ) ? absint( $manual['sheet'] ) : 0,
		);
		update_post_meta( $id, '_labelfill_manual', wp_json_encode( $clean ) );
		update_post_meta( $id, '_labelfill_status', 'manual' );
	}

	/**
	 * Turns manual mode off.
	 *
	 * @param int $id Product or variation ID.
	 */
	public static function unset_manual( $id ) {
		if ( 'manual' !== get_post_meta( $id, '_labelfill_status', true ) ) {
			return;
		}
		delete_post_meta( $id, '_labelfill_manual' );
		if ( get_post_meta( $id, '_labelfill_class', true ) ) {
			update_post_meta( $id, '_labelfill_status', 'ok' );
		} elseif ( get_post_meta( $id, '_labelfill_reg', true ) ) {
			update_post_meta( $id, '_labelfill_status', 'error' );
		} else {
			delete_post_meta( $id, '_labelfill_status' );
		}
	}
}
