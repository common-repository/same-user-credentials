<?php 
/**
 *  Creo la classe per la gestione dei log
 *  I log mantengono  gli ultimi 1000 log
 */
namespace sucw;

class SUCW_log 
{
    //@var array $log contiene i log
    static $logs = [];
    static $is_loaded = false;

    static function get() {
        if (self::$is_loaded) return self::$logs;
        if (is_file(self::get_log_file())) {
            $file = self::file_get_contents(self::get_log_file());
        } else {
            $file = '';
        }
        
        self::$is_loaded = true;
        self::$logs = explode("\n", $file);
        // inverti l'array
        return self::$logs;
    }

    /**
     * @var string $type | info | error | warning
     */
    static function add($user_login, $msg, $type = 'info', $response = '' ) {
        $logs = self::get();
        /**
         * Filter sucw-log-limit
         * Il numero di log da mantenere
         * @param int $log_limit Default 1000
         * @since 1.0.0
         */
        $log_limit = apply_filters('sucw-log-limit', 1000);
        if ($log_limit == 0) {
            return;
        }
        if (count($logs) > $log_limit) {
            $logs = array_slice($logs, -$log_limit);
        }
        $msg = str_replace(["\n","\t"], ' ', $msg);
        // trovo l'ip della chiamata
        $origin = self::get_ip();
        $response_name = self::save_last_tot_response($response);
        
        $logs[] = gmdate('Y-m-d H:i:s')."\t". $origin."\t".$user_login."\t".$msg."\t".$response_name."\t".$type;
        self::$logs = $logs;
       
        if (!wp_is_writable(self::get_log_dir())) {
            return;
        }
        self::file_put_contents(self::get_log_file(), implode("\n", $logs));
    }

    static function save_last_tot_response($response) {
        if ($response == '') return '-';
        // ciclo tutti i file e mantengo solo gli ultimi 10 response ordinati per data
        $files = glob(SUCW_PLUGIN_DIR . '/logs/response_*.txt');
        if (count($files) > 10) {
            usort($files, function($a, $b) {
                // uso il time inserito nel nome response_time()_md5
                $a = explode('_', basename($a));
                $b = explode('_', basename($b));
                if ($a[0] == $b[0] && $a[0] == 'response') {
                    return $a[1] - $b[1];
                } else {
                    return 0;
                }

            });
            $files = array_slice($files, -10);
            foreach($files as $file) {
                wp_delete_file($file);
            }
        }
        
        if (is_array($response) || is_object($response)) {
            $response = wp_json_encode($response);
        }
        $response_name = sanitize_file_name(time()."_".md5($response)).'.txt'; 
        // verifico se la cartella è scrivibile
        if (!wp_is_writable(self::get_log_dir())) {
            return '';
        }
        self::file_put_contents(self::get_log_dir().'/response_'.$response_name, $response);
        return $response_name;
    }

    /**
     * Restituisce il file di log
     * @return string
     */
    static function get_log_file() {
        //SUCW_PLUGIN_DIR . '/logs/log.log';
        $log_file = SUCW_Fn::get_opt('log_file_name');
        $log_file = sanitize_file_name($log_file);
        if ($log_file == '') {
            return '';
        }
        $dir = self::get_log_dir();
        $log_file = $dir . $log_file .'.log';
        // security path
        $log_file = str_replace('..', '', $log_file);
        return $log_file;
    }

    /**
     * Ritorna la cartella dei log
     * @return string
     */
    static function get_log_dir() {
        $dir = wp_upload_dir();
        $dir = $dir['basedir'].'/sucw-logs/';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
            self::file_put_contents($dir.'index.php', '<?php /* Silence is golden */');
        }
        return $dir;
    }

    static function get_log_path() {
        $dir = wp_upload_dir();
        return $dir['baseurl'].'/sucw-logs/';
    }

    /**
     * Restituisce l'indirizzo IP del client
     * Al momento non usata (verrà usata nei log)
     * @return string
     */
    static function get_ip() {
        $client_ip='';
        if (! empty($_SERVER['HTTP_CLIENT_IP'])) {
            // to get shared ISP IP address
            $client_ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } 
        else if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $client_ip = end(explode(',', sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR'])));
        }
        else if (! empty($_SERVER['HTTP_X_FORWARDED'])) {
            $client_ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED']);
        } 
        else if (! empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
            $client_ip = sanitize_text_field($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']);
        } 
        else if (! empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $client_ip = sanitize_text_field($_SERVER['HTTP_FORWARDED_FOR']);
        } 
        else if (! empty($_SERVER['HTTP_FORWARDED'])) {
            $client_ip = sanitize_text_field($_SERVER['HTTP_FORWARDED']);
        } 
        else if (! empty($_SERVER['REMOTE_ADDR'])) {
            $client_ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
        return $client_ip;
    }

    static private function file_get_contents($file) {
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }
        return $wp_filesystem->get_contents($file);
    }


    static private function file_put_contents($file, $content) {
        global $wp_filesystem;
        if ($file == "") return false;
        $dir = dirname($file);
        if (!is_dir($dir)) return false;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }
        return $wp_filesystem->put_contents($file, $content);
    }
}