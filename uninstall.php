<?php
// if uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

delete_option( 'sucw_options' );
// for site options in Multisite
delete_site_option( 'sucw_options' );
// cancello i logs
$dir = wp_upload_dir();
$dir = $dir['basedir'].'/sucw-logs/';
if (is_dir($dir)) {
    $files = glob($dir.'*');
    foreach ($files as $file) {
        if (!in_array($file, ['.', '..'])) {
            unlink($file);
        }
    }
    rmdir($dir);
}