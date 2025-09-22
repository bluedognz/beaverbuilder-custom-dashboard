<?php
/**
 * Plugin Name: Beaver Builder Custom Dashboard
 * Plugin URI: http://www.bluedogdigitalmarketing.com
 * Description: Customize the WordPress Dashboard using a Beaver Builder Template
 * Version: 1.3
 * Author: Blue Dog Digital
 * Author URI: http://www.bluedogdigitalmarketing.com
 * License: GPL2
 * Text Domain: bbcd
 */

if ( ! defined('WPINC') ) { die; }

/** === Core constants === */
define( 'BBCD_VERSION', '1.3' );
define( 'BBCD_PLUGIN_FILE', __FILE__ );
define( 'BBCD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBCD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BBCD_OPTION_KEY', 'bbcd_options' );
define( 'BBCD_CAPABILITY', 'manage_options' ); // who can access the settings page

/** === Options helper (defaults updated) === */
function bbcd_get_options() {
	$defaults = array(
        // Main feature toggles
		'enable_custom_dashboard' => 1,
		'template_post_id'        => 0,

        // Per-role visibility via capability gate
        // 'manage_options' (Admins), 'edit_others_posts' (Editors+),
        // 'publish_posts' (Authors+), 'edit_posts' (Contributors+), 'read' (all logged-in)
		'view_cap'                => 'edit_others_posts', // Editors+ by default

		// Optional: hide core widgets
		'hide_default_widgets'    => array(), // activity, at_a_glance, quick_draft, site_health, events

		// PUC settings (not used here directly but stored from Settings)
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

/** === Settings page bootstrap === */
require_once BBCD_PLUGIN_DIR . 'includes/class-bbcd-settings.php';
add_action( 'plugins_loaded', function () { new BBCD_Settings(); } );

/** === (Legacy) injector disable — keep class available but disabled by default ===
 * If you had an older file that always injected a fixed template into the dashboard,
 * it likely caused the “always shows ‘Dashboard’ template” issue.
 * We load it only if explicitly enabled.
 */
if ( ! defined( 'BBCD_DISABLE_LEGACY' ) ) {
	define( 'BBCD_DISABLE_LEGACY', true );
}
if ( ! BBCD_DISABLE_LEGACY && file_exists( BBCD_PLUGIN_DIR . 'classes/class-bb-custom-dashboard.php' ) ) {
	require_once BBCD_PLUGIN_DIR . 'classes/class-bb-custom-dashboard.php';
}

/** === Front-end preview route ============================================= */
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

	// Optionally hide selected core widgets
	if ( ! empty( $opts['hide_default_widgets'] ) && is_array( $opts['hide_default_widgets'] ) ) {
		add_action( 'admin_head-index.php', function() use ( $opts ) {
			$map = array(
				'activity'     => 'dashboard_activity',
				'at_a_glance'  => 'dashboard_right_now',   // legacy id
				'at_a_glance2' => 'dashboard_at_a_glance', // some installs use this id
				'quick_draft'  => 'dashboard_quick_press',
				'site_health'  => 'dashboard_site_health',
				'events'       => 'dashboard_primary',
			);
			foreach ( $opts['hide_default_widgets'] as $k ) {
				if ( $k === 'at_a_glance' ) {
					// Remove both possible IDs for At a Glance
					remove_meta_box( $map['at_a_glance'],  'dashboard', 'normal' );
					remove_meta_box( $map['at_a_glance'],  'dashboard', 'side' );
					remove_meta_box( $map['at_a_glance2'], 'dashboard', 'normal' );
					remove_meta_box( $map['at_a_glance2'], 'dashboard', 'side' );
					continue;
				}
				if ( isset( $map[ $k ] ) ) {
					remove_meta_box( $map[ $k ], 'dashboard', 'normal' );
					remove_meta_box( $map[ $k ], 'dashboard', 'side' );
				}
			}
		});
	}

	// Add the custom widget only if enabled and a template selected
	if ( empty( $opts['enable_custom_dashboard'] ) || $template_id <= 0 ) {
		return;
	}

	wp_add_dashboard_widget(
		'bbcd_custom_dashboard',
		'', // Title left blank for a cleaner look
		function () use ( $template_id, $opts ) {

			// Capability gate for who can VIEW the custom dashboard
			$required_cap = ! empty( $opts['view_cap'] ) ? $opts['view_cap'] : 'edit_others_posts';
			$user_can_view = current_user_can( $required_cap );

			// Pull template & status
			$template = get_post( $template_id );
			$status   = $template ? $template->post_status : '';

			// Draft/Private/Pending is restricted for non-editors
			$is_restricted_status = in_array( $status, array( 'draft', 'pending', 'private' ), true );

			// Top action row (only for users who can edit the template)
			if ( current_user_can( 'edit_post', $template_id ) ) {
				$admin_edit_url   = admin_url( 'post.php?post=' . $template_id . '&action=edit' );
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

			// Visibility fallback
			if ( ! $user_can_view ) {
				echo '<p>' . esc_html__( 'A custom dashboard is enabled, but you do not have permission to view it.', 'bbcd' ) . '</p>';
				return;
			}

			// Draft/private fallback for non-editors
			if ( $is_restricted_status && ! current_user_can( 'edit_post', $template_id ) ) {
				echo '<p>' . esc_html__( 'The dashboard template is not published yet. Please check back later.', 'bbcd' ) . '</p>';
				return;
			}

			// Enqueue Beaver Builder assets for fidelity (if available)
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

			// --- Robust render: try ID first, then slug, so rows/modules work too ---
			$type_meta = get_post_meta( $template_id, '_fl_builder_template_type', true ); // layout|row|module
			$slug      = get_post_field( 'post_name', $template_id );

			$attempts = array(
				sprintf( '[fl_builder_insert_layout id="%d"]', $template_id ),
				$slug ? sprintf( '[fl_builder_insert_layout slug="%s"]', esc_attr( $slug ) ) : '',
			);

			$content = '';
			foreach ( $attempts as $sc ) {
				if ( ! $sc ) continue;
				$out = do_shortcode( $sc );
				if ( ! empty( $out ) ) {
					$content = $out;
					break;
				}
			}

			if ( empty( $content ) ) {
				echo '<p>' . esc_html__( 'Selected Beaver Builder item could not be rendered. Ensure it exists and is published. If it’s a Saved Row/Module, try publishing it as a Saved Template.', 'bbcd' ) . '</p>';
				if ( current_user_can( 'manage_options' ) ) {
					echo '<p style="opacity:.7;">' . esc_html__( 'Debug:', 'bbcd' ) . ' ID=' . (int) $template_id . ', type=' . esc_html( $type_meta ?: 'unknown' ) . ', slug=' . esc_html( $slug ) . '</p>';
				}
			} else {
				echo '<div class="bbcd-widget-wrap">';
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
 * Plugin Update Checker (PUC) setup
 * ------------------------------------------------------------------------- */
require __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Auto-detect the actual plugin folder slug so you don't have to hardcode it.
$bbcd_plugin_slug = basename( dirname( __FILE__ ) );

$updateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/bluedognz/beaverbuilder-custom-dashboard',
	__FILE__,
	$bbcd_plugin_slug // must match actual plugin folder
);
$updateChecker->setBranch('main');
if ( defined('BB_CUSTOM_DASH_GH_TOKEN') && BB_CUSTOM_DASH_GH_TOKEN ) {
	$updateChecker->setAuthentication(BB_CUSTOM_DASH_GH_TOKEN);
}
// $updateChecker->getVcsApi()->enableReleaseAssets();
