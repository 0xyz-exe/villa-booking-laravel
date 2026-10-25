<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VillaReview extends Model
{
    protected $fillable = ['reservation_id', 'guest_id', 'rating', 'comment'];
    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function guest() { return $this->belongsTo(User::class, 'guest_id'); }
}
