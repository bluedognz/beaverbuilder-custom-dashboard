<?php
/**
 * Plugin Name: Beaver Builder Custom Dashboard
 * Plugin URI: http://www.bluedogdiywebsites.com
 * Description: Customize the WordPress Dashboard using a Beaver Builder Template
 * Version: 0.3
 * Author: Blue Dog Digital
 * Author URI: http://www.bluedogdiywebsites.com
 * License: GPL2
 */

/* remove all default dashboard widgets */

function remove_dashboard_meta() {
        remove_meta_box( 'dashboard_incoming_links', 'dashboard', 'normal' );
				remove_meta_box( 'dashboard_welcome', 'dashboard', 'normal' );
        remove_meta_box( 'dashboard_plugins', 'dashboard', 'normal' );
        remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
        remove_meta_box( 'dashboard_secondary', 'dashboard', 'normal' );
        remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
        remove_meta_box( 'dashboard_recent_drafts', 'dashboard', 'side' );
        remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
        remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
        remove_meta_box( 'dashboard_activity', 'dashboard', 'normal');//since 3.8
}
add_action( 'admin_init', 'remove_dashboard_meta' );

/* Place custom dashboard widget in the welcome panel */

add_action( 'admin_footer', 'custom_dashboard_widget' );
function custom_dashboard_widget() {
	// Bail if not viewing the main dashboard page
	if ( get_current_screen()->base !== 'dashboard' ) {
		return;
	}
	?>

  <style type="text/css">
  #custom-id.welcome-panel
  {
  margin: 0;
  padding: 0;
  background-color: transparent;
  border: 0px;
  }
  #custom-id.welcome-panel .fl-node-content
  {
  margin-left: -5px;
  }
  #dashboard-widgets .meta-box-sortables
  {
  display: inline;
  }
  .metabox-holder .postbox-container .empty-container
  {
  border: 0;
  }
  .metabox-holder .postbox-container .empty-container:after
  {
  content: none;
  }
  </style>

	<div id="custom-id" class="welcome-panel" style="display: none;">
		<div class="welcome-panel-content">
			<?php echo do_shortcode( '[fl_builder_insert_layout slug="dashboard" type="fl-builder-template"]' ); ?>
		</div>
	</div>

	<script>
		jQuery(document).ready(function($) {
			$('#welcome-panel').after($('#custom-id').show());
		});
	</script>

<?php }
