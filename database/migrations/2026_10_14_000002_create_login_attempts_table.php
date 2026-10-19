<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-02 (Identity): pre-authentication login attempt log.
 *
 * Deliberately NOT tenant-scoped: a login attempt happens before the
 * user is identified, so organization_id is not yet known. The
 * `BelongsToTenant` trait is not applied to the corresponding
 * Eloquent model and the `TenantTableArchTest` is updated in this PR
 * to exclude `login_attempts` from the tenant-table requirement.
 *
 * Columns:
 *  - email: the address the caller typed. Stored even for unknown
 *    addresses so the lockout logic can rate-limit per email without
 *    requiring a User row. PII; the PiiScrubber will redact it from
 *    logs (see `app/Support/Logging/PiiScrubber`).
 *  - ip_address: client IP. Captured by the standard Laravel request
 *    trust-proxy chain.
 *  - user_agent: best-effort user agent.
 *  - successful: boolean. 0 = bad credentials / 2FA failure / locked
 *    / rate-limited; 1 = logged in.
 *  - failure_reason: short string code from
 *    {@see \App\Modules\Platform\Domain\Identity\LoginFailureReason}.
 *    Nullable on success.
 *  - user_id: set when the email matched a real User, even on a
 *    failed attempt (so audit can correlate).
 *
 * Indexes: composite (email, attempted_at) is what the throttle uses
 * to count recent failures; (ip_address, attempted_at) is the
 * secondary throttle key for catch-all bot protection; (user_id) lets
 * the user-facing security log render history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 254);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->boolean('successful');
            $table->string('failure_reason', 32)->nullable();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('attempted_at')->useCurrent();

            $table->index(['email', 'attempted_at'], 'login_attempts_email_time_idx');
            $table->index(['ip_address', 'attempted_at'], 'login_attempts_ip_time_idx');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};