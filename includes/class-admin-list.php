<?php
/**
 * The "Class" column, the filter and the row action in the products list.
 *
 * @package LabelFill
 */

namespace LabelFill;

defined( 'ABSPATH' ) || exit;

/**
 * Products list.
 */
class AdminList {

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'columns' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filter' ), 20 );
		add_action( 'pre_get_posts', array( __CLASS__, 'query' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_action' ), 10, 2 );
	}

	/**
	 * Adds the column after the price.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $name ) {
			$out[ $key ] = $name;
			if ( 'price' === $key ) {
				$out['labelfill'] = __( 'Class', 'labelfill' );
			}
		}
		if ( ! isset( $out['labelfill'] ) ) {
			$out['labelfill'] = __( 'Class', 'labelfill' );
		}
		return $out;
	}

	/**
	 * The mini arrow, or a status badge.
	 *
	 * @param int $id Product ID.
	 * @return string
	 */
	public static function cell( $id ) {
		$d     = Store::raw( $id );
		$shown = Store::shown( $id );
		$out   = '';
		if ( ! empty( $shown['class'] ) && in_array( $d['status'], array( 'ok', 'manual', '' ), true ) && ( '' !== $d['reg'] || 'manual' === $d['status'] ) ) {
			$out = '<span class="labelfill-mini" title="' . esc_attr( Arrow::label( $shown['class'], $shown['scale'] ) ) . '">' . Arrow::svg( $shown['class'], $shown['scale'] ) . '</span>';
			if ( 'manual' === $d['status'] ) {
				$pill = ProductFields::pill( $d );
				$out .= ' <span class="labelfill-pill is-off">' . esc_html( $pill[1] ) . '</span>';
			}
			return $out;
		}
		$pill = ProductFields::pill( $d );
		if ( ! empty( $shown['class'] ) ) {
			$out = '<span class="labelfill-mini">' . Arrow::svg( $shown['class'], $shown['scale'] ) . '</span> ';
		}
		return $out . '<span class="labelfill-pill is-' . esc_attr( $pill[0] ) . '">' . esc_html( $pill[1] ) . '</span>';
	}

	/**
	 * Prints the column.
	 *
	 * @param string $column Column key.
	 * @param int    $id     Product ID.
	 */
	public static function column( $column, $id ) {
		if ( 'labelfill' === $column ) {
			echo '<span class="labelfill-cell" data-lf-id="' . esc_attr( $id ) . '">' . wp_kses( self::cell( $id ), Arrow::allowed() ) . '</span>';
		}
	}

	/**
	 * The chosen filter.
	 *
	 * @return string
	 */
	public static function state() {
		// A read-only list filter, like WooCommerce's own.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state = isset( $_GET['labelfill_state'] ) ? sanitize_key( wp_unslash( $_GET['labelfill_state'] ) ) : '';
		return in_array( $state, array( 'none', 'problems', 'changed' ), true ) ? $state : '';
	}

	/**
	 * The filter select.
	 *
	 * @param string $post_type Post type.
	 */
	public static function filter( $post_type ) {
		if ( 'product' !== $post_type ) {
			return;
		}
		$options = array(
			''         => __( 'Energy label: all', 'labelfill' ),
			'none'     => __( 'No number', 'labelfill' ),
			'problems' => __( 'With problems', 'labelfill' ),
			'changed'  => __( 'Changed', 'labelfill' ),
		);
		echo '<label class="screen-reader-text" for="labelfill-state">' . esc_html__( 'Energy label', 'labelfill' ) . '</label><select name="labelfill_state" id="labelfill-state">';
		foreach ( $options as $value => $name ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( self::state(), $value, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Applies the filter.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'product' !== $query->get( 'post_type' ) ) {
			return;
		}
		$state = self::state();
		if ( '' === $state ) {
			return;
		}
		$meta = (array) $query->get( 'meta_query' );
		if ( 'none' === $state ) {
			$meta[] = array(
				'relation' => 'AND',
				array(
					'relation' => 'OR',
					array(
						'key'     => '_labelfill_reg',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => '_labelfill_reg',
						'value' => '',
					),
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_labelfill_status',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_labelfill_status',
						'value'   => 'manual',
						'compare' => '!=',
					),
				),
			);
		} elseif ( 'changed' === $state ) {
			$meta[] = array(
				'key'   => '_labelfill_status',
				'value' => 'changed',
			);
		} else {
			// Products with a number whose status isn't ok or manual (an unchecked number counts too).
			$meta[] = array(
				'relation' => 'AND',
				array(
					'key'     => '_labelfill_reg',
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_labelfill_status',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_labelfill_status',
						'value'   => array( 'ok', 'manual' ),
						'compare' => 'NOT IN',
					),
				),
			);
		}
		$query->set( 'meta_query', $meta );
	}

	/**
	 * "Fill from EPREL" under the product name, for products with a number.
	 *
	 * @param array    $actions Row actions.
	 * @param \WP_Post $post    Post.
	 * @return array
	 */
	public static function row_action( $actions, $post ) {
		if ( 'product' === $post->post_type && current_user_can( 'edit_post', $post->ID ) && '' !== (string) get_post_meta( $post->ID, '_labelfill_reg', true ) ) {
			$actions['labelfill'] = '<button type="button" class="button-link labelfill-rowfill" data-lf-id="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Fill from EPREL', 'labelfill' ) . '</button>';
		}
		return $actions;
	}
}
