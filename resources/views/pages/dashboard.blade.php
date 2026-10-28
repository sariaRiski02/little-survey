@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')

<x-page-header title="Dashboard" subtitle="Ringkasan pelaksanaan survei dan hasil sementara." />

@if (! $project->isFullDptMode() && ! $project->is_random_sample)
    <div role="alert" class="mb-8 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm font-semibold text-amber-900">
        Sampel non-acak: hasil bersifat gambaran, bukan estimasi populasi.
    </div>
@endif

<livewire:livewire-component.dashboard.overview-dashboard :project="$project" />
<livewire:livewire-component.dashboard.results-dashboard :project="$project" />
@endsection
