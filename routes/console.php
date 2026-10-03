<?php

use App\Models\OneTimeToken;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Limpieza diaria de la zona pública: tokens vencidos/consumidos
// y sesiones de BD expiradas (SESSION_LIFETIME en minutos).
Schedule::call(function () {
    OneTimeToken::where('expires_at', '<', now()->subDay())->delete();
    OneTimeToken::whereNotNull('consumed_at')->where('consumed_at', '<', now()->subDay())->delete();

    $limite = now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp();
    DB::table('sessions')->where('last_activity', '<', $limite)->delete();
})->daily()->name('cuenta:limpieza')->withoutOverlapping();

// Purga de carritos abandonados (> 90 días sin actualizar).
// Nunca toca ventas, Kardex ni stock.
Schedule::call(function () {
    DB::table('carrito_items')->where('updated_at', '<', now()->subDays(90))->delete();
    DB::table('carritos')->where('updated_at', '<', now()->subDays(90))->delete();

    // PDFs compartidos por WhatsApp con más de 7 días.
    foreach (Storage::disk('public')->files('carrito-pdfs') as $archivo) {
        if (Storage::disk('public')->lastModified($archivo) < now()->subDays(7)->getTimestamp()) {
            Storage::disk('public')->delete($archivo);
        }
    }
})->daily()->name('carrito:purga-abandonados')->withoutOverlapping();
