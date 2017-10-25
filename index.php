<?php
/**
* Plugin Name: OC Access
* Plugin URI: http://mypluginuri.com/
* Description: A brief description about your plugin.
* Version: 1.0 or whatever version of the plugin (pretty self explanatory)
* Author: Plugin Author's Name
* Author URI: Author's website
* License: A "Slug" license name e.g. GPL12
*/
use Sabre\DAV\Client;


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

