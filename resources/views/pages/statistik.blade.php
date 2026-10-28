@extends('layouts.admin')
@section('title', 'Statistik')
@section('content')

@php($isFullDpt = $project->isFullDptMode())

<div>
    <x-page-header title="Statistik" subtitle="Hitung ukuran sampel{{ $isFullDpt ? ' dan undi responden' : '' }}." />

    @unless ($isFullDpt)
        <div class="mb-8 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900" role="note">
            Halaman ini berfungsi sebagai <strong>kalkulator ukuran sampel</strong>. Pengundian dan alokasi otomatis{{ $project->usesWilayah() ? ' per '.$project->wilayah_label : '' }} tidak tersedia; calon responden diimpor di halaman Responden.
        </div>
    @endunless

    <livewire:livewire-component.statistik.sample-parameter :project="$project" />
    <livewire:livewire-component.statistik.sample-result :project="$project" />

    @if ($isFullDpt)
        <livewire:livewire-component.statistik.draw-panel :project="$project" />
    @endif

    <livewire:livewire-component.statistik.significance-panel :project="$project" />
</div>
@endsection
