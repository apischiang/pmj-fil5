<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Models\Invoice;
use App\Models\Payment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
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
                            return $record->customer?->company_name;
                        } else {
                            return $record->vendor?->company_name;
                        }
                    })
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('customer', fn ($q) => $q->where('company_name', 'like', "%{$search}%"))
                            ->orWhereHas('vendor', fn ($q) => $q->where('company_name', 'like', "%{$search}%"));
                    }),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid' => 'danger',
                        'partial' => 'warning',
                        'paid' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('grand_total')
                    ->money('IDR')
                    ->sortable(),
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
                SelectFilter::make('customer')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->preload()
                    ->label('Customer'),
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
                Group::make('customer.company_name')
                    ->label('Customer'),
                Group::make('vendor.company_name')
                    ->label('Vendor'),
                Group::make('status')
                    ->label('Status'),
                Group::make('payment_status')
                    ->label('Payment Status'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('record_payment')
                    ->label('Record Payment')
                    ->icon('heroicon-o-currency-dollar')
                    ->form([
                        TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->default(fn (Invoice $record) => $record->grand_total - $record->payments()->sum('amount')),
                        DatePicker::make('payment_date')
                            ->default(now())
                            ->required(),
                        Select::make('method')
                            ->options([
                                'bank_transfer' => 'Bank Transfer',
                                'cash' => 'Cash',
                                'credit_card' => 'Credit Card',
                                'cheque' => 'Cheque',
                            ])
                            ->required(),
                        TextInput::make('reference'),
                        Textarea::make('notes'),
                    ])
                    ->action(function (Invoice $record, array $data) {
                        $record->payments()->create([
                            'amount' => $data['amount'],
                            'payment_date' => $data['payment_date'],
                            'method' => $data['method'],
                            'reference' => $data['reference'],
                            'notes' => $data['notes'],
                        ]);

                        // Recalculate status
                        $totalPaid = $record->payments()->sum('amount');
                        if ($totalPaid >= $record->grand_total) {
                            $record->update(['payment_status' => 'paid']);
                        } elseif ($totalPaid > 0) {
                            $record->update(['payment_status' => 'partial']);
                        }

                        Notification::make()
                            ->title('Payment recorded')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
