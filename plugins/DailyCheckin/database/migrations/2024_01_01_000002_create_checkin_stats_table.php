<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('checkin_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->comment('用户ID');
            $table->unsignedInteger('total_checkins')->default(0)->comment('总签到次数');
            $table->unsignedInteger('current_continuous_days')->default(0)->comment('当前连续签到天数');
            $table->unsignedInteger('max_continuous_days')->default(0)->comment('最大连续签到天数');
            $table->date('last_checkin_date')->nullable()->comment('最后签到日期');
            $table->date('first_checkin_date')->nullable()->comment('首次签到日期');
            $table->unsignedBigInteger('total_balance_earned')->default(0)->comment('累计获得余额(分)');
            $table->unsignedBigInteger('total_traffic_earned')->default(0)->comment('累计获得流量(KB)');
            $table->integer('created_at');
            $table->integer('updated_at');

            // 索引
            $table->unique('user_id', 'uk_user_stats');
            $table->index('total_checkins', 'idx_total_checkins');
            $table->index('current_continuous_days', 'idx_current_continuous');
            $table->index('max_continuous_days', 'idx_max_continuous');
            $table->index('last_checkin_date', 'idx_last_checkin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkin_stats');
    }
};
