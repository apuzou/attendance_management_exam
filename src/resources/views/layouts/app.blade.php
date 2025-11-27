<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'CT_勤怠管理')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/message.css') }}">
</head>
<body>
    <header class="header">
        <h1 class="header__logo">
            <img src="{{ asset('storage/logo.svg') }}" alt="COACHTECH" class="header__logo-image">
        </h1>
    </header>
    <main class="main">
        @yield('content')
    </main>
</body>
</html>

