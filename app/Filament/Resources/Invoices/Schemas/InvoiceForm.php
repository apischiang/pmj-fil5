<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class InvoiceForm
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
                                $set('customer_id', null);
                                $set('vendor_id', null);
                            }),

                        Select::make('customer_id')
                            ->relationship('customer', 'company_name')
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

                        TextInput::make('invoice_number')
                            ->default('INV-' . date('Ymd') . '-' . rand(1000, 9999))
                            ->required(),
                        DatePicker::make('date')
                            ->default(now())
                            ->required(),
                        DatePicker::make('due_date'),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'sent' => 'Sent', // For Sales
                                'posted' => 'Posted', // For Procurement
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->required(),
                        Select::make('payment_status')
                            ->options([
                                'unpaid' => 'Unpaid',
                                'partial' => 'Partial',
                                'paid' => 'Paid',
                            ])
                            ->default('unpaid')
                            ->required(),
                    ])->columns(2),

                Section::make('Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                TextInput::make('item_name')
                                    ->required(),
                                Textarea::make('description')
                                    ->rows(2),
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateLineTotal($set, $get)),
                                        TextInput::make('unit_price')
                                            ->numeric()
                                            ->default(0)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateLineTotal($set, $get)),
                                        TextInput::make('discount')
                                            ->numeric()
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateLineTotal($set, $get)),
                                        TextInput::make('vat_rate')
                                            ->label('VAT %')
                                            ->numeric()
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateLineTotal($set, $get)),
                                    ]),
                                TextInput::make('amount')
                                    ->disabled()
                                    ->dehydrated()
                                    ->numeric(),
                            ])
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateGrandTotal($set, $get))
                            ->columnSpanFull(),
                    ]),

                Section::make('Totals')
                    ->schema([
                        TextInput::make('subtotal')
                            ->numeric()
                            ->readOnly()
                            ->prefix('IDR'),
                        TextInput::make('tax_amount')
                            ->numeric()
                            ->readOnly()
                            ->prefix('IDR'),
                        TextInput::make('discount_amount')
                            ->numeric()
                            ->readOnly()
                            ->prefix('IDR'),
                        TextInput::make('grand_total')
                            ->numeric()
                            ->readOnly()
                            ->prefix('IDR'),
                    ])->columns(2),
                
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function calculateLineTotal(Set $set, Get $get): void
    {
        $qty = (float) $get('quantity');
        $price = (float) $get('unit_price');
        $discount = (float) $get('discount');
        $vatRate = (float) $get('vat_rate');

        $subtotal = ($qty * $price) - $discount;
        $amount = $subtotal * (1 + ($vatRate / 100));

        $set('amount', number_format($amount, 2, '.', ''));
    }

    public static function calculateGrandTotal(Set $set, Get $get): void
    {
        $items = $get('items');
        $subtotal = 0;
        $totalTax = 0;
        $totalDiscount = 0;
        $grandTotal = 0;

        if ($items) {
            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 0);
                $price = (float) ($item['unit_price'] ?? 0);
                $discount = (float) ($item['discount'] ?? 0);
                $vatRate = (float) ($item['vat_rate'] ?? 0);

                $lineSubtotal = ($qty * $price);
                $lineDiscount = $discount; 
                
                $taxable = $lineSubtotal - $lineDiscount;
                $tax = $taxable * ($vatRate / 100);
                
                $subtotal += $lineSubtotal;
                $totalDiscount += $lineDiscount;
                $totalTax += $tax;
            }
        }
        
        $grandTotal = $subtotal - $totalDiscount + $totalTax;

        $set('subtotal', number_format($subtotal, 2, '.', ''));
        $set('tax_amount', number_format($totalTax, 2, '.', ''));
        $set('discount_amount', number_format($totalDiscount, 2, '.', ''));
        $set('grand_total', number_format($grandTotal, 2, '.', ''));
    }
}
