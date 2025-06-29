<?php
// Start the session
session_start();

// Include database connection file and email sending function
require_once '../includes/config.php';
require_once '../includes/send_email.php'; // For sending approval email

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login.php");
    exit;
}

// Check if the logged-in user is an admin, otherwise redirect to applicant dashboard
if (isset($_SESSION["role"]) && $_SESSION["role"] !== 'admin') {
    header("location: ../dashboard.php"); // Redirect non-admins
    exit;
}

$applicant_id = null; // This will be the ID from the 'applicants' table
$applicant_data = [];
$education_data = [];
$documents_data = [];
$error_message = "";
$success_message = "";

// --- Get Applicant ID from URL ---
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $applicant_id = filter_var(trim($_GET['id']), FILTER_SANITIZE_NUMBER_INT);
    if ($applicant_id === false || $applicant_id <= 0) {
        $error_message = "Invalid applicant ID provided in URL.";
    }
} else {
    $error_message = "Applicant ID not provided in URL.";
}

// Ensure $pdo is available for use throughout the script
$pdo_connection_active = isset($pdo) && $pdo instanceof PDO;
if (!$pdo_connection_active) {
    $error_message .= " Database connection not established. Check ../includes/config.php.";
    error_log("Database connection failed in admin/view_applicant.php");
}


// --- Fetch all Email Templates ---
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt_templates = $pdo->query("SELECT id, template_name, subject, body FROM email_templates ORDER BY template_name ASC");
        $templates = $stmt_templates->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error_message .= " Database connection not available to fetch email templates.";
    }
} catch (PDOException $e) {
    $error_message .= " Could not fetch email templates: " . $e->getMessage();
    error_log("PDO Exception fetching email templates in view_applicant.php: " . $e->getMessage());
}

// --- Handle Sending Templated Email ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_templated_email'])) {
    if ($applicant_id) {
        $selected_template_id = filter_var($_POST['email_template'], FILTER_SANITIZE_NUMBER_INT);

        if (empty($selected_template_id)) {
            $error_message = "Please select an email template.";
        } else {
            // Re-fetch applicant data in case it was updated by status change above
            try {
                $sql_applicant_email = "SELECT a.first_name, a.last_name, a.phone_number, a.address, a.city, a.state, a.dob, a.other_names, a.application_status, u.email, a.id
                                        FROM applicants a JOIN users u ON a.user_id = u.id
                                        WHERE a.id = :applicant_id";
                $stmt_applicant_email = $pdo->prepare($sql_applicant_email);
                $stmt_applicant_email->bindParam(":applicant_id", $applicant_id, PDO::PARAM_INT);
                $stmt_applicant_email->execute();
                $applicant_email_info = $stmt_applicant_email->fetch(PDO::FETCH_ASSOC);

                if ($applicant_email_info) {
                    // Fetch the selected template
                    $sql_template = "SELECT subject, body FROM email_templates WHERE id = :id";
                    $stmt_template = $pdo->prepare($sql_template);
                    $stmt_template->bindParam(":id", $selected_template_id, PDO::PARAM_INT);
                    $stmt_template->execute();
                    $template_content = $stmt_template->fetch(PDO::FETCH_ASSOC);

                    if ($template_content) {
                        $populated_subject = populateEmailPlaceholders($template_content['subject'], $applicant_email_info);
                        $populated_body = populateEmailPlaceholders($template_content['body'], $applicant_email_info);

                        // Send the email
                        if (sendEmail($applicant_email_info['email'], $populated_subject, $populated_body)) {
                            $success_message .= " Email sent successfully to " . htmlspecialchars($applicant_email_info['email']) . " using template.";
                        } else {
                            $error_message .= " Failed to send email to " . htmlspecialchars($applicant_email_info['email']) . ". (SMTP error?)";
                            error_log("Failed to send templated email to " . $applicant_email_info['email'] . ". Subject: " . $populated_subject);
                        }
                    } else {
                        $error_message = "Selected email template not found.";
                    }
                } else {
                    $error_message = "Applicant details not found for sending email.";
                }
            } catch (PDOException $e) {
                $error_message = "Database error sending templated email: " . $e->getMessage();
                error_log("PDO Exception sending templated email: " . $e->getMessage());
            }
        }
    } else {
        $error_message = "Cannot send email: Applicant ID is missing or invalid.";
    }
}


// --- Process Status Update Form Submission ---
if ($pdo_connection_active && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    if ($applicant_id) {
        $new_status = trim($_POST['application_status']);

        // Corrected: Validate new status against allowed values (MUST MATCH DB ENUM EXACTLY)
        $allowed_statuses = [
            'pending',
            'under_review',
            'shortlisted', // Added from your DB ENUM
            'invited_for_test',
            'invited_for_interview',
            'medical_check',
            'recommended_for_training',
            'rejected',
            'Profile Incomplete',
            'Not Started'
            // Removed 'submitted' and 'accepted' as they are not in your DB ENUM
        ];
        if (!in_array($new_status, $allowed_statuses)) {
            $error_message = "Invalid application status selected for update. Please choose from allowed options.";
        } else {
            try {
                // Fetch current status and applicant email/name BEFORE updating
                $sql_get_current = "SELECT a.application_status, a.first_name, a.last_name, u.email
                                    FROM applicants a JOIN users u ON a.user_id = u.id
                                    WHERE a.id = :applicant_id";
                $stmt_get_current = $pdo->prepare($sql_get_current);
                $stmt_get_current->bindParam(":applicant_id", $applicant_id, PDO::PARAM_INT);
                $stmt_get_current->execute();
                $current_applicant_info = $stmt_get_current->fetch(PDO::FETCH_ASSOC);
                unset($stmt_get_current);

                if ($current_applicant_info) {
                    $current_status = $current_applicant_info['application_status'];
                    $applicant_email = $current_applicant_info['email'];
                    $applicant_name = htmlspecialchars($current_applicant_info['first_name'] . ' ' . $current_applicant_info['last_name']);

                    // Update status in the database
                    $sql_update_status = "UPDATE applicants SET application_status = :new_status WHERE id = :applicant_id";
                    $stmt_update_status = $pdo->prepare($sql_update_status);
                    $stmt_update_status->bindParam(":new_status", $new_status, PDO::PARAM_STR);
                    $stmt_update_status->bindParam(":applicant_id", $applicant_id, PDO::PARAM_INT);

                    if ($stmt_update_status->execute()) {
                        $success_message = "Application status updated to: " . ucwords(str_replace('_', ' ', $new_status));

                        // --- Send Email Logic ---
                        // Note: If you want to send emails for 'accepted' status,
                        // you MUST add 'accepted' to your database ENUM first.
                        // I'm assuming for now 'recommended_for_training' might be a final "accepted" state,
                        // or you need to add 'accepted' to your DB ENUM.
                        if ($new_status === 'recommended_for_training' && $current_status !== 'recommended_for_training') {
                            $subject = "Congratulations! Your Police Recruitment Application Status Update";
                            $body = "
                                <p>Dear " . $applicant_name . ",</p>
                                <p>We are pleased to inform you that your application for the Police Recruitment exercise has been **Recommended for Training**!</p>
                                <p>Further instructions regarding your next steps will be communicated shortly.</p>
                                <p>Sincerely,</p>
                                <p>The Police Recruitment Portal Team</p>
                            ";
                            if (sendEmail($applicant_email, $subject, $body)) {
                                $success_message .= " An email has been sent to the applicant regarding training recommendation.";
                            } else {
                                $error_message .= " Failed to send training recommendation email to applicant. (SMTP error?)";
                                error_log("Failed to send email to " . $applicant_email . " for training recommendation.");
                            }
                        } else if ($new_status === 'rejected' && $current_status !== 'rejected') {
                            $subject = "Update on Your Police Recruitment Application";
                            $body = "
                                <p>Dear " . $applicant_name . ",</p>
                                <p>We regret to inform you that your application for the Police Recruitment exercise has been **REJECTED** at this time.</p>
                                <p>We understand this news may be disappointing. We receive a high volume of applications, and not all candidates can be selected.</p>
                                <p>We wish you the best in your future endeavors.</p>
                                <p>Sincerely,</p>
                                <p>The Police Recruitment Portal Team</p>
                            ";
                            if (sendEmail($applicant_email, $subject, $body)) {
                                $success_message .= " A rejection email has been sent to the applicant.";
                            } else {
                                $error_message .= " Failed to send rejection email to applicant. (SMTP error?)";
                                error_log("Failed to send email to " . $applicant_email . " for rejection.");
                            }
                        }
                        // Important: Refresh the $applicant_data after a successful update
                        $applicant_data['application_status'] = $new_status;

                    } else {
                        $error_message = "Error updating application status: " . implode(":", $stmt_update_status->errorInfo());
                        error_log("SQL Error on status update: " . implode(":", $stmt_update_status->errorInfo()));
                    }
                    unset($stmt_update_status);

                } else {
                    $error_message = "Applicant not found for status update (ID: " . $applicant_id . ").";
                }

            } catch (PDOException $e) {
                error_log("PDO Exception updating applicant status: " . $e->getMessage());
                $error_message = "Database error during status update: " . $e->getMessage();
            }
        }
    } else {
        $error_message = "Cannot update status: Applicant ID is missing or invalid from URL.";
    }
}

// --- Fetch Applicant Details (after potential status update) ---
if (empty($error_message) && $applicant_id) {
    try {
        // Fetch applicant's personal profile (from applicants table)
        // Use a.id for the primary key lookup
        $sql_applicant = "SELECT a.*, u.email FROM applicants a JOIN users u ON a.user_id = u.id WHERE a.id = :applicant_id";
        $stmt_applicant = $pdo->prepare($sql_applicant);
        $stmt_applicant->bindParam(":applicant_id", $applicant_id, PDO::PARAM_INT);
        $stmt_applicant->execute();
        $applicant_data = $stmt_applicant->fetch(PDO::FETCH_ASSOC);
        unset($stmt_applicant);

        if (!$applicant_data) {
            $error_message = "Applicant with ID " . htmlspecialchars($applicant_id) . " not found.";
        } else {
            // Fetch education details
            $sql_education = "SELECT * FROM educational_backgrounds WHERE applicant_id = :applicant_id ORDER BY end_year DESC";
            $stmt_education = $pdo->prepare($sql_education);
            $stmt_education->bindParam(":applicant_id", $applicant_id, PDO::PARAM_INT);
            $stmt_education->execute();
            $education_data = $stmt_education->fetchAll(PDO::FETCH_ASSOC);
            unset($stmt_education);

            // Fetch uploaded documents
            $sql_documents = "SELECT * FROM documents WHERE applicant_id = :applicant_id";
            $stmt_documents = $pdo->prepare($sql_documents);
            $stmt_documents->bindParam(":applicant_id", $applicant_id, PDO::PARAM_INT);
            $stmt_documents->execute();
            $documents_data = $stmt_documents->fetchAll(PDO::FETCH_ASSOC);
            unset($stmt_documents);
        }

    } catch (PDOException $e) {
        error_log("PDO Exception fetching applicant details: " . $e->getMessage());
        $error_message = "Database error fetching applicant details: " . $e->getMessage();
    }
}
// Unset PDO connection for security (optional, but good practice if no more DB ops)
$pdo = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Details - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .status-tag {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px; /* Pill shape */
            font-weight: 600; /* semibold */
            font-size: 0.75rem; /* text-xs */
            line-height: 1;
            text-transform: capitalize; /* Make the displayed text nicely capitalized */
        }
        /* Tailwind-like colors for statuses - ensure these match your chosen ENUM values */
        .status-not_started, .status-profile_incomplete { background-color: #fef2f2; color: #b91c1c; } /* red-50 */
        .status-pending { background-color: #fefcbf; color: #8a6401; } /* yellow-100, yellow-800 */
        /* .status-submitted { background-color: #bfdbfe; color: #1e40af; } */ /* blue-100, blue-800 - Removed as 'submitted' is not in DB ENUM */
        .status-under_review { background-color: #e9d5ff; color: #6b21a8; } /* purple-100, purple-800 */
        .status-shortlisted { background-color: #d1fae5; color: #065f46; } /* new color for shortlisted */
        .status-invited_for_test, .status-invited_for_interview { background-color: #e0e7ff; color: #3730a3; } /* indigo-100, indigo-800 */
        .status-medical_check { background-color: #dbeafe; color: #1d4ed8; } /* blue-100, blue-700 */
        .status-recommended_for_training { background-color: #dcfce7; color: #166534; } /* green-100, green-800 */
        /* .status-accepted { background-color: #d1fae5; color: #065f46; } */ /* green-100, green-800 - Removed as 'accepted' is not in DB ENUM */
        .status-rejected { background-color: #fee2e2; color: #991b1b; } /* red-100, red-800 */
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <header class="bg-indigo-700 p-4 text-white shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Admin Panel</h1>
            <nav>
                <a href="dashboard.php" class="px-3 py-2 rounded hover:bg-indigo-800 transition duration-300">Dashboard</a>
                <a href="applicants.php" class="px-3 py-2 rounded hover:bg-indigo-800 transition duration-300">Applicants</a>
                <a href="../logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded ml-4 transition duration-300">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto mt-8 p-6 bg-white rounded-lg shadow-lg">
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"> <?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Success!</strong>
                <span class="block sm:inline"> <?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($applicant_data)): ?>
            <h2 class="text-3xl font-semibold text-gray-800 mb-6">Applicant Details: <?php echo htmlspecialchars($applicant_data['first_name'] . ' ' . ($applicant_data['last_name'] ?? '')); ?></h2>

            <div class="bg-blue-50 p-6 rounded-lg shadow-md mb-8 border-l-4 border-blue-600">
                <h3 class="text-xl font-semibold text-blue-800 mb-4">Update Application Status</h3>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $applicant_id; ?>" method="post" class="flex flex-col md:flex-row items-end md:space-x-4 space-y-4 md:space-y-0">
                    <div>
                        <label for="application_status_display" class="block text-gray-700 text-sm font-bold mb-2">Current Status:</label>
                        <span id="application_status_display" class="status-tag <?php echo 'status-' . str_replace(' ', '_', $applicant_data['application_status'] ?? 'not_started'); ?>">
                            <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($applicant_data['application_status'] ?? 'Not Started'))); ?>
                        </span>
                    </div>
                    <div class="flex-grow w-full md:w-auto">
                        <label for="new_application_status" class="block text-gray-700 text-sm font-bold mb-2">Change Status To:</label>
                        <select id="new_application_status" name="application_status"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <?php
                            // Corrected: Dropdown options must match your DB ENUM values
                            $statuses_for_dropdown = [
                                'Not Started' => 'Not Started',
                                'Profile Incomplete' => 'Profile Incomplete',
                                'pending' => 'Pending',
                                'under_review' => 'Under Review',
                                'shortlisted' => 'Shortlisted', // Added
                                'invited_for_test' => 'Invited for Test',
                                'invited_for_interview' => 'Invited for Interview',
                                'medical_check' => 'Medical Check',
                                'recommended_for_training' => 'Recommended for Training',
                                'rejected' => 'Rejected'
                                // Removed 'submitted' and 'accepted' as they are not in your DB ENUM
                            ];
                            foreach ($statuses_for_dropdown as $value => $label) {
                                $selected = (($applicant_data['application_status'] ?? '') == $value) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($value) . "' " . $selected . ">" . htmlspecialchars($label) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" name="update_status"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300 w-full md:w-auto">
                        Update Status
                    </button>
                </form>
            </div>

            <?php if (!empty($applicant_data) && !empty($templates)): ?>
    <div class="bg-purple-50 p-6 rounded-lg shadow-md mb-8 border-l-4 border-purple-600">
        <h3 class="text-xl font-semibold text-purple-800 mb-4">Send Templated Email to Applicant</h3>
        <form id="sendEmailForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $applicant_id; ?>" method="post">
            <div class="mb-4">
                <label for="email_template" class="block text-gray-700 text-sm font-bold mb-2">Select Template:</label>
                <select id="email_template" name="email_template" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        onchange="previewEmailTemplate()">
                    <option value="">-- Select a Template --</option>
                    <?php foreach ($templates as $template): ?>
                        <option value="<?php echo $template['id']; ?>"
                                data-subject="<?php echo htmlspecialchars($template['subject']); ?>"
                                data-body="<?php echo htmlspecialchars($template['body']); ?>">
                            <?php echo htmlspecialchars($template['template_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-sm text-gray-600 mt-2">Available Placeholders for this Applicant:
                    `{{applicant_name}}` (<?php echo htmlspecialchars($applicant_data['first_name'] . ' ' . $applicant_data['last_name']); ?>),
                    `{{applicant_first_name}}` (<?php echo htmlspecialchars($applicant_data['first_name']); ?>),
                    `{{applicant_last_name}}` (<?php echo htmlspecialchars($applicant_data['last_name']); ?>),
                    `{{applicant_email}}` (<?php echo htmlspecialchars($applicant_data['email']); ?>),
                    `{{applicant_phone}}` (<?php echo htmlspecialchars($applicant_data['phone_number']); ?>),
                    `{{applicant_id}}` (<?php echo htmlspecialchars($applicant_data['id']); ?>),
                    `{{current_status}}` (<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $applicant_data['application_status'] ?? ''))); ?>),
                    `{{date_of_birth}}` (<?php echo htmlspecialchars($applicant_data['dob'] ?? 'N/A'); ?>),
                    `{{middle_name}}` (<?php echo htmlspecialchars($applicant_data['other_names'] ?? 'N/A'); ?>),
                    `{{address}}` (<?php echo htmlspecialchars($applicant_data['address'] ?? 'N/A'); ?>),
                    `{{city}}` (<?php echo htmlspecialchars($applicant_data['city'] ?? 'N/A'); ?>),
                    `{{state}}` (<?php echo htmlspecialchars($applicant_data['state'] ?? 'N/A'); ?>)
                    </p>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Preview Subject:</label>
                <p id="preview_subject" class="bg-gray-100 p-2 rounded border border-gray-200 text-gray-800"></p>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Preview Body:</label>
                <div id="preview_body" class="bg-gray-100 p-2 rounded border border-gray-200 text-gray-800 overflow-y-auto max-h-60"></div>
            </div>

            <button type="submit" name="send_templated_email"
                    class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                Send Email
            </button>
        </form>
    </div>
<?php elseif (empty($applicant_data) && !empty($error_message)): ?>
    <?php else: ?>
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Info!</strong>
        <span class="block sm:inline"> No email templates available. Please create templates in the <a href="email_templates.php" class="underline">Email Templates</a> section.</span>
    </div>
<?php endif; ?>

<script>
    function populatePlaceholdersInPreview(text, applicantData) {
        // This JavaScript function mimics the PHP populateEmailPlaceholders
        // but uses the data available client-side from the current applicant.
        let result = text;
        result = result.replace(/{{applicant_name}}/g, applicantData.first_name + ' ' + applicantData.last_name);
        result = result.replace(/{{applicant_first_name}}/g, applicantData.first_name);
        result = result.replace(/{{applicant_last_name}}/g, applicantData.last_name);
        result = result.replace(/{{applicant_email}}/g, applicantData.email);
        result = result.replace(/{{applicant_phone}}/g, applicantData.phone_number);
        result = result.replace(/{{applicant_id}}/g, applicantData.id);
        result = result.replace(/{{current_status}}/g, applicantData.application_status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase()));
        result = result.replace(/{{date_of_birth}}/g, applicantData.dob || 'N/A');
        result = result.replace(/{{middle_name}}/g, applicantData.other_names || 'N/A');
        result = result.replace(/{{address}}/g, applicantData.address || 'N/A');
        result = result.replace(/{{city}}/g, applicantData.city || 'N/A');
        result = result.replace(/{{state}}/g, applicantData.state || 'N/A');
        // Add more replacements for any other placeholders you define
        // For dynamic ones like test_date, you might need to add inputs for them if they aren't in applicantData
        result = result.replace(/{{test_date}}/g, '{{test_date}}'); // Keep as placeholder for user to understand
        result = result.replace(/{{test_time}}/g, '{{test_time}}');
        result = result.replace(/{{test_location}}/g, '{{test_location}}');
        result = result.replace(/{{interview_date}}/g, '{{interview_date}}');
        result = result.replace(/{{interview_time}}/g, '{{interview_time}}');
        result = result.replace(/{{interview_location}}/g, '{{interview_location}}');
        return result;
    }

    const applicantData = {
        first_name: "<?php echo addslashes($applicant_data['first_name'] ?? ''); ?>",
        last_name: "<?php echo addslashes($applicant_data['last_name'] ?? ''); ?>",
        email: "<?php echo addslashes($applicant_data['email'] ?? ''); ?>",
        phone_number: "<?php echo addslashes($applicant_data['phone_number'] ?? ''); ?>",
        id: "<?php echo addslashes($applicant_data['id'] ?? ''); ?>",
        application_status: "<?php echo addslashes($applicant_data['application_status'] ?? ''); ?>",
        dob: "<?php echo addslashes($applicant_data['dob'] ?? 'N/A'); ?>",
        other_names: "<?php echo addslashes($applicant_data['other_names'] ?? 'N/A'); ?>",
        address: "<?php echo addslashes($applicant_data['address'] ?? 'N/A'); ?>",
        city: "<?php echo addslashes($applicant_data['city'] ?? 'N/A'); ?>",
        state: "<?php echo addslashes($applicant_data['state'] ?? 'N/A'); ?>"
        // Add more fields here that you want available for JS preview
    };

    function previewEmailTemplate() {
        const select = document.getElementById('email_template');
        const selectedOption = select.options[select.selectedIndex];
        const previewSubject = document.getElementById('preview_subject');
        const previewBody = document.getElementById('preview_body');

        if (selectedOption.value) {
            let subject = selectedOption.getAttribute('data-subject');
            let body = selectedOption.getAttribute('data-body');

            // Populate placeholders for preview
            subject = populatePlaceholdersInPreview(subject, applicantData);
            body = populatePlaceholdersInPreview(body, applicantData);

            previewSubject.textContent = subject;
            previewBody.innerHTML = body; // Use innerHTML to render HTML in body
        } else {
            previewSubject.textContent = "";
            previewBody.innerHTML = "";
        }
    }

    // Call on page load to show preview if a template is pre-selected (unlikely on first load)
    // or to clear previews if no template is selected.
    previewEmailTemplate();
</script>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <div class="bg-gray-50 p-6 rounded-lg shadow-md border-l-4 border-gray-400">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Personal Information</h3>
                    <p class="mb-2"><strong class="text-gray-700">Email:</strong> <?php echo htmlspecialchars($applicant_data['email'] ?? 'N/A'); ?></p>
                    <p class="mb-2"><strong class="text-gray-700">First Name:</strong> <?php echo htmlspecialchars($applicant_data['first_name'] ?? 'N/A'); ?></p>
                    <p class="mb-2"><strong class="text-gray-700">Middle Name:</strong> <?php echo htmlspecialchars($applicant_data['other_names'] ?? 'N/A'); ?></p>
                    <p class="mb-2"><strong class="text-gray-700">Last Name:</strong> <?php echo htmlspecialchars($applicant_data['last_name'] ?? 'N/A'); ?></p>
                    <p class="mb-2"><strong class="text-gray-700">Date of Birth:</strong> <?php echo htmlspecialchars($applicant_data['dob'] ?? 'N/A'); ?></p>
                    <p class="mb-2"><strong class="text-gray-700">Gender:</strong> <?php echo htmlspecialchars(ucfirst($applicant_data['gender'] ?? 'N/A')); ?></p>
                    <p class="mb-2"><strong class="text-gray-700">Phone Number:</strong> <?php echo htmlspecialchars($applicant_data['phone_number'] ?? 'N/A'); ?></p>
                    <p class="mb-2"><strong class="text-gray-700">Address:</strong> <?php echo htmlspecialchars($applicant_data['address'] ?? 'N/A'); ?></p>
                    <p class="mb-2"><strong class="text-gray-700">City:</strong> <?php echo htmlspecialchars($applicant_data['city'] ?? 'N/A'); ?></p>
                    <p class="mb-2"><strong class="text-gray-700">State:</strong> <?php echo htmlspecialchars($applicant_data['state'] ?? 'N/A'); ?></p>
                </div>

                <div class="bg-gray-50 p-6 rounded-lg shadow-md border-l-4 border-gray-400">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Education Details</h3>
                <?php if (!empty($education_data)): ?>
                    <?php foreach ($education_data as $edu): ?>
                        <div class="mb-4 pb-4 border-b border-gray-200 last:border-b-0">
                            <p class="mb-1"><strong class="text-gray-700">Institution:</strong> <?php echo htmlspecialchars($edu['institution_name'] ?? 'N/A'); ?></p>
                            <p class="mb-1"><strong class="text-gray-700">Degree/Certificate:</strong> <?php echo htmlspecialchars($edu['degree'] ?? 'N/A'); ?></p>
                            <p class="mb-1"><strong class="text-gray-700">Major/Field:</strong> <?php echo htmlspecialchars($edu['field_of_study'] ?? 'N/A'); ?></p>
                            <p class="mb-1"><strong class="text-gray-700">Start Date:</strong> <?php echo htmlspecialchars($edu['start_year'] ?? 'N/A'); ?></p>
                            <p class="mb-1"><strong class="text-gray-700">End Date:</strong> <?php echo htmlspecialchars($edu['end_year'] ?? 'N/A'); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-600">No education details found.</p>
                <?php endif; ?>
                </div>
            </div>
<!-- 
            <div class="bg-gray-50 p-6 rounded-lg shadow-md mb-8 border-l-4 border-gray-400">
                
            </div> -->

            <div class="bg-gray-50 p-6 rounded-lg shadow-md mb-8 border-l-4 border-gray-400">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Uploaded Documents</h3>
                <?php if (!empty($documents_data)): ?>
                    <ul class="list-disc pl-5 space-y-2">
                        <?php foreach ($documents_data as $doc): ?>
                            <li>
                                <strong class="text-gray-700"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $doc['document_type']))); ?>:</strong>
                                <a href="../uploads/<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="text-blue-600 hover:underline ml-2">View Document</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-gray-600">No documents uploaded.</p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <p class="text-red-600 text-center text-lg">
                <?php echo $error_message; ?>
                <br><a href="applicants.php" class="text-blue-600 hover:underline mt-4 block">Go back to Applicants List</a>
            </p>
        <?php endif; ?>

    </main>

    <footer class="bg-gray-800 text-white p-4 mt-12 text-center">
        <div class="container mx-auto">
            &copy; <?php echo date("Y"); ?> Police Recruitment Portal. All rights reserved.
        </div>
    </footer>

</body>
</html>