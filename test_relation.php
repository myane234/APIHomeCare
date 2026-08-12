<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $user = \App\Models\Users::find(2);
    if ($user) {
        echo "Found user\n";
        $roles = $user->roles()->pluck('roles.nama_role');
        echo "Roles: " . json_encode($roles) . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
