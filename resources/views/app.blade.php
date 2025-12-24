<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ config('site.name') }} - Content Aggregator</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div id="app"></div>
<!-- In development, this will load Nuxt from localhost:3000 -->
@if(app()->environment('local'))
    <script>
        window.location.href = "http://localhost:3000/app{{ request()->path() !== 'app' ? '/' . substr(request()->path(), 4) : '' }}";
    </script>
@endif
</body>
</html>
