<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GalleryAlbum extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'gallery_albums';

    public $timestamps = false;

    protected $guarded = [];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(GalleryMedia::class);
    }
}
