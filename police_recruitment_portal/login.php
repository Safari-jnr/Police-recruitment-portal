<?php
// Start the session
session_start();

// Check if the user is already logged in, if yes then redirect to dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    // Redirect based on role
    if (isset($_SESSION["role"]) && $_SESSION["role"] === 'admin') {
        header("location: admin/dashboard.php"); // Create this later
    } else {
        header("location: dashboard.php");
    }
    exit;
}

// Include database connection file
require_once 'includes/config.php';

// Define variables and initialize with empty values
$email = $password = "";
$email_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter email.";
    } else {
        $email = trim($_POST["email"]);
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate credentials
    if (empty($email_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, email, password, role FROM users WHERE email = :email";

        if ($stmt = $pdo->prepare($sql)) {
            // Bind parameters
            $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);

            // Set parameters
            $param_email = $email;

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Check if email exists, if yes then verify password
                if ($stmt->rowCount() == 1) {
                    if ($row = $stmt->fetch()) {
                        $id = $row["id"];
                        $email = $row["email"];
                        $hashed_password = $row["password"];
                        $role = $row["role"];

                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, start a new session
                            session_start();

                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["email"] = $email;
                            $_SESSION["role"] = $role;

                            // Redirect user to appropriate dashboard based on role
                            if ($role === 'admin') {
                                header("location: admin/dashboard.php"); // Will create this later
                            } else {
                                header("location: dashboard.php"); // Will create this next
                            }
                            exit();
                        } else {
                            // Password is not valid
                            $login_err = "Invalid email or password.";
                        }
                    }
                } else {
                    // Email doesn't exist
                    $login_err = "Invalid email or password.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            unset($stmt);
        }
    }

    // Close connection
    unset($pdo);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Police Recruitment Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal flex items-center justify-center min-h-screen">

    <div class="w-full max-w-md bg-white rounded-lg shadow-lg p-8">
        <h2 class="text-3xl font-semibold text-center text-gray-800 mb-6">Login</h2>
        <p class="text-gray-600 text-center mb-6">Please fill in your credentials to login.</p>

        <?php
        // Display success message from registration
        if (isset($_SESSION['success_message'])) {
            echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">';
            echo '<strong class="font-bold">Success!</strong>';
            echo '<span class="block sm:inline"> ' . $_SESSION['success_message'] . '</span>';
            echo '</div>';
            unset($_SESSION['success_message']); // Clear the message after displaying
        }
        // Display login error message
        if (!empty($login_err)) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">';
            echo '<strong class="font-bold">Error!</strong>';
            echo '<span class="block sm:inline"> ' . $login_err . '</span>';
            echo '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>"
                       class="shadow appearance-none border <?php echo (!empty($email_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       placeholder="Enter your email" required>
                <span class="text-red-500 text-xs italic"><?php echo $email_err; ?></span>
            </div>

            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" id="password" name="password"
                       class="shadow appearance-none border <?php echo (!empty($password_err)) ? 'border-red-500' : ''; ?> rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline"
                       placeholder="Enter your password" required>
                <span class="text-red-500 text-xs italic"><?php echo $password_err; ?></span>
            </div>

            <div class="flex items-center justify-between">
                <button type="submit"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                    Login
                </button>
            </div>
        </form>
        <p class="text-center text-gray-600 text-sm mt-6">
            Don't have an account? <a href="register.php" class="text-blue-500 hover:text-blue-800">Register now</a>.
        </p>
    </div>

</body>
</html>