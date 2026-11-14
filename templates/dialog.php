<?php
/**
 * The label window. dialog.js fills in the label and sheet of the arrow that opened it.
 *
 * @package LabelFill
 */

defined( 'ABSPATH' ) || exit;
?>
<dialog class="labelfill-dialog" aria-labelledby="labelfill-dialog-title">
	<div class="labelfill-dialog__box">
		<div class="labelfill-dialog__head">
			<h2 class="labelfill-dialog__title" id="labelfill-dialog-title"><?php esc_html_e( 'Energy label', 'labelfill' ); ?></h2>
			<button type="button" class="labelfill-dialog__close" aria-label="<?php esc_attr_e( 'Close', 'labelfill' ); ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></button>
		</div>
		<div class="labelfill-dialog__img">
			<img alt="<?php esc_attr_e( 'Energy label', 'labelfill' ); ?>" decoding="async">
			<p class="labelfill-dialog__missing" hidden><?php esc_html_e( 'The label could not be loaded. The class arrow above is the official class.', 'labelfill' ); ?></p>
		</div>
		<p class="labelfill-dialog__extra" hidden></p>
		<p class="labelfill-dialog__links"><a class="labelfill-dialog__sheet" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Product information sheet', 'labelfill' ); ?></a></p>
		<p class="labelfill-dialog__source"><?php esc_html_e( 'Source: EPREL, European Commission', 'labelfill' ); ?></p>
		<p class="labelfill-dialog__zoom"><?php esc_html_e( 'Pinch to zoom', 'labelfill' ); ?></p>
	</div>
</dialog>
