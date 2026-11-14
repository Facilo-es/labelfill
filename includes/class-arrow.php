<?php
/**
 * The legal class arrow: scales, colours and the inline SVG.
 *
 * @package LabelFill
 */

namespace LabelFill;

defined( 'ABSPATH' ) || exit;

/**
 * Arrow.
 */
class Arrow {

	/**
	 * Every class, best first.
	 */
	const ALL = array( 'A+++', 'A++', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G' );

	/**
	 * The seven label colours, best first (delegated regulations, CMYK to screen).
	 */
	const SEVEN = array( '#00A651', '#50B848', '#BED630', '#FFF200', '#FDB913', '#F37021', '#ED1C24' );

	/**
	 * Tyre label colours A to E (Regulation (EU) 2020/740).
	 */
	const TYRES = array( '#00A651', '#9BCB3C', '#FFF200', '#FDB913', '#ED1C24' );

	/**
	 * Scales the owner can pick by hand.
	 *
	 * @return array<string,string>
	 */
	public static function scales() {
		return array(
			'A_G'    => 'A–G',
			'A_E'    => __( 'A–E (tyres)', 'labelfill' ),
			'APPP_D' => __( 'A+++–D (older label)', 'labelfill' ),
		);
	}

	/**
	 * Normalises a class from EPREL or a form: "A_PLUS_PLUS" or "A++" to "A++".
	 *
	 * @param string $value Class.
	 * @return string
	 */
	public static function clean_class( $value ) {
		$value = strtoupper( trim( (string) $value ) );
		$value = str_replace( array( '_PLUS', 'PLUS', 'P' ), '+', $value );
		$value = preg_replace( '/[^A-G+]/', '', $value );
		return in_array( $value, self::ALL, true ) ? $value : '';
	}

	/**
	 * Normalises a scale to FROM_TO with P for plus: "A+++_D" to "APPP_D".
	 *
	 * @param string $value Scale.
	 * @return string
	 */
	public static function clean_scale( $value ) {
		$parts = preg_split( '/[_\-–]/u', strtoupper( (string) $value ) );
		if ( 2 !== count( $parts ) ) {
			return '';
		}
		$from = self::clean_class( $parts[0] );
		$to   = self::clean_class( $parts[1] );
		if ( '' === $from || '' === $to || array_search( $from, self::ALL, true ) >= array_search( $to, self::ALL, true ) ) {
			return '';
		}
		return str_replace( '+', 'P', $from ) . '_' . $to;
	}

	/**
	 * Classes of a scale, best first.
	 *
	 * @param string $scale Scale.
	 * @return string[]
	 */
	public static function classes( $scale ) {
		$scale = self::clean_scale( $scale );
		if ( '' === $scale ) {
			return array();
		}
		list( $from, $to ) = explode( '_', str_replace( 'P', '+', $scale ) );
		$start             = array_search( $from, self::ALL, true );
		return array_slice( self::ALL, $start, array_search( $to, self::ALL, true ) - $start + 1 );
	}

	/**
	 * Colour of a class on its scale.
	 *
	 * @param string $grade Class.
	 * @param string $scale Scale.
	 * @return string Hex, or '' when the class is not on the scale.
	 */
	public static function colour( $grade, $scale ) {
		$list = self::classes( $scale );
		$i    = array_search( $grade, $list, true );
		if ( false === $i ) {
			return '';
		}
		$n = count( $list );
		if ( 5 === $n && 'A' === $list[0] ) {
			return self::TYRES[ $i ];
		}
		return self::SEVEN[ $n > 1 ? (int) round( $i * 6 / ( $n - 1 ) ) : 0 ];
	}

	/**
	 * Inline SVG of the arrow, or '' when the data doesn't fit together.
	 *
	 * @param string $grade Class.
	 * @param string $scale Scale.
	 * @return string
	 */
	public static function svg( $grade, $scale ) {
		$colour = self::colour( $grade, $scale );
		if ( '' === $colour ) {
			return '';
		}
		$list    = self::classes( $scale );
		$lo      = $list[0];
		$hi      = end( $list );
		$outline = '#FFF200' === $colour ? ' stroke="#000" stroke-width="1.6" paint-order="stroke"' : '';
		$size    = strlen( $grade ) > 2 ? 17 : ( strlen( $grade ) > 1 ? 22 : 29 );
		$small   = strlen( $lo ) > 2 ? 7 : 12;
		return '<svg class="labelfill-svg" viewBox="0 0 66 36" aria-hidden="true" focusable="false">'
			. '<rect x=".75" y=".75" width="16.5" height="34.5" fill="#fff" stroke="#000" stroke-width="1.5"/>'
			. '<text x="9" y="15" font-size="' . $small . '" font-weight="800" text-anchor="middle" font-family="Arial,sans-serif" fill="#000">' . esc_html( $lo ) . '</text>'
			. '<path d="M9 18.2v4.6" stroke="#000" stroke-width="1.6"/>'
			. '<text x="9" y="32.6" font-size="12" font-weight="800" text-anchor="middle" font-family="Arial,sans-serif" fill="#000">' . esc_html( $hi ) . '</text>'
			. '<path d="M17 0h33l16 18-16 18H17z" fill="' . esc_attr( $colour ) . '"/>'
			. '<text x="36" y="' . ( 29 === $size ? '29.5' : '26' ) . '" font-size="' . $size . '" font-weight="800" fill="#fff" text-anchor="middle" font-family="Arial,sans-serif"' . $outline . '>' . esc_html( $grade ) . '</text>'
			. '</svg>';
	}

	/**
	 * Accessible name of the arrow button.
	 *
	 * @param string $grade Class.
	 * @param string $scale Scale.
	 * @return string
	 */
	public static function label( $grade, $scale ) {
		$list = self::classes( $scale );
		/* translators: 1: energy class, 2: best class of the scale, 3: worst class of the scale */
		return sprintf( __( 'Energy efficiency class %1$s, on a scale from %2$s to %3$s. View energy label', 'labelfill' ), $grade, $list ? $list[0] : '', $list ? end( $list ) : '' );
	}

	/**
	 * Tags and attributes allowed when printing HTML that holds an arrow.
	 *
	 * @return array
	 */
	public static function allowed() {
		$allowed = wp_kses_allowed_html( 'post' );
		$common  = array(
			'class'          => true,
			'id'             => true,
			'type'           => true,
			'name'           => true,
			'value'          => true,
			'checked'        => true,
			'selected'       => true,
			'disabled'       => true,
			'hidden'         => true,
			'placeholder'    => true,
			'aria-label'     => true,
			'aria-haspopup'  => true,
			'aria-live'      => true,
			'aria-hidden'    => true,
			'aria-checked'   => true,
			'role'           => true,
			'for'            => true,
			'autocomplete'   => true,
			'data-lf-label'  => true,
			'data-lf-sheet'  => true,
			'data-lf-extra'  => true,
			'data-lf-src'    => true,
			'data-lf-id'     => true,
			'data-lf-action' => true,
			'data-lf-kind'   => true,
			'data-lf-scales' => true,
			'data-lf-class'  => true,
		);
		foreach ( array( 'button', 'input', 'select', 'option', 'label', 'span', 'div', 'a', 'img', 'p', 'dl', 'dt', 'dd', 'b', 'strong', 'small', 'ul', 'li' ) as $tag ) {
			$allowed[ $tag ] = array_merge( isset( $allowed[ $tag ] ) ? $allowed[ $tag ] : array(), $common );
		}
		$svg             = array(
			'class'          => true,
			'viewbox'        => true,
			'aria-hidden'    => true,
			'focusable'      => true,
			'x'              => true,
			'y'              => true,
			'width'          => true,
			'height'         => true,
			'fill'           => true,
			'stroke'         => true,
			'stroke-width'   => true,
			'd'              => true,
			'font-size'      => true,
			'font-weight'    => true,
			'font-family'    => true,
			'text-anchor'    => true,
			'paint-order'    => true,
			'stroke-linecap' => true,
		);
		$allowed['svg']  = $svg;
		$allowed['rect'] = $svg;
		$allowed['text'] = $svg;
		$allowed['path'] = $svg;
		return $allowed;
	}
}
