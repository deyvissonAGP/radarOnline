<?php

use App\Http\Controllers\VagasController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VagasController::class, 'index'])->name('vagas.index');
Route::get('/resumo', [VagasController::class, 'resumo'])->name('vagas.resumo');
Route::get('/regras', [VagasController::class, 'regras'])->name('vagas.regras');
Route::get('/saude', [VagasController::class, 'saude'])->name('vagas.saude');
Route::post('/vagas/{vaga}/status', [VagasController::class, 'marcar'])->name('vagas.marcar');
