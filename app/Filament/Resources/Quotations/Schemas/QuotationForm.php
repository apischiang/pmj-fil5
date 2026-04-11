<?php

namespace App\Filament\Resources\Quotations\Schemas;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
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
                                            ->getOptionLabelFromRecordUsing(
                                                fn (Customer $record): string => self::formatCustomerOptionLabel($record)
                                            )
                                            ->searchable(['name', 'company_name'])
                                            ->required()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                if (! $state) {
                                                    return;
                                                }
                                                $customer = Customer::find($state);
                                                if (! $customer) {
                                                    return;
                                                }

                                                $initial = strtoupper($customer->initial ?? 'XX');
                                                $year = date('y');
                                                $month = date('m');
                                                $prefix = "PMJ/{$initial}/{$year}/{$month}/";

                                                // Find last number for this prefix pattern to increment
                                                $lastQuotation = Quotation::where('quotation_number', 'like', $prefix.'%')
                                                    ->orderBy('quotation_number', 'desc')
                                                    ->first();

                                                if ($lastQuotation) {
                                                    // Extract the last 3 digits
                                                    $lastSequence = intval(substr($lastQuotation->quotation_number, -3));
                                                    $newSequence = str_pad($lastSequence + 1, 3, '0', STR_PAD_LEFT);
                                                } else {
                                                    $newSequence = '001';
                                                }

                                                $set('quotation_number', $prefix.$newSequence);
                                            })
                                            ->createOptionForm([
                                                TextInput::make('company_name')->required(),
                                                TextInput::make('initial')->label('Initial (e.g. M)')->required()->maxLength(5),
                                                TextInput::make('name')->label('Buyer Name'),
                                                TextInput::make('email')->email(),
                                            ])
                                            ->columnSpan(2), // Customer ambil 2 kolom (50%)

                                        TextInput::make('quotation_number')
                                            ->label('Quotation #')
                                            ->readOnly()
                                            ->required()
                                            ->unique('quotations', 'quotation_number', ignoreRecord: true)
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

                                        Hidden::make('salesperson_id')
                                            ->required()
                                            ->default(fn (): ?int => auth()->id())
                                            ->afterStateHydrated(function (Set $set, $state): void {
                                                if (filled($state) || ! auth()->check()) {
                                                    return;
                                                }

                                                $set('salesperson_id', auth()->id());
                                            })
                                            ->dehydrated(),

                                        TextInput::make('salesperson_display')
                                            ->label('Salesperson')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->formatStateUsing(function ($state, $record, Get $get): string {
                                                if (filled($state)) {
                                                    return (string) $state;
                                                }

                                                if ($record?->salesperson) {
                                                    return (string) $record->salesperson->name;
                                                }

                                                $salespersonId = $get('salesperson_id');

                                                if ($salespersonId) {
                                                    return (string) (User::find($salespersonId)?->name ?? '-');
                                                }

                                                return (string) (auth()->user()?->name ?? '-');
                                            })
                                            ->columnSpan(2),
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
                                        Grid::make(24)
                                            ->schema([
                                                // Item & Desc (Span 4)
                                                Group::make()
                                                    ->columnSpan(16)
                                                    ->schema([
                                                        TextInput::make('item_name')
                                                            ->label('Item')
                                                            ->required()
                                                            ->placeholder('Item Name'),
                                                        Textarea::make('description')
                                                            ->label('Description')
                                                            ->placeholder('Description (optional)')
                                                            ->rows(4)
                                                            ->autosize(),

                                                        // Image (Span 1)
                                                        FileUpload::make('image')
                                                            ->label('Attachment')
                                                            ->directory('quotation-items')
                                                            ->columnSpan(1),
                                                    ]),

                                                // Metrics Row
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
                                                            ->label('UoM')
                                                            ->placeholder('Unit')
                                                            ->columnSpan(1),

                                                        TextInput::make('unit_price')
                                                            ->label('Price')
                                                            ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 0)
                                                            ->numeric()
                                                            ->default(null)
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

                                                        Hidden::make('vat_rate')
                                                            ->default(fn (Get $get): float => $get('../../has_vat') ? 11 : 0),

                                                        TextInput::make('amount')
                                                            ->label('Total')
                                                            ->disabled()
                                                            ->dehydrated()
                                                            ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 0)
                                                            ->numeric()
                                                            ->columnSpan(2)
                                                            ->prefix('Rp'),
                                                    ]),
                                            ]),
                                    ])
                                    ->defaultItems(1)
                                    ->cloneable()
                                    ->collapsible()
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
                                                        Toggle::make('has_vat')
                                                            ->label('Apply VAT 11%')
                                                            ->dehydrated(false)
                                                            ->inline(false)
                                                            ->live()
                                                            ->default(false)
                                                            ->afterStateHydrated(function (Set $set, Get $get, $state): void {
                                                                if ($state !== null) {
                                                                    return;
                                                                }

                                                                $hasVat = collect($get('items') ?? [])
                                                                    ->contains(fn (array $item): bool => (float) ($item['vat_rate'] ?? 0) > 0);

                                                                $set('has_vat', $hasVat);
                                                            })
                                                            ->afterStateUpdated(
                                                                fn (Set $set, Get $get, bool $state) => self::syncVatRateForItems($set, $get, $state)
                                                            ),

                                                        TextInput::make('subtotal')
                                                            ->label('Subtotal')
                                                            ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 0)
                                                            ->numeric()
                                                            ->readOnly()
                                                            ->prefix('Rp'),

                                                        Grid::make(2)
                                                            ->schema([
                                                                TextInput::make('discount_amount')
                                                                    ->label('Discount')
                                                                    ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 0)
                                                                    ->numeric()
                                                                    ->readOnly()
                                                                    ->prefix('Rp'),
                                                                TextInput::make('tax_amount')
                                                                    ->label('Tax')
                                                                    ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 0)
                                                                    ->numeric()
                                                                    ->readOnly()
                                                                    ->prefix('Rp'),
                                                            ]),

                                                        TextInput::make('grand_total')
                                                            ->label('Grand Total')
                                                            ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 0)
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

        $lineSubtotal = $qty * $price;
        $discountAmount = $lineSubtotal * ($discount / 100);
        $amount = $lineSubtotal - $discountAmount;

        $set('amount', number_format($amount, 0, '.', ''));
    }

    public static function calculateGrandTotal(Set $set, Get $get): void
    {
        self::updateQuotationTotals($set, $get('items'));
    }

    public static function syncVatRateForItems(Set $set, Get $get, bool $hasVat): void
    {
        $vatRate = $hasVat ? 11.0 : 0.0;
        $items = $get('items');

        if (! is_array($items)) {
            self::updateQuotationTotals($set, []);

            return;
        }

        $updatedItems = [];

        foreach ($items as $itemKey => $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);

            $lineSubtotal = $quantity * $unitPrice;
            $discountAmount = $lineSubtotal * ($discount / 100);
            $amount = $lineSubtotal - $discountAmount;

            $item['vat_rate'] = $vatRate;
            $item['amount'] = number_format($amount, 0, '.', '');

            $updatedItems[$itemKey] = $item;
        }

        $set('items', $updatedItems);

        self::updateQuotationTotals($set, $updatedItems);
    }

    /**
     * @param  array<int|string, array<string, mixed>>|null  $items
     */
    public static function updateQuotationTotals(Set $set, ?array $items): void
    {
        $subtotal = 0;
        $totalTax = 0;
        $totalDiscount = 0;

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

        $set('subtotal', number_format($subtotal, 0, '.', ''));
        $set('tax_amount', number_format($totalTax, 0, '.', ''));
        $set('discount_amount', number_format($totalDiscount, 0, '.', ''));
        $set('grand_total', number_format($grandTotal, 0, '.', ''));
    }

    public static function formatCustomerOptionLabel(Customer $customer): string
    {
        return collect([$customer->name, $customer->company_name])
            ->filter()
            ->implode(' - ');
    }
}
