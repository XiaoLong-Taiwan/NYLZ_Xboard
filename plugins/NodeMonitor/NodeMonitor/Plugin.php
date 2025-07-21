<?php

namespace Plugin\NodeMonitor;

use App\Services\Plugin\AbstractPlugin;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class Plugin extends AbstractPlugin
{
    /**
     * 插件启动时调用
     */
    public function boot(): void
    {
        // 注册前端配置钩子
        $this->filter('guest_comm_config', function ($config) {
            $config['node_monitor_enable'] = true;
            $config['node_monitor_interval'] = $this->getConfig('check_interval', 60);
            return $config;
        });

        // 注册定时任务
        $this->registerScheduledTasks();
    }

    /**
     * 注册定时任务
     */
    private function registerScheduledTasks(): void
    {
        $interval = $this->getConfig('check_interval', 60);
        
        // 注册定时检查任务
        $plugin = $this;
        app(Schedule::class)->call(function () use ($plugin) {
            $plugin->checkNodesStatus();
        })->everyMinute()->when(function () use ($plugin) {
            // 根据配置的间隔时间决定是否执行
            $lastCheck = cache('node_monitor_last_check', 0);
            $interval = $plugin->getConfig('check_interval', 60);
            return (time() - $lastCheck) >= $interval;
        });
    }

    /**
     * 检查节点状态
     */
    public function checkNodesStatus(): void
    {
        try {
            $nodes = $this->parseNodes();
            if (empty($nodes)) {
                return;
            }

            foreach ($nodes as $node) {
                $this->checkSingleNode($node);
            }

            // 更新最后检查时间
            cache(['node_monitor_last_check' => time()], now()->addHours(24));
        } catch (\Exception $e) {
            Log::error('节点监控检查失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 检查单个节点状态
     */
    public function checkSingleNode(array $node): bool
    {
        $name = $node['name'];
        $host = $node['host'];
        $port = $node['port'];
        $timeout = $this->getConfig('timeout', 10);
        $retryCount = $this->getConfig('retry_count', 3);
        
        $isOnline = false;
        
        // 重试机制
        for ($i = 0; $i < $retryCount; $i++) {
            if ($this->pingNode($host, $port, $timeout)) {
                $isOnline = true;
                break;
            }
            sleep(1); // 重试间隔1秒
        }
        
        $cacheKey = "node_status_{$name}";
        $lastStatus = cache($cacheKey, null);
        
        // 状态发生变化时发送通知
        if ($lastStatus !== null && $lastStatus !== $isOnline) {
            if (!$isOnline) {
                $this->sendNotification("🔴 节点离线警告\n\n节点名称: {$name}\n节点地址: {$host}:{$port}\n状态: 离线\n时间: " . date('Y-m-d H:i:s'));
            } elseif ($this->getConfig('enable_recovery_notify', true)) {
                $this->sendNotification("🟢 节点恢复通知\n\n节点名称: {$name}\n节点地址: {$host}:{$port}\n状态: 在线\n时间: " . date('Y-m-d H:i:s'));
            }
        }
        
        // 更新节点状态缓存
        cache([$cacheKey => $isOnline], now()->addHours(24));
        
        return $isOnline;
    }

    /**
     * 检测节点连通性
     */
    public function pingNode(string $host, int $port, int $timeout): bool
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($socket) {
            fclose($socket);
            return true;
        }
        return false;
    }

    /**
     * 解析节点配置
     */
    public function parseNodes(): array
    {
        $nodesConfig = $this->getConfig('nodes', '');
        
        // 如果有手动配置的节点，优先使用
        if (!empty($nodesConfig)) {
            $nodes = [];
            $nodeList = explode(',', $nodesConfig);
            
            foreach ($nodeList as $nodeStr) {
                $parts = explode(':', trim($nodeStr));
                if (count($parts) >= 3) {
                    $nodes[] = [
                        'name' => trim($parts[0]),
                        'host' => trim($parts[1]),
                        'port' => (int)trim($parts[2])
                    ];
                }
            }
            
            return $nodes;
        }
        
        // 如果启用自动发现且没有手动配置节点，返回默认监控节点
        $autoDiscover = $this->getConfig('auto_discover', false);
        if ($autoDiscover) {
            return $this->getAutoDiscoveredNodes();
        }
        
        return [];
    }
    
    /**
     * 获取自动发现的节点列表
     */
    private function getAutoDiscoveredNodes(): array
    {
        $defaultNodes = [
            [
                'name' => '本地Web服务',
                'host' => 'localhost',
                'port' => 80
            ],
            [
                'name' => '本地HTTPS服务',
                'host' => 'localhost', 
                'port' => 443
            ],
            [
                'name' => '本地MySQL',
                'host' => 'localhost',
                'port' => 3306
            ],
            [
                'name' => '本地Redis',
                'host' => 'localhost',
                'port' => 6379
            ]
        ];
        
        // 尝试从环境变量或配置中获取更多节点信息
        $additionalNodes = $this->discoverSystemNodes();
        
        return array_merge($defaultNodes, $additionalNodes);
    }
    
    /**
     * 发现系统节点
     */
    private function discoverSystemNodes(): array
    {
        $nodes = [];
        
        // 尝试从Laravel配置中获取数据库信息
        try {
            $dbHost = config('database.connections.mysql.host', 'localhost');
            $dbPort = config('database.connections.mysql.port', 3306);
            
            if ($dbHost !== 'localhost' && $dbHost !== '127.0.0.1') {
                $nodes[] = [
                    'name' => '系统数据库',
                    'host' => $dbHost,
                    'port' => (int)$dbPort
                ];
            }
        } catch (\Exception $e) {
            Log::debug('无法获取数据库配置信息', ['error' => $e->getMessage()]);
        }
        
        // 尝试从Redis配置中获取信息
        try {
            $redisHost = config('database.redis.default.host', 'localhost');
            $redisPort = config('database.redis.default.port', 6379);
            
            if ($redisHost !== 'localhost' && $redisHost !== '127.0.0.1') {
                $nodes[] = [
                    'name' => '系统Redis',
                    'host' => $redisHost,
                    'port' => (int)$redisPort
                ];
            }
        } catch (\Exception $e) {
            Log::debug('无法获取Redis配置信息', ['error' => $e->getMessage()]);
        }
        
        return $nodes;
    }

    /**
     * 发送Telegram通知
     */
    private function sendNotification(string $message): void
    {
        $botToken = $this->getConfig('bot_token', '');
        $chatIds = $this->getConfig('chat_id', '');
        
        if (empty($botToken) || empty($chatIds)) {
            Log::warning('节点监控通知配置不完整', ['bot_token' => !empty($botToken), 'chat_id' => !empty($chatIds)]);
            return;
        }
        
        $chatIdList = explode(',', $chatIds);
        
        foreach ($chatIdList as $chatId) {
            $chatId = trim($chatId);
            if (empty($chatId)) continue;
            
            $this->sendTelegramMessage($botToken, $chatId, $message);
        }
    }

    /**
     * 发送Telegram消息
     */
    public function sendTelegramMessage(string $botToken, string $chatId, string $message): bool
    {
        try {
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $data = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ];
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => http_build_query($data),
                    'timeout' => 10
                ]
            ]);
            
            $result = file_get_contents($url, false, $context);
            
            if ($result === false) {
                Log::error('发送Telegram消息失败', ['chat_id' => $chatId]);
                return false;
            } else {
                Log::info('Telegram消息发送成功', ['chat_id' => $chatId]);
                $response = json_decode($result, true);
                return $response && $response['ok'];
            }
        } catch (\Exception $e) {
            Log::error('发送Telegram消息异常', ['error' => $e->getMessage(), 'chat_id' => $chatId]);
            return false;
        }
    }
}