<!doctype html><html><head><meta charset="utf-8"><title>Purchase Report — {{ $type }}</title>
<style>body{font-family:system-ui,Arial,sans-serif;font-size:13px} table{width:100%;border-collapse:collapse} th,td{border:1px solid #ddd;padding:6px} th{background:#f5f5f5}</style></head><body>
<h3>Purchase Report — {{ ucfirst($type) }} — {{ $institute->name }}</h3>
<p class="text-muted">Filters: {{ json_encode($filters) }} — Printed {{ now()->format('Y-m-d H:i') }}</p>
@if(is_array($data) || $data instanceof \Illuminate\Support\Collection)
<pre>{{ json_encode($data, JSON_PRETTY_PRINT) }}</pre>
@else
<p>Print view for {{ $type }}</p>
@endif
<script>window.print()</script>
</body></html>

