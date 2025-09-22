<?php
/**
 * Plugin Name: Beaver Builder Custom Dashboard
 * Plugin URI: http://www.bluedogdigitalmarketing.com
 * Description: Customize the WordPress Dashboard using a Beaver Builder Template
 * Version: 1.2
 * Author: Blue Dog Digital
 * Author URI: http://www.bluedogdigitalmarketing.com
 * License: GPL2
 * Text Domain: bbcd
 */

if ( ! defined('WPINC') ) { die; }

/** === Core constants === */
define( 'BBCD_VERSION', '1.2' );
define( 'BBCD_PLUGIN_FILE', __FILE__ );
define( 'BBCD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBCD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BBCD_OPTION_KEY', 'bbcd_options' );
define( 'BBCD_CAPABILITY', 'manage_options' ); // for settings page access

/** === Load existing main class (if you use it elsewhere) === */
require_once BBCD_PLUGIN_DIR . 'classes/class-bb-custom-dashboard.php';

/** === Settings page === */
require_once BBCD_PLUGIN_DIR . 'includes/class-bbcd-settings.php';
add_action( 'plugins_loaded', function () { new BBCD_Settings(); } );

/** === Options helper (defaults updated) === */
function bbcd_get_options() {
	$defaults = array(
		'enable_custom_dashboard' => 1,
		'template_post_id'        => 0,
		// Who can see the custom dashboard widget:
		// 'manage_options' (Admins), 'edit_others_posts' (Editors+),
		// 'publish_posts' (Authors+), 'edit_posts' (Contributors+), 'read' (all logged-in)
		'view_cap'                => 'edit_others_posts', // Editors+ by default
		'hide_default_widgets'    => array(),
		'enable_auto_updates'     => 0,
		'github_repo'             => 'bluedognz/beaverbuilder-custom-dashboard',
	);
	return wp_parse_args( get_option( BBCD_OPTION_KEY, array() ), $defaults );
}

/** === Settings link on Plugins screen === */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
	$url = admin_url( 'admin.php?page=bbcd-settings' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'bbcd' ) . '</a>' );
	return $links;
});

/** === Front-end preview route (already added earlier) ===================== */
add_filter( 'query_vars', function( $vars ) {
	$vars[] = 'bbcd_preview';
	$vars[] = 'bbcd_nonce';
	return $vars;
});
add_filter( 'template_include', function( $template ) {
	$preview_id = get_query_var( 'bbcd_preview' );
	$nonce      = get_query_var( 'bbcd_nonce' );
	if ( $preview_id && $nonce && current_user_can( BBCD_CAPABILITY ) ) {
		$preview_id = absint( $preview_id );
		if ( wp_verify_nonce( $nonce, 'bbcd_preview_' . $preview_id ) ) {
			$plugin_template = BBCD_PLUGIN_DIR . 'templates/preview.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
	}
	return $template;
});

/** === Render chosen template on the Dashboard screen ====================== */
add_action( 'wp_dashboard_setup', function () {
	$opts        = bbcd_get_options();
	$template_id = isset( $opts['template_post_id'] ) ? absint( $opts['template_post_id'] ) : 0;

	// Hide selected core widgets (optional)
	if ( ! empty( $opts['hide_default_widgets'] ) && is_array( $opts['hide_default_widgets'] ) ) {
		add_action( 'admin_head-index.php', function() use ( $opts ) {
			$map = array(
				'activity'     => 'dashboard_activity',
				'at_a_glance'  => 'dashboard_right_now',
				'quick_draft'  => 'dashboard_quick_press',
				'site_health'  => 'dashboard_site_health',
				'events'       => 'dashboard_primary',
			);
			foreach ( $opts['hide_default_widgets'] as $k ) {
				if ( isset( $map[ $k ] ) ) {
					remove_meta_box( $map[ $k ], 'dashboard', 'normal' );
					remove_meta_box( $map[ $k ], 'dashboard', 'side' );
				}
			}
		});
	}

	// Add the custom widget only if enabled and a template selected.
	if ( empty( $opts['enable_custom_dashboard'] ) || $template_id <= 0 ) {
		return;
	}

	wp_add_dashboard_widget(
		'bbcd_custom_dashboard',
		'', // Title left blank for a cleaner look
		function () use ( $template_id, $opts ) {

			// Check per-role visibility via capability gate.
			$required_cap = ! empty( $opts['view_cap'] ) ? $opts['view_cap'] : 'edit_others_posts';
			$user_can_view = current_user_can( $required_cap );

			// Get template & status
			$template = get_post( $template_id );
			$status   = $template ? $template->post_status : '';

			// Draft/Private fallback for non-editors
			$is_restricted_status = in_array( $status, array( 'draft', 'pending', 'private' ), true );

			// Top action row (only for users who can edit the template)
			if ( current_user_can( 'edit_post', $template_id ) ) {
				$admin_edit_url   = admin_url( 'post.php?post=' . $template_id . '&action=edit' );
				// Many BB installs launch builder from the edit screen; this param usually works:
				$bb_edit_url      = admin_url( 'post.php?post=' . $template_id . '&action=edit&fl_builder=1' );
				$preview_url      = add_query_arg(
					array(
						'bbcd_preview' => $template_id,
						'bbcd_nonce'   => wp_create_nonce( 'bbcd_preview_' . $template_id ),
					),
					home_url( '/' )
				);

				echo '<div style="display:flex;gap:8px;margin:0 0 10px 0;">';
				echo '<a class="button button-primary" href="' . esc_url( $bb_edit_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Edit in Beaver Builder', 'bbcd' ) . '</a> ';
				echo '<a class="button" href="' . esc_url( $admin_edit_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Edit Template', 'bbcd' ) . '</a> ';
				echo '<a class="button" href="' . esc_url( $preview_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Preview (front-end)', 'bbcd' ) . '</a>';
				echo '</div>';
			}

			// If the user does not meet visibility, show a polite fallback.
			if ( ! $user_can_view ) {
				echo '<p>' . esc_html__( 'A custom dashboard is enabled, but you do not have permission to view it.', 'bbcd' ) . '</p>';
				return;
			}

			// If template is restricted (draft/private) and viewer cannot edit it, show fallback.
			if ( $is_restricted_status && ! current_user_can( 'edit_post', $template_id ) ) {
				echo '<p>' . esc_html__( 'The dashboard template is not published yet. Please check back later.', 'bbcd' ) . '</p>';
				return;
			}

			// Enqueue Beaver Builder styles/scripts for better fidelity (if available).
			add_action( 'admin_enqueue_scripts', function( $hook ) use ( $template_id ) {
				if ( 'index.php' !== $hook ) return;
				if ( class_exists( 'FLBuilder' ) ) {
					if ( method_exists( 'FLBuilder', 'enqueue_layout_styles_scripts' ) ) {
						FLBuilder::enqueue_layout_styles_scripts( $template_id );
					} elseif ( method_exists( 'FLBuilder', 'enqueue_layout_styles_scripts_by_id' ) ) {
						FLBuilder::enqueue_layout_styles_scripts_by_id( $template_id );
					}
				}
			}, 20 );

			// Render
			$shortcode = sprintf( '[fl_builder_insert_layout id="%d"]', $template_id );
			$content   = do_shortcode( $shortcode );

			if ( empty( $content ) ) {
				echo '<p>' . esc_html__( 'Selected Beaver Builder item could not be rendered. Ensure it exists and is published.', 'bbcd' ) . '</p>';
			} else {
				echo '<div class="bbcd-widget-wrap">'; // simple wrapper for styling if needed
				echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</div>';
			}
		}
	);

	// Move our widget to the top.
	add_action( 'admin_head-index.php', function () {
		global $wp_meta_boxes;
		if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['bbcd_custom_dashboard'] ) ) {
			$widget = $wp_meta_boxes['dashboard']['normal']['core']['bbcd_custom_dashboard'];
			unset( $wp_meta_boxes['dashboard']['normal']['core']['bbcd_custom_dashboard'] );
			$wp_meta_boxes['dashboard']['normal']['high']['bbcd_custom_dashboard'] = $widget;
		}
	});
});

/** -------------------------------------------------------------------------
 * Plugin Update Checker (PUC) setup (yours)
 * ------------------------------------------------------------------------- */
require __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// IMPORTANT: Third parameter must match the ACTUAL plugin folder name.
$updateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/bluedognz/beaverbuilder-custom-dashboard',
	__FILE__,
	'beaverbuilder-custom-dashboard'
);
$updateChecker->setBranch('main');
if ( defined('BB_CUSTOM_DASH_GH_TOKEN') && BB_CUSTOM_DASH_GH_TOKEN ) {
	$updateChecker->setAuthentication(BB_CUSTOM_DASH_GH_TOKEN);
}
// $updateChecker->getVcsApi()->enableReleaseAssets();
