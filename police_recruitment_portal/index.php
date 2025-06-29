<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Police Recruitment Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <header class="bg-blue-600 p-4 text-white shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Police Recruitment Portal</h1>
            <nav>
                <a href="index.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">Home</a>
                <a href="register.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">Register</a>
                <a href="login.php" class="px-3 py-2 rounded hover:bg-blue-700 transition duration-300">Login</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto mt-8 p-6 bg-white rounded-lg shadow-lg">
        <h2 class="text-3xl font-semibold text-center text-gray-800 mb-6">Welcome to the Online Police Recruitment Portal</h2>
        <p class="text-gray-600 text-lg text-center mb-4">
            Your gateway to a career in law enforcement. Register today to start your application process.
        </p>
        <div class="text-center mt-8">
            <a href="register.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-full text-lg transition duration-300">
                Start Your Application
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