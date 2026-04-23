<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\AntiRejeuHmac;

// 1. Endpoints internes (sans middleware, appelés en interne)
Route::post('/validate-token', [AuthController::class, 'validateToken']);
Route::post('/sso/tp5', [AuthController::class, 'ssoTp5']);

// 2. Routes publiques SÉCURISÉES (Protection Anti-Rejeu activée)
Route::middleware([AntiRejeuHmac::class])->group(function () {
    Route::post('/inscription', [AuthController::class, 'inscription']);
    Route::post('/register',    [AuthController::class, 'inscription']);
    Route::post('/connexion',   [AuthController::class, 'connexion']);
    Route::post('/login',       [AuthController::class, 'connexion']);
});

// 3. Routes privées (Nécessitent un token JWT valide)
Route::middleware('jwt')->group(function (): void {
    Route::get('/profil',       [AuthController::class, 'profil']);
    Route::get('/profile',      [AuthController::class, 'profil']);
    
    Route::put('/change-password', [AuthController::class, 'modifierMotDePasse']);
    
    Route::post('/deconnexion', [AuthController::class, 'deconnexion']);
    Route::post('/logout',      [AuthController::class, 'deconnexion']);
});