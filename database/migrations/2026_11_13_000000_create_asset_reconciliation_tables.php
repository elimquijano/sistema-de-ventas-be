<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL puede dejar tablas creadas si una migracion DDL falla a mitad.
        // Como esta migracion aun no fue registrada, limpiamos solo sus tablas nuevas
        // para permitir un reintento seguro sin tocar datos de modulos existentes.
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('asset_inventory_snapshot_items');
        Schema::dropIfExists('asset_inventory_snapshots');
        Schema::dropIfExists('asset_inventory_movements');
        Schema::dropIfExists('asset_reconciliation_product_mappings');
        Schema::enableForeignKeyConstraints();

        Schema::create('asset_reconciliation_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('units_per_sale')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'product_id'], 'asset_recon_business_product_uq');
            $table->index(['business_id', 'asset_id', 'active'], 'asset_reconciliation_mapping_lookup');
        });

        Schema::create('asset_inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_loan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('condition', 20)->nullable();
            $table->unsignedInteger('quantity');
            $table->string('counterparty')->nullable();
            $table->dateTime('occurred_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'occurred_at', 'type'], 'asset_inventory_movement_period');
            $table->index(['asset_id', 'occurred_at'], 'asset_inventory_movement_asset');
        });

        Schema::create('asset_inventory_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('base_snapshot_id')->nullable()->constrained('asset_inventory_snapshots')->nullOnDelete();
            $table->dateTime('counted_at');
            $table->string('status', 20);
            $table->boolean('confirmed_unbalanced')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'counted_at'], 'asset_inventory_snapshot_period');
        });

        Schema::create('asset_inventory_snapshot_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('asset_inventory_snapshots')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('full_quantity');
            $table->unsignedInteger('empty_quantity');
            $table->integer('expected_full_quantity')->nullable();
            $table->integer('expected_empty_quantity')->nullable();
            $table->integer('expected_total_quantity');
            $table->integer('full_difference')->nullable();
            $table->integer('empty_difference')->nullable();
            $table->integer('total_difference');
            $table->unsignedInteger('sales_quantity')->default(0);
            $table->unsignedInteger('loaned_out_quantity')->default(0);
            $table->unsignedInteger('returned_in_quantity')->default(0);
            $table->unsignedInteger('refilled_quantity')->default(0);
            $table->unsignedInteger('external_in_full')->default(0);
            $table->unsignedInteger('external_in_empty')->default(0);
            $table->unsignedInteger('external_out_full')->default(0);
            $table->unsignedInteger('external_out_empty')->default(0);
            $table->string('status', 30);
            $table->json('calculation')->nullable();
            $table->timestamps();

            $table->unique(['snapshot_id', 'asset_id'], 'asset_snapshot_item_asset_uq');
        });

        $now = now();
        DB::table('asset_loans')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(200, function ($loans) use ($now): void {
                $rows = [];

                foreach ($loans as $loan) {
                    $rows[] = $this->movementRow(
                        $loan,
                        'loan_out',
                        (int) $loan->quantity,
                        $loan->loan_date.' 00:00:00',
                        $now
                    );

                    $processedAt = $loan->return_date
                        ? $loan->return_date.' 00:00:00'
                        : ($loan->updated_at ?? $now);

                    foreach ([
                        'loan_return' => (int) $loan->returned_quantity,
                        'damaged' => (int) $loan->damaged_quantity,
                        'lost' => (int) $loan->lost_quantity,
                    ] as $type => $quantity) {
                        if ($quantity > 0) {
                            $rows[] = $this->movementRow($loan, $type, $quantity, $processedAt, $now);
                        }
                    }
                }

                if ($rows !== []) {
                    DB::table('asset_inventory_movements')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_inventory_snapshot_items');
        Schema::dropIfExists('asset_inventory_snapshots');
        Schema::dropIfExists('asset_inventory_movements');
        Schema::dropIfExists('asset_reconciliation_product_mappings');
    }

    private function movementRow(object $loan, string $type, int $quantity, $occurredAt, $now): array
    {
        return [
            'business_id' => $loan->business_id,
            'asset_id' => $loan->asset_id,
            'asset_loan_id' => $loan->id,
            'type' => $type,
            'condition' => $type === 'loan_return' ? 'empty' : null,
            'quantity' => $quantity,
            'counterparty' => $loan->borrower_name,
            'occurred_at' => $occurredAt,
            'notes' => $loan->notes,
            'created_by' => $loan->created_by,
            'metadata' => json_encode(['backfilled' => true]),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
};
