<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CouponTarget extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['coupon_id', 'target_type', 'target_id'];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
