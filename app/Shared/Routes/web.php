<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    $perfil = \App\Modules\SeguimientoEgresado\Support\Accesos\Perfil::desdeRolesDelPortal(auth()->user()->getRoleNames());

    if ($perfil) {
        return redirect()->route(\App\Modules\SeguimientoEgresado\Support\Accesos\RutaInicial::para($perfil));
    }

    return redirect()->route('login');
})->name('home');

Route::get('/dashboard', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    $perfil = \App\Modules\SeguimientoEgresado\Support\Accesos\Perfil::desdeRolesDelPortal(auth()->user()->getRoleNames());

    if ($perfil) {
        return redirect()->route(\App\Modules\SeguimientoEgresado\Support\Accesos\RutaInicial::para($perfil));
    }

    return redirect()->route('login');
})->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
