<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>little survey - Web survei otomatis</title>
    <meta name="description" content="little survey adalah web survei otomatis: kelola kandidat, DPT, dan responden, bagikan tautan survei, lalu lihat hasil dan analisis statistiknya secara langsung. Didukung oleh Lamda Tech.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-ink antialiased">
<a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded-xl focus:bg-white focus:px-4 focus:py-3">Lewati ke konten</a>

<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex min-h-16 max-w-6xl items-center justify-between px-4 sm:px-8">
        <a href="{{ route('beranda') }}" class="flex min-h-11 items-center gap-3 rounded-2xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-accent font-extrabold text-white" aria-hidden="true">LS</span>
            <span class="font-bold">little survey</span>
        </a>
        <a href="{{ route('proyek.index') }}" class="inline-flex min-h-11 items-center rounded-xl bg-accent px-5 font-semibold text-white hover:bg-accent/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">Masuk ke aplikasi</a>
    </div>
</header>

<main id="konten">
    <section class="bg-slate-50">
        <div class="mx-auto max-w-4xl px-4 py-16 text-center sm:px-8 sm:py-24">
            <p class="inline-flex items-center rounded-full border border-accent/20 bg-white px-4 py-1.5 text-sm font-semibold text-accent">Web survei otomatis</p>
            <h1 class="mt-6 text-balance text-4xl font-extrabold tracking-tight sm:text-5xl">Survei lebih rapi, hasil langsung terbaca.</h1>
            <p class="mx-auto mt-6 max-w-2xl text-pretty text-lg text-slate-600">
                little survey membantu Anda menjalankan survei dari awal sampai akhir: mulai dari menyiapkan kandidat dan data pemilih, membagikan tautan survei, sampai membaca hasil dan analisis statistiknya, tanpa rekap manual.
            </p>
            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ route('proyek.index') }}" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-accent px-8 font-semibold text-white hover:bg-accent/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent sm:w-auto">Mulai survei</a>
                <a href="#fitur" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-8 font-semibold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent sm:w-auto">Lihat fitur</a>
            </div>
        </div>
    </section>

    <section id="fitur" class="mx-auto max-w-6xl scroll-mt-4 px-4 py-16 sm:px-8 sm:py-20" aria-labelledby="judul-fitur">
        <h2 id="judul-fitur" class="text-center text-3xl font-extrabold tracking-tight">Semua yang dibutuhkan untuk survei</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-slate-600">Satu tempat untuk mengelola data, mengumpulkan jawaban, dan menganalisisnya.</p>

        <ul class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['Proyek terpisah', 'Setiap survei berjalan di proyeknya sendiri, lengkap dengan kandidat, pertanyaan, dan pengaturannya.'],
                ['Kandidat & pertanyaan', 'Atur daftar kandidat, urutan tampil, dan pertanyaan survei sesuai kebutuhan.'],
                ['DPT & responden', 'Impor DPT dari berkas, tarik sampel responden secara otomatis, dan ekspor datanya kapan saja.'],
                ['Tautan survei unik', 'Setiap responden mendapat tautan sendiri sehingga mudah dibagikan dan diisi dari ponsel.'],
                ['Dasbor & statistik', 'Pantau progres pengisian dan perolehan suara secara langsung dalam bentuk ringkas.'],
                ['Uji signifikansi', 'Lihat apakah selisih antar kandidat signifikan secara statistik, lengkap dengan penjelasannya.'],
            ] as [$judul, $deskripsi])
                <li class="rounded-2xl border border-slate-200 bg-white p-6">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent/10 text-accent" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                    </span>
                    <h3 class="mt-4 text-lg font-bold">{{ $judul }}</h3>
                    <p class="mt-2 text-slate-600">{{ $deskripsi }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="bg-slate-50" aria-labelledby="judul-cara">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-8 sm:py-20">
            <h2 id="judul-cara" class="text-center text-3xl font-extrabold tracking-tight">Cara kerjanya</h2>
            <ol class="mt-12 grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['Siapkan proyek', 'Buat proyek, isi kandidat, pertanyaan, dan impor data pemilih.'],
                    ['Bagikan tautan', 'Sampel responden ditarik otomatis, lalu bagikan tautan survei ke masing-masing responden.'],
                    ['Baca hasilnya', 'Jawaban masuk dan dirangkum otomatis di dasbor, siap dianalisis atau diekspor.'],
                ] as $i => [$judul, $deskripsi])
                    <li class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-accent font-extrabold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                        <h3 class="mt-4 text-lg font-bold">{{ $judul }}</h3>
                        <p class="mt-2 text-slate-600">{{ $deskripsi }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="mx-auto max-w-4xl px-4 py-16 text-center sm:px-8 sm:py-20" aria-labelledby="judul-cta">
        <h2 id="judul-cta" class="text-3xl font-extrabold tracking-tight">Siap memulai survei pertama Anda?</h2>
        <p class="mx-auto mt-3 max-w-xl text-slate-600">Buat proyek baru dan biarkan sisanya berjalan otomatis.</p>
        <a href="{{ route('proyek.index') }}" class="mt-8 inline-flex min-h-12 items-center justify-center rounded-xl bg-accent px-8 font-semibold text-white hover:bg-accent/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">Masuk ke aplikasi</a>
    </section>
</main>

<footer class="border-t border-slate-200 bg-white">
    <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm text-slate-600 sm:flex-row sm:px-8">
        <p>&copy; {{ date('Y') }} little survey</p>
        <p>
            Didukung oleh
            <a href="https://lamdatech.netlify.app/" target="_blank" rel="noopener" class="font-semibold text-accent underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">Lamda Tech</a>
        </p>
    </div>
</footer>
</body>
</html>
