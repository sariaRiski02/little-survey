@extends('layouts.admin')
@section('title', 'Pengaturan')
@section('content')

<x-page-header title="Pengaturan" subtitle="Atur identitas proyek dan cara data responden diperoleh." />

@if (session('status'))
    <p class="mb-6 max-w-2xl rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900" role="status">{{ session('status') }}</p>
@endif

<livewire:livewire-component.pengaturan.settings-form :project="$project" />
@endsection
