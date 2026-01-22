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
        
        echo '<div class="updated notice"><p>Question deleted.</p></div>';
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
        
        echo '<div class="updated notice"><p>' . count($ids) . ' questions deleted successfully.</p></div>';
    }

    // Handle Search
    $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    $surveys = $wpdb->get_results("SELECT * FROM $surveys_table");
    $questions_query = "
        SELECT q.*, s.title AS survey_title
        FROM $questions_table q
        LEFT JOIN $surveys_table s ON q.survey_id = s.id
    ";

    if (!empty($search_term)) {
        $like = '%' . $wpdb->esc_like($search_term) . '%';
        $questions_query .= $wpdb->prepare(" WHERE s.title LIKE %s", $like);
    }

    $questions_query .= " ORDER BY s.title ASC, q.id DESC";
    $questions = $wpdb->get_results($questions_query);
    ?>

    <div class="wrap">
        <h1>Survey Question Builder</h1>

        <form id="tes-add-question-form" style="max-width:800px; margin-bottom:20px;">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('tes_add_question'); ?>">

            <select name="survey_id" id="survey_id" required style="width:100%;margin-bottom:10px;">
                <option value="">Select Survey</option>
                <?php foreach ($surveys as $s): ?>
                    <option value="<?php echo esc_attr($s->id); ?>">
                        <?php echo esc_html($s->title); ?>
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
        // Group questions by survey title
        $grouped_questions = [];
        if ($questions) {
            foreach ($questions as $q) {
                if (empty($q->survey_title)) {
                    $q->survey_title = 'Unassigned Questions';
                }
                $grouped_questions[$q->survey_title][] = $q;
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
            <?php if (!empty($grouped_questions)): foreach ($grouped_questions as $survey_title => $questions_in_survey): 
                // Auto-expand if searching
                $is_expanded = !empty($search_term);
                $container_style = $is_expanded ? 'display: block;' : 'display: none;';
                $title_class = $is_expanded ? 'tes-survey-title active' : 'tes-survey-title';
            ?>
                <div class="tes-survey-section">
                    <h3 class="<?php echo $title_class; ?>">
                        <span><?php echo esc_html($survey_title); ?> (<?php echo count($questions_in_survey); ?> questions)</span>
                        <span class="dashicons dashicons-arrow-down-alt2 toggle-icon"></span>
                    </h3>
                    <div class="tes-questions-table-container" style="<?php echo $container_style; ?>">
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
                            <tbody>
                                <?php foreach ($questions_in_survey as $q): ?>
                                    <tr>
                                        <th scope="row" class="check-column"><input type="checkbox" name="question_ids[]" value="<?php echo esc_attr($q->id); ?>"></th>
                                        <td><?php echo esc_html($q->question_type); ?></td>
                                        <td><?php echo esc_html($q->question_text); ?></td>
                                        <td><?php echo esc_html($q->sub_question_title); ?></td>
                                        <td><?php echo esc_html($q->options); ?></td>
                                        <td><?php echo esc_html($q->created_at); ?></td>
                                        <td>
                                            <a class="button button-secondary"
                                               href="?page=tes-questions&delete=<?php echo $q->id; ?>"
                                               onclick="return confirm('Are you sure you want to delete this question?');">
                                               Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="card" style="padding: 15px;"><p>No questions found.</p></div>
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

        var explicitQuestions = [
            "The teacher gains attention of students at the beginning of the session.",
            "At the beginning of the session teacher inform about the objectives of the session",
            "The teacher checks students previous knowledge related to the topic of the class",
            "Teacher manages the time well ",
            "Language & pronunciation of the teacher is clear",
            "Preparation of the audio visual/teaching aids by the teacher is helpful",
            "Teacher encourages students to speak up and be active in the class",
            "The teacher emphasizes important point & summarize at the end of the class ",
            "The teacher provides useful & relevant references /sources for further study",
            "Teacher assess students learning within the class ",
            "Teaching learning session of this teacher is cooperative"
        ];

        var implicitQuestions = [
            "Teacher is willing to accept his/her own error",
            "Teacher respects the opinions of students",
            "The teacher is approachable as per need of students ",
            "Teachers emphasize good academic environment within the class."
        ];

        jQuery(document).ready(function($) {
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
                            var newRow = '<tr>' +
                                '<th scope="row" class="check-column"><input type="checkbox" name="question_ids[]" value="' + data.id + '"></th>' +
                                '<td>' + data.survey_title + '</td>' +
                                '<td>' + data.question_type + '</td>' +
                                '<td>' + data.question_text + '</td>' +
                                '<td>' + (data.sub_question_title || '') + '</td>' +
                                '<td>' + data.options + '</td>' +
                                '<td>' + data.created_at + '</td>' +
                                '<td><a class="button button-secondary" href="?page=tes-questions&delete=' + data.id + '">Delete</a></td>' +
                                '</tr>';
                            $('tbody').prepend(newRow);
                            
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
