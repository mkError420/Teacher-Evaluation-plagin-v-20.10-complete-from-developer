<?php
if (!defined('ABSPATH')) exit;

function tes_results_page() {
    global $wpdb;

    $surveys_table     = $wpdb->prefix . 'tes_surveys';
    $questions_table   = $wpdb->prefix . 'tes_questions';
    $submissions_table = $wpdb->prefix . 'tes_submissions';

    $selected_survey = isset($_GET['survey_id']) ? intval($_GET['survey_id']) : 0;
    $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    $query = "SELECT s.*, s.`group` as survey_group, t.name as teacher_name, t.department, t.phase, t.class_name, t.`group` as teacher_group 
              FROM $surveys_table s 
              LEFT JOIN {$wpdb->prefix}tes_teachers t ON s.teacher_id = t.id";
    if (!empty($search_term)) {
        $like = '%' . $wpdb->esc_like($search_term) . '%';
        $query .= $wpdb->prepare(" WHERE s.title LIKE %s OR t.name LIKE %s", $like, $like);
    }
    $query .= " ORDER BY s.id DESC";
    $surveys = $wpdb->get_results($query);

    // Get all teachers for filter
    $teachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}tes_teachers WHERE (role != 'advisor' OR role IS NULL) ORDER BY name ASC");
    $selected_teacher = 0;
    if ($selected_survey) {
        $selected_teacher = $wpdb->get_var($wpdb->prepare("SELECT teacher_id FROM $surveys_table WHERE id = %d", $selected_survey));
    }
    ?>

    <div class="wrap tes-results-dashboard-wrap" style="max-width: 1200px; margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
        
        <!-- Header Section -->
        <div style="margin-bottom: 24px;">
            <h1 style="display: flex; align-items: center; gap: 10px; margin: 0 0 6px 0; font-size: 26px; font-weight: 700; color: #0f172a;">
                <span class="dashicons dashicons-chart-pie" style="font-size: 30px; width: 30px; height: 30px; color: #0284c7;"></span>
                Survey Results Dashboard
            </h1>
            <p style="margin: 0; color: #64748b; font-size: 14px;">
                Comprehensive analytics, teacher performance evaluations, and student response metrics.
            </p>
        </div>

        <?php if (!empty($search_term) && empty($surveys)): ?>
            <div class="notice notice-warning is-dismissible" style="margin-bottom: 20px;">
                <p>No surveys found matching "<strong><?php echo esc_html($search_term); ?></strong>".</p>
            </div>
        <?php endif; ?>

        <!-- Filter & Search Toolbar (Unified, Simple, Modern) -->
        <div class="tes-filter-card" style="background: #ffffff; padding: 18px 22px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); margin-bottom: 25px;">
            <form method="get" action="<?php echo admin_url('admin.php'); ?>" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                <input type="hidden" name="page" value="tes-dashboard">
                
                <!-- Autocomplete Search Input -->
                <div style="position: relative; flex: 1; min-width: 240px; max-width: 340px;">
                    <input type="text" name="s" id="tes_survey_search_input" value="<?php echo esc_attr($search_term); ?>" placeholder="Search survey title or teacher..." style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13.5px; box-sizing: border-box;" autocomplete="off">
                    <div id="tes_survey_search_suggestions" style="display:none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; z-index: 1000; max-height: 280px; overflow-y: auto; box-shadow: 0 4px 15px rgba(0,0,0,0.1);"></div>
                </div>

                <!-- Teacher Filter Dropdown (No Change Option) -->
                <div style="min-width: 200px;">
                    <select id="tes_dashboard_teacher_filter" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13.5px; box-sizing: border-box; background: #fff;">
                        <option value="">Select Teacher</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo esc_attr($t->id); ?>" <?php selected($selected_teacher, $t->id); ?>>
                                <?php echo esc_html($t->name . ' (' . $t->department . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Survey Filter Dropdown (No Change Option) -->
                <div style="min-width: 240px; flex: 1;">
                    <select name="survey_id" id="tes_dashboard_survey_select" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13.5px; box-sizing: border-box; background: #fff;">
                        <option value="">Select Survey</option>
                        <?php foreach ($surveys as $s): ?>
                            <option value="<?php echo esc_attr($s->id); ?>" data-teacher-id="<?php echo esc_attr($s->teacher_id); ?>"
                                <?php selected($selected_survey, $s->id); ?>>
                                <?php echo esc_html($s->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="button button-primary" style="padding: 8px 18px; height: auto; font-size: 13.5px; font-weight: 600; border-radius: 6px; background: #0284c7; border: none; cursor: pointer; color: #fff; display: inline-flex; align-items: center; gap: 6px; transition: background 0.15s;">
                        View Results
                    </button>
                    <?php if (!empty($search_term) || !empty($selected_survey)): ?>
                        <a href="<?php echo admin_url('admin.php?page=tes-dashboard'); ?>" class="button" style="padding: 8px 14px; height: auto; font-size: 13.5px; border-radius: 6px; background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; text-decoration: none; display: inline-flex; align-items: center;">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php
        if ($selected_survey):

            // Get survey details including teacher name
            $survey_details = $wpdb->get_row($wpdb->prepare(
                "SELECT s.title, s.`group` as survey_group, t.name, t.class_name, t.phase, t.department, t.`group` as teacher_group 
                 FROM $surveys_table s 
                 LEFT JOIN {$wpdb->prefix}tes_teachers t ON s.teacher_id = t.id 
                 WHERE s.id = %d", 
                $selected_survey
            ));

            // Get questions
            $all_questions = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM $questions_table WHERE survey_id = %d", $selected_survey)
            );

            // Add fixed question for results display
            $fixed_question = new stdClass();
            $fixed_question->id = 'fixed_implicit_role_model';
            $fixed_question->survey_id = $selected_survey;
            $fixed_question->question_type = 'Implicit Issues';
            $fixed_question->question_text = 'How well does the teacher model the core values through how he/she behaves with students and with other staff persons?';
            $fixed_question->sub_question_title = 'I follow the teacher as my role model ';
            $fixed_question->options = 'To much extent,All Most,Yes  ';
            if (!$all_questions) {
                $all_questions = [];
            }
            $all_questions[] = $fixed_question;

            // Get submissions
            $submissions = $wpdb->get_results(
                $wpdb->prepare("SELECT s.id, s.student_id, s.student_name, s.answers, s.comment, s.submission_date,
                                       st.roll, st.phase as student_phase, st.class_name as student_class, st.batch_name, st.session, st.department as student_dept, st.`group` as student_group
                                FROM $submissions_table s
                                LEFT JOIN {$wpdb->prefix}tes_students st ON (s.student_id = st.id OR (s.student_id IS NULL AND s.student_name = st.student_name))
                                WHERE s.survey_id = %d
                                ORDER BY s.id DESC", $selected_survey)
            );

            if (!$all_questions || !$submissions): ?>
                <div style="background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center; color: #64748b;">
                    <div style="font-size: 18px; font-weight: 600; color: #1e293b; margin-bottom: 6px;">No Submissions Found</div>
                    <p style="margin: 0 0 16px 0;">No student evaluation submissions have been recorded for this survey yet.</p>
                    <a href="<?php echo admin_url('admin.php?page=tes-dashboard'); ?>" class="button button-secondary">
                        &larr; Back to All Surveys
                    </a>
                </div>
            <?php else:
                // Process Questions & Submissions
                $questions_by_id = [];
                foreach ($all_questions as $q) {
                    $questions_by_id[$q->id] = $q;
                }

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

                foreach ($submissions as $sub_idx => $sub) {
                    if (!empty($sub->comment)) {
                        $comments[] = [
                            'student' => $sub->student_name,
                            'text' => $sub->comment
                        ];
                    }
                    $answers = maybe_unserialize($sub->answers);
                    if (!is_array($answers)) continue;

                    $student_sum = 0;
                    $student_count = 0;
                    $student_details_list = [];

                    foreach ($all_questions as $q) {
                        $q_id = $q->id;
                        $q_ans = isset($answers[$q_id]) ? $answers[$q_id] : null;

                        $value = 0;
                        if ($q_ans !== null) {
                            if (!isset($answer_counts[$q_id])) $answer_counts[$q_id] = [];
                            if (!isset($answer_counts[$q_id][$q_ans])) $answer_counts[$q_id][$q_ans] = 0;
                            $answer_counts[$q_id][$q_ans]++;

                            if (isset($score_map[$q_ans])) {
                                $value = $score_map[$q_ans];
                            } else {
                                $q_options = array_map('trim', explode(',', $q->options));
                                $num_options = count($q_options);
                                $index = array_search($q_ans, $q_options);
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

                        $student_details_list[] = [
                            'q_id'   => $q_id,
                            'type'   => isset($q->question_type) ? $q->question_type : 'General',
                            'title'  => !empty($q->sub_question_title) ? $q->sub_question_title : $q->question_text,
                            'text'   => $q->question_text,
                            'answer' => $q_ans,
                            'score'  => $value > 0 ? number_format($value, 1) : null
                        ];
                    }

                    if ($student_count > 0 && !empty($sub->student_name)) {
                        $sub_key = !empty($sub->id) ? 'sub_' . $sub->id : 'sub_idx_' . $sub_idx;
                        $student_averages[$sub_key] = [
                            'key'            => $sub_key,
                            'student_name'   => $sub->student_name,
                            'avg'            => $student_sum / $student_count,
                            'date'           => $sub->submission_date,
                            'formatted_date' => date('F j, Y, g:i a', strtotime($sub->submission_date)),
                            'comment'        => !empty($sub->comment) ? $sub->comment : '',
                            'roll'           => !empty($sub->roll) ? $sub->roll : '',
                            'class'          => !empty($sub->student_class) ? $sub->student_class : (!empty($survey_details->class_name) ? $survey_details->class_name : ''),
                            'phase'          => !empty($sub->student_phase) ? $sub->student_phase : (!empty($survey_details->phase) ? $survey_details->phase : ''),
                            'batch'          => !empty($sub->batch_name) ? $sub->batch_name : '',
                            'session'        => !empty($sub->session) ? $sub->session : '',
                            'group'          => !empty($sub->student_group) ? $sub->student_group : '',
                            'details'        => $student_details_list
                        ];
                    }
                }

                $overall_avg = $total_count > 0 ? $total_sum / $total_count : null;

                // Calculate eligible students
                $total_eligible = 0;
                $eff_group = !empty($survey_details->survey_group) ? trim($survey_details->survey_group) : (!empty($survey_details->teacher_group) ? trim($survey_details->teacher_group) : '');
                if ($survey_details && !empty($survey_details->class_name)) {
                    $eligible_query = "SELECT COUNT(*) FROM {$wpdb->prefix}tes_students WHERE class_name = %s";
                    $e_params = [$survey_details->class_name];
                    if (!empty($survey_details->phase)) {
                        $eligible_query .= " AND phase = %s";
                        $e_params[] = $survey_details->phase;
                    }
                    if (!empty($eff_group) && strcasecmp($eff_group, 'all') !== 0) {
                        $eligible_query .= " AND `group` = %s";
                        $e_params[] = $eff_group;
                    }
                    $total_eligible = $wpdb->get_var($wpdb->prepare($eligible_query, ...$e_params));
                }
                $submission_count = count($submissions);
                $palette_colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#64748b', '#6366f1'];
            ?>

            <!-- Results View Actions Bar -->
            <div class="tes-no-print" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <a href="<?php echo admin_url('admin.php?page=tes-dashboard'); ?>" class="button" style="text-decoration: none; background: #f8fafc; color: #334155; padding: 8px 16px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    &larr; Back to All Surveys
                </a>
                <button onclick="window.print();" class="button" style="background: #0284c7; color: #fff; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    <span class="dashicons dashicons-printer" style="font-size: 17px; width: 17px; height: 17px; line-height: 1;"></span> Print / Save Report
                </button>
            </div>

            <!-- Print Area Container -->
            <div id="tes-admin-print-area">
                
                <!-- Executive Summary / KPI Cards -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    
                    <!-- College Branding Header -->
                    <div style="text-align: center; margin-bottom: 22px; border-bottom: 1px solid #f1f5f9; padding-bottom: 18px;">
                        <div style="display: inline-block; background: #0c273d; color: #ffffff; padding: 8px 18px; border-radius: 6px; font-size: 16px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 6px;">
                            Rangpur Community Medical College Hospital (RCMCH)
                        </div>
                        <div style="font-size: 12px; color: #64748b; font-weight: 500; margin-bottom: 10px;">
                            Category of The Medical College: Non govt.
                        </div>
                        <h2 style="margin: 8px 0 4px; font-size: 21px; font-weight: 700; color: #0f172a;">
                            <?php echo esc_html($survey_details->title); ?> - Evaluation Report
                        </h2>
                        <span style="font-size: 13px; color: #64748b;">
                            <strong>Date:</strong> <?php echo date('F j, Y'); ?>
                        </span>
                    </div>

                    <!-- 3 KPI Scorecard Grid -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
                        
                        <!-- KPI 1: Overall Average Rating -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; text-align: center;">
                            <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 600; margin-bottom: 6px;">Overall Average Rating</div>
                            <div style="font-size: 30px; font-weight: 800; color: #0284c7; line-height: 1.2;">
                                <?php echo number_format($overall_avg, 2); ?> <span style="font-size: 16px; color: #64748b; font-weight: normal;">/ 5.0</span>
                            </div>
                            <div style="margin-top: 8px;">
                                <?php
                                $rating_label = $overall_avg >= 4.5 ? 'Excellent' : ($overall_avg >= 3.5 ? 'Good' : ($overall_avg >= 2.5 ? 'Average' : 'Needs Improvement'));
                                $badge_bg = $overall_avg >= 3.5 ? '#ecfdf5' : ($overall_avg >= 2.5 ? '#fef3c7' : '#fee2e2');
                                $badge_color = $overall_avg >= 3.5 ? '#047857' : ($overall_avg >= 2.5 ? '#b45309' : '#dc2626');
                                ?>
                                <span style="background: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; display: inline-block;">
                                    ★ <?php echo $rating_label; ?>
                                </span>
                            </div>
                        </div>

                        <!-- KPI 2: Teacher & Academic Information -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; text-align: left;">
                            <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 600; margin-bottom: 6px;">Teacher Details</div>
                            <div style="font-size: 17px; font-weight: 700; color: #0f172a; margin-bottom: 6px;">
                                <?php echo esc_html($survey_details->name); ?>
                            </div>
                            <div style="font-size: 13px; color: #475569; line-height: 1.6;">
                                <div><strong>Dept:</strong> <?php echo esc_html($survey_details->department); ?></div>
                                <div><strong>Class:</strong> <?php echo esc_html($survey_details->class_name); ?> | <strong>Phase:</strong> <?php echo esc_html($survey_details->phase); ?><?php if (!empty($eff_group) && strcasecmp($eff_group, 'all') !== 0) echo ' | <strong>Group:</strong> ' . esc_html($eff_group); ?></div>
                            </div>
                        </div>

                        <!-- KPI 3: Participation & Submissions -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; text-align: center;">
                            <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 600; margin-bottom: 6px;">Student Responses</div>
                            <div style="font-size: 22px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
                                <?php echo intval($submission_count); ?> <span style="font-size: 14px; font-weight: normal; color: #64748b;">/ <?php echo intval($total_eligible); ?> Students</span>
                            </div>
                            <?php if ($total_eligible > 0): 
                                $resp_rate = round(($submission_count / $total_eligible) * 100, 1);
                            ?>
                                <div style="margin-top: 10px;">
                                    <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: #64748b; margin-bottom: 3px;">
                                        <span>Response Rate</span>
                                        <span style="font-weight: 700; color: #0f172a;"><?php echo $resp_rate; ?>%</span>
                                    </div>
                                    <div style="background: #e2e8f0; height: 6px; border-radius: 3px; overflow: hidden;">
                                        <div style="width: <?php echo min(100, $resp_rate); ?>%; background: #0284c7; height: 100%; border-radius: 3px;"></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 13px; color: #64748b; margin-top: 6px;">Total eligible students not configured</div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

                <!-- Group Questions by Type -->
                <?php
                $grouped_questions = [];
                foreach ($all_questions as $q) {
                    $type = isset($q->question_type) ? $q->question_type : 'General';
                    if (!isset($grouped_questions[$type])) {
                        $grouped_questions[$type] = [];
                    }
                    $grouped_questions[$type][] = $q;
                }

                $type_order = ['Explicit Issues', 'Implicit Issues'];
                foreach (array_keys($grouped_questions) as $type) {
                    if (!in_array($type, $type_order)) {
                        $type_order[] = $type;
                    }
                }

                foreach ($type_order as $type):
                    if (empty($grouped_questions[$type])) continue;
                    ?>
                    
                    <!-- Section Header -->
                    <div style="margin: 30px 0 16px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #0284c7; padding-bottom: 8px;">
                        <h2 style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
                            <?php echo esc_html($type); ?>
                        </h2>
                        <span style="background: #e0f2fe; color: #0284c7; font-size: 12px; font-weight: 700; padding: 2px 8px; border-radius: 999px;">
                            <?php echo count($grouped_questions[$type]); ?> Questions
                        </span>
                    </div>

                    <?php
                    foreach ($grouped_questions[$type] as $q):
                        $options = array_map('trim', explode(',', $q->options));
                        $avg = isset($averages[$q->id]) && $averages[$q->id]['count'] > 0 ? $averages[$q->id]['sum'] / $averages[$q->id]['count'] : null;
                        $chart_data = [];
                        foreach ($options as $opt) {
                            $chart_data[] = isset($answer_counts[$q->id][$opt]) ? intval($answer_counts[$q->id][$opt]) : 0;
                        }
                        $q_total_resp = array_sum($chart_data);
                    ?>
                        <div class="tes-question-container" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); page-break-inside: avoid;">
                            
                            <!-- Question Title & Rating Header -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                                <div style="flex: 1; min-width: 260px;">
                                    <div style="font-size: 15.5px; font-weight: 700; color: #0f172a; line-height: 1.4;">
                                        <?php echo esc_html($q->sub_question_title ? $q->sub_question_title : $q->question_text); ?>
                                    </div>
                                    <?php if ($q->sub_question_title && $q->question_text && $q->sub_question_title !== $q->question_text): ?>
                                        <div style="font-size: 13px; color: #64748b; margin-top: 4px; font-style: italic;">
                                            <?php echo esc_html($q->question_text); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($avg !== null): 
                                    $q_badge_bg = $avg >= 3.5 ? '#ecfdf5' : ($avg >= 2.5 ? '#fef3c7' : '#fee2e2');
                                    $q_badge_color = $avg >= 3.5 ? '#047857' : ($avg >= 2.5 ? '#b45309' : '#dc2626');
                                ?>
                                    <span style="background: <?php echo $q_badge_bg; ?>; color: <?php echo $q_badge_color; ?>; font-size: 13px; font-weight: 700; padding: 5px 12px; border-radius: 999px; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;">
                                        Average Rating: <?php echo number_format($avg, 2); ?> / 5
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- 2-Column Responsive Body: Table & Pie Chart -->
                            <div style="display: flex; gap: 24px; flex-wrap: wrap; align-items: center;">
                                
                                <!-- Left Column: Options Table (No Change Option) -->
                                <div class="tes-table-wrapper" style="flex: 1; min-width: 280px;">
                                    <table style="width: 100%; border-collapse: collapse; border: 1px solid #e2e8f0; font-size: 13px; border-radius: 8px; overflow: hidden;">
                                        <thead>
                                            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                                <th style="padding: 10px 12px; text-align: left; color: #475569; font-weight: 600;">Option</th>
                                                <th style="padding: 10px 12px; text-align: center; color: #475569; font-weight: 600; width: 85px;">Responses</th>
                                                <th style="padding: 10px 12px; text-align: left; color: #475569; font-weight: 600; width: 130px;">Distribution</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($options as $opt_idx => $opt): 
                                                $opt_count = isset($answer_counts[$q->id][$opt]) ? intval($answer_counts[$q->id][$opt]) : 0;
                                                $opt_pct = $q_total_resp > 0 ? round(($opt_count / $q_total_resp) * 100, 1) : 0;
                                                $color = $palette_colors[$opt_idx % count($palette_colors)];
                                            ?>
                                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                                    <td style="padding: 8px 12px; color: #334155;">
                                                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: <?php echo esc_attr($color); ?>; margin-right: 6px;"></span>
                                                        <?php echo esc_html($opt); ?>
                                                    </td>
                                                    <td style="padding: 8px 12px; text-align: center; font-weight: 700; color: #0f172a;">
                                                        <?php echo $opt_count; ?>
                                                    </td>
                                                    <td style="padding: 8px 12px;">
                                                        <div style="display: flex; align-items: center; gap: 8px;">
                                                            <div style="flex: 1; background: #f1f5f9; height: 6px; border-radius: 3px; overflow: hidden;">
                                                                <div style="width: <?php echo $opt_pct; ?>%; background: <?php echo esc_attr($color); ?>; height: 100%; border-radius: 3px;"></div>
                                                            </div>
                                                            <span style="font-size: 11px; color: #64748b; font-weight: 600; min-width: 32px;"><?php echo $opt_pct; ?>%</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Right Column: Chart.js Pie Chart -->
                                <div class="tes-chart-wrapper" style="width: 250px; max-width: 100%; margin: 0 auto; text-align: center; height: 230px; position: relative;">
                                    <canvas id="chart-<?php echo esc_attr($q->id); ?>" width="240" height="230"></canvas>
                                </div>
                            </div>

                            <script>
                            jQuery(document).ready(function($) {
                                var canvasEl = document.getElementById('chart-<?php echo esc_js($q->id); ?>');
                                if (!canvasEl) return;
                                var ctx = canvasEl.getContext('2d');
                                var colors = <?php echo json_encode($palette_colors); ?>;
                                new Chart(ctx, {
                                    type: 'pie',
                                    data: {
                                        labels: <?php echo json_encode($options); ?>,
                                        datasets: [{
                                            data: <?php echo json_encode($chart_data); ?>,
                                            backgroundColor: colors.slice(0, <?php echo count($options); ?>),
                                            borderColor: '#ffffff',
                                            borderWidth: 2
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'bottom',
                                                labels: {
                                                    boxWidth: 10,
                                                    padding: 6,
                                                    font: { size: 10.5 }
                                                }
                                            }
                                        }
                                    }
                                });
                            });
                            </script>
                        </div>
                    <?php endforeach; 
                endforeach; ?>

                <!-- Student-wise Average Ratings -->
                <?php if (!empty($student_averages)): ?>
                    <div style="background: #ffffff; padding: 22px; margin-top: 25px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); page-break-inside: avoid;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                            <div>
                                <h3 style="margin: 0; font-size: 17px; font-weight: 700; color: #0f172a;">Student-wise Average Ratings</h3>
                                <p style="margin: 3px 0 0; font-size: 12.5px; color: #64748b;">Click on any student name to view that student's complete detailed survey responses</p>
                            </div>
                            <span style="background: #e2e8f0; color: #334155; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px;">
                                <?php echo count($student_averages); ?> Students
                            </span>
                        </div>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                <thead>
                                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                        <th style="padding: 10px 14px; text-align: left; color: #475569; font-weight: 600;">Student Name</th>
                                        <th style="padding: 10px 14px; text-align: left; color: #475569; font-weight: 600;">Submission Date</th>
                                        <th style="padding: 10px 14px; text-align: right; color: #475569; font-weight: 600;">Average Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($student_averages as $key => $data): 
                                        $s_avg = $data['avg'];
                                        $s_badge_bg = $s_avg >= 3.5 ? '#ecfdf5' : ($s_avg >= 2.5 ? '#fef3c7' : '#fee2e2');
                                        $s_badge_color = $s_avg >= 3.5 ? '#047857' : ($s_avg >= 2.5 ? '#b45309' : '#dc2626');
                                    ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                            <td style="padding: 10px 14px;">
                                                <button type="button" class="tes-student-click-name" data-target="<?php echo esc_attr($key); ?>" style="background: none; border: none; padding: 0; margin: 0; font-family: inherit; font-size: 13.5px; font-weight: 600; color: #0284c7; cursor: pointer; text-align: left; display: inline-flex; align-items: center; gap: 8px;" title="Click to view detailed survey">
                                                    <span style="text-decoration: underline;"><?php echo esc_html($data['student_name']); ?></span>
                                                    <span style="font-size: 11px; font-weight: 600; background: #e0f2fe; color: #0284c7; padding: 2px 7px; border-radius: 4px; border: 1px solid #bae6fd;">
                                                        View Survey &#128065;
                                                    </span>
                                                </button>
                                            </td>
                                            <td style="padding: 10px 14px; color: #64748b;">
                                                <?php echo esc_html(date('F j, Y', strtotime($data['date']))); ?>
                                            </td>
                                            <td style="padding: 10px 14px; text-align: right;">
                                                <span style="background: <?php echo $s_badge_bg; ?>; color: <?php echo $s_badge_color; ?>; font-weight: 700; padding: 3px 10px; border-radius: 999px; display: inline-block;">
                                                    ★ <?php echo number_format($s_avg, 2); ?> / 5
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Student Comments Section -->
                <?php if (!empty($comments)): ?>
                    <div style="background: #ffffff; padding: 22px; margin-top: 25px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); page-break-inside: avoid;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                            <div>
                                <h3 style="margin: 0; font-size: 17px; font-weight: 700; color: #0f172a;">Student Feedback & Comments</h3>
                                <span style="font-size: 13px; color: #64748b;"><?php echo count($comments); ?> remarks submitted</span>
                            </div>
                            <input type="text" id="tes-comment-search" placeholder="Search comments by student..." style="width: 100%; max-width: 280px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                        </div>

                        <div id="tes-comments-list" style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($comments as $c): ?>
                                <div class="tes-comment-item" style="padding: 14px 18px; border: 1px solid #f1f5f9; background: #f8fafc; border-radius: 8px; border-left: 4px solid #0284c7;">
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                        <span style="background: #e2e8f0; color: #334155; font-size: 12px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">
                                            <?php echo esc_html($c['student']); ?>
                                        </span>
                                    </div>
                                    <div class="tes-comment-body" style="font-size: 13.5px; color: #334155; line-height: 1.5;">
                                        <?php echo nl2br(esc_html($c['text'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <script>
                    jQuery(document).ready(function($) {
                        $('#tes-comment-search').on('keyup', function() {
                            var value = $(this).val().toLowerCase();
                            $('#tes-comments-list .tes-comment-item').filter(function() {
                                var text = $(this).text().toLowerCase();
                                $(this).toggle(text.indexOf(value) > -1);
                            });
                        });
                    });
                    </script>
                <?php endif; ?>

            </div><!-- End Print Area -->

            <!-- Student Detailed Survey Modal Section -->
            <div id="tes-student-survey-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.65); z-index: 999999; align-items: center; justify-content: center; backdrop-filter: blur(3px); padding: 20px; box-sizing: border-box;">
                <div class="tes-student-modal-dialog" style="background: #ffffff; width: 100%; max-width: 820px; max-height: 88vh; border-radius: 14px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.25); display: flex; flex-direction: column; overflow: hidden; animation: tesModalFadeIn 0.2s ease-out;">
                    
                    <!-- Modal Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 24px; color: #0284c7;">&#128221;</span>
                            <div>
                                <h3 id="tes-modal-student-name" style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a;">Student Survey Details</h3>
                                <div style="font-size: 12.5px; color: #64748b; margin-top: 2px;">
                                    Evaluation survey response for <strong id="tes-modal-survey-title"><?php echo esc_html($survey_details->title); ?></strong>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="tes-close-modal-btn" style="background: none; border: none; font-size: 26px; line-height: 1; color: #64748b; cursor: pointer; padding: 4px 8px; border-radius: 6px; transition: color 0.15s;" title="Close (Esc)">
                            &times;
                        </button>
                    </div>

                    <!-- Modal Body (Scrollable) -->
                    <div id="tes-modal-body" style="padding: 22px 24px; overflow-y: auto; flex: 1;">
                        <!-- Content dynamically loaded from JSON -->
                    </div>

                    <!-- Modal Footer -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
                        <button type="button" id="tes-print-student-modal" class="button" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <span class="dashicons dashicons-printer" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span> Print Student Survey
                        </button>
                        <button type="button" class="tes-close-modal-btn button button-primary" style="background: #0284c7; color: #fff; border: none; padding: 8px 20px; border-radius: 6px; font-size: 13.5px; font-weight: 600; cursor: pointer;">
                            Close
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pre-rendered JSON for Instant Modal Display -->
            <script type="application/json" id="tes-student-submissions-data">
            <?php echo json_encode($student_averages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
            </script>

        <?php endif; ?>

        <?php else: ?>
            
            <!-- When No Survey Selected: All Surveys List View -->
            <div class="tes-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow: hidden;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; background: #f8fafc;">
                    <div>
                        <h3 style="margin: 0; font-size: 17px; font-weight: 700; color: #0f172a;">All Surveys List</h3>
                        <p style="margin: 3px 0 0; font-size: 13px; color: #64748b;">Select any survey to view comprehensive evaluation metrics</p>
                    </div>
                    <span style="background: #e2e8f0; color: #334155; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px;">
                        <?php echo count($surveys); ?> Surveys
                    </span>
                </div>

                <div style="overflow-x: auto;">
                    <table class="tes-table" style="width: 100%; border-collapse: collapse; font-size: 13.5px; text-align: left;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Survey Title</th>
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Teacher & Department</th>
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Phase</th>
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Class</th>
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Group</th>
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center;">Average Score</th>
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: right;">Action</th>
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
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                    <td style="padding: 14px 16px;">
                                        <div style="font-weight: 600; color: #0f172a; font-size: 14px;">
                                            <?php echo esc_html($survey->title); ?>
                                        </div>
                                        <?php if (!empty($survey->created_at)): ?>
                                            <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;">
                                                <?php echo esc_html(date('F j, Y', strtotime($survey->created_at))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <div style="font-weight: 600; color: #1e293b;"><?php echo esc_html($survey->teacher_name ?: 'N/A'); ?></div>
                                        <div style="font-size: 12px; color: #64748b;"><?php echo esc_html($survey->department ?: 'N/A'); ?></div>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <span style="background: #f1f5f9; color: #475569; font-size: 12px; font-weight: 500; padding: 3px 8px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                            <?php echo esc_html($survey->phase ?: 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <span style="background: #f1f5f9; color: #475569; font-size: 12px; font-weight: 500; padding: 3px 8px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                            <?php echo esc_html($survey->class_name ?: 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <?php 
                                        $s_grp = !empty($survey->survey_group) ? $survey->survey_group : (!empty($survey->teacher_group) ? $survey->teacher_group : '');
                                        if (!empty($s_grp) && strcasecmp($s_grp, 'all') !== 0): ?>
                                            <span style="background: #e0f2fe; color: #0369a1; font-size: 12px; font-weight: 600; padding: 3px 8px; border-radius: 4px; border: 1px solid #bae6fd;">
                                                Group <?php echo esc_html($s_grp); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 12px;">All</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 14px 16px; text-align: center;">
                                        <?php if ($total_score_count > 0): 
                                            $badge_bg = $avg_score >= 3.5 ? '#ecfdf5' : ($avg_score >= 2.5 ? '#fef3c7' : '#fee2e2');
                                            $badge_color = $avg_score >= 3.5 ? '#047857' : ($avg_score >= 2.5 ? '#b45309' : '#dc2626');
                                        ?>
                                            <span style="background: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>; font-weight: 700; font-size: 13px; padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px;">
                                                ★ <?php echo number_format($avg_score, 2); ?> <span style="font-weight: normal; font-size: 11px; opacity: 0.85;">/ 5</span>
                                            </span>
                                        <?php else: ?>
                                            <span style="background: #f1f5f9; color: #94a3b8; font-size: 12px; padding: 4px 10px; border-radius: 999px; display: inline-block;">
                                                No Data
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 14px 16px; text-align: right;">
                                        <a href="<?php echo admin_url('admin.php?page=tes-dashboard&survey_id=' . $survey->id); ?>" class="button" style="text-decoration: none; background: #0284c7; color: #fff; padding: 6px 14px; border-radius: 6px; font-weight: 600; font-size: 13px; border: none; display: inline-flex; align-items: center; gap: 4px; transition: background 0.15s;" onmouseover="this.style.background='#0369a1'" onmouseout="this.style.background='#0284c7'">
                                            View Results &rarr;
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="6" style="padding: 30px; text-align: center; color: #64748b;">
                                        <div style="font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 4px;">No surveys found</div>
                                        <div>No evaluation surveys match your search or filter criteria.</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <!-- Scoped Styles for Results Dashboard & Print -->
    <style>
        @keyframes tesModalFadeIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }
        .tes-student-click-name:hover span:first-child {
            color: #0369a1 !important;
        }
        .tes-student-click-name:hover span:last-child {
            background: #0284c7 !important;
            color: #ffffff !important;
        }
        .tes-survey-suggestion-item {
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            color: #1e293b;
            transition: background 0.15s;
        }
        .tes-survey-suggestion-item:hover {
            background-color: #f0f9ff;
            color: #0284c7;
        }
        .tes-survey-suggestion-item:last-child {
            border-bottom: none;
        }
        @media print {
            #adminmenumain, #wpadminbar, #wpfooter, .tes-filter-card, .tes-no-print, #tes-student-survey-modal {
                display: none !important;
            }
            #wpcontent, #wpbody-content {
                margin: 0 !important;
                padding: 0 !important;
            }
            body.wp-admin {
                background: #fff !important;
            }
            .tes-results-dashboard-wrap {
                margin: 0 !important;
                max-width: 100% !important;
            }
            .tes-question-container {
                page-break-inside: avoid !important;
                border: 1px solid #ddd !important;
            }
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
                            var html = '';
                            $.each(response.data, function(i, item) {
                                var escapedTitle = $('<div>').text(item.title).html();
                                var teacherInfo = item.teacher_name ? ' <span style="color:#64748b; font-size:0.9em;">(' + $('<div>').text(item.teacher_name).html() + ')</span>' : '';
                                html += '<div class="tes-survey-suggestion-item" data-id="' + item.id + '">' + escapedTitle + teacherInfo + '</div>';
                            });
                            suggestionsBox.html(html).show();
                        } else {
                            suggestionsBox.html('<div class="tes-survey-suggestion-item" style="cursor: default; color: #94a3b8;">No surveys found</div>').show();
                        }
                    }
                });
            }, 300);
        });

        $(document).on('click', '.tes-survey-suggestion-item', function() {
            var surveyId = $(this).data('id');
            if (!surveyId) return;
            window.location.href = '<?php echo admin_url('admin.php?page=tes-dashboard'); ?>&survey_id=' + surveyId;
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('#tes_survey_search_input').length && !$(e.target).closest('#tes_survey_search_suggestions').length) {
                suggestionsBox.hide();
            }
        });

        // Dynamic Teacher -> Survey Filter (Without Clearing Selected Values on Load)
        var $teacherSelect = $('#tes_dashboard_teacher_filter');
        var $surveySelect = $('#tes_dashboard_survey_select');
        var $surveyOptions = $surveySelect.find('option');

        function filterSurveys() {
            var teacherId = $teacherSelect.val();
            
            if (!teacherId) {
                // Show all surveys if no teacher chosen
                $surveyOptions.show();
                $surveySelect.prop('disabled', false);
            } else {
                $surveySelect.prop('disabled', false);
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
                if (currentVal && $surveySelect.find('option[value="' + currentVal + '"]').is(':hidden')) {
                    $surveySelect.val('');
                }
            }
        }

        $teacherSelect.on('change', filterSurveys);
        filterSurveys(); // Run on load to align with pre-selected teacher if any

        // --- Student Detailed Survey Modal Logic ---
        var studentSubmissions = {};
        try {
            studentSubmissions = JSON.parse($('#tes-student-submissions-data').html() || '{}');
        } catch(e) {
            console.error("Error parsing student submissions:", e);
        }

        $(document).on('click', '.tes-student-click-name', function(e) {
            e.preventDefault();
            var submissionKey = $(this).data('target');
            var data = studentSubmissions[submissionKey];
            if (!data) return;

            // Header title
            $('#tes-modal-student-name').text(data.student_name + (data.roll ? ' (Roll: ' + data.roll + ')' : ''));

            var html = '';

            // Top Stat Cards
            html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin-bottom: 20px;">';

            // Card 1: Score
            var scoreBadgeBg = data.avg >= 3.5 ? '#ecfdf5' : (data.avg >= 2.5 ? '#fef3c7' : '#fee2e2');
            var scoreBadgeColor = data.avg >= 3.5 ? '#047857' : (data.avg >= 2.5 ? '#b45309' : '#dc2626');
            html += '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; text-align: center;">';
            html += '<div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; letter-spacing: 0.5px;">Student Average Rating</div>';
            html += '<div style="font-size: 24px; font-weight: 800; color: #0284c7; margin: 4px 0;">' + Number(data.avg).toFixed(2) + ' <span style="font-size: 14px; font-weight: normal; color: #64748b;">/ 5</span></div>';
            html += '<span style="background: ' + scoreBadgeBg + '; color: ' + scoreBadgeColor + '; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px;">★ Average Score</span>';
            html += '</div>';

            // Card 2: Student Details
            html += '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px;">';
            html += '<div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; letter-spacing: 0.5px;">Student Details</div>';
            html += '<div style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 3px 0;">' + $('<div>').text(data.student_name).html() + '</div>';
            html += '<div style="font-size: 12px; color: #475569; line-height: 1.5;">';
            if (data.roll) html += '<div><strong>Roll:</strong> ' + $('<div>').text(data.roll).html() + '</div>';
            if (data.class) html += '<div><strong>Class:</strong> ' + $('<div>').text(data.class).html() + (data.phase ? ' | <strong>Phase:</strong> ' + $('<div>').text(data.phase).html() : '') + (data.group ? ' | <strong>Group:</strong> ' + $('<div>').text(data.group).html() : '') + '</div>';
            if (data.batch) html += '<div><strong>Batch:</strong> ' + $('<div>').text(data.batch).html() + '</div>';
            html += '</div></div>';

            // Card 3: Submission Date
            html += '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; text-align: center;">';
            html += '<div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; letter-spacing: 0.5px;">Submission Date</div>';
            html += '<div style="font-size: 14px; font-weight: 700; color: #0f172a; margin: 6px 0;">' + $('<div>').text(data.formatted_date).html() + '</div>';
            html += '<div style="font-size: 12px; color: #64748b;">Completed Evaluation</div>';
            html += '</div>';

            html += '</div>'; // End top cards

            // Question Responses Table
            html += '<div style="margin-top: 15px;">';
            html += '<h4 style="margin: 0 0 10px 0; font-size: 15px; font-weight: 700; color: #0f172a;">Detailed Survey Responses</h4>';
            html += '<table style="width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">';
            html += '<thead><tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">';
            html += '<th style="padding: 10px 12px; text-align: left; width: 35px; color: #475569;">#</th>';
            html += '<th style="padding: 10px 12px; text-align: left; color: #475569;">Question</th>';
            html += '<th style="padding: 10px 12px; text-align: left; color: #475569; width: 110px;">Category</th>';
            html += '<th style="padding: 10px 12px; text-align: left; color: #475569; width: 170px;">Selected Answer</th>';
            html += '<th style="padding: 10px 12px; text-align: center; color: #475569; width: 70px;">Rating</th>';
            html += '</tr></thead><tbody>';

            if (data.details && data.details.length > 0) {
                $.each(data.details, function(idx, item) {
                    html += '<tr style="border-bottom: 1px solid #f1f5f9;">';
                    html += '<td style="padding: 10px 12px; color: #64748b; font-weight: 600;">' + (idx + 1) + '</td>';
                    html += '<td style="padding: 10px 12px; color: #0f172a;">';
                    html += '<div style="font-weight: 600;">' + $('<div>').text(item.title).html() + '</div>';
                    if (item.text && item.text !== item.title) {
                        html += '<div style="font-size: 12px; color: #64748b; font-style: italic; margin-top: 2px;">' + $('<div>').text(item.text).html() + '</div>';
                    }
                    html += '</td>';
                    html += '<td style="padding: 10px 12px;">';
                    html += '<span style="background: #f1f5f9; color: #475569; font-size: 11px; padding: 2px 7px; border-radius: 4px; font-weight: 500; border: 1px solid #e2e8f0;">' + $('<div>').text(item.type).html() + '</span>';
                    html += '</td>';
                    html += '<td style="padding: 10px 12px;">';
                    if (item.answer) {
                        html += '<span style="background: #e0f2fe; color: #0369a1; font-weight: 600; font-size: 12px; padding: 3px 9px; border-radius: 6px; display: inline-block;">' + $('<div>').text(item.answer).html() + '</span>';
                    } else {
                        html += '<span style="color: #94a3b8; font-style: italic;">No answer</span>';
                    }
                    html += '</td>';
                    html += '<td style="padding: 10px 12px; text-align: center; font-weight: 700; color: #0284c7;">';
                    html += item.score ? item.score + ' / 5' : '-';
                    html += '</td>';
                    html += '</tr>';
                });
            }

            html += '</tbody></table></div>';

            // Student Comment / Remark
            if (data.comment) {
                html += '<div style="margin-top: 18px; padding: 14px 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; border-left: 4px solid #0284c7;">';
                html += '<strong style="display: block; font-size: 13px; color: #0f172a; margin-bottom: 4px;">Student Feedback / Remark:</strong>';
                html += '<div style="font-size: 13.5px; color: #334155; line-height: 1.5;">' + $('<div>').text(data.comment).html().replace(/\n/g, '<br>') + '</div>';
                html += '</div>';
            }

            $('#tes-modal-body').html(html);
            $('#tes-student-survey-modal').css('display', 'flex').hide().fadeIn(150);
            $('body').css('overflow', 'hidden');
        });

        // Close Modal
        function closeStudentModal() {
            $('#tes-student-survey-modal').fadeOut(150, function() {
                $('body').css('overflow', '');
            });
        }

        $(document).on('click', '.tes-close-modal-btn', closeStudentModal);

        $(document).on('click', '#tes-student-survey-modal', function(e) {
            if ($(e.target).is('#tes-student-survey-modal')) {
                closeStudentModal();
            }
        });

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#tes-student-survey-modal').is(':visible')) {
                closeStudentModal();
            }
        });

        // Print Student Survey Modal
        $(document).on('click', '#tes-print-student-modal', function() {
            var printContent = document.getElementById('tes-modal-body').innerHTML;
            var studentName = $('#tes-modal-student-name').text();
            var surveyTitle = $('#tes-modal-survey-title').text();
            var printWin = window.open('', '', 'width=850,height=650');
            printWin.document.write('<html><head><title>' + studentName + ' - Survey Evaluation</title>');
            printWin.document.write('<style>body{font-family:sans-serif;padding:25px;color:#1e293b;}table{width:100%;border-collapse:collapse;margin-top:10px;}th,td{border:1px solid #e2e8f0;padding:8px 10px;text-align:left;}th{background:#f8fafc;}@media print{button{display:none;}}</style>');
            printWin.document.write('</head><body>');
            printWin.document.write('<h2 style="margin:0 0 4px 0;color:#0f172a;">' + studentName + '</h2>');
            printWin.document.write('<p style="color:#64748b;margin:0 0 20px 0;font-size:14px;">Survey: <strong>' + surveyTitle + '</strong></p>');
            printWin.document.write(printContent);
            printWin.document.write('</body></html>');
            printWin.document.close();
            printWin.focus();
            setTimeout(function() {
                printWin.print();
                printWin.close();
            }, 300);
        });
    });
    </script>
<?php
}
