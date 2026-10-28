@extends('layouts.admin')
@section('title', 'Kandidat')
@section('content')

<div x-data="{ terkunci: {{ $project->is_locked ? 'true' : 'false' }}, acak: {{ isset($acakUrutan) ? 'true' : 'false' }}, hapus: false, hapusNama: '', ubah: false, edit: { nama: '', nomor: '', warna: '#0F6B6B' } }">


    <div x-show="!terkunci" class="mb-8 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900" role="status">
        <strong>Daftar kandidat masih terbuka.</strong> Kandidat akan dikunci setelah tautan survei pertama dikirim.
    </div>
    <div x-cloak x-show="terkunci" class="mb-8 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
        <strong>Daftar kandidat terkunci.</strong> Tautan survei sudah dikirim, sehingga kandidat tidak dapat ditambah, diubah, atau dihapus agar hasil tetap konsisten.
    </div>


    <div class="grid gap-8 lg:grid-cols-3">
        <div class="space-y-8 lg:col-span-2">

            {{-- Daftar Kandidat --}}

            <livewire:livewire-component.candidate.list-candidate :project="$project"/>
            {{-- Pengaturan urutan kandidat --}}
            <section aria-labelledby="h-acak" class="rounded-2xl border border-slate-200 bg-white p-6">
                <h2 id="h-acak" class="sr-only">Pengaturan urutan</h2>
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="font-bold" id="lbl-acak">Acak urutan kandidat untuk tiap responden</p>
                        <p class="text-sm text-slate-600">Mengurangi bias karena kandidat yang tampil paling atas.</p>
                    </div>
                    <button type="button" role="switch" :aria-checked="acak" aria-labelledby="lbl-acak" @click="acak = !acak" :class="acak ? 'bg-accent' : 'bg-slate-300'" class="relative inline-flex h-11 w-16 shrink-0 items-center rounded-full px-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                        <span :class="acak ? 'translate-x-6' : 'translate-x-0'" class="block h-8 w-8 rounded-full bg-white shadow transition-transform"></span>
                    </button>
                </div>
            </section>
        </div>

        {{-- Tambah Kandidat --}}
        <livewire:livewire-component.candidate.add-candidate :project="$project"/>

    </div>


</div>
@endsection
