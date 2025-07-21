<?php

namespace Plugin\DailyCheckin\Services;

use App\Models\User;
use App\Services\UserService;
use Plugin\DailyCheckin\Models\DailyCheckin;
use Plugin\DailyCheckin\Models\CheckinStats;
use App\Services\Plugin\PluginConfigService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckinService
{
    protected $configService;
    protected $userService;

    public function __construct()
    {
        $this->configService = app(PluginConfigService::class);
        $this->userService = app(UserService::class);
    }

    /**
     * 执行签到
     */
    public function checkin(int $userId, string $ipAddress = null, string $userAgent = null): array
    {
        // 检查是否已签到
        if ($this->hasTodayChecked($userId)) {
            throw new \Exception('今日已签到，请明天再来！');
        }

        // 获取用户
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('用户不存在');
        }

        // 检查插件是否启用
        if (!$this->isEnabled()) {
            throw new \Exception('签到功能已关闭');
        }

        try {
            return DB::transaction(function () use ($userId, $user, $ipAddress, $userAgent) {
                // 计算连续签到天数
                $continuousDays = $this->calculateContinuousDays($userId);

                // 计算奖励
                $rewards = $this->calculateRewards($continuousDays);

                // 创建签到记录
                $checkin = DailyCheckin::create([
                    'user_id' => $userId,
                    'checkin_date' => today(),
                    'reward_type' => $this->getConfig('reward_type', 'balance'),
                    'balance_reward' => $rewards['balance'],
                    'traffic_reward' => $rewards['traffic'],
                    'continuous_days' => $continuousDays,
                    'bonus_multiplier' => $rewards['multiplier'],
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);

                // 发放奖励
                $this->grantRewards($user, $rewards);

                // 更新统计数据
                $this->updateStats($userId, $rewards, $continuousDays);

                return [
                    'success' => true,
                    'message' => '签到成功！',
                    'data' => [
                        'continuous_days' => $continuousDays,
                        'balance_reward' => $rewards['balance'],
                        'traffic_reward' => $rewards['traffic'],
                        'multiplier' => $rewards['multiplier'],
                        'checkin_id' => $checkin->id,
                    ]
                ];
            });

        } catch (\Exception $e) {
            Log::error('签到失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 检查今日是否已签到
     */
    public function hasTodayChecked(int $userId): bool
    {
        return DailyCheckin::getTodayCheckin($userId) !== null;
    }

    /**
     * 获取用户签到状态
     */
    public function getCheckinStatus(int $userId): array
    {
        $todayChecked = $this->hasTodayChecked($userId);
        $stats = CheckinStats::getOrCreate($userId);
        $latestCheckin = DailyCheckin::getLatestCheckin($userId);

        return [
            'today_checked' => $todayChecked,
            'continuous_days' => $stats->current_continuous_days,
            'total_checkins' => $stats->total_checkins,
            'max_continuous_days' => $stats->max_continuous_days,
            'last_checkin_date' => $stats->last_checkin_date?->format('Y-m-d'),
            'next_reward' => $this->calculateRewards($stats->current_continuous_days + 1),
            'can_checkin' => !$todayChecked && $this->isEnabled(),
        ];
    }

    /**
     * 计算连续签到天数
     */
    protected function calculateContinuousDays(int $userId): int
    {
        $stats = CheckinStats::getOrCreate($userId);
        $latestCheckin = DailyCheckin::getLatestCheckin($userId);

        if (!$latestCheckin) {
            return 1; // 首次签到
        }

        $yesterday = today()->subDay();
        
        if ($latestCheckin->checkin_date->equalTo($yesterday)) {
            // 连续签到
            return $stats->current_continuous_days + 1;
        } else {
            // 中断了，重新开始
            return 1;
        }
    }

    /**
     * 计算奖励
     */
    protected function calculateRewards(int $continuousDays): array
    {
        $rewardType = $this->getConfig('reward_type', 'balance');
        $baseBalance = (int) $this->getConfig('base_balance_reward', 100);
        $baseTraffic = (int) $this->getConfig('base_traffic_reward', 100);
        
        // 计算连续奖励倍数
        $multiplier = $this->calculateBonusMultiplier($continuousDays);
        
        $balanceReward = 0;
        $trafficReward = 0;

        if ($rewardType === 'balance' || $rewardType === 'both') {
            $balanceReward = (int) ($baseBalance * $multiplier);
        }

        if ($rewardType === 'traffic' || $rewardType === 'both') {
            // 转换为字节 (MB -> 字节)
            $trafficReward = (int) ($baseTraffic * 1024 * 1024 * $multiplier);
        }

        return [
            'balance' => $balanceReward,
            'traffic' => $trafficReward,
            'multiplier' => $multiplier,
        ];
    }

    /**
     * 计算连续奖励倍数
     */
    protected function calculateBonusMultiplier(int $continuousDays): float
    {
        if (!$this->getConfig('continuous_bonus_enable', true)) {
            return 1.0;
        }

        $multiplier = (float) $this->getConfig('continuous_bonus_multiplier', 1.5);
        $maxDays = (int) $this->getConfig('max_continuous_days', 7);

        // 限制最大连续天数
        $effectiveDays = min($continuousDays, $maxDays);
        
        // 计算倍数：1 + (天数-1) * (倍数-1) / (最大天数-1)
        if ($effectiveDays <= 1) {
            return 1.0;
        }

        $bonusRate = ($multiplier - 1.0) * ($effectiveDays - 1) / ($maxDays - 1);
        return 1.0 + $bonusRate;
    }

    /**
     * 发放奖励
     */
    protected function grantRewards(User $user, array $rewards): void
    {
        // 发放余额奖励 - 直接更新避免嵌套锁
        if ($rewards['balance'] > 0) {
            $user->balance = ($user->balance ?? 0) + $rewards['balance'];
        }

        // 发放流量奖励
        if ($rewards['traffic'] > 0) {
            $user->transfer_enable = ($user->transfer_enable ?? 0) + $rewards['traffic'];
        }

        // 保存用户更改
        if ($rewards['balance'] > 0 || $rewards['traffic'] > 0) {
            $user->save();
        }
    }

    /**
     * 更新统计数据
     */
    protected function updateStats(int $userId, array $rewards, int $continuousDays): void
    {
        $stats = CheckinStats::getOrCreate($userId);
        $isFirstCheckin = $stats->total_checkins === 0;
        
        $stats->updateCheckinStats(
            $rewards['balance'],
            $rewards['traffic'],
            $continuousDays,
            $isFirstCheckin
        );
    }

    /**
     * 获取配置
     */
    protected function getConfig(string $key, $default = null)
    {
        $config = $this->configService->getDbConfig('daily_checkin');
        return $config[$key] ?? $default;
    }

    /**
     * 检查插件是否启用
     */
    protected function isEnabled(): bool
    {
        return (bool) $this->getConfig('enable', false);
    }
}
