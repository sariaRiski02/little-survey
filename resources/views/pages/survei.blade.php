@extends('layouts.survey')
@section('title', $respondent->Project->name)
@section('content')
    <livewire:livewire-component.survey.fill-survey :respondent="$respondent" />
@endsection
