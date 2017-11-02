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

require_once('pluginpage.php');

if( is_admin() )
    $my_settings_page = new PluginPage();
