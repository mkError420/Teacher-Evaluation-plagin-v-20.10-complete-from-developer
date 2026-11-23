<?php
if (!defined('ABSPATH')) exit;

function tes_surveys_page() {
    global $wpdb;

    $teachers_table = $wpdb->prefix . 'tes_teachers';
    $surveys_table  = $wpdb->prefix . 'tes_surveys';

    // Auto-assign questions to existing survey with 0 questions
    if (isset($_GET['auto_assign_questions'])) {
        $target_id = intval($_GET['auto_assign_questions']);
        if ($target_id > 0) {
            $inserted = tes_assign_default_questions_to_survey($target_id);
            echo '<div class="updated notice is-dismissible"><p>Successfully set ' . intval($inserted) . ' questions on survey from Survey Question Builder. <a href="' . esc_url(admin_url('admin.php?page=tes-questions&survey_id=' . $target_id)) . '">View in Survey Question Builder &rarr;</a></p></div>';
        }
    }

    // Add/Edit survey
    if (
        isset($_POST['tes_save_survey']) &&
        !empty($_POST['survey_title']) &&
        !empty($_POST['teacher_id'])
    ) {
        $survey_group = isset($_POST['survey_group']) ? sanitize_text_field(wp_unslash($_POST['survey_group'])) : '';
        if ($survey_group === 'new_group') {
            $survey_group = isset($_POST['new_survey_group']) ? sanitize_text_field(wp_unslash($_POST['new_survey_group'])) : '';
        }
        if (empty($survey_group) && !empty($_POST['teacher_id'])) {
            $t_grp = $wpdb->get_var($wpdb->prepare("SELECT `group` FROM $teachers_table WHERE id = %d", intval($_POST['teacher_id'])));
            if (!empty($t_grp)) {
                $survey_group = $t_grp;
            }
        }

        $data = [
            'title'      => sanitize_text_field(wp_unslash($_POST['survey_title'])),
            'teacher_id' => intval($_POST['teacher_id']),
            'group'      => $survey_group
        ];
        if (!empty($_POST['survey_id'])) {
            $wpdb->update($surveys_table, $data, ['id' => intval($_POST['survey_id'])]);
            echo '<div class="updated notice is-dismissible"><p>Survey updated successfully.</p></div>';
        } else {
            $wpdb->insert($surveys_table, $data);
            $new_survey_id = $wpdb->insert_id;

            // Automatically set survey questions from Survey Question Builder
            $q_count = tes_assign_default_questions_to_survey($new_survey_id);

            // Redirect and set at Survey Question Builder page
            $target_url = admin_url('admin.php?page=tes-questions&survey_id=' . $new_survey_id . '&survey_created=1&q_count=' . $q_count);
            echo '<script type="text/javascript">window.location.href = ' . json_encode($target_url) . ';</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url($target_url) . '"></noscript>';
            echo '<div class="updated notice is-dismissible"><p>Survey created successfully with ' . intval($q_count) . ' questions automatically populated from Survey Question Builder. Redirecting to Survey Question Builder... <a href="' . esc_url($target_url) . '">Click here if not redirected</a>.</p></div>';
            return;
        }
    }

    // Delete survey
    if (isset($_GET['delete_survey'])) {
        $wpdb->delete($surveys_table, ['id' => intval($_GET['delete_survey'])]);
        echo '<div class="updated notice"><p>Survey deleted successfully.</p></div>';
    }

    // Bulk Delete Surveys
    if (isset($_POST['tes_bulk_delete_surveys']) && !empty($_POST['survey_ids'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'tes_bulk_delete_surveys_nonce')) {
            wp_die('Security check failed');
        }
        $ids = array_map('intval', $_POST['survey_ids']);
        if (!empty($ids)) {
            $ids_string = implode(',', $ids);
            $wpdb->query("DELETE FROM $surveys_table WHERE id IN ($ids_string)");
            echo '<div class="updated notice"><p>' . count($ids) . ' surveys deleted successfully.</p></div>';
        }
    }

    // Handle Search
    $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    $teachers = $wpdb->get_results("SELECT * FROM $teachers_table WHERE (role != 'advisor' OR role IS NULL)");
    $phases = $wpdb->get_col("SELECT DISTINCT phase FROM $teachers_table WHERE phase != '' ORDER BY phase");
    $classes = $wpdb->get_col("SELECT DISTINCT class_name FROM $teachers_table WHERE class_name != '' ORDER BY class_name");
    $groups = $wpdb->get_col("
        SELECT DISTINCT `group` FROM (
            SELECT `group` FROM {$wpdb->prefix}tes_students WHERE `group` IS NOT NULL AND `group` != ''
            UNION
            SELECT `group` FROM {$wpdb->prefix}tes_teachers WHERE `group` IS NOT NULL AND `group` != ''
            UNION
            SELECT `group` FROM $surveys_table WHERE `group` IS NOT NULL AND `group` != '' AND `group` != 'all'
        ) AS combined_groups ORDER BY `group` ASC
    ");
    $surveys_query = "
        SELECT s.*, s.`group` as survey_group, t.name AS teacher_name, t.department, t.phase, t.class_name, t.`group` as teacher_group,
               (SELECT COUNT(*) FROM {$wpdb->prefix}tes_questions q WHERE q.survey_id = s.id) AS questions_count
        FROM $surveys_table s
        LEFT JOIN $teachers_table t ON s.teacher_id = t.id
    ";

    if (!empty($search_term)) {
        $like = '%' . $wpdb->esc_like($search_term) . '%';
        $surveys_query .= $wpdb->prepare(" WHERE s.title LIKE %s OR t.name LIKE %s OR t.phase LIKE %s OR t.class_name LIKE %s OR s.`group` LIKE %s OR t.`group` LIKE %s", $like, $like, $like, $like, $like, $like);
    }

    $surveys_query .= " ORDER BY s.id DESC";
    $surveys = $wpdb->get_results($surveys_query);

    $edit_survey = null;
    if (isset($_GET['edit_survey'])) {
        $edit_survey = $wpdb->get_row($wpdb->prepare("SELECT * FROM $surveys_table WHERE id = %d", intval($_GET['edit_survey'])));
    }
    ?>

    <div class="wrap">
        <h1><?php echo $edit_survey ? 'Edit' : 'Create'; ?> Teacher Survey</h1>

        <form method="post" style="margin-bottom:25px;">
            <?php if ($edit_survey): ?>
                <input type="hidden" name="survey_id" value="<?php echo esc_attr($edit_survey->id); ?>">
            <?php endif; ?>
            <input type="text"
                   name="survey_title"
                   placeholder="Survey Title"
                   required
                   value="<?php echo $edit_survey ? esc_attr($edit_survey->title) : ''; ?>"
                   style="width:300px;">

            <select id="tes_survey_phase_filter" style="margin-right: 5px;">
                <option value="">All Phases</option>
                <?php foreach ($phases as $p): ?>
                    <option value="<?php echo esc_attr($p); ?>"><?php echo esc_html($p); ?></option>
                <?php endforeach; ?>
            </select>

            <select id="tes_survey_class_filter" style="margin-right: 5px;">
                <option value="">All Classes</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
                <?php endforeach; ?>
            </select>

            <select id="tes_survey_group_filter" style="margin-right: 5px;">
                <option value="">All Groups</option>
                <?php foreach ($groups as $g): ?>
                    <option value="<?php echo esc_attr($g); ?>"><?php echo esc_html($g); ?></option>
                <?php endforeach; ?>
            </select>

            <select name="teacher_id" id="tes_survey_teacher_select" required style="margin-right: 5px;">
                <option value="">Select Teacher</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?php echo esc_attr($t->id); ?>"
                        data-phase="<?php echo esc_attr($t->phase); ?>"
                        data-class="<?php echo esc_attr($t->class_name); ?>"
                        data-group="<?php echo esc_attr($t->group); ?>"
                        <?php selected($edit_survey ? $edit_survey->teacher_id : '', $t->id); ?>>
                        <?php echo esc_html($t->name . ' (' . $t->department . (!empty($t->phase) ? ' - ' . $t->phase : '') . (!empty($t->class_name) ? ' - ' . $t->class_name : '') . (!empty($t->group) ? ' - Group ' . $t->group : '') . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="survey_group" id="tes_survey_group_select" style="margin-right: 5px; min-width: 140px;">
                <option value="">Select Group (or Auto from Teacher)</option>
                <option value="all" <?php selected($edit_survey ? $edit_survey->group : '', 'all'); ?>>All Groups (Open to All)</option>
                <?php foreach ($groups as $g): ?>
                    <option value="<?php echo esc_attr($g); ?>" <?php selected($edit_survey ? $edit_survey->group : '', $g); ?>>
                        Group: <?php echo esc_html($g); ?>
                    </option>
                <?php endforeach; ?>
                <option value="new_group">+ Add New Group</option>
            </select>

            <input type="text"
                   name="new_survey_group"
                   id="new_survey_group_input"
                   placeholder="Enter new group"
                   style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; display: none; width: 130px; margin-right: 5px;">

            <button type="submit"
                    name="tes_save_survey"
                    class="button button-primary">
                <?php echo $edit_survey ? 'Update' : 'Add'; ?> Survey
            </button>
            <?php if ($edit_survey): ?>
                <a href="?page=tes-surveys" class="button">Cancel</a>
            <?php endif; ?>
        </form>

        <h2>Existing Surveys</h2>

        <form method="get" style="margin-bottom: 15px;">
            <input type="hidden" name="page" value="tes-surveys">
            <div style="position: relative; display: inline-block; width: 320px;">
                <input type="text" name="s" id="tes_survey_search_input" value="<?php echo esc_attr($search_term); ?>" placeholder="Search by Survey, Teacher, Phase, Class or Group" style="width: 100%;" autocomplete="off">
                <div id="tes_survey_search_suggestions" style="display:none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ccd0d4; z-index: 1000; max-height: 300px; overflow-y: auto; box-shadow: 0 4px 5px rgba(0,0,0,0.1);"></div>
            </div>
            <button type="submit" class="button button-secondary">Search</button>
            <?php if (!empty($search_term)): ?>
                <a href="<?php echo admin_url('admin.php?page=tes-surveys'); ?>" class="button">Reset</a>
            <?php endif; ?>
        </form>

        <form method="post">
        <?php wp_nonce_field('tes_bulk_delete_surveys_nonce', '_wpnonce'); ?>
        <div class="tablenav top" style="padding: 10px 0;">
            <div class="alignleft actions">
                <button type="submit" name="tes_bulk_delete_surveys" class="button button-secondary" onclick="return confirm('Are you sure you want to delete selected surveys?');">Delete Selected</button>
            </div>
        </div>

        <table class="widefat striped">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>
                    <th>ID</th>
                    <th>Survey Title</th>
                    <th>Teacher</th>
                    <th>Phase</th>
                    <th>Class</th>
                    <th>Group</th>
                    <th>Questions</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($surveys): foreach ($surveys as $s): ?>
                    <tr>
                        <th scope="row" class="check-column"><input type="checkbox" name="survey_ids[]" value="<?php echo esc_attr($s->id); ?>"></th>
                        <td><?php echo esc_html($s->id); ?></td>
                        <td><?php echo esc_html($s->title); ?></td>
                        <td><?php echo esc_html($s->teacher_name . ' (' . $s->department . ')'); ?></td>
                        <td><?php echo esc_html($s->phase); ?></td>
                        <td><?php echo esc_html($s->class_name); ?></td>
                        <td>
                            <?php 
                            $disp_group = !empty($s->survey_group) ? $s->survey_group : (!empty($s->teacher_group) ? $s->teacher_group : '');
                            if (empty($disp_group) || strcasecmp($disp_group, 'all') === 0) {
                                echo '<span style="color:#64748b; font-size:12px;">All Groups</span>';
                            } else {
                                echo '<span style="background:#e0f2fe; color:#0369a1; font-weight:600; font-size:12px; padding:3px 8px; border-radius:4px; border:1px solid #bae6fd;">Group ' . esc_html($disp_group) . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($s->questions_count > 0): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=tes-questions&survey_id=' . $s->id)); ?>" class="button button-secondary button-small" style="font-weight: 600; color: #0284c7;" title="View and manage questions in Question Builder">
                                    &#9998; <?php echo intval($s->questions_count); ?> Questions
                                </a>
                            <?php else: ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=tes-surveys&auto_assign_questions=' . $s->id)); ?>" class="button button-primary button-small" style="font-size: 11px;" title="Set standard questions from Survey Question Builder">
                                    &#9889; Set Questions
                                </a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($s->created_at); ?></td>
                        <td>
                            <a href="?page=tes-surveys&edit_survey=<?php echo $s->id; ?>" class="button button-secondary">Edit</a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=tes-questions&survey_id=' . $s->id)); ?>" class="button button-secondary" title="Survey Question Builder">Questions</a>
                            <a href="?page=tes-surveys&delete_survey=<?php echo $s->id; ?>" class="button button-secondary" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr>
                        <td colspan="10">No surveys created yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </form>
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
        var searchInput = $('#tes_survey_search_input');
        var suggestionsBox = $('#tes_survey_search_suggestions');
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
                        action: 'tes_survey_search_autocomplete',
                        term: term
                    },
                    success: function(response) {
                        suggestionsBox.empty();
                        if (response.success && response.data.length > 0) {
                            $.each(response.data, function(i, item) {
                                var escapedTitle = $('<div>').text(item.title).html();
                                var effGroup = item.survey_group || item.teacher_group || '';
                                var meta = (item.teacher_name || 'No Teacher') + (item.phase ? ' (' + item.phase + ')' : '') + (item.class_name ? ' - ' + item.class_name : '') + (effGroup && effGroup.toLowerCase() !== 'all' ? ' - Group ' + effGroup : '');
                                
                                var $itemDiv = $('<div class="tes-suggestion-item"></div>');
                                $itemDiv.attr('data-value', item.title);
                                $itemDiv.html('<strong>' + escapedTitle + '</strong><br><span style="color:#666; font-size:12px;">' + meta + '</span>');
                                
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

        $(document).on('click', '#tes_survey_search_suggestions .tes-suggestion-item', function() {
            var value = $(this).data('value');
            searchInput.val(value);
            suggestionsBox.hide();
            searchInput.closest('form').submit();
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('#tes_survey_search_input').length && !$(e.target).closest('#tes_survey_search_suggestions').length) {
                suggestionsBox.hide();
            }
        });

        // Filter teachers by phase, class, and group
        $('#tes_survey_phase_filter, #tes_survey_class_filter, #tes_survey_group_filter').on('change', function() {
            var phase = $('#tes_survey_phase_filter').val();
            var className = $('#tes_survey_class_filter').val();
            var group = $('#tes_survey_group_filter').val();
            var $teacherSelect = $('#tes_survey_teacher_select');
            
            $teacherSelect.find('option[value!=""]').each(function() {
                var $opt = $(this);
                var optPhase = $opt.data('phase');
                var optClass = $opt.data('class');
                var optGroup = $opt.data('group');
                
                var matchPhase = !phase || optPhase == phase;
                var matchClass = !className || optClass == className;
                var matchGroup = !group || optGroup == group;
                
                if (matchPhase && matchClass && matchGroup) {
                    $opt.show();
                } else {
                    $opt.hide();
                }
            });
            
            // Reset selection if the currently selected teacher is hidden
            var selectedOption = $teacherSelect.find('option:selected');
            if (selectedOption.css('display') === 'none') {
                $teacherSelect.val('');
            }
        });

        // Auto-select group when teacher is selected
        $('#tes_survey_teacher_select').on('change', function() {
            var selectedOption = $(this).find('option:selected');
            var teacherGroup = selectedOption.data('group');
            var $groupSelect = $('#tes_survey_group_select');

            if (teacherGroup && (!$groupSelect.val() || $groupSelect.data('auto-set'))) {
                if ($groupSelect.find('option[value="' + teacherGroup + '"]').length > 0) {
                    $groupSelect.val(teacherGroup);
                } else {
                    $groupSelect.find('option[value="new_group"]').before('<option value="' + teacherGroup + '">Group: ' + teacherGroup + '</option>');
                    $groupSelect.val(teacherGroup);
                }
                $groupSelect.data('auto-set', true);
                $('#new_survey_group_input').hide();
            }
        });

        $('#tes_survey_group_select').on('change', function() {
            $(this).data('auto-set', false);
            if ($(this).val() === 'new_group') {
                $('#new_survey_group_input').show().focus();
            } else {
                $('#new_survey_group_input').hide();
            }
        });

        // Select All Checkbox
        $('#cb-select-all-1').on('click', function() {
            var checked = this.checked;
            $('input[name="survey_ids[]"]').prop('checked', checked);
        });
    });
    </script>
<?php
}
