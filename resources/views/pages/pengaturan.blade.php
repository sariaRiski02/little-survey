@extends('layouts.admin')
@section('title', 'Pengaturan')
@section('content')
@php
    $btnPrimary = 'min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
    $modeOpsi = [
        'dpt_lengkap' => ['DPT lengkap', 'Admin mengimpor DPT, lalu responden diundi di halaman Statistik.'],
        'responden_saja' => ['Responden saja', 'Admin mengimpor calon responden langsung di halaman Responden. Menu DPT disembunyikan.'],
    ];
@endphp

<x-page-header title="Pengaturan" subtitle="Atur identitas proyek dan cara data responden diperoleh." />

{{-- TODO(backend): simpan pengaturan; mode hanya boleh diganti sebelum ada data/tautan terkirim. --}}
<form action="#" method="post" @submit.prevent class="max-w-2xl space-y-8" x-data="{
          mode: @js($mode), wilayah: @js($wilayahLabel), pakai: @js($pakaiWilayah), jml: @js($jumlahWilayah),
          items: @js($wilayahKelola), baru: '', edit: null, editNama: '', hapus: false, hapusIdx: null, gA: '', gB: '', gNama: '', nextId: 1000,
          tambah() { const n = this.baru.trim(); if (!n) return; this.items.push({ id: this.nextId++, nama: n, responden: 0 }); this.baru = ''; },
          mulaiUbah(w) { this.edit = w.id; this.editNama = w.nama; },
          simpanUbah(w) { if (this.editNama.trim()) w.nama = this.editNama.trim(); this.edit = null; },
          gabung() {
              const a = this.items.find(x => x.id == this.gA), b = this.items.find(x => x.id == this.gB);
              if (!a || !b || a === b) return;
              a.responden += b.responden; a.nama = this.gNama.trim() || a.nama;
              this.items = this.items.filter(x => x !== b); this.gA = this.gB = this.gNama = '';
          },
          get tautan() {
              const p = new URLSearchParams();
              if (this.mode === 'responden_saja') p.set('mode', this.mode);
              if (!this.pakai) p.set('wilayah', '0'); else if (this.jml == 14) p.set('jml', '14');
              const q = p.toString();
              return '{{ route('dashboard') }}' + (q ? '?' + q : '');
          }
     }">
    <section aria-labelledby="h-umum" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
        <h2 id="h-umum" class="text-lg font-extrabold">Umum</h2>
        <x-field label="Nama proyek" name="nama" :value="$namaSurvei" required />
        <div class="space-y-1.5">
            <label for="f-wilayah" class="block text-sm font-semibold">Sebutan wilayah</label>
            <input id="f-wilayah" name="wilayah" type="text" x-model="wilayah" required aria-describedby="f-wilayah-help"
                   class="min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
            <p id="f-wilayah-help" class="text-xs text-slate-600">Contoh: Jaga, RT, Dusun. Dipakai di seluruh teks, misalnya “Progres <span x-text="wilayah || '…'"></span>”.</p>
        </div>
    </section>

    <section aria-labelledby="h-mode" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
        <h2 id="h-mode" class="text-lg font-extrabold">Mode data</h2>
        <fieldset class="space-y-3">
            <legend class="sr-only">Mode data</legend>
            @foreach ($modeOpsi as $nilai => [$judul, $ket])
                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent"
                       :class="mode === '{{ $nilai }}' ? 'border-accent bg-teal-50' : 'border-slate-200'">
                    <input type="radio" name="mode" value="{{ $nilai }}" x-model="mode" class="mt-1 h-5 w-5 accent-accent">
                    <span><span class="block font-bold">{{ $judul }}</span><span class="block text-sm text-slate-600">{{ $ket }}</span></span>
                </label>
            @endforeach
        </fieldset>

        {{-- Pengalih mode hanya untuk pratinjau; tidak menyimpan apa pun. --}}
        <p class="text-sm text-slate-600">
            Pengalih ini hanya pratinjau.
            <a :href="tautan" class="font-bold text-accent underline focus-visible:outline-2 focus-visible:outline-accent">Lihat seluruh aplikasi dalam mode ini</a>
        </p>

        <div x-cloak x-show="mode === 'responden_saja'" class="space-y-5 border-t border-slate-200 pt-5">
            <x-field label="Jumlah DPT" name="jumlah_dpt" type="number" min="0" :value="$pengaturan['jumlahDpt']" help="Opsional. Hanya berupa angka perkiraan populasi; jika kosong tampil “-”." />
            <label class="flex min-h-11 items-start gap-3 text-sm">
                <input type="checkbox" name="sampel_acak" @checked($pengaturan['sampelAcak']) class="mt-0.5 h-5 w-5 rounded accent-accent focus-visible:outline-2 focus-visible:outline-accent">
                <span><span class="block font-semibold">Sampel ini diambil secara acak</span><span class="block text-xs text-slate-600">Jika tidak dicentang, dashboard menampilkan peringatan sampel non-acak.</span></span>
            </label>
        </div>
    </section>

    <button type="submit" class="{{ $btnPrimary }}">Simpan pengaturan</button>

    <x-modal show="hapus" title="Hapus wilayah?">
        <span x-text="hapusIdx !== null && items[hapusIdx] ? items[hapusIdx].nama : ''" class="font-bold"></span> akan dihapus dari daftar. Pastikan tidak ada responden yang masih memakainya.
        <x-slot:footer>
            <button type="button" @click="hapus = false" class="min-h-11 rounded-2xl border border-slate-300 px-4 font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-accent">Batal</button>
            <button type="button" @click="items.splice(hapusIdx, 1); hapus = false" class="min-h-11 rounded-2xl bg-rose-700 px-4 font-bold text-white hover:bg-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700">Ya, hapus</button>
        </x-slot:footer>
    </x-modal>
</form>
@endsection
