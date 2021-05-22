<?php
/**
 * Plugin Name: Beaver Builder Custom Dashboard
 * Plugin URI: http://www.bluedogdigitalmarketing.com
 * Description: Customize the WordPress Dashboard using a Beaver Builder Template
 * Version: 0.5
 * Author: Blue Dog Digital
 * Author URI: http://www.bluedogdigitalmarketing.com
 * License: GPL2
 * Text Domain: bbcd
 */
 
// If this file is called directly, abort.
if ( ! defined('WPINC') ) {
	die;
}

require('classes/class-bb-custom-dashboard.php');
