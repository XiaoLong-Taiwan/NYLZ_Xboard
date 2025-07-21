<?php

namespace Plugin\DailyCheckin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCheckin extends Model
{
    protected $table = 'daily_checkins';
    protected $dateFormat = 'U'; // 使用Unix时间戳

    protected $fillable = [
        'user_id',
        'checkin_date',
        'reward_type',
        'balance_reward',
        'traffic_reward',
        'continuous_days',
        'bonus_multiplier',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'checkin_date' => 'date',
        'balance_reward' => 'integer',
        'traffic_reward' => 'integer',
        'continuous_days' => 'integer',
        'bonus_multiplier' => 'decimal:2',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取今日签到记录
     */
    public static function getTodayCheckin(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->where('checkin_date', today())
            ->first();
    }

    /**
     * 获取用户最近的签到记录
     */
    public static function getLatestCheckin(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->orderBy('checkin_date', 'desc')
            ->first();
    }

    /**
     * 获取用户签到历史
     */
    public static function getUserHistory(int $userId, int $limit = 30): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', $userId)
            ->orderBy('checkin_date', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 获取签到排行榜（按连续天数）
     */
    public static function getContinuousRanking(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::select('user_id', \DB::raw('MAX(continuous_days) as max_continuous'))
            ->with('user:id,email')
            ->groupBy('user_id')
            ->orderBy('max_continuous', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 获取总签到次数排行榜
     */
    public static function getTotalRanking(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::select('user_id', \DB::raw('COUNT(*) as total_checkins'))
            ->with('user:id,email')
            ->groupBy('user_id')
            ->orderBy('total_checkins', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 获取统计数据
     */
    public static function getStats(): array
    {
        $today = today();
        $yesterday = $today->copy()->subDay();
        $thisMonth = $today->copy()->startOfMonth();

        return [
            'today_total' => static::where('checkin_date', $today)->count(),
            'yesterday_total' => static::where('checkin_date', $yesterday)->count(),
            'month_total' => static::where('checkin_date', '>=', $thisMonth)->count(),
            'all_time_total' => static::count(),
            'unique_users' => static::distinct('user_id')->count(),
        ];
    }
}
