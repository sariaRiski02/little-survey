<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') — {{ $project->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    // TODO(backend): menu aktif & mode datang dari sesi/pengaturan sungguhan.

    $menu = [
        ['dashboard', 'Dashboard', 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
        ['kandidat', 'Kandidat', 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 20a8 8 0 0116 0'],
        ['dpt', 'DPT', 'M4 6h16M4 12h16M4 18h16'],
        ['statistik', 'Statistik', 'M5 20V10M12 20V4M19 20v-7'],
        ['pertanyaan', 'Pertanyaan', 'M9 9a3 3 0 115 2c-1 1-2 1.5-2 3M12 18h.01'],
        ['responden', 'Responden', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8z'],
        ['jawaban', 'Jawaban', 'M9 12h6M9 16h6M7 3h7l5 5v13H7zM14 3v5h5'],
        ['pengaturan', 'Pengaturan', 'M12 15a3 3 0 100-6 3 3 0 000 6zM19 12h2M3 12h2M12 3v2M12 19v2'],
    ];


    if (! $project->isFullDptMode()) {
        $menu = array_values(array_filter($menu, fn ($item) => $item[0] !== 'dpt'));
    }
@endphp
<body class="bg-slate-50 font-sans text-ink antialiased" x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false">

<div class="flex min-h-screen">
    <div x-cloak x-show="sidebar" x-transition.opacity @click="sidebar = false" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"></div>

    <aside id="sidebar"
           class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white p-5 overflow-y-auto transition-transform duration-200 lg:translate-x-0"
           :class="{ 'translate-x-0': sidebar }" aria-label="Menu utama">
        <div class="mb-8 flex items-start justify-between gap-2">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-accent font-extrabold text-white" aria-hidden="true">{{ mb_strtoupper(mb_substr($project->name, 0, 1)) }}</span>
                <div class="min-w-0">
                    <p class="text-sm font-bold leading-tight">{{ $project->name }}</p>
                    <a href="{{ route('proyek.index') }}" class="mt-1 inline-flex min-h-11 items-center text-xs font-bold text-accent underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent lg:min-h-0 lg:py-1">Ganti proyek</a>
                </div>
            </div>
            <button type="button" @click="sidebar = false" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-accent lg:hidden" aria-label="Tutup menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <nav class="flex flex-col gap-1">
            @foreach ($menu as [$route, $label, $icon])
                @php $aktif = request()->routeIs($route); @endphp
                <a href="{{ route($route) }}" @if($aktif) aria-current="page" @endif
                   class="flex min-h-11 items-center gap-3 rounded-2xl px-4 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent {{ $aktif ? 'bg-accent text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                    {{ $label }}
                </a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="mt-auto pt-6">
            @csrf
            <button type="submit" class="flex min-h-11 w-full items-center gap-3 rounded-2xl px-4 text-sm font-semibold text-slate-600 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                Keluar
            </button>
        </form>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col lg:pl-72">
        <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 lg:hidden">
            <button type="button" @click="sidebar = true" class="flex h-11 w-11 items-center justify-center rounded-xl hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-accent" aria-label="Buka menu" aria-controls="sidebar" :aria-expanded="sidebar">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span class="font-bold">{{ $project->name }}</span>
        </header>

        <main id="konten" class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-8 sm:py-12">
            @yield('content')
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
