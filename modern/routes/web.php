<?php

use App\Http\Controllers\InformationController;
use App\Http\Controllers\LegacyLoginController;
use App\Http\Controllers\MemberPasswordResetController;
use App\Http\Controllers\MemberProfileController;
use App\Http\Controllers\MemberRegistrationController;
use App\Http\Controllers\MemberReportController;
use App\Http\Controllers\MemberSubscriptionController;
use App\Http\Controllers\OfficialImageController;
use App\Http\Controllers\OfficialProfileController;
use App\Http\Controllers\OfficialQuizCatalogController;
use App\Http\Controllers\OfficialQuizController;
use App\Http\Controllers\PublicMemberController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('migration');
});

Route::get('/catalogue', OfficialQuizCatalogController::class)->name('catalogue');
Route::get('/aide', [InformationController::class, 'help'])->name('help');
Route::get('/sortie-cine', [InformationController::class, 'cinema'])->name('cinema');
Route::get('/membres', [PublicMemberController::class, 'index'])->name('members.index');
Route::get('/membres/{member}', [PublicMemberController::class, 'show'])->whereNumber('member')->name('members.show');
Route::get('/images-officielles/{kind}/{id}', OfficialImageController::class)
    ->whereIn('kind', ['quiz', 'question', 'answer'])->whereNumber('id')->name('official-image');
Route::get('/connexion', [LegacyLoginController::class, 'show'])->name('login');
Route::post('/connexion', [LegacyLoginController::class, 'login'])->name('login.submit');
Route::middleware('guest')->group(function () {
    Route::get('/inscription', [MemberRegistrationController::class, 'show'])->name('register');
    Route::post('/inscription', [MemberRegistrationController::class, 'register'])->middleware('throttle:5,1')->name('register.submit');
    Route::get('/inscription/confirmation/{token}', [MemberRegistrationController::class, 'confirmation'])->where('token', '[A-Za-z0-9]{64}')->name('register.confirmation');
    Route::post('/inscription/confirmation/{token}', [MemberRegistrationController::class, 'confirm'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:10,1')->name('register.confirm');
});
Route::get('/mot-de-passe-oublie', [MemberPasswordResetController::class, 'requestForm'])->name('password.request');
Route::post('/mot-de-passe-oublie', [MemberPasswordResetController::class, 'send'])->middleware('throttle:5,1')->name('password.email');
Route::get('/mot-de-passe/{user}/{token}', [MemberPasswordResetController::class, 'resetForm'])->whereNumber('user')->where('token', '[A-Za-z0-9]{64}')->name('password.reset');
Route::post('/mot-de-passe/{user}/{token}', [MemberPasswordResetController::class, 'reset'])->whereNumber('user')->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:10,1')->name('password.update');
Route::post('/deconnexion', [LegacyLoginController::class, 'logout'])->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/profil', [OfficialProfileController::class, 'show'])->name('profile');
    Route::get('/abonnements', [MemberSubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::post('/membres/{member}/abonnement', [MemberSubscriptionController::class, 'update'])->whereNumber('member')->name('subscriptions.update');
    Route::post('/membres/{member}/signaler', [MemberReportController::class, 'report'])->whereNumber('member')->middleware('throttle:10,1')->name('members.report');
    Route::get('/administration/signalements', [MemberReportController::class, 'index'])->name('admin.reports');
    Route::post('/administration/signalements/{report}/classer', [MemberReportController::class, 'resolve'])->whereNumber('report')->name('admin.reports.resolve');
    Route::get('/profil/modifier', [MemberProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profil/modifier', [MemberProfileController::class, 'update'])->middleware('throttle:10,1')->name('profile.update');
    Route::post('/profil/mot-de-passe', [MemberProfileController::class, 'changePassword'])->middleware('throttle:10,1')->name('profile.password');
    Route::post('/profil/suppression', [MemberProfileController::class, 'destroy'])->middleware('throttle:10,1')->name('profile.destroy');
    Route::post('/profil/favoris/{quiz}', [OfficialProfileController::class, 'favorite'])->whereNumber('quiz')->name('profile.favorite');
    Route::post('/quiz/{quiz}/resume', [OfficialQuizController::class, 'resume'])->whereNumber('quiz')->name('official-quiz.resume');
});
Route::get('/quiz/{quiz}', [OfficialQuizController::class, 'show'])->whereNumber('quiz')->name('official-quiz.show');
Route::post('/quiz/{quiz}/start', [OfficialQuizController::class, 'start'])->whereNumber('quiz')->name('official-quiz.start');
Route::get('/quiz/{quiz}/question', [OfficialQuizController::class, 'question'])->whereNumber('quiz')->name('official-quiz.question');
Route::post('/quiz/{quiz}/answer', [OfficialQuizController::class, 'answer'])->whereNumber('quiz')->name('official-quiz.answer');
Route::get('/quiz/{quiz}/result', [OfficialQuizController::class, 'result'])->whereNumber('quiz')->name('official-quiz.result');
Route::post('/quiz/{quiz}/next', [OfficialQuizController::class, 'next'])->whereNumber('quiz')->name('official-quiz.next');
