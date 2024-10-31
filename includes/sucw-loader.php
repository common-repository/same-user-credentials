<?php
/**
 * Il loader gestisce tutti gli hook e i filtri di wordpress usati dal plugin
 * ad eccezione del menu e delle api. Gli hook vengono usati solo dai siti client
*/
namespace sucw;

class SUCW_loader {
    public function __construct() {
        //add_action('wp_ajax_save_sucw_settings', [$this, 'save_sucw_settings']);
        if (SUCW_Fn::is_client()) {
           // add_action('wp_login', [$this, 'sync_login'], 10, 2);
            add_action('authenticate', [$this, 'sync_login'], 11, 3);
            // al caricamento della pagina controllo se devo fare un check di autenticazione
            add_action('init', [$this, 'check_auth']);
            // sovrascrivo il link per il recupero password
            add_filter('lostpassword_url', [$this, 'lostpassword_url'], 11, 2);
            // sovrascrio il link per la registrazione
            add_filter('register_url', [$this, 'register_url'], 11, 1);
            // aggiungo un hook alla modifica degli utenti
            add_action('admin_head-user-edit.php', [$this, 'admin_profile_head'], 10);
        }
    }
   
    /**
     * Invia le credenziali al server remoto 
     * @param WP_User $user
     * @param string $username
     * @param string $pwd
     * @ignore
     */
    public function sync_login($user, $username, $pwd) {
        if ($username == '' || $pwd == '') {
            return;
        }
        
        // di default faccio passare gli amministratori
        if (SUCW_fn::exclude_user_by_username($username)) return;
        $data_response = SUCW_Fn::sucw_loader_call_remote_server_login($username, $pwd);
        SUCW_Fn::set_checked_server();
        if ( $data_response == false || $data_response->response_status != 'ok') {
            // se l'utente non esiste in remoto ma esiste in locale, se non è tra gli utenti esclusi
            // gli sostituisco la password in locale con una password casuale assurda.
            // segno che l'ho aggiornato con un metadato e lo blocco
            $user = get_user_by('login', $username);
            if ($user) {
                SUCW_Fn::block_user($user->ID);
            }
        
            // print "<h1>Errore nella risposta del server</h1>";
            return;
        } else {
            $user_id = SUCW_Fn::get_user_id_from_server_response($data_response->user);
            if ($user_id == 0) {
                SUCW_Fn::create_user_from_server_response($data_response->user, $pwd);
            } else {
                // l'utente esiste già devo aggiornare i dati
                SUCW_Fn::update_user_from_server_response($user_id, $data_response->user, $pwd);
            }
         }
    }

    /**
     * Se è passato più di un giorno dall'ultimo controllo del login dal server
     * @ignore
     */
    public function check_auth() {
        // se l'utente è loggato
        if (!is_user_logged_in())  return;
        // verifico che l'utente non abbia un ruolo da escludere
        if (SUCW_fn::exclude_user()) return;
        // verifico che non sia stata già fatta una verifica in fase di login
        if (SUCW_Fn::get_checked_server()) return;

        // legge il metadato con la data di aggiornamento sucw_update
        $sucw_update = get_user_meta(get_current_user_id(), 'sucw_update', true);
       
        // se non esiste il metadato non fa nulla 
        if ($sucw_update == false) return;
        // se la data di aggiornamento è minore di 1 giorno
        if ($sucw_update + 86400 > time()) {
            return;
        } 
        // se la data di aggiornamento è maggiore di 1 giorno
        // allora fa una chiamata al server remoto
        $username = wp_get_current_user()->user_login;
        $data_response = SUCW_Fn::sucw_loader_call_remote_server_check($username);
        if ($data_response == false) return;
        if ($data_response->response_status == 'invalid-user') {
            wp_logout();
            wp_redirect(home_url());
            exit;
        }
        if ($data_response->response_status == 'ok') {
            $user_id = SUCW_Fn::get_user_id_from_server_response($data_response->user);
            if ($user_id > 0) {
                SUCW_Fn::update_user_from_server_response($user_id, $data_response->user, '');
            } else {
                // non trovo l'utente da verificare
            }
        }
    }
    
    /**
     * Sovrascrive il link per la password persa con il link del server remoto
     * @param string $_lostpassword_url il link di default
     * @param string $redirect il link di redirect
     * @return string il link per la password persa
     * @ignore
     */
    public function lostpassword_url($_lostpassword_url, $redirect) {
        $remote_server = SUCW_Fn::get_server_url();
        // imposto il redirect alla pagina di login
        if ($redirect == '') {
            $redirect = get_site_url() . '/wp-login.php';
        }
        /**
         * Filter sucw-remote-timeout
         * gestisce il link per la password persa
         * @param string $url il link della registrazione lato server
         * @since 1.0.0
         */
        return apply_filters('sucw-lostpassword-url', $remote_server . '/wp-login.php?action=lostpassword&redirect_to=' . urlencode($redirect));
    }

    /**
     * Sovrascrive il link per la registrazione con il link del server remoto
     * @param string $register_url il link di default
     * @return string il link per la registrazione
     * @ignore
     */
    public function register_url($register_url) {
        $remote_server = SUCW_Fn::get_server_url(). '/wp-login.php?action=register';
        /**
         * Filter sucw-register-url
         * gestisce il link per la registrazione
         * @param string $register_url il link di default
         * @since 1.0.0
         */
        return apply_filters('sucw_register_url', $remote_server);
    }

    /**
     * Stampa sopra il profilo dell'utente un avvertimento per dire che è gestito da un server remoto
     * @ignore
     */
    public function admin_profile_head() {
        if (!SUCW_Fn::is_client()) return;
        $user_id = filter_input(INPUT_GET, 'user_id', FILTER_SANITIZE_NUMBER_INT);
        $user_id = absint($user_id);
        // add_user_meta($user_id, 'sucw_update', time(), true);
        $sucw_update = get_user_meta($user_id , 'sucw_update', true);
        $sucw_update = absint($sucw_update);
        if (!($sucw_update > 0) ) return;
        $format = get_option('date_format'). ' ' . get_option('time_format');
        $date = gmdate($format, $sucw_update);
        $plugin_link = '<a href="'.admin_url().'?page=sucw">Same User Credentials</a>';
        /* translators: 1: plugin links,  2: date */
        $msg = sprintf(__(' The user is managed by the remote server through the  %1$s plugin.<br> This user was updated on <b>%2$s</b>.<br>Any changes to the user must be made on the remote server.', 'same-user-credentials'), $plugin_link, $date );
      
        echo '<div class="notice"><p>'.wp_kses_post($msg).'</p></div>';
    }
}
