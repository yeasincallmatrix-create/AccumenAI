@extends('layouts.standalone')

@section('title', 'Audit Trail — AccumenAI')
@section('page_title', 'Finance')

@push('styles')
<style>
    .sortable { cursor: pointer; user-select: none; }
    .sortable:hover { background-color: rgba(0,0,0,0.05); }
</style>
@endpush

@section('content')

<div class="standalone-heading">
    <h4>Audit Trail</h4>
    <p>Append-only log of every financial write (read-only). Rows are never edited or deleted; older entries appear below.</p>
</div>

@livewire('audit-trail-list')

@endsection
