<?php
/**
 * Front-end preview for Beaver Builder saved template/row/module.
 * URL: /?bbcd_preview=ID&bbcd_nonce=...
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$preview_id = absint( get_query_var( 'bbcd_preview' ) );
if ( ! $preview_id || ! current_user_can( BBCD_CAPABILITY ) ) {
	status_header( 403 );
	wp_die( esc_html__( 'You are not allowed to preview this item.', 'bbcd' ) );
}

get_header();

// Enqueue Beaver Builder assets for fidelity.
if ( class_exists( 'FLBuilder' ) ) {
	if ( method_exists( 'FLBuilder', 'enqueue_layout_styles_scripts' ) ) {
		FLBuilder::enqueue_layout_styles_scripts( $preview_id );
	} elseif ( method_exists( 'FLBuilder', 'enqueue_layout_styles_scripts_by_id' ) ) {
		FLBuilder::enqueue_layout_styles_scripts_by_id( $preview_id );
	}
}

echo '<main class="bbcd-preview" style="max-width:1200px;margin:40px auto;padding:0 20px;">';
echo '<h1 style="margin-bottom:20px;">' . esc_html__( 'Dashboard Template Preview', 'bbcd' ) . '</h1>';

$slug    = get_post_field( 'post_name', $preview_id );
$content = '';

$attempts = array(
	sprintf( '[fl_builder_insert_layout id="%d"]', $preview_id ),
	$slug ? sprintf( '[fl_builder_insert_layout slug="%s"]', esc_attr( $slug ) ) : '',
);

foreach ( $attempts as $sc ) {
	if ( ! $sc ) continue;
	$out = do_shortcode( $sc );
	if ( ! empty( $out ) ) {
		$content = $out;
		break;
	}
}

if ( empty( $content ) ) {
	echo '<p>' . esc_html__( 'Nothing rendered. Ensure the selected item exists and is a Beaver Builder saved layout/row/module.', 'bbcd' ) . '</p>';
} else {
	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

echo '</main>';

get_footer();
