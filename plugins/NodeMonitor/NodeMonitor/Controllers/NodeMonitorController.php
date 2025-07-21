<?php

namespace Plugin\NodeMonitor\Controllers;

use App\Http\Controllers\PluginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NodeMonitorController extends PluginController
{
    /**
     * 手动检查所有节点状态
     */
    public function checkNodes(Request $request)
    {
        // 检查插件状态
        if ($error = $this->beforePluginAction()) {
            return $error[1];
        }

        try {
            $nodes = $this->parseNodes();
            if (empty($nodes)) {
                return $this->fail([400, '未配置监控节点']);
            }

            $results = [];
            foreach ($nodes as $node) {
                $status = $this->checkSingleNode($node);
                $results[] = [
                    'name' => $node['name'],
                    'host' => $node['host'],
                    'port' => $node['port'],
                    'status' => $status ? 'online' : 'offline',
                    'checked_at' => date('Y-m-d H:i:s')
                ];
            }

            return $this->success([
                'message' => '节点状态检查完成',
                'nodes' => $results,
                'total' => count($results),
                'online' => count(array_filter($results, fn($r) => $r['status'] === 'online')),
                'offline' => count(array_filter($results, fn($r) => $r['status'] === 'offline'))
            ]);
        } catch (\Exception $e) {
            Log::error('手动检查节点状态失败', ['error' => $e->getMessage()]);
            return $this->fail([500, '检查失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 获取节点状态历史
     */
    public function getNodesStatus(Request $request)
    {
        // 检查插件状态
        if ($error = $this->beforePluginAction()) {
            return $error[1];
        }

        try {
            $nodes = $this->parseNodes();
            if (empty($nodes)) {
                return $this->success([
                    'message' => '未配置监控节点',
                    'nodes' => []
                ]);
            }

            $results = [];
            foreach ($nodes as $node) {
                $cacheKey = "node_status_{$node['name']}";
                $status = Cache::get($cacheKey, null);
                $lastCheckKey = "node_last_check_{$node['name']}";
                $lastCheck = Cache::get($lastCheckKey, null);
                
                $results[] = [
                    'name' => $node['name'],
                    'host' => $node['host'],
                    'port' => $node['port'],
                    'status' => $status === null ? 'unknown' : ($status ? 'online' : 'offline'),
                    'last_check' => $lastCheck ? date('Y-m-d H:i:s', $lastCheck) : null
                ];
            }

            return $this->success([
                'nodes' => $results,
                'last_global_check' => Cache::get('node_monitor_last_check', null) ? 
                    date('Y-m-d H:i:s', Cache::get('node_monitor_last_check')) : null
            ]);
        } catch (\Exception $e) {
            Log::error('获取节点状态失败', ['error' => $e->getMessage()]);
            return $this->fail([500, '获取状态失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 发送测试通知
     */
    public function sendTestNotification(Request $request)
    {
        // 检查插件状态
        if ($error = $this->beforePluginAction()) {
            return $error[1];
        }

        try {
            $botToken = $this->getConfig('bot_token');
            $chatIds = $this->getConfig('chat_id');
            
            if (empty($botToken) || empty($chatIds)) {
                return $this->fail([400, 'Telegram配置不完整，请检查Bot Token和Chat ID']);
            }

            $message = "🧪 节点监控测试通知\n\n这是一条测试消息，用于验证Telegram通知功能是否正常工作。\n\n时间: " . date('Y-m-d H:i:s');
            
            $chatIdList = explode(',', $chatIds);
            $successCount = 0;
            $errors = [];
            
            foreach ($chatIdList as $chatId) {
                $chatId = trim($chatId);
                if (empty($chatId)) continue;
                
                $result = $this->sendTelegramMessage($botToken, $chatId, $message);
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errors[] = "Chat ID {$chatId}: {$result['error']}";
                }
            }

            if ($successCount > 0) {
                return $this->success([
                    'message' => "测试通知发送完成，成功发送到 {$successCount} 个聊天",
                    'success_count' => $successCount,
                    'errors' => $errors
                ]);
            } else {
                return $this->fail([500, '所有通知发送失败: ' . implode('; ', $errors)]);
            }
        } catch (\Exception $e) {
            Log::error('发送测试通知失败', ['error' => $e->getMessage()]);
            return $this->fail([500, '发送测试通知失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 清除节点状态缓存
     */
    public function clearCache(Request $request)
    {
        // 检查插件状态
        if ($error = $this->beforePluginAction()) {
            return $error[1];
        }

        try {
            $nodes = $this->parseNodes();
            $clearedCount = 0;
            
            foreach ($nodes as $node) {
                $cacheKey = "node_status_{$node['name']}";
                $lastCheckKey = "node_last_check_{$node['name']}";
                
                if (Cache::forget($cacheKey)) {
                    $clearedCount++;
                }
                Cache::forget($lastCheckKey);
            }
            
            Cache::forget('node_monitor_last_check');

            return $this->success([
                'message' => "已清除 {$clearedCount} 个节点的状态缓存",
                'cleared_count' => $clearedCount
            ]);
        } catch (\Exception $e) {
            Log::error('清除缓存失败', ['error' => $e->getMessage()]);
            return $this->fail([500, '清除缓存失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 检查单个节点状态
     */
    private function checkSingleNode(array $node): bool
    {
        $timeout = $this->getConfig('timeout', 10);
        $retryCount = $this->getConfig('retry_count', 3);
        
        for ($i = 0; $i < $retryCount; $i++) {
            if ($this->pingNode($node['host'], $node['port'], $timeout)) {
                return true;
            }
            if ($i < $retryCount - 1) {
                sleep(1); // 重试间隔1秒
            }
        }
        
        return false;
    }

    /**
     * 检测节点连通性
     */
    private function pingNode(string $host, int $port, int $timeout): bool
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
    private function parseNodes(): array
    {
        // 直接使用Plugin类的方法，避免代码重复
        $plugin = new \Plugin\NodeMonitor\Plugin();
        return $plugin->parseNodes();
    }

    /**
     * 发送Telegram消息
     */
    private function sendTelegramMessage(string $botToken, string $chatId, string $message): array
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
                return ['success' => false, 'error' => '网络请求失败'];
            }
            
            $response = json_decode($result, true);
            if (!$response || !$response['ok']) {
                return ['success' => false, 'error' => $response['description'] ?? '未知错误'];
            }
            
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}