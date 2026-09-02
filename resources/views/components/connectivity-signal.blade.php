{{--
    Connectivity Signal — a reusable online/offline status dot for app topbars.

    Three states, driven purely by the browser's real network events (no server):

        STATE 1 — stable        : solid green dot, soft static glow, no rings.
        STATE 2 — offline       : red dot fading on a ~1.8s "struggle" loop plus
                                  three staggered red ripple rings, indefinitely.
        STATE 3 — reconnecting  : solid green dot (no fading) with three green
                                  ripple rings, for exactly 5s, then settle to
                                  STATE 1.

    Usage:

        <x-connectivity-signal />
        <x-connectivity-signal :size="12" />                         compact mobile header
        <x-connectivity-signal :size="20" label-offline="Disconnected" />

    Props:
        @prop int    $size            dot diameter in px (default 16)
        @prop int    $wrap            wrapper box in px (default size * 4 = 64)
        @prop string $labelOnline     tooltip / aria label when stable
        @prop string $labelOffline    tooltip / aria label when offline
        @prop string $labelReconnecting  tooltip / aria label while reconnecting

    The two custom @keyframes (ripple + fade-struggle) and the Alpine component
    definition are emitted once per page via @once, whatever the instance count.

    Why Alpine, not Livewire: this is pure client-side browser connectivity — a
    server round-trip would be wasted overhead and would simply not fire while
    genuinely offline.

    No-Alpine cold load: with Alpine absent the element stays inert and the CSS
    defaults render a plain static green dot (rings at opacity 0) — zero console
    errors.
--}}
@props([
    'size' => 16,
    'wrap' => null,
    'labelOnline' => 'Online',
    'labelOffline' => 'Offline',
    'labelReconnecting' => 'Reconnecting',
])

@php
    $wrap ??= $size * 4;
    // Glow scales with the dot so compact variants stay a soft light, not a blob.
    $glowBlur   = round($size * 0.375, 2);
    $glowSpread = round($size * 0.19, 2);
@endphp

@once('connectivity-signal-assets')
<style>
    /* =====================================================================
       Connectivity signal — scoped styles.
       Namespaced under .cs-root / .cs-*, so it never leaks into the page.
       Per-instance sizing is carried by the CSS custom properties set inline
       on each rendered wrapper.
       ===================================================================== */

    .cs-root {
        /* Palette — matches the working vanilla-JS prototype (Bootstrap-style
           success/danger, no neon/pastel). */
        --cs-green: #198754;
        --cs-green-glow: rgba(25, 135, 84, 0.55);
        --cs-red: #dc3545;
        --cs-red-glow: rgba(220, 53, 69, 0.55);

        /* Fallbacks — normally overridden by the inline style attribute. */
        --cs-dot-size: 16px;
        --cs-stage: 64px;

        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: var(--cs-stage);
        height: var(--cs-stage);
        flex-shrink: 0;
        user-select: none;
    }

    .cs-ring,
    .cs-dot {
        box-sizing: border-box;
    }

    /* --- The dot ----------------------------------------------------------
       The base style IS STATE 1 (stable): green, steady, soft glow, completely
       static. A no-Alpine cold load therefore renders a correct resting state
       with no animation classes applied. */
    .cs-dot {
        width: var(--cs-dot-size);
        height: var(--cs-dot-size);
        border-radius: 9999px;
        background-color: var(--cs-green);
        box-shadow: 0 0 var(--cs-glow-blur) var(--cs-glow-spread) var(--cs-green-glow);
    }

    /* STATE 2 — offline: red + the fade-struggle breathing loop (opacity AND
       glow intensity, ~1.8s). Fading never happens in any green state. */
    .cs-dot--offline {
        background-color: var(--cs-red);
        animation: cs-fade-struggle 1.8s ease-in-out infinite;
    }

    /* --- Rings ------------------------------------------------------------
       Always in the DOM but sitting at opacity 0 until the root gains
       .is-rippling, so they stay hidden on a no-Alpine cold load without
       depending on x-show/x-cloak. */
    .cs-rings {
        position: absolute;
        inset: 0;
        --cs-ring-color: var(--cs-green);
        pointer-events: none;
    }

    .cs-rings--red   { --cs-ring-color: var(--cs-red); }
    .cs-rings--green { --cs-ring-color: var(--cs-green); }

    .cs-ring {
        position: absolute;
        left: 50%;
        top: 50%;
        width: var(--cs-dot-size);
        height: var(--cs-dot-size);
        margin-left: calc(var(--cs-dot-size) / -2);
        margin-top: calc(var(--cs-dot-size) / -2);
        border: calc(var(--cs-dot-size) / 6) solid var(--cs-ring-color);
        border-radius: 9999px;
        opacity: 0;
        pointer-events: none;
        will-change: transform, opacity;
        animation-name: none;
        animation-duration: 1.8s;
        animation-timing-function: cubic-bezier(0.22, 0.61, 0.36, 1);
        animation-delay: 0s;
        animation-iteration-count: infinite;
    }

    /* Three rings, staggered ~0.5s apart (negative delays start them all
       mid-cycle so the stagger is visible from the very first frame). */
    .cs-ring--2 { animation-delay: -0.5s; }
    .cs-ring--3 { animation-delay: -1s; }

    .cs-root.is-rippling .cs-ring {
        animation-name: cs-ripple;
    }

    /* Ripple: scale 1 -> 4.5 while fading opacity 0.65 -> 0. Used by rings. */
    @keyframes cs-ripple {
        0%   { transform: scale(1);   opacity: 0.65; }
        100% { transform: scale(4.5); opacity: 0; }
    }

    /* Fade-struggle: breathing opacity AND glow between 1.0 and ~0.3.
       Used ONLY by the red/offline dot (STATE 2). */
    @keyframes cs-fade-struggle {
        0%, 100% {
            opacity: 1;
            box-shadow: 0 0 var(--cs-glow-blur) var(--cs-glow-spread) var(--cs-red-glow);
        }
        50% {
            opacity: 0.3;
            box-shadow: 0 0 2px 0 rgba(220, 53, 69, 0.25);
        }
    }

    /* Screen-reader-only live label (updates via x-text). */
    .cs-sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        border: 0;
        clip: rect(0, 0, 0, 0);
        overflow: hidden;
        white-space: nowrap;
    }

    /* Respect reduced-motion: no pulsing, no ripple — colour alone still
       communicates the state. */
    @media (prefers-reduced-motion: reduce) {
        .cs-ring { display: none; }
        .cs-dot--offline {
            animation: none;
            box-shadow: 0 0 var(--cs-glow-blur) var(--cs-glow-spread) var(--cs-red-glow);
        }
    }
</style>

<script>
    (function () {
        'use strict';

        /* STATE 3 -> STATE 1 settle window, in milliseconds. */
        var SETTLE_MS = 5000;

        /* Factory returning the Alpine component data object. Defined as a
           plain function so the component works no matter whether Alpine has
           booted before or after this classic script runs. */
        function mawaConnectivitySignal(labels) {
            labels = labels || {};

            return {
                /* 'stable' | 'offline' | 'reconnecting' */
                state: 'stable',
                isRippling: false,   // toggles the ripple rings
                settleTimer: null,   // pending STATE 3 -> STATE 1 timeout id
                statusLabel: labels.online || 'Online',
                labels: labels,
                _bound: false,       // guards against double binding

                /* Runs when Alpine boots the component. Listener wiring happens
                   here (plain addEventListener, as required) so the cancel-
                   stale-timer logic is co-located with the state changes. */
                init: function () {
                    var self = this;

                    // Idempotency guard: Alpine auto-invokes init() AND x-init
                    // may call it again — never bind the same listeners twice.
                    if (this._bound) { return; }
                    this._bound = true;

                    // Cold start reflects the real browser state rather than
                    // always defaulting to the resting state.
                    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                        this.gotoOffline();
                    } else {
                        this.gotoStable();
                    }

                    // Closure-bound handlers keep `this` pointing at the
                    // component no matter how the event fires.
                    this.boundOnline = function () {
                        self.gotoReconnecting();
                    };

                    this.boundOffline = function () {
                        // Spec: a running State-3 "settle to stable" timer must
                        // be cancelled — a stale 5s timer must never flip us to
                        // stable while we are genuinely offline again.
                        if (self.settleTimer) {
                            window.clearTimeout(self.settleTimer);
                            self.settleTimer = null;
                        }
                        self.gotoOffline();
                    };

                    window.addEventListener('online', this.boundOnline);
                    window.addEventListener('offline', this.boundOffline);
                },

                /* Alpine teardown hook (SPA node removal): clear the timer and
                   listeners so nothing fires against a detached component. */
                destroy: function () {
                    if (this.settleTimer) {
                        window.clearTimeout(this.settleTimer);
                        this.settleTimer = null;
                    }
                    if (this.boundOnline) { window.removeEventListener('online', this.boundOnline); }
                    if (this.boundOffline) { window.removeEventListener('offline', this.boundOffline); }
                    this.boundOnline = null;
                    this.boundOffline = null;
                    this._bound = false;
                },

                /* STATE 1 — stable / online (resting). */
                gotoStable: function () {
                    this.state = 'stable';
                    this.isRippling = false;
                    this.statusLabel = this.labels.online || 'Online';
                },

                /* STATE 2 — offline: red dot + red rings, indefinite. */
                gotoOffline: function () {
                    this.state = 'offline';
                    this.isRippling = true;
                    this.statusLabel = this.labels.offline || 'Offline';
                },

                /* STATE 3 — reconnecting: green, solid (no fading), green rings
                   ripple for exactly SETTLE_MS, then settle to STATE 1. */
                gotoReconnecting: function () {
                    var self = this;

                    // Restart the settle window if online fires again mid-settle.
                    if (this.settleTimer) {
                        window.clearTimeout(this.settleTimer);
                    }

                    this.state = 'reconnecting';
                    this.isRippling = true;
                    this.statusLabel = this.labels.reconnecting || 'Reconnecting';

                    this.settleTimer = window.setTimeout(function () {
                        self.settleTimer = null;
                        self.gotoStable();
                    }, SETTLE_MS);
                }
            };
        }

        /* Alpine evaluates `x-data` expressions against the window scope, so
           exposing the factory globally lets the component bootstrap even when
           Alpine boots after this classic script (deferred Vite build, CDN). */
        window.mawaConnectivitySignal = mawaConnectivitySignal;

        // If Alpine is already loaded (non-deferred CDN), also register the
        // idiomatic Alpine.data variant — behaviour is identical either way.
        if (window.Alpine && typeof window.Alpine.data === 'function') {
            window.Alpine.data('mawaConnectivitySignal', function () {
                return mawaConnectivitySignal();
            });
        }
    })();
</script>
@endonce

<div
    x-data="mawaConnectivitySignal({{ \Illuminate\Support\Js::from(['online' => $labelOnline, 'offline' => $labelOffline, 'reconnecting' => $labelReconnecting]) }})"
    x-init="init()"
    {{ $attributes->merge(['class' => 'cs-root relative inline-flex shrink-0 items-center justify-center']) }}
    :class="{ 'is-rippling': isRippling }"
    role="img"
    :aria-label="statusLabel"
    :title="statusLabel"
    style="--cs-dot-size: {{ $size }}px; --cs-stage: {{ $wrap }}px; --cs-glow-blur: {{ $glowBlur }}px; --cs-glow-spread: {{ $glowSpread }}px; width: var(--cs-stage); height: var(--cs-stage);"
>
    <span
        class="cs-rings"
        :class="{ 'cs-rings--red': state === 'offline', 'cs-rings--green': state !== 'offline' }"
        aria-hidden="true"
    >
        <span class="cs-ring cs-ring--1"></span>
        <span class="cs-ring cs-ring--2"></span>
        <span class="cs-ring cs-ring--3"></span>
    </span>

    <span
        class="cs-dot"
        :class="{ 'cs-dot--offline': state === 'offline' }"
        aria-hidden="true"
    ></span>

    <span class="cs-sr-only" x-text="statusLabel"></span>
</div>