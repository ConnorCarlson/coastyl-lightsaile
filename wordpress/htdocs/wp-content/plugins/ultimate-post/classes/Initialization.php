<?php
namespace ULTP;

defined('ABSPATH') || exit;

class ULTP_Initialization{

    private $all_blocks;

    public function __construct(){
        $this->compatibility_check();
        $this->requires(); // Include Necessary Files
        $this->blocks(); // Include Blocks
        $this->include_addons(); // Include Addons

        add_action('wp_head',                       array($this, 'popular_posts_tracker_callback'));
        add_filter('block_categories',              array($this, 'register_category_callback'), 10, 2); // Block Category Register
        add_action('after_setup_theme',             array($this, 'add_image_size'));

        add_action('enqueue_block_editor_assets',   array($this, 'register_scripts_back_callback')); // Only editor
        add_action('admin_enqueue_scripts',         array($this, 'register_scripts_option_panel_callback')); // Option Panel
        add_action('wp_enqueue_scripts',            array($this, 'register_scripts_front_callback')); // Both frontend
        register_activation_hook(ULTP_PATH.'ultimate-post.php', array($this, 'install_hook'));
        add_action( 'activated_plugin',             array($this, 'activation_redirect'));

        add_action('wp_ajax_ultp_next_prev',        array($this, 'ultp_next_prev_callback')); // Next Previous AJAX Call
        add_action('wp_ajax_nopriv_ultp_next_prev', array($this, 'ultp_next_prev_callback')); // Next Previous AJAX Call Logout User
        add_action('wp_ajax_ultp_filter',           array($this, 'ultp_filter_callback')); // Next Previous AJAX Call
        add_action('wp_ajax_nopriv_ultp_filter',    array($this, 'ultp_filter_callback')); // Next Previous AJAX Call Logout User
        add_action('wp_ajax_ultp_pagination',       array($this, 'ultp_pagination_callback')); // Page Number AJAX Call
        add_action('wp_ajax_nopriv_ultp_pagination',array($this, 'ultp_pagination_callback')); // Page Number AJAX Call Logout User
        add_action('wp_ajax_ultp_addon',           array($this, 'ultp_addon_callback')); // Next Previous AJAX Call

        add_action('admin_init', array($this, 'check_theme_compatibility') );
        add_action( 'after_switch_theme', array( $this, 'wpxpo_swithch_thememe' ) );
    }

    public function wpxpo_swithch_thememe () {
        $this->check_theme_compatibility();   
    }

    public function check_theme_compatibility() {
        $licence = apply_filters( 'ultp_theme_integration' , FALSE);
        $theme = get_transient( 'ulpt_theme_enable' );

        if( $licence ) {
            if( $theme != 'integration' ) {
                $themes = wp_get_theme();
                $api_params = array(
                    'wpxpo_theme_action' => 'theme_license',
                    'slug'      => $themes->get('TextDomain'),
                    'author'    => $themes->get('Author'),
                    'item_id'    => 181,
                    'url'        => home_url()
                );
                
                $response = wp_remote_post( 'https://www.wpxpo.com', array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

                if ( !is_wp_error( $response ) || 200 === wp_remote_retrieve_response_code( $response ) ) {
                    $license_data = json_decode( wp_remote_retrieve_body( $response ) );
                    if(isset($license_data->license)) {
                        if ( $license_data->license == 'valid' ) {
                            set_transient( 'ulpt_theme_enable', 'integration', 2592000 ); // 30 days time
                        }
                    }
                }
            }
        } else {
            if ( $theme == 'integration' ) {
                delete_transient('ulpt_theme_enable');
            }
        }
    }

    public function compatibility_check(){
        require_once ULTP_PATH.'classes/Compatibility.php';
        new \ULTP\Compatibility();
    }

    public function register_scripts_option_panel_callback(){
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_script('ultp-option-script', ULTP_URL.'assets/js/ultp-option.js', array('jquery'), ULTP_VER, true);
        wp_enqueue_style('ultp-option-style', ULTP_URL.'assets/css/ultp-option.css', array(), ULTP_VER);
        wp_localize_script('ultp-option-script', 'ultp_option_panel', array(
            'width' => ultimate_post()->get_setting('editor_container'),
            'security' => wp_create_nonce('ultp-nonce'),
            'ajax' => admin_url('admin-ajax.php')
        ));
    }

    public function register_scripts_common(){
        wp_enqueue_style('ultp-style', ULTP_URL.'assets/css/style.min.css', array(), ULTP_VER );
        wp_enqueue_script('ultp-script', ULTP_URL.'assets/js/ultp.min.js', array('jquery'), ULTP_VER, true);
        wp_localize_script('ultp-script', 'ultp_data_frontend', array(
            'url' => ULTP_URL,
            'ajax' => admin_url('admin-ajax.php'),
            'security' => wp_create_nonce('ultp-nonce')
        ));
    }

    // Backend and Frontend Load script
    public function register_scripts_front_callback() {
        if ('yes' == get_post_meta(get_the_ID(), '_ultp_active', true)) {
            $this->register_scripts_common();
        }
    }

    // Only Backend
    public function register_scripts_back_callback() {
        $this->register_scripts_common();
        wp_enqueue_script('ultp-blocks-editor-script', ULTP_URL.'assets/js/editor.blocks.min.js', array('wp-i18n', 'wp-element', 'wp-blocks', 'wp-components', 'wp-editor' ), ULTP_VER, true);
        wp_enqueue_style('ultp-blocks-editor-css', ULTP_URL.'assets/css/blocks.editor.css', array(), ULTP_VER);
        if(is_rtl()){ 
            wp_enqueue_style('ultp-blocks-editor-rtl-css', ULTP_URL.'assets/css/rtl.css', array(), ULTP_VER); 
        }
        
        $import = ultimate_post()->get_setting('hide_import_btn');
        
        $is_active = get_option('edd_ultp_license_status') == 'valid' ? true : ( get_transient( 'ulpt_theme_enable' ) == 'integration' ? true : false );

        wp_localize_script('ultp-blocks-editor-script', 'ultp_data', array(
            'url' => ULTP_URL,
            'ajax' => admin_url('admin-ajax.php'),
            'security' => wp_create_nonce('ultp-nonce'),
            'hide_import_btn' => $import,
            'upload' => wp_upload_dir()['basedir'] . '/ultp',
            'premium_link' => ultimate_post()->get_premium_link(),
            'license' => get_option('edd_ultp_license_key'),
            'active' => $is_active,
        ));
    }

    // Fire When Plugin First Install
    public function install_hook() {
        if (!get_option('ultp_options')) {
            ultimate_post()->init_set_data();
        }
    }

    public function activation_redirect($plugin) {
        if( $plugin == 'ultimate-post/ultimate-post.php' ) {
            exit(wp_redirect(admin_url('admin.php?page=ultp-settings')));
        }
    }

    // Require Blocks
    public function blocks() {
        require_once ULTP_PATH.'blocks/Post_List_1.php';
        require_once ULTP_PATH.'blocks/Post_List_2.php';
        require_once ULTP_PATH.'blocks/Post_List_3.php';
        require_once ULTP_PATH.'blocks/Post_List_4.php';
        require_once ULTP_PATH.'blocks/Post_Slider_1.php';
        require_once ULTP_PATH.'blocks/Post_Grid_1.php';
        require_once ULTP_PATH.'blocks/Post_Grid_2.php';
        require_once ULTP_PATH.'blocks/Post_Grid_3.php';
        require_once ULTP_PATH.'blocks/Post_Grid_4.php';
        require_once ULTP_PATH.'blocks/Post_Grid_5.php';
        require_once ULTP_PATH.'blocks/Post_Grid_6.php';
        require_once ULTP_PATH.'blocks/Post_Grid_7.php';
        require_once ULTP_PATH.'blocks/Heading.php';
        require_once ULTP_PATH.'blocks/Image.php';
        require_once ULTP_PATH.'blocks/Post_Module_1.php';
        require_once ULTP_PATH.'blocks/Post_Module_2.php';
        $this->all_blocks['ultimate-post_post-list-1'] = new \ULTP\blocks\Post_List_1();
        $this->all_blocks['ultimate-post_post-list-2'] = new \ULTP\blocks\Post_List_2();
        $this->all_blocks['ultimate-post_post-list-3'] = new \ULTP\blocks\Post_List_3();
        $this->all_blocks['ultimate-post_post-list-4'] = new \ULTP\blocks\Post_List_4();
        $this->all_blocks['ultimate-post_post-slider-1'] = new \ULTP\blocks\Post_Slider_1();
        $this->all_blocks['ultimate-post_post-grid-1'] = new \ULTP\blocks\Post_Grid_1();
        $this->all_blocks['ultimate-post_post-grid-2'] = new \ULTP\blocks\Post_Grid_2();
        $this->all_blocks['ultimate-post_post-grid-3'] = new \ULTP\blocks\Post_Grid_3();
        $this->all_blocks['ultimate-post_post-grid-4'] = new \ULTP\blocks\Post_Grid_4();
        $this->all_blocks['ultimate-post_post-grid-5'] = new \ULTP\blocks\Post_Grid_5();
        $this->all_blocks['ultimate-post_post-grid-6'] = new \ULTP\blocks\Post_Grid_6();
        $this->all_blocks['ultimate-post_post-grid-7'] = new \ULTP\blocks\Post_Grid_7();
        $this->all_blocks['ultimate-post_heading'] = new \ULTP\blocks\Heading();
        $this->all_blocks['ultimate-post_image'] = new \ULTP\blocks\Image();
        $this->all_blocks['ultimate-post_post-module-1'] = new \ULTP\blocks\Post_Module_1();
        $this->all_blocks['ultimate-post_post-module-2'] = new \ULTP\blocks\Post_Module_2();
    }

    // Require Categories
    public function requires() {
        require_once ULTP_PATH.'classes/Notice.php';
        require_once ULTP_PATH.'classes/Styles.php';
        require_once ULTP_PATH.'classes/Options.php';
        require_once ULTP_PATH.'classes/REST_API.php';
        require_once ULTP_PATH.'classes/Caches.php';
        new \ULTP\Caches();
        new \ULTP\Options();
        new \ULTP\Styles();
        new \ULTP\Notice();

        require_once ULTP_PATH.'classes/Deactive.php';
        new \ULTP\Deactive();
    }

    // Block Categories
    public function register_category_callback( $categories, $post ) {
        return array_merge(
            array(
                array( 
                    'slug' => 'ultimate-post', 
                    'title' => __( 'Gutenberg Post Blocks', 'ultimate-post' ) 
                )
            ), $categories 
        );
    }

    // Post View Post Meta Set
    public function popular_posts_tracker_callback($post_id) {
        if (!is_single()){ return; }
        global $post;
        if (empty($post_id)) { $post_id = $post->ID; }
        $count = (int)get_post_meta( $post_id, '__post_views_count', true );
        update_post_meta($post_id, '__post_views_count', $count ? (int)$count + 1 : 1 );
    }

    // Set Image Size
    public function add_image_size() {
        $size_disable = ultimate_post()->get_setting('disable_image_size');
        if ($size_disable != 'yes') {
            add_image_size('ultp_layout_landscape_large', 1200, 800, true);
            add_image_size('ultp_layout_landscape', 870, 570, true);
            add_image_size('ultp_layout_portrait', 600, 900, true);
            add_image_size('ultp_layout_square', 600, 600, true);
        }
    }

    // Include Addons directory
	public function include_addons() {
		$addons_dir = array_filter(glob(ULTP_PATH.'addons/*'), 'is_dir');
		if (count($addons_dir) > 0) {
			foreach( $addons_dir as $key => $value ) {
				$addon_dir_name = str_replace(dirname($value).'/', '', $value);
				$file_name = ULTP_PATH . 'addons/'.$addon_dir_name.'/init.php';
				if ( file_exists($file_name) ) {
					include_once $file_name;
				}
			}
		}
    }


    public function ultp_addon_callback() {
        if (!wp_verify_nonce($_REQUEST['wpnonce'], 'ultp-nonce') && $local){
            return ;
        }
        $addon_name = sanitize_text_field($_POST['addon']);
        $addon_value = sanitize_text_field($_POST['value']);
        if ($addon_name) {
            $addon_data = ultimate_post()->get_setting();
            $addon_data[$addon_name] = $addon_value;
            $GLOBALS['wopb_settings'][$addon_name] = $addon_value;
            update_option('ultp_options', $addon_data);
        }
    }

    public function ultp_next_prev_callback() {
        if (!wp_verify_nonce($_REQUEST['wpnonce'], 'ultp-nonce') && $local){
            return ;
        }

        $paged      = sanitize_text_field($_POST['paged']);
        $blockId    = sanitize_text_field($_POST['blockId']);
        $postId     = sanitize_text_field($_POST['postId']);
        $blockRaw   = sanitize_text_field($_POST['blockName']);
        $blockName  = str_replace('_','/', $blockRaw);

        if( $paged && $blockId && $postId && $blockName ) {
            $post = get_post($postId); 
            if (has_blocks($post->post_content)) {
                $blocks = parse_blocks($post->post_content);
                $this->block_return($blocks, $paged, $blockId, $blockRaw, $blockName);
            }
        }
    }

    public function filter_block_return($blocks, $blockId, $blockRaw, $blockName, $taxtype, $taxonomy) {
        foreach ($blocks as $key => $value) {
            if($blockName == $value['blockName']) {
                if($value['attrs']['blockId'] == $blockId) {
                    $attr = $this->all_blocks[$blockRaw]->get_attributes(true);
                    if($taxonomy) {
                        $value['attrs']['queryTaxValue'] = json_encode(array($taxonomy));
                        $value['attrs']['queryTax'] = $taxtype;
                    }
                    if(isset($value['attrs']['queryNumber'])){
                        $value['attrs']['queryNumber'] = $value['attrs']['queryNumber'];
                    }
                    $attr = array_merge($attr, $value['attrs']);
                    echo $this->all_blocks[$blockRaw]->content($attr, true);
                    die();
                }
            }
            if(!empty($value['innerBlocks'])){
                $this->filter_block_return($value['innerBlocks'], $blockId, $blockRaw, $blockName, $taxtype, $taxonomy);
            }
        }
    }


    public function ultp_filter_callback() {
        if (!wp_verify_nonce($_REQUEST['wpnonce'], 'ultp-nonce') && $local){
            return ;
        }
     
        $taxtype    = sanitize_text_field($_POST['taxtype']);
        $blockId    = sanitize_text_field($_POST['blockId']);
        $postId     = sanitize_text_field($_POST['postId']);
        $taxonomy   = sanitize_text_field($_POST['taxonomy']);
        $blockRaw   = sanitize_text_field($_POST['blockName']);
        $blockName  = str_replace('_','/', $blockRaw);

        if( $taxtype ) {
            $post = get_post($postId); 
            if (has_blocks($post->post_content)) {
                $blocks = parse_blocks($post->post_content);
                $this->filter_block_return($blocks, $blockId, $blockRaw, $blockName, $taxtype, $taxonomy);
            }
        }
    }

    public function ultp_pagination_callback() {
        if (!wp_verify_nonce($_REQUEST['wpnonce'], 'ultp-nonce') && $local) {
            return ;
        }

        $paged      = sanitize_text_field($_POST['paged']);
        $blockId    = sanitize_text_field($_POST['blockId']);
        $postId     = sanitize_text_field($_POST['postId']);
        $blockRaw   = sanitize_text_field($_POST['blockName']);
        $blockName  = str_replace('_','/', $blockRaw);

        if($paged) {
            $post = get_post($postId); 
            if (has_blocks($post->post_content)) {
                $blocks = parse_blocks($post->post_content);
                $this->block_return($blocks, $paged, $blockId, $blockRaw, $blockName);
            }
        }
    }


    public function block_return($blocks, $paged, $blockId, $blockRaw, $blockName) {
        foreach ($blocks as $key => $value) {
            if($blockName == $value['blockName']) {
                if($value['attrs']['blockId'] == $blockId) {
                    $attr = $this->all_blocks[$blockRaw]->get_attributes(true);
                    $attr['paged'] = $paged;
                    $attr = array_merge($attr, $value['attrs']);
                    echo $this->all_blocks[$blockRaw]->content($attr, true);
                    die();
                }
            }
            if(!empty($value['innerBlocks'])){
                $this->block_return($value['innerBlocks'], $paged, $blockId, $blockRaw, $blockName);
            }
        }
    }

}