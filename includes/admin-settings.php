<?php
if (!defined('ABSPATH')) exit;

function tes_settings_page() {
    ?>
    <div class="wrap">
        <h1>Settings & Shortcodes</h1>
        
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