<?php

namespace App\Filament\Resources\Quotations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class QuotationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // Menggunakan Group agar bisa full width tanpa grid kolom pembatas
                Group::make()
                    ->columnSpanFull()
                    ->schema([
                        // 1. Header Section (Customer & Details)
                        Section::make('Document Details')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Grid::make(4) // Grid 4 kolom agar rapi seperti tampilan "sebelumnya"
                                    ->schema([
                                        Select::make('customer_id')
                                            ->label('Customer')
                                            ->relationship('customer', 'company_name')
                                            ->searchable()
                                            ->required()
                                            ->preload()
                                            ->createOptionForm([
                                                TextInput::make('company_name')->required(),
                                                TextInput::make('name')->label('Contact Person'),
                                                TextInput::make('email')->email(),
                                            ])
                                            ->columnSpan(2), // Customer ambil 2 kolom (50%)

                                        TextInput::make('quotation_number')
                                            ->label('Quotation #')
                                            ->default('QT-' . date('Ymd') . '-' . rand(1000, 9999))
                                            ->required()
                                            ->columnSpan(1),

                                        Select::make('status')
                                            ->options([
                                                'draft' => 'Draft',
                                                'sent' => 'Sent',
                                                'accepted' => 'Accepted',
                                                'declined' => 'Declined',
                                            ])
                                            ->default('draft')
                                            ->required()
                                            ->native(false)
                                            ->columnSpan(1),

                                        DatePicker::make('date')
                                            ->label('Issue Date')
                                            ->default(now())
                                            ->required()
                                            ->columnSpan(1),

                                        DatePicker::make('expiry_date')
                                            ->label('Valid Until')
                                            ->minDate(now())
                                            ->columnSpan(1),
                                    ]),
                            ]),

                        // 2. Items Section (Full Width)
                        Section::make('Line Items')
                            ->icon('heroicon-o-shopping-cart')
                            ->schema([
                                Repeater::make('items')
                                    ->hiddenLabel()
                                    ->relationship()
                                    ->schema([
                                        Grid::make(12)
                                            ->schema([
                                                // Item & Desc (Span 5)
                                                Group::make()
                                                    ->columnSpan(5)
                                                    ->schema([
                                                        TextInput::make('item_name')
                                                            ->label('Item')
                                                            ->required()
                                                            ->placeholder('Item Name'),
                                                        TextInput::make('description')
                                                            ->label('Description')
                                                            ->placeholder('Description (optional)'),
                                                    ]),

                                                // Metrics Row (Span 7)
                                                TextInput::make('quantity')
                                                    ->label('Qty')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->required()
                                                    ->columnSpan(1)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateLineTotal($set, $get)),

                                                TextInput::make('unit_price')
                                                    ->label('Price')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->required()
                                                    ->columnSpan(2)
                                                    ->prefix('Rp')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateLineTotal($set, $get)),

                                                TextInput::make('discount')
                                                    ->label('Disc %')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->columnSpan(1)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateLineTotal($set, $get)),

                                                TextInput::make('vat_rate')
                                                    ->label('VAT %')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->columnSpan(1)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateLineTotal($set, $get)),

                                                TextInput::make('amount')
                                                    ->label('Total')
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->numeric()
                                                    ->columnSpan(2)
                                                    ->prefix('Rp'),
                                            ]),
                                    ])
                                    ->defaultItems(1)
                                    ->cloneable()
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['item_name'] ?? null)
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::calculateGrandTotal($set, $get))
                                    ->columnSpanFull(),
                            ]),

                        // 3. Footer Section (Notes & Totals)
                        Section::make()
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        // Notes (Left 2/3)
                                        Group::make()
                                            ->columnSpan(2)
                                            ->schema([
                                                Textarea::make('notes')
                                                    ->label('Notes / Terms')
                                                    ->rows(4)
                                                    ->placeholder('Enter payment terms, delivery notes, or other conditions...'),
                                            ]),

                                        // Totals (Right 1/3)
                                        Group::make()
                                            ->columnSpan(1)
                                            ->schema([
                                                Section::make('Summary')
                                                    ->schema([
                                                        TextInput::make('subtotal')
                                                            ->label('Subtotal')
                                                            ->numeric()
                                                            ->readOnly()
                                                            ->prefix('Rp'),
                                                        
                                                        Grid::make(2)
                                                            ->schema([
                                                                TextInput::make('discount_amount')
                                                                    ->label('Discount')
                                                                    ->numeric()
                                                                    ->readOnly()
                                                                    ->prefix('Rp'),
                                                                TextInput::make('tax_amount')
                                                                    ->label('Tax')
                                                                    ->numeric()
                                                                    ->readOnly()
                                                                    ->prefix('Rp'),
                                                            ]),

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
        $discount = (float) $get('discount');
        $vatRate = (float) $get('vat_rate');

        $lineSubtotal = $qty * $price;
        $discountAmount = $lineSubtotal * ($discount / 100);
        $taxable = $lineSubtotal - $discountAmount;
        $amount = $taxable * (1 + ($vatRate / 100));

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
                $lineDiscountAmount = $lineSubtotal * ($discount / 100);
                
                $taxable = $lineSubtotal - $lineDiscountAmount;
                $tax = $taxable * ($vatRate / 100);
                
                $subtotal += $lineSubtotal;
                $totalDiscount += $lineDiscountAmount;
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
