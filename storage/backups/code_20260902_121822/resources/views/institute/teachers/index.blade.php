@extends('layouts.standalone')

@php $isProfessional = \App\Support\InstituteDomain::isProfessional($institute ?? null); @endphp
@section('title', ($isProfessional ? 'Trainers' : 'Teachers') . ' — AccumenAI')
@section('page_title', $isProfessional ? 'Trainers' : 'Teachers')

@section('content')

@livewire('teacher-list')

@endsection