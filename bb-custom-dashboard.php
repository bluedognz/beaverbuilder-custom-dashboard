<?php
/**
 * Plugin Name: Beaver Builder Custom Dashboard
 * Plugin URI: http://www.bluedogdigitalmarketing.com
 * Description: Customize the WordPress Dashboard using a Beaver Builder Template
 * Version: 1.1
 * Author: Blue Dog Digital
 * Author URI: http://www.bluedogdigitalmarketing.com
 * License: GPL2
 * Text Domain: bbcd
 */

// If this file is called directly, abort.
if ( ! defined('WPINC') ) {
    die;
}

// Load the main plugin class.
require('classes/class-bb-custom-dashboard.php');

// -----------------------------------------------------------------------------
// Plugin Update Checker (PUC) setup
// -----------------------------------------------------------------------------

// 1. Include the PUC library (make sure you have a /plugin-update-checker folder).
require __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// 2. Build the update checker instance.
//    Replace "your-username" and "your-repo" with your GitHub details.
//    IMPORTANT: The slug must match the plugin folder name exactly.
$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/bluedognz/beaverbuilder-custom-dashboard', // GitHub repo
    __FILE__,                                                            // Main plugin file
    'beaverbuilder-custom-dashboard'                              // Plugin folder slug
);

// 3. (Optional) If you release from a branch other than 'main':
// $updateChecker->setBranch('main');

// 4. (Optional) If repo is private or to avoid GitHub rate limits, use a token:
if ( defined('BB_CUSTOM_DASH_GH_TOKEN') && BB_CUSTOM_DASH_GH_TOKEN ) {
    $updateChecker->setAuthentication(BB_CUSTOM_DASH_GH_TOKEN);
}

// 5. (Optional) If you attach ZIPs to Releases as assets, enable them:
// $updateChecker->getVcsApi()->enableReleaseAssets();
