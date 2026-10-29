@extends('layouts.admin')
@section('title', 'Jawaban')
@section('content')

<x-page-header title="Jawaban responden" subtitle="Lihat jawaban yang sudah masuk dan unduh sebagai Excel." />

<livewire:livewire-component.jawaban.list-jawaban :project="$project" />
@endsection
