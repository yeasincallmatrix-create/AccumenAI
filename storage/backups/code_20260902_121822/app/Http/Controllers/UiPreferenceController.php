<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Generic UI preferences (e.g. show/hide column visibility) for any
 * authenticated portal user. Values are stored on the account's
 * `preferences` JSON column so they survive logout and login.
 */
class UiPreferenceController extends Controller
{
    public function saveColumns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_.-]+$/'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', 'max:60'],
        ]);

        $request->user()->setPreference('columns_'.$data['key'], array_values($data['columns'] ?? []));

        return response()->json(['ok' => true]);
    }
}
