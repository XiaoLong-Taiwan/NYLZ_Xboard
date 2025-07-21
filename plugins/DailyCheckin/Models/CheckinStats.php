<?php

namespace Plugin\DailyCheckin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckinStats extends Model
{
    protected $table = 'checkin_stats';
    protected $dateFormat = 'U'; // 使用Unix时间戳

    protected $fillable = [
        'user_id',
        'total_checkins',
        'current_continuous_days',
        'max_continuous_days',
        'last_checkin_date',
        'first_checkin_date',
        'total_balance_earned',
        'total_traffic_earned',
    ];

    protected $casts = [
        'total_checkins' => 'integer',
        'current_continuous_days' => 'integer',
        'max_continuous_days' => 'integer',
        'last_checkin_date' => 'date',
        'first_checkin_date' => 'date',
        'total_balance_earned' => 'integer',
        'total_traffic_earned' => 'integer',
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
     * 获取或创建用户统计记录
     */
    public static function getOrCreate(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'total_checkins' => 0,
                'current_continuous_days' => 0,
                'max_continuous_days' => 0,
                'total_balance_earned' => 0,
                'total_traffic_earned' => 0,
            ]
        );
    }

    /**
     * 更新签到统计
     */
    public function updateCheckinStats(
        int $balanceReward,
        int $trafficReward,
        int $continuousDays,
        bool $isFirstCheckin = false
    ): void {
        $this->total_checkins += 1;
        $this->current_continuous_days = $continuousDays;
        $this->max_continuous_days = max($this->max_continuous_days, $continuousDays);
        $this->last_checkin_date = today();
        $this->total_balance_earned += $balanceReward;
        $this->total_traffic_earned += $trafficReward;

        if ($isFirstCheckin) {
            $this->first_checkin_date = today();
        }

        $this->save();
    }

    /**
     * 重置连续签到天数
     */
    public function resetContinuousDays(): void
    {
        $this->current_continuous_days = 0;
        $this->save();
    }

    /**
     * 获取排行榜数据
     */
    public static function getRankingData(string $type = 'continuous', int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::with('user:id,email');

        switch ($type) {
            case 'continuous':
                $query->orderBy('current_continuous_days', 'desc');
                break;
            case 'max_continuous':
                $query->orderBy('max_continuous_days', 'desc');
                break;
            case 'total':
                $query->orderBy('total_checkins', 'desc');
                break;
            case 'balance':
                $query->orderBy('total_balance_earned', 'desc');
                break;
            case 'traffic':
                $query->orderBy('total_traffic_earned', 'desc');
                break;
            default:
                $query->orderBy('current_continuous_days', 'desc');
        }

        return $query->limit($limit)->get();
    }
}
