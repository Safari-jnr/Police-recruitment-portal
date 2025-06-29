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
$template_id = null;
$template_data = [
    'template_name' => '',
    'subject' => '',
    'body' => ''
];

// Get template ID from URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $template_id = filter_var(trim($_GET['id']), FILTER_SANITIZE_NUMBER_INT);
    if ($template_id === false || $template_id <= 0) {
        $error_message = "Invalid template ID provided.";
    }
} else {
    $error_message = "Template ID not provided for editing.";
}

// Fetch existing template data if ID is valid
if ($template_id && empty($error_message)) {
    try {
        $sql = "SELECT * FROM email_templates WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":id", $template_id, PDO::PARAM_INT);
        $stmt->execute();
        $template_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template_data) {
            $error_message = "Email template not found.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error fetching template: " . $e->getMessage();
        error_log("PDO Exception fetching email template for edit: " . $e->getMessage());
    }
}

// Handle Update Request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_template']) && $template_id) {
    $template_name = trim($_POST['template_name']);
    $subject = trim($_POST['subject']);
    $body = trim($_POST['body']);

    if (empty($template_name) || empty($subject) || empty($body)) {
        $error_message = "All fields are required to update the template.";
    } else {
        try {
            $sql = "UPDATE email_templates SET template_name = :template_name, subject = :subject, body = :body, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(":template_name", $template_name, PDO::PARAM_STR);
            $stmt->bindParam(":subject", $subject, PDO::PARAM_STR);
            $stmt->bindParam(":body", $body, PDO::PARAM_STR);
            $stmt->bindParam(":id", $template_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $success_message = "Email template '" . htmlspecialchars($template_name) . "' updated successfully!";
                // Refresh template data after update
                $template_data['template_name'] = $template_name;
                $template_data['subject'] = $subject;
                $template_data['body'] = $body;
            } else {
                $error_message = "Error updating template.";
                error_log("Error updating email template: " . implode(":", $stmt->errorInfo()));
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // SQLSTATE for Integrity constraint violation (e.g., duplicate unique key)
                $error_message = "Template name '" . htmlspecialchars($template_name) . "' already exists. Please choose a different name.";
            } else {
                $error_message = "Database error: " . $e->getMessage();
                error_log("PDO Exception updating email template: " . $e->getMessage());
            }
        }
    }
}

$pdo = null; // Close PDO connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Email Template - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        <h2 class="text-3xl font-semibold text-gray-800 mb-6">Edit Email Template</h2>

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

        <?php if ($template_id && $template_data): ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $template_id; ?>" method="post">
                <div class="mb-4">
                    <label for="template_name" class="block text-gray-700 text-sm font-bold mb-2">Template Name:</label>
                    <input type="text" id="template_name" name="template_name" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           value="<?php echo htmlspecialchars($template_data['template_name']); ?>">
                </div>
                <div class="mb-4">
                    <label for="subject" class="block text-gray-700 text-sm font-bold mb-2">Subject:</label>
                    <input type="text" id="subject" name="subject" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           value="<?php echo htmlspecialchars($template_data['subject']); ?>">
                    <p class="text-xs text-gray-500 mt-1">Use placeholders like `{{applicant_name}}`, `{{applicant_email}}`, `{{test_date}}`, `{{interview_location}}` etc.</p>
                </div>
                <div class="mb-6">
                    <label for="body" class="block text-gray-700 text-sm font-bold mb-2">Body (HTML allowed):</label>
                    <textarea id="body" name="body" rows="10" required
                              class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($template_data['body']); ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">Use placeholders like `{{applicant_name}}`, `{{applicant_email}}`, `{{test_date}}`, `{{interview_location}}` etc.</p>
                </div>
                <button type="submit" name="update_template"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Update Template
                </button>
                <a href="email_templates.php" class="ml-4 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded transition duration-300">Cancel</a>
            </form>
        <?php elseif (!$template_id && empty($error_message)): ?>
            <p class="text-gray-600">Please provide a valid template ID to edit.</p>
            <a href="email_templates.php" class="text-blue-600 hover:underline mt-4 block">Go back to Email Templates List</a>
        <?php endif; ?>
    </main>

    <footer class="bg-gray-800 text-white p-4 mt-12 text-center">
        <div class="container mx-auto">
            &copy; <?php echo date("Y"); ?> Police Recruitment Portal. All rights reserved.
        </div>
    </footer>

</body>
</html>