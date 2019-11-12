<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLaramieDataMetaTable extends Migration
{
    /**
     * Create the meta table -- this will hold historical item data. It should look nearly identical to the main data
     * table with the exception of an additional relationship to point to its parent `laramie_data` item.
     */
    public function up()
    {
        Schema::create('laramie_data_meta', function (Blueprint $table) {
            $table->uuid('id');
            $table->uuid('user_id')->nullable();
            $table->uuid('laramie_data_id');
            $table->string('type');
            $table->jsonb('data')->default('{}');
            $table->timestamps(); // created_at is the time that _this_ record was created; updated_at is the time that the parent _item_ was prior to the revision getting added

            $table->primary('id');
            $table->index('laramie_data_id');
            $table->index('created_at');
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('laramie_data_meta');
    }
}
