<?php
/**
 * La struttura dei dati delle opzioni
 * Le variabile pubbliche sono le chiavi delle opzioni
*/
namespace sucw;

class SUCW_Options {

    /** @var $mode string server|client */
    public $mode = ''; 
    /** @var $server_url string */
    public $server_url = '';
    /** @var $private_key string */
    public $private_key = '';
    /** @var $log_file_name string il nome del log è randomico per protezione */
    public $log_file_name = '';

    function __construct() {
        $options = get_option('sucw_options');
        if (is_array($options)) {
            $this->mode = $options['mode'];
            $this->server_url = $options['server_url'];
            $this->private_key = $options['private_key'];
            if (!isset($options['log_file_name']) || $options['log_file_name'] == '') {
                $options['log_file_name'] =  'log_'. uniqid();
                $this->log_file_name = $options['log_file_name'];
                $this->save();
            } else {
                $this->log_file_name = $options['log_file_name'];
            }
        } else {
            $this->log_file_name =' log_'. uniqid();
        }
            
    }

    /**
     * Salva l'opzione richiesta
     * @param string $option
     * @param string $value
     * @return bool
     */
    public function set_option($option, $value) {
        // verifico se la proprietà esiste in questa classe
        if (property_exists($this, $option)) {
            $this->$option = $value;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Restituisce l'opzione richiesta
     * @return void
     */
    public function save() {
        // estraggo le variabili pubbliche della classe
        $vars = get_class_vars(__CLASS__);
        $options = [];
        foreach ($vars as $var => $_) {
            $options[$var] = $this->$var;
        }
        update_option('sucw_options', $options);
    }


    /**
     * Restituisce l'opzione richiesta
     * @param string $option
     * @return string
     */
    public function get_option($option = '') {
        if (property_exists($this, $option)) {
            return $this->$option;
        }
        return false;
    }

}