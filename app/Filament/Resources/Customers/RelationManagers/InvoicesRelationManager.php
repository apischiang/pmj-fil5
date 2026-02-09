<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Invoices';

    public function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_number')
            ->columns([
                TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable(),
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
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
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
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
