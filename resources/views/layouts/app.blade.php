<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title')</title>


    <!-- styles -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="{{asset('/css/app.css')}}" rel="stylesheet">

    <!-- scripts -->
    <script src="/js/app.js" defer></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>

</head>
@yield('content')
<body>

</body>
<script>
    var csrf_token = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
</script>
@yield('after_scripts')

</html>
