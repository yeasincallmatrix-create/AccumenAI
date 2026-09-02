@extends('layouts.institute')
@section('title','Candidates — HR')
@section('content')
<div class="standalone-heading"><h4>Candidates (CRM Leads)</h4></div>
<div class="admin-card">
    <table class="table table-sm mb-0">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th></tr></thead>
        <tbody>
            @foreach($candidates as $c)<tr><td>{{ $c->first_name }} {{ $c->last_name }}</td><td>{{ $c->email }}</td><td>{{ $c->phone }}</td></tr>@endforeach
        </tbody>
    </table>
    <div class="p-2">{{ $candidates->links() }}</div>
</div>
@endsection
