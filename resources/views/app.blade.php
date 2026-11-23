<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@inertiaHead</title>
    @inertiaHead
    <link href="/courier-v1.0.0/build/assets/css/theme.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <!-- Default free map: MapLibre GL JS (no API key required) -->
    <link href="https://unpkg.com/maplibre-gl@2.4.0/dist/maplibre-gl.css" rel="stylesheet" />
    <script src="https://unpkg.com/maplibre-gl@2.4.0/dist/maplibre-gl.js"></script>
    @if(config('alabiq.mapbox.token'))
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.8.0/mapbox-gl.css" rel="stylesheet" />
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.8.0/mapbox-gl.js"></script>
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
</head>
<body>
    @inertia
</body>
</html>