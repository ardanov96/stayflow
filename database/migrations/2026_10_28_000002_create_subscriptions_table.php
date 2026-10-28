<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The subscriptions of the organizations (task P1-11).
     *
     * The local table is the **single source of truth** (ADR-0013): the
     * gateway is told what to do, it is never asked what the state is.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained('organizations')->cascadeOnDelete();
            $table->string('plan', 32);
            $table->string('status', 16)->default('trialing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->string('gateway', 16)->default('xendit');
            $table->string('gateway_reference', 128)->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
        });

        // The invoices of the fees of the SaaS.
        Schema::create('subscription_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status', 16);
            // The period the invoice covers: one invoice per period, so a
            // replay of the webhook cannot charge twice.
            $table->string('period_reference', 64)->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_reason', 191)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'period_reference'], 'subscription_invoices_org_period_unique');
        });

        // The events of the gateways, with the identifier of the provider:
        // the same event delivered twice is the same event.
        Schema::create('billing_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('gateway', 16);
            $table->string('reference', 128);
            $table->string('type', 32);
            $table->timestamps();

            $table->unique(['gateway', 'reference'], 'billing_events_gateway_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_events');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');
    }
};