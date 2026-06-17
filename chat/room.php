<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'send_message') {
        $message = sanitize($_POST['message'] ?? '');
        
        if (!empty($message)) {
            $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message) VALUES (?, ?)");
            $stmt->execute([$userId, $message]);
            echo json_encode(['success' => true, 'message' => 'Message sent']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'load_messages') {
        $lastId = isset($_POST['last_id']) ? (int)$_POST['last_id'] : 0;
        
        $stmt = $pdo->prepare("
            SELECT cm.*, u.name as username,
                   COALESCE(p.profile_image, 'default.png') as profile_image
            FROM chat_messages cm
            JOIN users u ON cm.user_id = u.id
            LEFT JOIN profiles p ON cm.user_id = p.user_id
            WHERE cm.id > ?
            ORDER BY cm.created_at ASC
            LIMIT 50
        ");
        $stmt->execute([$lastId]);
        $messages = $stmt->fetchAll();
        
        $stmt = $pdo->query("
            SELECT DISTINCT cm.user_id, u.name as username, MAX(cm.created_at) as last_active
            FROM chat_messages cm
            JOIN users u ON cm.user_id = u.id
            WHERE cm.created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            GROUP BY cm.user_id, u.name
            ORDER BY u.name ASC
        ");
        $onlineUsers = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'messages' => $messages, 'online_users' => $onlineUsers]);
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT cm.*, u.name as username,
           COALESCE(p.profile_image, 'default.png') as profile_image
    FROM chat_messages cm
    JOIN users u ON cm.user_id = u.id
    LEFT JOIN profiles p ON cm.user_id = p.user_id
    ORDER BY cm.created_at DESC
    LIMIT 50
");
$stmt->execute();
$messages = array_reverse($stmt->fetchAll());

$stmt = $pdo->query("
    SELECT DISTINCT cm.user_id, u.name as username, MAX(cm.created_at) as last_active
    FROM chat_messages cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    GROUP BY cm.user_id, u.name
    ORDER BY u.name ASC
");
$onlineUsers = $stmt->fetchAll();

$stmt = $pdo->query("SELECT COUNT(*) as count FROM chat_messages");
$totalMessages = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Chat · Alumni Connect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:          #0a0a12;
            --surface-1:   #0f0f1c;
            --surface-2:   #141428;
            --surface-3:   #1a1a35;
            --border:      rgba(255,255,255,0.06);
            --border-glow: rgba(233,69,96,0.22);

            --accent:      #e94560;
            --accent-2:    #ff6b85;
            --accent-glow: rgba(233,69,96,0.18);

            --sent-from:   #c0303d;
            --sent-to:     #e94560;

            --text-1:  #f0f0ff;
            --text-2:  #a0a0c0;
            --text-3:  #606080;

            --green:   #2ecc8a;
            --green-bg:rgba(46,204,138,0.1);

            --radius-card: 20px;
            --radius-msg:  16px;
            --font: 'Sora', sans-serif;
            --mono: 'JetBrains Mono', monospace;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text-1);
            height: 100vh;
            overflow: hidden;
            /* Subtle noise texture */
            background-image:
                radial-gradient(ellipse 80% 50% at 20% -10%, rgba(233,69,96,0.08), transparent),
                radial-gradient(ellipse 60% 40% at 80% 110%, rgba(86,56,255,0.06), transparent);
        }

        /* ─── LAYOUT ─────────────────────────── */
        .shell {
            height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr;
        }

        /* ─── TOP BAR ─────────────────────────── */
        .topbar {
            background: var(--surface-1);
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            position: relative;
            z-index: 10;
        }

        .topbar::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border-glow), transparent);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-orb {
            width: 42px; height: 42px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--sent-from), var(--accent-2));
            display: grid;
            place-items: center;
            font-size: 18px;
            flex-shrink: 0;
            box-shadow: 0 0 0 1px var(--border-glow),
                        0 8px 24px rgba(233,69,96,0.3);
        }

        .brand-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-1);
            letter-spacing: -0.3px;
        }

        .brand-sub {
            font-size: 0.72rem;
            color: var(--text-3);
            margin-top: 1px;
            font-weight: 400;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .stat-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            border-radius: 999px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            font-size: 0.78rem;
            color: var(--text-2);
            white-space: nowrap;
            transition: border-color .2s;
        }

        .stat-chip:hover { border-color: var(--border-glow); }

        .pulse {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 0 3px var(--green-bg);
            animation: pulse-ring 2s ease-in-out infinite;
        }

        .btn-dash {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 999px;
            background: transparent;
            border: 1px solid var(--border-glow);
            color: var(--accent-2);
            font-family: var(--font);
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background .2s, box-shadow .2s;
        }

        .btn-dash:hover {
            background: var(--accent-glow);
            box-shadow: 0 0 16px rgba(233,69,96,0.15);
        }

        /* ─── BODY ─────────────────────────── */
        .chat-body {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 0;
            overflow: hidden;
        }

        /* ─── SIDEBAR ─────────────────────────── */
        .sidebar {
            background: var(--surface-1);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar-head {
            padding: 18px 18px 12px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-head-label {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--text-3);
        }

        .count-badge {
            background: var(--accent-glow);
            border: 1px solid var(--border-glow);
            color: var(--accent-2);
            font-size: 0.72rem;
            font-weight: 700;
            padding: 2px 9px;
            border-radius: 999px;
        }

        .sidebar-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }

        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: var(--surface-3); border-radius: 4px; }

        .user-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid transparent;
            cursor: pointer;
            margin-bottom: 4px;
            transition: background .18s, border-color .18s, transform .15s;
        }

        .user-card:hover {
            background: var(--surface-2);
            border-color: var(--border);
            transform: translateX(3px);
        }

        .user-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            border: 2px solid var(--surface-3);
        }

        .user-card-info { flex: 1; min-width: 0; }

        .user-card-name {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-1);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-card-status {
            font-size: 0.7rem;
            color: var(--green);
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 2px;
        }

        .user-card-status::before {
            content: '';
            width: 5px; height: 5px;
            border-radius: 50%;
            background: var(--green);
            flex-shrink: 0;
        }

        .no-users {
            text-align: center;
            padding: 28px 12px;
            color: var(--text-3);
            font-size: 0.8rem;
        }

        .no-users i { font-size: 28px; display: block; margin-bottom: 8px; }

        /* ─── MAIN ─────────────────────────── */
        .chat-main {
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 22px 26px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            background: var(--bg);
        }

        .messages-area::-webkit-scrollbar { width: 4px; }
        .messages-area::-webkit-scrollbar-track { background: transparent; }
        .messages-area::-webkit-scrollbar-thumb { background: var(--surface-3); border-radius: 4px; }

        /* ─── DATE DIVIDER ─────────────────────────── */
        .date-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 16px 0 12px;
        }

        .date-divider::before, .date-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .date-divider-label {
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: var(--text-3);
            font-family: var(--mono);
            white-space: nowrap;
        }

        /* ─── MESSAGES ─────────────────────────── */
        .msg-row {
            display: flex;
            align-items: flex-end;
            gap: 9px;
            margin-bottom: 3px;
            animation: msgIn 0.22s cubic-bezier(0.34,1.56,0.64,1) both;
        }

        .msg-row.sent { flex-direction: row-reverse; }

        .msg-avatar {
            width: 30px; height: 30px;
            border-radius: 50%;
            object-fit: cover;
            border: 1.5px solid var(--surface-3);
            flex-shrink: 0;
            margin-bottom: 2px;
        }

        .msg-avatar.invisible { visibility: hidden; }

        .msg-content { max-width: min(68%, 560px); }

        .msg-sender {
            font-size: 0.68rem;
            font-weight: 600;
            color: var(--text-3);
            margin-bottom: 4px;
            padding-left: 4px;
            letter-spacing: 0.2px;
        }

        .msg-row.sent .msg-sender { text-align: right; padding-right: 4px; padding-left: 0; }

        .bubble {
            padding: 10px 14px;
            border-radius: var(--radius-msg);
            font-size: 0.875rem;
            line-height: 1.55;
            word-break: break-word;
            position: relative;
        }

        .msg-row.received .bubble {
            background: var(--surface-2);
            border: 1px solid var(--border);
            color: var(--text-1);
            border-bottom-left-radius: 4px;
        }

        .msg-row.sent .bubble {
            background: linear-gradient(135deg, var(--sent-from), var(--accent));
            color: #fff;
            border-bottom-right-radius: 4px;
            box-shadow: 0 6px 22px rgba(233,69,96,0.25);
        }

        .msg-time {
            font-size: 0.65rem;
            color: var(--text-3);
            margin-top: 5px;
            font-family: var(--mono);
            padding: 0 4px;
        }

        .msg-row.sent .msg-time { text-align: right; }

        /* ─── EMPTY STATE ─────────────────────────── */
        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            color: var(--text-3);
            padding: 40px;
        }

        .empty-orb {
            width: 80px; height: 80px;
            border-radius: 24px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            display: grid;
            place-items: center;
            font-size: 34px;
            color: var(--accent);
        }

        .empty-state h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-2);
        }

        .empty-state p {
            font-size: 0.82rem;
            text-align: center;
            max-width: 240px;
            line-height: 1.6;
        }

        /* ─── TYPING ─────────────────────────── */
        .typing-row {
            display: none;
            align-items: flex-end;
            gap: 9px;
            margin-bottom: 6px;
            padding: 0 26px;
        }

        .typing-bubble {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-msg);
            border-bottom-left-radius: 4px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .typing-dot {
            width: 5px; height: 5px;
            border-radius: 50%;
            background: var(--text-3);
            animation: tdot 1.3s ease-in-out infinite;
        }

        .typing-dot:nth-child(2) { animation-delay: 0.18s; }
        .typing-dot:nth-child(3) { animation-delay: 0.36s; }

        /* ─── INPUT ─────────────────────────── */
        .composer-wrap {
            padding: 14px 20px 16px;
            background: var(--surface-1);
            border-top: 1px solid var(--border);
        }

        .composer {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 16px;
            display: flex;
            align-items: flex-end;
            gap: 10px;
            padding: 10px 10px 10px 16px;
            transition: border-color .2s, box-shadow .2s;
        }

        .composer:focus-within {
            border-color: var(--border-glow);
            box-shadow: 0 0 0 3px rgba(233,69,96,0.07),
                        0 8px 28px rgba(0,0,0,0.3);
        }

        .composer-input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-1);
            font-family: var(--font);
            font-size: 0.88rem;
            resize: none;
            min-height: 22px;
            max-height: 120px;
            line-height: 1.5;
        }

        .composer-input::placeholder { color: var(--text-3); }

        .send-btn {
            width: 40px; height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--sent-from), var(--accent));
            border: none;
            color: #fff;
            font-size: 16px;
            display: grid;
            place-items: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: transform .15s, box-shadow .2s;
            box-shadow: 0 4px 16px rgba(233,69,96,0.3);
        }

        .send-btn:hover { transform: translateY(-2px) scale(1.05); }
        .send-btn:active { transform: scale(0.95); }

        .composer-hint {
            font-size: 0.68rem;
            color: var(--text-3);
            margin-top: 8px;
            padding: 0 6px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: var(--mono);
        }

        .composer-hint kbd {
            font-family: var(--mono);
            background: var(--surface-3);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 1px 5px;
            font-size: 0.65rem;
            color: var(--text-2);
        }

        /* ─── SCROLL-TO-BOTTOM ─────────────────────────── */
        #scrollBtn {
            position: fixed;
            bottom: 100px; right: 28px;
            width: 40px; height: 40px;
            border-radius: 50%;
            background: var(--surface-2);
            border: 1px solid var(--border-glow);
            color: var(--accent-2);
            font-size: 16px;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 999;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            transition: transform .15s;
        }

        #scrollBtn:hover { transform: translateY(-2px); }

        /* ─── ANIMATIONS ─────────────────────────── */
        @keyframes msgIn {
            from { opacity: 0; transform: translateY(10px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes pulse-ring {
            0%, 100% { box-shadow: 0 0 0 3px var(--green-bg); }
            50%       { box-shadow: 0 0 0 6px transparent; }
        }

        @keyframes tdot {
            0%, 100% { transform: translateY(0); opacity: .4; }
            50%       { transform: translateY(-4px); opacity: 1; }
        }

        /* ─── RESPONSIVE ─────────────────────────── */
        @media (max-width: 768px) {
            body { overflow: auto; }

            .chat-body {
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr;
            }

            .sidebar {
                max-height: 130px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .sidebar-scroll { padding: 8px; }

            .user-card { display: inline-flex; width: auto; margin: 0 4px 4px 0; }

            .messages-area { padding: 16px; }

            .msg-content { max-width: 85%; }

            .shell { height: 100svh; }
        }
    </style>
</head>
<body>

<div class="shell">

    <!-- TOP BAR -->
    <header class="topbar">
        <div class="topbar-left">
            <div class="brand-orb">
                <i class="bi bi-chat-dots-fill"></i>
            </div>
            <div>
                <div class="brand-title">Community Chat</div>
                <div class="brand-sub">Alumni Connect · Public Room</div>
            </div>
        </div>
        <div class="topbar-right">
            <div class="stat-chip">
                <span class="pulse"></span>
                <span><strong id="onlineCount"><?= count($onlineUsers) ?></strong> online</span>
            </div>
            <div class="stat-chip">
                <i class="bi bi-chat-left-text" style="color:var(--accent)"></i>
                <span><?= number_format($totalMessages) ?> msgs</span>
            </div>
            <a href="../dashboard.php" class="btn-dash">
                <i class="bi bi-grid-fill"></i> Dashboard
            </a>
        </div>
    </header>

    <!-- BODY -->
    <div class="chat-body">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-head">
                <span class="sidebar-head-label">Online Now</span>
                <span class="count-badge" id="sidebarOnlineCount"><?= count($onlineUsers) ?></span>
            </div>
            <div class="sidebar-scroll" id="onlineUsersList">
                <?php if (empty($onlineUsers)): ?>
                    <div class="no-users">
                        <i class="bi bi-wifi-off"></i>
                        No one active right now
                    </div>
                <?php else: ?>
                    <?php foreach ($onlineUsers as $user): ?>
                        <div class="user-card" data-user-id="<?= $user['user_id'] ?>">
                            <img src="../uploads/profiles/default.png"
                                 class="user-avatar"
                                 alt="<?= htmlspecialchars($user['username']) ?>"
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['username']) ?>&background=e94560&color=fff'">
                            <div class="user-card-info">
                                <div class="user-card-name"><?= htmlspecialchars($user['username']) ?></div>
                                <div class="user-card-status">Active</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <!-- MESSAGES + INPUT -->
        <main class="chat-main">
            <div class="messages-area" id="chatMessages">

                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <div class="empty-orb"><i class="bi bi-chat-dots"></i></div>
                        <h4>No messages yet</h4>
                        <p>Be the first to break the silence. Say hello to your fellow alumni!</p>
                    </div>

                <?php else: ?>
                    <?php
                    $prevDate = null;
                    $prevUserId = null;
                    foreach ($messages as $i => $msg):
                        $msgDate = date('Y-m-d', strtotime($msg['created_at']));
                        $today = date('Y-m-d');
                        $yesterday = date('Y-m-d', strtotime('-1 day'));

                        if ($msgDate !== $prevDate):
                            $label = $msgDate === $today ? 'Today'
                                   : ($msgDate === $yesterday ? 'Yesterday'
                                   : date('M j, Y', strtotime($msg['created_at'])));
                    ?>
                        <div class="date-divider">
                            <span class="date-divider-label"><?= $label ?></span>
                        </div>
                    <?php
                        endif;
                        $isSent    = $msg['user_id'] == $userId;
                        $showAvatar = !$isSent && ($prevUserId !== $msg['user_id'] || $msgDate !== $prevDate);
                        $showName   = !$isSent && ($prevUserId !== $msg['user_id'] || $msgDate !== $prevDate);
                        $prevDate   = $msgDate;
                        $prevUserId = $msg['user_id'];
                    ?>
                        <div class="msg-row <?= $isSent ? 'sent' : 'received' ?>">
                            <?php if (!$isSent): ?>
                                <img src="../uploads/profiles/<?= $msg['profile_image'] ?>"
                                     class="msg-avatar <?= $showAvatar ? '' : 'invisible' ?>"
                                     alt="<?= htmlspecialchars($msg['username']) ?>"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($msg['username']) ?>&background=e94560&color=fff'">
                            <?php endif; ?>
                            <div class="msg-content">
                                <?php if ($showName): ?>
                                    <div class="msg-sender"><?= htmlspecialchars($msg['username']) ?></div>
                                <?php endif; ?>
                                <div class="bubble"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                <div class="msg-time"><?= date('g:i A', strtotime($msg['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- TYPING -->
            <div class="typing-row" id="typingRow">
                <div class="typing-bubble">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>

            <!-- COMPOSER -->
            <div class="composer-wrap">
                <div class="composer">
                    <textarea class="composer-input" id="messageInput"
                              placeholder="Message everyone…"
                              rows="1" autocomplete="off"></textarea>
                    <button class="send-btn" id="sendBtn" title="Send">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
                
            </div>
        </main>
    </div>
</div>

<!-- SCROLL TO BOTTOM -->
<button id="scrollBtn" onclick="scrollToBottom()" aria-label="Scroll to bottom">
    <i class="bi bi-chevron-down"></i>
</button>

<script>
    let lastMessageId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;
    let currentUserId = <?= $userId ?>;
    let isTyping = false, typingTimeout;

    // ── Auto-resize textarea ──
    const input = document.getElementById('messageInput');
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    });

    // ── Scroll helpers ──
    const messagesDiv = document.getElementById('chatMessages');

    function scrollToBottom() {
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    function isAtBottom() {
        return messagesDiv.scrollHeight - messagesDiv.scrollTop <= messagesDiv.clientHeight + 60;
    }

    function checkScrollBtn() {
        const btn = document.getElementById('scrollBtn');
        btn.style.display = (messagesDiv.scrollHeight - messagesDiv.scrollTop > messagesDiv.clientHeight + 100)
            ? 'flex' : 'none';
    }

    messagesDiv.addEventListener('scroll', checkScrollBtn);

    // ── Escape HTML ──
    function esc(t) {
        const d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }

    // ── Render a new message row ──
    function renderMessage(msg) {
        const sent = msg.user_id == currentUserId;
        const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const avatar = sent ? '' : `
            <img src="../uploads/profiles/${msg.profile_image || 'default.png'}"
                 class="msg-avatar"
                 alt="${esc(msg.username)}"
                 onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(msg.username)}&background=e94560&color=fff'">`;
        const senderLabel = sent ? '' : `<div class="msg-sender">${esc(msg.username)}</div>`;

        const row = document.createElement('div');
        row.className = `msg-row ${sent ? 'sent' : 'received'}`;
        row.innerHTML = `
            ${avatar}
            <div class="msg-content">
                ${senderLabel}
                <div class="bubble">${esc(msg.message).replace(/\n/g, '<br>')}</div>
                <div class="msg-time">${time}</div>
            </div>`;
        return row;
    }

    // ── Load new messages ──
    async function loadMessages() {
        try {
            const fd = new FormData();
            fd.append('action', 'load_messages');
            fd.append('last_id', lastMessageId);
            const res  = await fetch('room.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success) return;

            if (data.messages?.length) {
                const wasAtBottom = isAtBottom();

                // Remove empty state if present
                const empty = messagesDiv.querySelector('.empty-state');
                if (empty) empty.remove();

                data.messages.forEach(msg => {
                    messagesDiv.appendChild(renderMessage(msg));
                    if (msg.id > lastMessageId) lastMessageId = msg.id;
                });

                if (wasAtBottom) scrollToBottom();
                checkScrollBtn();
            }

            if (data.online_users) updateOnlineUsers(data.online_users);
        } catch (e) { console.error(e); }
    }

    // ── Update online users sidebar ──
    function updateOnlineUsers(users) {
        document.getElementById('onlineCount').textContent = users.length;
        document.getElementById('sidebarOnlineCount').textContent = users.length;
        const list = document.getElementById('onlineUsersList');

        if (!users.length) {
            list.innerHTML = `<div class="no-users"><i class="bi bi-wifi-off"></i>No one active right now</div>`;
            return;
        }

        list.innerHTML = users.map(u => `
            <div class="user-card" data-user-id="${u.user_id}">
                <img src="../uploads/profiles/default.png" class="user-avatar"
                     alt="${esc(u.username)}"
                     onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(u.username)}&background=e94560&color=fff'">
                <div class="user-card-info">
                    <div class="user-card-name">${esc(u.username)}</div>
                    <div class="user-card-status">Active</div>
                </div>
            </div>`).join('');
    }

    // ── Send ──
    async function sendMessage() {
        const msg = input.value.trim();
        if (!msg) return;

        const btn = document.getElementById('sendBtn');
        btn.disabled = true;

        try {
            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('message', msg);
            const res  = await fetch('room.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                input.value = '';
                input.style.height = 'auto';
                await loadMessages();
                scrollToBottom();
            }
        } catch (e) { console.error(e); }
        finally {
            btn.disabled = false;
        }
    }

    // ── Typing indicator (local UX only) ──
    function handleTyping() {
        if (!isTyping) {
            isTyping = true;
            document.getElementById('typingRow').style.display = 'flex';
        }
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            isTyping = false;
            document.getElementById('typingRow').style.display = 'none';
        }, 1200);
    }

    // ── Event listeners ──
    document.getElementById('sendBtn').addEventListener('click', sendMessage);

    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    input.addEventListener('input', handleTyping);

    // ── Poll & init ──
    setInterval(loadMessages, 2500);
    setTimeout(scrollToBottom, 300);
</script>
</body>
</html>