<?php
/**
 * Plugin Name: WP Posts Receiver
 * Plugin URI: https://github.com/DevWael/wp-posts-receiver
 * Description: Receive posts from another WP site
 * Version: 1.0.0
 * Author: Ahmad Wael
 * Author URI: https://www.bbioon.com
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 * Text Domain: wp-posts-receiver
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_POSTS_RECEIVER_VERSION', '1.0' );
define( 'WP_POSTS_RECEIVER_PLUGIN_URL', \plugin_dir_url( __FILE__ ) );
define( 'WP_POSTS_RECEIVER_PLUGIN_DIR', \plugin_dir_path( __FILE__ ) );

require_once WP_POSTS_RECEIVER_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! function_exists( 'as_enqueue_async_action' ) ) {
	require_once WP_POSTS_RECEIVER_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

use DevWael\WpPostsReceiver\Main;

Main::get_instance()->init();