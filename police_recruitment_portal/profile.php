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

// Define variables and initialize with empty values
$first_name = $last_name = $other_names = $dob = $gender = $phone_number = $application_status = "";
// NEW: Add variables for address, city, state, lga, nin
$address = $city = $state = $lga = $nin = "";

$first_name_err = $last_name_err = $dob_err = $gender_err = $phone_number_err = "";
// NEW: Add error variables for address, city, state, lga, nin
$address_err = $city_err = $state_err = $lga_err = $nin_err = "";

$success_message = $error_message = "";

// Fetch existing applicant data
// NEW: Select all new columns
$sql_fetch = "SELECT id, first_name, last_name, other_names, dob, gender, phone_number, application_status, address, city, state, lga, nin FROM applicants WHERE user_id = :user_id";
if ($stmt_fetch = $pdo->prepare($sql_fetch)) {
    $stmt_fetch->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    if ($stmt_fetch->execute()) {
        if ($stmt_fetch->rowCount() == 1) {
            $row = $stmt_fetch->fetch(PDO::FETCH_ASSOC);
            $first_name = $row['first_name'];
            $last_name = $row['last_name'];
            $other_names = $row['other_names'];
            $dob = $row['dob'];
            $gender = $row['gender'];
            $phone_number = $row['phone_number'];
            $application_status = $row['application_status']; // Display current status
            // NEW: Assign fetched values to new variables
            $address = $row['address'];
            $city = $row['city'];
            $state = $row['state'];
            $lga = $row['lga'];
            $nin = $row['nin'];
        }
    } else {
        $error_message = "Oops! Something went wrong fetching your profile. Please try again later.";
    }
    unset($stmt_fetch);
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate first name
    if (empty(trim($_POST["first_name"]))) {
        $first_name_err = "Please enter your first name.";
    } else {
        $first_name = trim($_POST["first_name"]);
    }

    // Validate last name
    if (empty(trim($_POST["last_name"]))) {
        $last_name_err = "Please enter your last name.";
    } else {
        $last_name = trim($_POST["last_name"]);
    }

    // Other names (optional)
    $other_names = trim($_POST["other_names"]);

    // Validate Date of Birth
    if (empty(trim($_POST["dob"]))) {
        $dob_err = "Please enter your date of birth.";
    } else {
        $dob = trim($_POST["dob"]);
        // Optional: Add more robust date validation (e.g., check format, age)
    }

    // Validate Gender
    if (empty(trim($_POST["gender"]))) {
        $gender_err = "Please select your gender.";
    } else {
        $gender = trim($_POST["gender"]);
        // Ensure selected gender is one of the ENUM values
        $allowed_genders = ['Male', 'Female', 'Other'];
        if (!in_array($gender, $allowed_genders)) {
            $gender_err = "Invalid gender selection.";
        }
    }

    // Validate Phone Number (optional, basic check)
    $phone_number = trim($_POST["phone_number"]);
    if (!empty($phone_number) && !preg_match("/^[0-9]{10,15}$/", $phone_number)) { // Basic check for 10-15 digits
        $phone_number_err = "Please enter a valid phone number (10-15 digits).";
    }

    // NEW: Validate Address
    if (empty(trim($_POST["address"]))) {
        $address_err = "Please enter your residential address.";
    } else {
        $address = trim($_POST["address"]);
    }

    // NEW: Validate City
    if (empty(trim($_POST["city"]))) {
        $city_err = "Please enter your city.";
    } else {
        $city = trim($_POST["city"]);
    }

    // NEW: Validate State
    if (empty(trim($_POST["state"]))) {
        $state_err = "Please select your state.";
    } else {
        $state = trim($_POST["state"]);
    }

    // NEW: Validate LGA
    if (empty(trim($_POST["lga"]))) {
        $lga_err = "Please enter your Local Government Area (LGA).";
    } else {
        $lga = trim($_POST["lga"]);
    }

    // NEW: Validate NIN
    if (empty(trim($_POST["nin"]))) {
        $nin_err = "Please enter your National Identification Number (NIN).";
    } elseif (!preg_match("/^\d{11}$/", trim($_POST["nin"]))) { // NIN typically 11 digits
        $nin_err = "NIN must be 11 digits.";
    } else {
        $nin = trim($_POST["nin"]);
        // Optional: Add a check for unique NIN if needed, but it might be better handled by a UNIQUE constraint in the DB
    }


    // Check input errors before inserting/updating in database
    // NEW: Include all new error variables in the check
    if (empty($first_name_err) && empty($last_name_err) && empty($dob_err) && empty($gender_err) && empty($phone_number_err) && empty($address_err) && empty($city_err) && empty($state_err) && empty($lga_err) && empty($nin_err)) {
        // Check if profile exists (for UPDATE) or needs to be inserted (for INSERT)
        if (!empty($application_status)) { // If $application_status is not empty, means data was fetched, so UPDATE
            // NEW: Add all new columns to UPDATE query
            $sql_save = "UPDATE applicants SET first_name = :first_name, last_name = :last_name, other_names = :other_names, dob = :dob, gender = :gender, phone_number = :phone_number, address = :address, city = :city, state = :state, lga = :lga, nin = :nin WHERE user_id = :user_id";
        } else { // No existing profile, so INSERT
            // NEW: Add all new columns to INSERT query
            $sql_save = "INSERT INTO applicants (user_id, first_name, last_name, other_names, dob, gender, phone_number, address, city, state, lga, nin, application_status) VALUES (:user_id, :first_name, :last_name, :other_names, :dob, :gender, :phone_number, :address, :city, :state, :lga, :nin, 'pending')";
        }

        if ($stmt_save = $pdo->prepare($sql_save)) {
            // Bind parameters
            $stmt_save->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt_save->bindParam(":first_name", $first_name, PDO::PARAM_STR);
            $stmt_save->bindParam(":last_name", $last_name, PDO::PARAM_STR);
            $stmt_save->bindParam(":other_names", $other_names, PDO::PARAM_STR);
            $stmt_save->bindParam(":dob", $dob, PDO::PARAM_STR);
            $stmt_save->bindParam(":gender", $gender, PDO::PARAM_STR);
            $stmt_save->bindParam(":phone_number", $phone_number, PDO::PARAM_STR);
            // NEW: Bind parameters for new fields
            $stmt_save->bindParam(":address", $address, PDO::PARAM_STR);
            $stmt_save->bindParam(":city", $city, PDO::PARAM_STR);
            $stmt_save->bindParam(":state", $state, PDO::PARAM_STR);
            $stmt_save->bindParam(":lga", $lga, PDO::PARAM_STR);
            $stmt_save->bindParam(":nin", $nin, PDO::PARAM_STR);

            // Attempt to execute the prepared statement
            if ($stmt_save->execute()) {
                $success_message = "Your profile has been saved successfully!";
                // After successful save, re-fetch the status to update the display if it was new insert
                if (empty($application_status)) {
                    $application_status = 'pending';
                }
            } else {
                $error_message = "Error: Could not save your profile. Please try again later.";
            }
            unset($stmt_save);
        }
    } else {
        $error_message = "Please correct the errors in the form.";
    }
}
unset($pdo); // Close connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Police Recruitment Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <header class="bg-blue-600 p-4 text-white shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">My Profile</h1>
            <nav>
                <a href="dashboard.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">Dashboard</a>
                <a href="profile.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">My Profile</a>
                <a href="education.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">Education</a> <a href="documents.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">Documents</a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded ml-4 transition duration-300">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto mt-8 p-6 bg-white rounded-lg shadow-lg">
        <h2 class="text-3xl font-semibold text-gray-800 mb-6">Complete Your Personal Profile</h2>

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

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="first_name" class="block text-gray-700 text-sm font-bold mb-2">First Name <span class="text-red-500">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>"
                           class="shadow appearance-none border <?php echo (!empty($first_name_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="Enter your first name" required>
                    <span class="text-red-500 text-xs italic"><?php echo $first_name_err; ?></span>
                </div>

                <div>
                    <label for="last_name" class="block text-gray-700 text-sm font-bold mb-2">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>"
                           class="shadow appearance-none border <?php echo (!empty($last_name_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="Enter your last name" required>
                    <span class="text-red-500 text-xs italic"><?php echo $last_name_err; ?></span>
                </div>

                <div class="md:col-span-2">
                    <label for="other_names" class="block text-gray-700 text-sm font-bold mb-2">Other Names (Optional)</label>
                    <input type="text" id="other_names" name="other_names" value="<?php echo htmlspecialchars($other_names); ?>"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="Enter your other names">
                </div>

                <div>
                    <label for="dob" class="block text-gray-700 text-sm font-bold mb-2">Date of Birth <span class="text-red-500">*</span></label>
                    <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($dob); ?>"
                           class="shadow appearance-none border <?php echo (!empty($dob_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           required>
                    <span class="text-red-500 text-xs italic"><?php echo $dob_err; ?></span>
                </div>

                <div>
                    <label for="gender" class="block text-gray-700 text-sm font-bold mb-2">Gender <span class="text-red-500">*</span></label>
                    <select id="gender" name="gender"
                            class="shadow appearance-none border <?php echo (!empty($gender_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo ($gender == 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($gender == 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($gender == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                    <span class="text-red-500 text-xs italic"><?php echo $gender_err; ?></span>
                </div>

                <div class="md:col-span-2">
                    <label for="phone_number" class="block text-gray-700 text-sm font-bold mb-2">Phone Number (e.g., 08012345678)</label>
                    <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>"
                           class="shadow appearance-none border <?php echo (!empty($phone_number_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="Enter your phone number">
                    <span class="text-red-500 text-xs italic"><?php echo $phone_number_err; ?></span>
                </div>

                <div class="md:col-span-2">
                    <label for="address" class="block text-gray-700 text-sm font-bold mb-2">Residential Address <span class="text-red-500">*</span></label>
                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($address); ?>"
                           class="shadow appearance-none border <?php echo (!empty($address_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="Enter your full residential address" required>
                    <span class="text-red-500 text-xs italic"><?php echo $address_err; ?></span>
                </div>

                <div>
                    <label for="city" class="block text-gray-700 text-sm font-bold mb-2">City <span class="text-red-500">*</span></label>
                    <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>"
                           class="shadow appearance-none border <?php echo (!empty($city_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="Enter your city" required>
                    <span class="text-red-500 text-xs italic"><?php echo $city_err; ?></span>
                </div>

                <div>
                    <label for="state" class="block text-gray-700 text-sm font-bold mb-2">State <span class="text-red-500">*</span></label>
                    <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($state); ?>"
                           class="shadow appearance-none border <?php echo (!empty($state_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="Enter your state" required>
                    <span class="text-red-500 text-xs italic"><?php echo $state_err; ?></span>
                </div>

                <div>
                    <label for="lga" class="block text-gray-700 text-sm font-bold mb-2">LGA (Local Government Area) <span class="text-red-500">*</span></label>
                    <input type="text" id="lga" name="lga" value="<?php echo htmlspecialchars($lga); ?>"
                           class="shadow appearance-none border <?php echo (!empty($lga_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="Enter your LGA" required>
                    <span class="text-red-500 text-xs italic"><?php echo $lga_err; ?></span>
                </div>

                <div>
                    <label for="nin" class="block text-gray-700 text-sm font-bold mb-2">National Identification Number (NIN) <span class="text-red-500">*</span></label>
                    <input type="text" id="nin" name="nin" value="<?php echo htmlspecialchars($nin); ?>"
                           class="shadow appearance-none border <?php echo (!empty($nin_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           placeholder="Enter your 11-digit NIN" maxlength="11" required>
                    <span class="text-red-500 text-xs italic"><?php echo $nin_err; ?></span>
                </div>
                </div>

            <div class="flex items-center justify-center">
                <button type="submit"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-full focus:outline-none focus:shadow-outline text-lg transition duration-300">
                    Save Profile
                </button>
            </div>
        </form>
    </main>

    <footer class="bg-gray-800 text-white p-4 mt-12 text-center">
        <div class="container mx-auto">
            &copy; <?php echo date("Y"); ?> Police Recruitment Portal. All rights reserved.
        </div>
    </footer>

</body>
</html>