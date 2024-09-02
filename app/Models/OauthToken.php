<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OauthToken extends Model
{
    use HasFactory;
    protected $fillable = [
        'client_id',
        'client_secret',
        'code',
        'scope',
        'redirect_url',
        'state',
        'grant_type',
    ];
}
