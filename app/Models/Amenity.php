<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Amenity extends Model
{
    protected $fillable = ['name'];
    public function villas() { return $this->belongsToMany(Villa::class); }
}
