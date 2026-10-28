@extends('layouts.admin')
@section('title', 'DPT')
@section('content')
@php
    $n = fn ($v) => number_format($v, 0, ',', '.');
    $selectCls = 'min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
    $btnGhost = 'min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $btnPrimary = 'min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
@endphp

<div x-data="{ terkunci: {{ $terkunci ? 'true' : 'false' }}, tab: 'impor', hapus: false, hapusNama: '', ubah: false, edit: { nama: '', lahir: '', wilayah: '' }, file: false }">
    <x-page-header title="DPT" subtitle="Daftar Pemilih Tetap sebagai dasar undian responden.">
        <x-slot:actions>
            {{-- Hanya pratinjau keadaan; hapus saat backend tersedia. --}}
            <button type="button" @click="terkunci = !terkunci" class="{{ $btnGhost }}">Pratinjau: <span x-text="terkunci ? 'Terkunci' : 'Terbuka'"></span></button>
        </x-slot:actions>
    </x-page-header>

    {{-- 5. Banner penguncian --}}
    {{-- TODO(backend): DPT dikunci setelah sampel diundi di halaman Statistik. --}}
    <div x-show="!terkunci" class="mb-8 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900" role="status">
        <strong>DPT masih terbuka.</strong> Data dikunci setelah sampel diundi.
    </div>
    <div x-cloak x-show="terkunci" class="mb-8 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
        <strong>DPT terkunci.</strong> Sampel sudah diundi, sehingga impor, penambahan, perubahan, dan penghapusan data dinonaktifkan.
    </div>

    {{-- 1. Ringkasan --}}
    <section aria-labelledby="h-ringkasan" class="mb-10">
        <h2 id="h-ringkasan" class="sr-only">Ringkasan</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <x-stat-card class="col-span-2 sm:col-span-3 lg:col-span-1" label="Total DPT" :value="$n($totalDpt)" />
            @if ($pakaiWilayah)
                <div class="col-span-2 grid max-h-72 grid-cols-2 gap-4 overflow-y-auto sm:col-span-3 sm:grid-cols-3 lg:col-span-3" tabindex="0" aria-label="DPT per {{ $wilayahLabel }}">
                    @foreach ($dptPerWilayah as $w)
                        <x-stat-card :label="$w['nama']" :value="$n($w['jumlah'])" />
                    @endforeach
                </div>
            @endif
        </div>
        @if ($pakaiWilayah && !count($dptPerWilayah))
            <x-empty-state class="mt-4" :title="'Belum ada data per '.$wilayahLabel" />
        @endif
    </section>

    {{-- 2 & 3. Impor dan form manual --}}
    <section class="mb-10 rounded-2xl border border-slate-200 bg-white p-6" aria-labelledby="h-tambah">
        <h2 id="h-tambah" class="text-lg font-extrabold">Tambah data DPT</h2>
        <div class="mt-4 inline-flex rounded-2xl bg-slate-100 p-1" role="tablist">
            <button type="button" role="tab" :aria-selected="tab === 'impor'" @click="tab = 'impor'" :class="tab === 'impor' ? 'bg-white shadow-sm' : ''" class="min-h-11 rounded-xl px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-accent">Impor Excel</button>
            <button type="button" role="tab" :aria-selected="tab === 'manual'" @click="tab = 'manual'" :class="tab === 'manual' ? 'bg-white shadow-sm' : ''" class="min-h-11 rounded-xl px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-accent">Input manual</button>
        </div>

        {{-- Impor --}}
        <div x-show="tab === 'impor'" role="tabpanel" class="mt-6 space-y-8">
            {{-- TODO(backend): unggah & baca berkas Excel, buat template unduhan, simpan hasil impor. --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-slate-600">Langkah 1 — unggah berkas Excel (.xlsx).</p>
                <a href="#" @click.prevent class="{{ $btnGhost }} inline-flex items-center" :class="terkunci ? 'pointer-events-none opacity-50' : ''">Unduh template</a>
            </div>
            <label class="flex min-h-40 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-6 text-center focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent" :class="terkunci ? 'cursor-not-allowed opacity-50' : 'hover:border-accent'">
                <span class="font-bold">Seret berkas ke sini atau klik untuk memilih</span>
                <span class="mt-1 text-sm text-slate-600">Format .xlsx, maksimal 5 MB</span>
                <input type="file" accept=".xlsx" class="sr-only" :disabled="terkunci" @change="file = $event.target.files.length > 0">
                <span x-cloak x-show="file" class="mt-3 text-sm font-semibold text-accent">Berkas dipilih (contoh tampilan)</span>
            </label>

            <div>
                <p class="mb-3 font-bold">Langkah 2 — pemetaan kolom</p>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($kolomExcel as $i => $kol)
                        <div class="space-y-1.5">
                            <label for="peta-{{ $i }}" class="block text-sm font-semibold">Kolom "{{ $kol }}"</label>
                            <select id="peta-{{ $i }}" :disabled="terkunci" class="{{ $selectCls }}">
                                @foreach ($bidangTujuan as $j => $b)
                                    <option @selected($j === $i)>{{ $b }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="mb-3 font-bold">Langkah 3 — pratinjau beberapa baris</p>
                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="w-full min-w-[32rem] text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600"><tr>@foreach (array_filter(['Nama', 'NIK', 'Tanggal lahir', $pakaiWilayah ? $wilayahLabel : null]) as $h)<th scope="col" class="px-4 py-3 font-bold">{{ $h }}</th>@endforeach</tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($pratinjau as $r)
                                <tr>@foreach ($r as $c)<td class="px-4 py-3">{{ $c }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <p class="mb-3 font-bold">Laporan baris bermasalah ({{ count($masalah) }})</p>
                @if (count($masalah))
                    <ul class="space-y-2">
                        @foreach ($masalah as $m)
                            <li class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 p-3 text-sm">
                                <x-badge :tone="$m['tone']">Baris {{ $m['baris'] }}</x-badge>
                                <span class="font-semibold">{{ $m['nama'] }}</span>
                                <span class="text-slate-600">{{ $m['alasan'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-empty-state title="Tidak ada baris bermasalah" />
                @endif
            </div>
            <button type="button" :disabled="terkunci" class="{{ $btnPrimary }}">Impor baris yang valid</button>
        </div>

        {{-- Manual --}}
        <form x-cloak x-show="tab === 'manual'" role="tabpanel" action="#" method="post" @submit.prevent class="mt-6">
            {{-- TODO(backend): simpan DPT; validasi NIK 16 digit, unik, tanggal lahir cocok NIK, wilayah valid. --}}
            <fieldset :disabled="terkunci" class="grid gap-5 sm:grid-cols-2 disabled:opacity-50">
                <x-field label="Nama" name="dpt-nama" required />
                <x-field label="NIK" name="dpt-nik" inputmode="numeric" maxlength="16" required help="16 digit." />
                <x-field label="Tanggal lahir" name="dpt-lahir" type="date" required />
                @if ($pakaiWilayah)
                    <div class="space-y-1.5">
                        <label for="dpt-wilayah" class="block text-sm font-semibold">{{ $wilayahLabel }}</label>
                        <select id="dpt-wilayah" required class="{{ $selectCls }}">
                            <option value="">Pilih {{ $wilayahLabel }}</option>
                            @foreach ($daftarWilayah as $w)<option>{{ $w }}</option>@endforeach
                        </select>
                    </div>
                @endif
                <x-field label="Nomor WhatsApp" name="dpt-wa" type="tel" help="Opsional." />
                <div class="space-y-1.5">
                    <label for="dpt-jk" class="block text-sm font-semibold">Jenis kelamin</label>
                    <select id="dpt-jk" class="{{ $selectCls }}"><option value="">Tidak diisi</option><option>Laki-laki</option><option>Perempuan</option></select>
                    <p class="text-xs text-slate-600">Opsional.</p>
                </div>
                <div class="sm:col-span-2"><button type="submit" class="{{ $btnPrimary }}">Simpan data</button></div>
            </fieldset>
        </form>
    </section>

    {{-- 4. Tabel --}}
    <section aria-labelledby="h-tabel" class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 id="h-tabel" class="text-lg font-extrabold">Data DPT</h2>
        {{-- TODO(backend): pencarian, filter, dan pagination dilakukan server-side. --}}
        <form action="#" @submit.prevent class="mt-4 grid gap-3 {{ $pakaiWilayah ? 'sm:grid-cols-3' : '' }}">
            <div class="{{ $pakaiWilayah ? 'sm:col-span-2' : '' }}">
                <label for="cari" class="sr-only">Cari nama atau NIK</label>
                <input id="cari" type="search" placeholder="Cari nama atau NIK" class="{{ $selectCls }}">
            </div>
            @if ($pakaiWilayah)
                <div>
                    <label for="filter-wilayah" class="sr-only">Filter {{ $wilayahLabel }}</label>
                    <select id="filter-wilayah" class="{{ $selectCls }}">
                        <option>Semua {{ $wilayahLabel }}</option>
                        @foreach ($daftarWilayah as $w)<option>{{ $w }}</option>@endforeach
                    </select>
                </div>
            @endif
        </form>

        @if (count($baris))
            <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200">
                <table class="w-full min-w-[44rem] text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            @foreach (array_filter(['Nama', 'NIK', 'Tanggal lahir', $pakaiWilayah ? $wilayahLabel : null, 'Responden']) as $h)<th scope="col" class="px-4 py-3 font-bold">{{ $h }}</th>@endforeach
                            <th scope="col" class="px-4 py-3 text-right font-bold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($baris as $r)
                            <tr>
                                <td class="px-4 py-3 font-semibold">{{ $r['nama'] }}</td>
                                <td class="px-4 py-3 font-mono">{{ $r['nik'] }}</td>
                                <td class="px-4 py-3">{{ $r['lahir'] }}</td>
                                @if ($pakaiWilayah)<td class="px-4 py-3">{{ $r['wilayah'] }}</td>@endif
                                <td class="px-4 py-3"><x-badge :tone="$r['terpilih'] ? 'success' : 'neutral'">{{ $r['terpilih'] ? 'Terpilih' : 'Tidak terpilih' }}</x-badge></td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" :disabled="terkunci" @click="edit = { nama: @js($r['nama']), lahir: @js($r['lahir']), wilayah: @js($r['wilayah']) }; ubah = true" class="{{ $btnGhost }}">Ubah</button>
                                        <button type="button" :disabled="terkunci" @click="hapusNama = @js($r['nama']); hapus = true" class="min-h-11 rounded-2xl border border-rose-300 px-4 text-sm font-bold text-rose-700 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 disabled:cursor-not-allowed disabled:opacity-50">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <nav class="mt-5 flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Halaman">
                <p class="text-slate-600">Menampilkan 1–{{ count($baris) }} dari {{ $n($totalBaris) }}</p>
                <div class="flex gap-2">
                    <button type="button" disabled class="{{ $btnGhost }}">Sebelumnya</button>
                    <button type="button" class="{{ $btnGhost }}">Berikutnya</button>
                </div>
            </nav>
        @else
            <x-empty-state class="mt-5" title="Belum ada data DPT" text="Impor berkas Excel atau tambah data secara manual." />
        @endif
    </section>

    {{-- TODO(backend): hapus DPT sungguhan setelah konfirmasi. --}}
    <x-modal show="hapus" title="Hapus data DPT?">
        Data <strong x-text="hapusNama"></strong> akan dihapus dari DPT. Tindakan ini tidak dapat dibatalkan.
        <x-slot:footer>
            <button type="button" @click="hapus = false" class="{{ $btnGhost }}">Batal</button>
            <button type="button" @click="hapus = false" class="min-h-11 rounded-2xl bg-rose-700 px-4 font-bold text-white hover:bg-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700">Ya, hapus</button>
        </x-slot:footer>
    </x-modal>

    {{-- TODO(backend): simpan perubahan DPT dengan validasi. --}}
    <x-modal show="ubah" title="Ubah data DPT">
        <div class="space-y-4">
            <x-field label="Nama" name="ubah-nama" x-model="edit.nama" />
            <x-field label="Tanggal lahir" name="ubah-lahir" x-model="edit.lahir" />
            @if ($pakaiWilayah)<x-field :label="$wilayahLabel" name="ubah-wilayah" x-model="edit.wilayah" />@endif
        </div>
        <x-slot:footer>
            <button type="button" @click="ubah = false" class="{{ $btnGhost }}">Batal</button>
            <button type="button" @click="ubah = false" class="{{ $btnPrimary }}">Simpan</button>
        </x-slot:footer>
    </x-modal>
</div>
@endsection
