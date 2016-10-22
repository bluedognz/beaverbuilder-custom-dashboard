<?php 

// If this file is called directly, abort.
if ( ! defined('WPINC') ) {
	die;
}

add_action( 'plugins_loaded', array( BB_Custom_Dashboard::get_instance(), 'init' ), 99 );

class BB_Custom_Dashboard {
	
	protected static $instance = NULL;
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self;
        }
        return self::$instance; 
    }
	
	public function init() {
		
		// remove dashboard metaboxes
		add_action( 'wp_dashboard_setup', array( $this, 'remove_dashboard_meta' ), 99 );
		
		// enqueue layout styles and scripts in admin
		add_action( 'admin_enqueue_scripts', 'FLBuilder::register_layout_styles_scripts' );
		add_action( 'admin_enqueue_scripts', 'FLBuilder::enqueue_all_layouts_styles_scripts' );
		
		// enqueue custom styles and scripts in admin
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles_scripts' ) );
		
		// display layout
		add_action( 'admin_footer', array( $this, 'display_dashboard_layout' ) );
    }
	
	/**
	 * Remove ALL dashboard widgets
	 * 
	 */
	function remove_dashboard_meta() {
		
		global $wp_meta_boxes;
		
		$wp_meta_boxes = array( 'dashboard' => array(
			'advanced' => array(),
			'side' => array(),
			'normal' => array(),
		) );
	}
	
	/**
	 * Enqueue custom styles and scripts in admin
	 * 
	 */
	function enqueue_styles_scripts() {
		
		wp_register_style( 'bbcd-styles', plugins_url( 'assets/css/bbcd-admin.css', dirname(__FILE__) ), array(), '' );
		wp_register_script( 'bbcd-js', plugins_url( 'assets/js/bbcd-admin.js', dirname(__FILE__) ), array(), '' );
		
		wp_enqueue_style( 'bbcd-styles' );
		wp_enqueue_script( 'bbcd-js' );
	}
	
	/**
	 * Display dashboard layout
	 * 
	 */
	function display_dashboard_layout() {

		if ( get_current_screen()->base !== 'dashboard' ) {
			return;
		}

		$layout = 	'<div id="bbcd-layout" class="welcome-panel" style="display: none;">';
		$layout .= 		'<div class="welcome-panel-content">';
		$layout .= 			do_shortcode( '[fl_builder_insert_layout slug="dashboard" type="fl-builder-template"]' );
		$layout .= 		'</div>';
		$layout .= 	'</div>';

		echo $layout;
	}
}