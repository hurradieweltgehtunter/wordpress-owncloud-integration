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

function get_attachment_by_title( $title ) {
    $args = array(
        'post_type' => 'attachment',
        'name' => sanitize_title($title),
        'posts_per_page' => 1,
        'post_status' => 'inherit',
    );
    $header = get_posts( $args );

    if(count($header) > 0)
        return $header[0];
    else
        return false;
}

function insertFile($file, $filestream) {
    echo 'inserting file ' . $file['name']  . "\n";
    print_r($file);
    $upload_file = wp_upload_bits($file['name'], null, $filestream);

    if (!$upload_file['error']) {
        $wp_filetype = wp_check_filetype($file['name'], null );

        $attachment = array(
            'post_mime_type' => $file['contenttype'],
            'post_parent' => 0,
            'post_title' => preg_replace('/\.[^.]+$/', '', $file['name']),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attachment_id = wp_insert_attachment( $attachment, $upload_file['file'], $parent_post_id );
        if (!is_wp_error($attachment_id)) {
            require_once(ABSPATH . "wp-admin" . '/includes/image.php');
            $attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] );

            update_post_meta( $attachment_id, 'oc_syncdate', date('d.m.Y H:i:s'));
            update_post_meta( $attachment_id, 'oc-etag', $file['etag']);

            wp_update_attachment_metadata( $attachment_id,  $attachment_data );
        }
    } else {
        echo 'ERROR';
        print_r($upload_file);
        echo '----------';
    }
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


// Add custom fields to media elements
/**
 * Add Photographer Name and URL fields to media uploader
 *
 * @param $form_fields array, fields to include in attachment form
 * @param $post object, attachment record in database
 * @return $form_fields, modified form fields
 */
  
function oc_attachment_custom_field( $form_fields, $post ) {
    $form_fields['oc-syncdate'] = array(
        'label' => 'Synced from owncloud',
        'input' => 'html',
        'html' => get_post_meta( $post->ID, 'oc_syncdate', true )
    );

    $form_fields['oc-etag'] = array(
        'input' => 'text',
        'value' => get_post_meta( $post->ID, 'oc-etag', true )
    );
 
    return $form_fields;
}
 
add_filter( 'attachment_fields_to_edit', 'oc_attachment_custom_field', 10, 2 );
 
/**
 * Save values of Photographer Name and URL in media uploader
 *
 * @param $post array, the post data for database
 * @param $attachment array, attachment fields from $_POST form
 * @return $post array, modified post data
 */
 
function oc_attachment_custom_fields_save( $post, $attachment ) {
    if( isset( $attachment['oc-syncdate'] ) )
        update_post_meta( $post['ID'], 'oc_syncdate', $attachment['oc-syncdate'] );

    if( isset( $attachment['oc-etag'] ) )
        update_post_meta( $post['ID'], 'oc-etag', $attachment['oc-etag'] );
 
    return $post;
}
 
add_filter( 'attachment_fields_to_save', 'oc_attachment_custom_fields_save', 10, 2 );

function enq_custom_scripts($hook) {
    
    wp_deregister_script('jquery');
    wp_enqueue_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js', array(), '3.2.1');
    wp_enqueue_script('my_custom_script', plugin_dir_url(__FILE__) . '/js/custom-script.js', array('jquery'), 1, true);

}

add_action('admin_enqueue_scripts', 'enq_custom_scripts');

// add top level menu page
function add_menu() {
    add_menu_page(
        'OC Access',
        'OC Access Options',
        'administrator',
        __FILE__,
        'options_page_html'
    );
}

add_action('admin_menu', 'add_menu');

// Layout of plugin page
function options_page_html() {
?>
    <div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <button class="runner">Run</button>
    <button class="empty">Empty media pool</button>

    <div class="result"></div>

    </div>
    <?php
}


// define AJAX functions
add_action( 'wp_ajax_get_files', 'get_files' );

function get_files() {

    include 'vendor/autoload.php';

    $settings = array(
        'baseUri' => 'http://localhost/owncloud/remote.php/webdav/',
        'userName' => 'admin',
        'password' => 'QCMFR-MIENG-IACPL-LQIZL',
        'depth' => 1
    );

    $client = new Client($settings);


    $file_list = $client->propfind('', array(
        '{DAV:}getetag',
        '{DAV:}getlastmodified',
        '{DAV:}getetag',
        '{DAV:}getcontenttype',
        '{DAV:}resourcetype',
        '{DAV:}fileid',
        '{DAV:}permissions',
        '{DAV:}size',
        '{DAV:}getcontentlength',
        '{DAV:}tags',
        '{DAV:}favorite',
        '{DAV:}comments-unread',
        '{DAV:}owner-display-name',
        '{DAV:}share-types'
    ), 1);

    $files = [];
    $updatedFiles = [];

    foreach($file_list as $uri => $props) {
        $file = array(
            'name' => str_replace('/owncloud/remote.php/webdav/', '', $uri),
            'etag' => str_replace('"', '', $props['{DAV:}getetag']),
            'lastmodified' => $props['{DAV:}getlastmodified'],
            'type' => $type,
            'contenttype' => $props['{DAV:}getcontenttype']
        );

        if($props['{DAV:}resourcetype'] === null) {
            echo 'file ' . $file['name'] . ' is a file' . "\n";
            $file['type'] = 'file';

            $existingFile = get_attachment_by_title(preg_replace('/\\.[^.\\s]{3,4}$/', '', $file['name'])); //remove file extension

            //save only updated files

            if($existingFile !== false) {
                echo 'file ' . $file['name'] . ' exists' . "\n";

                if(get_post_meta($existingFile->ID, 'oc-etag', true) !== $file['etag']) {
                    echo 'file ' . $file['name'] . ' has different etag' . "\n";

                    // TODO: takeover metadata like alt tags etc. from existing file

                    wp_delete_attachment( $existingFile->ID, true );

                    $updatedFiles[] = $file['name'];

                    $response = $client->request('GET', $file['name']); 
                    insertFile($file, $response['body']);
                } else {
                    echo 'file ' . $file['name'] . ' has same etag; continue' . "\n";
                }
            } else {
                echo 'file ' . $file['name'] . ' is not existing; inserting' . "\n";
                $response = $client->request('GET', $file['name']); 
                insertFile($file, $response['body']);
            }

        } else {
            echo 'file ' . $file['name'] . ' is a directory' . "\n";
            $type = 'directory';
        }
        $files[] = $file;
    }

    echo json_encode(array('files' => $files, 'updated_files' => $updated_files));

    wp_die(); // this is required to terminate immediately and return a proper response
}


function empty_media_pool(  ) {

    $attachments = get_posts( array(
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'any'
    ) );
    $log = array('errors' => [], 'success' => []);

    foreach ( $attachments as $attachment ) {
        if ( false === wp_delete_attachment( $attachment->ID, true ) ) {
            $log['errors'][] = 'Could not delete ' . $attachment->ID;
        } else {
            $log['success'][] = $attachment->ID . ' successfully deleted';
        }
    }

    echo json_encode($log);
    wp_die();
}

add_action( 'wp_ajax_empty_media_pool', 'empty_media_pool' );