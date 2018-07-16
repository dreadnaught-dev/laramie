<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Laramie\Globals;

class CreateDataTable extends Migration
{
    /**
     * Create the main data table, its indices, and seed it with the default roles.
     */
    public function up()
    {
        Schema::create('laramie_data', function (Blueprint $table) {
            $table->uuid('id');
            $table->uuid('user_id')->nullable();
            $table->string('type');
            $table->jsonb('data')->default('{}');
            $table->timestamps();

            $table->primary('id');
        });
        \DB::statement('CREATE INDEX on laramie_data (type)');
        \DB::statement('CREATE INDEX on laramie_data USING GIN (data jsonb_path_ops)');

        \DB::statement('INSERT INTO laramie_data (id, user_id, type, data, created_at, updated_at) VALUES(\''.Globals::SuperAdminRoleId.'\', null, \'LaramieRole\', \'{"name": "Super Admin"}\', now(), now())');
        \DB::statement('INSERT INTO laramie_data (id, user_id, type, data, created_at, updated_at) VALUES(\''.Globals::AdminRoleId.'\', null, \'LaramieRole\', \'{"name": "Admin"}\', now(), now())');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('laramie_data');
    }
}
