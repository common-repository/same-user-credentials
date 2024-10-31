<?php namespace sucw; ?>
<div class="wrap">
    <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
    <form action="admin.php?page=sucw" method="post" id="sucwSubmitForm">
        <input type="hidden" name="action" value="sucw_save_options">
        <?php wp_nonce_field('sucw');?>
        <h3 class="sucw-h3"><?php esc_html_e('Select whether the plugin should run as a server or client.', 'same-user-credentials'); ?></h3>

        <div class="sucw-grid-2cols">
            <?php
            $this->display_server_field();
            $this->display_client_field();
            ?>
        </div>
        <?php $this->display_messages(); ?>
        <div id="server-settings" style="display: none;">
             <?php // Sezione SERVER ?>
            <?php $this->server_private_key_field(); ?>
        </div>
        <div id="client-settings" style="display: none;">
            <?php // Sezione CLIENT ?>
            <?php  $this->server_url_field(); ?>
          
            <?php $this->private_key_field(); ?>
        </div>
        <div class="submit"><div name="submit" id="submit" class="button button-primary" onclick="sucw_submit_form()"><?php esc_html_e('Save Settings', 'same-user-credentials'); ?></div>
        </div>
      
    </form>
</div>