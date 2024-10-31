<?php
/**
 * Le funzioni di sistema che vengono usate
*/
namespace sucw;

class SUCW_Fn {

    static $class_option = false;

    static $checked_server = false;
    /**
     * Restituisce l'opzione del plugin richiesta
     * salvate dentro l'option sucw_options
     * @param string $option mode | server_url | private_key | log_file_name
     * @return string|NULL
     */
    static function get_opt($option = '') {
        if (self::$class_option == false) {
            self::$class_option = new SUCW_Options();
        }
        if (property_exists(self::$class_option, $option)) {
            return self::$class_option->$option;
        } 
        return NULL;
    }

    /**
     * Salva l'opzione richiesta
     * @param string $option mode | server_url | private_key
     * @param string $value
     */
    static function set_opt($option, $value) {
        if (self::$class_option == false) {
            self::$class_option = new SUCW_Options();
        }
        self::$class_option->set_option($option, $value);
        self::$class_option->save();

    }

    /**
     * Se devo salvare più opzioni in una volta
     * @var array $options è un array di opzioni da salvare
     */
    static function set_opts($options) {
        if (self::$class_option == false) {
            self::$class_option = new SUCW_Options();
        }
        foreach ($options as $option => $value) {
            self::$class_option->set_option($option, $value);
        }
        self::$class_option->save();
    }

    /**
     * Il sito è configurato come server?
     * @return bool
     */
    static function is_server() {
        return self::get_opt('mode') == 'server';
    }
    
    /**
     * Il sito è configurato come client?
     * @return bool
     */
    static function is_client() {
        return self::get_opt('mode') == 'client';
    }

    /**
     * Ritorna il nome del server SENZA lo slash finale
     * @return string
     */
    static function get_server_url() {
        return self::get_opt('server_url');
    }
    
    /**
     * Genera una chiave per l'encrypt
     * @ignore
     */
    static function generate_private_key() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Verifico se l'utente è stato già creato
     * @param Object $user l'utente da verificare 
     * $user è un oggetto che ritorna dalla chiamata al server
     * @return int|bool ( user_id or false )
     */
    static function get_user_id_from_server_response($user) {
        $user_data = $user->data;
        $user = get_user_by('login', $user_data->user_login);
        if ($user != false) {
            return $user->ID;
        }
        return false;
    }

    /**
     * Crea l'utente dalla risposta del server
     * Testati metadati anche multipli, ruoli, e dati aggiuntivi della riga wp_user
     * @param $user object la risposta del server $user->data i dati dell'utente
     * @param $pwd string la password dell'utente
     * @return int ( user_id or 0 for error)
     */
    static function create_user_from_server_response($user, $pwd) {
        $user_data = $user->data;
        if ($user_data->deleted == 1) {
            return 0;
        }

        $user_id = wp_create_user($user_data->user_login, $pwd, $user_data->user_email);

        if (!is_wp_error($user_id)) {
            // L'utente è stato creato con successo
            $new_user = new \WP_User($user_id);
            // ruoli
            self::update_roles($new_user, $user->roles);
            // aggiorno user
            self::update_user($user_id, $user_data);
            return $user_id;
        } else {
            // Si è verificato un errore durante la creazione dell'utente
            return 0;
        }
    }

    /**
     * Blocco l'utente e cambio la password
     * @param int $user_id l'id dell'utente
     */
    static function block_user($user_id) {
        $user_pwd = wp_generate_password(40);
        wp_set_password($user_pwd, $user_id);
        $new_user = new \WP_User($user_id);
        $new_user->set_role('none');
        update_user_meta($user_id, 'sucw_update', time());
    }

    /**
     * Verifico se l'utente in locale deve fare la sincronizzazione
     * Se ha un determinato ruolo torna true e non deve fare la sincronizzazione
     * per default sono gli amministratori
     * @param int $user_id l'id dell'utente
     */
    static function exclude_user($user_id = false) {
        if ($user_id) $user = get_userdata($user_id);
	    else $user = wp_get_current_user();
        return self::check_user_role($user);
    }

    /**
     * Verifico se l'utente deve fare la sincronizzazione
     * Se ha un determinato ruolo torna true e non deve fare la sincronizzazione
     * per default sono gli amministratori
     * @param string $username lo username dell'utente
     */
    static function exclude_user_by_username($username) {
        // se la username è un'email
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $user = get_user_by('email', $username);
            return self::check_user_role($user);
        } else {
            $user = get_user_by('login', $username);
            return self::check_user_role($user);
        }
    }

    /**
     * Verifica se un utente ha un determinato ruolo. 
     * Se non ce l'ha o non esiste l'utente
     * torna false
     * @return bool true se l'utente ha un ruolo determinato.
     *  Se è true lo escludo dalla sincronizzazione
     */
    static private function check_user_role($user) {
        if (!$user) return false;
        // TODO se non ha ruoli settati?? Da decidere
        if (isset($user->roles) && count($user->roles) == 0) return false;
        /**
         * Filter sucw-roles-exclude-all-sync-except
         * Esclude tutti i ruoli ad eccezione di:
         * @param array $array_exclude l'elenco dei ruoli default ['administrator']
         * @since 1.0.0
         */
        $array_except = apply_filters('sucw-roles-exclude-all-sync-except', []);
        if (is_array($array_except) && count($array_except) > 0) {
            // di base lo escludo dalla sincronizzazione
            // a meno che non abbia uno di questi ruoli
            foreach ($user->roles as $role) {
                if (in_array($role, $array_except)) {
                    return false;
                }
            }
            return true;
        } else {
            /**
             * Filter sucw-roles-to-exclude-sync
             * I ruoli da escludere dalla sincronizzazione
             * @param array $array_exclude l'elenco dei ruoli default ['administrator']
             * @since 1.0.0
             */
            $array_exclude = apply_filters('sucw-roles-to-exclude-sync', ['administrator']);
            foreach ($user->roles as $role) {
                if (in_array($role, $array_exclude)) {
                    return true;
                }
            }
        }

     
        return false;
    }
    /**
     * Aggiorna l'utente dalla risposta del server
     * Testati metadati anche multipli, ruoli, e dati aggiuntivi della riga wp_user
     * @param $user object la risposta del server $user->data i dati dell'utente
     * @param $pwd string la password dell'utente
     * @return int ( user_id or 0 for error)
     */
    static function update_user_from_server_response($user_id, $user, $pwd) {
        $user_data = $user->data;
        if ($user_data->deleted == 1) {
            self::block_user($user_id);
            return 0;
        }
        if ($user_id == false) {
            return 0;
        }
        $new_user = new \WP_User($user_id);
        if ($new_user == false) {
            return 0;
        }
        // aggiorno la password
        if ($pwd != '') {
            wp_set_password($pwd, $user_id);
        }
        // aggiorno l'email
        if ($user_data->user_email != $new_user->user_email) {
            wp_update_user( array(
                'ID'            => $user_id,
                'user_email'    => $user_data->user_email
            ));
        }

        self::update_roles($new_user, $user->roles);
      
        // aggiorno user
        self::update_user($user_id, $user_data);
        return $user_id;

    }

    /**
     * Aggiorna i ruoli dell'utente (Client)
     * @param object $new_user l'oggetto dell'utente
     * @param array $roles l'elenco dei ruoli
     */
    private static function update_roles( $new_user, $roles) {
        /**
         * Filter sucw-update-roles
         * L'elenco dei ruoli da aggiornare quando si crea o aggiorna i ruoli se è un array vuoto non aggiorna i ruoli
         * @param array $roles l'elenco dei ruoli
         * @since 1.0.0
         */
        $roles = apply_filters('sucw-update-roles', $roles);
        // rimuovo il ruolo amministratore da possibili update
        if (is_array($roles) && count($roles) > 0) {
            foreach ($roles as $key => $role) {
                if ($role == 'administrator') {
                    unset($roles[$key]);
                }
            }
        }
       
        if (count($roles) >= 1) {
            $new_user->set_role(array_shift($roles));
        }

        if (count($roles) > 0) {
            foreach ($roles as $role) {
                $new_user->add_role($role);
            }
        }
    }

    /**
     * Sia per la creazione che per l'aggiornamento dell'utente queste operazioni sono comuni
     * @param int $user_id l'id dell'utente (Client)
     * @param object $user_data i dati dell'utente (venuti dal server remoto)
     */
    private static function update_user($user_id, $user_data) {
        // aggiorno user
        wp_update_user( array(
            'ID'            => $user_id,
            'user_nicename' => $user_data->user_nicename,
            'user_url'      => $user_data->user_url,
            'user_registered' => $user_data->user_registered,
            'user_status'   => $user_data->user_status,
            'display_name'  => $user_data->display_name
        ));

        /**
         * Filter sucw-allow-metadata
         * @param bool $allow_metadata se true permette di aggiornare i metadati
         * se false non aggiorna i metadati, se è un array aggiorna solo i metadati presenti nell'array
         * @since 1.0.0
         */
        $allow_metadata = apply_filters('sucw-allow-metadata', true);
        // aggiorno i metadati 
        if ($allow_metadata === true || is_array($allow_metadata)) {
            if (isset($user_data->all_meta)) {
                foreach ($user_data->all_meta as $key => $value) {
                    if (is_array($allow_metadata) && !in_array($key, $allow_metadata)) {
                        continue;
                    }
                    if (is_array($value)) {
                        $val1 = array_shift($value);
                        \delete_user_meta($user_id, $key);
                        \add_user_meta($user_id, $key, $val1, true);
                        foreach ($value as  $v) {
                            \add_user_meta($user_id, $key, $v);
                        }
                    } else {
                        \update_user_meta($user_id, $key, $value);
                    }  
                }
            }
        }
        /**
         * Hook sucw-update-user Aggioramento di un utente
         * @param int $user_id l'id dell'utente
         * @param object $user_data i dati dell'utente
         * @since 1.0.0
         */
        do_action( 'sucw-update-user', $user_id, $user_data );
        
        \update_user_meta($user_id, 'sucw_update', time());
    }

    /**
     * Call remote server la chiamata dentro sucw-loader > sync_login
     * La chiamata server è dentro sucw-api
     * @param string $username lo username da chiamare
     * @param string $pwd la password da chiamare
     * @return object|bool ( response[body] or false )
     */
    static function sucw_loader_call_remote_server_login($username, $pwd) {
        //SUCW_log::add($username, 'sucw_loader_call_remote_server_login()', 'info');
        if ($username == '' || $pwd == '') return;
        $token = SUCW_auth::generate_token($username, $pwd);
        return self::sucw_call_server('login', $token, $username);
    }

    /**
     * chiedo i dati di un utente a partire dal suo username
     * in pratica è uguale a server_login, però non passo la password
     * @param string $username lo username da chiamare
     */
    static function sucw_loader_call_remote_server_check($username) {
        //SUCW_log::add($username, 'sucw_loader_call_remote_server_check()', 'info');
        if ($username == '' )  return;
        $token = SUCW_auth::generate_token($username, '');
        return self::sucw_call_server('check-user', $token, $username);

    }

     /**
     * Chiedo al server se la configurazione è corretta
     * Chiama il server e verifica il token (e l'indirizzo del server)
     * @return string 'ok'|'error'|'invalid_token'|'token_expired'
     */
    static function sucw_call_remote_server_config() {
        //SUCW_log::add($username, 'sucw_loader_call_remote_server_check()', 'info');
        if (!SUCW_Fn::is_client()) return false;
        $token = SUCW_auth::generate_token('', '');
        $ris = self::sucw_call_server('check-configuration', $token, 'check-configuration');
       // var_dump ($ris);
        if (is_object($ris) && property_exists($ris, 'response_status')) {
           
            if ($ris->response_status == 'ok') {
                return 'ok';
            } else if (property_exists($ris, 'msg')) {
                return $ris->msg;
            }
        }
        return 'error';

    }

    /**
     * Server per evitare che il check-user si esegua in fase di login. 
     */
    static function set_checked_server() {
        self::$checked_server = true;
    }

    static function get_checked_server() {
        return self::$checked_server;
    }


    /**
     * Eseguo la chiamata al server
     * @param string $api_call le chiamate (login|check-user|check-configuration)
     * @param string $token il token è la stringa criptata con i parametri da passare al server
     * tipo username e password
     * @param string $username lo username dell'utente
     * @return object|bool ( response[body] or false )
     */
    static private function sucw_call_server($api_call, $token, $username) {
        $start_time = microtime(true);
        $remote_server = SUCW_Fn::get_server_url();
        if ($remote_server == '')  return false;
        /**
         * Filter sucw-htaccess
         * Il server ha l'htaccess attivo altrimenti fa la chiamata con il parametro rest_route
         * @since 1.0.0
         */
        if (apply_filters('sucw-htaccess', true)) {
            $url = $remote_server . '/wp-json/sucw/v1/'.$api_call;
        } else {
            $url = $remote_server . '/?rest_route=/sucw/v1/'.$api_call;
        }
       
        $headers = array(
            'Authorization' => 'Bearer ' . $token,
        );
        $url_with_random_param = add_query_arg( 'nocache', time(), $url );

        /**
         * Filter sucw-remote-timeout
         * Il timeout della chiamata al server
         * @param int $timeout Default 15
         * @since 1.0.0
         */
        $timeout = apply_filters('sucw-remote-timeout', 15);
      
        /**
         * Filter sucw-remote-args
         * Sono gli argomenti per la chiamata al server da parte del client
         * @param array $args Default array('method' => 'POST', 'timeout' => $timeout, 'redirection' => 2, 'httpversion' => '1.0', 'blocking' => true, 'headers' => $headers, 'cookies' => [])
         * @since 1.0.0
         */
        $args = apply_filters('sucw-remote-args', array(
            'method' => 'POST',
            'timeout' => $timeout,
            'redirection' => 2,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => $headers,
            'cookies' => []
        ));
        $response = wp_remote_post($url_with_random_param,  $args);
        $end_time = microtime(true);
        if ($end_time - $start_time > $timeout / 3) {
            // il tempo di risposta è molto alto!!!
            SUCW_log::add($username, 'The server response time is very high ('.(round($end_time - $start_time, 2)).' sec)', 'warning');
        }

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            $error_msg = 'Called '.$url.' error: '.$error;
            SUCW_log::add($username, $error_msg, 'error');
            return false;
        }
       $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code != 200) { 
            $error_msg = 'Called '.$url.' Response code: '.$response_code;
            SUCW_log::add($username, $error_msg, 'error', $response);
            return false;
        }

        $data = json_decode($response['body']);
        
        if (JSON_ERROR_NONE !== json_last_error()) {
            SUCW_log::add($username, 'Error decoding json: '.json_last_error_msg(), 'error', $response);
            return false;
        } 

        $response_log = (isset($data->response_status)) ? $data->response_status : 'no_response_status';
        $type = 'info';
        if ($response_log == "error") {
            $type = "warning";
        }
        if ($response_log == "ok") {
            $type = "success";
        }
        if (isset($data->message)) {
            $response_log .= " ". $data->message;
        }
        SUCW_log::add($username, 'Called '.$url.' response: '.$response_log, $type, $response);
        return $data;
    }
}