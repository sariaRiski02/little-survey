@extends('layouts.admin')
@section('title', 'DPT')
@section('content')

<div x-data="{ terkunci: {{ $project->is_dpt_locked ? 'true' : 'false' }}, tab: 'impor', hapus: false, hapusMassal: false, hapusNama: '', hapusId: null, ubah: false, edit: { id: null, name: '', birth_date: '', wilayah: '' } }">
    <x-page-header title="DPT" subtitle="Daftar Pemilih Tetap sebagai dasar undian responden." />

    <div x-show="!terkunci" class="mb-8 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900" role="status">
        <strong>DPT masih terbuka.</strong> Data dikunci setelah sampel diundi.
    </div>
    <div x-cloak x-show="terkunci" class="mb-8 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
        <strong>DPT terkunci.</strong> Sampel sudah diundi, sehingga impor, penambahan, perubahan, dan penghapusan data dinonaktifkan.
    </div>

    <livewire:livewire-component.dpt.summary-dpt :project="$project" />

    <section class="mb-10 rounded-2xl border border-slate-200 bg-white p-6" aria-labelledby="h-tambah">
        <h2 id="h-tambah" class="text-lg font-extrabold">Tambah data DPT</h2>
        <div class="mt-4 inline-flex rounded-2xl bg-slate-100 p-1" role="tablist">
            <button type="button" role="tab" :aria-selected="tab === 'impor'" @click="tab = 'impor'" :class="tab === 'impor' ? 'bg-white shadow-sm' : ''" class="min-h-11 rounded-xl px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-accent">Impor Excel</button>
            <button type="button" role="tab" :aria-selected="tab === 'manual'" @click="tab = 'manual'" :class="tab === 'manual' ? 'bg-white shadow-sm' : ''" class="min-h-11 rounded-xl px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-accent">Input manual</button>
        </div>

        <div x-show="tab === 'impor'" role="tabpanel" class="mt-6">
            <livewire:livewire-component.dpt.import-dpt :project="$project" />
        </div>
        <div x-cloak x-show="tab === 'manual'" role="tabpanel" class="mt-6">
            <livewire:livewire-component.dpt.add-dpt :project="$project" />
        </div>
    </section>

    <livewire:livewire-component.dpt.list-dpt :project="$project" />
</div>
@endsection
