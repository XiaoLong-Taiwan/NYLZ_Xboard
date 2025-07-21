<?php

namespace Plugin\DailyCheckin\Controllers;

use App\Http\Controllers\Controller;
use Plugin\DailyCheckin\Services\CheckinService;
use Plugin\DailyCheckin\Models\DailyCheckin;
use Plugin\DailyCheckin\Models\CheckinStats;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CheckinController extends Controller
{
    protected $checkinService;

    public function __construct(CheckinService $checkinService)
    {
        $this->checkinService = $checkinService;
    }

    /**
     * 获取签到状态
     */
    public function getStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $status = $this->checkinService->getCheckinStatus($user->id);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 执行签到
     */
    public function checkin(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();

            $result = $this->checkinService->checkin($user->id, $ipAddress, $userAgent);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 获取签到历史
     */
    public function getHistory(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $limit = $request->input('limit', 30);
            $page = $request->input('page', 1);

            $history = DailyCheckin::where('user_id', $user->id)
                ->orderBy('checkin_date', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $history->items(),
                    'pagination' => [
                        'current_page' => $history->currentPage(),
                        'last_page' => $history->lastPage(),
                        'per_page' => $history->perPage(),
                        'total' => $history->total(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 获取签到排行榜
     */
    public function getRanking(Request $request): JsonResponse
    {
        try {
            $type = $request->input('type', 'continuous'); // continuous, total, balance, traffic
            $limit = $request->input('limit', 10);

            $ranking = CheckinStats::getRankingData($type, $limit);

            // 格式化排行榜数据
            $formattedRanking = $ranking->map(function ($item, $index) use ($type) {
                $userData = [
                    'rank' => $index + 1,
                    'user_id' => $item->user_id,
                    'email' => $item->user->email ?? '未知用户',
                ];

                switch ($type) {
                    case 'continuous':
                        $userData['value'] = $item->current_continuous_days;
                        $userData['label'] = '连续签到天数';
                        break;
                    case 'max_continuous':
                        $userData['value'] = $item->max_continuous_days;
                        $userData['label'] = '最大连续天数';
                        break;
                    case 'total':
                        $userData['value'] = $item->total_checkins;
                        $userData['label'] = '总签到次数';
                        break;
                    case 'balance':
                        $userData['value'] = $item->total_balance_earned;
                        $userData['label'] = '累计获得余额(分)';
                        break;
                    case 'traffic':
                        $userData['value'] = round($item->total_traffic_earned / 1024 / 1024, 2);
                        $userData['label'] = '累计获得流量(MB)';
                        break;
                }

                return $userData;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'type' => $type,
                    'ranking' => $formattedRanking
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 获取签到统计概览
     */
    public function getOverview(Request $request): JsonResponse
    {
        try {
            $stats = DailyCheckin::getStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
