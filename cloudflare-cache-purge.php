<?php
/*
Plugin Name: Cloudflare Cache Purge
Description: Automatically purges Cloudflare cache based on user-selected interval.
Date: 26/08/2023
Created by: wagnerschaider
Version: 1.0
*/

// Hook to schedule the cache purge event
add_action('wp', 'schedule_cache_purge');

// Schedule the cache purge event on plugin activation
register_activation_hook(__FILE__, 'schedule_cache_purge');

// Unschedule the cache purge event on plugin deactivation
register_deactivation_hook(__FILE__, 'unschedule_cache_purge');

// Function to schedule cache purge event
function schedule_cache_purge() {
    if (!wp_next_scheduled('cloudflare_cache_purge_event')) {
        wp_schedule_event(time(), 'cloudflare_cache_purge_interval', 'cloudflare_cache_purge_event');
    }
}

// Function to unschedule cache purge event
function unschedule_cache_purge() {
    wp_clear_scheduled_hook('cloudflare_cache_purge_event');
}

// Hook to trigger cache purge
add_action('cloudflare_cache_purge_event', 'purge_cloudflare_cache');

// Function to purge Cloudflare cache
function purge_cloudflare_cache() {
    $options = get_option('cloudflare_cache_purge_options');
    
    if (!$options['cache_purge_enabled']) {
        return;
    }

    $cloudflare_email = isset($options['cloudflare_email']) ? $options['cloudflare_email'] : '';
    $cloudflare_api_key = isset($options['cloudflare_api_key']) ? $options['cloudflare_api_key'] : '';
    $zone_id = isset($options['cloudflare_zone_id']) ? $options['cloudflare_zone_id'] : '';

    $url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
    $headers = array(
        'X-Auth-Email: ' . $cloudflare_email,
        'X-Auth-Key: ' . $cloudflare_api_key,
        'Content-Type: application/json'
    );

    $data = array('purge_everything' => true);

    $response = wp_safe_remote_post($url, array(
        'headers' => $headers,
        'body' => json_encode($data)
    ));

    // Check the HTTP status code in the response
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code === 200) {
        // Cache purged successfully
        error_log('Cache purged successfully.');
    } else {
        // Cache purge failed
        error_log('Cache purge failed. Response code: ' . $response_code);
    }
}

// Hook to add settings page to the admin menu
add_action('admin_menu', 'cloudflare_cache_purge_settings_menu');

// Function to add settings page
function cloudflare_cache_purge_settings_menu() {
    add_options_page(
        'Cloudflare Cache Purge Settings',
        'Cloudflare Cache Purge',
        'manage_options',
        'cloudflare-cache-purge-settings',
        'cloudflare_cache_purge_settings_page'
    );
}

function cloudflare_cache_purge_initialize_options() {
    $default_options = array(
        'cache_purge_enabled' => false,
        'cache_purge_interval' => 60 // Default interval: 1 hour
    );
    add_option('cloudflare_cache_purge_options', $default_options);
}
add_action('admin_init', 'cloudflare_cache_purge_initialize_options');


// Function to display settings page content
function cloudflare_cache_purge_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $options = get_option('cloudflare_cache_purge_options');
    
    if (isset($_POST['cloudflare_cache_purge_submit'])) {
        $options['cache_purge_enabled'] = ($_POST['cache_purge_enabled'] === '1');
        $options['cloudflare_email'] = sanitize_text_field($_POST['cloudflare_email']);
        $options['cloudflare_api_key'] = sanitize_text_field($_POST['cloudflare_api_key']);
        $options['cloudflare_zone_id'] = sanitize_text_field($_POST['cloudflare_zone_id']);
        $options['cache_purge_interval'] = intval($_POST['cache_purge_interval']);
        update_option('cloudflare_cache_purge_options', $options);
        
        echo '<div class="notice notice-success"><p>Configuration updated.</p></div>';
    }
    
    $cloudflare_email = isset($options['cloudflare_email']) ? $options['cloudflare_email'] : '';
    $cloudflare_api_key = isset($options['cloudflare_api_key']) ? $options['cloudflare_api_key'] : '';
    $cloudflare_zone_id = isset($options['cloudflare_zone_id']) ? $options['cloudflare_zone_id'] : '';
    $cache_purge_interval = isset($options['cache_purge_interval']) ? $options['cache_purge_interval'] : 60;
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p style="font-size: 12px; color: #666;">Version: 1.0 | Created by: wagnerschaider</p>
        <form method="post" action="">
            <?php settings_fields('cloudflare_cache_purge_options'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Cloudflare Email:</th>
                    <td>
                        <input type="text" name="cloudflare_email" value="<?php echo esc_attr($cloudflare_email); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Cloudflare API Key:</th>
                    <td>
                        <input type="text" name="cloudflare_api_key" value="<?php echo esc_attr($cloudflare_api_key); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Cloudflare Zone ID:</th>
                    <td>
                        <input type="text" name="cloudflare_zone_id" value="<?php echo esc_attr($cloudflare_zone_id); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Cache Purge Interval:</th>
                    <td>
                        <select name="cache_purge_interval">
                            <option value="30" <?php selected($cache_purge_interval, 30); ?>>30 minutes</option>
                            <option value="60" <?php selected($cache_purge_interval, 60); ?>>1 hour</option>
                            <option value="180" <?php selected($cache_purge_interval, 180); ?>>3 hours</option>
                            <option value="360" <?php selected($cache_purge_interval, 360); ?>>6 hours</option>
                            <option value="720" <?php selected($cache_purge_interval, 720); ?>>12 hours</option>
                            <option value="1440" <?php selected($cache_purge_interval, 1440); ?>>1 day</option>
                            <option value="4320" <?php selected($cache_purge_interval, 4320); ?>>3 days</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Enable Cache Purge:</th>
                    <td>
                        <label for="cache_purge_enabled">
                            <input type="checkbox" name="cache_purge_enabled" id="cache_purge_enabled" value="1" <?php checked($options['cache_purge_enabled'], true); ?>>
                            Enabled
                        </label>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="cloudflare_cache_purge_submit" class="button-primary" value="Save Changes">
            </p>
        </form>
    </div>
    <?php
}
?>
