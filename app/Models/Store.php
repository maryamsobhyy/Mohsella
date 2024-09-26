<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;
    protected $fillable = [
        'salla_store_id',
        'email',
        'user_id',
        'name',
        'salla_user_id',
        'entity',
        'status',
        'description',
        'domain',
        'type',
        'plan',

    ];
}
