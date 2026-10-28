@extends('layouts.admin')
@section('title', 'Pertanyaan')
@section('content')
@php
    $btnGhost = 'min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
@endphp

<div x-data="{ terkunci: {{ $project->is_questions_locked ? 'true' : 'false' }}, preview: false, template: false, hapus: false, hapusId: null, hapusText: '' }">
    <x-page-header title="Pertanyaan" subtitle="Susun kuesioner yang akan diisi responden.">
        <x-slot:actions>
            <button type="button" @click="preview = true" class="{{ $btnGhost }}">Pratinjau responden</button>
            <button type="button" @click="template = true" :disabled="terkunci" class="{{ $btnGhost }}">Buat dari template</button>
        </x-slot:actions>
    </x-page-header>

    <div x-show="!terkunci" class="mb-8 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900" role="status">
        <strong>Pertanyaan masih terbuka.</strong> Pertanyaan akan dikunci setelah tautan survei pertama dikirim.
    </div>
    <div x-cloak x-show="terkunci" class="mb-8 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
        <strong>Pertanyaan terkunci.</strong> Tautan survei sudah dikirim; menambah, mengubah, mengurutkan, atau menghapus pertanyaan dinonaktifkan agar jawaban tetap sebanding.
    </div>

    <aside class="mb-8 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900" aria-labelledby="h-netral">
        <h2 id="h-netral" class="font-extrabold">Jaga netralitas pertanyaan</h2>
        <p class="mt-1">Hindari kalimat yang mengarahkan jawaban responden.</p>
        <ul class="mt-3 space-y-1">
            <li><span class="font-bold text-rose-800">Mengarahkan:</span> “Apakah Anda setuju bahwa kandidat A yang paling peduli pada warga?”</li>
            <li><span class="font-bold text-teal-800">Netral:</span> “Jika pemilihan diadakan hari ini, siapa yang akan Anda pilih?”</li>
        </ul>
    </aside>

    <div class="grid gap-8 lg:grid-cols-5">
        <div class="lg:col-span-3">
            <livewire:livewire-component.question.list-question :project="$project" />
        </div>
        <div class="lg:col-span-2">
            <livewire:livewire-component.question.add-question :project="$project" />
        </div>
    </div>
</div>
@endsection
