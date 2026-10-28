<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Proyek') - little survey</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-ink antialiased">
<a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded-xl focus:bg-white focus:px-4 focus:py-3">Lewati ke konten</a>

<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex min-h-16 max-w-6xl items-center px-4 sm:px-8">
        <a href="{{ route('proyek.index') }}" class="flex min-h-11 items-center gap-3 rounded-2xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-accent font-extrabold text-white" aria-hidden="true">LS</span>
            <span class="font-bold">little survey</span>
        </a>
    </div>
</header>

<main id="konten" class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-8 sm:py-12">
    @yield('content')
</main>
</body>
</html>
