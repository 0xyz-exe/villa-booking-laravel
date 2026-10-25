<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Villa extends Model
{
    protected $fillable = ['owner_id', 'name', 'location', 'description', 'guest_capacity', 'nightly_rate', 'minimum_stay', 'image_path', 'active'];
    protected function casts(): array { return ['guest_capacity' => 'integer', 'nightly_rate' => 'decimal:2', 'minimum_stay' => 'integer', 'active' => 'boolean']; }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function amenities() { return $this->belongsToMany(Amenity::class); }
    public function seasonalRates() { return $this->hasMany(SeasonalRate::class); }
    public function reservations() { return $this->hasMany(Reservation::class); }
    public function reviews() { return $this->hasManyThrough(VillaReview::class, Reservation::class); }
}
