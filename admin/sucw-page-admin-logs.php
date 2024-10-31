<?php namespace sucw;

use DateTime;

 ?>
<div class="wrap">
    <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
    <?php if (!wp_is_writable(SUCW_log::get_log_dir())) : ?>
        <div class="sucw-alert-error">
            Log folder is not writable!! Please check the permissions of the folder <strong><?php echo esc_html(SUCW_PLUGIN_DIR . 'logs'); ?></strong>
        </div>
        <br>
    <?php endif; ?>

    <?php if (!is_array($logs)) : ?>
        <div class="sucw-alert-info">No log has been generated yet</div>
        <?php // verifico se è possibile creare un file dentro la cartella log ?>
        
          
    <?php else : ?> 
        <?php $count = 0; ?>
       
        <?php foreach($logs as $log) :?>
            <?php 
            if ($log == '') continue;
            $count++;
            $log = explode("\t", $log);
            $type = trim(array_pop($log));
            $link_response = array_pop($log);
            // estraggo il primo elemento
            $log_time = array_shift($log);
            $url = array_shift($log);
            $time = new DateTime($log_time);
            // local time
            $local_timezone = get_option('timezone_string');
            if (is_a($time, 'DateTime')) {
                // se non è lo stesso giorno
                if (isset($old_time) && $old_time->format('Y-m-d') != $time->format('Y-m-d')) {
                    echo "<h3>".esc_html($time->format('Y-m-d'))."</h3>";
                }
                $old_time = $time;
            }
            
            $txt_log = implode(" - ", $log);
           
            ?>
            <div class="sucw-log-row sucw-alert-<?php echo esc_attr($type); ?>">
                <?php  echo esc_html("#".$count).' '. esc_html($log_time) . ' - <b>'.esc_html($url).'</b> - '.esc_html($txt_log); ?>
                <?php if ($link_response != '-' && is_file(SUCW_log::get_log_dir(). 'response_'.$link_response)) : ?>
                    <?php $url = SUCW_log::get_log_path().'/response_'.$link_response; ?>
                    <a href="<?php echo esc_attr($url); ?>" target="_blank">Response</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>