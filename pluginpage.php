<?php
use Sabre\DAV\Client;
define('DEBUG', false);
class PluginPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        // add_action( 'admin_init', array( $this, 'page_init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'page_enqueue' ));

        add_filter( 'attachment_fields_to_edit', array($this, 'add_custom_attachment_fields'), 10, 2 );
        add_filter( 'attachment_fields_to_save', array($this, 'save_custom_attachment_fields'), 10, 2 );

        //AJAX Functions
        add_action( 'wp_ajax_get_folder_list', array($this, 'AJAX_get_folder_list') );
        add_action( 'wp_ajax_set_root_folder', array( $this, 'AJAX_set_root_folder' ));
        add_action( 'wp_ajax_get_files', array( $this, 'AJAX_sync' ) );
        add_action( 'wp_ajax_empty_media_pool', array( $this, 'AJAX_empty_media_pool' ));
        add_action( 'wp_ajax_test_connection', array( $this, 'AJAX_test_connection' ));

        // Load options
        $this->options['baseUri'] = get_option('ocBaseUri');
        $this->options['userName'] = get_option('ocUserName');
        $this->options['password'] = get_option('ocPassword');
        $this->options['rootPath'] = get_option('ocRootPath');
        $this->options['depth'] = 1;
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        add_menu_page(
            'OC Access',
            'OC Access Options',
            'administrator',
            __FILE__,
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        // if (count($_POST) > 0 && !wp_verify_nonce( '_wp_nonce', 'wpshout_option_page_example_action' )) {
        //     wp_die('Nonce verification failed');
        // }

        if (isset($_POST['ocBaseUri'])) {
            update_option('ocBaseUri', $_POST['ocBaseUri']);
            $this->options['baseUri'] = $_POST['ocBaseUri'];
        }

        if (isset($_POST['ocUserName'])) {
            update_option('ocUserName', $_POST['ocUserName']);
            $this->options['userName'] = $_POST['ocUserName'];
        }

        if (isset($_POST['ocPassword'])) {
            update_option('ocPassword', $_POST['ocPassword']);
            $this->options['password'] = $_POST['ocPassword'];
        }

        if (isset($_POST['ocRootPath'])) {
            update_option('ocRootPath', $_POST['ocRootPath']);
            $this->options['rootPath'] = $_POST['ocRootPath'];
        }

        ?>
        <div class="wrap">
          <div class="oc">
              <div class="oc-circle l1 s1 el1"></div>

              <div class="oc-circle l2 s3 el2"></div>
              <div class="oc-circle l2 s2 el3"></div>
              <div class="oc-circle l2 s2 el4"></div>

              <div class="oc-circle l3 s2 el5"></div>
              <div class="oc-circle l3 s2 el6"></div>

              <div class="oc-circle l4 s3 el7"></div>
              <div class="oc-circle l4 s2 el8"></div>
              <div class="oc-circle l4 s3 el9"></div>
              <div class="oc-headline">
                My Settings
              </div>
          </div>
            <form method="POST">

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ocBaseUri">ownCloud URL</label></th>
                            <td><input name="ocBaseUri" type="text" id="ocBaseUri" value="<?php echo $this->options['baseUri']; ?>" class="regular-text">
                                <p class="description">Deine ownCloud URL</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="ocUserName">ocUsername</label></th>
                            <td><input name="ocUserName" type="text" id="ocUserName" value="<?php echo $this->options['userName']; ?>" class="regular-text">
                                <p class="description">Username</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="ocPassword">ocPassword</label></th>
                            <td><input name="ocPassword" type="text" id="ocPassword" value="<?php echo $this->options['password']; ?>" class="regular-text">
                                <p class="description">Passwort</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="ocRootPath">ocRootPath</label></th>
                            <td>
                                <input name="ocRootPath" type="text" id="ocRootPath" value="<?php echo $this->options['rootPath']; ?>" class="regular-text" readonly>
                                <button class="get-folder-list button button-primary button-small">Get Folder List</button>
                                <p class="description">Root Pfad</p>
                                <div class="folder-list"></div>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">&nbsp;</th>
                            <td>
                                <button class="test-connection button button-primary button-large">Test Connection</button>

                                <div class="test-result"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php echo wp_nonce_field( 'wpshout_option_page_example_action' ); ?>
                <input type="submit" value="Save" class="button button-primary button-large">
            </form>

            <button class="runner">Run sync</button>
            <button class="empty">Empty media pool</button>
            <div class="sk-folding-cube loadanimation hidden">
              <div class="sk-cube1 sk-cube"></div>
              <div class="sk-cube2 sk-cube"></div>
              <div class="sk-cube4 sk-cube"></div>
              <div class="sk-cube3 sk-cube"></div>
            </div>
            <div class="result"></div>



        </div>
        <?php
    }

    /**
    *
    */
    public function page_enqueue() {
        wp_deregister_script('jquery');
        wp_enqueue_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js', array(), '3.2.1');
        wp_enqueue_script('jsTree', 'https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/jstree.min.js', array('jquery'), '3.2.1');
        wp_enqueue_script('my_custom_script', plugin_dir_url(__FILE__) . '/js/custom-script.js', array('jquery'), 1, true);

        wp_enqueue_style( 'jsTree', 'https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/themes/default/style.min.css' );
        wp_enqueue_style( 'style', plugin_dir_url(__FILE__) . '/css/styles.css' );
    }

    /**
     * Add custom fields for attachments
     */
    public function add_custom_attachment_fields( $form_fields, $post ) {
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

    /**
     * Save custom fields for attachments
     */
    function save_custom_attachment_fields( $post, $attachment ) {
        if( isset( $attachment['oc-syncdate'] ) )
            update_post_meta( $post['ID'], 'oc_syncdate', $attachment['oc-syncdate'] );

        if( isset( $attachment['oc-etag'] ) )
            update_post_meta( $post['ID'], 'oc-etag', $attachment['oc-etag'] );

        return $post;
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['id_number'] ) )
            $new_input['id_number'] = absint( $input['id_number'] );

        if( isset( $input['title'] ) )
            $new_input['title'] = sanitize_text_field( $input['title'] );

        return $new_input;
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function id_number_callback()
    {
        printf(
            '<input type="text" id="id_number" name="my_option_name[id_number]" value="%s" />',
            isset( $this->options['id_number'] ) ? esc_attr( $this->options['id_number']) : ''
        );
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function title_callback()
    {
        printf(
            '<input type="text" id="title" name="my_option_name[title]" value="%s" />',
            isset( $this->options['title'] ) ? esc_attr( $this->options['title']) : ''
        );
    }

    /**
     * Search media pool and get attachment by title
     */
    public function get_attachment_by_title( $title ) {
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




    public function insertFile($file, $filestream) {
        if(DEBUG) echo 'inserting file ' . $file['name']  . "\n";
        if(DEBUG) print_r($file);
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

    public function AJAX_get_folder_list() {
        include 'vendor/autoload.php';

        $folders = array(
            'name' => 'ownCloud Root',
            'path' => '/owncloud/remote.php/webdav/',
            'subs' => array(),
        );

        $settings = array(
            'baseUri' => $this->options['baseUri'] . '/remote.php/webdav',
            'userName' => $this->options['userName'],
            'password' => $this->options['password']
        );

        $client = new Client($settings);

        $folders['subs'] = $this->scanFolder($folders['subs'], $folders['path'], $client);

        echo json_encode(array('folders' => $folders));
        wp_die();
    }

    public function AJAX_set_root_folder() {
        update_option('ocRootPath', $_POST['folder']);

        echo json_encode(array('success' => true, 'rootPath' => $_POST['folder']));
        wp_die();
    }

    /**
     * Scans directory for subfolders
     *
     * @param array $folders parent folder array
     * @param string $path relative parent folder path
     * @param Sabre\DAV\Client $client WebDAV client
     */
    public function scanFolder($folders, $path, $client) {
        $response = $client->propfind($path, array(
            '{DAV:}resourcetype',
        ), 1);

        foreach ($response as $uri => $props) {
            $title = str_replace($path, '', $uri);

            if($props['{DAV:}resourcetype'] !== null) {
                $folder = array(
                    'name' => $title,
                    'path' => $uri,
                    'subs' => array(),
                );

                if ($folder['path'] != $path) {
                    $folder['subs'] = $this->scanFolder($folder['subs'], $folder['path'], $client);
                    array_push($folders, $folder);
                }
            }
        }
        return $folders;
    }

    public function AJAX_sync() {

        include 'vendor/autoload.php';
        $settings = array(
            'baseUri' => $this->options['baseUri'] . '/remote.php/webdav',
            'userName' => $this->options['userName'],
            'password' => $this->options['password']
        );

        $client = new Client($settings);

        $file_list = $client->propfind($this->options['rootPath'] | '', array(
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

        $log = array();

        foreach($file_list as $uri => $props) {
            $file = array(
                'name' => str_replace('/owncloud/remote.php/webdav/', '', $uri),
                'etag' => str_replace('"', '', $props['{DAV:}getetag']),
                'lastmodified' => $props['{DAV:}getlastmodified'],
                'type' => $type,
                'contenttype' => $props['{DAV:}getcontenttype']
            );

            if($props['{DAV:}resourcetype'] === null) {
                if(DEBUG) echo 'file ' . $file['name'] . ' is a file' . "\n";
                $file['type'] = 'file';

                $existingFile = $this->get_attachment_by_title(preg_replace('/\\.[^.\\s]{3,4}$/', '', $file['name'])); //remove file extension

                //save only updated files

                if($existingFile !== false) {
                    if(DEBUG) echo 'file ' . $file['name'] . ' exists' . "\n";

                    if(get_post_meta($existingFile->ID, 'oc-etag', true) !== $file['etag']) {
                        if(DEBUG) echo 'file ' . $file['name'] . ' has different etag' . "\n";

                        // TODO: takeover metadata like alt tags etc. from existing file

                        wp_delete_attachment( $existingFile->ID, true );

                        $response = $client->request('GET', $file['name']);
                        $this->insertFile($file, $response['body']);

                        $log[] = $file['name'] . ' already existing and changed; overwriting';
                    } else {
                        if(DEBUG) echo 'file ' . $file['name'] . ' has same etag; continue' . "\n";
                        $log[] = $file['name'] . ' already existing and not changed; do nothing';
                    }
                } else {
                    if(DEBUG) echo 'file ' . $file['name'] . ' is not existing; inserting' . "\n";
                    $response = $client->request('GET', $file['name']);
                    $this->insertFile($file, $response['body']);
                    $log[] = $file['name'] . ' is new; inserting';
                }

            } else {
                if(DEBUG) echo 'file ' . $file['name'] . ' is a directory' . "\n";
                $type = 'directory';
            }
        }

        echo json_encode(array('log' => $log));

        wp_die(); // this is required to terminate immediately and return a proper response
    }

    public function AJAX_empty_media_pool() {

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

    public function AJAX_test_connection() {

        include 'vendor/autoload.php';

        $settings = array(
            'baseUri' => $_POST['credentials']['baseUri'] . 'remote.php/webdav',
            'userName' => $_POST['credentials']['userName'],
            'password' => $_POST['credentials']['password'],
            'depth' => 1
        );

        $client = new Client($settings);

        try {
            $response = $client->request('GET');

            if ($response['statusCode'] > 400) {
                switch($response['statusCode']) {
                    case 401:
                        echo json_encode(array('status' => 'error', 'message' => 'Username or password was incorrect'));
                        break;

                    case 404:
                        echo json_encode(array('status' => 'error', 'message' => 'ownCloud and/or webDav service not reachable. Please check your ownCloud URL'));
                        break;

                    default:
                        echo json_encode(array('status' => 'error', 'message' => 'There was an unknown error. <br />' . nl2br(print_r($response, true))));
                        break;
                }
            } else {
                echo json_encode(array('status' => 'success', 'message' => 'Connection could be successfully established.'));
            }
        } catch (Exception $e) {
            print_r($e);
            echo json_encode(array('status' => 'error', 'message' => 'asd'));
        }

        wp_die();
    }
}
