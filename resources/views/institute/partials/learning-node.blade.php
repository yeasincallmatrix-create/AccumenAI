<li class="list-group-item d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <span>
        <i class="bi bi-folder"></i> {{ $node['name'] }}
        @if(!empty($node['branch_id'])) <small class="text-muted">[branch {{ $node['branch_id'] }}]</small> @endif
        @if(isset($node['children']) && count($node['children']))
            <small class="text-muted">— {{ count($node['children']) }} child(ren)</small>
        @endif
    </span>
    <span class="d-flex gap-1 align-items-center">
        <form method="POST" action="{{ route('academic.structure.settings.nodes.update', $node['id']) }}" class="d-inline">
            @csrf @method('PUT')
            <input type="hidden" name="name" value="{{ $node['name'] }} (edited)">
            <button class="btn btn-sm btn-outline-secondary py-0 px-1" style="font-size:11px">Edit</button>
        </form>
        <form method="POST" action="{{ route('academic.structure.settings.nodes.destroy', $node['id']) }}" class="d-inline">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger py-0 px-1" style="font-size:11px">Delete</button>
        </form>
    </span>
</li>
@if(!empty($node['children']))
    <ul class="list-group list-group-flush ms-4">
    @foreach($node['children'] as $child)
        @include('institute.partials.learning-node', ['node' => $child, 'level' => $level])
    @endforeach
    </ul>
@endif
