<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'company_name',
        'initial',
        'address',
        'vat_number',
        'email',
        'phone',
        'created_by',
    ];

    protected static function booted()
    {
        static::creating(function ($customer) {
            if (Auth::check()) {
                $customer->created_by = Auth::id();
            }
        });
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'buyer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
