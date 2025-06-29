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

// Check if the logged-in user is an applicant, otherwise redirect to a suitable page
if (isset($_SESSION["role"]) && $_SESSION["role"] !== 'applicant') {
    header("location: admin/dashboard.php"); // Redirect admins (assuming you'll create this)
    exit;
}

// Fetch applicant's basic details and application status
$user_id = $_SESSION['id']; // This is the user_id from the users table
$first_name = "Applicant"; // Default
$last_name = ""; // Default
$application_status = "Not Started"; // Default status if no profile exists

// Try to fetch the applicant's name and application status from the 'applicants' table
// NEW: Include application_status in the select query
$sql = "SELECT first_name, last_name, application_status FROM applicants WHERE user_id = :user_id";
$stmt = null; // Initialize stmt outside the try-catch for proper unset

try {
    if ($stmt = $pdo->prepare($sql)) {
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        if ($stmt->execute()) {
            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $first_name = htmlspecialchars($row['first_name']);
                $last_name = htmlspecialchars($row['last_name']);
                // NEW: Fetch the application status
                $application_status = htmlspecialchars($row['application_status']);
            } else {
                // If applicant profile doesn't exist yet, prompt them to create it
                $_SESSION['info_message'] = "Welcome! Please complete your profile to start your application.";
                $application_status = "Profile Incomplete"; // Indicate specific status
            }
        } else {
            // Error executing the query
            echo "Oops! Something went wrong fetching applicant details.";
            error_log("Error fetching applicant details for user_id: " . $user_id . " - " . implode(":", $stmt->errorInfo()));
        }
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    error_log("PDO Exception: " . $e->getMessage());
} finally {
    if ($stmt) {
        unset($stmt); // Close statement
    }
    unset($pdo); // Close connection
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Dashboard - Police Recruitment Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <header class="bg-blue-600 p-4 text-white shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Applicant Dashboard</h1>
            <nav>
                <a href="dashboard.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">Dashboard</a>
                <a href="profile.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">My Profile</a>
                <a href="education.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">Apply Now</a>
                <a href="documents.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">Documents</a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded ml-4 transition duration-300">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto mt-8 p-6 bg-white rounded-lg shadow-lg">
        <h2 class="text-3xl font-semibold text-gray-800 mb-6">
            Welcome, <?php echo ($first_name && $last_name) ? htmlspecialchars($first_name . ' ' . $last_name) : htmlspecialchars($_SESSION['email']); ?>!
        </h2>

        <?php
        // Display info message (e.g., if profile is incomplete)
        if (isset($_SESSION['info_message'])) {
            echo '<div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">';
            echo '<strong class="font-bold">Heads up!</strong>';
            echo '<span class="block sm:inline"> ' . $_SESSION['info_message'] . '</span>';
            echo '</div>';
            unset($_SESSION['info_message']); // Clear the message after displaying
        }
        ?>

        <div class="mb-8 p-6 bg-blue-50 border border-blue-200 rounded-lg shadow-md">
            <h3 class="text-2xl font-semibold text-blue-800 mb-4">Your Application Status:</h3>
            <?php
            $status_class = "text-gray-700";
            $status_message = "";

            switch ($application_status) {
                case 'Profile Incomplete':
                    $status_class = "text-red-600 font-bold";
                    $status_message = "Your profile is incomplete. Please go to 'My Profile' to fill in your personal details.";
                    break;
                case 'pending':
                    $status_class = "text-yellow-600 font-bold";
                    $status_message = "Your application has been submitted and is currently pending review.";
                    break;
                case 'under_review':
                    $status_class = "text-blue-600 font-bold";
                    $status_message = "Your application is currently under review by our team.";
                    break;
                case 'shortlisted':
                    $status_class = "text-green-600 font-bold";
                    $status_message = "Congratulations! You have been shortlisted. Please check for further instructions (e.g., test dates, interview schedule).";
                    break;
                case 'rejected':
                    $status_class = "text-red-700 font-bold";
                    $status_message = "We regret to inform you that your application was not successful at this time.";
                    break;
                case 'invited_for_test':
                    $status_class = "text-indigo-600 font-bold";
                    $status_message = "You have been invited for an aptitude test. Please check the 'Documents' or 'Announcements' section for details.";
                    break;
                case 'invited_for_interview':
                    $status_class = "text-purple-600 font-bold";
                    $status_message = "You have been invited for an interview. Please check the 'Documents' or 'Announcements' section for details.";
                    break;
                case 'recommended_for_training':
                    $status_class = "text-green-700 font-bold";
                    $status_message = "Congratulations! You have been recommended for training. Further details will be communicated soon.";
                    break;
                case 'Not Started':
                default:
                    $status_class = "text-gray-500 font-bold";
                    $status_message = "You have not started your application yet. Please complete your profile and educational background.";
                    break;
            }
            ?>
            <p class="text-xl <?php echo $status_class; ?> mb-2">
                Status: <span class="uppercase"><?php echo htmlspecialchars(str_replace('_', ' ', $application_status)); ?></span>
            </p>
            <p class="text-gray-700">
                <?php echo $status_message; ?>
            </p>
            <?php if ($application_status == 'Profile Incomplete' || $application_status == 'Not Started'): ?>
                <div class="mt-4">
                    <a href="profile.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full transition duration-300">
                        Complete Your Profile
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="bg-gray-50 p-6 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300">
                <h3 class="text-xl font-semibold text-gray-700 mb-3">My Profile</h3>
                <p class="text-gray-600 mb-4">View and update your personal, educational, and contact details.</p>
                <a href="profile.php" class="inline-block bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-full transition duration-300">
                    Go to Profile
                </a>
            </div>

            <div class="bg-gray-50 p-6 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300">
                <h3 class="text-xl font-semibold text-gray-700 mb-3">Education & Application</h3>
                <p class="text-gray-600 mb-4">Start a new application or continue a pending one.</p>
                <a href="education.php" class="inline-block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full transition duration-300">
                    Start Application
                </a>
            </div>

            <div class="bg-gray-50 p-6 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300">
                <h3 class="text-xl font-semibold text-gray-700 mb-3">Upload Documents</h3>
                <p class="text-gray-600 mb-4">Upload all required supporting documents for your application.</p>
                <a href="documents.php" class="inline-block bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-full transition duration-300">
                    Manage Documents
                </a>
            </div>
            </div>
    </main>

    <footer class="bg-gray-800 text-white p-4 mt-12 text-center">
        <div class="container mx-auto">
            &copy; <?php echo date("Y"); ?> Police Recruitment Portal. All rights reserved.
        </div>
    </footer>

</body>
</html>