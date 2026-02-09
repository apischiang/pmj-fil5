<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Header')
                    ->schema([
                        Select::make('type')
                            ->options([
                                'in' => 'Incoming (Sales)',
                                'out' => 'Outgoing (Procurement)',
                            ])
                            ->default('in')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('buyer_id', null);
                                $set('vendor_id', null);
                            }),
                        
                        Select::make('buyer_id')
                            ->label('Buyer (Customer)')
                            ->relationship('buyer', 'company_name')
                            ->searchable()
                            ->required()
                            ->preload()
                            ->visible(fn (Get $get) => $get('type') === 'in'),
                            
                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->relationship('vendor', 'company_name')
                            ->searchable()
                            ->required()
                            ->preload()
                            ->visible(fn (Get $get) => $get('type') === 'out'),

                        TextInput::make('po_number')
                            ->required(),
                        DatePicker::make('po_date')
                            ->default(now())
                            ->required(),
                        
                        TextInput::make('purchaser_name')
                            ->label('Purchaser')
                            ->placeholder('e.g., S01 - PS Stamping')
                            ->visible(fn (Get $get) => $get('type') === 'in'),
                            
                        TextInput::make('payment_term')
                            ->placeholder('e.g., Payable immediately')
                            ->visible(fn (Get $get) => $get('type') === 'in'),
                            
                        Select::make('currency')
                            ->options([
                                'IDR' => 'IDR',
                                'USD' => 'USD',
                                'EUR' => 'EUR',
                            ])
                            ->default('IDR')
                            ->required(),
                        Select::make('status')
                            ->options([
                                'open' => 'Open',
                                'processed' => 'Processed',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('open')
                            ->required(),
                        FileUpload::make('file_attachment')
                            ->directory('purchase-orders')
                            ->visibility('private')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('item_sequence')
                                            ->label('Item No.')
                                            ->default('00010')
                                            ->visible(fn (Get $get) => $get('../../type') === 'in'),
                                        TextInput::make('material_number')
                                            ->label('Material')
                                            ->visible(fn (Get $get) => $get('../../type') === 'in'),
                                        TextInput::make('item_name')
                                            ->label('Item Name')
                                            ->required()
                                            ->visible(fn (Get $get) => $get('../../type') === 'out'),
                                        Textarea::make('description')
                                            ->columnSpan(2),
                                    ]),
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateLineTotal($set, $get)),
                                        TextInput::make('uom')
                                            ->label('Unit')
                                            ->default('PC')
                                            ->visible(fn (Get $get) => $get('../../type') === 'in'),
                                        DatePicker::make('delivery_date')
                                            ->visible(fn (Get $get) => $get('../../type') === 'in'),
                                        TextInput::make('unit_price')
                                            ->numeric()
                                            ->default(0)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateLineTotal($set, $get)),
                                    ]),
                                TextInput::make('net_value')
                                    ->label('Total Price')
                                    ->disabled()
                                    ->dehydrated()
                                    ->numeric(),
                            ])
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateTotals($set, $get))
                            ->columnSpanFull(),
                    ]),

                Section::make('Totals')
                    ->schema([
                        TextInput::make('tax_rate')
                            ->label('Tax Rate %')
                            ->numeric()
                            ->default(11)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateTotals($set, $get)),
                        TextInput::make('tax_amount')
                            ->numeric()
                            ->readOnly()
                            ->prefix('IDR'),
                        TextInput::make('grand_total')
                            ->numeric()
                            ->readOnly()
                            ->prefix('IDR'),
                    ])->columns(3),
            ]);
    }

    public static function calculateLineTotal(Set $set, Get $get): void
    {
        $qty = (float) $get('quantity');
        $price = (float) $get('unit_price');
        $set('net_value', number_format($qty * $price, 2, '.', ''));
    }

    public static function calculateTotals(Set $set, Get $get): void
    {
        $items = $get('items');
        $taxRate = (float) $get('tax_rate');
        $totalNet = 0;

        if ($items) {
            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 0);
                $price = (float) ($item['unit_price'] ?? 0);
                $totalNet += ($qty * $price);
            }
        }

        $taxAmount = $totalNet * ($taxRate / 100);
        $grandTotal = $totalNet + $taxAmount;

        $set('tax_amount', number_format($taxAmount, 2, '.', ''));
        $set('grand_total', number_format($grandTotal, 2, '.', ''));
    }
}
