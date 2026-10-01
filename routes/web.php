<?php

use App\Http\Controllers\AccountSetupController;
use App\Http\Controllers\Admin\AccountPasswordController;
use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AssetActionController;
use App\Http\Controllers\Admin\AssetController;
use App\Http\Controllers\Admin\BookController;
use App\Http\Controllers\Admin\BulkIntakeController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InspectionOverdueController;
use App\Http\Controllers\Admin\InspectorPhoneController;
use App\Http\Controllers\Admin\LabelController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\ManageController;
use App\Http\Controllers\Admin\PersonController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SerialCheckController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SupplyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\ConfirmPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClassDebtController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\InspectorPhoneSetupController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\PublicAssetController;
use App\Http\Controllers\PublicCupboardController;
use App\Http\Controllers\PublicReportController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Reached only by scanning a QR or following a direct link. These carry no
| auth middleware at all: if a teacher scans a sticker and gets redirected to
| a login screen, the system has failed at its main job (spec section 3.1).
*/

Route::get('/a/{tag}', [PublicAssetController::class, 'show'])
    ->middleware('throttle:scan')
    ->name('scan');

Route::get('/a/{tag}/report', [PublicReportController::class, 'create'])
    ->middleware('throttle:scan')
    ->name('report.create');

Route::post('/a/{tag}/report', [PublicReportController::class, 'store'])
    ->middleware('throttle:report')
    ->name('report.store');

// Linked from the report form, so it carries no auth either.
Route::get('/privacy', PrivacyController::class)->name('privacy');

Route::get('/r/{reference}', [PublicReportController::class, 'show'])
    ->middleware('throttle:report-status')
    ->name('report.status');

// The QR on a fire extinguisher is an "I checked this" button.
Route::get('/a/{tag}/inspect', [InspectionController::class, 'create'])
    ->middleware('throttle:scan')
    ->name('inspection.create');

Route::post('/a/{tag}/inspect', [InspectionController::class, 'store'])
    ->middleware('throttle:supply')
    ->name('inspection.store');

// Setting up an inspector's phone: the code on the director's screen, scanned
// by that phone. Signed, once only, half an hour. Opening it changes nothing;
// only the button does, so a link preview cannot use the code up.
Route::get('/inspector/{phone}', [InspectorPhoneSetupController::class, 'show'])
    ->middleware(['signed', 'throttle:scan'])
    ->name('inspector-phone.setup');

Route::post('/inspector/{phone}', [InspectorPhoneSetupController::class, 'store'])
    ->middleware(['signed', 'throttle:supply'])
    ->name('inspector-phone.claim');

Route::get('/inspector-ready', [InspectorPhoneSetupController::class, 'done'])
    ->name('inspector-phone.done');

// One QR on the cupboard door.
Route::get('/c/{room}', [PublicCupboardController::class, 'show'])
    ->middleware('throttle:scan')
    ->name('cupboard');

Route::post('/c/{room}/{supply}/take', [PublicCupboardController::class, 'take'])
    ->middleware('throttle:supply')
    ->name('cupboard.take');

Route::post('/c/{room}/{supply}/recount', [PublicCupboardController::class, 'recount'])
    ->middleware('throttle:supply')
    ->name('cupboard.recount');

// A signed, read-only link for one class, expiring at the end of the year.
Route::get('/class/{class}', ClassDebtController::class)
    ->middleware('signed')
    ->name('class.debts');

Route::get('/photo/{path}', PhotoController::class)
    ->where('path', '.*')
    ->name('photo');

// The once-only link with which someone chooses their password, after an admin
// adds their account or resets it. Opening it changes nothing; only the form
// does. An unknown account and a dead link both answer 410.
Route::get('/account/setup/{user}/{token}', [AccountSetupController::class, 'show'])
    ->whereNumber('user')
    ->whereAlphaNumeric('token')
    ->middleware('throttle:account-setup')
    ->name('account.setup');

Route::post('/account/setup/{user}/{token}', [AccountSetupController::class, 'store'])
    ->whereNumber('user')
    ->whereAlphaNumeric('token')
    ->middleware('throttle:account-setup')
    ->name('account.setup.store');

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
|
| A handful of accounts, a real password, and no sign-up or "forgot password"
| screen. Accounts are added and reset by another admin in Manage → Users.
*/

Route::redirect('/', '/admin');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// account.active turns away an account switched off or reset mid-session.
Route::middleware(['auth', 'account.active'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('home');

    // Inside the auth group on purpose: this reads serial numbers and report
    // references, and must never answer an anonymous request.
    Route::get('search', SearchController::class)->name('search');

    Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('assets/create', [AssetController::class, 'create'])->name('assets.create');
    Route::post('assets', [AssetController::class, 'store'])->name('assets.store');

    // Before assets/{asset}: that route binds on the printed tag and would
    // otherwise treat "serial-check" as one.
    Route::get('assets/serial-check', SerialCheckController::class)->name('assets.serial-check');

    Route::get('intake', [BulkIntakeController::class, 'start'])->name('intake.start');
    Route::post('intake', [BulkIntakeController::class, 'find'])->name('intake.find');
    Route::get('intake/{asset}', [BulkIntakeController::class, 'edit'])->name('intake.edit');
    Route::put('intake/{asset}', [BulkIntakeController::class, 'update'])->name('intake.update');

    Route::get('labels', [LabelController::class, 'index'])->name('labels.index');
    Route::post('labels/blank', [LabelController::class, 'generateBlanks'])->name('labels.blank');
    Route::post('labels/queue', [LabelController::class, 'printQueue'])->name('labels.queue.print');
    Route::post('labels/reprint', [LabelController::class, 'reprint'])->name('labels.reprint');

    Route::get('assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::get('assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
    Route::put('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');

    Route::post('assets/{asset}/move', [AssetActionController::class, 'move'])->name('assets.move');
    Route::post('assets/{asset}/assign', [AssetActionController::class, 'assign'])->name('assets.assign');
    Route::post('assets/{asset}/status', [AssetActionController::class, 'changeStatus'])->name('assets.status');
    Route::post('assets/{asset}/retire', [AssetActionController::class, 'retire'])->name('assets.retire');

    Route::post('assets/{asset}/label-queue', [LabelController::class, 'addToQueue'])->name('labels.queue.add');
    Route::delete('assets/{asset}/label-queue', [LabelController::class, 'removeFromQueue'])->name('labels.queue.remove');
    Route::post('assets/{asset}/label', [LabelController::class, 'printOne'])->name('labels.one');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('reports/{report}/acknowledge', [ReportController::class, 'acknowledge'])->name('reports.acknowledge');
    Route::post('reports/{report}/resolve', [ReportController::class, 'resolve'])->name('reports.resolve');
    Route::post('reports/{report}/dismiss', [ReportController::class, 'dismiss'])->name('reports.dismiss');

    Route::get('books', [BookController::class, 'titles'])->name('books.titles');
    Route::post('books/titles', [BookController::class, 'storeTitle'])->name('books.titles.store');
    Route::get('books/titles/{title}/edit', [BookController::class, 'editTitle'])->name('books.titles.edit');
    Route::put('books/titles/{title}', [BookController::class, 'updateTitle'])->name('books.titles.update');
    Route::get('books/issue', [BookController::class, 'issueForm'])->name('books.issue.form');
    Route::post('books/issue', [BookController::class, 'issue'])->name('books.issue');
    Route::get('books/returns', [BookController::class, 'returnsForm'])->name('books.returns.form');
    Route::post('books/returns', [BookController::class, 'takeBack'])->name('books.returns');
    Route::post('books/returns/link', [BookController::class, 'replaceClassLink'])->name('books.class-link.replace');
    Route::get('books/student/{person}', [BookController::class, 'student'])->name('books.student');
    Route::put('books/student/{person}', [BookController::class, 'updateStudent'])->name('books.student.update');

    Route::get('people', [PersonController::class, 'index'])->name('people.index');
    Route::get('people/create', [PersonController::class, 'create'])->name('people.create');
    Route::post('people', [PersonController::class, 'store'])->name('people.store');
    Route::get('people/import', [PersonController::class, 'importForm'])->name('people.import.form');
    Route::post('people/import', [PersonController::class, 'import'])->name('people.import');
    Route::get('people/{person}/edit', [PersonController::class, 'edit'])->name('people.edit');
    Route::put('people/{person}', [PersonController::class, 'update'])->name('people.update');
    Route::post('people/{person}/deactivate', [PersonController::class, 'deactivate'])->name('people.deactivate');
    Route::post('people/{person}/reactivate', [PersonController::class, 'reactivate'])->name('people.reactivate');

    Route::get('supplies', [SupplyController::class, 'index'])->name('supplies.index');
    Route::post('supplies', [SupplyController::class, 'store'])->name('supplies.store');
    Route::get('supplies/{supply}/edit', [SupplyController::class, 'edit'])->name('supplies.edit');
    Route::put('supplies/{supply}', [SupplyController::class, 'update'])->name('supplies.update');
    Route::post('supplies/{supply}/adjust', [SupplyController::class, 'adjust'])->name('supplies.adjust');

    Route::get('inspections/overdue', InspectionOverdueController::class)->name('inspections.overdue');

    Route::get('inspectors', [InspectorPhoneController::class, 'index'])->name('inspectors.index');
    Route::post('inspectors', [InspectorPhoneController::class, 'store'])->name('inspectors.store');
    Route::get('inspectors/{phone}', [InspectorPhoneController::class, 'show'])->name('inspectors.show');
    Route::delete('inspectors/{phone}', [InspectorPhoneController::class, 'withdraw'])->name('inspectors.withdraw');

    Route::get('licenses', [LicenseController::class, 'index'])->name('licenses.index');
    Route::get('licenses/create', [LicenseController::class, 'create'])->name('licenses.create');
    Route::post('licenses', [LicenseController::class, 'store'])->name('licenses.store');
    Route::get('licenses/{license}', [LicenseController::class, 'show'])->name('licenses.show');
    Route::get('licenses/{license}/edit', [LicenseController::class, 'edit'])->name('licenses.edit');
    Route::put('licenses/{license}', [LicenseController::class, 'update'])->name('licenses.update');
    Route::post('licenses/{license}/renew', [LicenseController::class, 'renew'])->name('licenses.renew');
    Route::delete('licenses/{license}', [LicenseController::class, 'destroy'])->name('licenses.destroy');

    Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('activity/export', [ActivityController::class, 'export'])->name('activity.export');
    Route::post('activity/block', [ActivityController::class, 'blockDevice'])->name('activity.block');
    Route::delete('activity/block/{device}', [ActivityController::class, 'unblockDevice'])->name('activity.unblock');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    // Typing the password again, checked on the server. Wrong attempts are
    // limited in the controller, like sign-in.
    Route::get('confirm-password', [ConfirmPasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmPasswordController::class, 'store'])->name('password.confirm.store');

    // Asks for the current password on the form itself.
    Route::get('account/password', [AccountPasswordController::class, 'edit'])->name('account.password');
    Route::put('account/password', [AccountPasswordController::class, 'update'])->name('account.password.update');

    // Every account route, reads included, needs the password typed in the
    // last few minutes. Enforced here, on the server, for every method: a
    // request sent without the page cannot skip it.
    Route::middleware(RequirePassword::using('admin.password.confirm', ConfirmPasswordController::MINUTES * 60))->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
        Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    });

    Route::get('manage', ManageController::class)->name('manage');

    Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('rooms/create', [RoomController::class, 'create'])->name('rooms.create');
    Route::post('rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::get('rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
    Route::get('rooms/{room}/sheet', [RoomController::class, 'inventorySheet'])->name('rooms.sheet');
    Route::post('rooms/{room}/counted', [RoomController::class, 'markCounted'])->name('rooms.counted');
    Route::post('rooms/{room}/contents', [RoomController::class, 'storeContent'])->name('rooms.contents.store');
    // Scoped: the furniture line must belong to this room, or it is a 404.
    Route::put('rooms/{room}/contents/{content}', [RoomController::class, 'updateContent'])->scopeBindings()->name('rooms.contents.update');
    Route::get('rooms/{room}/edit', [RoomController::class, 'edit'])->name('rooms.edit');
    Route::put('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
});
