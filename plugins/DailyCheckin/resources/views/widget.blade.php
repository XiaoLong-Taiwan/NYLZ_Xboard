{{-- 每日签到小部件 --}}
<div class="daily-checkin-widget bg-white rounded-lg shadow-md p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-800">每日签到</h3>
        <div class="text-sm text-gray-500" id="checkin-date">
            {{ date('Y-m-d') }}
        </div>
    </div>

    <div id="checkin-content">
        <div class="text-center py-4">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
            <p class="text-gray-500 mt-2">加载中...</p>
        </div>
    </div>
</div>

<style>
.daily-checkin-widget {
    min-height: 200px;
}

.checkin-button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transition: all 0.3s ease;
}

.checkin-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.checkin-button:disabled {
    background: #e5e7eb;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.reward-item {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
}

.continuous-badge {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadCheckinStatus();
});

async function loadCheckinStatus() {
    try {
        const response = await fetch('/api/v1/plugin/daily-checkin/status', {
            headers: {
                'Authorization': 'Bearer ' + getAuthToken(),
                'Content-Type': 'application/json'
            }
        });

        const result = await response.json();
        
        if (result.success) {
            renderCheckinWidget(result.data);
        } else {
            showError(result.message);
        }
    } catch (error) {
        showError('加载签到状态失败');
    }
}

function renderCheckinWidget(data) {
    const content = document.getElementById('checkin-content');
    
    if (data.today_checked) {
        // 已签到状态
        content.innerHTML = `
            <div class="text-center">
                <div class="text-green-500 text-4xl mb-2">✓</div>
                <p class="text-green-600 font-semibold mb-3">今日已签到</p>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">连续签到:</span>
                        <span class="continuous-badge">${data.continuous_days} 天</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">总签到:</span>
                        <span class="font-semibold">${data.total_checkins} 次</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">最高连续:</span>
                        <span class="font-semibold">${data.max_continuous_days} 天</span>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-3">明天再来签到吧！</p>
            </div>
        `;
    } else if (data.can_checkin) {
        // 可以签到状态
        const nextReward = data.next_reward;
        content.innerHTML = `
            <div class="text-center">
                <button onclick="doCheckin()" class="checkin-button text-white px-6 py-3 rounded-lg font-semibold mb-3 w-full">
                    立即签到
                </button>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">连续签到:</span>
                        <span class="continuous-badge">${data.continuous_days} 天</span>
                    </div>
                    ${nextReward.balance > 0 ? `
                    <div class="reward-item">
                        💰 可获得 ${(nextReward.balance / 100).toFixed(2)} 元
                    </div>
                    ` : ''}
                    ${nextReward.traffic > 0 ? `
                    <div class="reward-item">
                        📶 可获得 ${(nextReward.traffic / 1024 / 1024).toFixed(0)} MB 流量
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    } else {
        // 签到功能关闭
        content.innerHTML = `
            <div class="text-center text-gray-500">
                <div class="text-3xl mb-2">🔒</div>
                <p>签到功能暂时关闭</p>
            </div>
        `;
    }
}

async function doCheckin() {
    const button = document.querySelector('.checkin-button');
    button.disabled = true;
    button.textContent = '签到中...';

    try {
        const response = await fetch('/api/v1/plugin/daily-checkin/checkin', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + getAuthToken(),
                'Content-Type': 'application/json'
            }
        });

        const result = await response.json();
        
        if (result.success) {
            showSuccess('签到成功！');
            loadCheckinStatus(); // 重新加载状态
        } else {
            showError(result.message);
            button.disabled = false;
            button.textContent = '立即签到';
        }
    } catch (error) {
        showError('签到失败，请重试');
        button.disabled = false;
        button.textContent = '立即签到';
    }
}

function getAuthToken() {
    // 这里需要根据实际的认证方式获取token
    return localStorage.getItem('auth_token') || '';
}

function showSuccess(message) {
    // 显示成功消息的实现
    alert(message);
}

function showError(message) {
    // 显示错误消息的实现
    alert(message);
}
</script>
