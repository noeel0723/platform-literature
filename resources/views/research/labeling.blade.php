<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="research-token" content="{{ $token }}">
    <title>Ground Truth Labeling — Literahaven Research</title>
    @foreach ($styles as $style)
        <link rel="stylesheet" href="{{ $style }}">
    @endforeach
</head>
<body>
    <div id="research-labeling"></div>
    @foreach ($scripts as $script)
        <script type="module" src="{{ $script }}"></script>
    @endforeach
</body>
</html>
