<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('team_permision', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('team_id');
            $table->unsignedBigInteger('permision_id');
            $table->timestamps();

            $table->unique(['team_id', 'permision_id']);
            $table->foreign('team_id')->references('id')->on('teams')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('permision_id')->references('id')->on('permisions')->onUpdate('cascade')->onDelete('cascade');
        });

        $now = now();
        $permisionId = DB::table('permisions')->insertGetId([
            'code' => 'requests.review',
            'name' => 'Aprobar solicitudes',
            'description' => 'Permite aprobar o rechazar solicitudes de ausencias/vacaciones.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $rrhhTeamId = DB::table('teams')
            ->whereRaw('LOWER(name) LIKE ?', ['%rrhh%'])
            ->value('id');

        if ($rrhhTeamId) {
            DB::table('team_permision')->insert([
                'team_id' => (int) $rrhhTeamId,
                'permision_id' => $permisionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_permision');
        Schema::dropIfExists('permisions');
    }
};
