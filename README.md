# Teacher Evaluation Survey

**Develop By:** MK.RABBANI(Website manager at Rangpur Group)  
**Version:** 20.10  
**Requires at least:** WordPress 5.0  
**Tested up to:** WordPress 6.4  

## Description

Teacher Evaluation Survey is a comprehensive WordPress plugin designed to facilitate teacher performance evaluations by students. It provides a complete ecosystem for managing teachers, students, and surveys, featuring an intuitive admin dashboard for data management and analytics, and a secure frontend for student submissions.

## Features

### 🎓 Admin Management
*   **Teacher Management:** 
    *   Add, edit, and delete teacher profiles.
    *   **CSV Import/Export:** Bulk import teachers from CSV files and export selected lists.
    *   **Bulk Delete:** Delete multiple teachers at once.
    *   **Search & Filter:** Advanced search with autocomplete for quick access. Filter by Phase and Class.
    *   **Date Tracking:** View creation date for teacher profiles.
    *   Organize by Departments, Phases, and Classes.
    *   **Department Management:** Rename or delete departments, automatically updating associated teachers and students.
*   **Supervisor Management:**
    *   Dedicated management for Advisors/Supervisors.
    *   **Date Tracking:** View creation date for supervisor profiles.
    *   Assign specific roles, designations, credentials, **Phase**, and **Class**.
*   **Student Management:** 
    *   Add and manage student profiles with extended details (Session, Batch, Phase, Roll, Class).
    *   **CSV Import/Export:** Bulk import students from CSV files and export selected lists.
    *   **Auto-Credentials:** Automatically sets `Username` and `Password` (often Roll number) for simplified onboarding.
    *   Searchable student database with bulk delete options. Filter by Phase and Class.
*   **Survey Builder:** 
    *   Create specific surveys linked to individual teachers.
    *   **Bulk Delete:** Delete multiple surveys at once.
    *   **Search:** Search surveys by title, teacher, phase, or class.
    *   **Date Tracking:** View creation date for surveys.
    *   Filter teachers by Phase and Class for easier assignment during creation.
*   **Question Management:** 
    *   **Categorized Questions:** Support for "Explicit Issues" and "Implicit Issues".
    *   **Smart Builder:** Predefined sub-question dropdowns based on question type.
    *   **Bulk Delete:** Delete multiple questions at once.
    *   **Accordion View:** Organized view of questions grouped by survey.
    *   **Search:** Search questions by survey name.
    *   **Date Tracking:** View creation date for questions.
    *   Add customizable multiple-choice questions (Fixed standard options: Never, Once in a while, Sometimes, Most of the times, Almost always).
*   **Results Dashboard:** 
    *   Visual analytics using Chart.js (Pie charts).
    *   Overall average ratings (calculated on a scale of 5).
    *   Detailed question-wise breakdown with response counts and charts.
    *   **Student-wise Analytics:** View submission averages, dates, and names.
    *   **Student Comments:** View and search qualitative feedback from students.
    *   **Search:** Quickly find results by survey title or teacher.

### 📊 Dedicated Dashboards
*   **Teacher Dashboard:**
    *   Secure login for teachers (`[teacher_dashboard]`).
    *   View assigned surveys and real-time performance analytics.
    *   **Search:** Search assigned surveys.
    *   Password visibility toggle.
*   **Advisor Dashboard:**
    *   Secure login for advisors (`[advisor_dashboard]`).
    *   Access to all survey results.
    *   **Enhanced Lists:** View Survey Phase and Class details directly in the dashboard list.
    *   **PDF Export:** Download comprehensive survey results as PDF reports.
        *   **Formatted Layout:** One question per page with charts and tables.
        *   **Custom Header:** Includes college branding and survey details.
        *   **Optimized:** Handles page breaks and layout adjustments automatically.
    *   **Date Display:** View submission dates for student feedback.
    *   Filter results by Teacher and Survey.
    *   Search functionality for quick access.

### 🏫 Student Frontend
*   **Secure Login:** Dedicated login interface for students using their assigned credentials.
*   **Smart Survey Loading:** Automatically filters and displays surveys relevant to the student's assigned **Class and Phase**.
*   **Submission Control:** Prevents duplicate submissions for the same survey.
*   **Responsive Interface:** Modern, AJAX-powered interface with password visibility toggle.
*   **Feedback:** Optional comment section for students.

## Installation

1.  Upload the plugin folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Upon activation, the plugin will automatically create the necessary database tables (`tes_teachers`, `tes_students`, `tes_surveys`, `tes_questions`, `tes_submissions`).

## Usage Guide

### Step 1: Populate Data
1.  Go to **Teacher Survey > Manage Teachers**. Add your departments and teachers. You can also import them via CSV.
2.  Go to **Teacher Survey > Manage Students**. Add students manually or import via CSV.
    *   *Tip:* Ensure Class and Phase match between students and teachers for correct survey assignment.

### Step 2: Create Surveys
1.  Go to **Teacher Survey > Manage Surveys**. Create a survey title and assign it to a specific teacher.
2.  Go to **Teacher Survey > Survey Questions**. Select the survey you just created and add questions (Explicit or Implicit).

### Step 3: Student Access
1.  Create a new WordPress page (e.g., "Feedback Portal").
2.  Add the shortcode `[teacher_survey]` to the page content.
3.  Publish the page. Students can now log in and submit evaluations.

### Step 4: View Reports
1.  Navigate to the main **Teacher Survey** menu item in Admin.
2.  Select a survey from the dropdown to view real-time statistics and charts.
3.  Alternatively, use the **Advisor Dashboard** on the frontend to download PDF reports.

## Shortcodes

*   `[teacher_survey]` - Renders the student login form and survey interface.
*   `[teacher_dashboard]` - Renders the teacher login and dashboard.
*   `[advisor_dashboard]` - Renders the advisor login and dashboard.

## Technical Details
*   **Database:** Uses custom tables for high performance.
*   **Frontend:** Powered by jQuery and AJAX for non-blocking interactions.
*   **Visualization:** Integrates Chart.js for data visualization.
*   **PDF Generation:** Uses html2pdf.js for client-side PDF creation.