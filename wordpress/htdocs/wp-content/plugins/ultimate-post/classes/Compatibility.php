<?php
namespace ULTP;

defined('ABSPATH') || exit;

class Compatibility{

    public function __construct(){
        add_action( 'upgrader_process_complete', array($this, 'plugin_upgrade_completed'), 10, 2 );
    }

    public function plugin_upgrade_completed( $upgrader_object, $options ) {
        if( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ){
            foreach( $options['plugins'] as $plugin ) {
                if( $plugin == ULTP_BASE ) {
                    $set_settings = array(
                        'ultp_templates' => 'true',
                        'ultp_elementor' => 'true',
                    );
                    
                    // Pro Addons Data Init
                    $addon_data = get_option('ultp_addons_option', array());
                    if (isset($addon_data['ultp_category'])) {
                        $set_settings['ultp_category'] = $addon_data['ultp_category'];
                    }

                    foreach ($set_settings as $key => $value) {
                        ultimate_post()->set_setting($key, $value);
                    }
                }
            }
        }
    }

}