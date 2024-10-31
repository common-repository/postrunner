<?php
/*
Plugin Name: Postrunner
Plugin URI:  http://postrunner.com/
Description: Use this plugin to receive guest posts from Postrunner.com. 
Author:      Postrunner.com
Author URI:  http://postrunner.com/
Version:     3.0.8
*/

define('PR_PLUGIN_VERSION', 3.08);
define('PR_COMM_URL', 'http://postrunner.com/pr2/comm.php');
define('PR_REVIEW_PERIOD', '7 day');

include_once dirname(__FILE__).'/class.postrunner.php';
include_once dirname(__FILE__).'/class.postrunnerclient.php';
include_once dirname(__FILE__).'/class.postrunnerserver.php';

$postrunner = new Postrunner();

register_activation_hook( __FILE__, array($postrunner, 'activate_plugin') );

add_action('trash_post',            array($postrunner, 'trash_post'));
add_action('before_delete_post',    array($postrunner, 'delete_post'));
add_action('admin_head',            array($postrunner, 'post_page_setup'));
add_action('wp_insert_post_data',   array($postrunner, 'handle_post_save'),      5,  2);
add_action('admin_notices',         array($postrunner, 'display_notices'));
add_action('init',                  array($postrunner, 'track_pageviews'));

add_filter('post_updated_notices',  array($postrunner, 'post_updated_notices'));
add_filter('post_date_column_time', array($postrunner, 'post_date_column_time'), 11, 4);
add_filter('display_post_states',   array($postrunner, 'display_post_states'),   1,  4);
add_filter('posts_join',            array($postrunner, 'posts_join'),            1,  4);
add_filter('posts_where',           array($postrunner, 'posts_where'),           1,  4);
add_filter('restrict_manage_posts', array($postrunner, 'post_page_dropdowns'),   1,  4);
add_filter('wp_dashboard_setup',    array($postrunner, 'dashboard_setup'),       1,  4);
add_filter('admin_head',            array($postrunner, 'dashboard_css'),         1,  4);

add_filter('admin_init', array($postrunner, 'settings'));
