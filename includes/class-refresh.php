<?php
/**
 * Daily refresh from EPREL, as its terms ask: every product at least every 30 days.
 *
 * @package LabelFill
 */

namespace LabelFill;

defined( 'ABSPATH' ) || exit;

/**
 * Refresh.
 */
class Refresh {

	const DAILY = 'labelfill_daily';
	const ONE   = 'labelfill_refresh_one';
	const GROUP = 'labelfill';

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( self::DAILY, array( __CLASS__, 'daily' ) );
		add_action( self::ONE, array( __CLASS__, 'one' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
	}

	/**
	 * Schedules the daily run once.
	 */
	public static function schedule() {
		if ( ! function_exists( 'as_has_scheduled_action' ) || as_has_scheduled_action( self::DAILY, array(), self::GROUP ) ) {
			return;
		}
		as_schedule_recurring_action( strtotime( 'tomorrow 03:00' ) + wp_rand( 0, 3600 ), DAY_IN_SECONDS, self::DAILY, array(), self::GROUP );
	}

	/**
	 * Removes our scheduled runs.
	 */
	public static function unschedule() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::DAILY, array(), self::GROUP );
			as_unschedule_all_actions( self::ONE, null, self::GROUP );
		}
	}

	/**
	 * Products a day: min(200, ceil(N / 30)).
	 *
	 * @param int $n Products with a number.
	 * @return int
	 */
	public static function batch_size( $n ) {
		return (int) min( 200, (int) ceil( $n / 30 ) );
	}

	/**
	 * The daily run: queues the products checked longest ago, about one request a second.
	 *
	 * @return int[] Product IDs queued.
	 */
	public static function daily() {
		$ids = self::oldest( self::batch_size( self::count_numbered() ) );
		foreach ( array_values( $ids ) as $i => $id ) {
			// Each fill makes about three requests.
			as_schedule_single_action( time() + $i * 3, self::ONE, array( 'id' => $id ), self::GROUP );
		}
		return $ids;
	}

	/**
	 * Refreshes one product.
	 *
	 * @param int $id Product or variation ID.
	 */
	public static function one( $id ) {
		$id = absint( $id );
		if ( ! $id || 'manual' === get_post_meta( $id, '_labelfill_status', true ) ) {
			return;
		}
		Store::fill( $id, true );
	}

	/**
	 * Products with a number, not entered by hand, checked longest ago.
	 *
	 * @param int $limit How many.
	 * @return int[]
	 */
	public static function oldest( $limit ) {
		global $wpdb;
		if ( $limit < 1 ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A sorted list across products and variations, read once a day.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = '_labelfill_reg' AND r.meta_value <> ''
				LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_labelfill_status'
				LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = '_labelfill_checked'
				WHERE p.post_type IN ('product','product_variation') AND p.post_status NOT IN ('trash','auto-draft')
				AND ( s.meta_value IS NULL OR s.meta_value <> 'manual' )
				ORDER BY CAST( COALESCE( c.meta_value, '0' ) AS UNSIGNED ) ASC, p.ID ASC LIMIT %d",
				$limit
			)
		);
		return array_map( 'intval', $ids );
	}

	/**
	 * Products and variations with a number.
	 *
	 * @return int
	 */
	public static function count_numbered() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Count only.
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = '_labelfill_reg' AND r.meta_value <> ''
			WHERE p.post_type IN ('product','product_variation') AND p.post_status NOT IN ('trash','auto-draft')"
		);
	}

	/**
	 * Products and variations with a status.
	 *
	 * @param string $status Status.
	 * @return int
	 */
	public static function count_status( $status ) {
		global $wpdb;
		$cache = wp_cache_get( 'count_' . $status, 'labelfill' );
		if ( false !== $cache ) {
			return (int) $cache;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Count only, cached for the request.
		$n = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_labelfill_status' AND s.meta_value = %s
				WHERE p.post_type IN ('product','product_variation') AND p.post_status NOT IN ('trash','auto-draft')",
				$status
			)
		);
		wp_cache_set( 'count_' . $status, $n, 'labelfill' );
		return $n;
	}
}
