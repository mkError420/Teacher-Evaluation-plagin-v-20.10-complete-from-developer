<?php
if (!defined('ABSPATH')) exit;

function tes_settings_page() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tes_save_settings'])) {
        if (!current_user_can('manage_options')) {
            wp_die('You are not allowed to manage these settings.');
        }
        check_admin_referer('tes_save_settings');

        update_option(
            'tes_student_email_verification_enabled',
            isset($_POST['tes_student_email_verification_enabled']) ? '1' : '0'
        );
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    $email_verification_enabled = get_option('tes_student_email_verification_enabled', '0') === '1';
    ?>
    <div class="wrap">
        <h1>Settings & Shortcodes</h1>
        
        <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
            <h2>Student Login</h2>
            <form method="post">
                <?php wp_nonce_field('tes_save_settings'); ?>
                <input type="hidden" name="tes_save_settings" value="1">
                <p>
                    <label>
                        <input type="checkbox" name="tes_student_email_verification_enabled" value="1" <?php checked($email_verification_enabled); ?>>
                        Require students to verify their email address with a one-time code when logging in
                    </label>
                </p>
                <p class="description">When enabled, each student must have a valid email address in the Students page. Codes expire after 10 minutes. This setting is off by default to avoid blocking existing student accounts; add student emails before enabling it.</p>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>

        <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
            <h2>Page Shortcodes</h2>
            <p>Copy and paste the following shortcodes into any WordPress Page to create the login portals.</p>
            
            <table class="widefat striped" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>Portal Type</th>
                        <th>Shortcode</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Student Login & Survey</strong></td>
                        <td><code>[teacher_survey]</code></td>
                        <td>Creates the student login page. After login, students can submit surveys.</td>
                    </tr>
                    <tr>
                        <td><strong>Supervisor Login</strong></td>
                        <td><code>[advisor_dashboard]</code></td>
                        <td>Creates the Supervisor (Advisor) login page to view survey results.</td>
                    </tr>
                    <tr>
                        <td><strong>Teacher Login</strong></td>
                        <td><code>[teacher_dashboard]</code></td>
                        <td>Creates the Teacher login page for teachers to view their own results.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}