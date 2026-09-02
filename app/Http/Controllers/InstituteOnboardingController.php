<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\IndustryRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Step-1 owner onboarding: pick country -> industry -> sub-industry.
 *
 * The choices are scoped by country (config/industry_rules.php) so the rest of
 * the onboarding form matches the country's industry rules. They are carried
 * into the workspace create form through the session and are never re-submitted
 * by the browser from step 2, which treats them as locked.
 */
class InstituteOnboardingController extends Controller
{
    public const SESSION_KEY = 'onboarding';

    public function step1(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isOwnerAccount(), 403);

        return view('workspace.onboarding', [
            'industries' => IndustryRules::industries(null),
            'countries' => config('countries', []),
            'rules' => Arr::except(config('industry_rules', []), ['global', 'capabilities']),
            'selection' => (array) session(self::SESSION_KEY, []),
        ]);
    }

    public function choose(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isOwnerAccount(), 403);

        $validated = self::validatedSelection($request->all());

        session([self::SESSION_KEY => $validated]);

        return redirect()->route('workspace.create');
    }

    /**
     * Validate a country -> industry -> sub-industry selection against the
     * current rules (config/countries.php + IndustryRules) and return the
     * normalized trio (sub_industry is null when none applies). Shared by the
     * authenticated onboarding and the pre-registration owner flow.
     */
    public static function validatedSelection(array $input): array
    {
        $data = validator($input, [
            'country' => ['required', 'string', 'max:80', Rule::in(array_keys(config('countries', [])))],
            'industry' => ['required', 'string', 'max:60', Rule::in(array_keys(IndustryRules::industries($input['country'] ?? null)))],
            'sub_industry' => ['nullable', 'string', 'max:60'],
        ])->validate();

        $country = $data['country'];
        $industry = $data['industry'];
        $subs = IndustryRules::subIndustries($country, $industry);
        $sub = null;

        if ($subs !== []) {
            $sub = $data['sub_industry'] ?? null;
            if ($sub === null || $sub === '') {
                throw ValidationException::withMessages([
                    'sub_industry' => 'Sub-industry is required for this industry in this country.',
                ]);
            }

            if (! array_key_exists($sub, $subs)) {
                throw ValidationException::withMessages([
                    'sub_industry' => 'The selected sub-industry is not available for this industry in this country.',
                ]);
            }
        }

        return [
            'country' => $country,
            'industry' => $industry,
            'sub_industry' => $sub,
        ];
    }

    /**
     * The stored, re-validated onboarding selection (country, industry,
     * sub_industry) or null when missing/invalid. Used by step 2 and cleared
     * once the institute is created.
     */
    public static function selection(): ?array
    {
        $selection = session(self::SESSION_KEY);
        if (! is_array($selection)) {
            return null;
        }

        $country = $selection['country'] ?? null;
        $industry = $selection['industry'] ?? null;
        $sub = $selection['sub_industry'] ?? null;

        if (! is_string($country) || ! array_key_exists($country, config('countries', []))) {
            return null;
        }

        if (! is_string($industry) || ! array_key_exists($industry, IndustryRules::industries($country))) {
            return null;
        }

        if ($sub !== null && ! array_key_exists($sub, IndustryRules::subIndustries($country, $industry))) {
            return null;
        }

        return [
            'country' => $country,
            'industry' => $industry,
            'sub_industry' => $sub,
        ];
    }

    public static function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
