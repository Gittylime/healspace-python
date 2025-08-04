<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HealSpace Therapy</title>
    <!-- Use the Inter font for a clean, modern look -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Include Tailwind CSS via CDN for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex flex-col min-h-screen bg-gray-50 text-gray-800">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg p-4 sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <!-- Logo or Site Name -->
            <a href="index.php" class="text-2xl font-bold text-indigo-600">HealSpace</a>

            <!-- Mobile Menu Button -->
            <button id="mobile-menu-button" class="md:hidden text-gray-600 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                </svg>
            </button>

            <!-- Desktop Menu -->
            <div class="hidden md:flex space-x-6 text-sm font-medium">
                <a href="index.php" class="text-gray-600 hover:text-indigo-600 transition duration-300">Home</a>
                <a href="about.php" class="text-indigo-600 font-bold transition duration-300">About Us</a>
                <a href="book_session.php" class="text-gray-600 hover:text-indigo-600 transition duration-300">Book Session</a>
                <a href="journal.php" class="text-gray-600 hover:text-indigo-600 transition duration-300">Journal</a>
                <a href="resources.php" class="text-gray-600 hover:text-indigo-600 transition duration-300">Resources</a>
                <a href="login.php" class="bg-indigo-600 text-white px-4 py-2 rounded-full hover:bg-indigo-700 transition duration-300 shadow-md">Login</a>
            </div>
        </div>

        <!-- Mobile Menu (hidden by default) -->
        <div id="mobile-menu" class="hidden md:hidden mt-4 space-y-2 text-center text-sm font-medium">
            <a href="index.php" class="block text-gray-600 hover:bg-gray-100 p-2 rounded-lg">Home</a>
            <a href="about.php" class="block text-indigo-600 font-bold bg-gray-100 p-2 rounded-lg">About Us</a>
            <a href="book_session.php" class="block text-gray-600 hover:bg-gray-100 p-2 rounded-lg">Book Session</a>
            <a href="journal.php" class="block text-gray-600 hover:bg-gray-100 p-2 rounded-lg">Journal</a>
            <a href="resources.php" class="block text-gray-600 hover:bg-gray-100 p-2 rounded-lg">Resources</a>
            <a href="login.php" class="block bg-indigo-600 text-white px-4 py-2 rounded-full hover:bg-indigo-700 transition duration-300 mt-2">Login</a>
        </div>
    </nav>
    <script>
        // Toggle mobile menu visibility
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    </script>
