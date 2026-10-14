<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Identity\Adapters;

use App\Models\User;
use App\Modules\Platform\Application\Identity\Ports\AuthMailer;
use App\Modules\Platform\Infrastructure\Notifications\PasswordResetMail;
use App\Modules\Platform\Infrastructure\Notifications\RecoveryCodeUsedMail;
use App\Modules\Platform\Infrastructure\Notifications\VerifyEmailMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Mailer adapter for the auth flow. Each method:
 *
 *  - resolves the user from the database (no enumeration: the
 *    caller is responsible for only calling us when the user
 *    exists);
 *  - builds a signed URL the user can click to complete the
 *    flow (Laravel's `URL::temporarySignedRoute` handles HMAC
 *    signing and expiry);
 *  - dispatches a Mailable (queueable, so the HTTP request
 *    doesn't block on the SMTP roundtrip).
 *
 * Signatures are verified by the route's `signed` middleware; if
 * anyone tampers with the id or the hash, the link 403s.
 */
final class LaravelAuthMailer implements AuthMailer
{
    public function sendVerificationLink(int $userId): void
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return;
        }
        $url = URL::temporarySignedRoute(
            'auth.email.verify',
            now()->addMinutes(60),
            [
                'id' => $user->getKey(),
                'hash' => sha1((string) $user->email),
            ],
        );
        Mail::to($user->email)->send(new VerifyEmailMail($url, (string) $user->name));
    }

    public function sendPasswordResetLink(string $email, string $token): void
    {
        // The token is opaque; we add it as a query param to the
        // reset URL and let the controller call the broker to
        // verify it on submit. We do NOT sign the URL because the
        // token itself is the bearer credential.
        $url = url(route('auth.password.reset', ['token' => $token], false)).'?email='.rawurlencode($email);
        Mail::to($email)->send(new PasswordResetMail($url, $email));
    }

    public function sendRecoveryCodeUsedNotice(int $userId, string $remainingCodeCount): void
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return;
        }
        Mail::to($user->email)->send(new RecoveryCodeUsedMail((string) $user->name, $remainingCodeCount));
    }
}