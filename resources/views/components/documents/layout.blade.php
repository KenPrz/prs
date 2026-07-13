<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Document' }}</title>
    <style>
        {!! file_get_contents(resource_path('css/documents.css')) !!}
    </style>
</head>
<body>
    {{ $slot }}
</body>
</html>
