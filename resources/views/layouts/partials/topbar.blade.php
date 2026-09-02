{{--
    Topbar / navbar partial — connectivity signal usage example.

    Drop this partial into a layout's header, or simply copy the
    <x-connectivity-signal /> line straight into an existing topbar:

        @include('layouts.partials.topbar')

    The component needs nothing from the server — it observes real browser
    'online'/'offline' events entirely on the client. Defaults render a 16px
    dot inside a 64px wrapper; adjust per context with the :size / :wrap props.

    Sizes:
      - Standard topbar            : <x-connectivity-signal />
      - Mobile header (compact)    : <x-connectivity-signal :size="12" :wrap="36" />
      - Slim icon row, no padding  : <x-connectivity-signal :size="14" :wrap="44" />
--}}

<nav class="bg-white/90 shadow-sm">
    <div class="flex items-center gap-3 px-4 py-2">

        <a href="{{ url('/') }}" class="font-semibold text-gray-900">
            AccumenAI
        </a>

        {{-- The connectivity indicator, sitting next to the brand. --}}
        @if ($institute && ($institute->industry ?? '') === 'education')
            <a href="{{ route('exams.index', ['tab' => 'exams']) }}" class="btn btn-link p-0 me-2" title="{{ mawa_e('exams.title') }}">
                <i class="bi bi-clipboard-check me-1"></i>
            </a>
        @endif

        <x-connectivity-signal />

        <span class="ms-auto text-sm text-gray-500">
            Connection status
        </span>

        {{-- A second, compact instance is harmless: styles/scripts are
             emitted once via @once, so two instances share them. --}}
        <x-connectivity-signal :size="12" :wrap="36" class="md:hidden" />

    </div>
</nav>