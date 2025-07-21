<?php

/**
 * 清理脚本 - 删除错误的表并重新安装
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// 初始化Laravel应用
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "开始清理每日签到插件数据...\n";
echo str_repeat("=", 50) . "\n";

try {

    // 1. 删除插件记录
    echo "1. 删除插件记录...\n";

    $deleted = DB::table('v2_plugins')->where('code', 'daily_checkin')->delete();
    if ($deleted > 0) {
        echo "   ✓ 删除了 {$deleted} 条插件记录\n";
    } else {
        echo "   - 插件记录不存在\n";
    }

    // 2. 删除数据表
    echo "\n2. 删除数据表...\n";

    DB::statement('DROP TABLE IF EXISTS daily_checkins');
    echo "   ✓ 删除 daily_checkins 表\n";

    DB::statement('DROP TABLE IF EXISTS checkin_stats');
    echo "   ✓ 删除 checkin_stats 表\n";

    // 3. 清理迁移记录
    echo "\n3. 清理迁移记录...\n";

    $deletedMigrations = DB::table('migrations')
        ->where('migration', 'like', '%daily_checkin%')
        ->orWhere('migration', 'like', '%checkin%')
        ->delete();

    if ($deletedMigrations > 0) {
        echo "   ✓ 删除了 {$deletedMigrations} 条迁移记录\n";
    } else {
        echo "   - 没有找到相关迁移记录\n";
    }

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ 清理完成！现在可以重新安装插件了。\n";
    echo "\n下一步:\n";
    echo "php install.php install\n";

} catch (Exception $e) {
    echo "\n❌ 清理失败: " . $e->getMessage() . "\n";
    echo "请手动检查数据库状态。\n";
    exit(1);
}
