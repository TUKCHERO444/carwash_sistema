<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background-light dark:bg-background-dark flex items-center justify-center transition-colors duration-300">

    <main class="w-full max-w-md px-6 py-8">
        @yield('content')
    </main>

</body>
</html>
