<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\StarredController;
use App\Http\Controllers\TaskListController;
use App\Http\Controllers\Web\CompleteSubtaskController;
use App\Http\Controllers\Web\CompleteTaskController;
use App\Http\Controllers\Web\FolderController;
use App\Http\Controllers\Web\FolderOrderController;
use App\Http\Controllers\Web\ListInvitationController;
use App\Http\Controllers\Web\MoveTaskController;
use App\Http\Controllers\Web\MoveTaskListController;
use App\Http\Controllers\Web\NotificationOpenController;
use App\Http\Controllers\Web\NotificationStatusController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\PasswordController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\RestoreSubtaskController;
use App\Http\Controllers\Web\RestoreTaskController;
use App\Http\Controllers\Web\StarTaskController;
use App\Http\Controllers\Web\SubtaskController;
use App\Http\Controllers\Web\TaskCommentController;
use App\Http\Controllers\Web\TaskController;
use App\Http\Controllers\Web\TaskListController as WebTaskListController;
use App\Http\Controllers\Web\TaskListMemberController;
use App\Http\Controllers\Web\TaskListMembershipController;
use App\Http\Controllers\Web\TaskListOrderController;
use App\Http\Controllers\Web\TaskListTaskController;
use App\Http\Controllers\Web\TaskOrderController;
use App\Http\Controllers\Web\TaskSubtaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;

Route::middleware('guest')->group(function () {
    Route::get('login', fn (Request $request) => Inertia::render('auth/login', [
        'status' => $request->session()->get('status'),
    ]))->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
    Route::get('register', fn () => Inertia::render('auth/register'))->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('forgot-password', fn (Request $request) => Inertia::render('auth/forgot-password', [
        'status' => $request->session()->get('status'),
    ]))->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('reset-password/{token}', fn (Request $request, string $token) => Inertia::render('auth/reset-password', [
        'email' => $request->query('email', ''),
        'token' => $token,
    ]))->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('auth/google', [GoogleController::class, 'redirect'])
        ->middleware('throttle:login')
        ->name('auth.google');

    Route::get('auth/google/callback', [GoogleController::class, 'callback'])
        ->middleware('throttle:login')
        ->name('auth.google.callback');
});

Route::get('privacy', fn () => Inertia::render('legal/privacy'))->name('privacy');
Route::get('terms', fn () => Inertia::render('legal/terms'))->name('terms');

Route::get('/', HomeController::class)->name('home');

Route::middleware('auth')->group(function () {
    Route::get('email/verify', fn (Request $request) => Inertia::render('auth/verify-email', [
        'email' => $request->user()->email,
        'status' => $request->session()->get('status'),
    ]))->name('verification.notice');
    Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::patch('onboarding', OnboardingController::class)->name('onboarding.complete');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('profile/background', [ProfileController::class, 'updateBackground'])->name('profile.background.update');
    Route::patch('profile/completion-sound', [ProfileController::class, 'updateCompletionSound'])->name('profile.completion-sound.update');
    Route::put('profile/password', [PasswordController::class, 'update'])->name('profile.password.update');
    Route::get('inbox', InboxController::class)->name('inbox');
    Route::get('starred', StarredController::class)->name('starred');
    Route::get('notifications', NotificationCenterController::class)->name('notifications.index');
    Route::post('notifications/{notification}/open', NotificationOpenController::class)->name('notifications.open');
    Route::patch('notifications/{notification}', NotificationStatusController::class)->name('notifications.update');
    Route::get('lists/{list}', TaskListController::class)->whereNumber('list')->name('lists.show');

    Route::put('folders/order', FolderOrderController::class)->name('folders.order');
    Route::post('folders', [FolderController::class, 'store'])->name('folders.store');
    Route::put('folders/{folder}', [FolderController::class, 'update'])->whereNumber('folder')->name('folders.update');
    Route::delete('folders/{folder}', [FolderController::class, 'destroy'])->whereNumber('folder')->name('folders.destroy');

    Route::put('lists/order', TaskListOrderController::class)->name('lists.order');
    Route::post('lists/{list}/move', MoveTaskListController::class)->whereNumber('list')->name('lists.move');
    Route::post('lists', [WebTaskListController::class, 'store'])->name('lists.store');
    Route::put('lists/{list}', [WebTaskListController::class, 'update'])->whereNumber('list')->name('lists.update');
    Route::delete('lists/{list}', [WebTaskListController::class, 'destroy'])->whereNumber('list')->name('lists.destroy');

    // Plan 1 ("Shared Lists and Collaboration"), Step 8: the web mirror of
    // routes/api.php's sharing lifecycle. No web equivalent of
    // "GET lists/{list}/members" or "GET invitations" — the frontend gets
    // both through Inertia props (currentList.members, the shared
    // notifications prop), not dedicated page routes.
    Route::post('lists/{list}/members', [TaskListMemberController::class, 'store'])
        ->middleware('throttle:invite')
        ->whereNumber('list')->name('lists.members.store');
    Route::delete('lists/{list}/members/{user}', [TaskListMemberController::class, 'destroy'])
        ->whereNumber('list')->whereNumber('user')->name('lists.members.destroy');
    Route::delete('lists/{list}/membership', [TaskListMembershipController::class, 'destroy'])
        ->whereNumber('list')->name('lists.membership.destroy');
    Route::post('invitations/{invitation}/accept', [ListInvitationController::class, 'accept'])
        ->whereNumber('invitation')->name('invitations.accept');
    Route::post('invitations/{invitation}/decline', [ListInvitationController::class, 'decline'])
        ->whereNumber('invitation')->name('invitations.decline');

    Route::post('lists/{list}/tasks', [TaskListTaskController::class, 'store'])->whereNumber('list')->name('lists.tasks.store');
    Route::put('lists/{list}/task-order', TaskOrderController::class)->whereNumber('list')->name('lists.task-order');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->whereNumber('task')->name('tasks.update');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->whereNumber('task')->name('tasks.comments.store');
    Route::post('tasks/{task}/subtasks', [TaskSubtaskController::class, 'store'])->whereNumber('task')->name('tasks.subtasks.store');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->whereNumber('task')->name('tasks.destroy');
    Route::post('tasks/{task}/complete', CompleteTaskController::class)->whereNumber('task')->name('tasks.complete');
    Route::post('tasks/{task}/restore', RestoreTaskController::class)->whereNumber('task')->name('tasks.restore');
    Route::put('tasks/{task}/star', StarTaskController::class)->whereNumber('task')->name('tasks.star');
    Route::post('tasks/{task}/move', MoveTaskController::class)->whereNumber('task')->name('tasks.move');

    Route::put('subtasks/{subtask}', [SubtaskController::class, 'update'])->whereNumber('subtask')->name('subtasks.update');
    Route::delete('subtasks/{subtask}', [SubtaskController::class, 'destroy'])->whereNumber('subtask')->name('subtasks.destroy');
    Route::post('subtasks/{subtask}/complete', CompleteSubtaskController::class)->whereNumber('subtask')->name('subtasks.complete');
    Route::post('subtasks/{subtask}/restore', RestoreSubtaskController::class)->whereNumber('subtask')->name('subtasks.restore');
});
