<?php
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// If you manually copied PHPMailer files, you need to require them
// The __DIR__ constant refers to the directory of the current file (send_email.php)
// So, it will be police_recruitment_portal/includes/
// We expect the PHPMailer files to be directly in police_recruitment_portal/includes/PHPMailer/
require_once __DIR__ . '/PHPMailer/src/Exception.php'; // Corrected path (removed 'src/')
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php'; // Corrected path (removed 'src/')
require_once __DIR__ . '/PHPMailer/src/SMTP.php';     // Corrected path (removed 'src/')

function sendEmail($recipientEmail, $subject, $body, $isHtml = true) {
    $mail = new PHPMailer(true); // Passing `true` enables exceptions

//     $mail->SMTPDebug = 4; // Set to 4 for detailed output, 2 for less detail
// $mail->Debugoutput = function($str, $level) {
//     echo "<pre>SMTP Debug ($level): $str</pre>"; // Output to browser
// };

    try {
        // Server settings (Your Mailtrap settings are good for testing!)
        $mail->isSMTP();                                            // Send using SMTP
        $mail->Host       = 'sandbox.smtp.mailtrap.io';            // Mailtrap SMTP Host
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = '3aa0d803b2ef3d';                       // Mailtrap Username
        $mail->Password   = '27e5d78e798dbe';                       // Mailtrap Password
        // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;         // Use if port is 465
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Use for port 2525 or 587
        $mail->Port       = 2525;                                   // Mailtrap Port

        // Recipients
        $mail->setFrom('no-reply@yourdomain.com', 'Police Recruitment Portal'); // Sender's email and name
        $mail->addAddress($recipientEmail);                         // Add a recipient

        // Content
        $mail->isHTML($isHtml);                                     // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $body;
        // $mail->AltBody = 'This is the plain text version for non-HTML mail clients'; // Optional plain-text body

        $mail->send();
        // echo 'Message has been sent'; // For debugging
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        // For development/debugging, you can uncomment the line below:
        // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        return false;
    }
}
// (Existing sendEmail function code should be here)

/**
 * Replaces placeholders in email subject and body with actual applicant data.
 *
 * @param string $text The subject or body text containing placeholders.
 * @param array $applicant_data An associative array of applicant details.
 * @return string The text with placeholders replaced.
 */
function populateEmailPlaceholders($text, $applicant_data) {
    $replacements = [];

    // Basic Applicant Info (from applicants table)
    $replacements['{{applicant_name}}'] = htmlspecialchars($applicant_data['first_name'] . ' ' . $applicant_data['last_name'] ?? '');
    $replacements['{{applicant_first_name}}'] = htmlspecialchars($applicant_data['first_name'] ?? '');
    $replacements['{{applicant_last_name}}'] = htmlspecialchars($applicant_data['last_name'] ?? '');
    $replacements['{{applicant_email}}'] = htmlspecialchars($applicant_data['email'] ?? '');
    $replacements['{{applicant_phone}}'] = htmlspecialchars($applicant_data['phone_number'] ?? '');
    $replacements['{{applicant_id}}'] = htmlspecialchars($applicant_data['id'] ?? '');
    $replacements['{{current_status}}'] = htmlspecialchars(ucwords(str_replace('_', ' ', $applicant_data['application_status'] ?? '')));
    $replacements['{{date_of_birth}}'] = htmlspecialchars($applicant_data['dob'] ?? 'N/A'); // Using 'dob' as per your DB
    $replacements['{{middle_name}}'] = htmlspecialchars($applicant_data['other_names'] ?? 'N/A'); // Using 'other_names' as per your DB
    $replacements['{{address}}'] = htmlspecialchars($applicant_data['address'] ?? 'N/A');
    $replacements['{{city}}'] = htmlspecialchars($applicant_data['city'] ?? 'N/A');
    $replacements['{{state}}'] = htmlspecialchars($applicant_data['state'] ?? 'N/A');
    // Add other applicant fields you want to make available as placeholders
    // e.g., $replacements['{{country}}'] = htmlspecialchars($applicant_data['country'] ?? 'N/A'); if you add country column

    // Example of dynamic placeholders for specific scenarios (e.g., test/interview dates)
    // You would typically set these in the session or pass them explicitly if they are not
    // directly part of the applicant_data fetched from the 'applicants' table.
    // For now, these will remain empty if not set in $applicant_data or passed.
    $replacements['{{test_date}}'] = ''; // Placeholder for a test date
    $replacements['{{test_time}}'] = ''; // Placeholder for a test time
    $replacements['{{test_location}}'] = ''; // Placeholder for a test location
    $replacements['{{interview_date}}'] = ''; // Placeholder for an interview date
    $replacements['{{interview_time}}'] = ''; // Placeholder for an interview time
    $replacements['{{interview_location}}'] = ''; // Placeholder for an interview location

    // Important: Iterate through $replacements to perform the replacement
    foreach ($replacements as $placeholder => $value) {
        $text = str_replace($placeholder, $value, $text);
    }

    return $text;
}

// Ensure the existing sendEmail function is accessible.
// If it's not wrapped in a function, ensure it is, or define it here if it was just raw code.
// Example:
/*
function sendEmail($to, $subject, $body) {
    // ... existing SwiftMailer or PHPMailer code ...
    // Make sure it returns true on success, false on failure
}
*/
?>