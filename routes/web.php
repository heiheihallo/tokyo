<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::planner.dashboard')->name('dashboard');
    Route::livewire('trips/manage', 'pages::trips.manage')->name('trips.manage');
});

Route::livewire('trips/{trip:slug}/timelines/{variant:slug}/days/{dayNode:stable_key}', 'pages::public.day')
    ->name('trips.public.days.show');
Route::livewire('trips/{trip:slug}/journal/{journalEntry}', 'pages::public.journal-show')
    ->name('trips.public.journal.show');
Route::livewire('trips/{trip:slug}/journal', 'pages::public.journal')
    ->name('trips.public.journal');
Route::livewire('trips/{trip:slug}', 'pages::public.trip')->name('trips.public');

require __DIR__.'/settings.php';
