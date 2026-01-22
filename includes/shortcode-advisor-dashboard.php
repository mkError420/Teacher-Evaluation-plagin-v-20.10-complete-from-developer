<?php
if (!defined('ABSPATH')) exit;

/**
 * Handle Advisor Authentication
 */
function tes_handle_advisor_auth() {
    if (is_admin()) return;

    // Login
    if (isset($_POST['tes_advisor_login']) && isset($_POST['username']) && isset($_POST['password'])) {
        global $wpdb;
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        
        // Check for advisor role
        $advisor = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tes_teachers WHERE username = %s AND role = 'advisor'", $username));

        if ($advisor && (wp_check_password($password, $advisor->password) || $password === $advisor->password)) {
            setcookie('tes_advisor_id', $advisor->id, time() + 86400, COOKIEPATH, COOKIE_DOMAIN);
            wp_redirect(remove_query_arg('tes_login_error'));
            exit;
        } else {
            wp_redirect(add_query_arg('tes_login_error', '1'));
            exit;
        }
    }

    // Logout
    if (isset($_REQUEST['tes_advisor_action']) && $_REQUEST['tes_advisor_action'] === 'logout') {
        setcookie('tes_advisor_id', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        wp_redirect(remove_query_arg('tes_advisor_action'));
        exit;
    }
}
add_action('init', 'tes_handle_advisor_auth');

/**
 * Advisor Dashboard Shortcode [advisor_dashboard]
 */
function tes_advisor_dashboard_shortcode() {
    global $wpdb;

    $advisor_id = isset($_COOKIE['tes_advisor_id']) ? intval($_COOKIE['tes_advisor_id']) : 0;
    $current_advisor = null;
    
    if ($advisor_id) {
        $current_advisor = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tes_teachers WHERE id = %d AND role = 'advisor'", $advisor_id));
        if (!$current_advisor) $advisor_id = 0;
    }

    ob_start();

    // --- Login Form ---
    if (!$advisor_id) {
        $error = isset($_GET['tes_login_error']);
        ?>
        <div class="tes-login-wrapper" style="max-width: 400px; margin: 40px auto; padding: 30px; background: #fff; border: 1px solid #ddd; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); font-family: sans-serif;">
            <h2 style="text-align: center; margin-bottom: 20px; color: #333; text-transform: uppercase; font-weight: bold">Teacher Login</h2>
            <?php if ($error): ?>
                <div style="background: #ffebee; color: #c62828; padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; border: 1px solid #ef9a9a;">Invalid username or password.</div>
            <?php endif; ?>
            <form method="post">
                <div style="margin-bottom: 20px;">
                    <label for="tes_username" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Username</label>
                    <input type="text" name="username" id="tes_username" required placeholder="Username" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 25px;">
                    <label for="tes_password" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Password</label>
                    <div style="position: relative;">
                        <input type="password" name="password" id="tes_password" required placeholder="Password" style="width: 100%; padding: 10px 40px 10px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
                        <span id="tes-toggle-password" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #757575; user-select: none; font-size: 18px;">&#128065;</span>
                    </div>
                </div>
                <button type="submit" name="tes_advisor_login" value="1" style="width: 100%; padding: 12px; background: #757575; color: white; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; text-transform: uppercase;cursor: pointer;">Login</button>
            </form>
            <div style="text-align: center; margin-top: 15px; color: #666; font-size: 12px;">Developed by IT Department (Rangpur Group)-2026</div>
        </div>
        <script>
        (function() {
            const togglePassword = document.getElementById('tes-toggle-password');
            const password = document.getElementById('tes_password');

            if (togglePassword && password) {
                togglePassword.addEventListener('click', function () {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    this.innerHTML = type === 'password' ? '&#128065;' : '&#128584;';
                });
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    // --- Dashboard ---
    $logout_url = add_query_arg('tes_advisor_action', 'logout');
    tes_render_advisor_dashboard_content($current_advisor, $logout_url);

    return ob_get_clean();
}
add_shortcode('advisor_dashboard', 'tes_advisor_dashboard_shortcode');

function tes_render_advisor_dashboard_content($current_advisor, $logout_url) {
    global $wpdb;

    $surveys_table     = $wpdb->prefix . 'tes_surveys';
    $teachers_table    = $wpdb->prefix . 'tes_teachers';
    $questions_table   = $wpdb->prefix . 'tes_questions';
    $submissions_table = $wpdb->prefix . 'tes_submissions';

    // Handle Filters
    $selected_survey = isset($_REQUEST['survey_id']) ? intval($_REQUEST['survey_id']) : 0;
    $search_term     = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';

    // Get all teachers for dropdown
    $all_teachers = $wpdb->get_results("SELECT id, name, department FROM $teachers_table WHERE (role != 'advisor' OR role IS NULL) ORDER BY name ASC");

    // Get all surveys for dropdown (and list)
    $surveys_query = "SELECT s.*, t.name as teacher_name, t.department 
                      FROM $surveys_table s 
                      LEFT JOIN $teachers_table t ON s.teacher_id = t.id";
    
    if (!empty($search_term)) {
        $like = '%' . $wpdb->esc_like($search_term) . '%';
        $surveys_query .= $wpdb->prepare(" WHERE s.title LIKE %s OR t.name LIKE %s", $like, $like);
    }
    
    $surveys_query .= " ORDER BY s.id DESC";
    $surveys = $wpdb->get_results($surveys_query);
    
    // Determine selected teacher from selected survey if present
    $selected_teacher = 0;
    if ($selected_survey) {
        $selected_teacher = $wpdb->get_var($wpdb->prepare("SELECT teacher_id FROM $surveys_table WHERE id = %d", $selected_survey));
    }
    ?>

    <div class="tes-dashboard-container" style="max-width: 1000px; margin: 20px auto; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e9ecef;">
            <div>
                <h2 style="margin: 0; font-size: 20px;">Survey Results Dashboard</h2>
                <span style="color: #666;">Welcome, <?php echo esc_html($current_advisor->name); ?></span>
            </div>
            <a href="<?php echo esc_url($logout_url); ?>" style="color: #dc3545; text-decoration: none; font-weight: bold;">Logout</a>
        </div>

        <!-- Search Survey (Autocomplete) -->
        <form method="get" style="margin-bottom: 15px;">
            <div style="position: relative; display: inline-block; width: 100%; max-width: 400px;">
                <input type="text" name="s" id="tes_advisor_search_input" value="<?php echo esc_attr($search_term); ?>" placeholder="Search Survey Title or Teacher..." style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" autocomplete="off">
                <div id="tes_advisor_search_suggestions" style="display:none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ccd0d4; z-index: 1000; max-height: 300px; overflow-y: auto; box-shadow: 0 4px 5px rgba(0,0,0,0.1);"></div>
            </div>
            <button type="submit" class="button button-secondary" style="padding: 10px 20px; cursor: pointer;">Search</button>
            <?php if (!empty($search_term)): ?>
                <a href="<?php echo remove_query_arg('s'); ?>" class="button" style="padding: 10px 20px; background: #eee; text-decoration: none; color: #333; border-radius: 4px; border: 1px solid #ccc;">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Select Survey (Dropdowns) -->
        <form method="get" style="margin-bottom:20px; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
            <?php if (!empty($search_term)): ?>
                <input type="hidden" name="s" value="<?php echo esc_attr($search_term); ?>">
            <?php endif; ?>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <select id="tes_advisor_teacher_filter" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; min-width: 200px;">
                    <option value="">Select Teacher</option>
                    <?php foreach ($all_teachers as $t): ?>
                        <option value="<?php echo esc_attr($t->id); ?>" <?php selected($selected_teacher, $t->id); ?>>
                            <?php echo esc_html($t->name . ' (' . $t->department . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="survey_id" id="tes_advisor_survey_select" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; min-width: 250px;">
                    <option value="">Select Survey</option>
                    <?php foreach ($surveys as $s): ?>
                        <option value="<?php echo esc_attr($s->id); ?>" data-teacher-id="<?php echo esc_attr($s->teacher_id); ?>"
                            <?php selected($selected_survey, $s->id); ?>>
                            <?php echo esc_html($s->title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="button button-primary" style="padding: 8px 15px; cursor: pointer; background: #0073aa; color: #fff; border: none; border-radius: 4px;">View Results</button>
            </div>
        </form>

        <?php if (!$selected_survey): ?>

            <!-- Survey List -->
            <div class="card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h3 style="margin-top: 0; margin-bottom: 15px;">All Surveys List</h3>
                <table class="widefat striped" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f1f1f1;">
                            <th style="text-align:left; padding: 10px; border-bottom: 2px solid #ddd;">Survey Title</th>
                            <th style="text-align:left; padding: 10px; border-bottom: 2px solid #ddd;">Teacher</th>
                            <th style="text-align:left; padding: 10px; border-bottom: 2px solid #ddd;">Department</th>
                            <th style="text-align:left; padding: 10px; border-bottom: 2px solid #ddd;">Score</th>
                            <th style="text-align:left; padding: 10px; border-bottom: 2px solid #ddd;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($surveys): foreach ($surveys as $survey): 
                            // Calculate Score for List View
                            $survey_questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $questions_table WHERE survey_id = %d", $survey->id));
                            
                            // Add fixed question
                            $fixed_question = new stdClass();
                            $fixed_question->id = 'fixed_implicit_role_model';
                            $fixed_question->survey_id = $survey->id;
                            $fixed_question->question_type = 'Implicit Issues';
                            $fixed_question->question_text = 'How well does the teacher model the core values through how he/she behaves with students and with other staff persons?';
                            $fixed_question->sub_question_title = 'I follow the teacher as my role model ';
                            $fixed_question->options = 'To much extent,All Most,Yes  ';
                            if (!$survey_questions) $survey_questions = [];
                            $survey_questions[] = $fixed_question;

                            $survey_submissions = $wpdb->get_results($wpdb->prepare("SELECT answers FROM $submissions_table WHERE survey_id = %d", $survey->id));
                            
                            $total_score_sum = 0;
                            $total_score_count = 0;

                            $score_map = [
                                'Never'             => 1,
                                'Once in a while'   => 2,
                                'Sometimes'         => 3,
                                'Most of the times' => 4,
                                'Almost always'     => 5
                            ];
                            
                            if ($survey_questions && $survey_submissions) {
                                $questions_map = [];
                                foreach ($survey_questions as $q) $questions_map[$q->id] = $q;

                                foreach ($survey_submissions as $sub) {
                                    $answers = maybe_unserialize($sub->answers);
                                    if (!is_array($answers)) continue;

                                    foreach ($answers as $q_id => $answer) {
                                        if (!isset($questions_map[$q_id])) continue;
                                        
                                        $value = 0;
                                        if (isset($score_map[$answer])) {
                                            $value = $score_map[$answer];
                                        } else {
                                            $q_options = array_map('trim', explode(',', $questions_map[$q_id]->options));
                                            $num_options = count($q_options);
                                            $index = array_search($answer, $q_options);
                                            if ($index !== false && $num_options > 0) {
                                                $value = (($num_options - $index) / $num_options) * 5;
                                            }
                                        }
                                        
                                        if ($value > 0) {
                                            $total_score_sum += $value;
                                            $total_score_count++;
                                        }
                                    }
                                }
                            }
                            
                            $avg_score = $total_score_count > 0 ? $total_score_sum / $total_score_count : 0;
                        ?>
                            <tr style="border-bottom: 1px solid #f0f0f0;">
                                <td style="padding: 10px;"><?php echo esc_html($survey->title); ?></td>
                                <td style="padding: 10px;"><?php echo esc_html($survey->teacher_name); ?></td>
                                <td style="padding: 10px;"><?php echo esc_html($survey->department); ?></td>
                                <td style="padding: 10px;">
                                    <?php echo $total_score_count > 0 ? number_format($avg_score, 2) . ' / 5' : 'No Data'; ?>
                                </td>
                                <td style="padding: 10px;">
                                    <a href="<?php echo esc_url(add_query_arg('survey_id', $survey->id)); ?>" class="button button-primary" style="text-decoration: none; background: #0073aa; color: #fff; padding: 5px 10px; border-radius: 3px;">View Results</a>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="4" style="padding: 15px; text-align: center;">No surveys found matching your criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: 
            // --- View Results ---
            // Get survey details including teacher name
            $survey_details = $wpdb->get_row($wpdb->prepare(
                "SELECT s.title, t.name, t.department, t.class_name, t.phase 
                 FROM $surveys_table s 
                 LEFT JOIN $teachers_table t ON s.teacher_id = t.id 
                 WHERE s.id = %d", 
                $selected_survey
            ));
        ?>
            <div style="margin-bottom: 20px;">
                <a href="<?php echo remove_query_arg('survey_id'); ?>" class="button" style="text-decoration: none; background: #eee; color: #333; padding: 8px 15px; border-radius: 4px; border: 1px solid #ccc;">&larr; Back to Dashboard</a>
                <button id="tes-download-pdf" class="button" style="background: #2271b1; color: #fff; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin-left: 10px;">Download PDF</button>
            </div>
            <div id="tes-pdf-content" style="padding: 20px; background: #fff; box-sizing: border-box;">
                <h2 style="text-align: center;background: #0c273d; color: #fff; padding: 10px 0;margin-top: 0; margin-bottom: 15px;">Rangpur Community Medical College Hospital (RCMCH)</h2>
                <h3 style="text-align: center; margin-top: 0; margin-bottom: 18px; background: #42525f; color: #fff;">Category of The Medical College:  Non govt</h3>
                <h2 style="margin-top: 0;"><?php echo esc_html($survey_details->title); ?> - Results</h2>
                <p><strong>Date:</strong> <?php echo date('F j, Y'); ?></p>
        <?php
            // Logic adapted from admin-results.php
            // Get questions
            $all_questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $questions_table WHERE survey_id = %d", $selected_survey));

            // Add fixed question
            $fixed_question = new stdClass();
            $fixed_question->id = 'fixed_implicit_role_model';
            $fixed_question->survey_id = $selected_survey;
            $fixed_question->question_type = 'Implicit Issues';
            $fixed_question->question_text = 'How well does the teacher model the core values through how he/she behaves with students and with other staff persons?';
            $fixed_question->sub_question_title = 'I follow the teacher as my role model ';
            $fixed_question->options = 'To much extent,All Most,Yes  ';
            if (!$all_questions) $all_questions = [];
            $all_questions[] = $fixed_question;

            $submissions = $wpdb->get_results($wpdb->prepare("SELECT s.answers, s.student_name, s.comment, s.submission_date 
                                                            FROM $submissions_table s 
                                                            WHERE s.survey_id = %d", $selected_survey));

            if (!$all_questions || !$submissions) {
                echo '<div style="padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 8px;">No submissions found for this survey yet.</div>';
            } else {
                // Process Data
                $questions_by_id = [];
                foreach ($all_questions as $q) $questions_by_id[$q->id] = $q;

                $answer_counts = [];
                $averages = [];
                $student_averages = [];
                $comments = [];
                $total_sum = 0;
                $total_count = 0;

                $score_map = [
                    'Never'             => 1,
                    'Once in a while'   => 2,
                    'Sometimes'         => 3,
                    'Most of the times' => 4,
                    'Almost always'     => 5
                ];

                foreach ($submissions as $sub) {
                    if (!empty($sub->comment)) {
                        $comments[] = ['student' => $sub->student_name, 'text' => $sub->comment];
                    }
                    $answers = maybe_unserialize($sub->answers);
                    if (!is_array($answers)) continue;

                    $student_sum = 0;
                    $student_count = 0;

                    foreach ($answers as $q_id => $answer) {
                        if (!isset($questions_by_id[$q_id])) continue;
                        if (!isset($answer_counts[$q_id])) $answer_counts[$q_id] = [];
                        if (!isset($answer_counts[$q_id][$answer])) $answer_counts[$q_id][$answer] = 0;
                        $answer_counts[$q_id][$answer]++;

                        $value = 0;
                        if (isset($score_map[$answer])) {
                            $value = $score_map[$answer];
                        } else {
                            $current_question = $questions_by_id[$q_id];
                            $q_options = array_map('trim', explode(',', $current_question->options));
                            $num_options = count($q_options);
                            $index = array_search($answer, $q_options);
                            if ($index !== false && $num_options > 0) {
                                $value = (($num_options - $index) / $num_options) * 5;
                            }
                        }
                        if ($value > 0) {
                            if (!isset($averages[$q_id])) $averages[$q_id] = ['sum' => 0, 'count' => 0];
                            $averages[$q_id]['sum'] += $value;
                            $averages[$q_id]['count']++;
                            $student_sum += $value;
                            $student_count++;
                            $total_sum += $value;
                            $total_count++;
                        }
                    }
                    if ($student_count > 0 && !empty($sub->student_name)) {
                        $student_averages[$sub->student_name] = [
                            'avg' => $student_sum / $student_count,
                            'date' => $sub->submission_date
                        ];
                    }
                }

                $overall_avg = $total_count > 0 ? $total_sum / $total_count : null;

                // Calculate eligible students and submissions
                $total_eligible = 0;
                if ($survey_details && !empty($survey_details->class_name)) {
                    $eligible_query = "SELECT COUNT(*) FROM {$wpdb->prefix}tes_students WHERE class_name = %s";
                    $e_params = [$survey_details->class_name];
                    if (!empty($survey_details->phase)) {
                        $eligible_query .= " AND phase = %s";
                        $e_params[] = $survey_details->phase;
                    }
                    $total_eligible = $wpdb->get_var($wpdb->prepare($eligible_query, ...$e_params));
                }
                $submission_count = count($submissions);

                if ($overall_avg !== null) {
                    echo '<div class="tes-pdf-top-section" style="background: #f1f1f1; padding: 15px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; page-break-after: always; width: 100%; box-sizing: border-box;">';
                    if ($survey_details) {
                        echo '<h2 style="margin: 0 0 10px 0; color: #0073aa;">Teacher: ' . esc_html($survey_details->name) . ' (' . esc_html($survey_details->department) . ')</h2>';
                        echo '<p style="margin: 0 0 10px 0; font-size: 1.1em; color: #555;">';
                        echo '<strong>Department:</strong> ' . esc_html($survey_details->department) . ' | ';
                        echo '<strong>Phase:</strong> ' . esc_html($survey_details->phase) . ' | ';
                        echo '<strong>Class:</strong> ' . esc_html($survey_details->class_name);
                        echo '</p>';
                    }
                    echo '<h2 style="margin: 0; color: #333;">Overall Average Rating: ' . number_format($overall_avg, 2) . ' / 5</h2>';
                    echo '<div style="margin-top: 10px; font-size: 1.1em; color: #555;">';
                    echo '<strong>Total Students:</strong> ' . intval($total_eligible) . ' | ';
                    echo '<strong>Submitted:</strong> ' . intval($submission_count);
                    echo '</div>';
                    echo '</div>';
                }

                // Group and Display
                $grouped_questions = [];
                foreach ($all_questions as $q) {
                    $type = isset($q->question_type) ? $q->question_type : 'General';
                    $grouped_questions[$type][] = $q;
                }
                $type_order = ['Explicit Issues', 'Implicit Issues'];
                foreach (array_keys($grouped_questions) as $type) {
                    if (!in_array($type, $type_order)) $type_order[] = $type;
                }

                // Count total questions to manage page breaks
                $total_questions_count = 0;
                foreach ($grouped_questions as $gq) $total_questions_count += count($gq);
                $current_q_count = 0;

                foreach ($type_order as $type):
                    if (empty($grouped_questions[$type])) continue;
                    echo '<h2 class="tes-pdf-section-header" style="margin-top: 30px; border-bottom: 2px solid #ccc; padding-bottom: 10px; color: #23282d;">' . esc_html($type) . '</h2>';
                    
                    foreach ($grouped_questions[$type] as $q):
                        $options = array_map('trim', explode(',', $q->options));
                        $avg = isset($averages[$q->id]) && $averages[$q->id]['count'] > 0 ? $averages[$q->id]['sum'] / $averages[$q->id]['count'] : null;
                        $chart_data = [];
                        foreach ($options as $opt) {
                            $chart_data[] = isset($answer_counts[$q->id][$opt]) ? intval($answer_counts[$q->id][$opt]) : 0;
                        }
                        $current_q_count++;
                        ?>
                        <div class="tes-question-container" style="margin-bottom:25px; padding:15px; border:1px solid #ddd; background: #fff; width: 100%; box-sizing: border-box; page-break-inside: avoid; page-break-after: always;">
                            <strong style="font-size: 1.1em;"><?php echo esc_html($q->sub_question_title ? $q->sub_question_title : $q->question_text); ?></strong>
                            <?php if ($q->sub_question_title && $q->question_text && $q->sub_question_title !== $q->question_text): ?>
                                <div style="font-size: 0.9em; color: #666; margin-top: 5px; font-style: italic;"><?php echo esc_html($q->question_text); ?></div>
                            <?php endif; ?>

                            <?php if ($avg !== null): ?>
                                <p><strong>Average Rating: <?php echo number_format($avg, 2); ?> / 5</strong></p>
                            <?php endif; ?>

                            <div style="margin-top: 10px;">
                                <div class="tes-table-wrapper" style="width: 100%; margin-bottom: 20px;">
                                    <table style="width: 100%; border-collapse: collapse; border: 1px solid #e5e5e5; font-size: 13px;">
                                        <thead>
                                            <tr style="background: #f8f9fa; border-bottom: 1px solid #e5e5e5;">
                                                <th style="padding: 8px; text-align: left; color: #32373c;">Option</th>
                                                <th style="padding: 8px; text-align: left; color: #32373c;">Responses</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($options as $opt): ?>
                                                <tr style="border-bottom: 1px solid #f0f0f0;">
                                                    <td style="padding: 8px;"><?php echo esc_html($opt); ?></td>
                                                    <td style="padding: 8px;"><?php echo isset($answer_counts[$q->id][$opt]) ? intval($answer_counts[$q->id][$opt]) : 0; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="tes-chart-wrapper" style="width: 300px; margin: 0 auto;">
                                    <canvas id="chart-<?php echo $q->id; ?>"></canvas>
                                </div>
                            </div>

                            <script>
                            jQuery(document).ready(function($) {
                                var ctx = document.getElementById('chart-<?php echo $q->id; ?>').getContext('2d');
                                var colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'];
                                new Chart(ctx, {
                                    type: 'pie',
                                    data: {
                                        labels: <?php echo json_encode($options); ?>,
                                        datasets: [{
                                            data: <?php echo json_encode($chart_data); ?>,
                                            backgroundColor: colors.slice(0, <?php echo count($options); ?>),
                                            borderColor: colors.slice(0, <?php echo count($options); ?>),
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        plugins: {
                                            legend: {
                                                position: 'top',
                                            }
                                        }
                                    }
                                });
                            });
                            </script>
                        </div>
                    <?php endforeach;
                endforeach;

                if (!empty($student_averages)): ?>
                    <div class="tes-student-averages-section">
                    <h2 class="tes-pdf-section-header" style="margin-top: 30px; border-bottom: 2px solid #ccc; padding-bottom: 10px; color: #23282d;">Student-wise Average Ratings</h2>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #e5e5e5; font-size: 13px; page-break-inside: auto;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 1px solid #e5e5e5;">
                                <th style="padding: 8px; text-align: left; color: #32373c;">Student Name</th>
                                <th style="padding: 8px; text-align: left; color: #32373c;">Submission Date</th>
                                <th style="padding: 8px; text-align: left; color: #32373c;">Average Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_averages as $name => $data): ?>
                                <tr style="border-bottom: 1px solid #f0f0f0; page-break-inside: avoid;">
                                    <td style="padding: 8px;"><?php echo esc_html($name); ?></td>
                                    <td style="padding: 8px;"><?php echo esc_html(date('F j, Y', strtotime($data['date']))); ?></td>
                                    <td style="padding: 8px;"><?php echo number_format($data['avg'], 2); ?> / 5</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif;
            }
            echo '</div>'; // End tes-pdf-content

            if (isset($comments) && !empty($comments)): ?>
                <div style="padding: 20px; background: #fff; margin-top: 20px; border: 1px solid #ddd; border-radius: 8px; page-break-before: always;">
                    <h2 class="tes-pdf-section-header" style="margin-top: 0; border-bottom: 2px solid #ccc; padding-bottom: 10px; color: #23282d; page-break-after: avoid;">Student Comments</h2>
                    <div style="display: block;">
                        <?php foreach ($comments as $c): ?>
                            <div style="width: 100%; margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; background: #fff; box-sizing: border-box;">
                                <strong style="display: block; color: #555; margin-bottom: 5px;"><?php echo esc_html($c['student']); ?>:</strong>
                                <?php echo nl2br(esc_html($c['text'])); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif;
            ?>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
            <script>
            document.getElementById('tes-download-pdf').addEventListener('click', function() {
                const element = document.getElementById('tes-pdf-content');
                const downloadBtn = this;
                const originalBtnText = downloadBtn.innerHTML;
                const originalWidth = element.style.width;

                // Provide user feedback
                downloadBtn.innerHTML = 'Preparing PDF...';
                downloadBtn.disabled = true;
                
                // Set fixed width for consistent PDF rendering
                element.style.width = '700px';

                const questionContainers = element.querySelectorAll('.tes-question-container');
                const sectionHeaders = element.querySelectorAll('.tes-pdf-section-header');
                const topSection = element.querySelector('.tes-pdf-top-section');
                const studentAverages = element.querySelector('.tes-student-averages-section');
                const tableWrappers = element.querySelectorAll('.tes-table-wrapper');
                const chartWrappers = element.querySelectorAll('.tes-chart-wrapper');

                // Remove margins for PDF to prevent blank pages between questions
                questionContainers.forEach(function(el) {
                    el.style.marginBottom = '0px';
                });
                
                // Adjust headers to prevent extra spacing issues
                sectionHeaders.forEach(function(el) {
                    el.style.marginTop = '10px';
                });
                
                // Compact layout for PDF to ensure single page fit
                tableWrappers.forEach(function(el) {
                    el.style.marginBottom = '10px';
                });
                chartWrappers.forEach(function(el) {
                    el.style.width = '250px';
                });
                
                if(topSection) topSection.style.marginBottom = '0px';
                if(studentAverages) studentAverages.style.display = 'none';

                // Remove page break from the last question to prevent trailing blank page
                if (questionContainers.length > 0) {
                    questionContainers[questionContainers.length - 1].style.pageBreakAfter = 'auto';
                }
                
                // Wait for charts to resize/redraw before capturing
                setTimeout(function() {
                    const canvases = element.querySelectorAll('canvas');
                    const tempImages = [];
                    const promises = [];

                    canvases.forEach(function(canvas) {
                        const promise = new Promise(function(resolve) {
                            const img = new Image();
                            img.onload = resolve;
                            img.src = canvas.toDataURL('image/png');
                            img.style.width = canvas.offsetWidth + 'px';
                            img.style.height = canvas.offsetHeight + 'px';
                            
                            canvas.style.display = 'none';
                            canvas.parentNode.insertBefore(img, canvas);
                            tempImages.push({ img: img, canvas: canvas });
                        });
                        promises.push(promise);
                    });

                    // Wait for all images to be loaded before generating PDF
                    Promise.all(promises).then(function () {
                        const opt = {
                          margin:       0.5,
                          filename:     '<?php echo esc_js(sanitize_file_name($survey_details->title)); ?>_results.pdf',
                          image:        { type: 'jpeg', quality: 0.98 },
                          html2canvas:  { scale: 2, useCORS: true, logging: false },
                          jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' },
                          pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
                        };
                        
                        html2pdf().set(opt).from(element).save().then(function() {
                            // Restore canvases and button state
                            tempImages.forEach(function(item) {
                                item.img.remove();
                                item.canvas.style.display = '';
                            });
                            // Restore margins
                            questionContainers.forEach(function(el) {
                                el.style.marginBottom = '25px';
                            });
                            sectionHeaders.forEach(function(el) {
                                el.style.marginTop = '30px';
                            });
                            // Restore layout
                            tableWrappers.forEach(function(el) {
                                el.style.marginBottom = '20px';
                            });
                            chartWrappers.forEach(function(el) {
                                el.style.width = '300px';
                            });
                            
                            if(topSection) topSection.style.marginBottom = '20px';
                            if(studentAverages) studentAverages.style.display = '';
                            if (questionContainers.length > 0) {
                                questionContainers[questionContainers.length - 1].style.pageBreakAfter = 'always';
                            }
                            
                            element.style.width = originalWidth;
                            downloadBtn.innerHTML = originalBtnText;
                            downloadBtn.disabled = false;
                        });
                    });
                }, 1000);
            });
            </script>
            <?php
        endif; ?>
    </div>

    <style>
        .tes-advisor-suggestion-item {
            padding: 8px 10px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        .tes-advisor-suggestion-item:hover {
            background-color: #f0f0f1;
            color: #2271b1;
        }
        .tes-advisor-suggestion-item:last-child {
            border-bottom: none;
        }
    </style>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var searchInput = $('#tes_advisor_search_input');
        var suggestionsBox = $('#tes_advisor_search_suggestions');
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
                    url: tes_ajax.ajax_url,
                    data: {
                        action: 'tes_survey_search_autocomplete',
                        term: term
                    },
                    success: function(response) {
                        suggestionsBox.empty();
                        if (response.success && response.data.length > 0) {
                            var html = '';
                            $.each(response.data, function(i, item) {
                                var escapedTitle = $('<div>').text(item.title).html();
                                var teacherInfo = item.teacher_name ? ' <span style="color:#888; font-size:0.9em;">(' + $('<div>').text(item.teacher_name).html() + ')</span>' : '';
                                html += '<div class="tes-advisor-suggestion-item" data-id="' + item.id + '">' + escapedTitle + teacherInfo + '</div>';
                            });
                            suggestionsBox.html(html).show();
                        } else {
                            suggestionsBox.html('<div class="tes-advisor-suggestion-item" style="cursor: default;">No survey available</div>').show();
                        }
                    }
                });
            }, 300);
        });

        $(document).on('click', '.tes-advisor-suggestion-item', function() {
            var surveyId = $(this).data('id');
            if (!surveyId) return;
            window.location.href = '?survey_id=' + surveyId;
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('#tes_advisor_search_input').length && !$(e.target).closest('#tes_advisor_search_suggestions').length) {
                suggestionsBox.hide();
            }
        });

        // Teacher Filter Logic
        var $teacherSelect = $('#tes_advisor_teacher_filter');
        var $surveySelect = $('#tes_advisor_survey_select');
        var $surveyOptions = $surveySelect.find('option');

        function filterSurveys() {
            var teacherId = $teacherSelect.val();
            
            if (!teacherId) {
                $surveySelect.find('option').show(); // Show all if no teacher selected
            } else {
                $surveyOptions.each(function() {
                    var $opt = $(this);
                    if ($opt.val() === '') {
                        $opt.show();
                        return; 
                    }
                    
                    if ($opt.data('teacher-id') == teacherId) {
                        $opt.show();
                    } else {
                        $opt.hide();
                    }
                });
                
                // If current selection is hidden, reset
                var currentVal = $surveySelect.val();
                if (currentVal && $surveySelect.find('option[value="'+currentVal+'"]').css('display') === 'none') {
                    $surveySelect.val('');
                }
            }
        }

        $teacherSelect.on('change', filterSurveys);
        filterSurveys(); // Run on load
    });
    </script>
    <?php
    // No ob_get_clean() here because it's a helper function that outputs directly
}