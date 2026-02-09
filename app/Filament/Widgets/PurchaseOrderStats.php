<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseOrder;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PurchaseOrderStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Purchase Orders', PurchaseOrder::count())
                ->description('Incoming & Outgoing')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),
            Stat::make('Open Incoming POs', PurchaseOrder::where('type', 'in')->where('status', 'open')->count())
                ->description('Sales Pending')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),
            Stat::make('Open Outgoing POs', PurchaseOrder::where('type', 'out')->where('status', 'open')->count())
                ->description('Procurement Pending')
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('warning'),
        ];
    }
}
