@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')

@php
    // TODO(backend): selisih, kesimpulan, dan persentase dihitung di server dari data responden sungguhan.
    $skala = 60; // batas kanan sumbu bar (%), hanya untuk tampilan
    $galat = $statistik['galat'];
    $urut = collect($kandidat)->sortByDesc('persen')->values();
    $selisih = $urut[0]['persen'] - ($urut[1]['persen'] ?? 0);
    $unggul = $selisih > $galat;
    $respons = round($ringkasan['sudahMengisi'] / max(1, $ringkasan['responden']) * 100, 1);
    $fmt = fn ($n) => number_format($n, 1, ',', '.');
@endphp

<x-page-header title="Dashboard" subtitle="Ringkasan pelaksanaan survei dan hasil sementara." />

@if ($sampelNonAcak)
    <div role="alert" class="mb-8 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm font-semibold text-amber-900">
        Sampel non-acak: hasil bersifat gambaran, bukan estimasi populasi.
    </div>
@endif

{{--
    {{-- 1. Kartu ringkasan --}}
    <section aria-labelledby="h-ringkasan" class="mb-10">
        <h2 id="h-ringkasan" class="sr-only">Ringkasan</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @if ($mode === 'responden_saja')
                <x-stat-card label="Perkiraan populasi" :value="$ringkasan['populasiPerkiraan'] !== null ? number_format($ringkasan['populasiPerkiraan'], 0, ',', '.') : '-'" hint="Opsional, diisi di Pengaturan" />
            @else
                <x-stat-card label="Jumlah DPT" :value="number_format($ringkasan['dpt'], 0, ',', '.')" />
            @endif
            <x-stat-card label="Jumlah responden" :value="number_format($ringkasan['responden'], 0, ',', '.')" />
            <x-stat-card label="Jumlah kandidat" :value="$ringkasan['kandidat']" />
            <x-stat-card label="Sudah mengisi" :value="number_format($ringkasan['sudahMengisi'], 0, ',', '.')" />
            <x-stat-card label="Belum mengisi" :value="number_format($ringkasan['belumMengisi'], 0, ',', '.')" />
            <x-stat-card label="Tingkat respons" :value="$fmt($respons).'%'" />
        </div>
    </section>

    <div class="grid gap-8 lg:grid-cols-2">
        {{-- 2. Progres wilayah --}}
        <section aria-labelledby="h-progres" class="rounded-2xl border border-slate-200 bg-white p-6">
            @if (! $pakaiWilayah)
                <h2 id="h-progres" class="text-lg font-extrabold">Progres pengisian</h2>
                @php $pTotal = $ringkasan['sudahMengisi'] / max(1, $ringkasan['responden']) * 100; @endphp
                <div class="mt-6">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="font-bold">Total</span>
                        <span class="text-sm font-bold">{{ $fmt($pTotal) }}%</span>
                    </div>
                    <p class="mb-2 text-sm text-slate-600">terisi {{ $ringkasan['sudahMengisi'] }} dari {{ $ringkasan['responden'] }} responden</p>
                    <x-progress-bar :value="$pTotal" label="Progres pengisian total" />
                </div>
            @else
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="h-progres" class="text-lg font-extrabold">Progres {{ $wilayahLabel }}</h2>
                <p class="text-sm text-slate-600">{{ count($wilayah) }} {{ $wilayahLabel }}</p>
            </div>
            @if (count($wilayah))
                <ul class="mt-6 grid max-h-[28rem] gap-x-6 gap-y-5 overflow-y-auto pr-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2" tabindex="0" aria-label="Daftar progres {{ $wilayahLabel }}">
                    @foreach ($wilayah as $w)
                        @php $p = $w['kuota'] ? $w['terisi'] / $w['kuota'] * 100 : 0; @endphp
                        <li>
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="font-bold">{{ $w['nama'] }}</span>
                                <span class="text-sm font-bold">{{ $fmt($p) }}%</span>
                            </div>
                            <p class="mb-2 text-sm text-slate-600">terisi {{ $w['terisi'] }} dari kuota {{ $w['kuota'] }}</p>
                            <x-progress-bar :value="$p" :label="'Progres '.$w['nama']" />
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty-state class="mt-6" :title="'Belum ada '.$wilayahLabel" />
            @endif
            @endif
        </section>

        {{-- 3. Perbandingan kandidat --}}
        <section aria-labelledby="h-kandidat" class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 id="h-kandidat" class="text-lg font-extrabold">Perbandingan kandidat</h2>
            @if (count($kandidat))
                <ul class="mt-6 space-y-6">
                    @foreach ($kandidat as $k)
                        @php
                            $kiri = max(0, $k['persen'] - $galat) / $skala * 100;
                            $lebar = (min($skala, $k['persen'] + $galat) - max(0, $k['persen'] - $galat)) / $skala * 100;
                        @endphp
                        <li>
                            <div class="mb-1 flex items-baseline justify-between gap-3">
                                <span class="flex items-center gap-2 font-bold">
                                    <span class="h-3 w-3 rounded-full" style="background-color: {{ $k['warna'] }}" aria-hidden="true"></span>{{ $k['nama'] }}
                                </span>
                                <span class="text-sm font-bold">{{ $fmt($k['persen']) }}% <span class="font-normal text-slate-600">(±{{ $fmt($galat) }})</span></span>
                            </div>
                            <div class="relative h-6 rounded-full bg-slate-100" role="img" aria-label="{{ $k['nama'] }} {{ $fmt($k['persen']) }} persen, galat plus minus {{ $fmt($galat) }}">
                                <div class="h-full rounded-full" style="width: {{ $k['persen'] / $skala * 100 }}%; background-color: {{ $k['warna'] }}"></div>
                                {{-- garis error: rentang persen ± galat --}}
                                <div class="absolute top-1/2 flex h-3 -translate-y-1/2 items-center" style="left: {{ $kiri }}%; width: {{ $lebar }}%">
                                    <span class="block h-3 w-0.5 bg-ink"></span>
                                    <span class="block h-0.5 flex-1 bg-ink"></span>
                                    <span class="block h-3 w-0.5 bg-ink"></span>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-6 space-y-3 border-t border-slate-200 pt-5">
                    <p class="text-sm text-slate-600">Di luar kandidat:</p>
                    @foreach ($lainnya as $l)
                        <div>
                            <div class="mb-1 flex justify-between text-sm"><span>{{ $l['nama'] }}</span><span class="font-bold">{{ $fmt($l['persen']) }}%</span></div>
                            <x-progress-bar :value="$l['persen'] / $skala * 100" color="#94A3B8" :label="$l['nama']" />
                        </div>
                    @endforeach
                </div>

                <dl class="mt-6 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-2xl bg-slate-50 p-3"><dt class="text-xs text-slate-600">n</dt><dd class="font-extrabold">{{ $statistik['n'] }}</dd></div>
                    <div class="rounded-2xl bg-slate-50 p-3"><dt class="text-xs text-slate-600">Kepercayaan</dt><dd class="font-extrabold">{{ $statistik['kepercayaan'] }}%</dd></div>
                    <div class="rounded-2xl bg-slate-50 p-3"><dt class="text-xs text-slate-600">Selisih teratas</dt><dd class="font-extrabold">{{ $fmt($selisih) }} poin</dd></div>
                </dl>
                <p class="mt-4 rounded-2xl p-4 text-sm font-bold {{ $unggul ? 'bg-teal-50 text-teal-900' : 'bg-amber-50 text-amber-900' }}">
                    {{ $unggul ? $urut[0]['nama'].' unggul' : 'Selisih masih dalam batas galat, belum bisa disimpulkan' }}
                </p>
            @else
                <x-empty-state class="mt-6" title="Belum ada kandidat" />
            @endif
        </section>
    </div>

    {{-- 4. Kualitas data --}}
    <section aria-labelledby="h-kualitas" class="mt-8 rounded-2xl border border-slate-200 bg-white p-6" x-data="{ tertimbang: false }">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 id="h-kualitas" class="text-lg font-extrabold">Kualitas data</h2>
            @if ($pakaiWilayah)
            <div class="inline-flex rounded-2xl bg-slate-100 p-1" role="group" aria-label="Jenis hasil">
                <button type="button" @click="tertimbang = false" :aria-pressed="!tertimbang"
                        :class="!tertimbang ? 'bg-white shadow-sm' : ''"
                        class="min-h-11 rounded-xl px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-accent">Mentah</button>
                <button type="button" @click="tertimbang = true" :aria-pressed="tertimbang"
                        :class="tertimbang ? 'bg-white shadow-sm' : ''"
                        class="min-h-11 rounded-xl px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-accent">Tertimbang</button>
            </div>
            @endif
        </div>

        <div class="mt-6 rounded-2xl bg-slate-50 p-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="text-sm text-slate-600">Margin of error aktual dibanding target</p>
                <p class="font-extrabold">±{{ $fmt($galat) }}% <span class="text-sm font-normal text-slate-600">dari target ±{{ $fmt($statistik['galatTarget']) }}%</span></p>
            </div>
            <x-progress-bar class="mt-3" :value="$galat / $statistik['galatTarget'] * 100" :color="$galat <= $statistik['galatTarget'] ? '#0F6B6B' : '#E11D48'" label="Galat aktual terhadap target" />
            <p class="mt-2"><x-badge :tone="$galat <= $statistik['galatTarget'] ? 'success' : 'danger'">{{ $galat <= $statistik['galatTarget'] ? 'Memenuhi target' : 'Belum memenuhi target' }}</x-badge></p>
        </div>

        @if ($pakaiWilayah)
        <p class="mt-6 text-sm text-slate-600">Suara {{ $urut[0]['nama'] }} per {{ $wilayahLabel }}. Dengan {{ $wilayahLabel }} banyak, angka per {{ $wilayahLabel }} kurang dapat diandalkan.</p>
        {{-- TODO(backend): angka mentah vs tertimbang dihitung server; di sini hanya mengganti angka dummy. --}}
        <ul class="mt-3 grid max-h-72 gap-3 overflow-y-auto pr-2 sm:grid-cols-2 lg:grid-cols-3" tabindex="0" aria-label="Hasil per {{ $wilayahLabel }}">
            @foreach ($wilayah as $w)
                <li class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
                    <span class="font-semibold">{{ $w['nama'] }}</span>
                    @if ($w['mentah'] === null)
                        <span class="text-slate-600">-</span>
                    @else
                        <span class="font-extrabold" x-text="tertimbang ? '{{ $fmt($w['tertimbang']) }}%' : '{{ $fmt($w['mentah']) }}%'">{{ $fmt($w['mentah']) }}%</span>
                    @endif
                </li>
            @endforeach
        </ul>
        @endif
    </section>
--}}
@endsection
