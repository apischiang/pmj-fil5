<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'item_sequence',
        'item_name', // For Outgoing
        'material_number', // For Incoming
        'description',
        'quantity',
        'uom',
        'delivery_date',
        'unit_price',
        'net_value', // Used for both (total_price alias)
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
