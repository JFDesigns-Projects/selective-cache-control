<?php
/**
 * Plugin Name: Selective Cache Control
 * Plugin URI: https://jfdesigns.com/
 * Description: Select specific pages to disable caching and optionally force reload on browser back button. Especially for hosting services like GoDaddy.
 * Version: 1.3.1
 * Author: Jay Forde
 * Author URI: https://jfdesigns.com/
 * License: GPL2
 * GitHub Plugin URI: https://github.com/JFDesigns-Projects/selective-cache-control
 */


if (!defined('ABSPATH')) exit;

// Add Settings Menu
add_action('admin_menu', function () {
    add_options_page('Selective Cache Control', 'Cache Control', 'manage_options', 'selective-cache-control', 'scc_settings_page');
});

// Register Settings
add_action('admin_init', function () {
    register_setting('scc_settings_group', 'scc_selected_pages');
    register_setting('scc_settings_group', 'scc_enable_reload');
});

// Render Settings Page
function scc_settings_page() {
    if (isset($_POST['scc_clear_selected'])) {
        update_option('scc_selected_pages', []);
        echo '<div class="updated notice"><p>Selected pages cleared!</p></div>';
    }

    $selected_pages = (array) get_option('scc_selected_pages', []);
    $enable_reload = get_option('scc_enable_reload', false);
    $pages = get_pages(['post_status' => 'publish']);
    ?>
    <div class="wrap">
        <h1>Selective Cache Control</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('scc_settings_group');
            do_settings_sections('scc_settings_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Select Pages to Disable Caching:</th>
                    <td>
                        <select name="scc_selected_pages[]" multiple style="height: 300px; width: 300px;">
                            <?php foreach ($pages as $page) : ?>
                                <option value="<?php echo esc_attr($page->ID); ?>" <?php selected(in_array($page->ID, $selected_pages)); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Hold Ctrl (Windows) or Command (Mac) to select multiple pages.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Force Reload on Back Button?</th>
                    <td>
                        <input type="checkbox" name="scc_enable_reload" value="1" <?php checked($enable_reload, 1); ?> />
                        <p class="description">Recommended for pages with forms using nonce validation.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <form method="post" action="">
            <input type="submit" name="scc_clear_selected" value="Clear Selected Pages" class="button button-secondary" style="margin-top: 20px;">
        </form>
    </div>
    <?php
}

// Apply No-Cache Headers
add_action('template_redirect', function () {
    if (is_page()) {
        $selected_pages = (array) get_option('scc_selected_pages', []);
        if (!empty($selected_pages) && in_array(get_queried_object_id(), $selected_pages)) {
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
            header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");
        }
    }
});

// Inject JavaScript for Force Reload
add_action('wp_footer', function () {
    if (is_page()) {
        $selected_pages = (array) get_option('scc_selected_pages', []);
        $enable_reload = get_option('scc_enable_reload', false);

        if ($enable_reload && !empty($selected_pages) && in_array(get_queried_object_id(), $selected_pages)) {
            ?>
<script>
window.addEventListener('pageshow', function(event) {
  if (event.persisted || (window.performance && performance.navigation.type === 2)) {
    window.location.reload();
  }
});
</script>
            <?php
        }
    }
});

// Add Admin Bar Status Indicator with Clickable Badge
add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!is_admin() && current_user_can('manage_options') && is_page()) {
        $selected_pages = (array) get_option('scc_selected_pages', []);
        $status = (in_array(get_queried_object_id(), $selected_pages)) ? 'Cache: Disabled' : 'Cache: Active';
        $color = (strpos($status, 'Disabled') !== false) ? '#d63638' : '#00a32a';

        // Admin Bar node with the toggleable click functionality
        $wp_admin_bar->add_node([
            'id'     => 'scc_cache_status',
            'title'  => '<span id="scc-cache-status" style="display:inline-block;padding:2px 8px;border-radius:12px;background:' . esc_attr($color) . ';color:#fff;font-size:12px;font-weight:600;cursor:pointer;">' . esc_html($status) . '</span>',
            'parent' => 'top-secondary',
            'meta'   => ['title' => 'Toggle Cache Status']
        ]);
    }
}, 100);

?>