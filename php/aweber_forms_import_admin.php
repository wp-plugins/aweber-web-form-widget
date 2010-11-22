<div class="wrap">
    <h2>AWeber Web Form Options</h2>
    <form name="aweber_forms_import_form" method="post" action="options.php">
        <?php wp_nonce_field('update-options'); ?>
        <input type="hidden" name="aweber_forms_import_hidden" value="Y">
        <table class="form-table">
            
            <?php
                $pluginAdminOptions = get_option('AWeberWebformPluginAdminOptions');
                settings_fields('AWeberWebformOauth');
                $oauth_removed = get_option('aweber_webform_oauth_removed');
                $oauth_id = get_option('aweber_webform_oauth_id');

                $authorize_success = False;
                $button_value = 'Make Connection';

                // Check to see if they removed the connection
                $authorization_removed = False;
                if ($oauth_removed == 'TRUE') $authorization_removed = True;

                if ($authorization_removed) {
                    update_option('AWeberWebformPluginAdminOptions', array(
                        'consumer_key' => '',
                        'consumer_secret' => '',
                        'access_key' => '',
                        'access_secret' => '',
                    ));
                    $pluginAdminOptions = get_option('AWeberWebformPluginAdminOptions');
                    echo '<div id="message" class="updated"><p>Your connection to your AWeber account has been closed.</p></div>';
                }
                elseif ($oauth_id and !$pluginAdminOptions['access_secret']) {
                    // Then they just saved a key and didn't remove anything
                    // Check it's validity then save it for later use
                    
                    try {
                        list($consumer_key, $consumer_secret, $access_key, $access_secret) = AWeberAPI::getDataFromAweberID($oauth_id);
                    } catch (AWeberException $e) {
                        list($consumer_key, $consumer_secret, $access_key, $access_secret) = null;
                    }
                    if (!$access_secret) {
                        echo $this->messages['access_token_failed'];
                    } else {
                        update_option('AWeberWebformPluginAdminOptions', array(
                            'consumer_key' => $consumer_key,
                            'consumer_secret' => $consumer_secret,
                            'access_key' => $access_key,
                            'access_secret' => $access_secret,
                        ));
                    }
                    $pluginAdminOptions = get_option('AWeberWebformPluginAdminOptions');
                }
                if ($pluginAdminOptions['access_key']) {
                    extract($pluginAdminOptions);
                    $aweber = $this->_get_aweber_api($consumer_key, $consumer_secret);
                    try {
                        $account = $aweber->getAccount($access_key, $access_secret);
                    } catch (AWeberResponseError $e) {
                        $account = null;
                    }
                    
                    if (!$account) {
                        echo $this->messages['auth_failed'];
                    }
                    
                    $authorize_success = True;
                    $button_value = 'Remove Connection';
                } 

                // Checks to see if the widget is installed
                // Not used yet, waiting on wireframe
                $aweber_option_name = strtolower($this->widgetOptionsName);
                $installed_widget = false;

                foreach (get_option('sidebars_widgets') as $widget){
                    if (is_array($widget) and in_array($aweber_option_name, $widget)) {
                        $installed_widget = true;
                    } 
                }
            ?>
            <?php if ($authorize_success): 
            ?>

            <p>You've successfully connected to your AWeber account!</p>
            <p>Next step - go to the <a href="widgets.php">Widgets Page</a> and drag the AWeber Web Form widget into your widget area.</p>
                <input type="hidden" name="aweber_webform_oauth_removed" value="TRUE" />
            <?php else: ?>
                <tr valign="top">
                <th scope="row">Step 1:</th>
                <td><a target="_blank" 
                       href="https://auth.aweber.com/1.0/oauth/authorize_app/f49b1bcf">Click here to get your authorization code</a>.
                </tr>

                <tr valign="top">
                <th scope="row">Step 2: Paste in your authorization code:</th>
                <td><input type="text" name="aweber_webform_oauth_id"
                           value="<?php echo $oauth_id; ?>" /></td>
            <?php endif ?>
            <?php if ($authorization_removed or $authorize_success): ?>
                <script type="text/javascript" >
                    jQuery.noConflict();
                    jQuery("#setting-error-settings_updated").hide();
                    jQuery("#aweber_auth_error").hide();
                </script>
            <?php endif ?>
        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="page_options" value="aweber_webform_oauth_id" />
        </table>

                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php _e($button_value) ?>" />
                </p>
    </form>
</div>
