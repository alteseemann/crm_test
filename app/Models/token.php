<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class token extends Model
{
    use HasFactory;
    public $timestamps = true;
    protected $fillable=['refresh_token','access_token','token_type','expires_in'];
}
