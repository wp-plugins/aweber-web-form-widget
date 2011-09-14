<?php
/**
    * AWeber Web Form Plugin object
    *
    * Main wordpress interface for integrating your AWeber Web Forms into
    * your blog.
    */
class AWeberWebformPlugin {
    var $adminOptionsName = 'AWeberWebformPluginAdminOptions';
    var $widgetOptionsName = 'AWeberWebformPluginWidgetOptions';
    var $oauthOptionsName = 'AWeberWebformOauth';
    var $messages = array();

    /**
        * Constructor
        */
    function AWeberWebformPlugin() {
        $aweber_settings_url = admin_url('options-general.php?page=aweber.php');
        $this->messages['auth_required'] = '<div id="aweber_auth_error" class="error">AWeber Web Form requires authentication. You will need you to update your <a href="' . $aweber_settings_url . '">settings</a> in order to continue to use AWeber Web Form.</div>';
        $this->messages['auth_error'] = '<div id="aweber_auth_error" class="error">AWeber Web Form authentication failed.  Please verify the <a href="' . admin_url('options-general.php?page=aweber.php') . '">settings</a> to continue to use AWeber Web Form.</div>';
        $this->messages['auth_failed'] = '<div id="aweber_auth_failed" class="error">AWeber Web Form authentication failed.  If this continues, click Remove Connection and re-authorize AWeber Web Form.</div>';
        $this->messages['access_token_failed'] = '<div id="aweber_access_token_failed" class="error">Invalid authorization code.  Please make sure you entered it correctly.</div>';
    }

    /**
        * Plugin initializer
        *
        * Main plugin initialization hook.
        * @return void
        */
    function init() {
        $this->getAdminOptions();
        $this->getWidgetOptions();
    }

    /**
        * Add content to the header tag.
        *
        * Hook for adding additional tags to the document's HEAD tag.
        * @return void
        */
    function addHeaderCode() {
        if (function_exists('wp_enqueue_script')) {
            // Admin page scripts
            if (is_admin()) {
                wp_enqueue_script('jquery');
            }
        }
    }

    /**
        * Get admin panel options.
        *
        * Retrieve admin panel settings variables as stored within wordpress.
        * @return array
        */
    function getAdminOptions() {
        $pluginAdminOptions = array(
            'aweber_id'       => null,
            'consumer_key'    => null,
            'consumer_secret' => null,
            'access_key'      => null,
            'access_secret'   => null,
        );
        $options = get_option($this->adminOptionsName);
        if (!empty($options)) {
            foreach ($options as $key => $option) {
                $pluginAdminOptions[$key] = $option;
            }
        }
        update_option($this->adminOptionsName, $pluginAdminOptions);
        return $pluginAdminOptions;
    }

    /**
        * Print admin panel settings page.
        *
        * Echo the HTML for the admin panel settings page.
        * @return void
        */
    function printAdminPage() {
        $options = $this->getAdminOptions();
        include(dirname(__FILE__) . '/aweber_forms_import_admin.php');
    }

    /**
        * Get widget options.
        *
        * Retrieve widget control settings variables as stored within wordpress.
        * @return array
        */
    function getWidgetOptions() {
        $pluginWidgetOptions = array(
            'list'         => null,
            'webform'      => null,
            'form_snippet' => null,
        );
        $options = get_option($this->widgetOptionsName);
        if (!empty($options)) {
            foreach ($options as $key => $option) {
                $pluginWidgetOptions[$key] = $option;
            }
        }
        update_option($this->widgetOptionsName, $pluginWidgetOptions);
        return $pluginWidgetOptions;
    }

    /**
        * Print widget in sidepanel.
        *
        * Echo the HTML for the widget handle.
        * @return void
        */
    function printWidget($args) {
        extract($args, EXTR_SKIP);
        echo $before_widget;
        if ($title) {
            echo $before_title . $title . $after_title;
        }
        echo $this->getWebformSnippet();
        echo $after_widget;
    }

    /**
        * Get a new AWeber API object
        *
        * Wrapper for AWeber API generation
        * @return AWeberAPI
        */
    function _get_aweber_api($consumer_key, $consumer_secret) {
        return new AWeberAPI($consumer_key, $consumer_secret);
    }

    /**
        * Print widget settings control in admin panel.
        *
        * Store settings and echo HTML for the widget control.
        * @return void
        */
    function printWidgetControl() {
        if (isset($_POST[$this->widgetOptionsName])) {
            $options = $this->getWidgetOptions();
            $widget_data = $_POST[$this->widgetOptionsName];
            if (isset($widget_data['submit']) && $widget_data['submit']) {
                $options['list'] = $widget_data['list'];
                $options['webform'] = $widget_data[$widget_data['list']]['webform'];
                if ($options['webform']) {
                    $admin_options = $this->getAdminOptions();
                    $aweber = $this->_get_aweber_api($admin_options['consumer_key'], $admin_options['consumer_secret']);
                    try {
                        $account = $aweber->getAccount($admin_options['access_key'], $admin_options['access_secret']);
                    } catch (AWeberException $e) {
                        $account = null;
                    }
                    if ($account) {
                        $this_form = $account->loadFromUrl($options['webform']);
                        $options['form_snippet'] = '<script type="text/javascript" src="' . $this->_getWebformJsUrl($this_form) . '"></script>';
                    }
                } else {
                    $options['form_snippet'] = '';
                }

                update_option($this->widgetOptionsName, $options);
            }
        } else {
            ?>
            <div id="<?php echo $this->widgetOptionsName; ?>-content" class="<?php echo $this->widgetOptionsName; ?>-content"><img src="images/loading.gif" height="16" width="16" id="aweber-webform-loading" style="float: left; padding-right: 5px" /> Loading...</div>
            <script type="text/javascript" >

            jQuery(document).ready(function($) {
                if (typeof(<?php echo $this->widgetOptionsName; ?>) != 'undefined') { return; }
                <?php echo $this->widgetOptionsName; ?> = true;

                var data = {
                    action: 'get_widget_control'
                };

                // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
                jQuery.post(ajaxurl, data, function(response) {
                    var primary_content = jQuery('.<?php echo $this->widgetOptionsName; ?>-content');
                    primary_content.each(function() { jQuery(this).html(response); });
                });
            });
            </script>
            <?php
        }
    }

    /**
        * Get Web Form javascript url
        *
        * Returns hosted javascript url of a given form.
        * @param AWeberEntry
        * @return string
        */
    function _getWebformJsUrl($webform) {
        $form_hash = $webform->id % 100;
        $form_hash = (($form_hash < 10) ? '0' : '') . $form_hash;
        $prefix = ($this->_isSplitTest($webform)) ? 'split_' : '';
        return 'http://forms.aweber.com/form/' . $form_hash . '/' . $prefix . $webform->id . '.js';
    }

    /**
        * Is a split test?
        *
        * Returns whether form object is a splittest.
        * @param AWeberEntry
        * @return bool
        */
    function _isSplitTest($webform) {
        return $webform->type == 'web_form_split_test';
    }

    function _end_response() {
        die();
    }

    /**
        * Response to be given to print action via AJAX.
        *
        * Echo HTML for widget control form asynchronously.
        * @return void
        */
    function printWidgetControlAjax() {
        $options = $this->getWidgetOptions();
        $admin_options = $this->getAdminOptions();

        // Render form
        $list = $options['list'];
        $webform = $options['webform'];

        $aweber = $this->_get_aweber_api($admin_options['consumer_key'], $admin_options['consumer_secret']);
        try {
            $account = $aweber->getAccount($admin_options['access_key'], $admin_options['access_secret']);
        } catch (AWeberException $e) {
            $account = null;
        }
        if (!$account) {
            echo $this->messages['auth_error'];
            return $this->_end_response();
        }

        $list_web_forms = array();
        foreach ($account->getWebForms() as $this_webform) {
            $link_parts = explode('/', $this_webform->url);
            $list_id = $link_parts[4];
            if (!array_key_exists($list_id, $list_web_forms)) {
                $list_web_forms[$list_id] = array(
                    'web_forms' => array(),
                    'split_tests' => array()
                );
            }
            $list_web_forms[$list_id]['web_forms'][] = $this_webform;
        }
        foreach ($account->getWebFormSplitTests() as $this_webform) {
            $link_parts = explode('/', $this_webform->url);
            $list_id = $link_parts[4];
            if (!array_key_exists($list_id, $list_web_forms)) {
                $list_web_forms[$list_id] = array(
                    'web_forms' => array(),
                    'split_tests' => array()
                );
            }
            $list_web_forms[$list_id]['split_tests'][] = $this_webform;
        }
        $lists = $account->lists;
        foreach ($lists as $this_list) {
            if (array_key_exists($this_list->id, $list_web_forms)) {
                $list_web_forms[$this_list->id]['list'] = $this_list;
            }
        }

        // The HTML form will go here
?>
<?php if (!empty($list_web_forms)): ?>
<select class="widefat <?php echo $this->widgetOptionsName; ?>-list" name="<?php echo $this->widgetOptionsName; ?>[list]" id="<?php echo $this->widgetOptionsName; ?>-list">
    <option value="">Step 1: Select A List</option>
    <?php foreach ($list_web_forms as $this_list_data): ?>
    <?php $this_list = $this_list_data['list']; ?>
    <option value="<?php echo $this_list->id; ?>"<?php echo ($this_list->id == $list) ? ' selected="selected"' : ""; ?>><?php echo $this_list->name; ?></option>
    <?php endforeach; ?>
</select>

<br />
<br />
<?php foreach ($list_web_forms as $this_list_id => $forms): ?>
<select class="widefat <?php echo $this->widgetOptionsName; ?>-form-select <?php echo $this->widgetOptionsName; ?>-<?php echo $this_list_id; ?>-webform" name="<?php echo $this->widgetOptionsName; ?>[<?php echo $this_list_id; ?>][webform]" id="<?php echo $this->widgetOptionsName; ?>-<?php echo $this_list_id; ?>-webform">
    <option value="">Step 2: Select A Web Form</option>
    <?php foreach ($forms['web_forms'] as $this_form): ?>
    <option value="<?php echo $this_form->url; ?>"<?php echo ($this_form->url == $webform) ? ' selected="selected"' : ''; ?>><?php echo $this_form->name; ?></option>
    <?php endforeach; ?>
    <?php foreach ($forms['split_tests'] as $this_form): ?>
    <option value="<?php echo $this_form->url; ?>"<?php echo ($this_form->url == $webform) ? ' selected="selected"' : ''; ?>>Split test: <?php echo $this_form->name; ?></option>
    <?php endforeach; ?>
</select>
<?php endforeach; ?>

<input type="hidden"
    name="<?php echo $this->widgetOptionsName; ?>[submit]"
    value="1"/>

<br /><br />
<a id="<?php echo $this->widgetOptionsName; ?>-form-preview" class="<?php echo $this->widgetOptionsName; ?>-form-preview" href="#" target="_blank">preview form</a>
<?php else: ?>
This AWeber account does not currently have any completed web forms.
<br /><br />
Please <a href="https://www.aweber.com/users/web_forms/index">create a web
form</a> in order to place it on your Wordpress blog.
<?php endif; ?>
<script type="text/javascript">
    jQuery(document).ready(function() {
        function hideFormSelectors() {
            jQuery('.<?php echo $this->widgetOptionsName; ?>-form-select').each(function() {
                jQuery(this).hide();
            });
        }

        function listDropDown() {
            return jQuery('.<?php echo $this->widgetOptionsName; ?>-list');
        }

        function currentFormDropDown() {
            var list;
            listDropDown().each(function() {
                list = jQuery(this).val();
            });
            if (list != "") {
                return jQuery('.<?php echo $this->widgetOptionsName; ?>-' + list + '-webform');
            }
            return undefined;
        }

        function updateViewableFormSelector() {
            hideFormSelectors();
            var dropdown = currentFormDropDown();
            if (dropdown != undefined) {
                dropdown.each(function() {
                    jQuery(this).show();
                });
            }
        }

        function updatePreviewLink() {
            var form_url = "";
            var preview = jQuery('.<?php echo $this->widgetOptionsName; ?>-form-preview');
            var form_dropdown = currentFormDropDown();
            if (form_dropdown != undefined) {
                form_dropdown.each(function() {
                    form_url = jQuery(this).val();
                });
            }
            if (form_url == "") {
                preview.each(function() {
                    jQuery(this).attr('href', '#');
                    jQuery(this).hide();
                });
            } else {
                form_url = form_url.split('/');
                var form_id = form_url.pop();
                var form_type = form_url.pop();
                if (form_type == 'web_form_split_tests') {
                    preview.each(function() {
                        jQuery(this).attr('href', '#');
                        jQuery(this).hide();
                    });
                } else {
                    preview.each(function() { jQuery(this).show(); });
                    var hash = form_id % 100;
                    hash = ((hash < 10) ? '0' : '') + hash;
                    preview.each(function() {
                        jQuery(this).attr('href', 'http://forms.aweber.com/form/' + hash + '/' + form_id + '.html');
                    });
                }
            }
        }

        updateViewableFormSelector();
        updatePreviewLink();

        listDropDown().each(function() {
            jQuery(this).change(function() {
                updateViewableFormSelector();
                var form_dropdown = currentFormDropDown();
                if (form_dropdown != undefined) {
                    form_dropdown.val('');
                }
                updatePreviewLink();
            });
        });
        jQuery('.<?php echo $this->widgetOptionsName; ?>-form-select').each(function() {
            jQuery(this).change(function() {
                updatePreviewLink();
            });
        });
    });
</script>

<?php
        $this->_end_response();
    }

    /**
        * Get web form snippet
        *
        * Retrieve webform snippet to be inserted in blog page.
        * @return string
        */
    function getWebformSnippet() {
        $options = $this->getWidgetOptions();
        return $options['form_snippet'];
    }
}
?>
