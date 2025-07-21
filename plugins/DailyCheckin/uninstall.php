<?php

/**
 * 每日签到插件专用卸载脚本
 * 
 * 使用方法：
 * php uninstall.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Plugin\PluginManager;
use Illuminate\Support\Facades\DB;

class CheckinPluginUninstaller
{
    protected $pluginCode = 'daily_checkin';
    protected $pluginManager;

    public function __construct()
    {
        // 初始化Laravel应用
        $app = require_once __DIR__ . '/../../bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

        $this->pluginManager = app(PluginManager::class);
    }

    public function uninstall()
    {
        echo "🗑️  每日签到插件卸载工具\n";
        echo str_repeat("=", 60) . "\n";

        try {
            // 1. 检查插件状态
            $this->checkPluginStatus();
            
            // 2. 显示将要删除的数据
            $this->showDataToDelete();
            
            // 3. 确认卸载
            if (!$this->confirmUninstall()) {
                echo "❌ 卸载已取消\n";
                return;
            }
            
            // 4. 执行卸载
            $this->performUninstall();
            
            // 5. 验证结果
            $this->verifyUninstall();
            
            echo "\n" . str_repeat("=", 60) . "\n";
            echo "✅ 插件卸载完成！\n";
            
        } catch (\Exception $e) {
            echo "\n❌ 卸载失败: " . $e->getMessage() . "\n";
            echo "请检查日志文件获取详细信息。\n";
            exit(1);
        }
    }

    protected function checkPluginStatus(): void
    {
        echo "1. 检查插件状态...\n";
        
        $plugin = DB::table('v2_plugins')->where('code', $this->pluginCode)->first();
        
        if (!$plugin) {
            echo "   ⚠ 插件未安装\n";
            
            // 检查是否有残留数据
            $hasData = $this->hasResidualData();
            if ($hasData) {
                echo "   发现残留数据，将进行清理\n";
            } else {
                echo "   没有发现相关数据\n";
                exit(0);
            }
        } else {
            echo "   插件状态: " . ($plugin->is_enabled ? "已启用" : "已禁用") . "\n";
            echo "   安装时间: {$plugin->installed_at}\n";
        }
    }

    protected function hasResidualData(): bool
    {
        // 检查数据库表
        $tables = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('daily_checkins', 'checkin_stats')");
        
        // 检查迁移记录
        $migrations = DB::table('migrations')
            ->where('migration', 'like', '%daily_checkin%')
            ->orWhere('migration', 'like', '%checkin%')
            ->exists();
            
        return !empty($tables) || $migrations;
    }

    protected function showDataToDelete(): void
    {
        echo "\n2. 将要删除的数据:\n";
        
        // 检查插件记录
        $pluginExists = DB::table('v2_plugins')->where('code', $this->pluginCode)->exists();
        echo "   " . ($pluginExists ? "✓" : "-") . " 插件记录\n";
        
        // 检查数据库表
        $tables = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('daily_checkins', 'checkin_stats')");
        foreach (['daily_checkins', 'checkin_stats'] as $table) {
            $exists = collect($tables)->contains('TABLE_NAME', $table);
            echo "   " . ($exists ? "✓" : "-") . " {$table} 表\n";
            
            if ($exists) {
                $count = DB::table($table)->count();
                echo "     ({$count} 条记录)\n";
            }
        }
        
        // 检查迁移记录
        $migrationCount = DB::table('migrations')
            ->where('migration', 'like', '%daily_checkin%')
            ->orWhere('migration', 'like', '%checkin%')
            ->count();
        echo "   " . ($migrationCount > 0 ? "✓" : "-") . " 迁移记录 ({$migrationCount} 条)\n";
    }

    protected function confirmUninstall(): bool
    {
        echo "\n⚠️  警告: 这将永久删除所有签到数据！\n";
        echo "确认卸载？(y/N): ";
        
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        return trim($line) === 'y' || trim($line) === 'Y';
    }

    protected function performUninstall(): void
    {
        echo "\n3. 执行卸载...\n";
        
        try {
            // 通过Xboard的插件管理器卸载
            if (DB::table('v2_plugins')->where('code', $this->pluginCode)->exists()) {
                echo "   禁用插件...\n";
                $this->pluginManager->disable($this->pluginCode);
                
                echo "   卸载插件...\n";
                $this->pluginManager->uninstall($this->pluginCode);
            }
            
            // 手动清理可能的残留数据
            echo "   清理残留数据...\n";
            $this->cleanupResidualData();
            
        } catch (\Exception $e) {
            echo "   ⚠ 自动卸载失败，尝试手动清理: " . $e->getMessage() . "\n";
            $this->cleanupResidualData();
        }
    }

    protected function cleanupResidualData(): void
    {
        // 删除插件记录
        $deleted = DB::table('v2_plugins')->where('code', $this->pluginCode)->delete();
        if ($deleted > 0) {
            echo "     ✓ 删除插件记录\n";
        }
        
        // 删除数据库表
        DB::statement('DROP TABLE IF EXISTS daily_checkins');
        DB::statement('DROP TABLE IF EXISTS checkin_stats');
        echo "     ✓ 删除数据库表\n";
        
        // 清理迁移记录
        $deletedMigrations = DB::table('migrations')
            ->where('migration', 'like', '%daily_checkin%')
            ->orWhere('migration', 'like', '%checkin%')
            ->delete();
        if ($deletedMigrations > 0) {
            echo "     ✓ 清理迁移记录 ({$deletedMigrations} 条)\n";
        }
    }

    protected function verifyUninstall(): void
    {
        echo "\n4. 验证卸载结果...\n";
        
        $issues = [];
        
        // 检查插件记录
        if (DB::table('v2_plugins')->where('code', $this->pluginCode)->exists()) {
            $issues[] = "插件记录仍然存在";
        }
        
        // 检查数据库表
        $tables = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('daily_checkins', 'checkin_stats')");
        if (!empty($tables)) {
            $issues[] = "数据库表仍然存在";
        }
        
        // 检查迁移记录
        if (DB::table('migrations')->where('migration', 'like', '%daily_checkin%')->orWhere('migration', 'like', '%checkin%')->exists()) {
            $issues[] = "迁移记录仍然存在";
        }
        
        if (empty($issues)) {
            echo "   ✅ 所有数据已完全清理\n";
        } else {
            echo "   ⚠ 发现残留数据:\n";
            foreach ($issues as $issue) {
                echo "     - {$issue}\n";
            }
        }
    }
}

// 命令行处理
if (php_sapi_name() === 'cli') {
    $uninstaller = new CheckinPluginUninstaller();
    $uninstaller->uninstall();
}
