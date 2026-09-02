{{-- DEV ONLY — Page Marker badge. Delete this file plus the `dev/page-marker`
     (and `/toggle`) routes and app/Support/PageMarker.php when development is done.
     Renders nothing while the platform setting `dev.page_marker_enabled` is off. --}}
@php
    use App\Support\PageMarker;
@endphp

@if (PageMarker::enabled())
    @php
        $pageMarkerNumber = PageMarker::page();
        $pageMarkerUrl = route('dev.page-marker');
    @endphp

<div class="page-marker" data-base-url="{{ $pageMarkerUrl }}">{{ $pageMarkerNumber }}</div>

<style>
    .page-marker {
        position: fixed;
        top: 30px;
        left: calc(50% + 45px);
        transform: translateX(-50%);
        z-index: 9999;
        min-width: 26px;
        height: 22px;
        padding: 0 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.78);
        color: #ffd24d;
        font: 700 13px/1 monospace;
        letter-spacing: 0.5px;
        border-radius: 999px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
        pointer-events: none;
        user-select: none;
    }
    .page-marker-modal {
        position: absolute;
        top: 14px;
        left: 50%;
        transform: translateX(-50%);
        min-width: 22px;
        height: 18px;
        padding: 0 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(220, 53, 69, 0.9);
        color: #fff;
        font: 700 11px/1 monospace;
        border-radius: 999px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
        pointer-events: none;
        user-select: none;
    }
</style>

<script>
(function () {
    var badges = {};
    var badge = document.querySelector('.page-marker');
    var baseUrl = badge ? badge.getAttribute('data-base-url') : '';

    if (!baseUrl) { return; }

    function place(modal, number) {
        var dialog = modal && modal.querySelector('.modal-dialog');
        if (!dialog || !number || dialog.querySelector('.page-marker-modal')) { return; }

        var el = document.createElement('div');
        el.className = 'page-marker-modal';
        el.textContent = number;
        dialog.appendChild(el);
    }

    function label(modal) {
        var id = modal && modal.getAttribute('id');
        if (!id) { return; }

        if (id in badges) {
            place(modal, badges[id]);
            return;
        }

        fetch(baseUrl + '?key=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && data.number) {
                    badges[id] = data.number;
                    place(modal, data.number);
                }
            })
            .catch(function () {});
    }

    document.addEventListener('shown.bs.modal', function (e) {
        label(e.target);
    });
})();
</script>
@endif