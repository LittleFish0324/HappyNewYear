<?php
// 数据库连接配置
$servername = "localhost";
$username = "happynewyear";
$password = "happynewyear0324";
$dbname = "happynewyear";

// 计算中国新年（春节）日期的函数
function getChineseNewYear($year) {
    // 以下是简化的春节日期表，实际应该使用农历计算库
    // 这里提供了2024-2030年的春节日期作为示例
    $springFestivalDates = [
        2024 => '2024-02-10',
        2025 => '2025-01-29',
        2026 => '2026-02-17',
        2027 => '2027-02-06',
        2028 => '2028-01-26',
        2029 => '2029-02-13',
        2030 => '2030-02-03'
    ];
    
    // 如果在已知范围内，直接返回
    if (isset($springFestivalDates[$year])) {
        return $springFestivalDates[$year];
    }
    
    // 对于未知年份，这里提供一个简单的估算方法
    // 实际应用中应使用专业的农历计算库
    $date = new DateTime("$year-02-05");
    return $date->format('Y-m-d');
}

// 创建数据库连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 获取下一个新年的年份
$currentYear = date("Y");
$nextYear = $currentYear + 1;

// 获取中国新年（春节）日期
$chineseNewYearDate = getChineseNewYear($nextYear);
$chineseNewYear = new DateTime($chineseNewYearDate);
$chineseNewYearYear = $chineseNewYear->format('Y');
$chineseNewYearMonth = $chineseNewYear->format('m');
$chineseNewYearDay = $chineseNewYear->format('d');

// 获取留言总数
$messageCount = 0;
$countResult = $conn->query("SELECT COUNT(*) AS count FROM message");
if ($countResult && $row = $countResult->fetch_assoc()) {
    $messageCount = $row['count'];
}

// 处理留言提交
$messageSubmitted = false;
$errorMsg = "";

// 敏感词过滤函数
define('SENSITIVE_WORDS', [
    // 敏感词列表 - 这里仅作为示例，可以根据实际需求扩展
    '不良词语1', '不良词语2', '不良词语3', '违规内容', '违法信息',
    // 政治敏感词等其他需要过滤的内容
]);

// 检查是否包含URL函数 - 完全修复正则表达式问题
function containsUrl($text) {
    // 先检查Email地址并临时替换，避免将Email误判为URL
    $emailPattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
    $tempText = preg_replace($emailPattern, '[EMAIL]', $text);
    
    // 使用更简单、更可靠的URL检测方法
    // 检查是否包含http://、https://或www.
    if (strpos($tempText, 'http://') !== false || 
        strpos($tempText, 'https://') !== false || 
        strpos($tempText, 'www.') !== false) {
        
        // 为了避免误报，进一步验证这些模式是否符合基本URL格式
        $urlPatterns = [
            // 使用安全的分隔符和适当转义
            '#\bhttps?://[a-z0-9]([\w\-]+\.)+[a-z]{2,}(/\S*)?#i',
            '#\bwww\.[a-z0-9]([\w\-]+\.)+[a-z]{2,}(/\S*)?#i',
        ];
        
        foreach ($urlPatterns as $pattern) {
            if (preg_match($pattern, $tempText) !== 0) {
                return true;
            }
        }
    }
    
    return false;
}

// 敏感词过滤函数
function filterSensitiveWords($text) {
    $filteredText = $text;
    foreach (SENSITIVE_WORDS as $word) {
        // 使用普通strlen函数替代mb_strlen，避免扩展依赖问题
        $replacement = str_repeat('*', strlen($word));
        $filteredText = str_ireplace($word, $replacement, $filteredText);
    }
    return $filteredText;
}

// 表单数据清理函数
function sanitizeInput($input) {
    // 去除多余的空白字符
    $input = trim($input);
    // 去除反斜杠
    $input = stripslashes($input);
    // HTML特殊字符转义
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_message"])) {
    // 清理输入数据
    $nickname = sanitizeInput($_POST["nickname"]);
    $content = sanitizeInput($_POST["content"]);
    
    // 验证昵称
    if (empty($nickname)) {
        $errorMsg = "请输入昵称";
    } elseif (strlen($nickname) > 50) {
        $errorMsg = "昵称长度不能超过50字";
    }
    // 验证内容
    elseif (empty($content)) {
        $errorMsg = "请输入祝福语";
    } elseif (strlen($content) > 200) {
        $errorMsg = "祝福语长度不能超过200字";
    }
    // 检查是否包含URL - 再次确认内容没有被修改
    elseif (containsUrl($content)) {
        $errorMsg = "留言内容不能包含链接";
    }
    else {
        // 过滤敏感词
        $filteredContent = filterSensitiveWords($content);
        $filteredNickname = filterSensitiveWords($nickname);
        
        // 准备SQL语句并绑定参数（防止SQL注入）
        $stmt = $conn->prepare("INSERT INTO message (nickname, content) VALUES (?, ?)");
        if ($stmt === false) {
            $errorMsg = "数据库错误: " . $conn->error;
        } else {
            // 绑定参数时使用参数化查询，避免SQL注入
            $stmt->bind_param("ss", $filteredNickname, $filteredContent);
            
            if ($stmt->execute()) {
                $messageSubmitted = true;
                
                // 初始化会话并设置提交标志
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['message_submitted'] = true;
                
                // 使用PRG模式防止表单重复提交
                header('Location: index.php?submitted=success');
                exit;
            } else {
                $errorMsg = "留言提交失败，请稍后再试";
            }
            
            $stmt->close();
        }
    }
}

// 获取所有留言
$messages = [];

// 使用预处理语句获取留言，增加安全性
$stmt = $conn->prepare("SELECT nickname, content, DATE_FORMAT(create_time, '%Y-%m-%d %H:%i:%s') AS formatted_time FROM message ORDER BY create_time DESC");
if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // 再次过滤敏感词，确保即使数据库中的数据也经过过滤
            $row['nickname'] = filterSensitiveWords($row['nickname']);
            $row['content'] = filterSensitiveWords($row['content']);
            $messages[] = $row;
        }
        $result->close();
    }
    $stmt->close();
}

// 随机选择3条留言
if (count($messages) > 3) {
    shuffle($messages);
    $messages = array_slice($messages, 0, 3);
    // 重新按时间排序，保持一致性
    usort($messages, function($a, $b) {
        return strtotime($b['formatted_time']) - strtotime($a['formatted_time']);
    });
}

// 初始化会话以存储提交状态
// 这是一种比HTTP_REFERER更可靠的方法
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查是否是重定向回来的成功提交
$messageSubmitted = false;

// 如果URL中有submitted=success参数，并且会话中也有提交标志
if (isset($_GET['submitted']) && $_GET['submitted'] === 'success' && isset($_SESSION['message_submitted']) && $_SESSION['message_submitted'] === true) {
    $messageSubmitted = true;
    // 清除会话中的提交标志，防止刷新页面再次显示成功提示
    unset($_SESSION['message_submitted']);
}

// 关闭数据库连接
$conn->close();
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $nextYear; ?>年新年倒计时</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <!-- 自定义Tailwind配置 -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#E63946', // 新年主题红色
                        secondary: '#FFD700', // 金色
                        accent: '#A8DADC', // 浅蓝色作为点缀
                        dark: '#1D3557', // 深蓝色
                        light: '#F1FAEE' // 浅色系背景
                    },
                    fontFamily: {
                        sans: ['Noto Sans SC', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .text-shadow {
                text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            }
            .text-shadow-lg {
                text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.5);
            }
            .countdown-digit {
                @apply bg-primary text-white text-4xl md:text-6xl lg:text-7xl font-bold rounded-lg p-4 md:p-6 shadow-lg transition-all duration-300 ease-in-out;
            }
            .countdown-label {
                @apply text-dark text-sm md:text-base font-medium mt-2;
            }
            .firework {
                position: absolute;
                pointer-events: none;
            }
            .snowflake {
                position: absolute;
                background-color: white;
                border-radius: 50%;
                animation: snowfall linear infinite;
            }
            .lantern {
                position: absolute;
                width: 60px;
                height: 80px;
                background: linear-gradient(to bottom, #e63946, #c1121f);
                border-radius: 50% 50% 20% 20%;
                box-shadow: 0 0 20px rgba(230, 57, 70, 0.8);
                animation: lanternSwing 5s ease-in-out infinite;
            }
            .lantern::before {
                content: '';
                position: absolute;
                top: -10px;
                left: 50%;
                transform: translateX(-50%);
                width: 40px;
                height: 20px;
                background: linear-gradient(to bottom, #ffd700, #ffed4e);
                border-radius: 50%;
            }
            .lantern::after {
                content: '';
                position: absolute;
                bottom: -5px;
                left: 50%;
                transform: translateX(-50%);
                width: 30px;
                height: 10px;
                background: linear-gradient(to bottom, #ffd700, #ffed4e);
                border-radius: 50%;
            }
        }

        @keyframes snowfall {
            0% {
                transform: translateY(-10%);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh);
                opacity: 0;
            }
        }

        @keyframes lanternSwing {
            0%, 100% {
                transform: rotate(-5deg);
            }
            50% {
                transform: rotate(5deg);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes scale {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .float-animation {
            animation: float 3s ease-in-out infinite;
        }

        .scale-animation {
            animation: scale 2s ease-in-out infinite;
        }

        body {
            background-image: url('newyear_background.svg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        .countdown-container {
            perspective: 1000px;
        }

        .countdown-digit:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .message-card {
            transition: all 0.3s ease-in-out;
        }

        .message-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        /* 响应式调整 */
        @media (max-width: 768px) {
            .countdown-digit {
                font-size: 2.5rem;
                padding: 1rem;
            }
            .lantern {
                width: 40px;
                height: 60px;
            }
        }
    </style>
</head>
<body class="font-sans text-dark">
    <!-- 灯笼装饰元素 -->
    <div class="lantern" style="top: 10%; left: 5%;"></div>
    <div class="lantern" style="top: 15%; left: 90%; animation-delay: 1s;"></div>
    <div class="lantern" style="top: 60%; left: 8%; animation-delay: 2s;"></div>
    <div class="lantern" style="top: 70%; left: 92%; animation-delay: 3s;"></div>

    <!-- 烟花效果容器 -->
    <div id="fireworks-container" class="fixed inset-0 pointer-events-none z-10"></div>

    <!-- 雪花效果容器 -->
    <div id="snow-container" class="fixed inset-0 pointer-events-none z-5"></div>

    <!-- 页面标题区域 -->
    <header class="py-8 text-center relative z-20">
        <h1 class="text-[clamp(2rem,5vw,3.5rem)] font-bold text-primary text-shadow-lg mb-4 float-animation">
            <?php echo $nextYear; ?>年新年倒计时
        </h1>
        <p class="text-[clamp(1rem,2vw,1.25rem)] text-dark max-w-2xl mx-auto">
            时光荏苒，转眼间又到了辞旧迎新的时刻。让我们一起倒数，迎接充满希望的新一年！
        </p>
        <div class="mt-4 flex justify-center items-center gap-2 text-dark/70">
            <i class="fa fa-comments-o text-primary"></i>
            <span>已有 <span id="message-count" class="font-bold text-primary"><?php echo $messageCount; ?></span> 条新年祝福</span>
        </div>
    </header>

    <!-- 主要内容区域 -->
    <main class="container mx-auto px-4 py-8 relative z-20">
        <!-- 倒计时核心区域 -->
        <section class="mb-16 countdown-container">
            <div class="bg-white/80 backdrop-blur-md rounded-2xl shadow-xl p-6 md:p-10 max-w-5xl mx-auto">
                <div class="flex flex-wrap justify-center gap-4 md:gap-8">
                    <div class="text-center">
                        <div id="days" class="countdown-digit">00</div>
                        <div class="countdown-label">天</div>
                    </div>
                    <div class="text-center">
                        <div id="hours" class="countdown-digit">00</div>
                        <div class="countdown-label">时</div>
                    </div>
                    <div class="text-center">
                        <div id="minutes" class="countdown-digit">00</div>
                        <div class="countdown-label">分</div>
                    </div>
                    <div class="text-center">
                        <div id="seconds" class="countdown-digit">00</div>
                        <div class="countdown-label">秒</div>
                    </div>
                </div>
                <div class="text-center mt-8 text-dark/70 italic">
                    距离<?php echo $nextYear; ?>年1月1日 00:00:00 还有
                </div>
            </div>
        </section>

        <!-- 中国新年（春节）倒计时区域 -->
        <section class="mb-16 countdown-container">
            <div class="bg-white/80 backdrop-blur-md rounded-2xl shadow-xl p-6 md:p-10 max-w-5xl mx-auto">
                <div class="flex flex-wrap justify-center gap-4 md:gap-8">
                    <div class="text-center">
                        <div id="days-cn" class="countdown-digit">00</div>
                        <div class="countdown-label">天</div>
                    </div>
                    <div class="text-center">
                        <div id="hours-cn" class="countdown-digit">00</div>
                        <div class="countdown-label">时</div>
                    </div>
                    <div class="text-center">
                        <div id="minutes-cn" class="countdown-digit">00</div>
                        <div class="countdown-label">分</div>
                    </div>
                    <div class="text-center">
                        <div id="seconds-cn" class="countdown-digit">00</div>
                        <div class="countdown-label">秒</div>
                    </div>
                </div>
                <div class="text-center mt-8 text-dark/70 italic">
                    距离<?php echo $chineseNewYearYear; ?>年春节（<?php echo $chineseNewYearMonth; ?>月<?php echo $chineseNewYearDay; ?>日）00:00:00 还有
                </div>
            </div>
        </section>

        <!-- 留言区域 -->
        <section class="max-w-5xl mx-auto">
            <div class="grid md:grid-cols-2 gap-8">
                <!-- 留言表单 -->
                <div class="bg-white/90 backdrop-blur-md rounded-2xl shadow-xl p-6 h-fit">
                    <h2 class="text-2xl font-bold text-primary mb-6 flex items-center">
                        <i class="fa fa-pencil-square-o mr-2"></i>留下您的新年祝福
                    </h2>
                    
                    <?php if ($messageSubmitted): ?>
                        <div class="mb-4 p-3 bg-green-100 text-green-800 rounded-lg shadow-inner animate-bounce">
                            <i class="fa fa-check-circle mr-2"></i>留言提交成功！
                        </div>
                        <script>
                            // 清除表单数据的脚本，确保在重定向后输入框是空的
                            document.addEventListener('DOMContentLoaded', function() {
                                document.getElementById('nickname').value = '';
                                document.getElementById('content').value = '';
                                document.getElementById('content-length').textContent = '0';
                            });
                        </script>
                    <?php elseif (!empty($errorMsg)): ?>
                        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded-lg shadow-inner">
                            <i class="fa fa-exclamation-circle mr-2"></i><?php echo $errorMsg; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form id="message-form" method="POST" action="">
                        <div class="mb-4">
                            <label for="nickname" class="block text-sm font-medium text-dark/80 mb-1">昵称</label>
                            <input type="text" id="nickname" name="nickname" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all" 
                                placeholder="请输入您的昵称" 
                                value="<?php echo isset($_POST['nickname']) ? htmlspecialchars($_POST['nickname']) : ''; ?>">
                        </div>
                        <div class="mb-6">
                            <label for="content" class="block text-sm font-medium text-dark/80 mb-1">祝福语</label>
                            <textarea id="content" name="content" rows="4" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all resize-none" 
                                placeholder="请输入您的新年祝福语（最多200字）"><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                            <p class="text-xs text-dark/60 mt-1 text-right"><span id="content-length">0</span>/200</p>
                        </div>
                        <button type="submit" name="submit_message" 
                            class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-3 px-6 rounded-lg transition-all shadow-lg hover:shadow-xl hover:scale-105 active:scale-95 flex justify-center items-center gap-2">
                            <i class="fa fa-paper-plane"></i>提交祝福
                        </button>
                    </form>
                </div>

                <!-- 留言列表 -->
                <div class="bg-white/90 backdrop-blur-md rounded-2xl shadow-xl p-6">
                    <h2 class="text-2xl font-bold text-primary mb-6 flex items-center">
                        <i class="fa fa-comments mr-2"></i>新年祝福墙
                    </h2>
                    
                    <div id="messages-container" class="space-y-4 max-h-[600px] overflow-y-auto pr-2">
                        <?php if (empty($messages)): ?>
                            <div class="text-center py-8 text-dark/60">
                                <i class="fa fa-comment-o text-4xl mb-2"></i>
                                <p>还没有留言，来做第一个送上祝福的人吧！</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="message-card bg-light/80 p-4 rounded-xl shadow">
                                    <div class="flex justify-between items-center mb-2">
                                        <h3 class="font-bold text-dark flex items-center">
                                            <i class="fa fa-user-o text-primary mr-2"></i><?php echo htmlspecialchars($message['nickname']); ?>
                                        </h3>
                                        <span class="text-xs text-dark/60"><?php echo $message['formatted_time']; ?></span>
                                    </div>
                                    <p class="text-dark/80 leading-relaxed"><?php echo nl2br(htmlspecialchars($message['content'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- 页脚 -->
    <footer class="mt-16 py-6 text-center text-dark/60 border-t border-gray-200 relative z-20">
        <p>© <?php echo date("Y"); ?> 新年倒计时网站 | 数据实时更新，祝福永不缺席</p>
    </footer>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 计算下一个新年的时间
            const currentYear = new Date().getFullYear();
            const nextYear = currentYear + 1;
            const newYearDate = new Date(nextYear, 0, 1, 0, 0, 0);
            
            // 计算中国新年（春节）的时间
            // 这里使用从PHP获取的日期信息，或者使用JavaScript内置逻辑
            const chineseNewYearDate = new Date(<?php echo $chineseNewYearYear; ?>, <?php echo $chineseNewYearMonth - 1; ?>, <?php echo $chineseNewYearDay; ?>, 0, 0, 0);
            
            // 倒计时更新函数
            function updateCountdown() {
                const now = new Date();
                const diff = newYearDate - now;
                
                // 计算天、时、分、秒
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                // 更新DOM
                document.getElementById('days').textContent = days.toString().padStart(2, '0');
                document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
                document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
                document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
                
                // 如果是最后10秒，添加动画效果
                if (days === 0 && hours === 0 && minutes === 0 && seconds < 11) {
                    createFireworks();
                }
            }
            
            // 中国新年倒计时更新函数
            function updateChineseNewYearCountdown() {
                const now = new Date();
                const diff = chineseNewYearDate - now;
                
                // 计算天、时、分、秒
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                // 更新DOM
                if (document.getElementById('days-cn')) {
                    document.getElementById('days-cn').textContent = days.toString().padStart(2, '0');
                    document.getElementById('hours-cn').textContent = hours.toString().padStart(2, '0');
                    document.getElementById('minutes-cn').textContent = minutes.toString().padStart(2, '0');
                    document.getElementById('seconds-cn').textContent = seconds.toString().padStart(2, '0');
                }
                
                // 如果是最后10秒，添加动画效果
                if (days === 0 && hours === 0 && minutes === 0 && seconds < 11) {
                    createFireworks();
                }
            }
            
            // 立即更新一次，然后每秒更新
            updateCountdown();
            updateChineseNewYearCountdown();
            
            // 创建一个统一的定时器来更新两个倒计时
            setInterval(function() {
                updateCountdown();
                updateChineseNewYearCountdown();
            }, 1000);
            
            // 字数统计功能
            const contentTextarea = document.getElementById('content');
            const contentLength = document.getElementById('content-length');
            
            if (contentTextarea && contentLength) {
                // 初始化字数
                contentLength.textContent = contentTextarea.value.length;
                
                // 监听输入事件
                contentTextarea.addEventListener('input', function() {
                    const length = this.value.length;
                    contentLength.textContent = length;
                    
                    // 如果超过200字，添加视觉提示
                    if (length > 200) {
                        contentLength.classList.add('text-red-500');
                    } else {
                        contentLength.classList.remove('text-red-500');
                    }
                });
            }
            
            // 创建烟花效果
            function createFireworks() {
                const container = document.getElementById('fireworks-container');
                if (!container) return;
                
                const fireworksCount = 5;
                
                for (let i = 0; i < fireworksCount; i++) {
                    setTimeout(() => {
                        const firework = document.createElement('div');
                        firework.className = 'firework';
                        
                        // 随机位置
                        const x = Math.random() * 100;
                        const y = Math.random() * 50;
                        
                        // 随机颜色
                        const colors = ['#E63946', '#457B9D', '#1D3557', '#A8DADC', '#FFD700'];
                        const color = colors[Math.floor(Math.random() * colors.length)];
                        
                        // 设置样式
                        firework.style.left = `${x}%`;
                        firework.style.top = `${y}%`;
                        firework.style.width = `${Math.random() * 10 + 5}px`;
                        firework.style.height = `${Math.random() * 10 + 5}px`;
                        firework.style.backgroundColor = color;
                        firework.style.borderRadius = '50%';
                        firework.style.animation = `scale 1s ease-out forwards`;
                        
                        container.appendChild(firework);
                        
                        // 移除烟花元素
                        setTimeout(() => {
                            container.removeChild(firework);
                        }, 1000);
                    }, i * 200);
                }
            }
            
            // 创建雪花效果
            function createSnowflakes() {
                const container = document.getElementById('snow-container');
                if (!container) return;
                
                const snowflakeCount = 50;
                
                for (let i = 0; i < snowflakeCount; i++) {
                    setTimeout(() => {
                        createSnowflake();
                    }, i * 200);
                }
            }
            
            function createSnowflake() {
                const container = document.getElementById('snow-container');
                if (!container) return;
                
                const snowflake = document.createElement('div');
                snowflake.className = 'snowflake';
                
                // 随机属性
                const size = Math.random() * 6 + 2;
                const x = Math.random() * 100;
                const duration = Math.random() * 10 + 5;
                const delay = Math.random() * 5;
                
                // 设置样式
                snowflake.style.width = `${size}px`;
                snowflake.style.height = `${size}px`;
                snowflake.style.left = `${x}%`;
                snowflake.style.animationDuration = `${duration}s`;
                snowflake.style.animationDelay = `${delay}s`;
                snowflake.style.opacity = Math.random() * 0.8 + 0.2;
                
                container.appendChild(snowflake);
                
                // 动画结束后移除
                snowflake.addEventListener('animationend', () => {
                    if (container.contains(snowflake)) {
                        container.removeChild(snowflake);
                    }
                    // 创建新的雪花
                    createSnowflake();
                });
            }
            
            // 启动雪花效果
            createSnowflakes();
            
            // 留言提交成功后的动画效果
            <?php if ($messageSubmitted): ?>
                setTimeout(() => {
                    const successMessage = document.querySelector('.bg-green-100');
                    if (successMessage) {
                        successMessage.classList.add('opacity-0', 'transition-opacity', 'duration-500');
                        setTimeout(() => {
                            successMessage.remove();
                        }, 500);
                    }
                }, 3000);
            <?php endif; ?>
        });
    </script>
</body>
</html>