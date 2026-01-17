<?php

declare(strict_types=1);

use App\Models\Credential;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Drop the old unique constraint and add new one that includes user_id
        Schema::table('credentials', function (Blueprint $table) {
            $table->dropUnique(['service', 'key']);
            $table->unique(['service', 'key', 'user_id']);
        });

        // Migrate existing Trakt tokens to the first admin user
        $adminUser = User::where('role', 'admin')->first();
        if ($adminUser) {
            Credential::where('service', 'trakt')
                ->whereNull('user_id')
                ->update(['user_id' => $adminUser->id]);
        }
    }

    public function down(): void
    {
        // Move Trakt credentials back to global (null user_id)
        Credential::where('service', 'trakt')
            ->whereNotNull('user_id')
            ->update(['user_id' => null]);

        Schema::table('credentials', function (Blueprint $table) {
            $table->dropUnique(['service', 'key', 'user_id']);
            $table->unique(['service', 'key']);
        });

        Schema::table('credentials', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
