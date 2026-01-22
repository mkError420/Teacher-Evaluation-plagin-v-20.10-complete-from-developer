<?php
if (!defined('ABSPATH')) exit;

/**
 * Handle Teacher Authentication
 */
function tes_handle_teacher_auth() {
    if (is_admin()) return;

    // Login
    if (isset($_POST['tes_teacher_login']) && isset($_POST['username']) && isset($_POST['password'])) {
        global $wpdb;
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        
        $teacher = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tes_teachers WHERE username = %s", $username));

        if ($teacher && (wp_check_password($password, $teacher->password) || $password === $teacher->password)) {
            setcookie('tes_teacher_id', $teacher->id, time() + 86400, COOKIEPATH, COOKIE_DOMAIN);
            wp_redirect(remove_query_arg('tes_login_error'));
            exit;
        } else {
            wp_redirect(add_query_arg('tes_login_error', '1'));
            exit;
        }
    }

    // Logout
    if (isset($_REQUEST['tes_teacher_action']) && $_REQUEST['tes_teacher_action'] === 'logout') {
        setcookie('tes_teacher_id', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        wp_redirect(remove_query_arg('tes_teacher_action'));
        exit;
    }
}
add_action('init', 'tes_handle_teacher_auth');

/**
 * Teacher Dashboard Shortcode [teacher_dashboard]
 */
function tes_teacher_dashboard_shortcode() {
    global $wpdb;

    $teacher_id = isset($_COOKIE['tes_teacher_id']) ? intval($_COOKIE['tes_teacher_id']) : 0;
    $current_teacher = null;
    
    if ($teacher_id) {
        $current_teacher = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tes_teachers WHERE id = %d", $teacher_id));
        if (!$current_teacher) $teacher_id = 0;
    }

    ob_start();

    // --- Login Form ---
    if (!$teacher_id) {
        $error = isset($_GET['tes_login_error']);
        ?>
        <div class="tes-login-wrapper" style="max-width: 400px; margin: 40px auto; padding: 30px; background: #fff; border: 1px solid #ddd; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); font-family: sans-serif;">
            <h2 style="text-align: center; margin-bottom: 20px; color: #333;">Teacher Dashboard Login</h2>
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
                <button type="submit" name="tes_teacher_login" value="1" style="width: 100%; padding: 12px; background: #0073aa; color: white; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer;">Login</button>
            </form>
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
    if (isset($current_teacher->role) && $current_teacher->role === 'advisor') {
        if (function_exists('tes_render_advisor_dashboard_content')) {
            $logout_url = add_query_arg('tes_teacher_action', 'logout');
            tes_render_advisor_dashboard_content($current_teacher, $logout_url);
            return ob_get_clean();
        }
    }

    $surveys_table     = $wpdb->prefix . 'tes_surveys';
    $questions_table   = $wpdb->prefix . 'tes_questions';
    $submissions_table = $wpdb->prefix . 'tes_submissions';

    // Handle Search
    $search_term = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';

    // Get surveys for this teacher only
    $query = "SELECT * FROM $surveys_table WHERE teacher_id = %d";
    $query_args = [$teacher_id];

    if (!empty($search_term)) {
        $query .= " AND title LIKE %s";
        $query_args[] = '%' . $wpdb->esc_like($search_term) . '%';
    }
    $query .= " ORDER BY id DESC";
    $surveys = $wpdb->get_results($wpdb->prepare($query, ...$query_args));

    $selected_survey = isset($_REQUEST['survey_id']) ? intval($_REQUEST['survey_id']) : 0;
    
    // Verify survey belongs to this teacher
    if ($selected_survey) {
        $check_ownership = $wpdb->get_var($wpdb->prepare("SELECT id FROM $surveys_table WHERE id = %d AND teacher_id = %d", $selected_survey, $teacher_id));
        if (!$check_ownership) $selected_survey = 0;
    }
    ?>

    <div class="tes-dashboard-container" style="max-width: 1000px; margin: 20px auto; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e9ecef;">
            <div>
                <h2 style="margin: 0; font-size: 20px;">Welcome, <?php echo esc_html($current_teacher->name); ?></h2>
                <span style="color: #666;"><?php echo esc_html($current_teacher->department); ?></span>
            </div>
        <a href="<?php echo esc_url(add_query_arg('tes_teacher_action', 'logout')); ?>" style="color: #dc3545; text-decoration: none; font-weight: bold;">Logout</a>
        </div>

        <!-- Search Form -->
        <form method="post" style="margin-bottom: 15px;">
            <div style="display: flex; gap: 10px;">
                <input type="text" name="s" value="<?php echo esc_attr($search_term); ?>" placeholder="Search Survey Title..." style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; flex-grow: 1; max-width: 300px;">
                <button type="submit" class="button" style="padding: 8px 15px; cursor: pointer;">Search</button>
                <?php if (!empty($search_term)): ?>
                    <a href="<?php echo remove_query_arg('s'); ?>" style="padding: 8px 15px; background: #eee; text-decoration: none; color: #333; border-radius: 4px; border: 1px solid #ccc;">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Select Survey Form -->
        <form method="post" style="margin-bottom:20px; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
            <select disabled style="margin-right: 5px; padding: 8px; border-radius: 4px; border: 1px solid #ccc; background-color: #f0f0f0; min-width: 200px;">
                <option selected><?php echo esc_html($current_teacher->name . ' (' . $current_teacher->department . ')'); ?></option>
            </select>

            <select name="survey_id" required style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; min-width: 250px;">
                <option value="">Select Survey</option>
                <?php foreach ($surveys as $s): ?>
                    <option value="<?php echo esc_attr($s->id); ?>" <?php selected($selected_survey, $s->id); ?>>
                        <?php echo esc_html($s->title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button button-primary" style="padding: 8px 15px; cursor: pointer;">View Results</button>
        </form>

        <?php if ($selected_survey):
            $survey_title = $wpdb->get_var($wpdb->prepare("SELECT title FROM $surveys_table WHERE id = %d", $selected_survey));
        ?>
        <?php
            // Logic adapted from admin-results.php
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

            $submissions = $wpdb->get_results($wpdb->prepare("SELECT s.answers, s.student_name, s.comment 
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
                        $student_averages[$sub->student_name] = $student_sum / $student_count;
                    }
                }

                $overall_avg = $total_count > 0 ? $total_sum / $total_count : null;

                if ($overall_avg !== null) {
                    echo '<div style="background: #f1f1f1; padding: 15px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">';
                    echo '<h2 style="margin: 0; color: #333;">Overall Average Rating: ' . number_format($overall_avg, 2) . ' / 5</h2>';
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

                foreach ($type_order as $type):
                    if (empty($grouped_questions[$type])) continue;
                    echo '<h2 style="margin-top: 30px; border-bottom: 2px solid #ccc; padding-bottom: 10px; color: #23282d;">' . esc_html($type) . '</h2>';
                    
                    foreach ($grouped_questions[$type] as $q):
                        $options = array_map('trim', explode(',', $q->options));
                        $avg = isset($averages[$q->id]) && $averages[$q->id]['count'] > 0 ? $averages[$q->id]['sum'] / $averages[$q->id]['count'] : null;
                        $chart_data = [];
                        foreach ($options as $opt) {
                            $chart_data[] = isset($answer_counts[$q->id][$opt]) ? intval($answer_counts[$q->id][$opt]) : 0;
                        }
                        ?>
                        <div style="margin-bottom:25px; padding:15px; border:1px solid #ddd; background: #fff;">
                            <strong style="font-size: 1.1em;"><?php echo esc_html($q->sub_question_title ? $q->sub_question_title : $q->question_text); ?></strong>
                            <?php if ($q->sub_question_title && $q->question_text && $q->sub_question_title !== $q->question_text): ?>
                                <div style="font-size: 0.9em; color: #666; margin-top: 5px; font-style: italic;"><?php echo esc_html($q->question_text); ?></div>
                            <?php endif; ?>

                            <?php if ($avg !== null): ?>
                                <p><strong>Average Rating: <?php echo number_format($avg, 2); ?> / 5</strong></p>
                            <?php endif; ?>

                            <table style="width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #e5e5e5;">
                                <thead>
                                    <tr style="background: #f8f9fa; border-bottom: 1px solid #e5e5e5;">
                                        <th style="padding: 10px; text-align: left; color: #32373c;">Option</th>
                                        <th style="padding: 10px; text-align: left; color: #32373c;">Responses</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($options as $opt): ?>
                                        <tr style="border-bottom: 1px solid #f0f0f0;">
                                            <td style="padding: 10px;"><?php echo esc_html($opt); ?></td>
                                            <td style="padding: 10px;"><?php echo isset($answer_counts[$q->id][$opt]) ? intval($answer_counts[$q->id][$opt]) : 0; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <div style="max-width: 300px; margin: 20px auto;">
                                <canvas id="chart-<?php echo $q->id; ?>"></canvas>
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
                    <h2 style="margin-top: 30px; border-bottom: 2px solid #ccc; padding-bottom: 10px; color: #23282d;">Student-wise Average Ratings</h2>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #e5e5e5;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 1px solid #e5e5e5;">
                                <th style="padding: 10px; text-align: left; color: #32373c;">Student Name</th>
                                <th style="padding: 10px; text-align: left; color: #32373c;">Average Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_averages as $name => $avg): ?>
                                <tr style="border-bottom: 1px solid #f0f0f0;">
                                    <td style="padding: 10px;"><?php echo esc_html($name); ?></td>
                                    <td style="padding: 10px;"><?php echo number_format($avg, 2); ?> / 5</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif;

                if (!empty($comments)): ?>
                    <h2 style="margin-top: 30px; border-bottom: 2px solid #ccc; padding-bottom: 10px; color: #23282d;">Student Comments</h2>
                    <div style="background: #fff; border: 1px solid #ddd; overflow: hidden;">
                        <?php foreach ($comments as $c): ?>
                            <div style="padding: 15px; border-bottom: 1px solid #eee;">
                                <strong style="display: block; color: #555; margin-bottom: 5px;"><?php echo esc_html($c['student']); ?>:</strong>
                                <?php echo nl2br(esc_html($c['text'])); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif;
            }
        endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('teacher_dashboard', 'tes_teacher_dashboard_shortcode');