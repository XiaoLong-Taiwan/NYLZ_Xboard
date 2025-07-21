<?php

/**
 * 快速清理脚本 - 专门用于测试
 * 
 * 使用方法：
 * php quick_cleanup.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// 初始化Laravel应用
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🧹 快速清理每日签到插件数据...\n";
echo str_repeat("-", 40) . "\n";

try {
    $totalCleaned = 0;
    
    // 1. 删除插件记录
    $deleted = DB::table('v2_plugins')->where('code', 'daily_checkin')->delete();
    if ($deleted > 0) {
        echo "✓ 插件记录: {$deleted} 条\n";
        $totalCleaned += $deleted;
    }
    
    // 2. 删除数据表
    DB::statement('DROP TABLE IF EXISTS daily_checkins');
    DB::statement('DROP TABLE IF EXISTS checkin_stats');
    echo "✓ 数据表: 已删除\n";
    $totalCleaned += 2;
    
    // 3. 清理迁移记录
    $deletedMigrations = DB::table('migrations')
        ->where('migration', 'like', '%daily_checkin%')
        ->orWhere('migration', 'like', '%checkin%')
        ->delete();
    if ($deletedMigrations > 0) {
        echo "✓ 迁移记录: {$deletedMigrations} 条\n";
        $totalCleaned += $deletedMigrations;
    }
    
    echo str_repeat("-", 40) . "\n";
    
    if ($totalCleaned > 0) {
        echo "🎉 清理完成！共清理 {$totalCleaned} 项数据\n";
    } else {
        echo "✨ 数据库已经很干净了！\n";
    }
    
    echo "\n现在可以重新安装插件了！\n";

} catch (Exception $e) {
    echo "❌ 清理失败: " . $e->getMessage() . "\n";
    exit(1);
}
