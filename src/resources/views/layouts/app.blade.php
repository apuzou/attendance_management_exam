<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'CT_勤怠管理')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/message.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components/title.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components/container.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components/table.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components/link.css') }}">
    @stack('styles')
</head>
<body>
    <header class="header">
        @auth
            <div class="header__inner">
                <h1 class="header__logo">
                    <img src="{{ asset('storage/logo.svg') }}" alt="COACHTECH" class="header__logo-image">
                </h1>
                <nav class="header__nav">
                    <a href="{{ route('attendance.index') }}" class="header__nav-link">勤怠</a>
                    <a href="{{ route('attendance.list') }}" class="header__nav-link">勤怠一覧</a>
                    <a href="{{ route('correction.index') }}" class="header__nav-link">申請</a>
                    <form method="POST" action="{{ route('logout') }}" class="header__nav-form">
                        @csrf
                        <button type="submit" class="header__nav-link header__nav-link--button">ログアウト</button>
                    </form>
                </nav>
            </div>
        @else
            <h1 class="header__logo">
                <img src="{{ asset('storage/logo.svg') }}" alt="COACHTECH" class="header__logo-image">
            </h1>
        @endauth
    </header>
    @if (session('success'))
        <div class="success-message">
            {{ session('success') }}
        </div>
    @endif

    <main class="main">
        @yield('content')
    </main>
</body>
</html>

