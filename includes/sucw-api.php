<?php
namespace sucw;
/**
 * La classe Api gestisce le api appunto per il login
 * Quindi il server
 */

class SUCW_Api
{
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        if (SUCW_Fn::is_server()) {
            register_rest_route('sucw/v1', '/login', array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_login_request'),
            ));

            register_rest_route('sucw/v1', '/check-user', array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_check_user_request'),
            ));

            register_rest_route('sucw/v1', '/check-configuration', array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_check_configuration_request'),
            ));
        }
    }

    /**
     * Fa il login sul server
     */
    public function handle_login_request($request)
    {
        $user = SUCW_auth::server_get_user_from_bearer(true);
        if (!$user) {
            $response = ['response_status'=>'error', 'message'=>'Invalid credentials'];
        } else {
            $response = ['response_status'=>'ok', 'user'=>$user];
        }
         /**
         * Filter sucw-api-response
         * La risposta del server alla chiamata api del client di login
         * @param array $response ['response_status'=>'ok', 'user'=>$user] | ['response_status'=>'error', 'message'=>'...']
         * @param string $type login | check-user
         * @since 1.0.0
         */
        $response = apply_filters('sucw-api-response', $response, 'login');
        return new \WP_REST_Response( $response, 200);
    }

    /**
     * Ritorna i dati dell'utente
     */
    public function handle_check_user_request($request) {
        $user = SUCW_auth::server_get_user_from_bearer(false);
        if (!$user) {
            if (SUCW_auth::$last_error == 'invalid_user') {
                $response = ['response_status'=>'invalid-user'];
            } else if (SUCW_auth::$last_error == 'token_expired') {
                $response = ['response_status'=>'token-expired'];
            } else {
                $response = ['response_status'=>'error'];
            }
        } else {
            $response = ['response_status'=>'ok', 'user' => $user];
        }
        /**
         * Filter sucw-api-response
         * La risposta del server alla chiamata api del client di login
         * @param array $response ['response_status'=>'ok', 'user'=>$user] | ['response_status'=>'error', 'message'=>'...']
         * @param string $type login | check-user
         * @since 1.0.0
         */
        $response = apply_filters('sucw-api-response', $response, 'check-user');
        // ritorno tutti i dati dell'utente
        return new \WP_REST_Response($response, 200);
    }

    /**
     * Verifica se la configurazione del client è corretta
     */
    public function handle_check_configuration_request($request) {
        $check = SUCW_auth::server_check_bearer(false);
        if ($check != 'ok') {
            return  new \WP_REST_Response(['response_status'=>'error', 'msg'=>$check], 200);
        }
        return new \WP_REST_Response(['response_status'=>'ok'], 200);
    }
}
