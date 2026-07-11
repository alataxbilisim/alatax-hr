<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * encrypt() çıktısı varchar(255)'e sığmıyor — text'e genişlet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->change();
        });
    }

    public function down(): void
    {
        // PostgreSQL: uzun değerler varsa kısaltma hatası olabilir — dikkatli rollback
        DB::statement('ALTER TABLE users ALTER COLUMN two_factor_secret TYPE varchar(255) USING left(two_factor_secret, 255)');
    }
};
