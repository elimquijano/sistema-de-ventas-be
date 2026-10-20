<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('clients');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('status')->default('active');
            $table->boolean('receive_notifications')->default(false);
            $table->string('notification_channel')->default('whatsapp');
            $table->text('fcm_token')->nullable();
            $table->string('fcm_target_type')->default('token');
            $table->unsignedBigInteger('business_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->text('address')->nullable();
            $table->text('address_detail')->nullable();
            $table->string('image')->nullable();
            $table->json('route')->nullable();
            $table->string('estimated_time')->nullable();
            $table->string('approximate_distance')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $migration = require database_path(
            'migrations/2026_10_19_000000_add_listing_indexes_to_clients_table.php'
        );
        $migration->up();
    }

    public function test_client_listing_keeps_unbounded_page_requests_compatible(): void
    {
        $user = User::factory()->create(['business_id' => 2]);
        Sanctum::actingAs($user);

        $now = now();
        $clients = [];

        for ($number = 125; $number >= 1; $number--) {
            $clients[] = [
                'name' => sprintf('Cliente %03d', $number),
                'business_id' => 2,
                'latitude' => 0,
                'longitude' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
        }

        $clients[] = [
            'name' => 'Cliente de otro negocio',
            'business_id' => 3,
            'latitude' => 0,
            'longitude' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
        $clients[] = [
            'name' => 'Cliente eliminado',
            'business_id' => 2,
            'latitude' => 0,
            'longitude' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => $now,
        ];

        DB::table('clients')->insert($clients);

        $this->getJson('/api/clients?per_page=-1')
            ->assertOk()
            ->assertJsonPath('per_page', 125)
            ->assertJsonPath('total', 125)
            ->assertJsonCount(125, 'data')
            ->assertJsonPath('data.0.name', 'Cliente 001')
            ->assertJsonPath('data.124.name', 'Cliente 125')
            ->assertJsonPath('data.0.business_id', 2)
            ->assertJsonMissingPath('data.0.route')
            ->assertJsonMissingPath('data.0.created_by_user_id')
            ->assertJsonMissingPath('data.0.created_at')
            ->assertJsonMissingPath('data.0.updated_at');
    }

    public function test_client_listing_has_a_stable_name_and_id_order(): void
    {
        $user = User::factory()->create(['business_id' => 2]);
        Sanctum::actingAs($user);

        $now = now();
        DB::table('clients')->insert([
            [
                'id' => 30,
                'name' => 'Mismo nombre',
                'business_id' => 2,
                'latitude' => 0,
                'longitude' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 10,
                'name' => 'Mismo nombre',
                'business_id' => 2,
                'latitude' => 0,
                'longitude' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->getJson('/api/clients?per_page=1000')
            ->assertOk()
            ->assertJsonPath('per_page', 1000)
            ->assertJsonPath('data.0.id', 10)
            ->assertJsonPath('data.1.id', 30);
    }
}
