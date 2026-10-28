@extends('layouts.admin')
@section('title', 'Statistik')
@section('content')
@php
    $lengkap = $mode === 'dpt_lengkap';
    $n = fn ($v) => number_format($v, 0, ',', '.');
    $btnGhost = 'min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $btnPrimary = 'min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
@endphp

<div x-data="{ diundi: {{ $sudahDiundi ? 'true' : 'false' }}, ulang: false, alasan: '', paham: false }">
    <x-page-header title="Statistik" subtitle="Hitung ukuran sampel{{ $lengkap ? ' dan undi responden' : '' }}." />

    {{-- 5. Catatan mode responden_saja --}}
    @unless ($lengkap)
        <div class="mb-8 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900" role="note">
            Halaman ini berfungsi sebagai <strong>kalkulator ukuran sampel</strong>. Pengundian dan alokasi otomatis{{ $pakaiWilayah ? ' per '.$wilayahLabel : '' }} tidak tersedia; calon responden diimpor di halaman Responden.
        </div>
    @endunless

    {{-- 1. Form parameter --}}
    <section aria-labelledby="h-param" class="mb-8 rounded-2xl border border-slate-200 bg-white p-6">
        <h2 id="h-param" class="text-lg font-extrabold">Parameter</h2>
        {{-- TODO(backend): hitung ulang ukuran sampel di server saat parameter dikirim; validasi rentang nilai. --}}
        <form action="#" method="post" @submit.prevent class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @if ($lengkap)
                <x-field label="Populasi (N)" name="populasi" :value="$param['populasi']" readonly class="bg-slate-100" help="Terisi otomatis dari jumlah DPT." />
            @else
                <x-field label="Populasi (N)" name="populasi" type="number" min="1" :value="$param['populasi']" help="Perkiraan jumlah populasi. Isi manual." />
            @endif
            <div class="space-y-1.5">
                <label for="kepercayaan" class="block text-sm font-semibold">Tingkat kepercayaan</label>
                <select id="kepercayaan" class="min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                    @foreach ([90, 95, 99] as $k)<option value="{{ $k }}" @selected($k === $param['kepercayaan'])>{{ $k }}%</option>@endforeach
                </select>
                <p class="text-xs text-slate-600">Bawaan 95%.</p>
            </div>
            <x-field label="Margin of error (%)" name="galat" type="number" step="0.1" :value="$param['galat']" help="Bawaan 5%." />
            <x-field label="Proporsi (p)" name="p" type="text" inputmode="decimal" :value="$param['p']" help="Bawaan 0,5 (paling hati-hati)." />
            <x-field label="Cadangan non-respons (%)" name="cadangan" type="number" :value="$param['cadangan']" help="Bawaan 20%." />
            <div class="flex items-end"><button type="submit" class="{{ $btnPrimary }} w-full">Hitung</button></div>
        </form>
    </section>

    {{-- 2. Hasil hitung --}}
    <section aria-labelledby="h-hasil" class="mb-8 rounded-2xl border border-slate-200 bg-white p-6">
        <h2 id="h-hasil" class="text-lg font-extrabold">Hasil hitung</h2>
        <p class="mt-1 text-sm text-slate-600">Angka di bawah hanya contoh statis.</p>
        <ol class="mt-5 space-y-3">
            @foreach ($langkah as $i => $l)
                <li class="flex gap-4 rounded-2xl bg-slate-50 p-4">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-extrabold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold">{{ $l['judul'] }}</p>
                        <p class="break-words text-sm text-slate-600">{{ $l['rumus'] }}</p>
                    </div>
                    <p class="text-lg font-extrabold">{{ $l['hasil'] }}</p>
                </li>
            @endforeach
        </ol>
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <x-stat-card label="Ukuran sampel" :value="$n($ukuranSampel)" hint="responden yang dibutuhkan" />
            <x-stat-card label="Jumlah undangan" :value="$n($jumlahUndangan)" hint="termasuk cadangan non-respons" />
        </div>
    </section>

    @if ($lengkap)
        {{-- 3. Alokasi --}}
        @if (! $pakaiWilayah)
            <div class="mb-8 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900" role="note">
                Pengundian acak sederhana dari seluruh daftar.
            </div>
        @else
        <section aria-labelledby="h-alokasi" class="mb-8 rounded-2xl border border-slate-200 bg-white p-6">
            <h2 id="h-alokasi" class="text-lg font-extrabold">Alokasi per {{ $wilayahLabel }}</h2>
            @if (count($alokasi))
                <div class="mt-5 max-h-[32rem] overflow-auto rounded-2xl border border-slate-200" tabindex="0" aria-label="Tabel alokasi per {{ $wilayahLabel }}">
                    <table class="w-full min-w-[34rem] text-left text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-slate-600">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-bold">{{ $wilayahLabel }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-bold">Jumlah DPT</th>
                                <th scope="col" class="px-4 py-3 text-right font-bold">Persentase</th>
                                <th scope="col" class="px-4 py-3 text-right font-bold">Kuota sampel</th>
                                <th scope="col" class="px-4 py-3 text-right font-bold">Kuota undangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($alokasi as $a)
                                <tr>
                                    <th scope="row" class="px-4 py-3 font-semibold">{{ $a['nama'] }}</th>
                                    <td class="px-4 py-3 text-right">{{ $n($a['dpt']) }}</td>
                                    <td class="px-4 py-3 text-right">{{ $a['persen'] }}%</td>
                                    <td class="px-4 py-3 text-right">{{ $a['sampel'] }}</td>
                                    <td class="px-4 py-3 text-right">{{ $a['undangan'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50 font-extrabold">
                            <tr>
                                <th scope="row" class="px-4 py-3">Total</th>
                                <td class="px-4 py-3 text-right">{{ $n($totalAlokasi['dpt']) }}</td>
                                <td class="px-4 py-3 text-right">{{ $totalAlokasi['persen'] }}%</td>
                                <td class="px-4 py-3 text-right">{{ $totalAlokasi['sampel'] }}</td>
                                <td class="px-4 py-3 text-right">{{ $totalAlokasi['undangan'] }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="mt-3 text-xs text-slate-600">Kuota undangan dibulatkan ke atas per {{ $wilayahLabel }}, sehingga totalnya bisa sedikit lebih besar dari jumlah undangan di atas.</p>
            @else
                <x-empty-state class="mt-5" :title="'Belum ada data '.$wilayahLabel" text="Impor DPT terlebih dahulu." />
            @endif
        </section>
        @endif

        {{-- 4. Pengundian --}}
        <section aria-labelledby="h-undi" class="rounded-2xl border border-slate-200 bg-white p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="h-undi" class="text-lg font-extrabold">Pengundian</h2>
                {{-- Hanya pratinjau keadaan; hapus saat backend tersedia. --}}
                <button type="button" @click="diundi = !diundi" class="{{ $btnGhost }}">Pratinjau: <span x-text="diundi ? 'Sudah diundi' : 'Belum diundi'"></span></button>
            </div>
            {{-- TODO(backend): undi acak per {{ $wilayahLabel }} memakai seed yang disimpan; kunci DPT & kandidat; undi ulang wajib alasan dan tercatat. --}}
            <div x-show="!diundi" class="mt-5 space-y-4">
                <p class="text-slate-600">Undi acak memilih responden dari DPT{{ $pakaiWilayah ? ' sesuai kuota tiap '.$wilayahLabel : ' secara acak sederhana dari seluruh daftar' }}. Setelah diundi, DPT dikunci.</p>
                <button type="button" @click="diundi = true" class="{{ $btnPrimary }}">Undi acak</button>
            </div>
            <div x-cloak x-show="diundi" class="mt-5 space-y-4">
                <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
                    <strong>Pengundian terkunci.</strong> DPT tidak dapat diubah. Undi ulang membutuhkan konfirmasi dan alasan, dan akan dicatat.
                </div>
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-xs text-slate-600">Nilai seed</dt><dd class="font-mono text-lg font-extrabold">{{ $seed }}</dd></div>
                    <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-xs text-slate-600">Waktu undi</dt><dd class="font-bold">{{ $waktuUndi }}</dd></div>
                </dl>
                <button type="button" @click="ulang = true" class="min-h-11 rounded-2xl border border-rose-300 px-4 text-sm font-bold text-rose-700 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600">Undi ulang…</button>
            </div>
        </section>

        <x-modal show="ulang" title="Undi ulang responden?">
            Undi ulang akan mengganti daftar responden saat ini.
            <div class="mt-4 space-y-4">
                <div class="space-y-1.5">
                    <label for="alasan" class="block text-sm font-semibold text-ink">Alasan undi ulang (wajib)</label>
                    <textarea id="alasan" rows="3" x-model="alasan" class="w-full rounded-2xl border border-slate-300 bg-white p-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"></textarea>
                </div>
                <label class="flex min-h-11 items-center gap-3 text-sm text-ink">
                    <input type="checkbox" x-model="paham" class="h-5 w-5 rounded border-slate-300 accent-accent focus-visible:outline-2 focus-visible:outline-accent">
                    Saya paham daftar responden lama akan diganti.
                </label>
            </div>
            <x-slot:footer>
                <button type="button" @click="ulang = false" class="{{ $btnGhost }}">Batal</button>
                <button type="button" :disabled="alasan.trim() === '' || !paham" @click="ulang = false; alasan = ''; paham = false" class="min-h-11 rounded-2xl bg-rose-700 px-4 font-bold text-white hover:bg-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700 disabled:cursor-not-allowed disabled:opacity-50">Undi ulang</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
@endsection
