<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SetContentSecurityPolicy
{
    /**
     * Add a strict Content-Security-Policy header to every response, and
     * share a nonce with views so the inline <script> tags emitted by
     * Livewire/Flux (@fluxAppearance, @fluxScripts, @vite) can be allow-
     * listed without relying on 'unsafe-inline' for scripts.
     *
     * The nonce is stored in the session rather than generated fresh per
     * request: wire:navigate swaps page content via background requests
     * without a real page load, so a script tag rendered during one of
     * those swaps still has to satisfy the CSP header the browser received
     * on the original, full document load. A per-request nonce would mismatch
     * as soon as any navigation happened; reusing one per session doesn't.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = $request->session()->get('csp_nonce');

        if (! $nonce) {
            $nonce = Str::random(40);
            $request->session()->put('csp_nonce', $nonce);
        }

        Vite::useCspNonce($nonce);
        View::share('cspNonce', $nonce);

        $response = $next($request);

        // avatars come from the OIDC provider's "picture" claim, an
        // arbitrary external host we can't know in advance - allowing any
        // https source is the pragmatic middle ground between 'self' (which
        // would silently break every avatar) and naming a specific host
        //
        // style-src needs 'unsafe-inline': Flux's dropdown/tooltip/popover
        // positioning sets the style attribute directly via JS (Floating UI),
        // which CSP governs independently of script-src - there's no nonce-
        // friendly way around this short of patching the library, and a
        // style attribute can't execute script, so the risk is low
        // the bracketed ws://[::1]:* form isn't valid CSP source syntax, so
        // it's left out - localhost/127.0.0.1 already cover the Vite dev
        // server's HMR socket for local development
        $connectSrc = "'self'".(app()->isLocal() ? ' ws://localhost:* ws://127.0.0.1:*' : '');

        // script-src needs 'unsafe-eval': Alpine.js (bundled by Livewire,
        // driving every Flux component - dropdowns, modals, toasts, the
        // sidebar) evaluates x-data/x-on/x-bind expressions via the Function
        // constructor at its core. Livewire's "csp_safe" bundle only makes
        // that failure degrade gracefully when unsafe-eval is missing - it
        // doesn't remove the requirement - so nothing here actually works
        // without it short of switching to Alpine's separate, much more
        // restrictive @alpinejs/csp build, which Flux itself doesn't support
        $policy = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' https: data:",
            "font-src 'self'",
            "connect-src {$connectSrc}",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $policy);

        return $response;
    }
}
