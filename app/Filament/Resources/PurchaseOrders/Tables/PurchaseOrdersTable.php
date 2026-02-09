<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in' => 'Incoming',
                        'out' => 'Outgoing',
                        default => ucfirst($state),
                    }),
                TextColumn::make('partner_name')
                    ->label('Partner')
                    ->state(function ($record) {
                        if ($record->type === 'in') {
                            return $record->buyer?->company_name;
                        } else {
                            return $record->vendor?->company_name;
                        }
                    })
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('buyer', fn ($q) => $q->where('company_name', 'like', "%{$search}%"))
                            ->orWhereHas('vendor', fn ($q) => $q->where('company_name', 'like', "%{$search}%"));
                    }),
                TextColumn::make('po_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->money(fn ($record) => $record->currency ?? 'IDR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('file_attachment')
                    ->label('Attachment')
                    ->formatStateUsing(fn ($state) => $state ? 'View File' : 'No File')
                    ->url(fn ($record) => $record->file_attachment ? asset('storage/' . $record->file_attachment) : null)
                    ->openUrlInNewTab()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'in' => 'Incoming (Sales)',
                        'out' => 'Outgoing (Procurement)',
                    ]),
                SelectFilter::make('buyer')
                    ->relationship('buyer', 'company_name')
                    ->searchable()
                    ->preload()
                    ->label('Customer (Buyer)'),
                SelectFilter::make('vendor')
                    ->relationship('vendor', 'company_name')
                    ->searchable()
                    ->preload()
                    ->label('Vendor'),
            ])
            ->groups([
                Group::make('type')
                    ->label('Type')
                    ->getTitleFromRecordUsing(fn ($record): string => match ($record->type) {
                        'in' => 'Incoming (Sales)',
                        'out' => 'Outgoing (Procurement)',
                        default => ucfirst($record->type),
                    }),
                Group::make('buyer.company_name')
                    ->label('Customer'),
                Group::make('vendor.company_name')
                    ->label('Vendor'),
                Group::make('po_date')
                    ->label('Date')
                    ->date()
                    ->collapsible(),
                Group::make('status')
                    ->label('Status'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('convert_to_invoice')
                    ->label('Convert to Invoice')
                    ->icon('heroicon-o-document-currency-dollar')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Convert Purchase Order to Invoice')
                    ->modalDescription('This will create a new Draft Invoice with items from this Purchase Order. You can then edit the invoice to handle partial payments or shipments.')
                    ->action(function (PurchaseOrder $record) {
                        // Create Invoice Header
                        $invoice = Invoice::create([
                            'type' => $record->type,
                            'customer_id' => $record->buyer_id,
                            'vendor_id' => $record->vendor_id,
                            'invoice_number' => 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
                            'date' => now(),
                            'due_date' => now(), // Can be adjusted later
                            'status' => 'draft',
                            'payment_status' => 'unpaid',
                            'subtotal' => 0, // Will be calculated from items
                            'tax_amount' => 0,
                            'discount_amount' => 0,
                            'grand_total' => 0,
                            'notes' => 'Converted from PO: ' . $record->po_number,
                        ]);

                        $subtotal = 0;
                        $taxAmount = 0;
                        $taxRate = $record->tax_rate ?? 0;

                        // Create Invoice Items
                        foreach ($record->items as $item) {
                            $itemAmount = $item->quantity * $item->unit_price;
                            
                            $invoice->items()->create([
                                'item_name' => $record->type === 'in' ? $item->material_number : $item->item_name,
                                'description' => $item->description,
                                'quantity' => $item->quantity,
                                'unit_price' => $item->unit_price,
                                'discount' => 0,
                                'vat_rate' => $taxRate,
                                'amount' => $itemAmount * (1 + ($taxRate / 100)),
                            ]);

                            $subtotal += $itemAmount;
                        }

                        // Recalculate Invoice Totals
                        $taxAmount = $subtotal * ($taxRate / 100);
                        $grandTotal = $subtotal + $taxAmount;

                        $invoice->update([
                            'subtotal' => $subtotal,
                            'tax_amount' => $taxAmount,
                            'grand_total' => $grandTotal,
                        ]);

                        Notification::make()
                            ->title('Invoice created successfully')
                            ->success()
                            ->send();

                        return redirect()->to(InvoiceResource::getUrl('edit', ['record' => $invoice]));
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
