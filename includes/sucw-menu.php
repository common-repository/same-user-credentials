<?php
/**
 * Aggiunge le voci di menu al pannello di amministrazione
 */
namespace sucw;
class SUCW_menu {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
    }

    public function add_admin_menu() {
        // Aggiungi la voce di menu principale
        add_menu_page('Same User Credentials', 'SUC', 'manage_options', 'sucw', array(new SUCW_Admin(), 'controller_display_configuration_page'), 'dashicons-admin-network', 74);

        add_submenu_page('sucw', 'Logs', 'Logs', 'manage_options', 'sucw-logs', array(new SUCW_Admin(), 'display_logs_page'));
        
     
    }
}

