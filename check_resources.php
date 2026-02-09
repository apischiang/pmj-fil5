<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Filament\Facades\Filament;

echo "Checking registered resources for 'admin' panel...\n";

try {
    $panel = Filament::getPanel('admin');
    $resources = $panel->getResources();

    $found = false;
    foreach ($resources as $resource) {
        echo "- " . $resource . "\n";
        if (str_contains($resource, 'RoleResource')) {
            $found = true;
            // Check navigation group and label
            echo "  > Label: " . $resource::getNavigationLabel() . "\n";
            echo "  > Group: " . $resource::getNavigationGroup() . "\n";
            echo "  > Slug: " . $resource::getSlug() . "\n";
        }
    }

    if ($found) {
        echo "\nShield RoleResource is REGISTERED.\n";
    } else {
        echo "\nShield RoleResource is NOT registered.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
