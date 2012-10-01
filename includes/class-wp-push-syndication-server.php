<?php

require_once( dirname( __FILE__ ) . '/class-wp-client-factory.php' );

class WP_Push_Syndication_Server {

    public  $push_syndicate_settings;
    public  $push_syndicate_default_settings;
    public  $push_syndicate_transports;

    private $version = '2.0';

    function __construct() {

        // initialization
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );

		// custom columns
		add_filter( 'manage_edit-syn_site_columns', array( $this, 'add_new_columns' ) );
		add_action( 'manage_syn_site_posts_custom_column', array( $this, 'manage_columns' ), 10, 2);
 
        // submenus
        add_action( 'admin_menu', array( $this, 'register_syndicate_settings' ) );

        // defining sites
        add_action( 'save_post', array( $this, 'save_site_settings' ) );

        // loading necessary styles and scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts_and_styles' ) );

        // filter admin notices in custom post types
        add_filter( 'post_updated_messages', array( $this, 'push_syndicate_admin_messages' ) );

        // syndicating content
        add_action( 'add_meta_boxes', array( $this, 'add_post_metaboxes' ) );
        add_action( 'transition_post_status', array( $this, 'save_syndicate_settings' ) ); // use transition_post_status instead of save_post because the former is fired earlier which causes race conditions when a site group select and publish happen on the same load
        add_action( 'wp_trash_post', array( $this, 'delete_content' ) );

        // adding custom time interval
        add_filter( 'cron_schedules', array( $this, 'cron_add_pull_time_interval' ) );

        // firing a cron job
        add_action( 'transition_post_status', array( $this, 'pre_schedule_push_content' ) );
		add_action( 'delete_post', array( $this, 'schedule_delete_content' ) );

		$this->register_syndicate_actions();

    }

    public function init() {

        register_post_type( 'syn_site', array(
            'labels' => array(
                'name'              => __( 'Sites' ),
                'singular_name'     => __( 'Site' ),
                'add_new'           => __( 'Add Site' ),
                'add_new_item'      => __( 'Add New Site' ),
                'edit_item'         => __( 'Edit Site' ),
                'new_item'          => __( 'New Site' ),
                'view_item'         => __( 'View Site' ),
                'search_items'      => __( 'Search Sites' ),
            ),
            'description'           => __( 'Sites in the netowrk' ),
            'public'                => false,
            'show_ui'               => true,
            'publicly_queryable'    => false,
            'exclude_from_search'   => false,
            'menu_position'         => 80,
            // @TODO we need a menu icon here
            'hierarchical'          => false, // @TODO check this
            'query_var'             => true,
            'supports'              => array( 'title' ),
            'can_export'            => true,
            'register_meta_box_cb'  => array( $this, 'site_metaboxes' ),
        ));

        register_taxonomy( 'syn_sitegroup', 'syn_site', array(
                'labels' => array(
                    'name'              => __( 'Site Groups' ),
                    'singular_name'     => __( 'Site Group' ),
                    'search_items'      => __( 'Search Site Groups' ),
                    'popular_items'     => __( 'Popular Site Groups' ),
                    'all_items'         => __( 'All Site Groups' ),
                    'parent_item'       => __( 'Parent Site Group' ),
                    'parent_item_colon' => __( 'Parent Site Group' ),
                    'edit_item'         => __( 'Edit Site Group' ),
                    'update_item'       => __( 'Update Site Group' ),
                    'add_new_item'      => __( 'Add New Site Group' ),
                    'new_item_name'     => __( 'New Site Group Name' ),

                ),
                'public'                => false,
                'show_ui'               => true,
                'show_tagcloud'         => false,
                'show_in_nav_menus'     => false,
                'hierarchical'          => true,
                'rewrite'               => false,
        ));

        $this->push_syndicate_default_settings = array(
            'selected_pull_sitegroups'  => array(),
            'selected_post_types'       => array( 'post' ),
            'delete_pushed_posts'       => 'off',
            'selected_user_roles'       => array( 'administrator' ),
            'pull_time_interval'        => '3600',
            'update_pulled_posts'       => 'off',
            'client_id'                 => '',
            'client_secret'             => ''
        );

        $this->push_syndicate_settings = wp_parse_args( (array) get_option( 'push_syndicate_settings' ), $this->push_syndicate_default_settings );

    }

	public function register_syndicate_actions() {
		add_action( 'syn_push_content', array( $this, 'push_content' ) );
		add_action( 'syn_delete_content', array( $this, 'delete_content' ) );
		add_action( 'syn_pull_content', array( $this, 'pull_content' ) );
	}

	public function add_new_columns( $columns ) {
		$new_columns = array();
		$new_columns['cb'] = '<input type="checkbox" />';
		$new_columns['title'] = _x( 'Site Name', 'column name' );
		$new_columns['mode'] = _x( 'Mode', 'column name' );
		$new_columns['syn_sitegroup'] = _x( 'Groups', 'column name' );
		$new_columns['date'] = _x('Date', 'column name');
		return $new_columns;
	}

	public function manage_columns( $column_name, $id ) {
		global $wpdb;
		switch ( $column_name ) {
			case 'mode':
				$transport_mode = get_post_meta( $id, 'syn_transport_mode', true);
				echo esc_html( $transport_mode );
				break;
			case 'syn_sitegroup':
				the_terms( $id, 'syn_sitegroup', '', ', ', '' );
				break;
			default:
				break;
		}
	}
	
    public function admin_init() {

        // @TODO define more parameters
		$name_match = '#class-wp-(.+)-client\.php#';
		
		$full_path = __DIR__ . '/';
		if ( $handle = opendir( $full_path ) ) {
			while ( false !== ( $entry = readdir( $handle ) ) ) {
				if ( !preg_match( $name_match, $entry, $matches ) )
					continue;
				require_once( $full_path . $entry );
				$class_name = 'WP_' . strtoupper( $matches[1] ) . '_Client';
				if ( !class_exists( $class_name ) )
					continue;
				$client_data = call_user_func( array( $class_name, 'get_client_data' ) );
				if ( is_array( $client_data ) && !empty( $client_data ) ) {
					$this->push_syndicate_transports[$client_data['id']] = array( 'name' => $client_data['name'], 'modes' => $client_data['modes'] ); 
					}
			}
		}

		// register styles and scripts
        wp_register_style( 'syn_sites', plugins_url( 'css/sites.css', __FILE__ ), array(), $this->version  );

        // register settings
        register_setting( 'push_syndicate_settings', 'push_syndicate_settings', array( $this, 'push_syndicate_settings_validate' ) );

    }

    public function load_scripts_and_styles( $hook ) {

        global $typenow;
        if( $hook == 'edit.php' && $typenow == 'syn_site') {
            wp_enqueue_style( 'syn_sites' );
        }

    }

    public function push_syndicate_settings_validate( $raw_settings ) {

        $settings                               = array();
        $settings['client_id']                  = sanitize_text_field( $raw_settings['client_id'] );
        $settings['client_secret']              = sanitize_text_field( $raw_settings['client_secret'] );
        $settings['selected_post_types']        = !empty( $raw_settings['selected_post_types'] ) ? $raw_settings['selected_post_types'] : array() ;
        $settings['delete_pushed_posts']        = !empty( $raw_settings['delete_pushed_posts'] ) ? $raw_settings['delete_pushed_posts'] : 'off' ;
        $settings['selected_pull_sitegroups']   = !empty( $raw_settings['selected_pull_sitegroups'] ) ? $raw_settings['selected_pull_sitegroups'] : array() ;
        $settings['pull_time_interval']         = !empty( $raw_settings['pull_time_interval'] ) ? $raw_settings['pull_time_interval'] : '3600' ;
        $settings['selected_user_roles']        = !empty( $raw_settings['selected_user_roles'] ) ? $raw_settings['selected_user_roles'] : array() ;
        $settings['update_pulled_posts']        = !empty( $raw_settings['update_pulled_posts'] ) ? $raw_settings['update_pulled_posts'] : 'off' ;

        $this->pre_schedule_pull_content( $settings['selected_pull_sitegroups'] );

        return $settings;

    }

    public function register_syndicate_settings() {
        add_submenu_page( 'options-general.php', esc_html__( 'Push Syndication Settings', 'push-syndication' ), esc_html__( 'Push Syndication', 'push-syndication' ), 'manage_options', 'push-syndicate-settings', array( $this, 'display_syndicate_settings' ) );
    }

    public function display_syndicate_settings() {

        add_settings_section( 'push_syndicate_pull_sitegroups', esc_html__( 'Site Groups' , 'push-syndication' ), array( $this, 'display_pull_sitegroups_description' ), 'push_syndicate_pull_sitegroups' );
        add_settings_field( 'pull_sitegroups_selection', esc_html__( 'select sitegroups', 'push-syndication' ), array( $this, 'display_pull_sitegroups_selection' ), 'push_syndicate_pull_sitegroups', 'push_syndicate_pull_sitegroups' );

        add_settings_section( 'push_syndicate_pull_options', esc_html__( 'Pull Options' , 'push-syndication' ), array( $this, 'display_pull_options_description' ), 'push_syndicate_pull_options' );
        add_settings_field( 'pull_time_interval', esc_html__( 'specify time interval in milliseconds', 'push-syndication' ), array( $this, 'display_time_interval_selection' ), 'push_syndicate_pull_options', 'push_syndicate_pull_options' );
        add_settings_field( 'update_pulled_posts', esc_html__( 'update pulled posts', 'push-syndication' ), array( $this, 'display_update_pulled_posts_selection' ), 'push_syndicate_pull_options', 'push_syndicate_pull_options' );

        add_settings_section( 'push_syndicate_post_types', esc_html__( 'Post Types' , 'push-syndication' ), array( $this, 'display_push_post_types_description' ), 'push_syndicate_post_types' );
        add_settings_field( 'post_type_selection', esc_html__( 'select post types', 'push-syndication' ), array( $this, 'display_post_types_selection' ), 'push_syndicate_post_types', 'push_syndicate_post_types' );

        add_settings_section( 'push_syndicate_user_roles', esc_html__( 'User Roles', 'push-syndication' ), array( $this, 'display_push_user_roles_description' ), 'push_syndicate_user_roles' );
        add_settings_field( 'user_role_selection', esc_html__( 'select user roles', 'push-syndication' ), array( $this, 'display_user_roles_selection' ), 'push_syndicate_user_roles', 'push_syndicate_user_roles' );

        add_settings_section( 'delete_pushed_posts', esc_html__(' Delete Pushed Posts ', 'push-syndication' ), array( $this, 'display_delete_pushed_posts_description' ), 'delete_pushed_posts' );
        add_settings_field( 'delete_post_check', esc_html__(' delete pushed posts ', 'push-syndication' ), array( $this, 'display_delete_pushed_posts_selection' ), 'delete_pushed_posts', 'delete_pushed_posts' );

        add_settings_section( 'api_token', esc_html__(' API Token Configuration ', 'push-syndication' ), array( $this, 'display_apitoken_description' ), 'api_token' );
        add_settings_field( 'client_id', esc_html__(' Enter your client id ', 'push-syndication' ), array( $this, 'display_client_id' ), 'api_token', 'api_token' );
        add_settings_field( 'client_secret', esc_html__(' Enter your client secret ', 'push-syndication' ), array( $this, 'display_client_secret' ), 'api_token', 'api_token' );

        ?>

        <div class="wrap" xmlns="http://www.w3.org/1999/html">

            <?php screen_icon(); // @TODO custom screen icon ?>

            <h2><?php esc_html_e( 'Push Syndicate Settings', 'push-syndication' ); ?></h2>

            <form action="options.php" method="post">

                <?php settings_fields( 'push_syndicate_settings' ); ?>

                <?php do_settings_sections( 'push_syndicate_pull_sitegroups' ); ?>

                <?php do_settings_sections( 'push_syndicate_pull_options' ); ?>

                <?php submit_button( '  Pull Now ' ); ?>

                <?php do_settings_sections( 'push_syndicate_post_types' ); ?>

                <?php do_settings_sections( 'push_syndicate_user_roles' ); ?>

                <?php do_settings_sections( 'delete_pushed_posts' ); ?>

                <?php do_settings_sections( 'api_token' ); ?>

                <?php submit_button(); ?>

            </form>

            <?php $this->get_api_token() ?>

        </div>

        <?php

    }

    public function display_pull_sitegroups_description() {
        echo esc_html__( 'Select the sitegroups to pull content', 'push-syndication' );
    }

    public function display_pull_sitegroups_selection() {

        // get all sitegroups
        $sitegroups = get_terms( 'syn_sitegroup', array(
            'fields'        => 'all',
            'hide_empty'    => false,
            'orderby'       => 'name'
        ) );

        // if there are no sitegroups defined return
        if( empty( $sitegroups ) ) {
            echo '<p>' . esc_html__( 'No sitegroups defined yet. You must group your sites into sitegroups to syndicate content', 'push-syndication' ) . '</p>';
            echo '<p><a href="' . esc_url( get_admin_url() . 'edit-tags.php?taxonomy=sitegroups&post_type=site' ) . '" target="_blank" >' . esc_html__( 'Create new', 'push-syndication' ) . '</a></p>';
            return;
        }

        foreach( $sitegroups as $sitegroup ) {

            ?>

            <p>
                <label>
                    <input type="checkbox" name="push_syndicate_settings[selected_pull_sitegroups][]" value="<?php echo esc_html( $sitegroup->slug ); ?>" <?php $this->checked_array( $sitegroup->slug, $this->push_syndicate_settings['selected_pull_sitegroups'] ) ?> />
                    <?php echo esc_html( $sitegroup->name ); ?>
                </label>
                <?php echo esc_html( $sitegroup->description ); ?>
            </p>

            <?php

        }

    }

    public  function display_pull_options_description() {
        echo esc_html__( 'Configure options for pulling content', 'push-syndication' );
    }

    public function display_time_interval_selection() {
        echo '<input type="text" size="10" name="push_syndicate_settings[pull_time_interval]" value="' . esc_attr( $this->push_syndicate_settings['pull_time_interval'] ) . '"/>';
    }

    public function display_update_pulled_posts_selection() {
        // @TODO refractor this
        echo '<input type="checkbox" name="push_syndicate_settings[update_pulled_posts]" value="on" ';
        echo checked( $this->push_syndicate_settings['update_pulled_posts'], 'on' ) . ' />';
    }

    public function display_push_post_types_description() {
        echo esc_html__( 'Select the post types to add support for pushing content', 'push-syndication' );
    }

    public function display_post_types_selection() {

        // @TODO add more suitable filters
        $post_types = get_post_types( array( 'public' => true ) );

        echo '<ul>';

        foreach( $post_types as $post_type  ) {

            ?>

            <li>
                <label>
                    <input type="checkbox" name="push_syndicate_settings[selected_post_types][]" value="<?php echo esc_attr( $post_type ); ?>" <?php echo $this->checked_array( $post_type, $this->push_syndicate_settings['selected_post_types'] ); ?> />
                    <?php echo esc_html( $post_type ); ?>
                </label>
            </li>

            <?php

        }

        echo '</ul>';

    }

    public function display_push_user_roles_description() {
        echo esc_html__( 'Select the user roles to enable for pushing content', 'push-syndication' );
    }

    public function display_user_roles_selection() {

        $user_roles = $this->get_user_roles();

        echo '<ul>';

        foreach( $user_roles as $key => $value ) {

            ?>

            <li>
                <label>
                    <input type="checkbox" name="push_syndicate_settings[selected_user_roles][]" value="<?php echo esc_attr( $key ); ?>" <?php echo $this->checked_array( $key, $this->push_syndicate_settings['selected_user_roles'] ); ?>/>
                    <?php echo esc_html( $key ); ?>
                </label>
            </li>

            <?php

        }

        echo '</ul>';

    }

    public function get_user_roles() {
        global $wp_roles;

        if ( ! isset( $wp_roles ) )
            $wp_roles = new WP_Roles();

        return $wp_roles->get_names();
    }

    public function display_delete_pushed_posts_description() {
        echo esc_html__( 'Tick the box to delete all the pushed posts when the master post is deleted', 'push-syndication' );
    }

    public function display_delete_pushed_posts_selection() {
        // @TODO refractor this
        echo '<input type="checkbox" name="push_syndicate_settings[delete_pushed_posts]" value="on" ';
        echo checked( $this->push_syndicate_settings['delete_pushed_posts'], 'on' ) . ' />';
    }

    public function  display_apitoken_description() {
        // @TODO add client type information
        echo '<p>' . esc_html__( 'To syndicate content to WordPress.com you must ', 'push-syndication' ). '<a href="https://developer.wordpress.com/apps/new/">' . esc_html__( 'create a new application', 'push-syndication' ) . '</a></p>';
        echo '<p>' . esc_html__( 'Enter the Redirect URI as follows', 'push-syndication' ) . '</p>';
        echo '<p><b>' . esc_html( menu_page_url( 'push-syndicate-settings', false ) ) . '</p></b>';
    }

    public function display_client_id() {
        echo '<input type="text" size=100 name="push_syndicate_settings[client_id]" value="' . esc_attr( $this->push_syndicate_settings['client_id'] ) . '"/>';
    }

    public function display_client_secret() {
        echo '<input type="text" size=100 name="push_syndicate_settings[client_secret]" value="' . esc_attr( $this->push_syndicate_settings['client_secret'] ) . '"/>';
    }

    public function get_api_token() {

        $redirect_uri           = menu_page_url( 'push-syndicate-settings', false );
        $authorization_endpoint = 'https://public-api.wordpress.com/oauth2/authorize?client_id=' . $this->push_syndicate_settings['client_id'] . '&redirect_uri=' .  $redirect_uri . '&response_type=code';

        echo '<h3>' . esc_html__( 'Authorization ', 'push-syndication' ) . '</h3>';

        // if code is not found return or settings updated return
        if( empty( $_GET['code'] ) || !empty( $_GET[ 'settings-updated' ] ) ) {

            echo '<p>' . esc_html__( 'Click the authorize button to generate api token', 'push-syndication' ) . '</p>';

            ?>

            <input type=button class="button-primary" onClick="parent.location='<?php echo esc_url( $authorization_endpoint ); ?>'" value=" Authorize  ">

            <?php

            return;

        }

        $response = wp_remote_post( 'https://public-api.wordpress.com/oauth2/token', array(
            'sslverify' => false,
            'body'      => array (
                'client_id'     => $this->push_syndicate_settings['client_id'],
                'redirect_uri'  => $redirect_uri,
                'client_secret' => $this->push_syndicate_settings['client_secret'],
                'code'          => $_GET['code'],
                'grant_type'    => 'authorization_code'
            ),
        ) );

        $result = json_decode( $response['body'] );

        if( !empty( $result->error ) ) {

            echo '<p>' . esc_html__( 'Error retrieving API token ', 'push-syndication' ) . esc_html( $result->error_description ) . esc_html__( 'Please authorize again', 'push-syndication' ) . '</p>';

            ?>

            <input type=button class="button-primary" onClick="parent.location='<?php echo esc_url( $authorization_endpoint ); ?>'" value=" Authorize  ">

            <?php

            return;

        }

        ?>

        <table class="form-table">
            <tbody>
            <tr valign="top">
                <th scope="row">Access token</th>
                <td><?php echo esc_html( $result->access_token ); ?></td>
            </tr>
            <tr valign="top">
                <th scope="row">Blog ID</th>
                <td><?php echo esc_html( $result->blog_id ); ?></td>
            </tr>
            <tr valign="top">
                <th scope="row">Blog URL</th>
                <td><?php echo esc_html( $result->blog_url ); ?></td>
            </tr>
            </tbody>
        </table>

        <?php

        echo '<p>' . esc_html__( 'Enter the above details in relevant fields when registering a ', 'push-syndication' ). '<a href="http://wordpress.com" target="_blank">WordPress.com</a>' . esc_html__( 'site', 'push-syndication' ) . '</p>';

    }

    public function display_sitegroups_selection() {

        echo '<h3>' . esc_html__( 'Select Sitegroups', 'push-syndication' ) . '</h3>';

        $selected_sitegroups = get_option( 'syn_selected_sitegroups' );
        $selected_sitegroups = !empty( $selected_sitegroups ) ? $selected_sitegroups : array() ;

        // get all sitegroups
        $sitegroups = get_terms( 'syn_sitegroup', array(
            'fields'        => 'all',
            'hide_empty'    => false,
            'orderby'       => 'name'
        ) );

        // if there are no sitegroups defined return
        if( empty( $sitegroups ) ) {
            echo '<p>' . esc_html__( 'No sitegroups defined yet. You must group your sites into sitegroups to syndicate content', 'push-syndication' ) . '</p>';
            echo '<p><a href="' . esc_url( get_admin_url() . 'edit-tags.php?taxonomy=sitegroups&post_type=site' ) . '" target="_blank" >' . esc_html__( 'Create new', 'push-syndication' ) . '</a></p>';
            return;
        }

        foreach( $sitegroups as $sitegroup ) {

            ?>

            <p>
                <label>
                    <input type="checkbox" name="syn_selected_sitegroups[]" value="<?php echo esc_html( $sitegroup->slug ); ?>" <?php $this->checked_array( $sitegroup->slug, $selected_sitegroups ) ?> />
                    <?php echo esc_html( $sitegroup->name ); ?>
                </label>
                <?php echo esc_html( $sitegroup->description ); ?>
            </p>

            <?php

        }

    }

    public function site_metaboxes() {
        add_meta_box('sitediv', __(' Site Settings '), array( $this, 'add_site_settings_metabox' ), 'syn_site', 'normal', 'high');
        remove_meta_box('submitdiv', 'syn_site', 'side');
    }

    public function add_site_settings_metabox( $post ) {

        global $post;

        $transport_type = get_post_meta( $post->ID, 'syn_transport_type', true);
		$transport_mode = get_post_meta( $post->ID, 'syn_transport_mode', true);
        $site_enabled   = get_post_meta( $post->ID, 'syn_site_enabled', true);

        // default values
        $transport_type = !empty( $transport_type ) ? $transport_type : 'WP_XMLRPC';
        $transport_mode = !empty( $transport_mode ) ? $transport_mode : 'pull' ;
        $site_enabled   = !empty( $site_enabled ) ? $site_enabled : 'off';

        // nonce for verification when saving
        wp_nonce_field( plugin_basename( __FILE__ ), 'site_settings_noncename' );

        $this->display_transports( $transport_type, $transport_mode );

        try {
            $class = $transport_type . '_client';
            WP_Client_Factory::display_client_settings( $post, $class );
        } catch( Exception $e ) {
            echo $e;
        }

        ?>

        <p>
            <input type="checkbox" name="site_enabled" <?php echo checked( $site_enabled, 'on' ); ?>/>
            <label> Enable </label>
        </p>
        <p class="submit">
            <input type="submit" name="addsite" id="addsite" class="button-primary" value="  Add Site  "/>
        </p>
        <div class="clear"></div>

        <?php

    }

    public function display_transports( $transport_type, $mode ) {

        echo '<p>' . esc_html__( 'Select a transport type', 'push-syndication' ) . '</p>';
        echo '<form action="">';
		// TODO: add direction
        echo '<select name="transport_type" onchange="this.form.submit()">';

		$values = array();
		$max_len = 0;
        foreach( $this->push_syndicate_transports as $key => $value ) {
			if ( $key == $transport_type )
				$modes = $value['modes'];
			echo '<option value="' . esc_html( $key ) . '"' . selected( $key, $transport_type ) . '>' . esc_html( $value['name'] ) . '</option>';
		}

        echo '</select>';
		echo '<select name="transport_mode" onchange="this.form.submit()">';
		foreach( $modes as $supported_mode ) {
			echo '<option value="' . esc_html( $supported_mode ) . '"' . selected( $supported_mode, $mode ) . '>' . esc_html( $supported_mode ) . '</option>';
		}
		echo '</select>';
        echo '</form>';

    }

    public function save_site_settings() {

        global $post;

        // autosave verification
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return;

        // if our nonce isn't there, or we can't verify it return
        if( !isset( $_POST['site_settings_noncename'] ) || !wp_verify_nonce( $_POST['site_settings_noncename'], plugin_basename( __FILE__ ) ) )
            return;

        update_post_meta( $post->ID, 'syn_transport_type', $_POST['transport_type'] );
		update_post_meta( $post->ID, 'syn_transport_mode', $_POST['transport_mode'] );
		
        $site_enabled = isset( $_POST['site_enabled'] ) ? 'on' : 'off';
        $class = $_POST['transport_type'] . '_Client';

        try {
            $save = WP_Client_Factory::save_client_settings( $post->ID, $class );
            if( !$save )
                return;
            $client = WP_Client_Factory::get_client( $_POST['transport_type'], $post->ID );

            if( $client->test_connection()  ) {
                add_filter('redirect_post_location', create_function( '$location', 'return add_query_arg("message", 251, $location);' ) );
            } else {
                $site_enabled = 'off';
            }

        } catch( Exception $e ) {
            add_filter('redirect_post_location', create_function( '$location', 'return add_query_arg("message", 250, $location);' ) );
        }

        update_post_meta( $post->ID, 'syn_site_enabled', $site_enabled );

    }

    public function push_syndicate_admin_messages( $messages ) {

        // general error messages
        $messages['syn_site'][250] = __( 'Transport class not found!', 'push-syndication' );
        $messages['syn_site'][251] = __( 'Connection Successful!', 'push-syndication' );

        // xmlrpc error messages.
        $messages['syn_site'][301] = __( 'Invalid URL.', 'push-syndication' );
        $messages['syn_site'][302] = __( 'You do not have sufficient capability to perform this action.', 'push-syndication' );
        $messages['syn_site'][303] = __( 'Bad login/pass combination.', 'push-syndication' );
        $messages['syn_site'][304] = __( 'XML-RPC services are disabled on this site.', 'push-syndication' );
        $messages['syn_site'][305] = __( 'Transport error. Invalid endpoint', 'push-syndication' );
        $messages['syn_site'][306] = __( 'Something went wrong when connecting to the site.', 'push-syndication' );

        // WordPress.com REST error messages
        $messages['site'][301] = __( 'Invalid URL', 'push-syndication' );

        // RSS error messages

        return $messages;

    }

    public function add_post_metaboxes() {

        // return if no post types supports push syndication
        if( empty( $this->push_syndicate_settings['selected_post_types'] ) )
            return;

        if( !$this->current_user_can_syndicate() )
            return;

        $selected_post_types = $this->push_syndicate_settings[ 'selected_post_types' ];
        foreach( $selected_post_types as $selected_post_type ) {
            add_meta_box( 'syndicatediv', __( ' Syndicate ' ), array( $this, 'add_syndicate_metabox' ), $selected_post_type, 'side', 'high' );
            //add_meta_box( 'syndicationstatusdiv', __( ' Syndication Status ' ), array( $this, 'add_syndication_status_metabox' ), $selected_post_type, 'normal', 'high' );
        }

    }

    public function add_syndicate_metabox( ) {

        global $post;

        // nonce for verification when saving
        wp_nonce_field( plugin_basename( __FILE__ ), 'syndicate_noncename' );

        // get all sitegroups
        $sitegroups = get_terms( 'syn_sitegroup', array(
            'fields'        => 'all',
            'hide_empty'    => false,
            'orderby'       => 'name'
        ) );

        // if there are no sitegroups defined return
        if( empty( $sitegroups ) ) {
            echo '<p>' . esc_html__( 'No sitegroups defined yet. You must group your sites into sitegroups to syndicate content', 'push-syndication' ) . '</p>';
            echo '<p><a href="' . esc_url( get_admin_url() . 'edit-tags.php?taxonomy=sitegroups&post_type=site' ) . '" target="_blank" >' . esc_html__( 'Create new', 'push-syndication' ) . '</a></p>';
            return;
        }

        $selected_sitegroups = get_post_meta( $post->ID, '_syn_selected_sitegroups', true );
        $selected_sitegroups = !empty( $selected_sitegroups ) ? $selected_sitegroups : array() ;

        echo '<ul>';

        foreach( $sitegroups as $sitegroup  ) {

            ?>
            <li>
                <label>
                    <input type="checkbox" name="selected_sitegroups[]" value="<?php echo esc_html( $sitegroup->slug ); ?>" <?php $this->checked_array( $sitegroup->slug, $selected_sitegroups ) ?> />
                    <?php echo esc_html( $sitegroup->name ); ?>
                </label>
                <p> <?php echo esc_html( $sitegroup->description ); ?> </p>
            </li>
            <?php

        }

        echo '</ul>';

    }

    public function checked_array( $value, $group ) {
        if( !empty( $group ) ) {
            if( in_array( $value, $group ) ) {
                echo 'checked="checked"';
            }
        }
    }

    public function save_syndicate_settings() {

        global $post;

        // @TODO add cap check

        // autosave verification
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return;

        // if our nonce isn't there, or we can't verify it return
        if( !isset( $_POST['syndicate_noncename'] ) || !wp_verify_nonce( $_POST['syndicate_noncename'], plugin_basename( __FILE__ ) ) )
            return;

        if ( !current_user_can( 'manage_options' ) )
            return;

        $selected_sitegroups = !empty( $_POST['selected_sitegroups'] ) ? $_POST['selected_sitegroups'] : '' ;
        update_post_meta( $post->ID, '_syn_selected_sitegroups', $selected_sitegroups );

    }

    public function add_syndication_status_metabox() {
        // @TODO retrieve syndication status and display
    }

    public function pre_schedule_push_content() {

        global $post;

        // autosave verification
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return;

        // if our nonce isn't there, or we can't verify it return
        if( !isset( $_POST['syndicate_noncename'] ) || !wp_verify_nonce( $_POST['syndicate_noncename'], plugin_basename( __FILE__ ) ) )
            return;

        if( !$this->current_user_can_syndicate() )
            return;

        $sites = $this->get_sites_by_post_ID( $post->ID );

        $this->schedule_push_content( $sites );

    }

	function schedule_push_content( $sites ) {
		wp_schedule_single_event(
            time() - 1,
            'syn_push_content',
            array( $sites )
        );
	}

    // cron job function to syndicate content
    public function push_content( $sites ) {

        // if another process running on it return
        if( get_transient( 'syn_syndicate_lock' ) == 'locked' )
            return

        // set value as locked, valid for 5 mins
        set_transient( 'syn_syndicate_lock', 'locked', 60*5 );

        /** start of critical section **/

        require_once( dirname( __FILE__ ) . '/class-wp-client-factory.php' );

        $post_ID = $sites['post_ID'];

        // an array containing states of sites
        $slave_post_states = get_post_meta( $post_ID, '_syn_slave_post_states', true );
        $slave_post_states = !empty( $slave_post_states ) ? $slave_post_states : array() ;

        if( !empty( $sites['selected_sites'] ) ) {

            foreach( $sites['selected_sites'] as $site ) {

                $transport_type = get_post_meta( $site->ID, 'syn_transport_type', true);
                $client         = WP_Client_Factory::get_client( $transport_type  ,$site->ID );
                $info           = $this->get_site_info( $site->ID, $slave_post_states, $client );

                if( $info['state'] == 'new' || $info['state'] == 'new-error' ) { // states 'new' and 'new-error'

					$push_new_shortcircuit = apply_filters( 'syn_pre_push_new_post_shortcircuit', false, $post_ID, $site, $transport_type, $client, $info );
					if ( true === $push_new_shortcircuit )
						continue;
					
                    $result = $client->new_post( $post_ID );

					do_action( 'syn_post_push_new_post', $result, $post_ID, $site, $transport_type, $client, $info );
					
                    $this->validate_result_new_post( $result, $slave_post_states, $site->ID, $client );
                } else { // states 'success', 'edit-error' and 'remove-error'
					$push_edit_shortcircuit = apply_filters( 'syn_pre_push_edit_post_shortcircuit', false, $post_ID, $site, $transport_type, $client, $info );
					if ( true === $push_edit_shortcircuit )
						continue;
					
                    $result = $client->edit_post( $post_ID, $info['ext_ID'] );

					do_action( 'syn_post_push_edit_post', $result, $post_ID, $site, $transport_type, $client, $info );

                    $this->validate_result_edit_post( $result, $info, $slave_post_states, $site->ID, $client );
                }

            }

        }

        if( !empty( $sites['removed_sites'] ) ) {

            foreach( $sites['removed_sites'] as $site ) {

                $transport_type = get_post_meta( $site->ID, 'syn_transport_type', true);
                $client         = WP_Client_Factory::get_client( $transport_type  ,$site->ID );
                $info           = $this->get_site_info( $site->ID, $slave_post_states, $client );

                // if the post is not pushed we do not need to delete them
                if( $info['state'] == 'success' || $info['state'] == 'edit-error' || $info['state'] == 'remove-error' ) {

                    $result = $client->delete_post( $info['ext_ID'] );
                    if( !$result ) {
                        $slave_post_states[ 'remove-error' ][ $site->ID ] = array(
                            'error_code'    => $client->get_error_code(),
                            'error_message' => $client->get_error_message()
                        );
                    }

                }

            }

        }

        update_post_meta( $post_ID, '_syn_slave_post_states', $slave_post_states );

        /** end of critical section **/

        // release the lock.
        delete_transient( 'syn_syndicate_lock' );

    }

    public function get_sites_by_post_ID( $post_ID ) {

        $selected_sitegroups    = get_post_meta( $post_ID, '_syn_selected_sitegroups', true );
        $selected_sitegroups    = !empty( $selected_sitegroups ) ? $selected_sitegroups : array() ;
        $old_sitegroups         = get_post_meta( $post_ID, '_syn_old_sitegroups', true );
        $old_sitegroups         = !empty( $old_sitegroups ) ? $old_sitegroups : array() ;
        $removed_sitegroups     = array_diff( $old_sitegroups, $selected_sitegroups );

        // initialization
        $data = array(
            'post_ID'           => $post_ID,
            'selected_sites'    => array(),
            'removed_sites'     => array(),
        );

        if( !empty( $selected_sitegroups ) ) {

            foreach( $selected_sitegroups as $selected_sitegroup ) {

                // get all the sites in the sitegroup
                $sites = $this->get_sites_by_sitegroup( $selected_sitegroup );
                if( empty( $sites ) )
                    continue;

                foreach( $sites as $site ) {
                    $site_enabled = get_post_meta( $site->ID, 'syn_site_enabled', true);
                    if( $site_enabled == 'on' ) {
                        $data[ 'selected_sites' ][] = $site;
                    }
                }

            }

        }

        if( !empty( $removed_sitegroups ) ) {

            foreach( $removed_sitegroups as $removed_sitegroup ) {

                // get all the sites in the sitegroup
                $sites = $this->get_sites_by_sitegroup( $removed_sitegroup );
                if( empty( $sites ) )
                    continue;

                foreach( $sites as $site ) {
                    $site_enabled = get_post_meta( $site->ID, 'syn_site_enabled', true);
                    if( $site_enabled == 'on' ) {
                        $data[ 'removed_sites' ][] = $site;
                    }
                }

            }

        }

        update_post_meta( $post_ID, '_syn_old_sitegroups', $selected_sitegroups );

        return $data;

    }

    // return an array of sites as objects based on sitegroup
    public function get_sites_by_sitegroup( $sitegroup ) {

        // @TODO if sitegroup is deleted?

        $results = new WP_Query(array(
            'post_type'         => 'syn_site',
            'posts_per_page'    => -1, // retrieve all posts
            'tax_query'         => array(
                array(
                    'taxonomy'  => 'syn_sitegroup',
                    'field'     => 'slug',
                    'terms'     => $sitegroup
                )
            )
        ));

        return (array)$results->posts;

    }

    /**
     * $site_states is an array containing state of the site
     * with regard to the post the state. The states are
     *  success - the post was pushed successfully.
     *  new-error - error when creating the post.
     *  edit-error - error when editing the post.
     *  remove-error - error when removing the post in a slave site, when the sitegroup is unselected
     *  new - if the state is not found or the post is deleted in the slave site.
     */
    public function get_site_info( $site_ID, &$slave_post_states, $client ) {

        if( empty( $slave_post_states ) )
            return array( 'state' => 'new' );

        foreach( $slave_post_states as $state => $sites  ) {
            if(   array_key_exists( $site_ID, $sites )   &&   !empty( $sites[ $site_ID ]['ext_ID'] )   ) {
                if( $client->is_post_exists( $sites[ $site_ID ]['ext_ID'] ) ) {
                    $info = array(
                        'state'     => $state,
                        'ext_ID'    => $sites[ $site_ID ]['ext_ID'] );
                    unset( $slave_post_states[ $state ] [$site_ID] );
                    return $info;
                } else {
                    return array( 'state' => 'new' );
                }
            }
        }

        return array( 'state' => 'new' );

    }

    /**
     * if the result is false state transitions
     * new          -> new-error
     * new-error    -> new-error
     * remove-error -> new-error
     */
    public function validate_result_new_post( $result, &$slave_post_states, $site_ID, $client ) {

        if( $result ) {
            $slave_post_states[ 'success' ][ $site_ID ] = array(
                'ext_ID'        => (int)$client->get_response()
            );
        } else {
            $slave_post_states[ 'new-error' ][ $site_ID ] = array(
                'error_code'    => $client->get_error_code(),
                'error_message' => $client->get_error_message()
            );
        }

    }

    /**
     * if the result is false state transitions
     * edit-error   -> edit-error
     * success      -> edit-error
     */
    public function validate_result_edit_post( $result, $info, &$slave_post_states, $site_ID, $client ) {

        if( $result ) {
            $slave_post_states[ 'success' ][ $site_ID ] = array(
                'ext_ID'       => $info[ 'ext_ID' ]
            );
        } else {
            $slave_post_states[ 'edit-error' ][ $site_ID ] = array(
                'ext_ID'        => $info[ 'ext_ID' ],
                'error_code'    => $client->get_error_code(),
                'error_message' => $client->get_error_message()
            );
        }

    }

    public function pre_schedule_delete_content( $post_id ) {

        // if slave post deletion is not enabled return
        $delete_pushed_posts =  !empty( $this->push_syndicate_settings[ 'delete_pushed_posts' ] ) ? $this->push_syndicate_settings[ 'delete_pushed_posts' ] : 'off' ;
        if( $delete_pushed_posts != 'on' )
            return;

        if( !$this->current_user_can_syndicate() )
            return;

        $this->schedule_delete_content( $post_id );

    }

	public function schedule_delete_content( $post_id ) {
		wp_schedule_single_event(
            time() - 1,
            'syn_delete_content',
            array( $post_ID )
        );
	}

    public function delete_content( $post_ID ) {

        require_once( dirname( __FILE__ ) . '/class-wp-client-factory.php' );

        $delete_error_sites = get_option( 'syn_delete_error_sites' );
        $delete_error_sites = !empty( $delete_error_sites ) ? $delete_error_sites : array() ;
        $slave_posts        = $this->get_slave_posts( $post_ID );

        if( empty( $slave_posts ) )
            return;

        foreach( $slave_posts as $site_ID => $ext_ID ) {

            $site_enabled = get_post_meta( $site_ID, 'syn_site_enabled', true);

            // check whether the site is enabled
            if( $site_enabled == 'on' ) {

                $transport_type = get_post_meta( $site_ID, 'syn_transport_type', true);
                $client         = WP_Client_Factory::get_client( $transport_type , $site_ID );

                if( $client->is_post_exists( $ext_ID ) ) {
					$push_delete_shortcircuit = apply_filters( 'syn_pre_push_delete_post_shortcircuit', false, $ext_ID, $post_ID, $site_ID, $transport_type, $client );
					if ( true === $push_delete_shortcircuit )
						continue;
					
                    $result = $client->delete_post( $ext_ID );

					do_action( 'syn_post_push_delete_post', $result, $ext_ID, $post_ID, $site_ID, $transport_type, $client );
					
                    if( !$result ) {
                        $delete_error_sites[ $site_ID ] = array( $ext_ID );
                    }

                }

            }

        }

        update_option( 'syn_delete_error_sites', $delete_error_sites );
        // all post metadata will be automatically deleted including slave_post_states

    }

    // get the slave posts as $site_ID => $ext_ID
    public function get_slave_posts( $post_ID ) {

        // array containing states of sites
        $slave_post_states = get_post_meta( $post_ID, '_syn_slave_post_states', true );
        if ( empty( $slave_post_states ) )
            return;

        // array containing slave posts as $site_ID => $ext_ID
        $slave_posts = array();

        foreach( $slave_post_states as $state ) {
            foreach( $state as $site_ID => $info ) {
                if( !empty( $info[ 'ext_ID' ] ) ) {
                    $slave_posts[ $site_ID ] = $info[ 'ext_ID' ];
                }
            }
        }

        return $slave_posts;

    }

    // checking user capability
    public function current_user_can_syndicate() {

        $current_user_roles     = wp_get_current_user()->roles;
        $selected_user_roles    = $this->push_syndicate_settings['selected_user_roles'];
        $match                  = array_intersect( $current_user_roles, $selected_user_roles );

        return !empty( $match );

    }

    public function cron_add_pull_time_interval( $schedules ) {

        // Adds the custom time interval to the existing schedules.
        $schedules['pull_time_interval'] = array(
            'interval' => intval( $this->push_syndicate_settings['pull_time_interval'] ),
            'display' => __( 'Pull Time Interval', 'push-syndication' )
        );

        return $schedules;

    }

    public function pre_schedule_pull_content( $selected_sitegroups ) {

        if ( !current_user_can( 'manage_options' ) )
            return;

        if( empty( $selected_sitegroups ) )
            return;

        $sites = array();
        foreach( $selected_sitegroups as $selected_sitegroup ) {
            $sites = array_merge( $sites, $this->get_sites_by_sitegroup( $selected_sitegroup ) );
        }

		
		$this->schedule_pull_content( $sites );

    }

	public function schedule_pull_content( $sites ) {
		// to unschedule a cron we need the original arguements passed to schedule the cron
        // we are saving it as a siteoption
        $old_pull_sites = get_option( 'syn_old_pull_sites' );

        if( !empty( $old_pull_sites ) ) {
            $timestamp = wp_next_scheduled( 'syn_pull_content', array( $old_pull_sites ) );
            if( $timestamp )
                wp_unschedule_event( $timestamp, 'syn_pull_content', array( $old_pull_sites ) );
        }

		wp_schedule_event(
            time() - 1,
            'pull_time_interval',
            'syn_pull_content',
            array( $sites )
        );

        update_option( 'syn_old_pull_sites', $sites );
	}

    public function pull_content( $sites ) {

        foreach( $sites as $site ) {

            $site_enabled = get_post_meta( $site->ID, 'syn_site_enabled', true );
            if( $site_enabled != 'on' )
                continue;

            $inserted_posts = get_post_meta( $site->ID, 'syn_inserted_posts', true);
            $transport_type = get_post_meta( $site->ID, 'syn_transport_type', true);
            $client         = WP_Client_Factory::get_client( $transport_type  ,$site->ID );
            $posts          = $client->get_posts();

            if( empty( $posts ) )
                continue;

            foreach( $posts as $post ) {
                if( in_array( $post['post_guid'], $inserted_posts ) ) {
					$pull_edit_shortcircuit = apply_filters( 'syn_pre_pull_edit_post_shortcircuit', false, $post, $site, $transport_type, $client );
					if ( true === $pull_edit_shortcircuit )
						continue;
				
                    // if updation is disabled continue
                    if( $this->push_syndicate_settings['update_pulled_posts'] != 'on' )
                        continue;

                    $post['ID'] = array_search( $post['post_guid'], $inserted_posts );
                    $result = wp_update_post( $post, true );

					do_action( 'syn_post_pull_edit_post', $result, $post, $site, $transport_type, $client );
					
                } else {
					$pull_new_shortcircuit = apply_filters( 'syn_pre_pull_new_post_shortcircuit', false, $post, $site, $transport_type, $client );
					if ( true === $pull_new_shortcircuit )
						continue;
				
                    $result = wp_insert_post( $post, true );

					do_action( 'syn_post_pull_new_post', $result, $post, $site, $transport_type, $client );
					
					if( !is_wp_error( $result ) )
                        $inserted_posts[ $result ] = $post['post_guid'];

                }

            }

            update_post_meta( $site->ID, 'syn_inserted_posts', $inserted_posts );

        }

    }

}
