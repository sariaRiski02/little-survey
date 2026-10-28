@extends('layouts.guest')
@section('title', 'Hasil survei — '.$project->name)
@section('content')
    <livewire:livewire-component.guest.public-results :project="$project" />
@endsection
