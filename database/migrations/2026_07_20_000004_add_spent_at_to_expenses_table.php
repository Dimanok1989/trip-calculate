<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dateTime('spent_at')->nullable()->after('comment');
            $table->boolean('has_time')->default(true)->after('spent_at');
        });

        foreach (DB::table('expenses')->get() as $expense) {
            DB::table('expenses')->where('id', $expense->id)->update([
                'spent_at' => $expense->created_at,
                'has_time' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['spent_at', 'has_time']);
        });
    }
};
