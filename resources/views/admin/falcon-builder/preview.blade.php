<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: {{ $post->title }}</title>
</head>
<body>
    {!! apply_falcon_filters('falcon_the_content', $post->content) !!}
</body>
</html>
