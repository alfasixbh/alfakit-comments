<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alfakit_comments', function (Blueprint $table): void {
            $table->id();
            $table->morphs('commentable');
            $table->nullableMorphs('author');
            $table->longText('body');
            $table->timestamps();
        });

        Schema::create('alfakit_comment_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comment_id')->constrained('alfakit_comments')->cascadeOnDelete();
            $table->morphs('reactor');
            $table->string('reaction', 32);
            $table->timestamps();

            $table->unique(['comment_id', 'reactor_type', 'reactor_id', 'reaction'], 'alfakit_comment_reactions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alfakit_comment_reactions');
        Schema::dropIfExists('alfakit_comments');
    }
};
