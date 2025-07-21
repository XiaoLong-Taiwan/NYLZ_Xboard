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
        Schema::create('daily_checkins', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->comment('用户ID');
            $table->date('checkin_date')->comment('签到日期');
            $table->string('reward_type', 20)->comment('奖励类型：balance,traffic,both');
            $table->unsignedInteger('balance_reward')->default(0)->comment('余额奖励(分)');
            $table->unsignedBigInteger('traffic_reward')->default(0)->comment('流量奖励(字节)');
            $table->unsignedInteger('continuous_days')->default(1)->comment('连续签到天数');
            $table->decimal('bonus_multiplier', 3, 2)->default(1.00)->comment('奖励倍数');
            $table->string('ip_address', 45)->nullable()->comment('签到IP地址');
            $table->text('user_agent')->nullable()->comment('用户代理');
            $table->integer('created_at')->comment('签到时间');
            $table->integer('updated_at')->comment('更新时间');

            // 索引
            $table->index(['user_id', 'checkin_date'], 'idx_user_date');
            $table->index('checkin_date', 'idx_checkin_date');
            $table->index('user_id', 'idx_user_id');
            $table->index('continuous_days', 'idx_continuous_days');

            // 唯一约束：每个用户每天只能签到一次
            $table->unique(['user_id', 'checkin_date'], 'uk_user_daily_checkin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_checkins');
    }
};
