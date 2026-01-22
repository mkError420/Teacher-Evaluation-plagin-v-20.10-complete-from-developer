<?php
if (!defined('ABSPATH')) exit;

function tes_teachers_page() {
    global $wpdb;

    $table = $wpdb->prefix . 'tes_teachers';

    // Add/Edit teacher
    if (isset($_POST['tes_save_teacher']) && !empty($_POST['teacher_name'])) {
        $teacher_name = sanitize_text_field($_POST['teacher_name']);
        $teacher_department = sanitize_text_field($_POST['teacher_department']);
        $teacher_manual_id = isset($_POST['teacher_manual_id']) ? sanitize_text_field($_POST['teacher_manual_id']) : '';
        $teacher_phase = isset($_POST['teacher_phase']) ? sanitize_text_field($_POST['teacher_phase']) : '';
        $teacher_class = isset($_POST['teacher_class']) ? sanitize_text_field($_POST['teacher_class']) : '';
        $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;
        $teacher_role = 'teacher';

        // Handle new department
        if ($teacher_department === 'new_department') {
            $teacher_department = sanitize_text_field($_POST['new_department']);
            if (empty($teacher_department)) {
                echo '<div class="error notice"><p>Please enter a name for the new department.</p></div>';
                return;
            }
        } elseif ($teacher_department === 'edit_current' && $teacher_id) {
            // Editing department name - this will rename the department for ALL teachers in that department
            $new_dept_name = sanitize_text_field($_POST['edit_department']);
            if (empty($new_dept_name)) {
                echo '<div class="error notice"><p>Please enter a new department name.</p></div>';
                return;
            }

            $old_department = $edit_teacher->department;

            // Update all teachers in the old department to the new department name
            $wpdb->update(
                $table,
                ['department' => $new_dept_name],
                ['department' => $old_department]
            );

            // Update all students in the old department to the new department name
            $wpdb->update(
                $wpdb->prefix . 'tes_students',
                ['department' => $new_dept_name],
                ['department' => $old_department]
            );

            $teacher_department = $new_dept_name;
            echo '<div class="updated notice"><p>Department renamed successfully. All teachers in "' . esc_html($old_department) . '" have been moved to "' . esc_html($new_dept_name) . '".</p></div>';
        }

        // Handle new phase
        if ($teacher_phase === 'new_phase') {
            $teacher_phase = sanitize_text_field($_POST['new_phase']);
            if (empty($teacher_phase)) {
                echo '<div class="error notice"><p>Please enter a name for the new phase.</p></div>';
                return;
            }
        }

        // Handle new class
        if ($teacher_class === 'new_class') {
            $teacher_class = sanitize_text_field($_POST['new_class']);
            if (empty($teacher_class)) {
                echo '<div class="error notice"><p>Please enter a name for the new class.</p></div>';
                return;
            }
        }

        // Check for duplicate teacher name in same department
        $existing_check = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE name = %s AND department = %s AND id != %d AND (role != 'advisor' OR role IS NULL)",
            $teacher_name,
            $teacher_department,
            $teacher_id ?: 0
        ));

        if ($existing_check > 0) {
            echo '<div class="error notice"><p>A teacher with this name already exists in the ' . esc_html($teacher_department) . ' department.</p></div>';
        } else {
            $data = [
                'name' => $teacher_name,
                'department' => $teacher_department,
                'teacher_id_number' => $teacher_manual_id,
                'phase' => $teacher_phase,
                'class_name' => $teacher_class,
                'role' => $teacher_role
            ];

            if ($teacher_id) {
                // Update existing teacher
                $wpdb->update($table, $data, ['id' => $teacher_id]);
                echo '<div class="updated notice"><p>Teacher updated successfully.</p></div>';
            } else {
                // Add new teacher
                $wpdb->insert($table, $data);
                echo '<div class="updated notice"><p>Teacher added successfully.</p></div>';
            }
        }
    }

    // Delete teacher
    if (isset($_GET['delete'])) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'tes_delete_teacher')) {
            wp_die('Security check failed');
        }
        $wpdb->delete($table, ['id' => intval($_GET['delete'])]);
        echo '<div class="updated notice"><p>Teacher deleted successfully.</p></div>';
    }

    // Bulk Delete Teachers
    if (isset($_POST['tes_bulk_action']) && $_POST['tes_bulk_action'] === 'delete' && !empty($_POST['teacher_ids'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'tes_bulk_delete_teachers')) {
            wp_die('Security check failed');
        }
        $ids = array_map('intval', $_POST['teacher_ids']);
        if (!empty($ids)) {
            $ids_string = implode(',', $ids);
            $wpdb->query("DELETE FROM $table WHERE id IN ($ids_string)");
            echo '<div class="updated notice"><p>' . count($ids) . ' teachers deleted successfully!</p></div>';
        }
    }

    // Handle department operations
    if (isset($_POST['tes_rename_department']) && !empty($_POST['old_department']) && !empty($_POST['new_department_name'])) {
        $old_dept = sanitize_text_field($_POST['old_department']);
        $new_dept = sanitize_text_field($_POST['new_department_name']);

        if ($old_dept !== $new_dept) {
            // Check if new department name already exists
            $existing_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE department = %s",
                $new_dept
            ));

            if ($existing_count > 0) {
                echo '<div class="error notice"><p>A department with the name "' . esc_html($new_dept) . '" already exists.</p></div>';
            } else {
                // Rename department for all teachers
                $updated = $wpdb->update(
                    $table,
                    ['department' => $new_dept],
                    ['department' => $old_dept]
                );

                // Rename department for all students
                $wpdb->update(
                    $wpdb->prefix . 'tes_students',
                    ['department' => $new_dept],
                    ['department' => $old_dept]
                );

                if ($updated !== false) {
                    echo '<div class="updated notice"><p>Department renamed successfully from "' . esc_html($old_dept) . '" to "' . esc_html($new_dept) . '".</p></div>';
                } else {
                    echo '<div class="error notice"><p>Failed to rename department.</p></div>';
                }
            }
        }
    }

    if (isset($_GET['delete_department'])) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'tes_delete_department')) {
            wp_die('Security check failed');
        }
        $dept_to_delete = sanitize_text_field($_GET['delete_department']);

        // Update teachers to remove this department
        $wpdb->update(
            $table,
            ['department' => ''],
            ['department' => $dept_to_delete]
        );

        // Update students to remove this department
        $wpdb->update(
            $wpdb->prefix . 'tes_students',
            ['department' => ''],
            ['department' => $dept_to_delete]
        );

        echo '<div class="updated notice"><p>Department "' . esc_html($dept_to_delete) . '" deleted. Teachers and Students in this department are now unassigned.</p></div>';
    }

    // Bulk Delete Departments
    if (isset($_POST['tes_bulk_delete_departments']) && !empty($_POST['department_names'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'tes_bulk_delete_departments_nonce')) {
            wp_die('Security check failed');
        }
        
        $depts = array_map('sanitize_text_field', $_POST['department_names']);
        $count = 0;
        
        foreach ($depts as $dept) {
            $wpdb->update($table, ['department' => ''], ['department' => $dept]);
            $wpdb->update($wpdb->prefix . 'tes_students', ['department' => ''], ['department' => $dept]);
            $count++;
        }
        
        echo '<div class="updated notice"><p>' . $count . ' departments deleted. Teachers and Students unassigned.</p></div>';
    }

    // Handle Search and Filtering
    $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $filter_phase = isset($_GET['filter_phase']) ? sanitize_text_field($_GET['filter_phase']) : '';
    $filter_class = isset($_GET['filter_class']) ? sanitize_text_field($_GET['filter_class']) : '';

    $sql = "SELECT * FROM $table";
    $where_clauses = [];
    $params = [];

    $where_clauses[] = "(role != 'advisor' OR role IS NULL)";

    if (!empty($search_term)) {
        $like = '%' . $wpdb->esc_like($search_term) . '%';
        $where_clauses[] = "(name LIKE %s OR department LIKE %s OR teacher_id_number LIKE %s OR phase LIKE %s OR class_name LIKE %s)";
        array_push($params, $like, $like, $like, $like, $like);
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

    $sql .= " ORDER BY name ASC";
    
    if (!empty($params)) {
        $teachers = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    } else {
        $teachers = $wpdb->get_results($sql);
    }


    // Get unique departments for dropdown
    $departments = $wpdb->get_col("
        SELECT DISTINCT department FROM (
            SELECT department FROM $table WHERE department != ''
            UNION
            SELECT department FROM {$wpdb->prefix}tes_students WHERE department != ''
        ) AS combined_departments ORDER BY department
    ");

    // Get unique phases for dropdown
    $phases = $wpdb->get_col("
        SELECT DISTINCT phase FROM (
            SELECT phase FROM $table WHERE phase != ''
            UNION
            SELECT phase FROM {$wpdb->prefix}tes_students WHERE phase != ''
        ) AS combined_phases ORDER BY phase
    ");

    // Get unique classes for dropdown
    $classes = $wpdb->get_col("
        SELECT DISTINCT class_name FROM $table WHERE class_name != '' ORDER BY class_name
    ");

    // Get teacher for editing
    $edit_teacher = null;
    if (isset($_GET['edit'])) {
        $edit_teacher = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($_GET['edit'])));
    }
    ?>

    <div class="wrap">
        <h1><?php echo $edit_teacher ? 'Edit' : 'Add'; ?> Teacher</h1>

        <form method="post" style="margin-bottom:20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <?php if ($edit_teacher): ?>
                <input type="hidden" name="teacher_id" value="<?php echo esc_attr($edit_teacher->id); ?>">
            <?php endif; ?>

            <h3 style="margin-top: 0; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Teacher Details</h3>
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 20px;">
            <input type="text"
                   name="teacher_name"
                   placeholder="Teacher Name"
                   value="<?php echo $edit_teacher ? esc_attr($edit_teacher->name) : ''; ?>"
                   required
                   style="margin-right:10px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">

            <input type="text"
                   name="teacher_manual_id"
                   placeholder="Teacher ID"
                   value="<?php echo $edit_teacher ? esc_attr($edit_teacher->teacher_id_number) : ''; ?>"
                   style="margin-right:10px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100px;">

            <select name="teacher_phase"
                    id="teacher_phase_select"
                    style="margin-right:10px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-width: 120px;">
                <option value="">Select Phase</option>
                <?php foreach ($phases as $ph): ?>
                    <option value="<?php echo esc_attr($ph); ?>"
                        <?php selected($edit_teacher ? $edit_teacher->phase : '', $ph); ?>>
                        <?php echo esc_html($ph); ?>
                    </option>
                <?php endforeach; ?>
                <option value="new_phase">+ Add New Phase</option>
            </select>

            <input type="text"
                   name="new_phase"
                   id="new_phase_input"
                   placeholder="Enter new phase"
                   style="margin-right:10px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; display: none;">

            <select name="teacher_class"
                    id="teacher_class_select"
                    style="margin-right:10px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-width: 120px;">
                <option value="">Select Class</option>
                <?php foreach ($classes as $cls): ?>
                    <option value="<?php echo esc_attr($cls); ?>"
                        <?php selected($edit_teacher ? $edit_teacher->class_name : '', $cls); ?>>
                        <?php echo esc_html($cls); ?>
                    </option>
                <?php endforeach; ?>
                <option value="new_class">+ Add New Class</option>
            </select>

            <input type="text"
                   name="new_class"
                   id="new_class_input"
                   placeholder="Enter new class"
                   style="margin-right:10px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; display: none;">

            <select name="teacher_department"
                    id="teacher_department_select"
                    required
                    style="margin-right:10px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-width: 150px;">
                <option value="">Select Department</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo esc_attr($dept); ?>"
                        <?php selected($edit_teacher ? $edit_teacher->department : '', $dept); ?>>
                        <?php echo esc_html($dept); ?>
                    </option>
                <?php endforeach; ?>
                <option value="new_department">+ Add New Department</option>
                <?php if ($edit_teacher): ?>
                    <option value="edit_current">Edit Current Department</option>
                <?php endif; ?>
            </select>

            <input type="text"
                   name="new_department"
                   id="new_department_input"
                   placeholder="Enter new department name"
                   style="margin-right:10px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; display: none;">

            <input type="text"
                   name="edit_department"
                   id="edit_department_input"
                   placeholder="Edit department name"
                   value="<?php echo $edit_teacher ? esc_attr($edit_teacher->department) : ''; ?>"
                   style="margin-right:10px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; display: none;">

            </div>

            <div style="margin-top: 20px;">
            <button type="submit"
                    name="tes_save_teacher"
                    class="button button-primary">
                <?php echo $edit_teacher ? 'Update' : 'Add'; ?> Teacher
            </button>

            <?php if ($edit_teacher): ?>
                <a href="?page=tes-teachers" class="button">Cancel</a>
            <?php endif; ?>
            </div>
        </form>

        <form method="get" style="margin-bottom: 15px;">
            <input type="hidden" name="page" value="tes-teachers">
            
            <select name="filter_phase" style="margin-right: 5px;">
                <option value="">All Phases</option>
                <?php foreach ($phases as $ph): ?>
                    <option value="<?php echo esc_attr($ph); ?>" <?php selected($filter_phase, $ph); ?>><?php echo esc_html($ph); ?></option>
                <?php endforeach; ?>
            </select>

            <select name="filter_class" style="margin-right: 5px;">
                <option value="">All Classes</option>
                <?php foreach ($classes as $cls): ?>
                    <option value="<?php echo esc_attr($cls); ?>" <?php selected($filter_class, $cls); ?>><?php echo esc_html($cls); ?></option>
                <?php endforeach; ?>
            </select>

            <div style="position: relative; display: inline-block; width: 300px;">
                <input type="text" name="s" id="tes_teacher_search_input" value="<?php echo esc_attr($search_term); ?>" placeholder="Search by Name, ID, Dept, Phase or Class" style="width: 100%;" autocomplete="off">
                <div id="tes_teacher_search_suggestions" style="display:none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ccd0d4; z-index: 1000; max-height: 300px; overflow-y: auto; box-shadow: 0 4px 5px rgba(0,0,0,0.1);"></div>
            </div>
            <button type="submit" class="button button-secondary">Filter</button>
            <?php if (!empty($search_term) || !empty($filter_phase) || !empty($filter_class)): ?>
                <a href="<?php echo admin_url('admin.php?page=tes-teachers'); ?>" class="button">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Import Teachers Form -->
        <div class="card" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3>Import Teachers from CSV</h3>
            <p class="description">
                Upload a CSV file with headers matching the database columns.<br>
                <strong>Allowed Columns:</strong> Name, Department, Teacher ID, Phase, Class.
            </p>
            
            <form id="tes-import-teacher-form" enctype="multipart/form-data">
                <div style="margin-bottom: 10px;">
                    <select name="import_department" style="width: 100%; max-width: 300px;">
                        <option value="">Select Department (Optional - Overrides CSV)</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo esc_attr($dept); ?>"><?php echo esc_html($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="file" name="import_file" accept=".csv" required>
                <button type="submit" class="button button-primary">Import Teachers</button>
                <span class="spinner" style="float: none; margin-top: 0;"></span>
            </form>
            
            <div id="tes-import-result" style="margin-top: 10px; font-weight: bold;"></div>
        </div>

        <h2>All Teachers</h2>

        <button type="button" id="tes-download-teachers-csv" class="button button-primary" style="margin-bottom: 15px;" disabled>Download Selected CSV</button>

        <form method="post">
        <?php wp_nonce_field('tes_bulk_delete_teachers'); ?>
        <div class="tablenav top" style="padding: 10px 0;">
            <div class="alignleft actions">
                <button type="submit" name="tes_bulk_action" value="delete" class="button button-secondary" onclick="return confirm('Are you sure you want to delete the selected teachers?');">Delete Selected</button>
            </div>
        </div>

        <table class="widefat striped">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>
                    <th>Teacher ID</th>
                    <th>Teacher Name</th>
                    <th>Department</th>
                    <th>Phase</th>
                    <th>Class</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($teachers): foreach ($teachers as $t): ?>
                    <tr>
                        <th scope="row" class="check-column"><input type="checkbox" name="teacher_ids[]" value="<?php echo esc_attr($t->id); ?>"></th>
                        <td><?php echo esc_html($t->teacher_id_number); ?></td>
                        <td><?php echo esc_html($t->name); ?></td>
                        <td><?php echo esc_html($t->department); ?></td>
                        <td><?php echo esc_html($t->phase); ?></td>
                        <td><?php echo esc_html($t->class_name); ?></td>
                        <td><?php echo esc_html($t->created_at); ?></td>
                        <td>
                            <a class="button button-secondary"
                               href="?page=tes-teachers&edit=<?php echo $t->id; ?>"
                               style="margin-right: 5px;">
                               Edit
                            </a>
                            <a class="button button-secondary"
                               href="<?php echo wp_nonce_url('?page=tes-teachers&delete=' . $t->id, 'tes_delete_teacher'); ?>"
                               onclick="return confirm('Are you sure you want to delete this teacher?');">
                               Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4">No teachers found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </form>
    </div>

    <div class="wrap" style="margin-top: 30px;">
        <h2>Manage Departments</h2>

        <?php
        // Get department statistics
        $dept_stats = $wpdb->get_results("
            SELECT department, COUNT(*) as teacher_count
            FROM $table
            WHERE department != '' AND (role != 'advisor' OR role IS NULL)
            GROUP BY department
            ORDER BY department
        ");
        ?>

        <?php if ($dept_stats): ?>
            <form method="post">
            <?php wp_nonce_field('tes_bulk_delete_departments_nonce'); ?>
            <div class="tablenav top" style="padding: 10px 0;">
                <div class="alignleft actions">
                    <button type="submit" name="tes_bulk_delete_departments" value="delete" class="button button-secondary" onclick="return confirm('Are you sure you want to delete selected departments? Teachers and Students will be unassigned.');">Delete Selected</button>
                </div>
            </div>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-depts"></td>
                        <th>Department Name</th>
                        <th>Number of Teachers</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dept_stats as $stat): ?>
                        <tr>
                            <th scope="row" class="check-column"><input type="checkbox" name="department_names[]" value="<?php echo esc_attr($stat->department); ?>"></th>
                            <td><?php echo esc_html($stat->department); ?></td>
                            <td><?php echo esc_html($stat->teacher_count); ?></td>
                            <td>
                                <button type="button" class="button button-secondary rename-dept-btn"
                                        data-dept="<?php echo esc_attr($stat->department); ?>"
                                        style="margin-right: 5px;">
                                    Rename
                                </button>
                                    <a class="button button-secondary"
                                       href="<?php echo wp_nonce_url('?page=tes-teachers&delete_department=' . urlencode($stat->department), 'tes_delete_department'); ?>"
                                       onclick="return confirm('Are you sure you want to delete this department? Teachers and Students will be unassigned.');">
                                       Delete
                                    </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </form>
        <?php else: ?>
            <p>No departments found.</p>
        <?php endif; ?>
    </div>

    <!-- Rename Department Modal -->
    <div id="rename-dept-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); min-width: 400px;">
            <h3>Rename Department</h3>
            <form method="post">
                <input type="hidden" name="old_department" id="old_dept_input">
                <p>
                    <label>Current Name: <span id="current-dept-name"></span></label>
                </p>
                <p>
                    <label for="new_dept_name">New Department Name:</label><br>
                    <input type="text" name="new_department_name" id="new_dept_name" required style="width: 100%; padding: 8px; margin-top: 5px;">
                </p>
                <p style="margin-top: 15px;">
                    <button type="submit" name="tes_rename_department" class="button button-primary">Rename Department</button>
                    <button type="button" id="cancel-rename" class="button">Cancel</button>
                </p>
            </form>
        </div>
    </div>

    <style>
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
            function updateDepartmentInputs() {
                var selectedValue = $('select[name="teacher_department"]').val();
                var newDeptInput = $('#new_department_input');
                var editDeptInput = $('#edit_department_input');

                // Hide all inputs first
                newDeptInput.hide().removeAttr('required');
                editDeptInput.hide().removeAttr('required');

                if (selectedValue === 'new_department') {
                    newDeptInput.show().attr('required', true);
                } else if (selectedValue === 'edit_current') {
                    editDeptInput.show().attr('required', true);
                }
            }

            function updatePhaseInputs() {
                var selectedValue = $('select[name="teacher_phase"]').val();
                var newPhaseInput = $('#new_phase_input');

                newPhaseInput.hide().removeAttr('required');

                if (selectedValue === 'new_phase') {
                    newPhaseInput.show().attr('required', true);
                }
            }

            function updateClassInputs() {
                var selectedValue = $('select[name="teacher_class"]').val();
                var newClassInput = $('#new_class_input');

                newClassInput.hide().removeAttr('required');

                if (selectedValue === 'new_class') {
                    newClassInput.show().attr('required', true);
                }
            }

            $('select[name="teacher_department"]').on('change', updateDepartmentInputs);
            $('select[name="teacher_phase"]').on('change', updatePhaseInputs);
            $('select[name="teacher_class"]').on('change', updateClassInputs);

            // Import Teacher Form Handler
            $('#tes-import-teacher-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var formData = new FormData(this);
                formData.append('action', 'tes_import_teachers');
                
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

            // Initialize on page load
            updateDepartmentInputs();
            updatePhaseInputs();
            updateClassInputs();

            // Handle form submission to ensure required fields are filled
            $('form').on('submit', function(e) {
                var deptSelect = $('select[name="teacher_department"]');
                var newDeptInput = $('#new_department_input');
                var editDeptInput = $('#edit_department_input');
                var phaseSelect = $('select[name="teacher_phase"]');
                var newPhaseInput = $('#new_phase_input');
                var classSelect = $('select[name="teacher_class"]');
                var newClassInput = $('#new_class_input');

                if (deptSelect.val() === 'new_department' && newDeptInput.val().trim() === '') {
                    alert('Please enter a name for the new department.');
                    newDeptInput.focus();
                    e.preventDefault();
                    return false;
                }

                if (deptSelect.val() === 'edit_current' && editDeptInput.val().trim() === '') {
                    alert('Please enter a new name for the department.');
                    editDeptInput.focus();
                    e.preventDefault();
                    return false;
                }

                if (phaseSelect.val() === 'new_phase' && newPhaseInput.val().trim() === '') {
                    alert('Please enter a name for the new phase.');
                    newPhaseInput.focus();
                    e.preventDefault();
                    return false;
                }

                if (classSelect.val() === 'new_class' && newClassInput.val().trim() === '') {
                    alert('Please enter a name for the new class.');
                    newClassInput.focus();
                    e.preventDefault();
                    return false;
                }
            });

            // Handle rename department modal
            $('.rename-dept-btn').on('click', function() {
                var deptName = $(this).data('dept');
                $('#old_dept_input').val(deptName);
                $('#current-dept-name').text(deptName);
                $('#new_dept_name').val(deptName);
                $('#rename-dept-modal').show();
            });

            $('#cancel-rename').on('click', function() {
                $('#rename-dept-modal').hide();
            });

            // Close modal when clicking outside
            $('#rename-dept-modal').on('click', function(e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });

            // CSV Download Logic
            $('#tes-download-teachers-csv').on('click', function() {
                var csv = [];
                var header = ["Teacher ID", "Teacher Name", "Department", "Phase", "Class", "Date"];
                csv.push(header.join(","));

                $('table.widefat tbody tr').each(function() {
                    var $row = $(this);
                    var checkbox = $row.find('input[name="teacher_ids[]"]');
                    
                    if (checkbox.length && checkbox.is(':checked')) {
                        var rowData = [];
                        var $cells = $row.find('td');
                        
                        // Extract text and handle CSV escaping
                        rowData.push('"' + $cells.eq(0).text().trim().replace(/"/g, '""') + '"'); // Teacher ID
                        rowData.push('"' + $cells.eq(1).text().trim().replace(/"/g, '""') + '"'); // Name
                        rowData.push('"' + $cells.eq(2).text().trim().replace(/"/g, '""') + '"'); // Department
                        rowData.push('"' + $cells.eq(3).text().trim().replace(/"/g, '""') + '"'); // Phase
                        rowData.push('"' + $cells.eq(4).text().trim().replace(/"/g, '""') + '"'); // Class
                        rowData.push('"' + $cells.eq(5).text().trim().replace(/"/g, '""') + '"'); // Date
                        
                        csv.push(rowData.join(","));
                    }
                });

                if (csv.length <= 1) {
                    alert('Please select at least one teacher.');
                    return;
                }

                var csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
                var downloadLink = document.createElement("a");
                downloadLink.download = "teachers_list.csv";
                downloadLink.href = window.URL.createObjectURL(csvFile);
                downloadLink.style.display = "none";
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
            });

            function updateDownloadButton() {
                var checkedCount = $('input[name="teacher_ids[]"]:checked').length;
                var btn = $('#tes-download-teachers-csv');
                if (checkedCount > 0) {
                    btn.prop('disabled', false);
                    btn.text('Download Selected CSV (' + checkedCount + ')');
                } else {
                    btn.prop('disabled', true);
                    btn.text('Download Selected CSV');
                }
            }

            $(document).on('change', 'input[name="teacher_ids[]"]', updateDownloadButton);

            // Select All Checkbox
            $('#cb-select-all-1').on('click', function() {
                var checked = this.checked;
                $('input[name="teacher_ids[]"]').prop('checked', checked);
                updateDownloadButton();
            });

            // Select All Departments Checkbox
            $('#cb-select-all-depts').on('click', function() {
                var checked = this.checked;
                $('input[name="department_names[]"]').prop('checked', checked);
            });

            // Teacher search autocomplete
            var searchInput = $('#tes_teacher_search_input');
            var suggestionsBox = $('#tes_teacher_search_suggestions');
            var timer;

            searchInput.on('input', function() {
                var term = $(this).val();
                clearTimeout(timer);
                
                if (term.length < 1) {
                    suggestionsBox.hide().empty();
                    return;
                }

                timer = setTimeout(function() {
                    $.ajax({
                        url: ajaxurl,
                        data: {
                            action: 'tes_teacher_search_autocomplete',
                            term: term
                        },
                        success: function(response) {
                            suggestionsBox.empty();
                            if (response.success && response.data.length > 0) {
                                $.each(response.data, function(i, item) {
                                    var escapedName = $('<div>').text(item.name).html();
                                    var escapedDept = $('<div>').text(item.department).html();
                                    var escapedID = item.teacher_id_number ? $('<div>').text(item.teacher_id_number).html() : '';
                                    var escapedPhase = item.phase ? $('<div>').text(item.phase).html() : '';
                                    var escapedClass = item.class_name ? $('<div>').text(item.class_name).html() : '';
                                    
                                    var label = escapedName + (escapedID ? ' (' + escapedID + ')' : '');
                                    var meta = escapedDept + (escapedPhase ? ' - ' + escapedPhase : '') + (escapedClass ? ' - ' + escapedClass : '');

                                    var $itemDiv = $('<div class="tes-suggestion-item"></div>');
                                    $itemDiv.attr('data-value', item.name);
                                    $itemDiv.html('<strong>' + label + '</strong><br><span style="color:#666; font-size:12px;">' + meta + '</span>');
                                    
                                    suggestionsBox.append($itemDiv);
                                });
                                suggestionsBox.show();
                            } else {
                                suggestionsBox.hide();
                            }
                        }
                    });
                }, 300);
            });

            $(document).on('click', '#tes_teacher_search_suggestions .tes-suggestion-item', function() {
                var value = $(this).data('value');
                searchInput.val(value);
                suggestionsBox.hide();
                searchInput.closest('form').submit();
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('#tes_teacher_search_input').length && !$(e.target).closest('#tes_teacher_search_suggestions').length) {
                    suggestionsBox.hide();
                }
            });

            updateDownloadButton();
        });
    </script>

<?php
}
