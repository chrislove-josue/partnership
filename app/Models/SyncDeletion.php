<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncDeletion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'entity',
        'entity_id',
        'deleted_at',
    ];

    protected $casts = [
        'deleted_at' => 'immutable_datetime',
    ];
}
