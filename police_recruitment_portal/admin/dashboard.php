<?php
session_start();
require_once '../includes/config.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'admin') {
    header("location: ../login.php");
    exit;
}

$total_applicants = 0;
$status_counts = [];
$recent_applicants = [];
$dashboard_error = ""; // To store any specific dashboard errors

try {
    // 1. Fetch Total Applicants
    $stmt = $pdo->query("SELECT COUNT(id) AS total_applicants FROM applicants");
    $total_applicants = $stmt->fetchColumn();

    // 2. Fetch Applicants by Status
    $stmt = $pdo->query("SELECT application_status, COUNT(id) AS count FROM applicants GROUP BY application_status ORDER BY count DESC");
    $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Recent Applicants (e.g., last 5)
    $sql_recent = "SELECT a.id, a.first_name, a.last_name, a.application_status, a.created_at, u.email 
                   FROM applicants a 
                   JOIN users u ON a.user_id = u.id 
                   ORDER BY a.created_at DESC 
                   LIMIT 5";
    $stmt_recent = $pdo->query($sql_recent);
    $recent_applicants = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $dashboard_error = "Database error fetching dashboard data: " . $e->getMessage();
    error_log("PDO Exception on dashboard: " . $e->getMessage());
} finally {
    $pdo = null; // Close PDO connection
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
        .status-under_review { background-color: #e9d5ff; color: #6b21a8; } /* purple-100, purple-800 */
        .status-shortlisted { background-color: #d1fae5; color: #065f46; } /* new color for shortlisted */
        .status-invited_for_test, .status-invited_for_interview { background-color: #e0e7ff; color: #3730a3; } /* indigo-100, indigo-800 */
        .status-medical_check { background-color: #dbeafe; color: #1d4ed8; } /* blue-100, blue-700 */
        .status-recommended_for_training { background-color: #dcfce7; color: #166534; } /* green-100, green-800 */
        .status-accepted { background-color: #d1fae5; color: #065f46; } /* If you added 'accepted' */
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
                <a href="email_templates.php" class="px-3 py-2 rounded hover:bg-indigo-800 transition duration-300">Email Templates</a>
                <a href="../logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded ml-4 transition duration-300">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto mt-8 p-6 bg-white rounded-lg shadow-lg">
    <h2 class="text-3xl font-semibold text-gray-800 mb-6">Welcome, <?php echo htmlspecialchars($_SESSION["username"] ?? 'Admin'); ?>!</h2>

        <?php if (!empty($dashboard_error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"> <?php echo $dashboard_error; ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-blue-50 p-6 rounded-lg shadow-md border-l-4 border-blue-600">
                <h3 class="text-xl font-semibold text-blue-800 mb-2">Total Applicants</h3>
                <p class="text-4xl font-bold text-blue-900"><?php echo $total_applicants; ?></p>
                <a href="applicants.php" class="text-blue-600 hover:underline mt-2 inline-block">View All</a>
            </div>

            <div class="bg-purple-50 p-6 rounded-lg shadow-md border-l-4 border-purple-600 col-span-1 md:col-span-2">
                <h3 class="text-xl font-semibold text-purple-800 mb-4">Applicants by Status</h3>
                <div class="space-y-2">
                    <?php if (empty($status_counts)): ?>
                        <p class="text-gray-600">No applicants found to categorize.</p>
                    <?php else: ?>
                        <?php foreach ($status_counts as $status_data): ?>
                            <div class="flex justify-between items-center text-gray-700">
                                <span class="text-md capitalize"><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($status_data['application_status']))); ?></span>
                                <span class="font-semibold text-lg"><?php echo $status_data['count']; ?></span>
                                <span class="status-tag <?php echo 'status-' . str_replace(' ', '_', $status_data['application_status']); ?>">
                                    <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($status_data['application_status']))); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 p-6 rounded-lg shadow-md border-l-4 border-gray-400">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Recent Applications</h3>
            <?php if (empty($recent_applicants)): ?>
                <p class="text-gray-600">No recent applications found.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-sm">
                        <thead>
                            <tr class="bg-gray-100 border-b border-gray-200 text-gray-700 text-left text-sm font-medium uppercase tracking-wider">
                                <th class="py-3 px-4">Applicant Name</th>
                                <th class="py-3 px-4">Email</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Applied On</th>
                                <th class="py-3 px-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_applicants as $applicant): ?>
                                <tr class="border-b border-gray-200 last:border-b-0 hover:bg-gray-50">
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?></td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($applicant['email']); ?></td>
                                    <td class="py-3 px-4">
                                        <span class="status-tag <?php echo 'status-' . str_replace(' ', '_', $applicant['application_status']); ?>">
                                            <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($applicant['application_status']))); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($applicant['created_at']))); ?></td>
                                    <td class="py-3 px-4">
                                        <a href="view_applicant.php?id=<?php echo $applicant['id']; ?>"
                                           class="bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-1 px-3 rounded text-xs transition duration-300">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-gray-800 text-white p-4 mt-12 text-center">
        <div class="container mx-auto">
            &copy; <?php echo date("Y"); ?> Police Recruitment Portal. All rights reserved.
        </div>
    </footer>

</body>
</html>