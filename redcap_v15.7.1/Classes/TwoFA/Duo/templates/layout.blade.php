<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>REDCap</title>
    <link rel="stylesheet" type="text/css" href="{{$APP_PATH_CSS}}style.css" >
    <link rel="stylesheet" type="text/css" href="{{$APP_PATH_WEBPACK}}css/fontawesome/css/all.min.css" >
    <link rel="shortcut icon" href="{{$APP_PATH_IMAGES}}favicon.ico" type="image/x-icon">
</head>
<body>
<style>
body {
    margin: 10px auto;
}
.content {
    padding: 20px;
}
</style>
    <div class="container">
        @section('header')
        {{-- here goes header content --}}
        <div class="header">
            <a href="{{$APP_PATH_WEBROOT_PARENT}}">
                <img src="{{$APP_PATH_IMAGES}}redcap-logo.png" title="REDCap" style="height:45px;">
            </a>
        </div>
        @show

        <div class="content">
            @yield('content')
        </div>
    </div>
</body>
</html>