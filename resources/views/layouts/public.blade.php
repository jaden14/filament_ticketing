<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Request Form' }}</title>
    
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
    
    <!-- Filament Styles -->
    @filamentStyles
    
    <!-- Your custom theme -->
    @vite('resources/css/filament/admin/theme.css')
</head>
<body class="antialiased">
    {{ $slot }}
    
    <!-- Livewire Notifications -->
    @livewire('notifications')
    
    <!-- Filament Scripts -->
    @filamentScripts
    
    <!-- Your custom scripts -->
    @vite('resources/js/app.js')
</body>
</html>