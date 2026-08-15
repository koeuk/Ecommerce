<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute as CastAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAddress extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'label', 'receiver_name', 'phone',
        'address_line1', 'address_line2', 'city', 'state', 'postal_code',
        'country_code', 'latitude', 'longitude',
        'is_default_shipping', 'is_default_billing',
    ];

    protected function casts(): array
    {
        return [
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function fullAddress(): CastAttribute
    {
        return CastAttribute::make(get: fn () => collect([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
        ])->filter()->implode(', '));
    }

    /**
     * Flattens the address for snapshotting onto an order. Orders store this
     * as JSON so later edits here never rewrite order history.
     */
    public function toSnapshot(): array
    {
        return [
            'receiver_name' => $this->receiver_name,
            'phone' => $this->phone,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country_code' => $this->country_code,
        ];
    }
}
