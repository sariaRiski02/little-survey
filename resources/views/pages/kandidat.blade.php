@extends('layouts.admin')
@section('title', 'Kandidat')
@section('content')

<div x-data="{ terkunci: {{ $project->is_locked ? 'true' : 'false' }}, hapus: false, hapusNama: '', ubah: false, edit: { id: null, name: '', number: '', color: '#0F6B6B', picture: null } }">


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
            <livewire:livewire-component.candidate.order-setting :project="$project"/>
        </div>

        {{-- Tambah Kandidat --}}
        <livewire:livewire-component.candidate.add-candidate :project="$project"/>

    </div>


</div>
@endsection
