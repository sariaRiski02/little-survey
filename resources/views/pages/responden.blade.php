@extends('layouts.admin')
@section('title', 'Responden')
@section('content')
@php
    $isRespondentsOnly = ! $project->isFullDptMode();
    $wilayahOptions = $project->usesWilayah() ? app(\App\Services\RespondentService::class)->wilayahOptions($project) : [];
    $inputField = 'min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
    $btnGhost = 'min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $btnPrimary = 'min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
@endphp

<div x-data="{ ekspor: false, tmpl: false, tambah: false, impor: false, hapus: false, hapusId: null, hapusNama: '', salin: null,
               salinTautan(id, url) { navigator.clipboard?.writeText(url); this.salin = id; setTimeout(() => this.salin = null, 2000); } }">
    <x-page-header title="Responden" subtitle="Kelola tautan survei untuk setiap responden.">
        <x-slot:actions>
            <button type="button" @click="tmpl = true" class="{{ $btnGhost }}">Template pesan</button>
            <button type="button" @click="ekspor = true" class="{{ $btnGhost }}">Ekspor tautan (CSV)</button>
            @if ($isRespondentsOnly)
                <button type="button" @click="impor = true" class="{{ $btnGhost }}">Impor Excel</button>
                <button type="button" @click="tambah = true" class="{{ $btnPrimary }}">Tambah responden</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-700" role="note">
        Halaman ini hanya menampilkan status pengisian dan tautan. Jawaban responden ada di menu <a href="{{ route('jawaban', ['project' => $project->code_project]) }}" class="font-bold text-accent underline focus-visible:outline-2 focus-visible:outline-accent">Jawaban</a>.
        Menandai tautan pertama sebagai terkirim akan mengunci daftar kandidat dan pertanyaan.
    </div>
    @unless ($isRespondentsOnly)
        <div class="mb-8 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900" role="note">
            Daftar ini berasal dari undian di <a href="{{ route('statistik', ['project' => $project->code_project]) }}" class="font-bold underline focus-visible:outline-2 focus-visible:outline-accent">Statistik</a>.
        </div>
    @else
        <div class="mb-8"></div>
    @endunless

    <x-modal show="ekspor" title="Ekspor tautan responden">
        <form id="form-ekspor" method="GET" action="{{ route('responden.export', ['project' => $project->code_project]) }}" class="space-y-4" @submit="ekspor = false">
            @if ($project->usesWilayah())
                <div class="space-y-1.5">
                    <label for="x-wilayah" class="block text-sm font-semibold text-ink">{{ $project->wilayah_label }}</label>
                    <select id="x-wilayah" name="wilayah" class="{{ $inputField }}">
                        <option value="">Semua {{ $project->wilayah_label }}</option>
                        @foreach ($wilayahOptions as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="space-y-1.5">
                <label for="x-status" class="block text-sm font-semibold text-ink">Status</label>
                <select id="x-status" name="status" class="{{ $inputField }}">
                    <option value="">Semua status</option>
                    <option value="{{ \App\Models\Respondent::STATUS_NOT_SENT }}">Belum dikirim</option>
                    <option value="{{ \App\Models\Respondent::STATUS_SENT }}">Terkirim (belum mengisi)</option>
                    <option value="{{ \App\Models\Respondent::STATUS_FILLED }}">Sudah mengisi</option>
                </select>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" @click="ekspor = false" class="{{ $btnGhost }}">Batal</button>
            <button type="submit" form="form-ekspor" class="{{ $btnPrimary }}">Unduh</button>
        </x-slot:footer>
    </x-modal>

    <livewire:livewire-component.responden.summary-responden :project="$project" />
    <livewire:livewire-component.responden.list-responden :project="$project" />
    <livewire:livewire-component.responden.invitation-template :project="$project" />

    @if ($isRespondentsOnly)
        <livewire:livewire-component.responden.add-responden :project="$project" />
        <livewire:livewire-component.responden.import-responden :project="$project" />
    @endif
</div>
@endsection
