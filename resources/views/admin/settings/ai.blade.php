@extends('layouts.standalone')

@php $backUrl = route('admin.settings.index'); @endphp

@section('title', 'AI Settings — AccumenAI')
@section('page_title', 'AI Settings')

@section('content')

<div class="standalone-heading">
    <h4>AI Settings</h4>
    <p>Configure the platform AI layer. Institute owners can only enable or disable AI for their own institute — they cannot see these credentials.</p>
</div>

@include('admin.settings._ai')

@endsection
