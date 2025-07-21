<?php

/**
 * 测试插件配置是否正确
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

// 初始化Laravel应用
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\Plugin\PluginManager;
use App\Services\Plugin\PluginConfigService;

echo "测试每日签到插件配置...\n";
echo str_repeat("=", 50) . "\n";

try {
    $pluginManager = app(PluginManager::class);
    $configService = app(PluginConfigService::class);
    
    // 1. 测试配置文件读取
    echo "1. 测试配置文件读取...\n";
    $configFile = __DIR__ . '/config.json';
    if (!file_exists($configFile)) {
        throw new Exception("配置文件不存在: {$configFile}");
    }
    
    $config = json_decode(file_get_contents($configFile), true);
    if (!$config) {
        throw new Exception("配置文件格式错误");
    }
    
    echo "   ✓ 配置文件读取成功\n";
    echo "   插件名称: {$config['name']}\n";
    echo "   插件代码: {$config['code']}\n";
    echo "   版本: {$config['version']}\n";
    echo "   配置项数量: " . count($config['configs']) . "\n";
    
    // 2. 测试配置项格式
    echo "\n2. 测试配置项格式...\n";
    foreach ($config['configs'] as $index => $item) {
        $required = ['label', 'field_name', 'field_type', 'default_value'];
        foreach ($required as $field) {
            if (!isset($item[$field])) {
                throw new Exception("配置项 {$index} 缺少必需字段: {$field}");
            }
        }
        echo "   ✓ 配置项 '{$item['field_name']}' 格式正确\n";
    }
    
    // 3. 测试默认值提取
    echo "\n3. 测试默认值提取...\n";
    $reflection = new ReflectionClass($pluginManager);
    $method = $reflection->getMethod('extractDefaultConfig');
    $method->setAccessible(true);
    
    $defaultValues = $method->invoke($pluginManager, $config);
    echo "   提取的默认值:\n";
    foreach ($defaultValues as $key => $value) {
        echo "   - {$key}: {$value}\n";
    }
    
    // 4. 测试配置验证
    echo "\n4. 测试配置验证...\n";
    $validateMethod = $reflection->getMethod('validateConfig');
    $validateMethod->setAccessible(true);
    
    $isValid = $validateMethod->invoke($pluginManager, $config);
    if ($isValid) {
        echo "   ✓ 配置验证通过\n";
    } else {
        throw new Exception("配置验证失败");
    }
    
    // 5. 测试插件路径
    echo "\n5. 测试插件路径...\n";
    $pluginPath = $pluginManager->getPluginPath('daily_checkin');
    echo "   插件路径: {$pluginPath}\n";
    
    if (file_exists($pluginPath . '/Plugin.php')) {
        echo "   ✓ 主类文件存在\n";
    } else {
        echo "   ⚠ 主类文件不存在\n";
    }
    
    // 6. 测试数据库迁移文件
    echo "\n6. 测试数据库迁移文件...\n";
    $migrationsPath = $pluginPath . '/database/migrations';
    if (is_dir($migrationsPath)) {
        $migrations = glob($migrationsPath . '/*.php');
        echo "   找到 " . count($migrations) . " 个迁移文件:\n";
        foreach ($migrations as $migration) {
            echo "   - " . basename($migration) . "\n";
        }
    } else {
        echo "   ⚠ 迁移目录不存在\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ 所有测试通过！插件配置正确。\n";
    echo "\n下一步:\n";
    echo "1. 运行安装脚本: php install.php install\n";
    echo "2. 或者在管理后台手动安装插件\n";
    
} catch (Exception $e) {
    echo "\n❌ 测试失败: " . $e->getMessage() . "\n";
    echo "请检查配置文件和插件结构。\n";
    exit(1);
}
