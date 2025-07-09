<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Police Recruitment Portal</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @keyframes fadeUp {
      0% { opacity: 0; transform: translateY(20px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeUp {
      animation: fadeUp 0.8s ease-out both;
    }
  </style>
</head>
<body class="bg-gray-100 font-sans leading-relaxed tracking-wide text-gray-800">

  <!-- Header -->
  <header class="bg-blue-700 p-5 text-white shadow-md">
    <div class="container mx-auto flex justify-between items-center animate-fadeUp">
      <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Police Recruitment Portal</h1>
      <nav class="space-x-3 text-sm md:text-base">
        <a href="index.php" class="hover:underline transition">Home</a>
        <a href="register.php" class="hover:underline transition">Register</a>
        <a href="login.php" class="hover:underline transition">Login</a>
      </nav>
    </div>
  </header>

 <!-- Hero Section with Background -->
<section class="relative bg-cover bg-center h-[500px]" style="background-image: url('images/NPF.jpg');">
  <div class="absolute inset-0 bg-black bg-opacity-60 flex flex-col justify-center items-center text-center px-4">
    <h2 class="text-4xl md:text-5xl font-bold text-white mb-4 animate-fadeUp">Welcome to the Recruitment Portal</h2>
    <p class="text-lg md:text-xl text-gray-200 mb-6 max-w-2xl animate-fadeUp">
      Begin your journey toward a respected and secure career in law enforcement.
    </p>
    <a href="register.php" class="bg-blue-600 hover:bg-blue-800 text-white font-semibold py-3 px-6 rounded-full shadow-md transition animate-fadeUp">
      Start Your Application
    </a>
  </div>
</section>

  <!-- Why Join -->
  <section class="container mx-auto mt-12 p-6 bg-white rounded-xl shadow-md animate-fadeUp">
    <h3 class="text-2xl font-semibold text-blue-700 mb-4">Why Join the Police Force?</h3>
    <ul class="space-y-2 list-disc pl-6 text-gray-700">
      <li>Serve and protect your community with honor</li>
      <li>Earn a steady income and retirement benefits</li>
      <li>Access lifelong training and promotions</li>
      <li>Be part of a proud and respected national institution</li>
    </ul>
  </section>

  <!-- Requirements -->
  <section class="container mx-auto mt-12 p-6 bg-white rounded-xl shadow-md animate-fadeUp">
    <h3 class="text-2xl font-semibold text-blue-700 mb-4">Basic Requirements</h3>
    <ul class="space-y-2 list-disc pl-6 text-gray-700">
      <li>Nigerian citizen by birth</li>
      <li>Between 18 and 35 years of age</li>
      <li>Minimum 5 credits in SSCE (including English & Maths)</li>
      <li>Physically and mentally fit for active duty</li>
    </ul>
  </section>

  <!-- Testimonials -->
  <section class="container mx-auto mt-12 p-6 bg-white rounded-xl shadow-md animate-fadeUp">
    <h3 class="text-2xl font-semibold text-blue-700 mb-6">Testimonials</h3>
    <div class="grid md:grid-cols-2 gap-6">
      <div class="bg-gray-100 p-5 rounded-md shadow-sm hover:shadow-md transition">
        <p class="italic">"Becoming a police officer gave me purpose and the skills to serve. The training was tough, but I am proud today."</p>
        <p class="mt-3 font-semibold text-blue-700">– Inspector Musa A.</p>
      </div>
      <div class="bg-gray-100 p-5 rounded-md shadow-sm hover:shadow-md transition">
        <p class="italic">"Joining the force opened up career opportunities and taught me discipline, courage, and responsibility."</p>
        <p class="mt-3 font-semibold text-blue-700">– Sergeant Ada E.</p>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-gray-900 text-white p-4 mt-16 text-center">
    &copy; <?php echo date("Y"); ?> Police Recruitment Portal. All rights reserved.
  </footer>

</body>
</html>
