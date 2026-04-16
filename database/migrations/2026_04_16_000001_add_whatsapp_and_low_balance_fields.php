<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_number', 30)->nullable()->after('email');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('scheduled_slot', 20)->nullable()->after('type');
            $table->timestamp('sent_at')->nullable()->after('is_sent');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn(['scheduled_slot', 'sent_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('whatsapp_number');
        });
    }
};
