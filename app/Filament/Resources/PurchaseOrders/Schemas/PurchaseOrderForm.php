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
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Group::make()
                    ->columnSpanFull()
                    ->schema([
                        // 1. Header Section
                        Section::make('Document Details')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        Select::make('type')
                                            ->label('PO Type')
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
                                            })
                                            ->columnSpan(1),

                                        Group::make()
                                            ->columnSpan(2)
                                            ->schema([
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
                                            ]),

                                        TextInput::make('po_number')
                                            ->label('PO Number')
                                            ->required()
                                            ->columnSpan(1),

                                        Select::make('status')
                                            ->options([
                                                'open' => 'Open',
                                                'processed' => 'Processed',
                                                'completed' => 'Completed',
                                                'cancelled' => 'Cancelled',
                                            ])
                                            ->default('open')
                                            ->required()
                                            ->native(false)
                                            ->columnSpan(1),

                                        DatePicker::make('po_date')
                                            ->label('PO Date')
                                            ->default(now())
                                            ->required()
                                            ->columnSpan(1),
                                            
                                        // Row 2
                                        TextInput::make('purchaser_name')
                                            ->label('Purchaser')
                                            ->placeholder('e.g., S01 - PS Stamping')
                                            ->visible(fn (Get $get) => $get('type') === 'in')
                                            ->columnSpan(2),
                                            
                                        TextInput::make('payment_term')
                                            ->placeholder('e.g., Payable immediately')
                                            ->visible(fn (Get $get) => $get('type') === 'in')
                                            ->columnSpan(2),
                                            
                                        Select::make('currency')
                                            ->options([
                                                'IDR' => 'IDR',
                                                'USD' => 'USD',
                                                'EUR' => 'EUR',
                                            ])
                                            ->default('IDR')
                                            ->required()
                                            ->columnSpan(1),
                                    ]),
                            ]),

                        // 2. Items Section
                        Section::make('Line Items')
                            ->icon('heroicon-o-shopping-cart')
                            ->schema([
                                Repeater::make('items')
                                    ->hiddenLabel()
                                    ->relationship()
                                    ->schema([
                                        Grid::make(24)
                                            ->schema([
                                                // Item Details (Span 5)
                                                Group::make()
                                                    ->columnSpan(16)
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
                                                            ->label('Description')
                                                            ->rows(2),
                                                    ]),

                                                // Metrics (Span 7)
                                                Group::make()
                                                    ->columnSpan(8)
                                                    ->schema([
                                                TextInput::make('quantity')
                                                    ->label('Qty')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->required()
                                                    ->columnSpan(1)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateLineTotal($set, $get)),

                                                TextInput::make('uom')
                                                    ->label('Unit')
                                                    ->default('PC')
                                                    ->visible(fn (Get $get) => $get('../../type') === 'in')
                                                    ->columnSpan(1),

                                                DatePicker::make('delivery_date')
                                                    ->label('Delivery')
                                                    ->visible(fn (Get $get) => $get('../../type') === 'in')
                                                    ->columnSpan(2),

                                                TextInput::make('unit_price')
                                                    ->label('Price')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->required()
                                                    ->columnSpan(2)
                                                    ->prefix('Rp')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateLineTotal($set, $get)),

                                                TextInput::make('net_value')
                                                    ->label('Total')
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->numeric()
                                                    ->columnSpan(2)
                                                    ->prefix('Rp'),
                                                    ]),
                                            ]),
                                    ])
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateTotals($set, $get))
                                    ->columnSpanFull()
                                    ->cloneable()
                                    ->collapsible(),
                            ]),

                        // 3. Footer Section
                        Section::make()
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        // Left Column (Attachments & Notes)
                                        Group::make()
                                            ->columnSpan(2)
                                            ->schema([
                                                FileUpload::make('file_attachment')
                                                    ->label('Attachment')
                                                    ->directory('purchase-orders')
                                                    ->visibility('private'),
                                            ]),

                                        // Right Column (Totals)
                                        Group::make()
                                            ->columnSpan(1)
                                            ->schema([
                                                Section::make('Summary')
                                                    ->schema([
                                                        TextInput::make('tax_rate')
                                                            ->label('Tax Rate %')
                                                            ->numeric()
                                                            ->default(11)
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateTotals($set, $get)),
                                                        
                                                        TextInput::make('tax_amount')
                                                            ->label('Tax Amount')
                                                            ->numeric()
                                                            ->readOnly()
                                                            ->prefix('Rp'),
                                                            
                                                        TextInput::make('grand_total')
                                                            ->label('Grand Total')
                                                            ->numeric()
                                                            ->readOnly()
                                                            ->prefix('Rp')
                                                            ->extraInputAttributes(['class' => 'text-2xl font-bold text-primary-600']),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ]),
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
