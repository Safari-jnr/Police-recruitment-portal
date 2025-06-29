<?php
// Start the session
session_start();

// Include database connection file
require_once 'includes/config.php';

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

// Corrected variable names to match database columns and form intent
$institution_name = $degree = $field_of_study = $start_year = $end_year = $grade = "";
$institution_name_err = $degree_err = $field_of_study_err = $start_year_err = $end_year_err = ""; // Corrected error variables
$success_message = $error_message = "";

// --- First, get the applicant_id for the current user ---
$sql_get_applicant_id = "SELECT id FROM applicants WHERE user_id = :user_id";
if ($stmt_get_applicant_id = $pdo->prepare($sql_get_applicant_id)) {
    $stmt_get_applicant_id->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_get_applicant_id->execute()) {
        if ($stmt_get_applicant_id->rowCount() == 1) {
            $applicant_row = $stmt_get_applicant_id->fetch(PDO::FETCH_ASSOC);
            $applicant_id = $applicant_row['id'];
        } else {
            // Applicant profile does not exist, redirect them to create it first
            $_SESSION['info_message'] = "Please complete your personal profile before adding educational details.";
            header("location: profile.php");
            exit();
        }
    } else {
        $error_message = "Error fetching applicant ID.";
    }
    unset($stmt_get_applicant_id);
}
// --- End of getting applicant_id ---

// Processing form data when form is submitted to add new education
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_education'])) {

    // Validate Institution Name
    if (empty(trim($_POST["institution_name"]))) {
        $institution_name_err = "Please enter institution name.";
    } else {
        $institution_name = trim($_POST["institution_name"]);
    }

    // Validate Degree (was degree_certificate)
    if (empty(trim($_POST["degree"]))) { // Changed from degree_certificate to degree
        $degree_err = "Please enter degree or certificate."; // Changed err variable
    } else {
        $degree = trim($_POST["degree"]); // Changed from degree_certificate to degree
    }

    // Validate Field of Study (NEW)
    if (empty(trim($_POST["field_of_study"]))) {
        $field_of_study_err = "Please enter field of study.";
    } else {
        $field_of_study = trim($_POST["field_of_study"]);
    }

    // Validate Start Year (was start_date)
    if (empty(trim($_POST["start_year"]))) { // Changed from start_date to start_year
        $start_year_err = "Please enter start year."; // Changed err variable
    } else {
        $start_year = trim($_POST["start_year"]); // Changed from start_date to start_year
        // Basic year validation
        if (!is_numeric($start_year) || $start_year < 1900 || $start_year > date("Y")) {
             $start_year_err = "Please enter a valid start year.";
        }
    }

    // End Year is optional (was end_date)
    $end_year = trim($_POST["end_year"]); // Changed from end_date to end_year
    // Optional: Check if end_year is after start_year if both are provided
    if (!empty($start_year) && !empty($end_year)) {
        if (!is_numeric($end_year) || $end_year < 1900 || $end_year > (date("Y") + 10)) { // Allow a bit into future for ongoing
            $end_year_err = "Please enter a valid end year.";
        } else if ($start_year > $end_year) {
            $end_year_err = "End year cannot be before start year.";
        }
    }


    // Grade is optional
    $grade = trim($_POST["grade"]);

    // Check input errors before inserting in database
    if (empty($institution_name_err) && empty($degree_err) && empty($field_of_study_err) && empty($start_year_err) && empty($end_year_err)) {
        // Changed table name to educational_backgrounds and column names to match DB
        $sql_insert_education = "INSERT INTO educational_backgrounds (applicant_id, institution_name, degree, field_of_study, start_year, end_year, grade) VALUES (:applicant_id, :institution_name, :degree, :field_of_study, :start_year, :end_year, :grade)";

        if ($stmt_insert_education = $pdo->prepare($sql_insert_education)) {
            $stmt_insert_education->bindParam(":applicant_id", $applicant_id, PDO::PARAM_INT);
            $stmt_insert_education->bindParam(":institution_name", $institution_name, PDO::PARAM_STR);
            $stmt_insert_education->bindParam(":degree", $degree, PDO::PARAM_STR); // Changed
            $stmt_insert_education->bindParam(":field_of_study", $field_of_study, PDO::PARAM_STR); // NEW
            $stmt_insert_education->bindParam(":start_year", $start_year, PDO::PARAM_INT); // Changed
            $stmt_insert_education->bindParam(":end_year", $end_year, PDO::PARAM_INT); // Changed
            $stmt_insert_education->bindParam(":grade", $grade, PDO::PARAM_STR);

            if ($stmt_insert_education->execute()) {
                $success_message = "Educational entry added successfully!";
                // Clear form fields after successful submission
                $institution_name = $degree = $field_of_study = $start_year = $end_year = $grade = ""; // Clear new fields too
            } else {
                $error_message = "Error: Could not add educational entry. Please try again later.";
            }
            unset($stmt_insert_education);
        }
    } else {
        $error_message = "Please correct the errors in the form.";
    }
}

// Processing request to delete education entry
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['delete_id']) && $applicant_id) {
    $delete_id = filter_var($_GET['delete_id'], FILTER_VALIDATE_INT);

    if ($delete_id) {
        // Ensure the education entry belongs to the current applicant
        // Changed table name to educational_backgrounds
        $sql_delete = "DELETE FROM educational_backgrounds WHERE id = :id AND applicant_id = :applicant_id";
        if ($stmt_delete = $pdo->prepare($sql_delete)) {
            $stmt_delete->bindParam(":id", $delete_id, PDO::PARAM_INT);
            $stmt_delete->bindParam(":applicant_id", $applicant_id, PDO::PARAM_INT);

            if ($stmt_delete->execute()) {
                if ($stmt_delete->rowCount() > 0) {
                    $success_message = "Educational entry deleted successfully!";
                } else {
                    $error_message = "Error: Entry not found or you don't have permission to delete it.";
                }
            } else {
                $error_message = "Error: Could not delete educational entry. Please try again later.";
            }
            unset($stmt_delete);
        }
    } else {
        $error_message = "Invalid deletion request.";
    }
    // Redirect to clean URL after delete operation to prevent re-deletion on refresh
    header("location: education.php");
    exit();
}


// Fetch all existing educational entries for the current applicant
$educational_entries = [];
if ($applicant_id) { // Only fetch if applicant_id is available
    // Changed table name to educational_backgrounds
    $sql_fetch_all_education = "SELECT * FROM educational_backgrounds WHERE applicant_id = :applicant_id ORDER BY start_year DESC"; // Changed ORDER BY to start_year
    if ($stmt_fetch_all_education = $pdo->prepare($sql_fetch_all_education)) {
        $stmt_fetch_all_education->bindParam(":applicant_id", $applicant_id, PDO::PARAM_INT);
        if ($stmt_fetch_all_education->execute()) {
            $educational_entries = $stmt_fetch_all_education->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error_message = "Error fetching existing educational entries.";
        }
        unset($stmt_fetch_all_education);
    }
}

unset($pdo); // Close connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Educational Background - Police Recruitment Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <header class="bg-blue-600 p-4 text-white shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Educational Background</h1>
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
        <h2 class="text-3xl font-semibold text-gray-800 mb-6">Add Educational Details</h2>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Success!</strong>
                <span class="block sm:inline"> <?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"> <?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="mb-8 p-6 border rounded-lg bg-gray-50">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">New Education Entry</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                <div>
                    <label for="institution_name" class="block text-gray-700 text-sm font-bold mb-2">Institution Name <span class="text-red-500">*</span></label>
                    <input type="text" id="institution_name" name="institution_name" value="<?php echo htmlspecialchars($institution_name); ?>"
                           class="shadow appearance-none border <?php echo (!empty($institution_name_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="e.g., University of XYZ" required>
                    <span class="text-red-500 text-xs italic"><?php echo $institution_name_err; ?></span>
                </div>

                <div>
                    <label for="degree" class="block text-gray-700 text-sm font-bold mb-2">Degree/Certificate <span class="text-red-500">*</span></label>
                    <input type="text" id="degree" name="degree" value="<?php echo htmlspecialchars($degree); ?>" class="shadow appearance-none border <?php echo (!empty($degree_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="e.g., BSc Computer Science, SSCE" required>
                    <span class="text-red-500 text-xs italic"><?php echo $degree_err; ?></span> </div>

                <div>
                    <label for="field_of_study" class="block text-gray-700 text-sm font-bold mb-2">Field of Study <span class="text-red-500">*</span></label>
                    <input type="text" id="field_of_study" name="field_of_study" value="<?php echo htmlspecialchars($field_of_study); ?>"
                           class="shadow appearance-none border <?php echo (!empty($field_of_study_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="e.g., Computer Science, Arts, Engineering" required>
                    <span class="text-red-500 text-xs italic"><?php echo $field_of_study_err; ?></span>
                </div>


                <div>
                    <label for="start_year" class="block text-gray-700 text-sm font-bold mb-2">Start Year <span class="text-red-500">*</span></label>
                    <input type="number" id="start_year" name="start_year" value="<?php echo htmlspecialchars($start_year); ?>" class="shadow appearance-none border <?php echo (!empty($start_year_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="YYYY" min="1900" max="<?php echo date("Y"); ?>" required>
                    <span class="text-red-500 text-xs italic"><?php echo $start_year_err; ?></span>
                </div>

                <div>
                    <label for="end_year" class="block text-gray-700 text-sm font-bold mb-2">End Year (Optional)</label>
                    <input type="number" id="end_year" name="end_year" value="<?php echo htmlspecialchars($end_year); ?>" class="shadow appearance-none border <?php echo (!empty($end_year_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="YYYY" min="1900" max="<?php echo date("Y") + 10; ?>">
                    <span class="text-red-500 text-xs italic"><?php echo $end_year_err; ?></span>
                </div>

                <div class="md:col-span-2">
                    <label for="grade" class="block text-gray-700 text-sm font-bold mb-2">Grade/CGPA (Optional)</label>
                    <input type="text" id="grade" name="grade" value="<?php echo htmlspecialchars($grade); ?>"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="e.g., 2.1, Distinction, A">
                </div>
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" name="add_education"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-full focus:outline-none focus:shadow-outline text-lg transition duration-300">
                    Add Education
                </button>
            </div>
        </form>

        <h2 class="text-3xl font-semibold text-gray-800 mt-12 mb-6">Your Educational Entries</h2>
        <?php if (empty($educational_entries)): ?>
            <p class="text-gray-600 text-center">No educational entries added yet. Use the form above to add one.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-md">
                    <thead>
                        <tr class="bg-gray-200 text-gray-700 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">Institution</th>
                            <th class="py-3 px-6 text-left">Degree/Certificate</th>
                            <th class="py-3 px-6 text-left">Field of Study</th> <th class="py-3 px-6 text-left">Start Year</th>
                            <th class="py-3 px-6 text-left">End Year</th>
                            <th class="py-3 px-6 text-left">Grade</th>
                            <th class="py-3 px-6 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm font-light">
                        <?php foreach ($educational_entries as $entry): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6 text-left whitespace-nowrap"><?php echo htmlspecialchars($entry['institution_name']); ?></td>
                                <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($entry['degree']); ?></td> <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($entry['field_of_study']); ?></td> <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($entry['start_year']); ?></td> <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($entry['end_year'] ?? 'Present'); ?></td> <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($entry['grade'] ?? 'N/A'); ?></td>
                                <td class="py-3 px-6 text-center">
                                    <a href="education.php?delete_id=<?php echo $entry['id']; ?>"
                                       onclick="return confirm('Are you sure you want to delete this entry?');"
                                       class="text-red-600 hover:text-red-900 font-medium ml-2">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <div class="flex justify-between items-center mt-8 pt-8 border-t border-gray-200">
        <a href="profile.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center transition duration-300">
            &leftarrow; Previous: Personal Profile
        </a>
        <a href="documents.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center transition duration-300">
            Next: Upload Documents &rightarrow;
        </a>
    </div>


    </main>

    <footer class="bg-gray-800 text-white p-4 mt-12 text-center">
        <div class="container mx-auto">
            &copy; <?php echo date("Y"); ?> Police Recruitment Portal. All rights reserved.
        </div>
    </footer>

</body>
</html>