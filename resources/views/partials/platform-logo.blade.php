{{-- AccumenAI platform logo (Admin → Settings → Appearance upload, else bundled mark).
     Usage: @include('partials.platform-logo', ['height' => 32]) --}}
<img src="{{ platform_logo_url() }}" alt="AccumenAI"
     style="height:{{ $height ?? 32 }}px;width:auto;vertical-align:middle;" @if(!empty($class))class="{{ $class }}"@endif>
