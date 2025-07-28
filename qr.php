<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
      body {
        font-family: 'Inter', sans-serif;
      }
    </style>
  </head>
  <body class="bg-gradient-to-tr from-blue-900 via-purple-900 to-pink-900 min-h-screen flex items-center justify-center px-4">
    <div class="bg-white rounded-3xl shadow-xl max-w-md w-full p-8 space-y-6">
      <h2 class="text-3xl font-bold text-gray-800 text-center">Welcome Back 👋</h2>
      <p class="text-gray-500 text-center">Login to your account and join the fun!</p>

      <form class="space-y-5">
        <div>
          <label class="block mb-1 text-sm text-gray-600 font-semibold" for="email">Email</label>
          <input
            type="email"
            id="email"
            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-500"
            placeholder="you@example.com"
            required
          />
        </div>
        <div>
          <label class="block mb-1 text-sm text-gray-600 font-semibold" for="password">Password</label>
          <input
            type="password"
            id="password"
            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-500"
            placeholder="••••••••"
            required
          />
        </div>

        <div class="flex items-center justify-between">
          <label class="flex items-center text-sm text-gray-600">
            <input type="checkbox" class="form-checkbox text-pink-600" />
            <span class="ml-2">Remember me</span>
          </label>
          <a href="#" class="text-sm text-pink-600 hover:underline">Forgot Password?</a>
        </div>

        <button
          type="submit"
          class="w-full bg-gradient-to-r from-pink-500 to-purple-600 text-white font-semibold py-3 rounded-xl shadow-md hover:shadow-lg transition-transform hover:-translate-y-0.5"
        >
          Login
        </button>
      </form>

      <p class="text-center text-sm text-gray-600">
        Don’t have an account?
        <a href="#" class="text-pink-500 font-semibold hover:underline">Sign up</a>
      </p>
    </div>
  </body>
</html>
