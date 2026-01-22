<?php
if (!defined('ABSPATH')) exit;

function tes_supervisors_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'tes_teachers';

    // Add/Edit Supervisor
    if (isset($_POST['tes_save_supervisor']) && !empty($_POST['supervisor_name'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'tes_save_supervisor')) {
            wp_die('Security check failed');
        }

        $name = sanitize_text_field($_POST['supervisor_name']);
        $username = sanitize_text_field($_POST['supervisor_username']);
        $password = $_POST['supervisor_password'];
        $department = isset($_POST['supervisor_department']) ? sanitize_text_field($_POST['supervisor_department']) : '';
        $designation = isset($_POST['supervisor_designation']) ? sanitize_text_field($_POST['supervisor_designation']) : '';
        $class_name = isset($_POST['supervisor_class']) ? sanitize_text_field($_POST['supervisor_class']) : '';
        $phase = isset($_POST['supervisor_phase']) ? sanitize_text_field($_POST['supervisor_phase']) : '';
        $supervisor_id = !empty($_POST['supervisor_id']) ? intval($_POST['supervisor_id']) : null;

        // Validation
        if (empty($name) || empty($username)) {
             echo '<div class="error notice"><p>Name and Username are required.</p></div>';
        } else {
            // Check Username Uniqueness
            $user_check = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE username = %s AND id != %d",
                $username,
                $supervisor_id ?: 0
            ));

            if ($user_check > 0) {
                echo '<div class="error notice"><p>Username already exists.</p></div>';
            } else {
                $data = [
                    'name' => $name,
                    'username' => $username,
                    'role' => 'advisor',
                    'department' => $department,
                    'designation' => $designation,
                    'class_name' => $class_name,
                    'phase' => $phase
                ];

                if (!empty($password)) {
                    $data['password'] = $password;
                }

                if ($supervisor_id) {
                    $wpdb->update($table, $data, ['id' => $supervisor_id]);
                    echo '<div class="updated notice"><p>Supervisor updated successfully.</p></div>';
                } else {
                    if (empty($password)) {
                         echo '<div class="error notice"><p>Password is required for new supervisors.</p></div>';
                    } else {
                        $data['password'] = $password;
                        $wpdb->insert($table, $data);
                        echo '<div class="updated notice"><p>Supervisor added successfully.</p></div>';
                    }
                }
            }
        }
    }

    // Delete Supervisor
    if (isset($_GET['delete'])) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'tes_delete_supervisor')) {
            wp_die('Security check failed');
        }
        $wpdb->delete($table, ['id' => intval($_GET['delete'])]);
        echo '<div class="updated notice"><p>Supervisor deleted successfully.</p></div>';
    }

    // Get Supervisor for editing
    $edit_supervisor = null;
    if (isset($_GET['edit'])) {
        $edit_supervisor = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($_GET['edit'])));
    }

    // List Supervisors
    $supervisors = $wpdb->get_results("SELECT * FROM $table WHERE role = 'advisor' ORDER BY name ASC");
    
    ?>
    <div class="wrap">
        <h1><?php echo $edit_supervisor ? 'Edit' : 'Add'; ?> Supervisor Teacher</h1>
        
        <form method="post" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px; max-width: 600px;">
            <?php wp_nonce_field('tes_save_supervisor'); ?>
            <?php if ($edit_supervisor): ?>
                <input type="hidden" name="supervisor_id" value="<?php echo esc_attr($edit_supervisor->id); ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="supervisor_name">Name</label></th>
                    <td><input type="text" name="supervisor_name" id="supervisor_name" value="<?php echo $edit_supervisor ? esc_attr($edit_supervisor->name) : ''; ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="supervisor_designation">Designation</label></th>
                    <td><input type="text" name="supervisor_designation" id="supervisor_designation" value="<?php echo $edit_supervisor ? esc_attr($edit_supervisor->designation) : ''; ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="supervisor_department">Department</label></th>
                    <td><input type="text" name="supervisor_department" id="supervisor_department" value="<?php echo $edit_supervisor ? esc_attr($edit_supervisor->department) : ''; ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="supervisor_class">Class</label></th>
                    <td><input type="text" name="supervisor_class" id="supervisor_class" value="<?php echo $edit_supervisor ? esc_attr($edit_supervisor->class_name) : ''; ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="supervisor_phase">Phase</label></th>
                    <td><input type="text" name="supervisor_phase" id="supervisor_phase" value="<?php echo $edit_supervisor ? esc_attr($edit_supervisor->phase) : ''; ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="supervisor_username">Username</label></th>
                    <td><input type="text" name="supervisor_username" id="supervisor_username" value="<?php echo $edit_supervisor ? esc_attr($edit_supervisor->username) : ''; ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="supervisor_password">Password</label></th>
                    <td>
                        <input type="text" name="supervisor_password" id="supervisor_password" class="regular-text" <?php echo $edit_supervisor ? '' : 'required'; ?>>
                        <?php if ($edit_supervisor): ?><p class="description">Leave blank to keep current password.</p><?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="tes_save_supervisor" class="button button-primary"><?php echo $edit_supervisor ? 'Update' : 'Add'; ?> Supervisor</button>
                <?php if ($edit_supervisor): ?>
                    <a href="?page=tes-supervisors" class="button">Cancel</a>
                <?php endif; ?>
            </p>
        </form>

        <h2>All Supervisors</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($supervisors): foreach ($supervisors as $s): ?>
                    <tr>
                        <td><?php echo esc_html($s->name); ?></td>
                        <td><?php echo esc_html($s->department); ?></td>
                        <td><?php echo esc_html($s->username); ?></td>
                        <td><?php echo esc_html($s->password); ?></td>
                        <td><?php echo esc_html(date('F j, Y', strtotime($s->created_at))); ?></td>
                        <td>
                            <a href="?page=tes-supervisors&edit=<?php echo $s->id; ?>" class="button button-small">Edit</a>
                            <a href="<?php echo wp_nonce_url('?page=tes-supervisors&delete=' . $s->id, 'tes_delete_supervisor'); ?>" class="button button-small button-link-delete" onclick="return confirm('Delete this supervisor?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6">No supervisors found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script>
    jQuery(document).ready(function($) {
        setTimeout(function() {
            $('.notice').fadeOut('slow');
        }, 5000);
    });
    </script>
    <?php
}