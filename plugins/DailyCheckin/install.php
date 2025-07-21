<?php

/**
 * 每日签到插件安装脚本
 * 
 * 使用方法：
 * php install.php
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Services\Plugin\PluginManager;
use Illuminate\Support\Facades\Artisan;

class CheckinPluginInstaller
{
    protected $pluginCode = 'daily_checkin';
    protected $pluginManager;

    public function __construct()
    {
        // 初始化Laravel应用
        $app = require_once __DIR__ . '/../../../bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

        $this->pluginManager = app(PluginManager::class);
    }

    public function install()
    {
        echo "开始安装每日签到插件...\n";

        try {
            // 0. 安装前自动清理（方便测试）
            echo "清理安装前残留数据...\n";
            $this->cleanupBeforeInstall();

            // 1. 检查插件是否已安装
            if ($this->isInstalled()) {
                echo "插件已安装，跳过安装步骤\n";
                return;
            }

            // 2. 运行数据库迁移
            echo "运行数据库迁移...\n";
            $this->runMigrations();

            // 3. 安装插件
            echo "注册插件...\n";
            $this->pluginManager->install($this->pluginCode);

            // 4. 启用插件
            echo "启用插件...\n";
            $this->pluginManager->enable($this->pluginCode);

            // 5. 设置默认配置
            echo "设置默认配置...\n";
            $this->setDefaultConfig();

            // 6. 创建定时任务提示
            echo "安装完成！\n";
            $this->showPostInstallInstructions();

        } catch (\Exception $e) {
            echo "安装失败: " . $e->getMessage() . "\n";
            echo "错误详情: " . $e->getTraceAsString() . "\n";
        }
    }

    protected function cleanupBeforeInstall(): void
    {
        try {
            // 1. 删除插件记录
            $deleted = \DB::table('v2_plugins')->where('code', $this->pluginCode)->delete();
            if ($deleted > 0) {
                echo "   删除了 {$deleted} 条插件记录\n";
            }

            // 2. 删除数据库表
            \DB::statement('DROP TABLE IF EXISTS daily_checkins');
            \DB::statement('DROP TABLE IF EXISTS checkin_stats');
            echo "   删除了数据库表\n";

            // 3. 清理迁移记录
            $deletedMigrations = \DB::table('migrations')
                ->where('migration', 'like', '%daily_checkin%')
                ->orWhere('migration', 'like', '%checkin%')
                ->delete();
            if ($deletedMigrations > 0) {
                echo "   删除了 {$deletedMigrations} 条迁移记录\n";
            }

            echo "   清理完成\n";

        } catch (\Exception $e) {
            echo "   清理过程中出现警告: " . $e->getMessage() . "\n";
            // 不抛出异常，继续安装过程
        }
    }

    protected function isInstalled(): bool
    {
        return \DB::table('v2_plugins')->where('code', $this->pluginCode)->exists();
    }

    protected function runMigrations(): void
    {
        Artisan::call('migrate', [
            '--path' => 'plugins/DailyCheckin/database/migrations',
            '--force' => true
        ]);

        echo Artisan::output();
    }

    protected function setDefaultConfig(): void
    {
        $defaultConfig = [
            'enable' => '1',
            'reward_type' => 'balance',
            'base_balance_reward' => '100',
            'base_traffic_reward' => '100',
            'continuous_bonus_enable' => '1',
            'continuous_bonus_multiplier' => '1.5',
            'max_continuous_days' => '7',
            'reset_time' => '0',
            'show_ranking' => '1',
        ];

        \App\Models\Plugin::where('code', $this->pluginCode)
            ->update(['config' => json_encode($defaultConfig)]);
    }

    protected function showPostInstallInstructions(): void
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "每日签到插件安装成功！\n";
        echo str_repeat("=", 60) . "\n";
        
        echo "\n📋 后续步骤：\n";
        echo "1. 在管理后台配置插件参数\n";
        echo "2. 添加定时任务（推荐）：\n";
        echo "   0 1 * * * cd " . base_path() . " && php artisan checkin:reset-continuous\n";
        echo "3. 在前端集成签到组件\n";
        
        echo "\n🔗 API接口：\n";
        echo "- 签到状态: GET /api/v1/plugin/daily-checkin/status\n";
        echo "- 执行签到: POST /api/v1/plugin/daily-checkin/checkin\n";
        echo "- 签到历史: GET /api/v1/plugin/daily-checkin/history\n";
        echo "- 排行榜: GET /api/v1/plugin/daily-checkin/ranking\n";
        
        echo "\n⚙️ 管理接口：\n";
        echo "- 统计数据: GET /api/v1/admin/plugin/daily-checkin/stats\n";
        echo "- 签到记录: GET /api/v1/admin/plugin/daily-checkin/records\n";
        echo "- 插件配置: GET/POST /api/v1/admin/plugin/daily-checkin/config\n";
        
        echo "\n📖 更多信息请查看 README.md 文件\n";
        echo str_repeat("=", 60) . "\n";
    }

    public function uninstall()
    {
        echo "开始卸载每日签到插件...\n";
        echo str_repeat("=", 50) . "\n";

        try {
            // 1. 检查插件是否存在
            if (!$this->isInstalled()) {
                echo "插件未安装，无需卸载\n";
                return;
            }

            // 2. 显示将要删除的数据
            echo "将要删除以下数据:\n";
            echo "- 插件记录\n";
            echo "- 数据库表: daily_checkins, checkin_stats\n";
            echo "- 迁移记录\n";
            echo "- 所有签到数据\n";

            // 3. 确认卸载
            echo "\n确认卸载？这将删除所有签到数据！(y/N): ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            fclose($handle);

            if (trim($line) !== 'y' && trim($line) !== 'Y') {
                echo "卸载已取消\n";
                return;
            }

            // 4. 禁用插件
            echo "\n1. 禁用插件...\n";
            $this->pluginManager->disable($this->pluginCode);
            echo "   ✓ 插件已禁用\n";

            // 5. 卸载插件（这会调用插件的uninstall方法）
            echo "\n2. 卸载插件...\n";
            $this->pluginManager->uninstall($this->pluginCode);
            echo "   ✓ 插件已卸载\n";

            // 6. 验证清理结果
            echo "\n3. 验证清理结果...\n";
            $this->verifyUninstall();

            echo "\n" . str_repeat("=", 50) . "\n";
            echo "✅ 卸载完成！所有数据已清理。\n";

        } catch (\Exception $e) {
            echo "\n❌ 卸载失败: " . $e->getMessage() . "\n";
            echo "请检查日志文件获取详细信息。\n";
        }
    }

    protected function verifyUninstall(): void
    {
        $issues = [];

        // 检查插件记录
        $pluginExists = \DB::table('v2_plugins')->where('code', $this->pluginCode)->exists();
        if ($pluginExists) {
            $issues[] = "插件记录仍然存在";
        } else {
            echo "   ✓ 插件记录已删除\n";
        }

        // 检查数据库表
        $tablesExist = \DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('daily_checkins', 'checkin_stats')");
        if (!empty($tablesExist)) {
            $issues[] = "数据库表仍然存在";
        } else {
            echo "   ✓ 数据库表已删除\n";
        }

        // 检查迁移记录
        $migrationsExist = \DB::table('migrations')
            ->where('migration', 'like', '%daily_checkin%')
            ->orWhere('migration', 'like', '%checkin%')
            ->exists();
        if ($migrationsExist) {
            $issues[] = "迁移记录仍然存在";
        } else {
            echo "   ✓ 迁移记录已删除\n";
        }

        if (!empty($issues)) {
            echo "   ⚠ 发现问题:\n";
            foreach ($issues as $issue) {
                echo "     - {$issue}\n";
            }
        } else {
            echo "   ✓ 所有数据已完全清理\n";
        }
    }

    protected function dropTables(): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('daily_checkins');
        \Illuminate\Support\Facades\Schema::dropIfExists('checkin_stats');
        echo "数据库表已删除\n";
    }

    public function status()
    {
        echo "每日签到插件状态：\n";
        echo str_repeat("-", 40) . "\n";

        $plugin = \App\Models\Plugin::where('code', $this->pluginCode)->first();

        if (!$plugin) {
            echo "状态: 未安装\n";
            return;
        }

        echo "状态: " . ($plugin->is_enabled ? "已启用" : "已禁用") . "\n";
        echo "版本: " . $plugin->version . "\n";
        echo "安装时间: " . $plugin->installed_at . "\n";

        // 统计数据
        $stats = \Plugin\DailyCheckin\Models\DailyCheckin::getStats();
        echo "\n📊 统计数据：\n";
        echo "今日签到: " . $stats['today_total'] . "\n";
        echo "昨日签到: " . $stats['yesterday_total'] . "\n";
        echo "本月签到: " . $stats['month_total'] . "\n";
        echo "总签到数: " . $stats['all_time_total'] . "\n";
        echo "参与用户: " . $stats['unique_users'] . "\n";
    }
}

// 命令行处理
if (php_sapi_name() === 'cli') {
    $installer = new CheckinPluginInstaller();
    
    $command = $argv[1] ?? 'install';
    
    switch ($command) {
        case 'install':
            $installer->install();
            break;
        case 'uninstall':
            $installer->uninstall();
            break;
        case 'status':
            $installer->status();
            break;
        default:
            echo "用法: php install.php [install|uninstall|status]\n";
            echo "  install   - 安装插件\n";
            echo "  uninstall - 卸载插件\n";
            echo "  status    - 查看插件状态\n";
    }
}
