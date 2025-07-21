<?php

namespace Plugin\DailyCheckin\Controllers;

use App\Http\Controllers\Controller;
use Plugin\DailyCheckin\Models\DailyCheckin;
use Plugin\DailyCheckin\Models\CheckinStats;
use App\Services\Plugin\PluginConfigService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    protected $configService;

    public function __construct(PluginConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * 获取签到统计数据
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $stats = DailyCheckin::getStats();
            
            // 获取最近7天的签到数据
            $recentDays = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = today()->subDays($i);
                $count = DailyCheckin::where('checkin_date', $date)->count();
                $recentDays[] = [
                    'date' => $date->format('Y-m-d'),
                    'count' => $count
                ];
            }

            // 获取奖励统计
            $rewardStats = DailyCheckin::selectRaw('
                SUM(balance_reward) as total_balance,
                SUM(traffic_reward) as total_traffic,
                AVG(continuous_days) as avg_continuous
            ')->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => $stats,
                    'recent_days' => $recentDays,
                    'rewards' => [
                        'total_balance' => $rewardStats->total_balance ?? 0,
                        'total_traffic_mb' => round(($rewardStats->total_traffic ?? 0) / 1024 / 1024, 2),
                        'avg_continuous' => round($rewardStats->avg_continuous ?? 0, 1),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取签到记录列表
     */
    public function getRecords(Request $request): JsonResponse
    {
        try {
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);
            $userId = $request->input('user_id');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $query = DailyCheckin::with('user:id,email')
                ->orderBy('created_at', 'desc');

            // 筛选条件
            if ($userId) {
                $query->where('user_id', $userId);
            }

            if ($startDate) {
                $query->where('checkin_date', '>=', $startDate);
            }

            if ($endDate) {
                $query->where('checkin_date', '<=', $endDate);
            }

            $records = $query->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $records->items(),
                    'pagination' => [
                        'current_page' => $records->currentPage(),
                        'last_page' => $records->lastPage(),
                        'per_page' => $records->perPage(),
                        'total' => $records->total(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除签到记录
     */
    public function deleteRecord(Request $request, int $id): JsonResponse
    {
        try {
            $record = DailyCheckin::findOrFail($id);
            
            // 删除记录前需要更新统计数据
            $stats = CheckinStats::where('user_id', $record->user_id)->first();
            if ($stats) {
                $stats->total_checkins = max(0, $stats->total_checkins - 1);
                $stats->total_balance_earned = max(0, $stats->total_balance_earned - $record->balance_reward);
                $stats->total_traffic_earned = max(0, $stats->total_traffic_earned - $record->traffic_reward);
                $stats->save();
            }

            $record->delete();

            return response()->json([
                'success' => true,
                'message' => '签到记录已删除'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取插件配置
     */
    public function getConfig(Request $request): JsonResponse
    {
        try {
            $config = $this->configService->getConfig('daily_checkin');

            return response()->json([
                'success' => true,
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新插件配置
     */
    public function updateConfig(Request $request): JsonResponse
    {
        try {
            $config = $request->input('config', []);
            
            $this->configService->updateConfig('daily_checkin', $config);

            return response()->json([
                'success' => true,
                'message' => '配置已更新'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 重置用户签到统计
     */
    public function resetUserStats(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id');
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => '用户ID不能为空'
                ], 400);
            }

            $stats = CheckinStats::where('user_id', $userId)->first();
            if ($stats) {
                $stats->current_continuous_days = 0;
                $stats->save();
            }

            return response()->json([
                'success' => true,
                'message' => '用户签到统计已重置'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
