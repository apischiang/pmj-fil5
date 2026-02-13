<?php

namespace App\Filament\Resources\DeliveryOrders\Schemas;

use App\Models\PurchaseOrder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class DeliveryOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Section::make('Header')
                    ->columnSpan(4)
                    ->schema([
                        Select::make('purchase_order_id')
                            ->label('Purchase Order')
                            ->relationship('purchaseOrder', 'po_number')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(request()->query('purchase_order_id'))
                            ->live()
                            ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                if ($state && empty($get('items'))) {
                                    $po = PurchaseOrder::with('items')->find($state);
                                    if ($po) {
                                        $set('items', self::getPOItemsData($po));
                                    }
                                }
                            })
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (!$state) {
                                    $set('items', []);
                                    return;
                                }
                                $po = PurchaseOrder::with('items')->find($state);
                                if ($po) {
                                    $set('items', self::getPOItemsData($po));
                                }
                            }),
                        TextInput::make('do_number')
                            ->label('DO Number')
                            ->default('DO-' . date('Ymd') . '-' . rand(1000, 9999))
                            ->required(),
                        DatePicker::make('do_date')
                            ->label('Date')
                            ->default(now())
                            ->required(),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'confirmed' => 'Confirmed',
                                'shipped' => 'Shipped',
                                'received' => 'Received',
                            ])
                            ->default('draft')
                            ->required(),
                    ])->columns(1),

                Section::make('Items')
                    ->columnSpan(8)
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Grid::make(12)
                                    ->schema([
                                        TextInput::make('purchase_order_item_id')
                                            ->hidden()
                                            ->required(),
                                        TextInput::make('item_name')
                                            ->label('Item')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->formatStateUsing(function ($state, $record, Get $get) {
                                                if ($state) return $state;
                                                if ($record && $record->purchaseOrderItem) {
                                                     return $record->purchaseOrderItem->item_name ?? $record->purchaseOrderItem->material_number;
                                                }
                                                $poItemId = $get('purchase_order_item_id');
                                                if ($poItemId) {
                                                    $poItem = \App\Models\PurchaseOrderItem::find($poItemId);
                                                    return $poItem->item_name ?? $poItem->material_number;
                                                }
                                                return null;
                                            })
                                            ->columnSpan(12),
                                        TextInput::make('ordered_quantity')
                                            ->label('Ordered')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->numeric()
                                            ->formatStateUsing(function ($state, $record, Get $get) {
                                                if ($state) return $state;
                                                if ($record && $record->purchaseOrderItem) {
                                                     return $record->purchaseOrderItem->quantity;
                                                }
                                                $poItemId = $get('purchase_order_item_id');
                                                if ($poItemId) {
                                                    $poItem = \App\Models\PurchaseOrderItem::find($poItemId);
                                                    return $poItem->quantity;
                                                }
                                                return 0;
                                            })
                                            ->columnSpan(3),
                                        TextInput::make('previously_delivered')
                                            ->label('Prev. Delivered')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->numeric()
                                            ->formatStateUsing(function ($state, $record, Get $get) {
                                                if ($state !== null) return $state;
                                                // Calculate if not set in state (e.g. edit mode, but this field is not in DB)
                                                // Actually, on edit, we don't need to recalculate global delivered, 
                                                // unless we want to show how much was delivered APART from this record?
                                                // For simplicity, just show 0 or calculate if needed.
                                                // But recalculating requires querying ALL DO Items.
                                                $poItemId = $get('purchase_order_item_id');
                                                if ($poItemId) {
                                                    $delivered = \App\Models\DeliveryOrderItem::where('purchase_order_item_id', $poItemId)
                                                        ->where('delivery_order_id', '!=', $record?->delivery_order_id) // Exclude current DO
                                                        ->sum('quantity');
                                                    return $delivered;
                                                }
                                                return 0;
                                            })
                                            ->columnSpan(3),
                                        TextInput::make('quantity')
                                            ->label('Current Delivery')
                                            ->numeric()
                                            ->required()
                                            ->columnSpan(6),
                                    ]),
                            ])
                            ->addable(false)
                            ->deletable(true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function getPOItemsData(PurchaseOrder $po): array
    {
        return $po->items->map(function ($item) {
            $delivered = \App\Models\DeliveryOrderItem::where('purchase_order_item_id', $item->id)->sum('quantity');
            $remaining = max(0, $item->quantity - $delivered);

            return [
                'purchase_order_item_id' => $item->id,
                'item_name' => $item->item_name ?? $item->material_number ?? 'Unknown Item',
                'ordered_quantity' => $item->quantity,
                'previously_delivered' => $delivered,
                'quantity' => $remaining,
            ];
        })->toArray();
    }
}
