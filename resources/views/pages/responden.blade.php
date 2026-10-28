@extends('layouts.admin')
@section('title', 'Responden')
@section('content')
@php
    $saja = $mode === 'responden_saja';
    $n = fn ($v) => number_format($v, 0, ',', '.');
    $btnGhost = 'min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $btnPrimary = 'min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $input = 'min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
    $label = ['belum' => ['Belum dikirim', 'neutral'], 'terkirim' => ['Terkirim', 'warning'], 'mengisi' => ['Sudah mengisi', 'success']];
@endphp

<div x-data="{ pesan: @js($templatePesan), tmpl: false, tambah: false, impor: false, hapus: false, hapusNama: '', salin: null,
               salinTautan(id, url) { navigator.clipboard?.writeText(url); this.salin = id; setTimeout(() => this.salin = null, 2000); } }">
    <x-page-header title="Responden" subtitle="Kelola tautan survei untuk setiap responden.">
        <x-slot:actions>
            <button type="button" @click="tmpl = true" class="{{ $btnGhost }}">Template pesan</button>
            {{-- TODO(backend): ekspor CSV daftar tautan. --}}
            <button type="button" class="{{ $btnGhost }}">Ekspor tautan (CSV)</button>
            @if ($saja)
                <button type="button" @click="impor = true" class="{{ $btnGhost }}">Impor Excel</button>
                <button type="button" @click="tambah = true" class="{{ $btnPrimary }}">Tambah responden</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- 6. Catatan privasi --}}
    <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-700" role="note">
        Halaman ini <strong>tidak menampilkan jawaban responden</strong>; hanya status pengisian dan tautan.
    </div>
    @unless ($saja)
        <div class="mb-8 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900" role="note">
            Daftar ini berasal dari undian di <a href="{{ route('statistik') }}" class="font-bold underline focus-visible:outline-2 focus-visible:outline-accent">Statistik</a>.
        </div>
    @else
        <div class="mb-8"></div>
    @endunless

    {{-- 1. Kartu --}}
    <section aria-labelledby="h-ringkasan" class="mb-10">
        <h2 id="h-ringkasan" class="sr-only">Ringkasan</h2>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-card label="Total responden" :value="$n($ringkasan['total'])" />
            <x-stat-card label="Sudah mengisi" :value="$n($ringkasan['sudah'])" />
            <x-stat-card label="Belum mengisi" :value="$n($ringkasan['belum'])" />
            <x-stat-card label="Tautan belum dikirim" :value="$n($ringkasan['belumDikirim'])" />
        </div>
    </section>

    {{-- 3. Tabel --}}
    <section aria-labelledby="h-tabel" class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 id="h-tabel" class="text-lg font-extrabold">Daftar responden</h2>
        {{-- TODO(backend): pencarian, filter, dan pagination server-side. --}}
        <form action="#" @submit.prevent class="mt-4 grid gap-3 {{ $pakaiWilayah ? 'sm:grid-cols-4' : 'sm:grid-cols-3' }}">
            <div class="sm:col-span-2">
                <label for="cari" class="sr-only">Cari kode atau nama</label>
                <input id="cari" type="search" placeholder="Cari kode atau nama" class="{{ $input }}">
            </div>
            @if ($pakaiWilayah)
                <div>
                    <label for="f-wilayah" class="sr-only">Filter {{ $wilayahLabel }}</label>
                    <select id="f-wilayah" class="{{ $input }}"><option>Semua {{ $wilayahLabel }}</option>@foreach ($daftarWilayah as $w)<option>{{ $w }}</option>@endforeach</select>
                </div>
            @endif
            <div>
                <label for="f-status" class="sr-only">Filter status</label>
                <select id="f-status" class="{{ $input }}"><option>Semua status</option>@foreach ($label as $l)<option>{{ $l[0] }}</option>@endforeach</select>
            </div>
        </form>

        @if (count($baris))
            <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200">
                <table class="w-full min-w-[56rem] text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            @foreach (array_filter(['Kode', 'Nama', $pakaiWilayah ? $wilayahLabel : null, 'Status', 'Waktu mengisi']) as $h)<th scope="col" class="px-4 py-3 font-bold">{{ $h }}</th>@endforeach
                            <th scope="col" class="px-4 py-3 font-bold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($baris as $r)
                            @php
                                $pesanWa = 'Halo '.$r['nama'].', Anda diundi mengisi survei singkat. Isi di sini: '.$r['tautan'];
                                $adaNomor = (bool) $r['telepon'];
                            @endphp
                            <tr class="align-top">
                                <td class="px-4 py-3 font-mono">{{ $r['kode'] }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $r['nama'] }}</td>
                                @if ($pakaiWilayah)<td class="px-4 py-3">{{ $r['wilayah'] }}</td>@endif
                                <td class="px-4 py-3"><x-badge :tone="$label[$r['status']][1]">{{ $label[$r['status']][0] }}</x-badge></td>
                                <td class="px-4 py-3">{{ $r['waktu'] ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" @click="salinTautan({{ $r['id'] }}, @js($r['tautan']))" class="{{ $btnGhost }}"><span x-text="salin === {{ $r['id'] }} ? 'Tersalin ✓' : 'Salin tautan'">Salin tautan</span></button>
                                        {{-- TODO(backend): buat ulang tautan (token baru), tautan lama tidak berlaku. --}}
                                        <button type="button" class="{{ $btnGhost }}">Buat ulang tautan</button>
                                        @if ($adaNomor)
                                            <a href="https://wa.me/{{ $r['telepon'] }}?text={{ rawurlencode($pesanWa) }}" target="_blank" rel="noopener" class="{{ $btnGhost }} inline-flex items-center">Kirim via WhatsApp</a>
                                        @else
                                            <span aria-disabled="true" title="Tidak ada nomor telepon" class="{{ $btnGhost }} inline-flex cursor-not-allowed items-center opacity-50">Kirim via WhatsApp</span>
                                        @endif
                                        {{-- TODO(backend): hapus responden hanya jika belum mengisi. --}}
                                        @if ($r['status'] !== 'mengisi')
                                            <button type="button" @click="hapusNama = @js($r['nama']); hapus = true" class="min-h-11 rounded-2xl border border-rose-300 px-4 text-sm font-bold text-rose-700 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600">Hapus</button>
                                        @endif
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
            <x-empty-state class="mt-5" title="Belum ada responden" :text="$saja ? 'Tambah responden atau impor dari Excel.' : 'Lakukan pengundian di halaman Statistik.'" />
        @endif
    </section>

    {{-- Modal hapus --}}
    <x-modal show="hapus" title="Hapus responden?">
        Responden <strong x-text="hapusNama"></strong> dan tautannya akan dihapus. Tindakan ini tidak dapat dibatalkan.
        <x-slot:footer>
            <button type="button" @click="hapus = false" class="{{ $btnGhost }}">Batal</button>
            <button type="button" @click="hapus = false" class="min-h-11 rounded-2xl bg-rose-700 px-4 font-bold text-white hover:bg-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700">Ya, hapus</button>
        </x-slot:footer>
    </x-modal>

    {{-- 5. Template pesan --}}
    {{-- TODO(backend): simpan template pesan undangan; {nama} dan {tautan} diganti server. --}}
    <x-modal show="tmpl" title="Template pesan undangan">
        <div class="space-y-1.5">
            <label for="tpl" class="block text-sm font-semibold text-ink">Isi pesan</label>
            <textarea id="tpl" rows="5" x-model="pesan" aria-describedby="tpl-help" class="w-full rounded-2xl border border-slate-300 bg-white p-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"></textarea>
            <p id="tpl-help" class="text-xs">Gunakan <code>{nama}</code> dan <code>{tautan}</code> sebagai penanda.</p>
        </div>
        <x-slot:footer>
            <button type="button" @click="tmpl = false" class="{{ $btnGhost }}">Batal</button>
            <button type="button" @click="tmpl = false" class="{{ $btnPrimary }}">Simpan</button>
        </x-slot:footer>
    </x-modal>

    @if ($saja)
        {{-- Tambah responden --}}
        {{-- TODO(backend): simpan responden; validasi NIK 16 digit & unik, tanggal lahir cocok NIK. --}}
        <x-modal show="tambah" title="Tambah responden">
            <div class="max-h-[60vh] space-y-4 overflow-y-auto p-1">
                <x-field label="Nama" name="r-nama" required />
                <x-field label="NIK" name="r-nik" inputmode="numeric" maxlength="16" required help="16 digit." />
                <x-field label="Tanggal lahir" name="r-lahir" type="date" required />
                <div class="space-y-1.5">
                    <label for="r-jk" class="block text-sm font-semibold text-ink">Jenis kelamin</label>
                    <select id="r-jk" required class="{{ $input }}"><option value="">Pilih</option><option>Laki-laki</option><option>Perempuan</option></select>
                </div>
                @if ($pakaiWilayah)
                    <div class="space-y-1.5">
                        <label for="r-wil" class="block text-sm font-semibold text-ink">{{ $wilayahLabel }}</label>
                        <select id="r-wil" required class="{{ $input }}"><option value="">Pilih {{ $wilayahLabel }}</option>@foreach ($daftarWilayah as $w)<option>{{ $w }}</option>@endforeach</select>
                    </div>
                @endif
                <x-field label="Sosial media" name="r-sosmed" help="Opsional." />
                <x-field label="Nomor telepon" name="r-telp" type="tel" help="Opsional. Dibutuhkan untuk kirim via WhatsApp." />
            </div>
            <x-slot:footer>
                <button type="button" @click="tambah = false" class="{{ $btnGhost }}">Batal</button>
                <button type="button" @click="tambah = false" class="{{ $btnPrimary }}">Simpan</button>
            </x-slot:footer>
        </x-modal>

        {{-- Impor Excel --}}
        {{-- TODO(backend): unggah & baca Excel, template unduhan, simpan hasil impor. --}}
        <x-modal show="impor" title="Impor responden dari Excel">
            <div class="max-h-[65vh] space-y-6 overflow-y-auto p-1">
                <p class="text-sm">Kolom wajib: Nama, NIK, Tanggal lahir, Jenis kelamin{{ $pakaiWilayah ? ', '.$wilayahLabel : '' }}. Opsional: Sosial media, Nomor telepon.</p>
                <a href="#" @click.prevent class="{{ $btnGhost }} inline-flex items-center">Unduh template</a>
                <label class="flex min-h-32 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-4 text-center hover:border-accent focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent">
                    <span class="font-bold text-ink">Seret berkas atau klik untuk memilih</span>
                    <span class="text-xs">Format .xlsx</span>
                    <input type="file" accept=".xlsx" class="sr-only">
                </label>
                <div>
                    <p class="mb-2 font-bold text-ink">Pemetaan kolom</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($kolomExcel as $i => $kol)
                            <div class="space-y-1">
                                <label for="pt-{{ $i }}" class="block text-sm font-semibold text-ink">Kolom "{{ $kol }}"</label>
                                <select id="pt-{{ $i }}" class="{{ $input }}">@foreach ($bidangTujuan as $j => $b)<option @selected($j === $i)>{{ $b }}</option>@endforeach</select>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="mb-2 font-bold text-ink">Pratinjau</p>
                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="w-full min-w-[30rem] text-left text-sm">
                            <thead class="bg-slate-50"><tr>@foreach (array_filter(['Nama', 'NIK', 'Tanggal lahir', 'JK', $pakaiWilayah ? $wilayahLabel : null]) as $h)<th scope="col" class="px-3 py-2 font-bold">{{ $h }}</th>@endforeach</tr></thead>
                            <tbody class="divide-y divide-slate-100">@foreach ($pratinjau as $r)<tr>@foreach ($r as $c)<td class="px-3 py-2">{{ $c }}</td>@endforeach</tr>@endforeach</tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <p class="mb-2 font-bold text-ink">Laporan baris bermasalah ({{ count($masalah) }})</p>
                    <ul class="space-y-2">
                        @foreach ($masalah as $m)
                            <li class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 p-3 text-sm">
                                <x-badge :tone="$m['tone']">Baris {{ $m['baris'] }}</x-badge>
                                <span class="font-semibold text-ink">{{ $m['nama'] }}</span><span>{{ $m['alasan'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <x-slot:footer>
                <button type="button" @click="impor = false" class="{{ $btnGhost }}">Batal</button>
                <button type="button" @click="impor = false" class="{{ $btnPrimary }}">Impor baris valid</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
@endsection
