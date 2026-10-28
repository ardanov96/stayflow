<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The delivery log of the notifications (task P1-15).
     *
     * One row per attempt to deliver a notification to one recipient. The
     * row is written whether the delivery worked or not: a notification
     * that failed silently is a notification that never arrives, and the
     * guest never gets the confirmation.
     */
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->cascadeOnDelete();

            // The channel: mail today, whatsapp tomorrow.
            $table->string('channel', 32);
            // The template of the notification (the mailable class).
            $table->string('template', 191);
            // The locale the notification was rendered in.
            $table->string('locale', 8);
            // The recipient of the notification.
            $table->string('recipient', 255);
            // sent, failed, bounced or complained.
            $table->string('status', 16);
            // The identifier the provider gave the message.
            $table->string('provider_message_id', 191)->nullable();
            // Why the delivery failed.
            $table->string('error', 255)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'notification_deliveries_org_status_index');
            $table->index('provider_message_id', 'notification_deliveries_provider_message_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};