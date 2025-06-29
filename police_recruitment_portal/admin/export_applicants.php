<?php
session_start();
require_once '../includes/config.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'admin') {
    header("location: ../login.php");
    exit;
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="applicants_export_' . date('Ymd_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Define CSV columns (headers)
// IMPORTANT: Adjust these column names to exactly match your database columns
// for first_name, middle_name (other_names), last_name, dob
// and ensure all next_of_kin columns are present if you want them.
$headers = [
    'Applicant ID', 'Email', 'First Name', 'Middle Name', 'Last Name',
    'Date of Birth', 'Gender', 'Phone Number', 'Address', 'City', 'State', 'LGA', 'NIN',
    'Application Status',
    'Registered At'
];
fputcsv($output, $headers);

try {
    // SQL Query to fetch data
    // Ensure column names here match your database exactly (e.g., other_names for middle_name, dob for date_of_birth)
    $sql = "SELECT
                a.id,
                u.email,
                a.first_name,
                a.other_names, -- Use 'other_names' for middle name as per your DB
                a.last_name,
                a.dob, -- Use 'dob' for date of birth as per your DB
                a.gender,
                a.phone_number,
                a.address,
                a.city,
                a.state,
                a.lga,
                a.nin,
                a.application_status,
                a.created_at AS registered_at
            FROM
                applicants a
            JOIN
                users u ON a.user_id = u.id
            ORDER BY a.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Format data if necessary (e.g., status to readable string)
        $row['application_status'] = ucwords(str_replace('_', ' ', $row['application_status']));

        // Add more formatting or data manipulation if needed for specific columns

        // Write the row to the CSV file
        fputcsv($output, $row);
    }

} catch (PDOException $e) {
    // If there's a database error, write an error message to the CSV (or log it)
    fputcsv($output, ['Error fetching data: ' . $e->getMessage()]);
    error_log("Database error during CSV export: " . $e->getMessage());
} finally {
    // Close the output stream
    fclose($output);
    $pdo = null; // Close PDO connection
}
exit; // Important: terminate script after file generation
?>