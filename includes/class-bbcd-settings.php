<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BBCD_Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_menu() {
		add_menu_page(
			__( 'BB Dashboard', 'bbcd' ),
			__( 'BB Dashboard', 'bbcd' ),
			BBCD_CAPABILITY,
			'bbcd-settings',
			array( $this, 'render_page' ),
			'dashicons-screenoptions',
			59
		);
	}

	public function register_settings() {
		register_setting(
			'bbcd_settings_group',
			BBCD_OPTION_KEY,
			array( $this, 'sanitize' )
		);

		add_settings_section(
			'bbcd_main_section',
			__( 'Dashboard Settings', 'bbcd' ),
			function() {
				echo '<p>' . esc_html__( 'Choose who can view the custom dashboard and which Beaver Builder template/row to show.', 'bbcd' ) . '</p>';
			},
			'bbcd-settings'
		);

		add_settings_field(
			'enable_custom_dashboard',
			__( 'Enable Custom Dashboard', 'bbcd' ),
			array( $this, 'field_enable_custom_dashboard' ),
			'bbcd-settings',
			'bbcd_main_section'
		);

		add_settings_field(
			'view_cap',
			__( 'Visible To', 'bbcd' ),
			array( $this, 'field_view_cap' ),
			'bbcd-settings',
			'bbcd_main_section'
		);

		add_settings_field(
			'template_post_id',
			__( 'Template / Saved Row', 'bbcd' ),
			array( $this, 'field_template_picker' ),
			'bbcd-settings',
			'bbcd_main_section'
		);

		add_settings_field(
			'hide_default_widgets',
			__( 'Hide Default Dashboard Widgets', 'bbcd' ),
			array( $this, 'field_hide_default_widgets' ),
			'bbcd-settings',
			'bbcd_main_section'
		);

		add_settings_field(
			'enable_auto_updates',
			__( 'Enable Auto-Updates (PUC)', 'bbcd' ),
			array( $this, 'field_enable_auto_updates' ),
			'bbcd-settings',
			'bbcd_main_section'
		);

		add_settings_field(
			'github_repo',
			__( 'GitHub Repo (owner/repo)', 'bbcd' ),
			array( $this, 'field_github_repo' ),
			'bbcd-settings',
			'bbcd_main_section'
		);
	}

	public function sanitize( $input ) {
		$clean = array();

		$clean['enable_custom_dashboard'] = ! empty( $input['enable_custom_dashboard'] ) ? 1 : 0;

		$allowed_caps = array(
			'manage_options',
			'edit_others_posts',
			'publish_posts',
			'edit_posts',
			'read',
		);
		$clean['view_cap'] = 'edit_others_posts';
		if ( ! empty( $input['view_cap'] ) && in_array( $input['view_cap'], $allowed_caps, true ) ) {
			$clean['view_cap'] = $input['view_cap'];
		}

		$clean['template_post_id'] = isset( $input['template_post_id'] ) ? absint( $input['template_post_id'] ) : 0;

		$clean['hide_default_widgets'] = array();
		if ( ! empty( $input['hide_default_widgets'] ) && is_array( $input['hide_default_widgets'] ) ) {
			$allowed = array( 'activity', 'at_a_glance', 'quick_draft', 'site_health', 'events' );
			foreach ( $input['hide_default_widgets'] as $key ) {
				if ( in_array( $key, $allowed, true ) ) {
					$clean['hide_default_widgets'][] = $key;
				}
			}
		}

		$clean['enable_auto_updates'] = ! empty( $input['enable_auto_updates'] ) ? 1 : 0;

		$clean['github_repo'] = '';
		if ( ! empty( $input['github_repo'] ) ) {
			$clean['github_repo'] = sanitize_text_field( $input['github_repo'] );
		}

		return $clean;
	}

	/* ===== Field renderers ===== */

	public function field_enable_custom_dashboard() {
		$opts = bbcd_get_options();
		printf(
			'<label><input type="checkbox" name="%1$s[enable_custom_dashboard]" value="1" %2$s /> %3$s</label>',
			esc_attr( BBCD_OPTION_KEY ),
			checked( ! empty( $opts['enable_custom_dashboard'] ), true, false ),
			esc_html__( 'Replace the default Dashboard widgets with a Beaver Builder template widget.', 'bbcd' )
		);
		echo '<p class="description">' . esc_html__( 'Uncheck to restore the default WordPress Dashboard.', 'bbcd' ) . '</p>';
	}

	public function field_view_cap() {
		$opts   = bbcd_get_options();
		$current = ! empty( $opts['view_cap'] ) ? $opts['view_cap'] : 'edit_others_posts';

		$choices = array(
			'manage_options'     => __( 'Administrators only', 'bbcd' ),
			'edit_others_posts'  => __( 'Editors and above', 'bbcd' ),
			'publish_posts'      => __( 'Authors and above', 'bbcd' ),
			'edit_posts'         => __( 'Contributors and above', 'bbcd' ),
			'read'               => __( 'All logged-in users', 'bbcd' ),
		);

		printf( '<select name="%s[view_cap]">', esc_attr( BBCD_OPTION_KEY ) );
		foreach ( $choices as $cap => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $cap ),
				selected( $current, $cap, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Controls which roles can see the custom dashboard widget.', 'bbcd' ) . '</p>';
	}

	public function field_template_picker() {
		$opts    = bbcd_get_options();
		$current = isset( $opts['template_post_id'] ) ? absint( $opts['template_post_id'] ) : 0;

		$templates = $this->get_bb_templates_with_types();

		if ( empty( $templates ) ) {
			echo '<p>' . esc_html__( 'No Beaver Builder templates found.', 'bbcd' ) . '</p>';
			echo '<p class="description">' . esc_html__( 'Create a Saved Template (layout/row/module) in Beaver Builder and return here to select it.', 'bbcd' ) . '</p>';
			return;
		}

		printf(
			'<select name="%1$s[template_post_id]" id="bbcd-template-picker" class="regular-text">',
			esc_attr( BBCD_OPTION_KEY )
		);

		printf(
			'<option value="0"%s>%s</option>',
			selected( 0, $current, false ),
			esc_html__( '— Select a template or saved row —', 'bbcd' )
		);

		foreach ( $templates as $t ) {
			$label = $t->post_title . ' (ID ' . $t->ID . ', ' . strtoupper( $t->bb_type ) . ')';
			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $t->ID,
				selected( (int) $t->ID, $current, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		// Preview link (front-end) – only when a selection exists.
		if ( $current ) {
			$preview_url = add_query_arg(
				array(
					'bbcd_preview' => $current,
					'bbcd_nonce'   => wp_create_nonce( 'bbcd_preview_' . $current ),
				),
				home_url( '/' )
			);
			echo ' <a class="button" target="_blank" href="' . esc_url( $preview_url ) . '">' . esc_html__( 'Preview (front-end)', 'bbcd' ) . '</a>';
		}

		echo '<p class="description">' . esc_html__( 'Supports Beaver Builder saved Layouts, Rows, and Modules.', 'bbcd' ) . '</p>';
	}

	private function get_bb_templates_with_types() {
		$post_types = array();
		if ( post_type_exists( 'fl-builder-template' ) ) {
			$post_types[] = 'fl-builder-template';
		}
		if ( post_type_exists( 'fl-theme-layout' ) ) {
			$post_types[] = 'fl-theme-layout';
		}
		if ( empty( $post_types ) ) return array();

		$posts = get_posts( array(
			'post_type'        => $post_types,
			'posts_per_page'   => 200,
			'post_status'      => array( 'publish', 'private', 'draft', 'pending' ),
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => true,
		) );

		foreach ( $posts as &$p ) {
			$type = get_post_meta( $p->ID, '_fl_builder_template_type', true );
			if ( ! $type ) $type = 'layout';
			$p->bb_type = $type; // layout|row|module
		}
		return $posts;
	}

	public function field_hide_default_widgets() {
		$opts = bbcd_get_options();
		$current = isset( $opts['hide_default_widgets'] ) && is_array( $opts['hide_default_widgets'] ) ? $opts['hide_default_widgets'] : array();
		$choices = array(
			'activity'     => __( 'Activity', 'bbcd' ),
			'at_a_glance'  => __( 'At a Glance', 'bbcd' ),
			'quick_draft'  => __( 'Quick Draft', 'bbcd' ),
			'site_health'  => __( 'Site Health Status', 'bbcd' ),
			'events'       => __( 'Events & News', 'bbcd' ),
		);

		foreach ( $choices as $key => $label ) {
			printf(
				'<label style="display:block;margin:4px 0;"><input type="checkbox" name="%1$s[hide_default_widgets][]" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( BBCD_OPTION_KEY ),
				esc_attr( $key ),
				checked( in_array( $key, $current, true ), true, false ),
				esc_html( $label )
			);
		}
		echo '<p class="description">' . esc_html__( 'These core widgets will be removed when the Dashboard loads.', 'bbcd' ) . '</p>';
	}

	public function field_enable_auto_updates() {
		$opts = bbcd_get_options();
		printf(
			'<label><input type="checkbox" name="%1$s[enable_auto_updates]" value="1" %2$s /> %3$s</label>',
			esc_attr( BBCD_OPTION_KEY ),
			checked( ! empty( $opts['enable_auto_updates'] ), true, false ),
			esc_html__( 'Enable GitHub update checks via Plugin Update Checker (PUC).', 'bbcd' )
		);
	}

	public function field_github_repo() {
		$opts = bbcd_get_options();
		printf(
			'<input type="text" name="%1$s[github_repo]" value="%2$s" class="regular-text" placeholder="owner/repo" />',
			esc_attr( BBCD_OPTION_KEY ),
			isset( $opts['github_repo'] ) ? esc_attr( $opts['github_repo'] ) : ''
		);
		echo '<p class="description">' . esc_html__( 'Example: bluedognz/beaverbuilder-custom-dashboard', 'bbcd' ) . '</p>';
	}

	public function render_page() {
		if ( ! current_user_can( BBCD_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bbcd' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Beaver Builder Custom Dashboard — Settings', 'bbcd' ); ?></h1>
			<form method="post" action="options.php">
				<?php
					settings_fields( 'bbcd_settings_group' );
					do_settings_sections( 'bbcd-settings' );
					submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
