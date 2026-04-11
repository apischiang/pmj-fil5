<?php

namespace App\Filament\Resources\Quotations\Tables;

use App\Models\Quotation;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;

class QuotationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quotation_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.company_name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
                        'accepted' => 'success',
                        'declined' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('grand_total')
                    ->money('IDR', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Created By'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (Quotation $record): string => URL::temporarySignedRoute(
                        'quotations.pdf.download',
                        now()->addMinutes(5),
                        ['quotation' => $record],
                    ))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
