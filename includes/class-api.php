<?php
/**
 * EPREL public API: number parsing, record, label and sheet addresses.
 *
 * @package LabelFill
 */

namespace LabelFill;

defined( 'ABSPATH' ) || exit;

/**
 * Api.
 */
class Api {

	const HOST = 'https://eprel.ec.europa.eu';

	/**
	 * EPREL's sheet languages.
	 */
	const LANGS = array( 'BG', 'CS', 'DA', 'DE', 'EL', 'EN', 'ES', 'ET', 'FI', 'FR', 'GA', 'HR', 'HU', 'IT', 'LT', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SL', 'SV' );

	/**
	 * Pulls an EPREL number out of a pasted number or link.
	 *
	 * @param string $input What the owner pasted.
	 * @return string Digits, or '' when there is none.
	 */
	public static function parse_number( $input ) {
		$input = (string) $input;
		if ( preg_match( '#eprel\.ec\.europa\.eu/(?:qr|screen/product/[a-z0-9]+|api/product)/(\d{1,8})#i', $input, $m ) || preg_match( '/^\s*(\d{1,8})\s*$/', $input, $m ) ) {
			return ltrim( $m[1], '0' ) === '' ? '' : ltrim( $m[1], '0' );
		}
		return '';
	}

	/**
	 * EPREL language code for a WordPress locale, EN when EPREL has none.
	 *
	 * @param string $locale Locale.
	 * @return string
	 */
	public static function lang( $locale = '' ) {
		$locale = $locale ? $locale : get_locale();
		$code   = strtoupper( substr( $locale, 0, 2 ) );
		if ( in_array( $code, array( 'CA', 'GL', 'EU' ), true ) ) {
			return 'ES';
		}
		return in_array( $code, self::LANGS, true ) ? $code : 'EN';
	}

	/**
	 * One GET to EPREL. Retries once after a pause on a timeout, 429 or 5xx.
	 *
	 * @param string $path Path from the host.
	 * @return array{code:int,body:mixed,raw:string}
	 */
	public static function get( $path ) {
		$args = array(
			'timeout'    => 10,
			'user-agent' => 'LabelFill/' . LABELFILL_VERSION . ' (+https://wordpress.org/plugins/labelfill/)',
			'headers'    => array( 'Accept' => 'application/json' ),
		);
		for ( $try = 0; $try < 2; $try++ ) {
			$res = wp_remote_get( self::HOST . $path, $args );
			if ( is_wp_error( $res ) ) {
				$code = 0;
				$raw  = $res->get_error_message();
			} else {
				$code = (int) wp_remote_retrieve_response_code( $res );
				$raw  = (string) wp_remote_retrieve_body( $res );
			}
			if ( 0 !== $code && 429 !== $code && $code < 500 ) {
				break;
			}
			if ( 0 === $try ) {
				$pause = (int) apply_filters( 'labelfill_retry_pause', 2 );
				if ( $pause > 0 ) {
					sleep( $pause );
				}
			}
		}
		return array(
			'code' => $code,
			'body' => json_decode( $raw, true ),
			'raw'  => $raw,
		);
	}

	/**
	 * Reads one product from EPREL.
	 *
	 * @param string   $number EPREL number.
	 * @param string[] $langs  Sheet languages wanted.
	 * @return array Status plus the data found.
	 */
	public static function fetch( $number, $langs = array() ) {
		$res  = self::get( '/api/product/' . rawurlencode( $number ) );
		$body = is_array( $res['body'] ) ? $res['body'] : array();
		$code = isset( $body['code'] ) ? $body['code'] : '';
		if ( 404 === $res['code'] || 'NOT_FOUND' === $code ) {
			return array( 'status' => 'not_found' );
		}
		if ( 403 === $res['code'] || 'UNVERIFIED_SUPPLIER' === $code ) {
			return array( 'status' => 'unverified' );
		}
		if ( 200 !== $res['code'] || empty( $body['productGroup'] ) || empty( $body['energyClass'] ) ) {
			self::debug( $number, $res );
			return array( 'status' => 'error' );
		}
		$group = sanitize_key( $body['productGroup'] );
		$data  = array(
			'status'   => empty( $body['blocked'] ) ? 'ok' : 'blocked',
			'reg'      => $number,
			'group'    => $group,
			'class'    => Arrow::clean_class( $body['energyClass'] ),
			'scale'    => Arrow::clean_scale( isset( $body['energyClassRange'] ) ? $body['energyClassRange'] : '' ),
			'supplier' => self::supplier( $body ),
			'model'    => isset( $body['modelIdentifier'] ) ? trim( (string) $body['modelIdentifier'] ) : '',
			'version'  => isset( $body['versionId'] ) ? (int) $body['versionId'] : 0,
			'label'    => self::file_url( 'labels', $group, $number, 'format=PNG' ),
			'sheets'   => array(),
		);
		if ( 'tyres' === $group ) {
			$data['wet']   = isset( $body['wetGripClass'] ) ? Arrow::clean_class( $body['wetGripClass'] ) : '';
			$data['noise'] = isset( $body['externalRollingNoiseClass'] ) ? Arrow::clean_class( $body['externalRollingNoiseClass'] ) : '';
		}
		foreach ( array_unique( array_merge( $langs, array( 'EN' ) ) ) as $lang ) {
			$url = self::file_url( 'fiches', $group, $number, 'language=' . $lang );
			if ( $url ) {
				$data['sheets'][ $lang ] = $url;
			}
		}
		if ( '' === $data['label'] ) {
			$data['status'] = 'error';
		}
		return $data;
	}

	/**
	 * Supplier name from a record.
	 *
	 * @param array $body Record.
	 * @return string
	 */
	private static function supplier( $body ) {
		// The brand on the label first, as shoppers know it; the company behind it otherwise.
		if ( ! empty( $body['supplierOrTrademark'] ) ) {
			return trim( (string) $body['supplierOrTrademark'] );
		}
		return isset( $body['organisation']['organisationName'] ) ? trim( (string) $body['organisation']['organisationName'] ) : '';
	}

	/**
	 * Full address of a label or sheet file, or ''.
	 *
	 * @param string $kind   labels or fiches.
	 * @param string $group  Product group.
	 * @param string $number EPREL number.
	 * @param string $query  Extra query.
	 * @return string
	 */
	public static function file_url( $kind, $group, $number, $query ) {
		$res = self::get( '/api/products/' . rawurlencode( $group ) . '/' . rawurlencode( $number ) . '/' . $kind . '?' . $query . '&noRedirect=true' );
		if ( 200 === $res['code'] && is_array( $res['body'] ) && ! empty( $res['body']['address'] ) ) {
			$address = (string) $res['body']['address'];
			if ( 0 === strpos( $address, '/' ) ) {
				$address = self::HOST . $address;
			}
			return 0 === strpos( $address, self::HOST . '/' ) ? esc_url_raw( $address ) : '';
		}
		return '';
	}

	/**
	 * Notes an odd answer in the debug log, when the owner turned it on.
	 *
	 * @param string $number EPREL number.
	 * @param array  $res    Answer.
	 */
	private static function debug( $number, $res ) {
		if ( empty( Settings::get()['debug'] ) || ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		wc_get_logger()->warning( $number . ' HTTP ' . $res['code'] . ': ' . substr( $res['raw'], 0, 500 ), array( 'source' => 'labelfill' ) );
	}
}
