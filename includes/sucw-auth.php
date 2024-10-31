<?php
namespace sucw;
/**
 * La classe Auth gestisce il bearer token che invia l'autenticazione
 * @ignore
 */

/**
 * Questa classe gestisce L'autenticazione alle chiamate api. 
 * 
 * 
 * 
 * @package sucw
 * @version 1.0.0
 */

class SUCW_auth
{
    static $last_error = '';
    /**
     * Verifica se il bearer è un utente valido nel caso ritorna l'utente
     * @var Bool $authenticate se true verifica l'autenticazione, 
     * altrimenti ritorna l'utente direttamente dalla username
     * @return wp_user|bool
     */
    static function server_get_user_from_bearer($authenticate = true) {
        self::$last_error = '';
        $time_to_expire = 86400; // 1 giorno
        $token = self::getAuthorizationHeader();
        if ($token === null) return false;
        $token_array = self::decrypt($token);
        if (is_array($token_array)) {
            $time = $token_array[0];
            $user = $token_array[1];
            $password = $token_array[2];
            if ($time + $time_to_expire < time()) {
                self::$last_error = 'token_expired';
                SUCW_log::add($token_array[1], 'Error login: token_expired', 'error');
                return false;
            }
            if ($authenticate) {
                $user = wp_authenticate($user, $password);
            } else {
                $user = get_user_by('login', $user);
            }
            if (is_wp_error($user) || !$user) {
                if ($authenticate) {
                    SUCW_log::add($token_array[1], 'Error login: invalid_credentials', 'warning');
                    self::$last_error = 'invalid_credentials';
                } else {
                    SUCW_log::add($token_array[1], 'Error login: invalid_user', 'warning');
                    self::$last_error = 'invalid_user';
                }
                return false;
            }
            if (isset($user->allcaps)) {
                unset($user->allcaps);
            }
            if (isset($user->filter)) {
                unset($user->filter);
            }
            if (isset($user->user_pass)) {
                unset($user->user_pass);
            }
            // trovo i ruoli dell'utente
            $user_roles = get_userdata($user->ID)->roles;
           
            // se sono tutti a false allora non ha ruoli e lo considero cancellato
            if (count(array_filter($user_roles)) == 0) {
                SUCW_log::add($user->user_login, 'Error login: user_deleted', 'error');
                $user->deleted = 1;
            }  else {
                // filtro per accettare solo i ruoli che mi interessano
                $allow_user_roles = apply_filters('sucw-server-allowed-user-roles', []);
                if (is_array($allow_user_roles) && count($allow_user_roles) > 0) {
                    foreach ($user_roles as $role) {
                        if (!in_array($role, $allow_user_roles)) {
                            SUCW_log::add($token_array[1], 'Error login: A role not allowed called: '.$role, 'warning');
                            self::$last_error = 'invalid_credentials';
                            return false;
                        }
                    }
                } else {
                    /**
                     * Se l'utente ha uno dei ruoli bloccati non lo faccio passare
                     * @var array $block_user_roles
                     * @return array
                     * @since 1.0.0
                     */
                    $block_user_roles =  apply_filters('sucw-block-user-roles', ['administrator']);
                    if (is_array($block_user_roles)) {
                        foreach ($user_roles as $role) {
                            if (in_array($role, $block_user_roles)) {
                                SUCW_log::add($token_array[1], 'Error login: A blocked role called', 'warning');
                                self::$last_error = 'invalid_credentials';
                                return false;
                            }
                        }
                    }
                }

                SUCW_log::add($user->user_login, 'logged in user', 'success');
            }     
            if (!isset($user->deleted)) {
                $user->deleted = 0;
            }
            // aggiungo i metadati
            $array_exclude = ['source_domain', 'wp_capabilities', 'locale', 'use_ssl', 'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'admin_color', 'show_admin_bar_front', 'dismissed_wp_pointers', 'primary_blog', 'wp_user_level'];
            $get_all_meta = get_user_meta($user->ID);
            foreach ($get_all_meta as $key => $_) {
                if (in_array($key, $array_exclude)) {
                    // rimuovo i metadati da get_all_meta
                    unset($get_all_meta[$key]);
                }   
            }
            $user->all_meta = $get_all_meta;
            return $user;
        }
        return false;
    }

    /** 
     * verifica solo se il bearer è corretto
     * @return string
     * @ignore
     */
    static function server_check_bearer() {
        self::$last_error = '';
        $time_to_expire = 86400; // 1 giorno
        $token = self::getAuthorizationHeader();

        if ($token === null) {
            return 'invalid_token';
        }
        $token_array = self::decrypt($token);
        if (is_array($token_array) && count($token_array) > 0) {
            $time = $token_array[0];
            if ($time + $time_to_expire < time()) {
                SUCW_log::add('CHECK CONFIGURATION', 'token_expired', 'error');
                return 'token_expired';
            }
            return 'ok';
        }
        return 'invalid_token';
    }


    /**
     * Genera un token bearer per l'autenticazione
     * Il token è formato da usercrypt(timestamp, user, password);
     * @ignore
     */
    static function generate_token($username, $password) {
        return  self::encrypt(wp_json_encode([time(), $username, $password]));
    }


    /**
     * Encrypt and decrypt a string per il bearer
     * @ignore
     */
    private static function encrypt($string) {
        $key = SUCW_Fn::get_opt('private_key');
        $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($string, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary=true);
        return self::urlsafeB64Encode( $iv.$hmac.$ciphertext_raw );
    }

    /**
     * decrypt una stringa per il bearer
     * 
     * @param string $string
     * @param string $key
     * @return string|false
     * @ignore
     */
    private static function decrypt($string) {
        $key = SUCW_Fn::get_opt('private_key');
        $c = self::urlsafeB64Decode($string);
        $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, $sha2len=32);
        $ciphertext_raw = substr($c, $ivlen+$sha2len);
        $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary=true);
        if (!hash_equals($hmac, $calcmac)) { // timing attack safe comparison
            $original_plaintext = false;
        }
        if ($original_plaintext !== false) {
            $original_plaintext = json_decode($original_plaintext);
        }
        return $original_plaintext;
    }

     /**
     * Decode a string with URL-safe Base64.
     * @param string $input A Base64 encoded string
     * @return string A decoded string
     * @ignore
     */
    private static function urlsafeB64Decode(string $input): string
    {
        $remainder = \strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= \str_repeat('=', $padlen);
        }
        return \base64_decode(\strtr($input, '-_', '+/'));
    }

    /**
     * Encode a string with URL-safe Base64.
     * @param string $input The string you want encoded
     * @return string The base64 encode of what you passed in
     * @ignore
     */
    private static function urlsafeB64Encode(string $input): string {
        return \str_replace('=', '', \strtr(\base64_encode($input), '+/', '-_'));
    }

    /**
     * Ritorna l'header Authorization
     */
    private static function  getAuthorizationHeader() {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim(wp_kses($_SERVER["Authorization"], false));
        }
        else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim(wp_kses($_SERVER["HTTP_AUTHORIZATION"], false));
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        if (substr($headers, 0, 7) == 'Bearer ' && strlen($headers) > 7) {
            return substr($headers, 7);
        }
        return $headers;
    }

}

