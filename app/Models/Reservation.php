<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = ['reference', 'villa_id', 'guest_id', 'check_in', 'check_out', 'guests', 'nights', 'total_price', 'status'];
    protected function casts(): array { return ['check_in' => 'date', 'check_out' => 'date', 'total_price' => 'decimal:2']; }
    public function villa() { return $this->belongsTo(Villa::class); }
    public function guest() { return $this->belongsTo(User::class, 'guest_id'); }
    public function review() { return $this->hasOne(VillaReview::class); }
}
