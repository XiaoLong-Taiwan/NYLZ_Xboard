<?php

/**
 * 验证数据库兼容性脚本
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// 初始化Laravel应用
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "验证数据库兼容性...\n";
echo str_repeat("=", 50) . "\n";

try {
    // 1. 检查 v2_user 表结构
    echo "1. 检查 v2_user 表结构...\n";
    
    if (!Schema::hasTable('v2_user')) {
        throw new Exception("v2_user 表不存在！请确保 Xboard 已正确安装。");
    }
    
    // 获取 v2_user 表的 id 字段信息
    $columns = DB::select("SHOW COLUMNS FROM v2_user WHERE Field = 'id'");
    if (empty($columns)) {
        throw new Exception("v2_user 表没有 id 字段！");
    }
    
    $idColumn = $columns[0];
    echo "   v2_user.id 字段类型: {$idColumn->Type}\n";
    
    // 检查是否为 int 类型
    if (strpos(strtolower($idColumn->Type), 'int') === false) {
        throw new Exception("v2_user.id 字段类型不是整数类型！");
    }
    
    // 判断是否为 unsigned
    $isUnsigned = strpos(strtolower($idColumn->Type), 'unsigned') !== false;
    echo "   是否为 unsigned: " . ($isUnsigned ? "是" : "否") . "\n";
    
    // 判断大小
    if (strpos(strtolower($idColumn->Type), 'bigint') !== false) {
        $userIdType = 'unsignedBigInteger';
        echo "   推荐使用: unsignedBigInteger\n";
    } else {
        $userIdType = 'unsignedInteger';
        echo "   推荐使用: unsignedInteger\n";
    }
    
    // 2. 检查迁移文件
    echo "\n2. 检查迁移文件...\n";
    
    $migrationFile = __DIR__ . '/database/migrations/2024_01_01_000001_create_daily_checkins_table.php';
    if (!file_exists($migrationFile)) {
        throw new Exception("迁移文件不存在！");
    }
    
    $migrationContent = file_get_contents($migrationFile);
    
    if (strpos($migrationContent, 'unsignedInteger(\'user_id\')') !== false) {
        echo "   ✓ 迁移文件使用 unsignedInteger\n";
        if ($userIdType === 'unsignedInteger') {
            echo "   ✓ 数据类型兼容\n";
        } else {
            echo "   ⚠ 数据类型可能不兼容，但通常可以工作\n";
        }
    } elseif (strpos($migrationContent, 'unsignedBigInteger(\'user_id\')') !== false) {
        echo "   ✓ 迁移文件使用 unsignedBigInteger\n";
        if ($userIdType === 'unsignedBigInteger') {
            echo "   ✓ 数据类型兼容\n";
        } else {
            echo "   ❌ 数据类型不兼容！需要修改为 unsignedInteger\n";
        }
    } else {
        echo "   ❌ 无法确定迁移文件中的数据类型\n";
    }
    
    // 3. 检查其他相关表
    echo "\n3. 检查其他相关表...\n";
    
    $relatedTables = ['v2_order', 'v2_commission_log', 'v2_stat_user'];
    foreach ($relatedTables as $tableName) {
        if (Schema::hasTable($tableName)) {
            $columns = DB::select("SHOW COLUMNS FROM {$tableName} WHERE Field = 'user_id'");
            if (!empty($columns)) {
                $column = $columns[0];
                echo "   {$tableName}.user_id: {$column->Type}\n";
            }
        }
    }
    
    // 4. 检查时间戳兼容性
    echo "\n4. 检查时间戳兼容性...\n";

    $timestampTables = ['v2_user', 'v2_order', 'v2_commission_log'];
    $useUnixTimestamp = true;

    foreach ($timestampTables as $tableName) {
        if (Schema::hasTable($tableName)) {
            $columns = DB::select("SHOW COLUMNS FROM {$tableName} WHERE Field = 'created_at'");
            if (!empty($columns)) {
                $column = $columns[0];
                echo "   {$tableName}.created_at: {$column->Type}\n";
                if (strpos(strtolower($column->Type), 'timestamp') !== false) {
                    $useUnixTimestamp = false;
                }
            }
        }
    }

    echo "   推荐时间戳类型: " . ($useUnixTimestamp ? "integer (Unix时间戳)" : "timestamp") . "\n";

    // 5. 检查流量字段兼容性
    echo "\n5. 检查流量字段兼容性...\n";

    $trafficColumns = DB::select("SHOW COLUMNS FROM v2_user WHERE Field IN ('transfer_enable', 'u', 'd')");
    foreach ($trafficColumns as $column) {
        echo "   v2_user.{$column->Field}: {$column->Type}\n";
    }

    // 6. 生成建议
    echo "\n6. 兼容性总结...\n";

    if ($userIdType === 'unsignedInteger' && $useUnixTimestamp) {
        echo "   ✅ 完全兼容，可以直接安装\n";
    } else {
        echo "   ⚠ 部分兼容性问题：\n";
        if ($userIdType !== 'unsignedInteger') {
            echo "     - user_id 字段类型需要调整\n";
        }
        if (!$useUnixTimestamp) {
            echo "     - 时间戳类型需要调整\n";
        }
    }

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ 兼容性检查完成！\n";

} catch (Exception $e) {
    echo "\n❌ 检查失败: " . $e->getMessage() . "\n";
    exit(1);
}
