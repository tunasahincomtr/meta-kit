<?php

namespace TunaSahincomtr\MetaKit\Models;

use Illuminate\Database\Eloquent\Model;

class MetaKitAlias extends Model
{
    protected $table = 'metakit_aliases';

    protected $fillable = [
        'domain',
        'old_path',
        'new_path',
    ];

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];
}

