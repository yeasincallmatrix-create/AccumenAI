<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryMedia extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'gallery_media';

    public $timestamps = false;

    protected $guarded = [];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class);
    }
}
