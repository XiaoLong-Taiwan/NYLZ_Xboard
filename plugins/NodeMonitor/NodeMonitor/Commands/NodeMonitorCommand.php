<?php

namespace Plugin\NodeMonitor\Commands;

use Illuminate\Console\Command;
use Plugin\NodeMonitor\Plugin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NodeMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'node-monitor:check {--test-notify : 发送测试通知} {--clear-cache : 清除状态缓存} {--status : 显示节点状态}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '节点监控管理命令';

    /**
     * 插件实例
     *
     * @var Plugin
     */
    protected $plugin;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Plugin $plugin = null)
    {
        parent::__construct();
        $this->plugin = $plugin ?: app(Plugin::class);
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($this->option('test-notify')) {
            return $this->sendTestNotification();
        }

        if ($this->option('clear-cache')) {
            return $this->clearCache();
        }

        if ($this->option('status')) {
            return $this->showStatus();
        }

        // 默认执行节点检查
        return $this->checkNodes();
    }

    /**
     * 检查节点状态
     */
    protected function checkNodes(): int
    {
        $this->info('开始检查节点状态...');
        
        $nodes = $this->plugin->parseNodes();
        if (empty($nodes)) {
            $this->error('未配置监控节点');
            return 1;
        }

        $this->info("共配置 " . count($nodes) . " 个节点");
        
        $results = [];
        foreach ($nodes as $node) {
            $this->line("检查节点: {$node['name']} ({$node['host']}:{$node['port']})");
            
            $status = $this->plugin->checkSingleNode($node);
            $statusText = $status ? '<info>在线</info>' : '<error>离线</error>';
            
            $this->line("  状态: {$statusText}");
            
            $results[] = [
                'name' => $node['name'],
                'host' => $node['host'],
                'port' => $node['port'],
                'status' => $status
            ];
        }

        // 显示汇总
        $online = count(array_filter($results, fn($r) => $r['status']));
        $offline = count($results) - $online;
        
        $this->info("\n检查完成:");
        $this->line("  在线: <info>{$online}</info>");
        $this->line("  离线: <error>{$offline}</error>");
        
        return 0;
    }

    /**
     * 发送测试通知
     */
    protected function sendTestNotification(): int
    {
        $this->info('发送测试通知...');
        
        $botToken = $this->plugin->getConfig('bot_token');
        $chatIds = $this->plugin->getConfig('chat_id');
        
        if (empty($botToken) || empty($chatIds)) {
            $this->error('Telegram配置不完整，请检查Bot Token和Chat ID');
            return 1;
        }

        $message = "🧪 节点监控测试通知\n\n这是一条通过命令行发送的测试消息。\n\n时间: " . date('Y-m-d H:i:s');
        
        $chatIdList = explode(',', $chatIds);
        $successCount = 0;
        
        foreach ($chatIdList as $chatId) {
            $chatId = trim($chatId);
            if (empty($chatId)) continue;
            
            $this->line("发送到 Chat ID: {$chatId}");
            
            if ($this->plugin->sendTelegramMessage($botToken, $chatId, $message)) {
                $this->info("  ✓ 发送成功");
                $successCount++;
            } else {
                $this->error("  ✗ 发送失败");
            }
        }

        if ($successCount > 0) {
            $this->info("\n测试通知发送完成，成功发送到 {$successCount} 个聊天");
            return 0;
        } else {
            $this->error("\n所有通知发送失败");
            return 1;
        }
    }

    /**
     * 清除缓存
     */
    protected function clearCache(): int
    {
        $this->info('清除节点状态缓存...');
        
        $nodes = $this->plugin->parseNodes();
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

        $this->info("已清除 {$clearedCount} 个节点的状态缓存");
        return 0;
    }

    /**
     * 显示节点状态
     */
    protected function showStatus(): int
    {
        $nodes = $this->plugin->parseNodes();
        if (empty($nodes)) {
            $this->error('未配置监控节点');
            return 1;
        }

        $this->info('节点状态概览:');
        
        $headers = ['节点名称', '地址', '端口', '状态', '最后检查'];
        $rows = [];
        
        foreach ($nodes as $node) {
            $cacheKey = "node_status_{$node['name']}";
            $status = Cache::get($cacheKey, null);
            $lastCheckKey = "node_last_check_{$node['name']}";
            $lastCheck = Cache::get($lastCheckKey, null);
            
            $statusText = $status === null ? '未知' : ($status ? '在线' : '离线');
            $lastCheckText = $lastCheck ? date('Y-m-d H:i:s', $lastCheck) : '从未检查';
            
            $rows[] = [
                $node['name'],
                $node['host'],
                $node['port'],
                $statusText,
                $lastCheckText
            ];
        }
        
        $this->table($headers, $rows);
        
        $lastGlobalCheck = Cache::get('node_monitor_last_check', null);
        if ($lastGlobalCheck) {
            $this->info("\n最后全局检查: " . date('Y-m-d H:i:s', $lastGlobalCheck));
        }
        
        return 0;
    }

}