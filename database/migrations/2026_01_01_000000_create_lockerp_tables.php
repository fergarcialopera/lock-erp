<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->jsonb('settings')->nullable(); // includes open_latency_ms
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role'); // ADMIN|RESPONSABLE|READONLY
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lockers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('compartments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('locker_id')->constrained('lockers')->cascadeOnDelete();
            $table->string('code');
            $table->string('status')->default('AVAILABLE'); // AVAILABLE|MAINTENANCE
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->string('barcode')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('compartment_inventories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignUlid('compartment_id')->constrained('compartments')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('qty_available')->default(0);
            $table->integer('qty_reserved')->default(0);
            $table->unique(['clinic_id', 'compartment_id', 'product_id'], 'clinic_comp_prod_unique');
            $table->timestamps();
        });

        Schema::create('dispenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignUlid('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('locker_id')->constrained('lockers')->cascadeOnDelete();
            $table->foreignUlid('compartment_id')->constrained('compartments')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->string('status')->default('PENDING'); // PENDING|RETIRED|CANCELLED
            $table->timestamp('requested_at');
            $table->timestamp('read_at')->nullable();
            $table->string('external_ref')->nullable();
            $table->jsonb('meta')->nullable();
            $table->unique(['clinic_id', 'external_ref'], 'dispenses_clinic_external_ref_unique');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->ulid('actor_user_id')->nullable();
            $table->string('actor_type'); // USER|SYSTEM
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id');
            $table->timestamp('occurred_at');
            $table->jsonb('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('dispenses');
        Schema::dropIfExists('compartment_inventories');
        Schema::dropIfExists('products');
        Schema::dropIfExists('compartments');
        Schema::dropIfExists('lockers');
        Schema::dropIfExists('users');
        Schema::dropIfExists('clinics');
    }
};
