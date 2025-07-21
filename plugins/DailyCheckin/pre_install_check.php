<?php

/**
 * 安装前检查脚本 - 确保所有兼容性问题都已解决
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// 初始化Laravel应用
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "每日签到插件 - 安装前检查\n";
echo str_repeat("=", 60) . "\n";

$allChecksPass = true;

try {
    // 1. 检查必需的表是否存在
    echo "1. 检查必需的表...\n";
    
    $requiredTables = ['v2_user', 'v2_plugins'];
    foreach ($requiredTables as $table) {
        if (Schema::hasTable($table)) {
            echo "   ✓ {$table} 表存在\n";
        } else {
            echo "   ❌ {$table} 表不存在\n";
            $allChecksPass = false;
        }
    }
    
    // 2. 检查用户表结构
    echo "\n2. 检查用户表结构...\n";
    
    $userColumns = DB::select("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v2_user' AND COLUMN_NAME IN ('id', 'balance', 'transfer_enable')");
    
    $columnTypes = [];
    foreach ($userColumns as $column) {
        $columnTypes[$column->COLUMN_NAME] = $column->COLUMN_TYPE;
        echo "   v2_user.{$column->COLUMN_NAME}: {$column->COLUMN_TYPE}\n";
    }
    
    // 验证关键字段类型
    if (isset($columnTypes['id']) && strpos($columnTypes['id'], 'int') !== false) {
        echo "   ✓ user_id 兼容性: OK\n";
    } else {
        echo "   ❌ user_id 兼容性: 类型不匹配\n";
        $allChecksPass = false;
    }
    
    if (isset($columnTypes['balance']) && strpos($columnTypes['balance'], 'int') !== false) {
        echo "   ✓ balance 兼容性: OK\n";
    } else {
        echo "   ❌ balance 兼容性: 类型不匹配\n";
        $allChecksPass = false;
    }
    
    if (isset($columnTypes['transfer_enable']) && strpos($columnTypes['transfer_enable'], 'bigint') !== false) {
        echo "   ✓ transfer_enable 兼容性: OK\n";
    } else {
        echo "   ⚠ transfer_enable 兼容性: 类型可能不匹配\n";
    }
    
    // 3. 检查时间戳格式
    echo "\n3. 检查时间戳格式...\n";
    
    $timestampColumns = DB::select("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v2_user' AND COLUMN_NAME IN ('created_at', 'updated_at')");
    
    $useUnixTimestamp = true;
    foreach ($timestampColumns as $column) {
        echo "   v2_user.{$column->COLUMN_NAME}: {$column->COLUMN_TYPE}\n";
        if (strpos(strtolower($column->COLUMN_TYPE), 'timestamp') !== false) {
            $useUnixTimestamp = false;
        }
    }
    
    if ($useUnixTimestamp) {
        echo "   ✓ 时间戳格式: Unix时间戳 (兼容)\n";
    } else {
        echo "   ⚠ 时间戳格式: MySQL timestamp (已适配)\n";
    }
    
    // 4. 检查插件表结构
    echo "\n4. 检查插件表结构...\n";
    
    $pluginColumns = DB::select("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v2_plugins'");
    
    $hasRequiredColumns = ['code' => false, 'config' => false, 'is_enabled' => false];
    foreach ($pluginColumns as $column) {
        if (isset($hasRequiredColumns[$column->COLUMN_NAME])) {
            $hasRequiredColumns[$column->COLUMN_NAME] = true;
            echo "   ✓ {$column->COLUMN_NAME}: {$column->COLUMN_TYPE}\n";
        }
    }
    
    foreach ($hasRequiredColumns as $column => $exists) {
        if (!$exists) {
            echo "   ❌ 缺少必需字段: {$column}\n";
            $allChecksPass = false;
        }
    }
    
    // 5. 检查是否已安装
    echo "\n5. 检查插件安装状态...\n";
    
    $existingPlugin = DB::table('v2_plugins')->where('code', 'daily_checkin')->first();
    if ($existingPlugin) {
        echo "   ⚠ 插件已安装 (版本: {$existingPlugin->version})\n";
        echo "   如需重新安装，请先运行: php cleanup.php\n";
    } else {
        echo "   ✓ 插件未安装，可以进行安装\n";
    }
    
    // 6. 检查迁移文件
    echo "\n6. 检查迁移文件...\n";
    
    $migrationFiles = [
        '2024_01_01_000001_create_daily_checkins_table.php',
        '2024_01_01_000002_create_checkin_stats_table.php'
    ];
    
    foreach ($migrationFiles as $file) {
        $path = __DIR__ . '/database/migrations/' . $file;
        if (file_exists($path)) {
            echo "   ✓ {$file}\n";
        } else {
            echo "   ❌ 缺少迁移文件: {$file}\n";
            $allChecksPass = false;
        }
    }
    
    // 7. 检查配置文件
    echo "\n7. 检查配置文件...\n";
    
    $configFile = __DIR__ . '/config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if ($config && isset($config['code']) && isset($config['configs'])) {
            echo "   ✓ config.json 格式正确\n";
            echo "   插件代码: {$config['code']}\n";
            echo "   配置项数量: " . count($config['configs']) . "\n";
        } else {
            echo "   ❌ config.json 格式错误\n";
            $allChecksPass = false;
        }
    } else {
        echo "   ❌ 缺少配置文件: config.json\n";
        $allChecksPass = false;
    }
    
    // 8. 最终结果
    echo "\n" . str_repeat("=", 60) . "\n";
    
    if ($allChecksPass) {
        echo "✅ 所有检查通过！可以安全安装插件。\n";
        echo "\n下一步:\n";
        echo "php install.php install\n";
        return 0;
    } else {
        echo "❌ 发现兼容性问题，请解决后再安装。\n";
        echo "\n如需帮助，请查看:\n";
        echo "- README.md\n";
        echo "- INSTALL.md\n";
        return 1;
    }

} catch (Exception $e) {
    echo "\n❌ 检查失败: " . $e->getMessage() . "\n";
    return 1;
}
