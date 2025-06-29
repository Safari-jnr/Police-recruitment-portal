<?php
// Start the session
session_start();

// Include database connection file
require_once '../includes/config.php';

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

$applicants = [];
$error_message = "";

try {
    // Fetch all applicants and their current application status
    // We join with the users table to get the email address as well
    $sql = "SELECT a.id, a.first_name, a.last_name, a.application_status, u.email, a.created_at
            FROM applicants a
            JOIN users u ON a.user_id = u.id
            ORDER BY a.created_at DESC"; // Order by most recent applicants first

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error fetching applicants: " . $e->getMessage());
    $error_message = "Error fetching applicant data. Please try again later.";
}
unset($pdo); // Close connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Applicants - Admin Panel</title>
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
        <h2 class="text-3xl font-semibold text-gray-800 mb-6">Manage Applicants</h2>
        <div class="mb-6 text-right">
          <a href="export_applicants.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition duration-300">
             Export All Applicants to CSV
          </a> 
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"> <?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($applicants)): ?>
            <p class="text-gray-600 text-center py-8">No applicants registered yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-md">
                    <thead>
                        <tr class="bg-gray-200 text-gray-700 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">ID</th>
                            <th class="py-3 px-6 text-left">Name</th>
                            <th class="py-3 px-6 text-left">Email</th>
                            <th class="py-3 px-6 text-left">Status</th>
                            <th class="py-3 px-6 text-left">Registered On</th>
                            <th class="py-3 px-6 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm font-light">
                        <?php foreach ($applicants as $applicant): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6 text-left whitespace-nowrap"><?php echo htmlspecialchars($applicant['id']); ?></td>
                                <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?></td>
                                <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($applicant['email']); ?></td>
                                <td class="py-3 px-6 text-left">
                                    <span class="px-2 py-1 font-semibold leading-tight rounded-full
                                        <?php
                                            // Dynamic styling for status
                                            if ($applicant['application_status'] == 'pending') echo 'bg-yellow-100 text-yellow-800';
                                            else if ($applicant['application_status'] == 'submitted') echo 'bg-blue-100 text-blue-800';
                                            else if ($applicant['application_status'] == 'under_review') echo 'bg-purple-100 text-purple-800';
                                            else if ($applicant['application_status'] == 'invited_for_test') echo 'bg-indigo-100 text-indigo-800';
                                            else if ($applicant['application_status'] == 'accepted') echo 'bg-green-100 text-green-800';
                                            else if ($applicant['application_status'] == 'rejected') echo 'bg-red-100 text-red-800';
                                            else echo 'bg-gray-100 text-gray-800'; // Default
                                        ?>">
                                        <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($applicant['application_status']))); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-left"><?php echo date('Y-m-d', strtotime($applicant['created_at'])); ?></td>
                                <td class="py-3 px-6 text-center">
                                    <a href="view_applicant.php?id=<?php echo $applicant['id']; ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-xs transition duration-300">View Details</a>
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