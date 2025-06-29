<?php
session_start();
require_once '../includes/config.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'admin') {
    header("location: ../login.php");
    exit;
}

$error_message = "";
$success_message = "";
$templates = [];

// Handle Adding a New Template
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_template'])) {
    $template_name = trim($_POST['template_name']);
    $subject = trim($_POST['subject']);
    $body = trim($_POST['body']);

    if (empty($template_name) || empty($subject) || empty($body)) {
        $error_message = "All fields are required to add a new template.";
    } else {
        try {
            $sql = "INSERT INTO email_templates (template_name, subject, body) VALUES (:template_name, :subject, :body)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(":template_name", $template_name, PDO::PARAM_STR);
            $stmt->bindParam(":subject", $subject, PDO::PARAM_STR);
            $stmt->bindParam(":body", $body, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $success_message = "Email template '" . htmlspecialchars($template_name) . "' added successfully!";
                // Clear form fields after successful submission
                $template_name = $subject = $body = '';
            } else {
                $error_message = "Error adding template. It might be a duplicate name.";
                error_log("Error adding email template: " . implode(":", $stmt->errorInfo()));
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // SQLSTATE for Integrity constraint violation (e.g., duplicate unique key)
                $error_message = "Template name '" . htmlspecialchars($template_name) . "' already exists. Please choose a different name.";
            } else {
                $error_message = "Database error: " . $e->getMessage();
                error_log("PDO Exception adding email template: " . $e->getMessage());
            }
        }
    }
}

// Handle Deleting a Template
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $template_id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
    if ($template_id) {
        try {
            $sql = "DELETE FROM email_templates WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(":id", $template_id, PDO::PARAM_INT);
            if ($stmt->execute()) {
                $success_message = "Email template deleted successfully.";
            } else {
                $error_message = "Error deleting template.";
                error_log("Error deleting email template: " . implode(":", $stmt->errorInfo()));
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
            error_log("PDO Exception deleting email template: " . $e->getMessage());
        }
    } else {
        $error_message = "Invalid template ID for deletion.";
    }
}

// Fetch all templates for display
try {
    $stmt = $pdo->query("SELECT * FROM email_templates ORDER BY template_name ASC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message .= " Could not fetch templates: " . $e->getMessage();
    error_log("PDO Exception fetching email templates: " . $e->getMessage());
}

$pdo = null; // Close PDO connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Email Templates - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Optional: Add custom styles here if needed */
        .template-body {
            max-height: 150px; /* Limit height */
            overflow-y: auto; /* Add scroll if content overflows */
            background-color: #f9fafb; /* bg-gray-50 */
            padding: 0.5rem;
            border-radius: 0.25rem;
            border: 1px solid #e5e7eb; /* border-gray-200 */
        }
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <header class="bg-indigo-700 p-4 text-white shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Admin Panel</h1>
            <nav>
                <a href="dashboard.php" class="px-3 py-2 rounded hover:bg-indigo-800 transition duration-300">Dashboard</a>
                <a href="applicants.php" class="px-3 py-2 rounded hover:bg-indigo-800 transition duration-300">Applicants</a>
                <a href="email_templates.php" class="px-3 py-2 rounded hover:bg-indigo-800 transition duration-300">Email Templates</a>
                <a href="../logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded ml-4 transition duration-300">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto mt-8 p-6 bg-white rounded-lg shadow-lg">
        <h2 class="text-3xl font-semibold text-gray-800 mb-6">Manage Email Templates</h2>

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

        <div class="bg-blue-50 p-6 rounded-lg shadow-md mb-8 border-l-4 border-blue-600">
            <h3 class="text-xl font-semibold text-blue-800 mb-4">Add New Email Template</h3>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-4">
                    <label for="template_name" class="block text-gray-700 text-sm font-bold mb-2">Template Name:</label>
                    <input type="text" id="template_name" name="template_name" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           value="<?php echo htmlspecialchars($template_name ?? ''); ?>">
                </div>
                <div class="mb-4">
                    <label for="subject" class="block text-gray-700 text-sm font-bold mb-2">Subject:</label>
                    <input type="text" id="subject" name="subject" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           value="<?php echo htmlspecialchars($subject ?? ''); ?>">
                    <p class="text-xs text-gray-500 mt-1">Use placeholders like `{{applicant_name}}`, `{{applicant_email}}`, `{{test_date}}`, `{{interview_location}}` etc.</p>
                </div>
                <div class="mb-6">
                    <label for="body" class="block text-gray-700 text-sm font-bold mb-2">Body (HTML allowed):</label>
                    <textarea id="body" name="body" rows="10" required
                              class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($body ?? ''); ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">Use placeholders like `{{applicant_name}}`, `{{applicant_email}}`, `{{test_date}}`, `{{interview_location}}` etc.</p>
                </div>
                <button type="submit" name="add_template"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Add Template
                </button>
            </form>
        </div>

        <h3 class="text-2xl font-semibold text-gray-800 mb-4">Existing Email Templates</h3>
        <?php if (empty($templates)): ?>
            <p class="text-gray-600">No email templates found. Add one using the form above.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-sm">
                    <thead>
                        <tr class="bg-gray-100 border-b border-gray-200 text-gray-700 text-left text-sm font-medium uppercase tracking-wider">
                            <th class="py-3 px-4">Template Name</th>
                            <th class="py-3 px-4">Subject</th>
                            <th class="py-3 px-4">Body (Preview)</th>
                            <th class="py-3 px-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $template): ?>
                            <tr class="border-b border-gray-200 last:border-b-0 hover:bg-gray-50">
                                <td class="py-3 px-4"><?php echo htmlspecialchars($template['template_name']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($template['subject']); ?></td>
                                <td class="py-3 px-4">
                                    <div class="template-body">
                                        <?php echo nl2br(htmlspecialchars(strip_tags($template['body']))); // Display plain text preview ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <a href="edit_email_template.php?id=<?php echo $template['id']; ?>"
                                       class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded text-xs transition duration-300 mr-2">Edit</a>
                                    <a href="email_templates.php?action=delete&id=<?php echo $template['id']; ?>"
                                       onclick="return confirm('Are you sure you want to delete this template? This cannot be undone.');"
                                       class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded text-xs transition duration-300">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <footer class="bg-gray-800 text-white p-4 mt-12 text-center">
        <div class="container mx-auto">
            &copy; <?php echo date("Y"); ?> Police Recruitment Portal. All rights reserved.
        </div>
    </footer>

</body>
</html>