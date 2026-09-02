<?php

namespace App\Geo\Contracts;

use App\Models\Country;

/**
 * Boundary between the address UI / importer and wherever geography data comes
 * from. Today a local data-package reader implements it; tomorrow an external
 * geography API can provide the exact same stream of records without the
 * address UI changing at all.
 */
interface GeoDataProvider
{
    /**
     * Stream raw location records one at a time (never the whole set in memory).
     *
     * @return iterable<array{
     *     level: int,
     *     code: string,
     *     name: string,
     *     parent_code: ?string,
     *     postal_code: ?string,
     *     latitude: ?string,
     *     longitude: ?string,
     * }>
     */
    public function records(): iterable;

    public function providedCountry(): ?Country;
}
