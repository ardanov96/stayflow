<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-02 (Identity) columns added to the default Laravel users table.
 *
 *  - two_factor_secret: the per-user TOTP base32 secret. Stored encrypted
 *    at the application layer (Laravel `encrypted` cast) so a DB dump
 *    never exposes usable material. `text` because the encrypted
 *    ciphertext is much longer than 255 chars.
 *  - two_factor_recovery_codes: JSON array of hashed recovery codes.
 *    Generated once at enrollment, shown in plaintext only on the
 *    enrollment confirmation screen, then hashed (Argon2id via the
 *    `hashed` cast on a per-element wrapper) before this write. Hashed
 *    so a DB read alone cannot impersonate the user.
 *  - two_factor_confirmed_at: set when the user proves they can produce
 *    a valid TOTP code from the freshly-generated secret. Until this is
 *    set, `hasTwoFactor()` is false even if `two_factor_secret` is
 *    populated (guards against the "user abandons enrollment" race).
 *  - last_login_at / last_login_ip: populated on successful login for
 *    the user-facing "last activity" surface and for security review.
 *  - failed_login_count / locked_until: in-DB throttle counter that
 *    survives `RateLimiter` cache eviction (Redis flush) and lets the
 *    login action apply a hard lockout cap independent of any
 *    transport-level rate limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');

            $table->timestamp('last_login_at')->nullable()->after('two_factor_confirmed_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->unsignedInteger('failed_login_count')->default(0)->after('last_login_ip');
            $table->timestamp('locked_until')->nullable()->after('failed_login_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'last_login_at',
                'last_login_ip',
                'failed_login_count',
                'locked_until',
            ]);
        });
    }
};