<?php
/**
 * Gestisco la pagina amministrativa principale
 */
namespace sucw;

 class SUCW_Admin {

    var $error = false;
    var $error_msg = '';
    var $saved = false;

    public function __construct() {  
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));    
    }

  
    public function display_server_field() {
        $option = SUCW_Fn::get_opt('mode');
        ?>
        <div class="sucw-option">
            <input type="radio" id="sucw_server" name="sucw_options[mode]" value="server" <?php checked($option, 'server'); ?>>
            <label for="sucw_server">
                <h3><?php esc_html_e('SERVER', 'same-user-credentials'); ?></h3>
                   <?php esc_html_e('The site identified as the server shares login data with other sites. It is the site on which users register.', 'same-user-credentials'); ?></p>
                </p>
                
            </label>
        </div>
        <?php
    }
    
    public function display_client_field() {
        $option = SUCW_Fn::get_opt('mode');
        ?>
        <div class="sucw-option">
            <input type="radio" id="sucw_client" name="sucw_options[mode]" value="client" <?php checked($option, 'client'); ?>>
            <label for="sucw_client">
                <h3><?php esc_html_e('CLIENT', 'same-user-credentials'); ?></h3>
                <p><?php esc_html_e('Client sites inherit server site login access data.', 'same-user-credentials'); ?>
                <br><?php esc_html_e('Administrator users* remain excluded from login sharing.', 'same-user-credentials'); ?><br>
            <spam class="sucw-small"><?php esc_html_e('*You can change this option from wordpress hooks', 'same-user-credentials'); ?></spam></p>
            </label>
        </div>
        <?php
    }



    public function server_url_field() {
        $option = SUCW_Fn::get_opt('server_url');
        ?>
        <div class="sucw-grid-textfield">
            <label for="sucw_server_url">
                Server URL
            </label>
            <div>
            <input type="text" id="sucw_server_url" name="sucw_options[server_url]" value="<?php echo esc_attr($option); ?>">
            </div>
        </div>
        <p class="sucw-info-field"><?php esc_html_e('The address of the server to connect to. Just the domain with the final slash. Ex: https://www.example.com', 'same-user-credentials'); ?></p>
        <?php
    }

    public function server_private_key_field() {
        $option = SUCW_Fn::get_opt('private_key');
        if ($option == '') {
            $option = SUCW_Fn::generate_private_key();
        }
        ?>
        <div class="">
            <h3 style="text-align: center";>Your Private Key: <?php echo esc_html($option); ?></h3>
            <input type="hidden" id="sucw_private_key" name="sucw_options[server_private_key]" value="<?php echo esc_attr($option); ?>">
        </div>
        <?php
    }

    public function private_key_field() {
        $option = SUCW_Fn::get_opt('private_key');
        $mode = SUCW_Fn::get_opt('mode');
        if ($mode == "server") {
            $option = '';
        }
        ?>
        <div class="sucw-grid-textfield">
            <label for="sucw_private_key">Private Key</label>
            <div>
            <input type="text" id="sucw_private_key" name="sucw_options[client_private_key]" value="<?php echo esc_attr($option); ?>">
            </div>
        </div>
        <p class="sucw-info-field">
            <?php esc_html_e('Copy the private key you find on the "server" site here.', 'same-user-credentials'); ?>
        </p>
        <?php
    }

    public function display_messages() {
        if ($this->error) {
            ?>
            <div class="sucw-alert-error">
                <p><?php echo esc_html($this->error_msg); ?></p>
            </div>
            <?php
        } else if ($this->saved) {
            ?>
            <div class="sucw-alert-success">
                <p><?php esc_html_e('Settings saved successfully', 'same-user-credentials'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Aggiunge gli stili e gli script necessari
     */
    public function enqueue_styles() {
        wp_enqueue_style('sucw-admin', plugin_dir_url(__FILE__) . 'style.css', [], SUCW_VERSION);
        wp_enqueue_script('sucw-admin', plugin_dir_url(__FILE__) . 'script.js', array('jquery'), '1.0.0', true);

    }

    /**
     * Disegna la pagina è la funzione chiamata dal menu
     */
    public function controller_display_configuration_page() {
        // Controlla se l'utente ha le autorizzazioni necessarie
        if (!current_user_can('manage_options')) {
            wp_die(esc_html(__('You do not have sufficient permissions to access this page.', 'same-user-credentials')));
        }
        $this->saved = false;
        $this->error = false;
        $this->error_msg = '';
        if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'sucw') && $_POST) {
            // sanitize all $_post data
            $mode = sanitize_text_field($_POST['sucw_options']['mode']);
            $server_url = sanitize_text_field($_POST['sucw_options']['server_url']);
            $server_url = strtok($server_url, '?');
            // se l'ultimo carattere  è uno slash lo rimuovo
            if (substr($server_url, -1) == '/') {
                $server_url = substr($server_url, 0, -1);
            }
            // se l'url non è valido
            if ($mode == "client") {
                if (!filter_var($server_url, FILTER_VALIDATE_URL) || $server_url == '') {
                    // ERRORE INSERIMENTO DATI!
                    $this->error = true;
                    $this->error_msg = __('The server URL is invalid', 'same-user-credentials');
                   
                } else {
                    $server_url = sanitize_url($server_url);
                }
            } 
            
            if ($mode == "server") {
                if ($_POST['sucw_options']['server_private_key'] == '') {
                    $private_key = SUCW_Fn::generate_private_key();
                } else {
                    $private_key = sanitize_text_field($_POST['sucw_options']['server_private_key']);
                }
            } else {
                $private_key = sanitize_text_field($_POST['sucw_options']['client_private_key']);
            }
            if (!$this->error) {
                // verifico se la configurazione è corretta
                if ($mode == 'client') {
                     // salvo le opzioni
                    SUCW_Fn::set_opts(['mode' => $mode, 'server_url' => $server_url, 'private_key' => $private_key]);
                    $msg_response = SUCW_Fn::sucw_call_remote_server_config();
                    if ($msg_response != 'ok') {
                        $this->error = true;
                        if ($msg_response == 'invalid_token') {
                            $this->error_msg = __('The private key is not valid', 'same-user-credentials');
                        } else if ($msg_response == 'token_expired') {
                            $this->error_msg = __('The server and client clocks are not synchronized', 'same-user-credentials');
                        } else {
                            $this->error_msg = __('Something went wrong with the server configuration, check the logs for more information', 'same-user-credentials');
                        }
                       
                    }
                }
                if (!$this->error) {
                    $this->saved = true;
                }
                // salvo le opzioni
                SUCW_Fn::set_opts(['mode' => $mode, 'server_url' => $server_url, 'private_key' => $private_key]);
                // get user nice name
                $user = wp_get_current_user();
                SUCW_log::add($user->user_login, 'ADMINISTRATION: The plugin configuration has been changed', 'info');
            }
            
        } 
        $mode = SUCW_Fn::get_opt('mode');
        $key = SUCW_Fn::get_opt('private_key');
        if ($mode == 'server' && $key == '') {
            SUCW_Fn::set_opt('private_key', SUCW_Fn::generate_private_key());
        }

        // Visualizza la pagina di configurazione
        require_once(__DIR__.'/sucw-page-admin-options.php');
        
    }


    public function display_logs_page() {
        // Controlla se l'utente ha le autorizzazioni necessarie
        if (!current_user_can('manage_options')) {
            wp_die(esc_html(__('You do not have sufficient permissions to access this page.', 'same-user-credentials')));
        }
        $logs = SUCW_log::get();
        $logs = array_reverse($logs);
        require_once(__DIR__.'/sucw-page-admin-logs.php');
    }

}