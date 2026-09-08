<?php

namespace App\Http\Middleware;

use App\Support\NameCaser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global person-name normalizer: every First Name / Last Name (plus
 * father/mother/guardian/middle names) arriving via HTTP is converted to
 * Initial Caps before validation/controllers run.
 *
 * Covers web + api, top-level keys and one level of nesting
 * (e.g. student[first_name], lead[first_name]).
 */
class NormalizePersonNames
{
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        if (! empty($input)) {
            $normalized = $this->normalizeArray($input);
            if ($normalized !== $input) {
                $request->merge($normalized);
            }
        }

        return $next($request);
    }

    private function normalizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && is_string($key) && NameCaser::isNameKey($key)) {
                $data[$key] = NameCaser::title($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->normalizeArray($value);
            }
        }

        return $data;
    }
}
