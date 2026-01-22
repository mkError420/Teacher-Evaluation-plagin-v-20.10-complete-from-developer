<?php
if (!defined('ABSPATH')) exit;

/**
 * Students Management Page
 */
function tes_students_page() {
    global $wpdb;
    $students_table = $wpdb->prefix . 'tes_students';

    // Add new student
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student') {
        if (!isset($_POST['tes_nonce']) || !wp_verify_nonce($_POST['tes_nonce'], 'tes_add_student')) {
            wp_die('Security check failed');
        }

        if (isset($_POST['first_name'])) {
            $first_name = sanitize_text_field($_POST['first_name']);
            $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
            $student_name = trim($first_name . ' ' . $last_name);
            if (!empty($last_name)) {
                $username = strtolower($last_name);
            } else {
                $username = strtolower($first_name);
            }
        } else {
            $student_name = sanitize_text_field($_POST['student_name']);
            $username = $student_name;
        }

        $class_name = sanitize_text_field($_POST['class_name']);
        if ($class_name === 'new_class') {
            $class_name = sanitize_text_field($_POST['new_class']);
        }
        $session = sanitize_text_field($_POST['session']);
        $batch_name = sanitize_text_field($_POST['batch_name']);
        $phase = sanitize_text_field($_POST['phase']);
        $roll = sanitize_text_field($_POST['roll']);

        // Auto-set Username and Password from Name and Roll
        $password = $roll;

        // Validate
        if (empty($student_name) || empty($class_name) || empty($roll)) {
            echo '<div class="notice notice-error"><p>Student Name, Class and Roll are required.</p></div>';
        } else {
            // Check if username exists
            $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $students_table WHERE username = %s", $username));
            if ($existing) {
                echo '<div class="notice notice-error"><p>Username already exists. Please choose another.</p></div>';
            } else {
                // Load WordPress security functions
                require_once(ABSPATH . 'wp-includes/pluggable.php');
                
                // Hash password securely
                // $hashed_password = wp_hash_password($password);
                $hashed_password = $password; // Store plain text per request
                
                // Insert student into database
                $result = $wpdb->insert(
                    $students_table,
                    [
                        'student_name' => $student_name,
                        'username' => $username,
                        'password' => $hashed_password,
                        'class_name' => $class_name,
                        'session' => $session,
                        'batch_name' => $batch_name,
                        'phase' => $phase,
                        'roll' => $roll
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
                );

                if ($result) {
                    echo '<div class="notice notice-success"><p>Student added successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Error adding student: ' . esc_html($wpdb->last_error) . '</p></div>';
                }
            }
        }
    }

    // Update student
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_student') {
        if (!isset($_POST['tes_nonce']) || !wp_verify_nonce($_POST['tes_nonce'], 'tes_add_student')) {
            wp_die('Security check failed');
        }

        $student_id = intval($_POST['student_id']);
        $student_name = sanitize_text_field($_POST['student_name']);
        $username = sanitize_text_field($_POST['username']);
        $password = sanitize_text_field($_POST['password']);
        $class_name = sanitize_text_field($_POST['class_name']);
        if ($class_name === 'new_class') {
            $class_name = sanitize_text_field($_POST['new_class']);
        }
        $session = sanitize_text_field($_POST['session']);
        $batch_name = sanitize_text_field($_POST['batch_name']);
        $phase = sanitize_text_field($_POST['phase']);
        $roll = sanitize_text_field($_POST['roll']);

        if (empty($student_name) || empty($username) || empty($class_name)) {
            echo '<div class="notice notice-error"><p>Name, Username and Class are required.</p></div>';
        } else {
            // Check if username exists for other students
            $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $students_table WHERE username = %s AND id != %d", $username, $student_id));
            if ($existing) {
                echo '<div class="notice notice-error"><p>Username already exists. Please choose another.</p></div>';
            } else {
                $data = [
                    'student_name' => $student_name,
                    'username' => $username,
                    'class_name' => $class_name,
                    'session' => $session,
                    'batch_name' => $batch_name,
                    'phase' => $phase,
                    'roll' => $roll
                ];

                // Only update password if provided
                if (!empty($password)) {
                    // require_once(ABSPATH . 'wp-includes/pluggable.php');
                    // $data['password'] = wp_hash_password($password);
                    $data['password'] = $password; // Store plain text per request
                }

                $wpdb->update($students_table, $data, ['id' => $student_id]);
                echo '<div class="notice notice-success"><p>Student updated successfully!</p></div>';
                $_GET['action'] = ''; // Reset action to clear edit form
            }
        }
    }

    // Delete student
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['student_id'])) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'tes_delete_student')) {
            wp_die('Security check failed');
        }

        $student_id = intval($_GET['student_id']);
        $wpdb->delete($students_table, ['id' => $student_id], ['%d']);
        echo '<div class="notice notice-success"><p>Student deleted successfully!</p></div>';
    }

    // Bulk Delete Students
    if (isset($_POST['tes_bulk_action']) && $_POST['tes_bulk_action'] === 'delete' && !empty($_POST['student_ids'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'tes_bulk_delete_students')) {
            wp_die('Security check failed');
        }
        $ids = array_map('intval', $_POST['student_ids']);
        if (!empty($ids)) {
            $ids_string = implode(',', $ids);
            $wpdb->query("DELETE FROM $students_table WHERE id IN ($ids_string)");
            echo '<div class="notice notice-success"><p>' . count($ids) . ' students deleted successfully!</p></div>';
        }
    }

    // Get student for editing
    $edit_student = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['student_id'])) {
        $edit_student = $wpdb->get_row($wpdb->prepare("SELECT * FROM $students_table WHERE id = %d", intval($_GET['student_id'])));
    }

    // Get unique phases and classes for filters
    $phases = $wpdb->get_col("SELECT DISTINCT phase FROM $students_table WHERE phase != '' ORDER BY phase ASC");
    $classes = $wpdb->get_col("SELECT DISTINCT class_name FROM $students_table WHERE class_name != '' ORDER BY class_name ASC");

    // Handle Search and Filtering
    $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $filter_phase = isset($_GET['filter_phase']) ? sanitize_text_field($_GET['filter_phase']) : '';
    $filter_class = isset($_GET['filter_class']) ? sanitize_text_field($_GET['filter_class']) : '';

    $sql = "SELECT * FROM $students_table";
    $where_clauses = [];
    $params = [];

    if (!empty($search_term)) {
        $where_clauses[] = "student_name LIKE %s";
        $params[] = '%' . $wpdb->esc_like($search_term) . '%';
    }

    if (!empty($filter_phase)) {
        $where_clauses[] = "phase = %s";
        $params[] = $filter_phase;
    }

    if (!empty($filter_class)) {
        $where_clauses[] = "class_name = %s";
        $params[] = $filter_class;
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $sql .= " ORDER BY created_at DESC";

    if (!empty($params)) {
        $students = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    } else {
        $students = $wpdb->get_results($sql);
    }

    ?>

    <div class="wrap">
        <h1>Manage Students</h1>

        <!-- Add Student Form -->
        <div class="card" style="margin-bottom: 20px;">
            <h2><?php echo $edit_student ? 'Edit Student' : 'Add New Student'; ?></h2>
            <form method="POST" class="tes-add-student-form">
                <?php wp_nonce_field('tes_add_student', 'tes_nonce'); ?>
                <input type="hidden" name="action" value="<?php echo $edit_student ? 'update_student' : 'add_student'; ?>">
                <?php if ($edit_student): ?>
                    <input type="hidden" name="student_id" value="<?php echo esc_attr($edit_student->id); ?>">
                <?php endif; ?>

                <style>
                    .tes-horizontal-form {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 15px;
                        align-items: flex-start;
                    }
                    .tes-form-group {
                        display: flex;
                        flex-direction: column;
                        margin-bottom: 10px;
                    }
                    .tes-form-group label {
                        font-weight: 600;
                        margin-bottom: 5px;
                        font-size: 12px;
                    }
                    .tes-form-group input[type="text"], 
                    .tes-form-group select {
                        width: 180px !important;
                        padding: 6px;
                        border: 1px solid #ccc;
                        border-radius: 4px;
                    }
                    .tes-form-group .description {
                        font-size: 11px;
                        color: #666;
                        margin: 2px 0 0;
                        max-width: 180px;
                    }
                    .tes-form-actions {
                        display: flex;
                        align-items: flex-end;
                        margin-bottom: 10px;
                        height: 60px; /* Align with inputs */
                        
                    }
                </style>

                <div class="tes-horizontal-form">
                    <?php if ($edit_student): ?>
                    <div class="tes-form-group">
                        <label for="student_name">Student Name *</label>
                        <input type="text" id="student_name" name="student_name" required
                               value="<?php echo esc_attr($edit_student->student_name); ?>">
                    </div>
                    <?php else: ?>
                    <div class="tes-form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="tes-form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name">
                    </div>
                    <?php endif; ?>

                    <?php if ($edit_student): ?>
                    <div class="tes-form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required
                               value="<?php echo $edit_student ? esc_attr($edit_student->username) : ''; ?>">
                        <p class="description">Must be unique</p>
                    </div>

                    <div class="tes-form-group">
                        <label for="password">Password <?php echo $edit_student ? '(Blank to keep)' : '*'; ?></label>
                        <?php
                        $pass_val = '';
                        if ($edit_student && strpos($edit_student->password, '$P$') !== 0 && strpos($edit_student->password, '$H$') !== 0) {
                            $pass_val = $edit_student->password;
                        }
                        ?>
                        <input type="text" id="password" name="password" <?php echo $edit_student ? '' : 'required'; ?> 
                               value="<?php echo esc_attr($pass_val); ?>">
                    </div>
                    <?php endif; ?>

                    <div class="tes-form-group">
                        <label for="class_name">Class *</label>
                        <select id="class_name" name="class_name" required onchange="toggleNewClass(this)">
                            <option value="">Select Class</option>
                            <?php 
                                $classes = $wpdb->get_col("
                                    SELECT DISTINCT class_name FROM (
                                        SELECT class_name FROM {$wpdb->prefix}tes_teachers WHERE class_name != ''
                                        UNION
                                        SELECT class_name FROM {$wpdb->prefix}tes_students WHERE class_name != ''
                                    ) AS combined_classes ORDER BY class_name
                                ");
                                foreach ($classes as $cls) {
                                    $selected = ($edit_student && $edit_student->class_name === $cls) ? 'selected' : '';
                                    echo '<option value="' . esc_attr($cls) . '" ' . $selected . '>' . esc_html($cls) . '</option>';
                                }
                            ?>
                            <option value="new_class">+ Add New Class</option>
                        </select>
                        <input type="text" id="new_class" name="new_class" placeholder="New Class Name"
                               style="display: none; margin-top: 5px;">
                        <script>
                            function toggleNewClass(select) {
                                var input = document.getElementById('new_class');
                                if (select.value === 'new_class') {
                                    input.style.display = 'block';
                                    input.required = true;
                                } else {
                                    input.style.display = 'none';
                                    input.required = false;
                                }
                            }
                        </script>
                    </div>

                    <div class="tes-form-group">
                        <label for="session">Session</label>
                        <input type="text" id="session" name="session" value="<?php echo $edit_student ? esc_attr($edit_student->session) : ''; ?>">
                    </div>

                    <div class="tes-form-group">
                        <label for="batch_name">Batch Name</label>
                        <input type="text" id="batch_name" name="batch_name" value="<?php echo $edit_student ? esc_attr($edit_student->batch_name) : ''; ?>">
                    </div>

                    <div class="tes-form-group">
                        <label for="phase">Phase</label>
                        <input type="text" id="phase" name="phase" value="<?php echo $edit_student ? esc_attr($edit_student->phase) : ''; ?>">
                    </div>

                    <div class="tes-form-group">
                        <label for="roll">Roll</label>
                        <input type="text" id="roll" name="roll" required value="<?php echo $edit_student ? esc_attr($edit_student->roll) : ''; ?>">
                    </div>

                    <div class="tes-form-actions">
                        <button type="submit" class="button button-primary"><?php echo $edit_student ? 'Update' : 'Add New Student'; ?></button>
                        <?php if ($edit_student): ?>
                            <a href="<?php echo admin_url('admin.php?page=tes-students'); ?>" class="button" style="margin-left: 5px;">Cancel</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Import Students Form -->
        <div class="card" style="margin-bottom: 20px;">
            <h3>Import Students from CSV</h3>
            <p class="description">
                Upload a CSV file with headers matching the database columns.<br>
                <strong>Allowed Columns:</strong> first_name, last_name, class_name, session, batch_name, phase, roll.
            </p>
            
            <form id="tes-import-form" enctype="multipart/form-data">
                <input type="file" name="import_file" accept=".csv" required>
                <button type="submit" class="button button-primary">Import Students</button>
                <span class="spinner" style="float: none; margin-top: 0;"></span>
            </form>
            
            <div id="tes-import-result" style="margin-top: 10px; font-weight: bold;"></div>
        </div>

        <!-- Students List -->
        <div class="card">
            <h2>Students List</h2>

            <form method="get" style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="hidden" name="page" value="tes-students">
                
                <select name="filter_phase" style="min-width: 150px;">
                    <option value="">All Phases</option>
                    <?php foreach($phases as $phase): ?>
                        <option value="<?php echo esc_attr($phase); ?>" <?php selected($filter_phase, $phase); ?>><?php echo esc_html($phase); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="filter_class" style="min-width: 150px;">
                    <option value="">All Classes</option>
                    <?php foreach($classes as $class): ?>
                        <option value="<?php echo esc_attr($class); ?>" <?php selected($filter_class, $class); ?>><?php echo esc_html($class); ?></option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="s" value="<?php echo esc_attr($search_term); ?>" placeholder="Search by Student Name..." style="width: 250px;">
                
                <button type="submit" class="button button-secondary">Filter</button>
                
                <?php if (!empty($search_term) || !empty($filter_phase) || !empty($filter_class)): ?>
                    <a href="<?php echo admin_url('admin.php?page=tes-students'); ?>" class="button">Reset</a>
                <?php endif; ?>
            </form>

            <button type="button" id="tes-download-students-csv" class="button button-primary" style="margin-bottom: 15px;" disabled>Download Selected CSV</button>

            <?php if ($students): ?>
                <form method="post">
                <?php wp_nonce_field('tes_bulk_delete_students'); ?>
                <div class="tablenav top" style="padding: 10px 0;">
                    <div class="alignleft actions">
                        <button type="submit" name="tes_bulk_action" value="delete" class="button button-secondary" onclick="return confirm('Are you sure you want to delete the selected students?');">Delete Selected</button>
                    </div>
                </div>

                <table class="widefat striped" id="tes-students-table">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>
                            <th>Student Name</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Class</th>
                            <th>Session</th>
                            <th>Batch</th>
                            <th>Phase</th>
                            <th>Roll</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <th scope="row" class="check-column"><input type="checkbox" name="student_ids[]" value="<?php echo esc_attr($student->id); ?>"></th>
                                <td><?php echo esc_html($student->student_name); ?></td>
                                <td><code><?php echo esc_html($student->username); ?></code></td>
                                <td><code><?php echo (strpos($student->password, '$P$') === 0 || strpos($student->password, '$H$') === 0) ? '(Hidden/Hashed)' : esc_html($student->password); ?></code></td>
                                <td><?php echo esc_html($student->class_name); ?></td>
                                <td><?php echo esc_html($student->session); ?></td>
                                <td><?php echo esc_html($student->batch_name); ?></td>
                                <td><?php echo esc_html($student->phase); ?></td>
                                <td><?php echo esc_html($student->roll); ?></td>
                                <td><?php echo esc_html($student->created_at); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=tes-students&action=edit&student_id=' . $student->id); ?>" 
                                       class="button button-secondary" style="margin-right: 5px;">
                                        Edit
                                    </a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=tes-students&action=delete&student_id=' . $student->id), 'tes_delete_student'); ?>" 
                                       class="button button-danger" 
                                       onclick="return confirm('Are you sure you want to delete this student?');">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </form>
            <?php else: ?>
                <p>No students found. Add one to get started!</p>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .tes-add-student-form table {
            margin-bottom: 20px;
        }
        .button-danger {
            background-color: #dc3545;
            color: white;
            text-decoration: none;
            border: none;
        }
        .button-danger:hover {
            background-color: #c82333;
            color: white;
        }
        .tes-suggestion-item {
            padding: 8px 10px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        .tes-suggestion-item:hover {
            background-color: #f0f0f1;
            color: #2271b1;
        }
        .tes-suggestion-item:last-child {
            border-bottom: none;
        }
    </style>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#tes-download-students-csv').on('click', function() {
            var csv = [];
            // Header
            var header = ["Student Name", "Username", "Password", "Class", "Session", "Batch", "Phase", "Roll"];
            csv.push(header.join(","));

            // Rows
            $('#tes-students-table tbody tr').each(function() {
                var $row = $(this);
                var checkbox = $row.find('input[name="student_ids[]"]');
                
                if (checkbox.length && checkbox.is(':checked')) {
                    var rowData = [];
                    // Get cells (td) - skipping the first th (checkbox)
                    var $cells = $row.find('td');
                    
                    // Extract text and handle CSV escaping
                    rowData.push('"' + $cells.eq(0).text().trim().replace(/"/g, '""') + '"'); // Name
                    rowData.push('"' + $cells.eq(1).text().trim().replace(/"/g, '""') + '"'); // Username
                    rowData.push('"' + $cells.eq(2).text().trim().replace(/"/g, '""') + '"'); // Password
                    rowData.push('"' + $cells.eq(3).text().trim().replace(/"/g, '""') + '"'); // Class
                    rowData.push('"' + $cells.eq(4).text().trim().replace(/"/g, '""') + '"'); // Session
                    rowData.push('"' + $cells.eq(5).text().trim().replace(/"/g, '""') + '"'); // Batch
                    rowData.push('"' + $cells.eq(6).text().trim().replace(/"/g, '""') + '"'); // Phase
                    rowData.push('"' + $cells.eq(7).text().trim().replace(/"/g, '""') + '"'); // Roll
                    
                    csv.push(rowData.join(","));
                }
            });

            if (csv.length <= 1) {
                alert('Please select at least one student.');
                return;
            }

            var csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
            var downloadLink = document.createElement("a");
            downloadLink.download = "students_list.csv";
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        });

        // Import Form Handler
        $('#tes-import-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var formData = new FormData(this);
            formData.append('action', 'tes_import_students');
            
            form.find('.spinner').addClass('is-active');
            $('#tes-import-result').html('');

            $.ajax({
                url: ajaxurl, 
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    form.find('.spinner').removeClass('is-active');
                    if (response.success) {
                        $('#tes-import-result').html('<span style="color:green;">' + response.data + '</span>');
                        form[0].reset();
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        $('#tes-import-result').html('<span style="color:red;">' + response.data + '</span>');
                    }
                },
                error: function() {
                    form.find('.spinner').removeClass('is-active');
                    $('#tes-import-result').html('<span style="color:red;">Server error. Please try again.</span>');
                }
            });
        });

        function updateDownloadButton() {
            var checkedCount = $('input[name="student_ids[]"]:checked').length;
            var btn = $('#tes-download-students-csv');
            if (checkedCount > 0) {
                btn.prop('disabled', false);
                btn.text('Download Selected CSV (' + checkedCount + ')');
            } else {
                btn.prop('disabled', true);
                btn.text('Download Selected CSV');
            }
        }

        $(document).on('change', 'input[name="student_ids[]"]', updateDownloadButton);

        // Select All Checkbox
        $('#cb-select-all-1').on('click', function() {
            var checked = this.checked;
            $('input[name="student_ids[]"]').prop('checked', checked);
            updateDownloadButton();
        });

        updateDownloadButton();

        // Auto-hide notices after 5 seconds
        setTimeout(function() {
            $('.notice').fadeOut('slow');
        }, 5000);
    });
    </script>

    <?php
}
