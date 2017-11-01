<?php
/**
* Plugin Name: OC Access
* Plugin URI: https://github.com/hurradieweltgehtunter/wordpress-owncloud-integration
* Description: Connect your ownCloud to your wordpress instance and use it as media pool
* Version: 0.1 Work in progress. Not meant for use in production environments.
* Author: Florian Lenz, Yannik Bürkle, Daniel Schwarz
* License: A "Slug" license name e.g. GPL12
*/
//TODO update license

use Sabre\DAV\Client;

add_action('init', 'load_language');
function load_language() {
    load_plugin_textdomain('wordpress-owncloud-integration', FALSE, basename(dirname(__FILE__)) . '/languages/');
}


// removes the "Add media" button from posts
function disableMediaButtonsInPost(){
    remove_action( 'media_buttons', 'media_buttons' );
}
add_action('admin_head', 'disableMediaButtonsInPost');

// prevent any uploads to media pool
function prevent_upload( $file ) {
    $file['error'] = 'You cannot upload to the wordpress media pool as you are using ownCloud';
    return $file;
}
add_filter( 'wp_handle_upload_prefilter', 'prevent_upload' );



require_once('pluginpage.php');

if( is_admin() )
    $my_settings_page = new PluginPage();
