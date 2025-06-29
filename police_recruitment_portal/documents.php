<?php
// Start the session
session_start();

// Include database connection file
require_once 'includes/config.php';

// Include the email sending function
require_once 'includes/send_email.php'; // ADDED: Include the email function

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Check if the logged-in user is an applicant
if (isset($_SESSION["role"]) && $_SESSION["role"] !== 'applicant') {
    header("location: admin/dashboard.php"); // Redirect admins
    exit;
}

$user_id = $_SESSION['id'];
$applicant_id = null; // We need the actual applicant_id from the applicants table
$applicant_email = $_SESSION['email']; // Get applicant's email from session
$first_name = ''; // Initialize for email personalization
$last_name = '';  // Initialize for email personalization

$document_type = $description = "";
$document_type_err = $file_upload_err = "";
$success_message = $error_message = "";

// --- First, get the applicant_id and name for the current user ---
$sql_get_applicant_info = "SELECT id, first_name, last_name FROM applicants WHERE user_id = :user_id";
if ($stmt_get_applicant_info = $pdo->prepare($sql_get_applicant_info)) {
    $stmt_get_applicant_info->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_get_applicant_info->execute()) {
        if ($stmt_get_applicant_info->rowCount() == 1) {
            $applicant_row = $stmt_get_applicant_info->fetch(PDO::FETCH_ASSOC);
            $applicant_id = $applicant_row['id'];
            $first_name = $applicant_row['first_name'];
            $last_name = $applicant_row['last_name'];
        } else {
            // Applicant profile does not exist, redirect them to create it first
            $_SESSION['info_message'] = "Please complete your personal profile before uploading documents.";
            header("location: profile.php");
            exit();
        }
    } else {
        $error_message = "Error fetching applicant ID.";
    }
    unset($stmt_get_applicant_info);
}
// --- End of getting applicant_id and name ---

// NEW BLOCK: Processing request to mark application as submitted and send email
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['finish_application'])) {
    if ($applicant_id) {
        // Update application status to 'submitted'
        $sql_update_status = "UPDATE applicants SET application_status = 'submitted' WHERE id = :applicant_id";
        if ($stmt_update_status = $pdo->prepare($sql_update_status)) {
            $stmt_update_status->bindParam(":applicant_id", $applicant_id, PDO::PARAM_INT);
            if ($stmt_update_status->execute()) {
                // Application status updated
                $current_success_message = "Your application has been successfully submitted!";

                // --- Send Email to Applicant ---
                $applicant_full_name = (!empty($first_name) && !empty($last_name)) ? htmlspecialchars($first_name . ' ' . $last_name) : 'Applicant';

                $subject = "Your Police Recruitment Application Submitted Successfully";
                $body = "
                    <p>Dear " . $applicant_full_name . ",</p>
                    <p>This is to confirm that your application for the Police Recruitment exercise has been successfully submitted.</p>
                    <p>Your application is now under review by the administration. You will be notified via email of any updates regarding your application status.</p>
                    <p>Thank you for your interest in joining the police force.</p>
                    <p>Sincerely,</p>
                    <p>The Police Recruitment Portal Team</p>
                ";

                if (sendEmail($applicant_email, $subject, $body)) {
                    $current_success_message .= " A confirmation email has been sent to your registered email address.";
                } else {
                    $error_message .= " There was an issue sending the confirmation email, but your application was submitted.";
                }

                // Store in session to display on dashboard as we are redirecting
                $_SESSION['success_message'] = $current_success_message;
                header("location: dashboard.php");
                exit();

            } else {
                $error_message = "Error: Could not submit your application status. Please try again.";
            }
            unset($stmt_update_status);
        }
    } else {
        $error_message = "Applicant ID not found. Please complete your profile first.";
    }
}
// END NEW BLOCK

// Processing form data when form is submitted for document upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_document'])) {

    // Validate document type
    if (empty(trim($_POST["document_type"]))) {
        $document_type_err = "Please select a document type.";
    } else {
        $document_type = trim($_POST["document_type"]);
    }

    // Description is optional
    $description = trim($_POST["description"]);

    // Handle file upload
    if (isset($_FILES["document_file"]) && $_FILES["document_file"]["error"] == UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $max_file_size = 5 * 1024 * 1024; // 5 MB

        $file_name = $_FILES["document_file"]["name"];
        $file_tmp_name = $_FILES["document_file"]["tmp_name"];
        $file_size = $_FILES["document_file"]["size"];
        $file_type = mime_content_type($file_tmp_name); // More reliable than $_FILES['type']

        // Generate a unique filename to prevent overwrites and security issues
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $unique_file_name = uniqid('doc_') . '.' . $file_extension;
        $upload_dir = 'uploads/'; // Relative to your current script

        // Ensure upload directory exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true); // Create directory if it doesn't exist
        }

        $target_file = $upload_dir . $unique_file_name;

        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            $file_upload_err = "Only JPG, PNG, and PDF files are allowed.";
        }

        // Validate file size
        if ($file_size > $max_file_size) {
            $file_upload_err = "File is too large. Maximum size is 5MB.";
        }

        // If no upload errors, move the file and insert into database
        if (empty($file_upload_err)) {
            if (move_uploaded_file($file_tmp_name, $target_file)) {
                // File successfully uploaded, now insert path into database
                $sql_insert_doc = "INSERT INTO documents (applicant_id, document_type, file_path, description) VALUES (:applicant_id, :document_type, :file_path, :description)";
                if ($stmt_insert_doc = $pdo->prepare($sql_insert_doc)) {
                    $stmt_insert_doc->bindParam(":applicant_id", $applicant_id, PDO::PARAM_INT);
                    $stmt_insert_doc->bindParam(":document_type", $document_type, PDO::PARAM_STR);
                    $stmt_insert_doc->bindParam(":file_path", $target_file, PDO::PARAM_STR);
                    $stmt_insert_doc->bindParam(":description", $description, PDO::PARAM_STR);

                    if ($stmt_insert_doc->execute()) {
                        $success_message = "Document uploaded successfully!";
                        // Clear form fields
                        $document_type = $description = "";
                    } else {
                        // If database insert fails, try to delete the uploaded file
                        unlink($target_file);
                        $error_message = "Error: Could not save document details to database. Please try again later.";
                    }
                    unset($stmt_insert_doc);
                }
            } else {
                $file_upload_err = "Error uploading file. Please try again.";
            }
        }
    } else {
        if (isset($_FILES["document_file"]) && $_FILES["document_file"]["error"] == UPLOAD_ERR_NO_FILE) {
            $file_upload_err = "Please select a file to upload.";
        } else if (isset($_FILES["document_file"])) {
            $file_upload_err = "File upload error: " . $_FILES["document_file"]["error"]; // For debugging
        } else {
             $file_upload_err = "File upload failed or no file selected.";
        }
    }

    if (!empty($document_type_err) || !empty($file_upload_err)) {
        $error_message = "Please correct the errors in the form.";
    }
}

// Processing request to delete document entry
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['delete_id']) && $applicant_id) {
    $delete_id = filter_var($_GET['delete_id'], FILTER_VALIDATE_INT);

    if ($delete_id) {
        // First, get the file_path to delete the actual file
        $sql_get_path = "SELECT file_path FROM documents WHERE id = :id AND applicant_id = :applicant_id";
        if ($stmt_get_path = $pdo->prepare($sql_get_path)) {
            $stmt_get_path->bindParam(":id", $delete_id, PDO::PARAM_INT);
            $stmt_get_path->bindParam(":applicant_id", $applicant_id, PDO::PARAM_INT);
            if ($stmt_get_path->execute()) {
                $file_row = $stmt_get_path->fetch(PDO::FETCH_ASSOC);
                if ($file_row) {
                    $file_to_delete = $file_row['file_path'];

                    // Delete the record from the database
                    $sql_delete = "DELETE FROM documents WHERE id = :id AND applicant_id = :applicant_id";
                    if ($stmt_delete = $pdo->prepare($sql_delete)) {
                        $stmt_delete->bindParam(":id", $delete_id, PDO::PARAM_INT);
                        $stmt_delete->bindParam(":applicant_id", $applicant_id, PDO::PARAM_INT);

                        if ($stmt_delete->execute()) {
                            if ($stmt_delete->rowCount() > 0) {
                                // If database record deleted, try to delete the physical file
                                if (file_exists($file_to_delete) && unlink($file_to_delete)) {
                                    $success_message = "Document and file deleted successfully!";
                                } else {
                                    $success_message = "Document record deleted, but physical file could not be removed (or already missing).";
                                }
                            } else {
                                $error_message = "Error: Document not found or you don't have permission to delete it.";
                            }
                        } else {
                            $error_message = "Error: Could not delete document record. Please try again later.";
                        }
                        unset($stmt_delete);
                    }
                } else {
                    $error_message = "Document not found.";
                }
            } else {
                $error_message = "Error retrieving file path for deletion.";
            }
            unset($stmt_get_path);
        }
    } else {
        $error_message = "Invalid deletion request.";
    }
    // Redirect to clean URL after delete operation to prevent re-deletion on refresh
    header("location: documents.php");
    exit();
}


// Fetch all existing documents for the current applicant
$uploaded_documents = [];
if ($applicant_id) { // Only fetch if applicant_id is available
    $sql_fetch_all_docs = "SELECT * FROM documents WHERE applicant_id = :applicant_id ORDER BY uploaded_at DESC";
    if ($stmt_fetch_all_docs = $pdo->prepare($sql_fetch_all_docs)) {
        $stmt_fetch_all_docs->bindParam(":applicant_id", $applicant_id, PDO::PARAM_INT);
        if ($stmt_fetch_all_docs->execute()) {
            $uploaded_documents = $stmt_fetch_all_docs->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error_message = "Error fetching existing documents.";
        }
        unset($stmt_fetch_all_docs);
    }
}

unset($pdo); // Close connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Documents - Police Recruitment Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <header class="bg-blue-600 p-4 text-white shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Upload Documents</h1>
            <nav>
                <a href="dashboard.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">Dashboard</a>
                <a href="profile.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">My Profile</a>
                <a href="education.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">Education</a>
                <a href="documents.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">Documents</a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded ml-4 transition duration-300">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto mt-8 p-6 bg-white rounded-lg shadow-lg">
        <h2 class="text-3xl font-semibold text-gray-800 mb-6">Upload Required Documents</h2>
        <p class="text-gray-600 mb-4">Please upload clear copies of your documents (JPG, PNG, PDF, max 5MB).</p>

        <?php
        // Display success/error messages from current operation or session (for redirection)
        if (!empty($success_message)) {
            echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Success!</strong>
                    <span class="block sm:inline"> ' . $success_message . '</span>
                  </div>';
        }
        if (!empty($error_message)) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline"> ' . $error_message . '</span>
                  </div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="mb-8 p-6 border rounded-lg bg-gray-50">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">New Document Upload</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                <div>
                    <label for="document_type" class="block text-gray-700 text-sm font-bold mb-2">Document Type <span class="text-red-500">*</span></label>
                    <select id="document_type" name="document_type"
                            class="shadow appearance-none border <?php echo (!empty($document_type_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            required>
                        <option value="">Select Document Type</option>
                        <option value="Passport Photo" <?php echo ($document_type == 'Passport Photo') ? 'selected' : ''; ?>>Passport Photo</option>
                        <option value="Birth Certificate" <?php echo ($document_type == 'Birth Certificate') ? 'selected' : ''; ?>>Birth Certificate</option>
                        <option value="SSCE Certificate" <?php echo ($document_type == 'SSCE Certificate') ? 'selected' : ''; ?>>SSCE Certificate</option>
                        <option value="Degree Certificate" <?php echo ($document_type == 'Degree Certificate') ? 'selected' : ''; ?>>Degree Certificate</option>
                        <option value="State of Origin Certificate" <?php echo ($document_type == 'State of Origin Certificate') ? 'selected' : ''; ?>>State of Origin Certificate</option>
                        <option value="Medical Report" <?php echo ($document_type == 'Medical Report') ? 'selected' : ''; ?>>Medical Report</option>
                        <option value="Other" <?php echo ($document_type == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                    <span class="text-red-500 text-xs italic"><?php echo $document_type_err; ?></span>
                </div>

                <div>
                    <label for="document_file" class="block text-gray-700 text-sm font-bold mb-2">Select File <span class="text-red-500">*</span></label>
                    <input type="file" id="document_file" name="document_file"
                           class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 <?php echo (!empty($file_upload_err)) ? 'border border-red-500' : ''; ?>"
                           accept=".jpg,.jpeg,.png,.pdf" required>
                    <span class="text-red-500 text-xs italic"><?php echo $file_upload_err; ?></span>
                    <p class="text-gray-500 text-xs mt-1">Accepted: JPG, PNG, PDF. Max size: 5MB.</p>
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="block text-gray-700 text-sm font-bold mb-2">Description (Optional)</label>
                    <textarea id="description" name="description"
                              class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline h-24"
                              placeholder="e.g., My West African Examination Council Certificate"><?php echo htmlspecialchars($description); ?></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" name="upload_document"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-full focus:outline-none focus:shadow-outline text-lg transition duration-300">
                    Upload Document
                </button>
            </div>
        </form>

        <h2 class="text-3xl font-semibold text-gray-800 mt-12 mb-6">Your Uploaded Documents</h2>
        <?php if (empty($uploaded_documents)): ?>
            <p class="text-gray-600 text-center">No documents uploaded yet. Use the form above to upload one.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-md">
                    <thead>
                        <tr class="bg-gray-200 text-gray-700 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">Document Type</th>
                            <th class="py-3 px-6 text-left">Description</th>
                            <th class="py-3 px-6 text-left">File Name</th>
                            <th class="py-3 px-6 text-left">Uploaded On</th>
                            <th class="py-3 px-6 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm font-light">
                        <?php foreach ($uploaded_documents as $doc): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6 text-left whitespace-nowrap"><?php echo htmlspecialchars($doc['document_type']); ?></td>
                                <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($doc['description'] ?? 'N/A'); ?></td>
                                <td class="py-3 px-6 text-left">
                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="text-blue-600 hover:underline">
                                        <?php echo basename($doc['file_path']); ?>
                                    </a>
                                </td>
                                <td class="py-3 px-6 text-left"><?php echo date('Y-m-d H:i', strtotime($doc['uploaded_at'])); ?></td>
                                <td class="py-3 px-6 text-center">
                                    <a href="documents.php?delete_id=<?php echo $doc['id']; ?>"
                                       onclick="return confirm('Are you sure you want to delete this document? This will also delete the file from the server.');"
                                       class="text-red-600 hover:text-red-900 font-medium ml-2">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="flex justify-between items-center mt-8 pt-8 border-t border-gray-200">
            <a href="education.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center transition duration-300">
                &leftarrow; Previous: Educational Background
            </a>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <button type="submit" name="finish_application"
                        class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-flex items-center transition duration-300">
                    Finish Application &rightarrow;
                </button>
            </form>
        </div>

    </main>

    <footer class="bg-gray-800 text-white p-4 mt-12 text-center">
        <div class="container mx-auto">
            &copy; <?php echo date("Y"); ?> Police Recruitment Portal. All rights reserved.
        </div>
    </footer>

</body>
</html>