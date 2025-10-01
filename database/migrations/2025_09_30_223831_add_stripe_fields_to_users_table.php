<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('current_plan')->default('Free')->after('email');
            $table->string('subscription_status')->nullable()->after('current_plan');
            $table->string('stripe_customer_id')->nullable()->after('subscription_status');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'current_plan',
                'subscription_status',
                'stripe_customer_id',
                'stripe_subscription_id'
            ]);
        });
    }
};