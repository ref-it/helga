<?php

use App\Http\Controllers\Auth\OidcController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SubscriptionController;
use App\Livewire\Home;
use App\Livewire\Plan\Admin;
use App\Livewire\Plan\AllPlans;
use App\Livewire\Plan\Create;
use App\Livewire\Plan\Edit as PlanEdit;
use App\Livewire\Plan\Mine;
use App\Livewire\Plan\Show;
use App\Livewire\Shift\Edit as ShiftEdit;
use App\Livewire\Shift\Subscriptions as ShiftSubscriptions;
use App\Livewire\Subscription\ConfirmRemove as SubscriptionConfirmRemove;
use App\Livewire\Subscription\Edit;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/**
 * Display home view, where users can create a plan
 */
Route::get('/', Home::class)->name('home');

Route::get('/cron', [PlanController::class, 'cron'])->name('cron');

/**
 * OIDC login / logout
 */
Route::get('/login', [OidcController::class, 'redirect'])->name('login');
Route::get('/auth/callback', [OidcController::class, 'callback'])->name('oidc.callback');
Route::post('/logout', [OidcController::class, 'logout'])->name('logout');
Route::post('/auth/backchannel-logout', [OidcController::class, 'backChannelLogout'])->name('oidc.backchannel-logout');

/**
 * Create a new plan. Requires login - the creator becomes the plan's owner.
 */
Route::get('/plan/create', Create::class)->middleware('auth')->name('plan.create');

/**
 * Unauthenticated show plan.
 */
Route::get('/s/{plan:view_id}', Show::class)->name('plan.show');

/*****************************************
 *  Routes for logged-in plan owners/shared-group members
 *****************************************  */

Route::get('/plans/mine', Mine::class)->middleware('auth')->name('plan.mine');
Route::get('/subscriptions/mine', App\Livewire\Subscription\Mine::class)->middleware('auth')->name('subscription.mine');

/**
 * Admin overview of every plan in the system - global admins only
 * (OIDC_ADMIN_GROUPS). Must be registered before the `/plans/{plan}` route
 * below so it isn't swallowed by the route-model-binding wildcard.
 */
Route::get('/plans/admin', AllPlans::class)
    ->middleware(['auth', 'can:viewAny,App\Models\Plan'])->name('plan.admin_all');

Route::get('/plans/{plan}', Admin::class)
    ->middleware(['auth', 'can:view,plan'])->name('plan.manage');

/**
 * Exporting
 */
Route::get('/plans/{plan}/export', [PlanController::class, 'export'])
    ->middleware(['auth', 'can:manage,plan'])->name('plan.export');
Route::get('/plans/{plan}/export/pdf', [PlanController::class, 'exportPdf'])
    ->middleware(['auth', 'can:manage,plan'])->name('plan.export.pdf');
// $plan is optional here (importing as a brand new plan), so the ability
// check stays inline in the controller instead of route middleware
Route::post('/plans/import/{plan?}', [PlanController::class, 'import'])->middleware('auth')->name('plan.import');

/**
 * Edit plan details.
 */
Route::get('/plans/{plan}/edit', PlanEdit::class)
    ->middleware(['auth', 'can:manage,plan'])->name('plan.edit');
Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])
    ->middleware(['auth', 'can:forceDelete,plan'])->name('plan.destroy');

/**
 * Edit plan shifts.
 */
Route::get('/plans/{plan}/shift/create', App\Livewire\Shift\Create::class)
    ->middleware(['auth', 'can:create,App\Models\Shift,plan'])->name('plan.shift.create');
Route::post('/plans/{plan}/shift', [ShiftController::class, 'store'])
    ->middleware(['auth', 'can:create,App\Models\Shift,plan'])->name('plan.shift.store');
Route::get('/plans/{plan}/shift/{shift}/edit', ShiftEdit::class)
    ->middleware(['auth', 'can:update,shift'])->name('plan.shift.edit');
Route::delete('/plans/{plan}/shift/{shift}', [ShiftController::class, 'destroy'])
    ->middleware(['auth', 'can:forceDelete,shift'])->name('plan.shift.destroy');
Route::get('/plans/{plan}/shift/{shift}/subscriptions', ShiftSubscriptions::class)
    ->middleware(['auth', 'can:view,plan'])->name('plan.shift.subscriptions');

/**
 * Edit subscription
 */
Route::get('/plans/{plan}/shift/{shift}/{subscription}/edit', Edit::class)
    ->middleware(['auth', 'can:update,subscription'])->name('plan.shift.subscription.edit');
Route::delete('/plans/{plan}/shift/{shift}/{subscription}', [SubscriptionController::class, 'destroy'])
    ->middleware(['auth', 'can:forceDelete,subscription'])->name('plan.shift.subscription.destroy');

/*****************************************
 *  Routes for everybody (need view_id) - anonymous, unaffected by login
 *****************************************  */

/**
 * Full details of a single shift.
 */
Route::get('/s/{plan:view_id}/shift/{shift}/details', App\Livewire\Shift\Show::class)
    ->name('plan.shift.show');

/**
 * Subscribe to a shift
 */
Route::get('/s/{plan:view_id}/shift/{shift}', App\Livewire\Subscription\Create::class)
    ->name('plan.subscription.create');

Route::get('/s/{plan:view_id}/shift/{shift}/{subscription}/verify-email', [SubscriptionController::class, 'verifyEmail'])
    ->middleware('signed')->name('plan.subscription.verifyEmail');

/**
 * Unsubscribe from a shift.
 *
 * A logged-in user unsubscribes themselves instantly (matched by their
 * account email) after confirming in a modal - no email round-trip needed
 * since login already proves who they are.
 *
 * Anonymous subscribers have no account to prove their identity with, so
 * they get a signed-ish confirmation link in their sign-up email instead
 * (see Subscription::sendEmailVerification()); this stays open to guests.
 */
Route::delete('/s/{plan:view_id}/shift/{shift}/unsubscribe', [SubscriptionController::class, 'unsubscribeSelf'])
    ->middleware('auth')->name('plan.subscription.unsubscribeSelf');
Route::get('/s/{plan:view_id}/shift/{shift}/remove/{confirmation}', SubscriptionConfirmRemove::class)
    ->name('plan.subscription.confirmRemove');

/**
 * Links
 */
Route::get('/accessibility', fn (): Redirector|\Illuminate\Http\RedirectResponse => redirect(config('app.links.accessibility')))->name('accessibility');

Route::get('/contributors', fn (): Redirector|\Illuminate\Http\RedirectResponse => redirect(config('app.links.contributors')))->name('contributors');

Route::get('/documentation', fn (): Redirector|\Illuminate\Http\RedirectResponse => redirect(config('app.links.documentation')))->name('documentation');

Route::get('/imprint', fn (): Redirector|\Illuminate\Http\RedirectResponse => redirect(config('app.links.imprint')))->name('imprint');

Route::get('/privacy', fn (): Redirector|\Illuminate\Http\RedirectResponse => redirect(config('app.links.privacy')))->name('privacy');

Route::get('/source-code', fn (): Redirector|\Illuminate\Http\RedirectResponse => redirect(config('app.links.source_code')))->name('source-code');

Route::get('/translate', fn (): Redirector|\Illuminate\Http\RedirectResponse => redirect(config('app.links.translate')))->name('translate');
