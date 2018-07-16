<?php

use Illuminate\Database\Migrations\Migration;

class CreateExtensionUuidOssp extends Migration
{
    /**
     * Create the uuid-ossp extension -- this gives the db access to uuid functions. Useful for bulk creation.
     */
    public function up()
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        } catch (\Exception $e) {
            echo 'Failed to create Extension uuid-ossp';
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
    }
}
