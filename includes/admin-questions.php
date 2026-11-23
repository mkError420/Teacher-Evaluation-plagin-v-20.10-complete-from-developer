<?php
if (!defined('ABSPATH')) exit;

function tes_questions_page() {
    global $wpdb;

    $surveys_table   = $wpdb->prefix . 'tes_surveys';
    $questions_table = $wpdb->prefix . 'tes_questions';

    /* -------------------------
       ADD QUESTION (AJAX now handles this, but keep for fallback? No, remove old logic since AJAX)
    --------------------------*/
    // Removed old bulk add logic

    /* -------------------------
       DELETE QUESTION
    --------------------------*/
    if (isset($_GET['delete'])) {
        $q_id = intval($_GET['delete']);
        $survey_id = $wpdb->get_var($wpdb->prepare("SELECT survey_id FROM $questions_table WHERE id = %d", $q_id));
        
        $wpdb->delete($questions_table, ['id' => $q_id]);
        
        if ($survey_id) {
            $wpdb->update($surveys_table, ['last_updated' => current_time('mysql')], ['id' => $survey_id]);
        }
        
        echo '<div class="updated notice is-dismissible"><p>Question deleted.</p></div>';
    }

    /* -------------------------
       AUTO ASSIGN QUESTIONS
    --------------------------*/
    if (isset($_GET['auto_assign_questions'])) {
        $target_id = intval($_GET['auto_assign_questions']);
        if ($target_id > 0) {
            $inserted = tes_assign_default_questions_to_survey($target_id);
            echo '<div class="updated notice is-dismissible"><p>Successfully set ' . intval($inserted) . ' questions on survey from Survey Question Builder.</p></div>';
        }
    }

    /* -------------------------
       BULK DELETE QUESTIONS
    --------------------------*/
    if (isset($_POST['tes_bulk_delete_questions']) && !empty($_POST['question_ids'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'tes_bulk_delete_questions_nonce')) {
            wp_die('Security check failed');
        }
        $ids = array_map('intval', $_POST['question_ids']);
        $ids_string = implode(',', $ids);
        
        // Get survey IDs before deleting
        $survey_ids = $wpdb->get_col("SELECT DISTINCT survey_id FROM $questions_table WHERE id IN ($ids_string)");
        
        $wpdb->query("DELETE FROM $questions_table WHERE id IN ($ids_string)");
        
        if ($survey_ids) {
            $survey_ids_string = implode(',', array_map('intval', $survey_ids));
            $wpdb->query("UPDATE $surveys_table SET last_updated = NOW() WHERE id IN ($survey_ids_string)");
        }
        
        echo '<div class="updated notice is-dismissible"><p>' . count($ids) . ' questions deleted successfully.</p></div>';
    }

    // Handle Search and Active Selection
    $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $selected_survey_id = isset($_GET['survey_id']) ? intval($_GET['survey_id']) : 0;
    $is_just_created    = !empty($_GET['survey_created']);
    $created_q_count    = isset($_GET['q_count']) ? intval($_GET['q_count']) : 0;

    $surveys = $wpdb->get_results("
        SELECT s.*, t.name AS teacher_name, t.department, t.phase, t.class_name
        FROM $surveys_table s
        LEFT JOIN {$wpdb->prefix}tes_teachers t ON s.teacher_id = t.id
        ORDER BY s.id DESC
    ");

    $surveys_by_id = [];
    if ($surveys) {
        foreach ($surveys as $s) {
            $surveys_by_id[$s->id] = $s;
        }
    }

    $questions_query = "
        SELECT q.*, s.title AS survey_title
        FROM $questions_table q
        LEFT JOIN $surveys_table s ON q.survey_id = s.id
    ";

    if (!empty($search_term)) {
        $like = '%' . $wpdb->esc_like($search_term) . '%';
        $questions_query .= $wpdb->prepare(" WHERE s.title LIKE %s", $like);
    }

    $questions_query .= " ORDER BY s.id DESC, q.id ASC";
    $questions = $wpdb->get_results($questions_query);
    ?>

    <div class="wrap">
        <h1>Survey Question Builder</h1>

        <?php if ($is_just_created && $selected_survey_id > 0): 
            $created_survey = isset($surveys_by_id[$selected_survey_id]) ? $surveys_by_id[$selected_survey_id] : null;
        ?>
            <div class="updated notice is-dismissible" style="border-left: 4px solid #0284c7; background: #ffffff; padding: 16px 20px; margin: 18px 0 22px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 20px; color: #0284c7;">&#10004;</span>
                            <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #0f172a;">
                                Survey Automatically Set with Questions!
                            </h3>
                        </div>
                        <p style="margin: 6px 0 0; font-size: 13.5px; color: #475569;">
                            Survey <strong>"<?php echo esc_html($created_survey ? $created_survey->title : 'New Survey'); ?>"</strong> 
                            <?php if ($created_survey && $created_survey->teacher_name): ?>
                                (Teacher: <strong><?php echo esc_html($created_survey->teacher_name); ?></strong><?php echo $created_survey->department ? ' - ' . esc_html($created_survey->department) : ''; ?>)
                            <?php endif; ?>
                            has been set at Survey Question Builder with <strong><?php echo $created_q_count > 0 ? $created_q_count : 'all'; ?> standard questions</strong> automatically populated.
                        </p>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=tes-surveys')); ?>" class="button button-secondary" style="font-weight: 600;">
                            &larr; Create Another Teacher Survey
                        </a>
                        <a href="#tes-survey-section-<?php echo $selected_survey_id; ?>" class="button button-primary" style="background: #0284c7; border-color: #0284c7; font-weight: 600;">
                            View Questions Below &darr;
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form id="tes-add-question-form" style="max-width:800px; margin-bottom:20px;">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('tes_add_question'); ?>">

            <select name="survey_id" id="survey_id" required style="width:100%;margin-bottom:10px;">
                <option value="">Select Survey</option>
                <?php foreach ($surveys as $s): ?>
                    <option value="<?php echo esc_attr($s->id); ?>" <?php selected($selected_survey_id, $s->id); ?>>
                        <?php echo esc_html($s->title . (!empty($s->teacher_name) ? ' (' . $s->teacher_name . ')' : '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="question_type" id="question_type" required style="width:100%;margin-bottom:10px;">
                <option value="Explicit Issues">Explicit Issues</option>
                <option value="Implicit Issues">Implicit Issues</option>
            </select>

            <input type="text" name="question_text" id="question_text" placeholder="Question Title" required style="width:100%; margin-bottom:10px; padding:8px;">
            <select name="sub_question_title" id="sub_question_title" required style="width:100%; margin-bottom:10px; padding:8px;">
                <option value="">Select Sub Question</option>
            </select>

            <p class="description">Standard Answer Options (Fixed):</p>
            <div id="options-container">
                <input type="text" name="options[]" value="Never" readonly style="width:48%; margin-bottom:5px; padding:8px; background-color: #f9f9f9;">
                <input type="text" name="options[]" value="Once in a while" readonly style="width:48%; margin-bottom:5px; padding:8px; background-color: #f9f9f9;">
                <input type="text" name="options[]" value="Sometimes" readonly style="width:48%; margin-bottom:5px; padding:8px; background-color: #f9f9f9;">
                <input type="text" name="options[]" value="Most of the times" readonly style="width:48%; margin-bottom:5px; padding:8px; background-color: #f9f9f9;">
                <input type="text" name="options[]" value="Almost always" readonly style="width:48%; margin-bottom:5px; padding:8px; background-color: #f9f9f9;">
            </div>

            <button type="submit"
                    class="button button-primary"
                    style="margin-top:10px;">
                Add Question
            </button>
        </form>

        <hr>

        <h2>Existing Questions</h2>

        <form method="get" style="margin-bottom: 15px;">
            <input type="hidden" name="page" value="tes-questions">
            <div style="position: relative; display: inline-block; width: 300px;">
                <input type="text" name="s" id="tes_question_search_input" value="<?php echo esc_attr($search_term); ?>" placeholder="Search by Survey Name" style="width: 100%;" autocomplete="off">
                <div id="tes_question_search_suggestions" style="display:none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ccd0d4; z-index: 1000; max-height: 300px; overflow-y: auto; box-shadow: 0 4px 5px rgba(0,0,0,0.1);"></div>
            </div>
            <button type="submit" class="button button-secondary">Search</button>
            <?php if (!empty($search_term)): ?>
                <a href="<?php echo admin_url('admin.php?page=tes-questions'); ?>" class="button">Reset</a>
            <?php endif; ?>
        </form>

        <?php
        // Group questions by survey_id
        $grouped_questions = [];
        if ($questions) {
            foreach ($questions as $q) {
                $s_id = intval($q->survey_id);
                $grouped_questions[$s_id][] = $q;
            }
        }

        // Determine which surveys to show in accordion
        $display_survey_ids = [];

        // If active survey selected, prioritize it at the top
        if ($selected_survey_id > 0 && isset($surveys_by_id[$selected_survey_id])) {
            $display_survey_ids[] = $selected_survey_id;
        }

        // Add remaining surveys
        foreach ($surveys_by_id as $s_id => $s_obj) {
            if (!in_array($s_id, $display_survey_ids)) {
                if (!empty($search_term)) {
                    $match_title = stripos($s_obj->title, $search_term) !== false;
                    $match_teacher = !empty($s_obj->teacher_name) && stripos($s_obj->teacher_name, $search_term) !== false;
                    if ($match_title || $match_teacher) {
                        $display_survey_ids[] = $s_id;
                    }
                } else {
                    $display_survey_ids[] = $s_id;
                }
            }
        }
        ?>

        <form method="post">
        <?php wp_nonce_field('tes_bulk_delete_questions_nonce', '_wpnonce'); ?>
        <div class="tablenav top" style="padding: 10px 0;">
            <div class="alignleft actions">
                <button type="submit" name="tes_bulk_delete_questions" class="button button-secondary" onclick="return confirm('Are you sure you want to delete selected questions?');">Delete Selected</button>
            </div>
        </div>

        <div id="tes-questions-accordion">
            <?php if (!empty($display_survey_ids)): foreach ($display_survey_ids as $s_id): 
                $s_info = isset($surveys_by_id[$s_id]) ? $surveys_by_id[$s_id] : null;
                $survey_title = $s_info ? $s_info->title : 'Survey #' . $s_id;
                $teacher_info = $s_info && !empty($s_info->teacher_name) ? $s_info->teacher_name . (!empty($s_info->department) ? ' (' . $s_info->department . ')' : '') : '';
                $questions_in_survey = isset($grouped_questions[$s_id]) ? $grouped_questions[$s_id] : [];
                $q_count = count($questions_in_survey);

                // Auto-expand if searching or if it is the actively selected/just created survey
                $is_selected = ($selected_survey_id > 0 && $s_id === $selected_survey_id);
                $is_expanded = (!empty($search_term) || $is_selected);
                $container_style = $is_expanded ? 'display: block;' : 'display: none;';
                $title_class = $is_expanded ? 'tes-survey-title active' : 'tes-survey-title';
                $section_border = $is_selected ? 'border: 2px solid #0284c7; box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);' : 'border: 1px solid #ccd0d4;';
            ?>
                <div class="tes-survey-section" id="tes-survey-section-<?php echo esc_attr($s_id); ?>" style="<?php echo $section_border; ?>">
                    <h3 class="<?php echo $title_class; ?>">
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span style="font-weight: 700; color: #0f172a;"><?php echo esc_html($survey_title); ?></span>
                            <?php if (!empty($teacher_info)): ?>
                                <span style="color: #64748b; font-size: 12.5px; font-weight: normal;">&bull; <?php echo esc_html($teacher_info); ?></span>
                            <?php endif; ?>
                            <?php if ($q_count > 0): ?>
                                <span style="background: #e2e8f0; color: #334155; font-size: 11.5px; font-weight: 600; padding: 2px 8px; border-radius: 999px;">
                                    <?php echo $q_count; ?> questions
                                </span>
                            <?php else: ?>
                                <span style="background: #fef3c7; color: #b45309; font-size: 11.5px; font-weight: 600; padding: 2px 8px; border-radius: 999px;">
                                    0 questions
                                </span>
                            <?php endif; ?>
                            <?php if ($is_selected): ?>
                                <span style="background: #0284c7; color: #ffffff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px;">
                                    Active / Set
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="dashicons dashicons-arrow-down-alt2 toggle-icon"></span>
                    </h3>
                    <div class="tes-questions-table-container" style="<?php echo $container_style; ?>">
                        <?php if ($q_count > 0): ?>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <td class="manage-column column-cb check-column"><input type="checkbox" class="select-all-survey-questions"></td>
                                        <th>Type</th>
                                        <th>Question</th>
                                        <th>Sub Question</th>
                                        <th>Options</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="tes-survey-tbody-<?php echo esc_attr($s_id); ?>">
                                    <?php foreach ($questions_in_survey as $q): ?>
                                        <tr>
                                            <th scope="row" class="check-column"><input type="checkbox" name="question_ids[]" value="<?php echo esc_attr($q->id); ?>"></th>
                                            <td>
                                                <span style="background: #f1f5f9; color: #334155; font-size: 11.5px; font-weight: 600; padding: 2px 7px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                                    <?php echo esc_html($q->question_type); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html($q->question_text); ?></td>
                                            <td><strong><?php echo esc_html($q->sub_question_title); ?></strong></td>
                                            <td><span style="font-size: 12px; color: #64748b;"><?php echo esc_html($q->options); ?></span></td>
                                            <td><?php echo esc_html($q->created_at); ?></td>
                                            <td>
                                                <a class="button button-secondary button-small"
                                                   href="?page=tes-questions&delete=<?php echo $q->id; ?><?php echo $selected_survey_id ? '&survey_id=' . $selected_survey_id : ''; ?>"
                                                   onclick="return confirm('Are you sure you want to delete this question?');">
                                                   Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="padding: 16px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div style="color: #92400e; font-size: 13.5px;">
                                    This survey has no questions assigned yet.
                                </div>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=tes-questions&auto_assign_questions=' . $s_id . '&survey_id=' . $s_id)); ?>" class="button button-primary button-small" style="background: #0284c7; border-color: #0284c7; font-weight: 600;">
                                    &#9889; Set Questions Automatically from Builder
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="card" style="padding: 15px;"><p>No surveys or questions found.</p></div>
            <?php endif; ?>
        </div>
        </form>

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
            .tes-survey-section {
                border: 1px solid #ccd0d4;
                margin-bottom: 10px;
                background: #fff;
                border-radius: 4px;
                overflow: hidden;
            }
            .tes-survey-title {
                margin: 0;
                padding: 12px 15px;
                cursor: pointer;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 1.1em;
                font-weight: 600;
            }
            .tes-survey-title:hover {
                background: #f0f0f1;
            }
            .tes-survey-title .toggle-icon {
                transition: transform 0.2s ease-in-out;
            }
            .tes-survey-title.active .toggle-icon {
                transform: rotate(-180deg);
            }
            .tes-questions-table-container {
                padding: 0 15px 15px;
                border-top: 1px solid #e0e0e0;
            }
        </style>
        <script>
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

        var explicitQuestions = <?php echo json_encode(tes_get_builder_subquestions('Explicit Issues')); ?>;
        var implicitQuestions = <?php echo json_encode(tes_get_builder_subquestions('Implicit Issues')); ?>;

        jQuery(document).ready(function($) {
            // Auto scroll to active/newly set survey section
            <?php if ($selected_survey_id > 0): ?>
            var $targetSec = $('#tes-survey-section-<?php echo intval($selected_survey_id); ?>');
            if ($targetSec.length) {
                setTimeout(function() {
                    $('html, body').animate({
                        scrollTop: $targetSec.offset().top - 80
                    }, 350);
                }, 150);
            }
            <?php endif; ?>

            $('#tes-add-question-form').on('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(this);
                formData.append('action', 'tes_add_question');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            var sId = data.survey_id || $('#survey_id').val();
                            var newRow = '<tr>' +
                                '<th scope="row" class="check-column"><input type="checkbox" name="question_ids[]" value="' + data.id + '"></th>' +
                                '<td><span style="background: #f1f5f9; color: #334155; font-size: 11.5px; font-weight: 600; padding: 2px 7px; border-radius: 4px; border: 1px solid #e2e8f0;">' + data.question_type + '</span></td>' +
                                '<td>' + data.question_text + '</td>' +
                                '<td><strong>' + (data.sub_question_title || '') + '</strong></td>' +
                                '<td><span style="font-size: 12px; color: #64748b;">' + data.options + '</span></td>' +
                                '<td>' + data.created_at + '</td>' +
                                '<td><a class="button button-secondary button-small" href="?page=tes-questions&delete=' + data.id + '">Delete</a></td>' +
                                '</tr>';

                            var $targetTbody = $('#tes-survey-tbody-' + sId);
                            if ($targetTbody.length) {
                                $targetTbody.prepend(newRow);
                                var $section = $('#tes-survey-section-' + sId);
                                $section.find('.tes-questions-table-container').slideDown('fast');
                                $section.find('.tes-survey-title').addClass('active');
                            } else {
                                location.reload();
                            }
                            
                            // Remove selected option to prevent duplicate addition
                            $("#sub_question_title option:selected").remove();
                            $('#sub_question_title').val('');
                        } else {
                            alert(response.data);
                        }
                    },
                    error: function() {
                        alert('An error occurred.');
                    }
                });
            });

            // Question search autocomplete
            var searchInput = $('#tes_question_search_input');
            var suggestionsBox = $('#tes_question_search_suggestions');
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
                            action: 'tes_question_search_autocomplete',
                            term: term
                        },
                        success: function(response) {
                            suggestionsBox.empty();
                            if (response.success && response.data.length > 0) {
                                $.each(response.data, function(i, item) {
                                    var escapedSurvey = $('<div>').text(item.survey_title).html();

                                    var $itemDiv = $('<div class="tes-suggestion-item"></div>');
                                    $itemDiv.attr('data-value', item.survey_title);
                                    $itemDiv.html('<strong>' + escapedSurvey + '</strong>');
                                    
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

            $(document).on('click', '#tes_question_search_suggestions .tes-suggestion-item', function() {
                var value = $(this).data('value');
                searchInput.val(value);
                suggestionsBox.hide();
                searchInput.closest('form').submit();
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('#tes_question_search_input').length && !$(e.target).closest('#tes_question_search_suggestions').length) {
                    suggestionsBox.hide();
                }
            });

            // Accordion for survey questions
            $('.tes-survey-title').on('click', function() {
                $(this).toggleClass('active');
                $(this).next('.tes-questions-table-container').slideToggle('fast');
            });

            // Select all checkbox for a survey section
            $('.select-all-survey-questions').on('click', function() {
                var checked = this.checked;
                $(this).closest('.tes-questions-table-container').find('tbody input[type="checkbox"][name="question_ids[]"]').prop('checked', checked);
            });
            
            function populateSubQuestions() {
                var type = $('#question_type').val();
                var $subQ = $('#sub_question_title');
                
                $subQ.empty();
                $subQ.append('<option value="">Select Sub Question</option>');
                
                var list = [];
                if (type === 'Explicit Issues') {
                    list = explicitQuestions;
                } else if (type === 'Implicit Issues') {
                    list = implicitQuestions;
                }
                
                $.each(list, function(index, value) {
                    $subQ.append($('<option></option>').attr('value', value).text(value));
                });
            }

            // Handle Question Type Change
            $('#question_type').on('change', function() {
                var type = $(this).val();
                var $qText = $('#question_text');

                if (type === 'Explicit Issues') {
                    $qText.val('How well does the teacher teach the core subject?');
                } else if (type === 'Implicit Issues') {
                    $qText.val('How well does the teacher model the core values through how he/she behaves with students and with other staff persons?');
                }
                populateSubQuestions();
            });

            // Reset options when survey changes
            $('#survey_id').on('change', function() {
                populateSubQuestions();
            });

            // Initialize state
            $('#question_type').trigger('change');
        });
        </script>
    </div>

<?php
}
