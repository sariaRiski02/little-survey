@extends('layouts.admin')
@section('title', 'Pertanyaan')
@section('content')
@php
    $btnGhost = 'min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $btnPrimary = 'min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $input = 'min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
@endphp

<div x-data="{
        terkunci: {{ $terkunci ? 'true' : 'false' }},
        items: @js($daftarPertanyaan),
        tipe: @js($tipe),
        kandidat: @js($namaKandidat),
        form: { tipe: 'tunggal', cabangId: '', cabangJawab: '' },
        preview: false, template: false, hapus: false, hapusIdx: null,
        pindah(i, d) { const j = i + d; if (j < 0 || j >= this.items.length) return; [this.items[i], this.items[j]] = [this.items[j], this.items[i]]; },
        get durasi() { return Math.max(1, Math.ceil(this.items.length * 0.5)); },
        get opsiCabang() { const q = this.items.find(x => x.id == this.form.cabangId); return q ? q.pilihan : []; },
        get butuhPilihan() { return ['tunggal', 'ganda', 'skala'].includes(this.form.tipe); }
     }">
    <x-page-header title="Pertanyaan" subtitle="Susun kuesioner yang akan diisi responden.">
        <x-slot:actions>
            <button type="button" @click="preview = true" class="{{ $btnGhost }}">Pratinjau responden</button>
            <button type="button" @click="template = true" :disabled="terkunci" class="{{ $btnGhost }}">Buat dari template</button>
            {{-- Hanya pratinjau keadaan; hapus saat backend tersedia. --}}
            <button type="button" @click="terkunci = !terkunci" class="{{ $btnGhost }}">Pratinjau: <span x-text="terkunci ? 'Terkunci' : 'Terbuka'"></span></button>
        </x-slot:actions>
    </x-page-header>

    {{-- 6. Keadaan terkunci --}}
    {{-- TODO(backend): kunci pertanyaan setelah tautan survei pertama dikirim. --}}
    <div x-show="!terkunci" class="mb-8 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900" role="status">
        <strong>Pertanyaan masih terbuka.</strong> Pertanyaan akan dikunci setelah tautan survei pertama dikirim.
    </div>
    <div x-cloak x-show="terkunci" class="mb-8 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
        <strong>Pertanyaan terkunci.</strong> Tautan survei sudah dikirim; menambah, mengubah, mengurutkan, atau menghapus pertanyaan dinonaktifkan agar jawaban tetap sebanding.
    </div>

    {{-- 5. Peringatan netralitas --}}
    <aside class="mb-8 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900" aria-labelledby="h-netral">
        <h2 id="h-netral" class="font-extrabold">Jaga netralitas pertanyaan</h2>
        <p class="mt-1">Hindari kalimat yang mengarahkan jawaban responden.</p>
        <ul class="mt-3 space-y-1">
            <li><span class="font-bold text-rose-800">Mengarahkan:</span> “Apakah Anda setuju bahwa kandidat A yang paling peduli pada warga?”</li>
            <li><span class="font-bold text-teal-800">Netral:</span> “Jika pemilihan diadakan hari ini, siapa yang akan Anda pilih?”</li>
        </ul>
    </aside>

    <div class="grid gap-8 lg:grid-cols-5">
        {{-- 1. Daftar --}}
        <section aria-labelledby="h-daftar" class="lg:col-span-3">
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="h-daftar" class="text-lg font-extrabold">Daftar pertanyaan (<span x-text="items.length"></span>)</h2>
                <p class="text-sm text-slate-600">Perkiraan durasi pengisian: <strong>± <span x-text="durasi"></span> menit</strong></p>
            </div>
            {{-- TODO(backend): simpan urutan, ubah, dan hapus pertanyaan; durasi dihitung server. --}}
            <ol class="space-y-3" x-show="items.length">
                <template x-for="(q, i) in items" :key="q.id">
                    <li class="rounded-2xl border border-slate-200 bg-white p-5">
                        <div class="flex gap-4">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-extrabold" x-text="i + 1" aria-hidden="true"></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold" x-text="q.teks"></p>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs font-bold">
                                    <span class="rounded-full bg-slate-100 px-3 py-1" x-text="tipe[q.tipe]"></span>
                                    <span class="rounded-full px-3 py-1" :class="q.wajib ? 'bg-teal-50 text-teal-800' : 'bg-slate-100 text-slate-700'" x-text="q.wajib ? 'Wajib' : 'Opsional'"></span>
                                    <span x-show="q.tidakMauMenjawab" class="rounded-full bg-slate-100 px-3 py-1">Ada “Tidak mau menjawab”</span>
                                    <span x-show="q.cabang" class="rounded-full bg-amber-50 px-3 py-1 text-amber-800" x-text="q.cabang ? 'Tampil jika no. ' + (items.findIndex(x => x.id == q.cabang.id) + 1) + ' = ' + q.cabang.jawab : ''"></span>
                                </div>
                                <p x-show="q.tipe === 'kandidat'" class="mt-2 text-xs text-slate-600">Pilihan diambil dari data kandidat dan diacak per responden.</p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap justify-end gap-2">
                            <button type="button" :disabled="terkunci || i === 0" @click="pindah(i, -1)" :aria-label="'Naikkan pertanyaan ' + (i + 1)" class="{{ $btnGhost }}">↑ Naik</button>
                            <button type="button" :disabled="terkunci || i === items.length - 1" @click="pindah(i, 1)" :aria-label="'Turunkan pertanyaan ' + (i + 1)" class="{{ $btnGhost }}">↓ Turun</button>
                            <button type="button" :disabled="terkunci" @click="hapusIdx = i; hapus = true" class="min-h-11 rounded-2xl border border-rose-300 px-4 text-sm font-bold text-rose-700 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 disabled:cursor-not-allowed disabled:opacity-50">Hapus</button>
                        </div>
                    </li>
                </template>
            </ol>
            <div x-show="!items.length"><x-empty-state title="Belum ada pertanyaan" text="Tambahkan pertanyaan atau mulai dari template." /></div>
        </section>

        {{-- 2 & 3. Form tambah --}}
        <section aria-labelledby="h-tambah" class="h-fit rounded-2xl border border-slate-200 bg-white p-6 lg:col-span-2">
            <h2 id="h-tambah" class="text-lg font-extrabold">Tambah pertanyaan</h2>
            {{-- TODO(backend): simpan pertanyaan; validasi teks wajib, minimal 2 pilihan, cabang hanya ke pertanyaan sebelumnya. --}}
            <form action="#" method="post" @submit.prevent class="mt-5">
                <fieldset :disabled="terkunci" class="space-y-5 disabled:opacity-50">
                    <div class="space-y-1.5">
                        <label for="p-teks" class="block text-sm font-semibold">Teks pertanyaan</label>
                        <textarea id="p-teks" rows="3" required class="w-full rounded-2xl border border-slate-300 bg-white p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"></textarea>
                    </div>
                    <x-field label="Keterangan" name="p-ket" help="Opsional. Petunjuk singkat di bawah pertanyaan." />
                    <div class="space-y-1.5">
                        <label for="p-tipe" class="block text-sm font-semibold">Tipe</label>
                        <select id="p-tipe" x-model="form.tipe" class="{{ $input }}">
                            @foreach ($tipe as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach
                        </select>
                    </div>
                    <div x-show="butuhPilihan" class="space-y-1.5">
                        <label for="p-pilihan" class="block text-sm font-semibold">Pilihan jawaban</label>
                        <textarea id="p-pilihan" rows="4" aria-describedby="p-pilihan-help" class="w-full rounded-2xl border border-slate-300 bg-white p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"></textarea>
                        <p id="p-pilihan-help" class="text-xs text-slate-600">Satu pilihan per baris. Untuk skala, isi label tiap tingkat.</p>
                    </div>
                    <div x-cloak x-show="form.tipe === 'kandidat'" class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-700">
                        <p class="font-bold">Pilihan diambil dari data kandidat dan diacak per responden:</p>
                        <ul class="mt-2 list-disc pl-5"><template x-for="k in kandidat"><li x-text="k"></li></template></ul>
                        <p class="mt-2 text-xs text-slate-600">Kelola kandidat di menu Kandidat.</p>
                    </div>
                    <div class="space-y-1">
                        <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" checked class="h-5 w-5 rounded accent-accent focus-visible:outline-2 focus-visible:outline-accent"> Wajib dijawab</label>
                        <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" class="h-5 w-5 rounded accent-accent focus-visible:outline-2 focus-visible:outline-accent"> Sediakan opsi “Tidak mau menjawab”</label>
                    </div>

                    <fieldset class="space-y-3 rounded-2xl border border-slate-200 p-4">
                        <legend class="px-2 text-sm font-bold">Percabangan (opsional)</legend>
                        <p class="text-xs text-slate-600">Tampilkan hanya jika pertanyaan X dijawab Y.</p>
                        <div class="space-y-1.5">
                            <label for="p-cabang-x" class="block text-sm font-semibold">Pertanyaan X</label>
                            <select id="p-cabang-x" x-model="form.cabangId" @change="form.cabangJawab = ''" class="{{ $input }}">
                                <option value="">Selalu tampilkan</option>
                                <template x-for="(q, i) in items" :key="q.id"><option :value="q.id" x-text="(i + 1) + '. ' + q.teks.slice(0, 40)"></option></template>
                            </select>
                        </div>
                        <div x-show="form.cabangId" class="space-y-1.5">
                            <label for="p-cabang-y" class="block text-sm font-semibold">Dijawab Y</label>
                            <select id="p-cabang-y" x-model="form.cabangJawab" class="{{ $input }}">
                                <option value="">Pilih jawaban</option>
                                <template x-for="o in opsiCabang"><option x-text="o"></option></template>
                            </select>
                        </div>
                    </fieldset>
                    <button type="submit" class="{{ $btnPrimary }} w-full">Tambah pertanyaan</button>
                </fieldset>
                <p x-cloak x-show="terkunci" class="mt-3 text-sm font-semibold text-amber-900">Formulir dinonaktifkan karena pertanyaan terkunci.</p>
            </form>
        </section>
    </div>

    {{-- Modal hapus --}}
    {{-- TODO(backend): hapus pertanyaan sungguhan setelah konfirmasi. --}}
    <x-modal show="hapus" title="Hapus pertanyaan?">
        Pertanyaan “<span x-text="hapusIdx !== null && items[hapusIdx] ? items[hapusIdx].teks : ''"></span>” akan dihapus. Tindakan ini tidak dapat dibatalkan.
        <x-slot:footer>
            <button type="button" @click="hapus = false" class="{{ $btnGhost }}">Batal</button>
            <button type="button" @click="items.splice(hapusIdx, 1); hapus = false" class="min-h-11 rounded-2xl bg-rose-700 px-4 font-bold text-white hover:bg-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700">Ya, hapus</button>
        </x-slot:footer>
    </x-modal>

    {{-- 4. Template --}}
    {{-- TODO(backend): terapkan paket template ke database (mengganti atau menambah pertanyaan). --}}
    <x-modal show="template" title="Buat dari template">
        <p>Paket <strong>Survei Preferensi Pemilih</strong> berisi pertanyaan berikut:</p>
        <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm">
            @foreach ($templatePaket as $t)<li>{{ $t }}</li>@endforeach
        </ol>
        <p class="mt-3 text-xs">Tampilan saja; belum menambahkan pertanyaan.</p>
        <x-slot:footer>
            <button type="button" @click="template = false" class="{{ $btnGhost }}">Batal</button>
            <button type="button" @click="template = false" class="{{ $btnPrimary }}">Gunakan template</button>
        </x-slot:footer>
    </x-modal>

    {{-- 6. Pratinjau responden --}}
    <x-modal show="preview" title="Pratinjau tampilan responden">
        <div class="max-h-[60vh] space-y-5 overflow-y-auto pr-1">
            <template x-for="(q, i) in items" :key="q.id">
                <div>
                    <p class="font-bold text-ink"><span x-text="(i + 1) + '. ' + q.teks"></span><span x-show="q.wajib" class="text-rose-700"> *</span></p>
                    <div class="mt-2 space-y-1 text-sm">
                        <template x-if="q.tipe === 'isian'"><div class="min-h-11 rounded-2xl border border-slate-300 px-4 py-3 text-slate-500">Jawaban Anda…</div></template>
                        <template x-if="q.tipe !== 'isian'">
                            <div class="space-y-1">
                                <template x-for="o in q.pilihan"><label class="flex min-h-11 items-center gap-3 rounded-2xl border border-slate-200 px-4"><input :type="q.tipe === 'ganda' ? 'checkbox' : 'radio'" disabled class="h-4 w-4"><span x-text="o"></span></label></template>
                                <label x-show="q.tidakMauMenjawab" class="flex min-h-11 items-center gap-3 rounded-2xl border border-slate-200 px-4"><input type="radio" disabled class="h-4 w-4">Tidak mau menjawab</label>
                            </div>
                        </template>
                    </div>
                    <p x-show="q.tipe === 'kandidat'" class="mt-1 text-xs text-slate-500">Urutan kandidat diacak per responden.</p>
                </div>
            </template>
        </div>
        <x-slot:footer>
            <button type="button" @click="preview = false" class="{{ $btnPrimary }}">Tutup</button>
        </x-slot:footer>
    </x-modal>
</div>
@endsection
