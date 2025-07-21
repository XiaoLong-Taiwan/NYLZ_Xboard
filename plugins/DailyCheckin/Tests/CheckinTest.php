<?php

namespace Plugin\DailyCheckin\Tests;

use App\Models\User;
use Plugin\DailyCheckin\Services\CheckinService;
use Plugin\DailyCheckin\Models\DailyCheckin;
use Plugin\DailyCheckin\Models\CheckinStats;
use App\Services\Plugin\PluginConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckinTest extends TestCase
{
    use RefreshDatabase;

    protected $checkinService;
    protected $configService;
    protected $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->checkinService = new CheckinService();
        $this->configService = app(PluginConfigService::class);
        
        // 创建测试用户
        $this->testUser = User::factory()->create([
            'balance' => 0,
            'transfer_enable' => 0,
        ]);

        // 设置测试配置
        $this->setTestConfig();
    }

    protected function setTestConfig(): void
    {
        // 模拟插件配置
        $config = [
            'enable' => '1',
            'reward_type' => 'both',
            'base_balance_reward' => '100',
            'base_traffic_reward' => '100',
            'continuous_bonus_enable' => '1',
            'continuous_bonus_multiplier' => '1.5',
            'max_continuous_days' => '7',
        ];

        // 这里需要模拟配置存储
        // 在实际测试中，你可能需要创建Plugin记录或使用其他方式
    }

    /** @test */
    public function user_can_checkin_successfully()
    {
        // 执行签到
        $result = $this->checkinService->checkin($this->testUser->id, '127.0.0.1', 'Test Agent');

        // 验证返回结果
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['data']['continuous_days']);
        $this->assertGreaterThan(0, $result['data']['balance_reward']);
        $this->assertGreaterThan(0, $result['data']['traffic_reward']);

        // 验证数据库记录
        $this->assertDatabaseHas('daily_checkins', [
            'user_id' => $this->testUser->id,
            'checkin_date' => today(),
            'continuous_days' => 1,
        ]);

        // 验证统计数据
        $stats = CheckinStats::where('user_id', $this->testUser->id)->first();
        $this->assertNotNull($stats);
        $this->assertEquals(1, $stats->total_checkins);
        $this->assertEquals(1, $stats->current_continuous_days);
    }

    /** @test */
    public function user_cannot_checkin_twice_in_same_day()
    {
        // 第一次签到
        $this->checkinService->checkin($this->testUser->id);

        // 第二次签到应该失败
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('今日已签到，请明天再来！');
        
        $this->checkinService->checkin($this->testUser->id);
    }

    /** @test */
    public function continuous_checkin_increases_reward()
    {
        // 模拟连续签到
        $firstResult = $this->checkinService->checkin($this->testUser->id);
        
        // 模拟第二天
        $this->travel(1)->day();
        
        // 更新用户统计以模拟连续签到
        $stats = CheckinStats::where('user_id', $this->testUser->id)->first();
        $stats->current_continuous_days = 1;
        $stats->last_checkin_date = today()->subDay();
        $stats->save();
        
        $secondResult = $this->checkinService->checkin($this->testUser->id);

        // 第二天的奖励应该更高
        $this->assertEquals(2, $secondResult['data']['continuous_days']);
        $this->assertGreaterThan($firstResult['data']['balance_reward'], $secondResult['data']['balance_reward']);
    }

    /** @test */
    public function checkin_status_returns_correct_information()
    {
        $status = $this->checkinService->getCheckinStatus($this->testUser->id);

        // 初始状态
        $this->assertFalse($status['today_checked']);
        $this->assertEquals(0, $status['continuous_days']);
        $this->assertEquals(0, $status['total_checkins']);
        $this->assertTrue($status['can_checkin']);

        // 签到后状态
        $this->checkinService->checkin($this->testUser->id);
        $status = $this->checkinService->getCheckinStatus($this->testUser->id);

        $this->assertTrue($status['today_checked']);
        $this->assertEquals(1, $status['continuous_days']);
        $this->assertEquals(1, $status['total_checkins']);
        $this->assertFalse($status['can_checkin']);
    }

    /** @test */
    public function rewards_are_granted_correctly()
    {
        $initialBalance = $this->testUser->balance;
        $initialTraffic = $this->testUser->transfer_enable;

        $result = $this->checkinService->checkin($this->testUser->id);

        // 刷新用户数据
        $this->testUser->refresh();

        // 验证余额增加
        $this->assertEquals(
            $initialBalance + $result['data']['balance_reward'],
            $this->testUser->balance
        );

        // 验证流量增加
        $this->assertEquals(
            $initialTraffic + $result['data']['traffic_reward'],
            $this->testUser->transfer_enable
        );
    }

    /** @test */
    public function continuous_days_reset_after_missing_day()
    {
        // 第一天签到
        $this->checkinService->checkin($this->testUser->id);
        
        // 跳过一天
        $this->travel(2)->days();
        
        // 第三天签到，连续天数应该重置为1
        $result = $this->checkinService->checkin($this->testUser->id);
        
        $this->assertEquals(1, $result['data']['continuous_days']);
    }

    /** @test */
    public function ranking_data_is_calculated_correctly()
    {
        // 创建多个用户并签到
        $users = User::factory()->count(3)->create();
        
        foreach ($users as $index => $user) {
            // 模拟不同的签到次数
            for ($i = 0; $i <= $index; $i++) {
                $this->travel($i)->days();
                $this->checkinService->checkin($user->id);
            }
            $this->travelBack();
        }

        $ranking = CheckinStats::getRankingData('total', 10);
        
        $this->assertCount(3, $ranking);
        // 验证排序正确（签到次数最多的在前面）
        $this->assertGreaterThanOrEqual($ranking[1]->total_checkins, $ranking[0]->total_checkins);
    }
}
