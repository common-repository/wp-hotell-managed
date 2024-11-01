<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}
 
delete_option( 'wphm_options' );
delete_option( 'wphm_blacklisted_ips' );
?>