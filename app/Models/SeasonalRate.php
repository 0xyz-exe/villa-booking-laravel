<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeasonalRate extends Model
{
    protected $fillable = ['villa_id', 'starts_on', 'ends_on', 'nightly_rate'];
    protected function casts(): array { return ['starts_on' => 'date', 'ends_on' => 'date', 'nightly_rate' => 'decimal:2']; }
    public function villa() { return $this->belongsTo(Villa::class); }
}
