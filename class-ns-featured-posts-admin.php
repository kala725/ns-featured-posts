<?php
require_once(plugin_dir_path( __FILE__ ) . 'widgets/nsfp-featured-post-widget.php');
/**
 * NS Featured Posts
 *
 * @package   NS_Featured_Posts_Admin
 * @author    Nilambar Sharma <nilambar@outlook.com>
 * @license   GPL-2.0+
 * @link      http://nilambar.net
 * @copyright 2013 Nilambar Sharma
 */

/**
 * NS Featured Posts Admin class.
 *
 * @package NS_Featured_Posts_Admin
 * @author  Nilambar Sharma <nilambar@outlook.com>
 */
class NS_Featured_Posts_Admin
{

    /**
     * Instance of this class.
     *
     * @since    1.0.0
     *
     * @var      object
     */
    protected static $instance = null;

    /**
     * Slug of the plugin screen.
     *
     * @since    1.0.0
     *
     * @var      string
     */
    protected $plugin_screen_hook_suffix = null;

  	protected $options = array();




    /**
     * Initialize the plugin by loading admin scripts & styles and adding a
     * settings page and menu.
     *
     * @since     1.0.0
     */
    private function __construct()
    {

        /*
         * Call $plugin_slug from public plugin class.
         *
         */
        $plugin = NS_Featured_Posts::get_instance();
        $this->plugin_slug = $plugin->get_plugin_slug();
    		$this->options = $plugin->ns_featured_posts_get_options_array();


        // Load admin style sheet and JavaScript.
        // add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        // add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Add the options page and menu item.
        add_action('admin_menu', array($this, 'ns_featured_posts_add_plugin_admin_menu'));


        /*
         * Add an action link pointing to the options page.
         */
        $plugin_basename = plugin_basename(plugin_dir_path(__FILE__) . 'ns-featured-posts.php');
        add_filter('plugin_action_links_' . $plugin_basename, array($this, 'ns_featured_posts_add_action_links'));

        /*
         * Define custom functionality.
         */

        add_action( 'admin_init', array($this, 'ns_featured_posts_add_columns_head'));
        add_action( 'admin_init', array($this, 'plugin_register_settings'));
        add_action( 'admin_head', array( $this,'add_script_to_admin_head') );
        add_action( 'admin_head', array( $this,'add_style_to_admin_head') );
        add_action( 'wp_ajax_nsfeatured_posts', array( $this, 'nsfp_ajax_featured_post' ) );

        add_action( 'restrict_manage_posts', array( $this, 'nsfp_table_filtering' ) );
        add_filter( 'parse_query', array( $this, 'nsfp_query_filtering' ) );

        add_filter( 'pre_get_posts', array( $this, 'nsfp_filtering_query_for_listing' ) );

        add_action( 'widgets_init', array( $this, 'nsfp_custom_widgets' ) );

        // Metabox stuffs
        add_action( 'add_meta_boxes', array( $this, 'add_featured_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'nsfp_save_meta_box' ) );

    }

    /**
     * Return an instance of this class.
     *
     * @since     1.0.0
     *
     * @return    object    A single instance of this class.
     */
    public static function get_instance()
    {

        // If the single instance hasn't been set, set it now.
        if (null == self::$instance)
        {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Register and enqueue admin-specific style sheet.
     *
     * @since     1.0.0
     *
     * @return    null    Return early if no settings page is registered.
     */
    public function enqueue_admin_styles()
    {

        if (!isset($this->plugin_screen_hook_suffix))
        {
            return;
        }

        $screen = get_current_screen();
        if ( $this->plugin_screen_hook_suffix == $screen->id )
        {
            // wp_enqueue_style('ns-featured-posts-admin-styles', plugins_url('css/admin.css', __FILE__), array(), NS_Featured_Posts::VERSION);
        }
    }

    /**
     * Register and enqueue admin-specific JavaScript.
     *
     * @since     1.0.0
     *
     * @return    null    Return early if no settings page is registered.
     */
    public function enqueue_admin_scripts()
    {

        if (!isset($this->plugin_screen_hook_suffix))
        {
            return;
        }

        $screen = get_current_screen();
        if ($this->plugin_screen_hook_suffix == $screen->id)
        {
            // wp_enqueue_script( 'ns-featured-posts-admin-script', plugins_url('js/admin.js', __FILE__), array('jquery'), NS_Featured_Posts::VERSION);
        }
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     *
     * @since    1.0.0
     */
    public function ns_featured_posts_add_plugin_admin_menu()
    {

        /*
         * Add a settings page for this plugin to the Settings menu.
         */
        $this->plugin_screen_hook_suffix = add_options_page(
                __('NS Featured Posts Settings Page', 'ns-featured-posts'), __('NS Featured Posts', 'ns-featured-posts'), 'manage_options', 'ns-featured-posts', array($this, 'display_plugin_admin_page')
        );
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_page()
    {
        // Check that the user is allowed to update options
        if (!current_user_can('manage_options'))
        {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        include_once( 'views/admin.php' );

    }

    /**
     * Add settings action link to the plugins page.
     *
     * @since    1.0.0
     */
    public function ns_featured_posts_add_action_links($links)
    {

        return array_merge(
                array(
            'settings' => '<a href="' . admin_url('options-general.php?page=' . $this->plugin_slug) . '">' . __('Settings', 'ns-featured-posts' ) . '</a>'
                ), $links
        );
    }

    /**
     * Add columns to the listing.
     *
     * @since    1.0.0
     */
    function ns_featured_posts_add_columns_head(){
        foreach ( $this->options['nsfp_posttypes'] as $post_type => $val ) {
            add_filter('manage_edit-'.$post_type.'_columns', array( $this,'add_featured_column_heading'), 2);
            add_action('manage_'.$post_type.'_posts_custom_column', array( $this,'add_featured_column_content'), 10, 2);
        }
    }

    /**
     * Add heading in the featured column.
     *
     * @since    1.0.0
     */
    function add_featured_column_heading( $columns ){
        $columns['ns_featured_posts_col'] = __( 'Featured', 'ns-featured-posts' );
        return $columns;
    }

    /**
     * Add column content in the featured column.
     *
     * @since    1.0.0
     */
    function add_featured_column_content( $column, $id ){
        if ( $column == 'ns_featured_posts_col' ){
          $class = '';
          $ns_featured = get_post_meta( $id, '_is_ns_featured_post', true );
          $ns_featured_rank = get_post_meta( $id, '_ns_featured_post_rank', true );
          $classes = array('ns_featured_posts_icon');
          if ('yes' == $ns_featured) {
              $classes[] = 'selected';
          }
          echo  '<a id="btn-post-featured_'.$id.'" class="'.implode(' ', $classes).'"></a>
                <p><span>Post Rank: '.$ns_featured_rank.'</span></p>';
        }
    }

    /**
     * Function to handle AJAX request.
     *
     * @since    1.0.0
     */
    function nsfp_ajax_featured_post(){
        $ns_featured = $_POST['ns_featured'];
        $id = (int)$_POST['post'];
        $ns_featured_post_rank = get_post_meta( $id, '_ns_featured_post_rank', true );

        if(empty($ns_featured_post_rank))
            $ns_featured_post_rank = 1;

        if( !empty( $id ) && $ns_featured !== NULL ) {
            if ( $ns_featured == 'no' ){
                delete_post_meta( $id, "_is_ns_featured_post" );
                delete_post_meta( $id, "_ns_featured_post_rank" );
            }
            else {
                update_post_meta( $id, "_is_ns_featured_post", 'yes' );
                update_post_meta( $id, "_ns_featured_post_rank", $ns_featured_post_rank );
            }
        }
        wp_send_json_success();
    }

    /**
     * Add scripts in the admin head.
     *
     * @since    1.0.0
     */
    function add_script_to_admin_head(){
        global $pagenow;

        if ( 'edit.php' != $pagenow ) {
            return;
        }

        if (current_user_can("administrator")) {
            ?>
            <script type="text/javascript" language="javascript">
                jQuery(document).ready(function($){
                        jQuery('.ns_featured_posts_icon').click(function() {
                            var selected = 'yes';
                            if ( jQuery(this).hasClass( 'selected' ) ){
                                jQuery(this).removeClass( 'selected' );
                                selected = 'no';
                            } else { jQuery(this).addClass( 'selected' ); }
                            // get id
                            var tempID = jQuery(this).attr( 'id' );
                                tempID = tempID.split( '_' );
                            jQuery.post( ajaxurl, 'action=nsfeatured_posts&post='+tempID[1]+'&ns_featured='+selected );

                        });
                    });

            </script>
            <?php
        }
    }

    /**
     * Add styles in the admin head.
     *
     * @since    1.0.0
     */
    function add_style_to_admin_head(){

        global $pagenow;

        if ( 'edit.php' != $pagenow ) {
            return;
        }

        $img_url = plugins_url( 'images/featured.png' , __FILE__ );
        ?>
        <style>
            #ns_featured_posts_col, .column-ns_featured_posts_col{
                width:100px; text-align: center !important;
            }
            .ns_featured_posts_icon{
                display:block; height:24px; width:24px; margin:8px auto 0 auto; border:none;
                background: transparent url(<?php echo esc_url( $img_url ); ?>) 0 0 no-repeat; cursor:pointer;
            }
            .ns_featured_posts_icon.selected, .ns_featured_posts_icon:active{
                background-position:0 -24px;
            }
        </style>
        <?php
    }

    /**
     * Add meta box in posts.
     *
     * @since    1.1
     */
    function add_featured_meta_boxes(){

      global $typenow;
      $allowed = array();
      foreach ( $this->options['nsfp_posttypes'] as $post_type => $val ) {
          $allowed[] = $post_type;
      }
      if ( ! in_array($typenow,  $allowed )  ) {
          return;
      }
      $screens = $allowed;
      foreach ( $screens as $screen ) {
        add_meta_box(
          'nsfp_meta_box_featured',
          __( 'Featured', 'ns-featured-posts' ),
          array( $this, 'nsfp_meta_box_featured_callback' ),
          $screen,
          'side'
        );
      }

    }

    /**
     * Featured meta box callback.
     *
     * @since    1.0.0
     */
    function nsfp_meta_box_featured_callback( $post ){

      $is_ns_featured_post = get_post_meta( $post->ID, '_is_ns_featured_post', true );
      $ns_featured_post_rank = get_post_meta( $post->ID, '_ns_featured_post_rank', true );
      wp_nonce_field( plugin_basename( __FILE__ ), 'nsfp_featured_metabox_nonce' );
      ?>
      <p>
      <input type="hidden" name="nsfp_settings[make_this_featured]" value="0" />
      <input type="checkbox" name="nsfp_settings[make_this_featured]" value="yes" <?php checked( $is_ns_featured_post, 'yes', true); ?> />
      <span class="small"><?php _e( 'Check this to make this post featured.', 'wen-logo-slider' ); ?></span>
      </p>
      <p>
        <span class="small"><?php _e( 'You can configure the post rank here.', 'wen-logo-slider' ); ?></span>
        <input type="number" name="nsfp_settings[make_this_featured_rank]" value="<?php echo $ns_featured_post_rank ?>" max="100" min="1" />
      </p>
      <?php

    }

    function nsfp_save_meta_box( $post_id ){

      $allowed = array();
      foreach ( $this->options['nsfp_posttypes'] as $post_type => $val ) {
        $allowed[] = $post_type;
      }
      if ( ! in_array( get_post_type( $post_id ),  $allowed )  ) {
        return $post_id;
      }

      // Bail if we're doing an auto save
      if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

      // if our nonce isn't there, or we can't verify it, bail
      if ( ! isset( $_POST['nsfp_featured_metabox_nonce'] ) || ! wp_verify_nonce( $_POST['nsfp_featured_metabox_nonce'], plugin_basename( __FILE__ ) ) )
          return $post_id;

      // if our current user can't edit this post, bail
      if( ! current_user_can( 'edit_post' , $post_id ) )
        return $post_id;

      $featured_value = '';
      if ( isset( $_POST['nsfp_settings']['make_this_featured'] ) && 'yes' == $_POST['nsfp_settings']['make_this_featured'] ) {
        $featured_value = 'yes';
        }

    $ns_featured_post_rank = $_POST['nsfp_settings']['make_this_featured_rank'];
    if(empty($ns_featured_post_rank))
        $ns_featured_post_rank = 1;

      if ( 'yes' == $featured_value ) {
        update_post_meta( $post_id, '_is_ns_featured_post', $featured_value );
        update_post_meta( $post_id, '_ns_featured_post_rank', $ns_featured_post_rank );
      }
      else{
        delete_post_meta( $post_id, '_is_ns_featured_post' );
        delete_post_meta( $post_id, '_ns_featured_post_rank' );
      }
      return $post_id;

    }



    /**
     * Filtering dropdown in the post listing.
     *
     * @since    1.0.0
     */
    function nsfp_table_filtering(){
        global $wpdb, $typenow ;
        $allowed = array();
        foreach ( $this->options['nsfp_posttypes'] as $post_type => $val ) {
            $allowed[]= $post_type;
        }
        if ( ! in_array($typenow,  $allowed )  ) {
            return;
        }
        echo '<select name="filter-ns-featured-posts" id="filter-ns-featured-posts">';
        echo '<option value="" >'. __( 'Show all', 'ns-featured-posts' ) .'</option>';
        echo '<option value="yes" '.selected( $_GET['filter-ns-featured-posts'], 'yes', false ) .'>'. __( 'Featured', 'ns-featured-posts' ) .'</option>';
        echo '<option value="no" '.selected( $_GET['filter-ns-featured-posts'], 'no', false ) .'>'. __( 'Not Featured', 'ns-featured-posts' ) .'</option>';
        echo '</select>';
    }

    /**
     * Query filtering in the post listing.
     *
     * @since    1.0.0
     */
    function nsfp_query_filtering($query){

        global $pagenow;
        $qv = &$query->query_vars;
        if ( is_admin() && $pagenow == 'edit.php'){

            if ( ! isset( $qv['meta_query'] ) ) {
              $qv['meta_query'] = array();
            }

            if( !empty( $_GET['filter-ns-featured-posts'] ) ) {

                if ('yes' == $_GET['filter-ns-featured-posts'] ) {
                    $qv['meta_query'][] = array(
                       'key' => '_is_ns_featured_post',
                       'compare' => '=',
                       'value' => 'yes',
                    );
                } // end if yes

                if ('no' == $_GET['filter-ns-featured-posts'] ) {
                    $qv['meta_query'][] = array(
                       'key' => '_is_ns_featured_post',
                       'compare' => 'NOT EXISTS',
                       'value' => '',
                    );
                } // end if no

            } // end if not empty

            // for filter link
            if ( isset($_GET['post_status']) && 'nsfp' == $_GET['post_status']  ) {
                if ( isset($_GET['featured']) && 'yes' == $_GET['featured']  ) {

                    $qv['meta_query'][] = array(
                       'key' => '_is_ns_featured_post',
                       'compare' => '=',
                       'value' => 'yes',
                    );

                }
            }

        } // end if

    }

    /**
     * Adding filtering link
     */
    function nsfp_filtering_query_for_listing( $wp_query ){

        if( is_admin()) {
            $allowed_posttypes = array();
            foreach ( $this->options['nsfp_posttypes'] as $post_type => $val ) {
                $allowed_posttypes[]= $post_type;
            }
            if ( ! empty( $allowed_posttypes ) ) {
                foreach ( $allowed_posttypes as $val ) {
                    add_filter( 'views_edit-' . $val, array( $this,
                        'nsfp_add_views_link'
                    ));
                }
            }
        }
    }

    /**
     * Adding views link
     */
    function nsfp_add_views_link( $views ){

        $post_type = ( (isset($_GET['post_type']) && $_GET['post_type'] != "" ) ? $_GET['post_type'] : 'post');
        $count = $this->get_total_featured_count($post_type);
        $class = ( isset( $_GET['featured'] ) &&  $_GET['featured'] == 'yes' )  ? "current" : '';
        $args = array(
            'post_type'   => $post_type,
            'post_status' => 'nsfp',
            'featured'    => 'yes',
            );
        $url = esc_url( add_query_arg( $args,  admin_url('edit.php') ) );
        $views['featured'] = '<a href="' . $url . '" class="' . $class . '" >'
            .__('Featured','ns-featured-posts')
            .'<span class="count">'
            . ' ('.$count.') '
            .'</span>'
            .'</a>';

        return $views;
    }

    /**
     * Get total featured count
     */
    function get_total_featured_count( $post_type ){
        $args = array(
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'meta_key'       => '_is_ns_featured_post',
            'meta_value'     => 'yes',
        );
        $postlist = get_posts( $args );
        return count($postlist);
    }

    /**
     * NSFP Widgets
     */
    function nsfp_custom_widgets(){
        register_widget( 'NSFP_Featured_Post_Widget' );
    }

    /**
     * Register plugin settings
     */
    public function plugin_register_settings()
    {
      register_setting('nsfp-plugin-options-group', 'nsfp_plugin_options', array( $this, 'ns_featured_posts_plugin_options_validate') );

  		add_settings_section('main_settings', __( 'NS Featured Posts Settings', 'ns-featured-posts' ) , array($this, 'ns_featured_posts_plugin_section_text_callback'), 'ns-featured-posts-main');

  		add_settings_field('nsfp_posttypes', __( 'Enabled Featured for', 'ns-featured-posts' ), array($this, 'nsfp_posttypes_callback'), 'ns-featured-posts-main', 'main_settings');


    }
	// validate our options
	function ns_featured_posts_plugin_options_validate($input) {

    if ( ! isset( $input['nsfp_posttypes'] ) ) {
      $input['nsfp_posttypes'] = array();
    }
		return $input;
	}

	function ns_featured_posts_plugin_section_text_callback() {
    	//echo '<p>'.__('Change your NS Featured Posts settings.', 'ns-featured-posts' ).'</p>';
	}

	function nsfp_posttypes_callback() {
		?>
		<p>
			<input type="checkbox" name="nsfp_plugin_options[nsfp_posttypes][post]" value="1"
			<?php checked(isset($this -> options['nsfp_posttypes']['post']) && 1 == $this -> options['nsfp_posttypes']['post']); ?> /><?php _e("Post",  'ns-featured-posts' ); ?>
		</p>
		<p>
			<input type="checkbox" name="nsfp_plugin_options[nsfp_posttypes][page]" value="1"
			<?php checked(isset($this -> options['nsfp_posttypes']['page']) && 1 == $this -> options['nsfp_posttypes']['page']); ?> /><?php _e("Page",  'ns-featured-posts' ); ?>
		</p>
		<?php
		$args = array(
			'public' => true,
			'_builtin' => false
		);
		$post_types_custom = get_post_types( $args, 'objects' );

		if (!empty($post_types_custom)){
			foreach ($post_types_custom as $key => $ptype){
                $name = $ptype->labels->{'name'};
			?>
            <p>
                <input type="checkbox" name="nsfp_plugin_options[nsfp_posttypes][<?php echo $key; ?>]" value="1"
                <?php checked( isset($this -> options['nsfp_posttypes'][$key]) && 1 == $this -> options['nsfp_posttypes'][$key]); ?> /><?php echo $name; ?>
            </p>

			<?php
			} // end foreach
		}

	} // end function nsfp_posttypes_callback

} // end class
