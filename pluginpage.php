<?php
use Sabre\DAV\Client;

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
        include 'vendor/autoload.php';

        // Load options
        $this->options['baseUri'] = get_option('ocBaseUri'); // URL to owncloud instance
        $this->options['urlParsed'] = parse_url($this->options['baseUri']);
        $this->options['webdavSlug'] = 'remote.php/webdav/';
        $this->options['syncPath'] = get_option('ocsyncPath');
        $this->options['userName'] = get_option('ocUserName');
        $this->options['syncbothways'] = get_option('ocsyncbothways');

        $this->options['password'] = get_option('ocPassword');
        $this->options['depth'] = 1;

        $settings = array(
            'baseUri' => $this->options['baseUri'] . $this->options['webdavSlug'],
            'userName' => $this->options['userName'],
            'password' => $this->options['password']
        );

        $this->client = new Client($settings);

        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'page_enqueue' ));

        add_filter( 'attachment_fields_to_edit', array($this, 'add_custom_attachment_fields'), 10, 2 );
        add_filter( 'attachment_fields_to_save', array($this, 'save_custom_attachment_fields'), 10, 2 );

        if ($this->options['syncbothways'] === '1') {
            add_filter( 'add_attachment', array($this, 'sync_to_oc') );
            add_action( 'delete_attachment', array($this, 'delete_from_oc') );
        }

        //AJAX Functions
        add_action( 'wp_ajax_get_folder_list', array($this, 'AJAX_get_folder_list') );
        add_action( 'wp_ajax_get_files', array( $this, 'AJAX_sync' ) );
        add_action( 'wp_ajax_empty_media_pool', array( $this, 'AJAX_empty_media_pool' ));
        add_action( 'wp_ajax_test_connection', array( $this, 'AJAX_test_connection' ));
    }

    /**
     * Add options page to WP Menu
     */
    public function add_plugin_page()
    {
        add_options_page(
            'ownCloud Access',
            'ownCloud Access',
            'administrator',
            __FILE__,
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * build plugin options page
     */
    public function create_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
            if ( ! isset( $_POST['_oc_nonce'] ) || ! wp_verify_nonce( $_POST['_oc_nonce'], 'oc_settings_nonce' ) ) {
                wp_die( 'Cheating, Huh?' );
            }

            if (isset($_POST['oc_settings_submitted']) && $_POST['oc_settings_submitted'] === '1') {
                if (isset($_POST['ocBaseUri'])) {
                    if(substr($_POST['ocBaseUri'], -1) !== '/')
                        $_POST['ocBaseUri'] = $_POST['ocBaseUri'] . '/';

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

                if (isset($_POST['ocsyncPath'])) {
                    $_POST['ocsyncPath'] = str_replace($this->options['urlParsed']['path'] . $this->options['webdavSlug'],'', $_POST['ocsyncPath']);

                    $_POST['ocsyncPath'] = rtrim($_POST['ocsyncPath'], '/');

                    update_option('ocsyncPath', $_POST['ocsyncPath']);
                    $this->options['syncPath'] = $_POST['ocsyncPath'];
                }

                if (isset($_POST['ocsyncbothways'])) {
                    update_option('ocsyncbothways', $_POST['ocsyncbothways']);
                    $this->options['syncbothways'] = $_POST['ocsyncbothways'];
                } else {
                    update_option('ocsyncbothways', 0);
                    $this->options['syncbothways'] = 0;
                }
            }
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
            </div>
            <form method="POST">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="ocBaseUri">ownCloud URL</label>
                            </th>
                            <td><input name="ocBaseUri" type="text" id="ocBaseUri" value="<?php echo $this->options['baseUri']; ?>" class="regular-text">
                                <p class="description">URL to your ownCloud instance, e.g. https://owncloud.example.com</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="ocUserName">ocUsername</label></th>
                            <td><input name="ocUserName" type="text" id="ocUserName" value="<?php echo $this->options['userName']; ?>" class="regular-text">
                                <p class="description">Username as set in "App-Passwords"</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="ocPassword">ocPassword</label></th>
                            <td><input name="ocPassword" type="text" id="ocPassword" value="<?php echo $this->options['password']; ?>" class="regular-text">
                                <p class="description">Password as set in "App-Passwords"</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">&nbsp;</th>
                            <td>
                                <button class="test-connection button button-small"> <i class="fa fa-cloud"></i> Test Connection</button>
                                <span class="test-result"></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="ocsyncPath">ocsyncPath</label></th>
                            <td>
                                <input name="ocsyncPath" type="text" id="ocsyncPath" value="<?php echo $this->options['syncPath']; ?>" class="regular-text" readonly>
                                <div class="folder-list">
                                    <i class="fa fa-spin fa-cog connection-icon"></i>
                                </div>        
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="ocsyncbothways">Sync both ways</label></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><span>Mitgliedschaft</span></legend><label for="ocsyncbothways">
                                    <input name="ocsyncbothways" type="checkbox" id="ocsyncbothways" value="1" <?php if ($this->options['syncbothways'] === '1') echo 'checked="checked"'; ?>>
                                    If checked, the plugin syncs files uploaded in wordpress back to your owncloud instance.</label>
                                </fieldset>
                                <p class="description">Note: This means also that files get deleted in your ownCloud if deleted in Wordpress.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"></th>
                            <td>
                                <?php echo wp_nonce_field( 'oc_settings_nonce', '_oc_nonce' ); ?>
                                <input type="hidden" name="oc_settings_submitted" value="1" />
                                <button type="submit" value="Save" class="save button button-success">Save</button>
                                <button type="button" class="runner button"><i class="fa fa-repeat"></i> Run sync</button>
                                <button type="button" class="empty button button-danger"><i class="fa fa-trash"></i> Empty media pool</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
            <br>

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
    * insert scripts and styles
    */
    public function page_enqueue() {
        wp_deregister_script('jquery');
        wp_enqueue_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js', array(), '3.2.1');
        wp_enqueue_script('jsTree', 'https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/jstree.min.js', array('jquery'), '3.2.1');
        wp_enqueue_script('my_custom_script', plugin_dir_url(__FILE__) . '/js/custom-script.js', array('jquery'), 1, true);

        wp_enqueue_style( 'jsTree', 'https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/themes/default/style.min.css' );
        wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css' );

        wp_enqueue_style( 'style', plugin_dir_url(__FILE__) . '/css/styles.css' );
    }

    /**
     * Add custom fields for attachments
     */
    public function add_custom_attachment_fields( $form_fields, $post ) {
        $form_fields['oc_syncdate'] = array(
            'label' => 'ownCloud sync',
            'input' => 'html',
            'html' => get_post_meta( $post->ID, 'oc_syncdate', true )
        );

        $form_fields['oc_etag'] = array(
            'input' => 'hidden',
            'value' => get_post_meta( $post->ID, 'oc_etag', true )
        );

        return $form_fields;
    }

    /**
     * Save custom fields for attachments
     */
    function save_custom_attachment_fields( $post, $attachment ) {
        // currently there are no fields the user can edit 
        return $post;
    }

    /**
     * Search media pool and get attachment by title
     * @param string $title filename without file extension
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

    /**
     * Inserts a file into the WP filesystem and DB
     * @param array $file contains file information
                    [name] => <filename>
                    [etag] => <etag>
                    [lastmodified] => Tue, 21 Nov 2017 15:22:08 GMT
                    [type] => file
                    [contenttype] => <mime type>
    */
    public function insertFile($file) {
        $response = $this->client->request('GET', $this->options['syncPath'] . '/' . $file['name']);

        $upload_file = wp_upload_bits($file['name'], null, $response['body']);

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
                update_post_meta( $attachment_id, 'oc_etag', $file['etag']);

                wp_update_attachment_metadata( $attachment_id,  $attachment_data );
            }
        } else {
            echo 'ERROR';
            print_r($upload_file);
            echo '----------';
        }
    }

    

    /**
     * Scans directory for subfolders
     *
     * @param array $folders parent folder array
     * @param string $path relative parent folder path
     */
    public function scanFolder($folders, $path) {
        $response = $this->client->propfind($path, array(
            '{DAV:}resourcetype',
        ), 1);

        foreach ($response as $uri => $props) {
            $title = str_replace($path, '', $uri);

            if($props['{DAV:}resourcetype'] !== null) {
                $folder = array(
                        'text' => $title,
                        'data' => $uri,
                        'a_attr' => array(
                            'data-path' => $uri
                        ),
                        'path' => $uri,
                        'children' => array(),
                    );

                if ($folder['path'] != $path) {
                    $folder['children'] = $this->scanFolder($folder['children'], $folder['path']);
                    array_push($folders, $folder);
                }
            }
        }
        return $folders;
    }

    /**
     * Syncs files uploaded in wordpress to owncloud
     *
     * @param int $attachmentId ID of new added file
     */
    public function sync_to_oc ($attachmentId) {

        $fileUrl = wp_get_attachment_url($attachmentId);
        $filePath = get_attached_file($attachmentId);

        $ofile = fopen($filePath, "r");
        $rfile = fread($ofile, filesize($filePath));
        $filename = basename($fileUrl);

        $response = $this->client->request('PUT', $this->options['syncPath'] . '/' . $filename, $rfile);

        update_post_meta( $attachmentId, 'oc_etag', $response['headers']['etag'] );
        update_post_meta( $attachmentId, 'oc_syncdate', date('d.m.Y H:i:s') );
    }

    /**
     * Deletes a file from ownCloud if deleted in WP
     *
     * @param int $postId ID of element to delete
     */
    public function delete_from_oc($postId) {
        if ('attachment' === get_post_type($postId)) {
            $filename = basename ( get_attached_file( $postId ) );
            $response = $this->client->request('DELETE', $this->options['syncPath'] . '/' . $filename);
        }
    }

    /**
     * Tests the webdav connection for any given credentials
     * @param string $baseUri the ownCloud URL, e.g. http://example.com/myowncloud
     * @param string $userName user name as set in ownCloud -> Security -> app passwords
     * @param string $password password as set in ownCloud -> Security -> app passwords
    */
    private function test_connection($baseUri, $userName, $password) {
        if(substr($baseUri, -1) !== '/')
            $baseUri = $baseUri . '/';

        $settings = array(
            'baseUri' => $baseUri . $this->options['webdavSlug'],
            'userName' => $userName,
            'password' => $password,
            'depth' => 1
        );

        $client = new Client($settings);

        try {
            $response = $client->request('GET');

            if ($response['statusCode'] > 400) {
                switch($response['statusCode']) {
                    case 401:
                        return array('status' => 'error', 'message' => 'Username or password was incorrect');
                        break;

                    case 404:
                        return array('status' => 'error', 'message' => 'ownCloud and/or webDav service not reachable. Please check your ownCloud URL');
                        break;

                    default:
                        return array('status' => 'error', 'message' => 'There was an unknown error. <br />' . nl2br(print_r($response, true)));
                        break;
                }
            } else {
                return array('status' => 'success', 'message' => 'Connection could be successfully established.');
            }
        } catch (Exception $e) {
            return array('status' => 'error', 'message' => 'There was an unknown error.');
        }
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
        wp_die(); // this is required to terminate immediately and return a proper response
    }

    public function AJAX_test_connection() {

        echo json_encode($this->test_connection($_POST['credentials']['baseUri'], $_POST['credentials']['userName'], $_POST['credentials']['password']));

        wp_die();
    }

    public function AJAX_get_folder_list() {
        $list = array();
        $test = $this->test_connection($this->options['baseUri'], $this->options['userName'], $this->options['password']);

        if($test['status'] === 'success') {

            $list = $this->scanFolder(array(), $this->options['urlParsed']['path'] . $this->options['webdavSlug']);    
        }
        echo json_encode($list);
        wp_die();
    }

    public function AJAX_sync() {
        $file_list = $this->client->propfind($this->options['syncPath'], array(
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
                'name' => str_replace('/', '', strrchr($uri, '/')),
                'etag' => str_replace('"', '', $props['{DAV:}getetag']),
                'lastmodified' => $props['{DAV:}getlastmodified'],
                'type' => $type,
                'contenttype' => $props['{DAV:}getcontenttype']
            );

            if($props['{DAV:}resourcetype'] === null) {
                $file['type'] = 'file';

                $existingFile = $this->get_attachment_by_title(preg_replace('/\\.[^.\\s]{3,4}$/', '', $file['name'])); //remove file extension

                if($existingFile !== false) {
                    //save only updated files
                    if(get_post_meta($existingFile->ID, 'oc_etag', true) !== $file['etag']) {

                        // TODO: takeover metadata like alt tags etc. from existing file

                        wp_delete_attachment( $existingFile->ID, true );

                        $this->insertFile($file);

                        $log[] = $file['name'] . ' already existing and changed; overwriting';
                    } else {
                        $log[] = $file['name'] . ' already existing and not changed; do nothing';
                    }
                } else {
                    $this->insertFile($file);
                    $log[] = $file['name'] . ' is new; inserting';
                }

            } else {
                $type = 'directory';
            }
        }

        echo json_encode(array('log' => $log));

        wp_die(); // this is required to terminate immediately and return a proper response
    }
}
