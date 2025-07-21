<?php

namespace Plugin\DailyCheckin\Console\Commands;

use Plugin\DailyCheckin\Models\CheckinStats;
use Plugin\DailyCheckin\Models\DailyCheckin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetContinuousCheckin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkin:reset-continuous';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '重置中断签到用户的连续签到天数';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('开始检查并重置连续签到天数...');

        try {
            $yesterday = today()->subDay();
            $twoDaysAgo = today()->subDays(2);

            // 查找需要重置的用户：昨天没有签到但前天有签到的用户
            $usersToReset = CheckinStats::where('current_continuous_days', '>', 0)
                ->where('last_checkin_date', '<', $yesterday)
                ->get();

            $resetCount = 0;

            foreach ($usersToReset as $stats) {
                // 检查用户是否真的中断了签到
                $lastCheckin = DailyCheckin::where('user_id', $stats->user_id)
                    ->where('checkin_date', $yesterday)
                    ->first();

                if (!$lastCheckin) {
                    // 用户昨天没有签到，重置连续天数
                    $stats->current_continuous_days = 0;
                    $stats->save();
                    $resetCount++;

                    $this->line("重置用户 {$stats->user_id} 的连续签到天数");
                }
            }

            $this->info("完成！共重置了 {$resetCount} 个用户的连续签到天数");

            Log::info('连续签到重置任务完成', [
                'reset_count' => $resetCount,
                'date' => today()->format('Y-m-d')
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('重置连续签到天数时发生错误: ' . $e->getMessage());
            
            Log::error('连续签到重置任务失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}
