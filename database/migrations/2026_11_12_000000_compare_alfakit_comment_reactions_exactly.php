<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL and MariaDB compare every emoji as equal under their default collation, so a second,
 * different reaction of the same person was taken for the first. Tables created before the
 * column had a binary collation get it here; other databases already compare exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true) || ! Schema::hasTable('alfakit_comment_reactions')) {
            return;
        }

        Schema::table('alfakit_comment_reactions', function (Blueprint $table): void {
            $table->string('reaction', 32)->collation('utf8mb4_bin')->change();
        });
    }

    public function down(): void
    {
        // The exact comparison is kept: going back would merge different reactions again.
    }
};
