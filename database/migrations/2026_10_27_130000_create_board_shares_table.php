<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_shares', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('board_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('access', 16)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'recipient_user_id', 'access']);
            $table->index(['board_id', 'created_at']);
        });

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE board_shares
            ADD CONSTRAINT board_shares_shape_check CHECK (
                (kind = 'user' AND recipient_user_id IS NOT NULL AND access IN ('view', 'edit') AND expires_at IS NULL)
                OR (kind = 'team' AND recipient_user_id IS NULL AND access IN ('view', 'edit') AND expires_at IS NULL)
                OR (kind = 'link' AND recipient_user_id IS NULL AND access IS NULL)
            )
            SQL);
        DB::statement("CREATE UNIQUE INDEX board_shares_one_user_recipient ON board_shares (board_id, recipient_user_id) WHERE kind = 'user'");
        DB::statement("CREATE UNIQUE INDEX board_shares_one_team ON board_shares (board_id) WHERE kind = 'team'");
    }

    public function down(): void
    {
        Schema::dropIfExists('board_shares');
    }
};
