<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$host = '127.0.0.1';
$db   = 'MapNav';
$user = 'root';
$pass = '';

$action = $_GET['action'] ?? 'data';

// ==========================================
// 1. 处理路由请求 (Proxy to Python)
// ==========================================
if ($action === 'route') {
    $from = $_GET['from'] ?? 18;
    $to = $_GET['to'] ?? 47;
    $step_free = $_GET['step_free'] ?? '0';
    $route_type = $_GET['route_type'] ?? '';
    
    $url = "http://127.0.0.1:5000/api/route?from=$from&to=$to&step_free=$step_free&route_type=" . urlencode($route_type);
    
    $options = [
        'http' => [
            'method'  => 'GET',
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);
    $res = @file_get_contents($url, false, $context);
    
    if ($res === false) {
        $error = error_get_last();
        echo json_encode([
            'ok' => false, 
            'error' => "无法连接到 Python 服务。请确保 'python route.py' 正在运行。详细报错: " . ($error['message'] ?? '未知错误')
        ]);
        exit;
    }
    
    // 🚨 核心修复：强转为整型 (int)，防止 "200" !== 200 的 Bug
    if (isset($http_response_header)) {
        preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
        $status = (int)($match[1] ?? 200);
        if ($status !== 200) {
            echo json_encode([
                'ok' => false, 
                'error' => "Python 服务返回错误 (HTTP $status)。原始返回: " . substr($res, 0, 100)
            ]);
            exit;
        }
    }
    
    echo $res;
    exit;
}

// ==========================================
// 1b. 查找最近資源 (Proxy to Python /api/nearest)
// ==========================================
if ($action === 'nearest') {
    $from = $_GET['from'] ?? '';
    $types = $_GET['types'] ?? '';
    $route_type = $_GET['route_type'] ?? '';
    $n = $_GET['n'] ?? '10';

    $url = "http://127.0.0.1:5000/api/nearest?from=" . urlencode($from)
        . "&types=" . urlencode($types)
        . "&route_type=" . urlencode($route_type)
        . "&n=" . urlencode($n);

    $options = [
        'http' => [
            'method'  => 'GET',
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);
    $res = @file_get_contents($url, false, $context);

    if ($res === false) {
        $error = error_get_last();
        echo json_encode([
            'ok' => false,
            'error' => "無法連接到 Python 服務。請確保 'python route.py' 正在運行。詳細報錯: " . ($error['message'] ?? '未知錯誤')
        ]);
        exit;
    }

    if (isset($http_response_header)) {
        preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
        $status = (int)($match[1] ?? 200);
        if ($status !== 200) {
            echo json_encode([
                'ok' => false,
                'error' => "Python 服務返回錯誤 (HTTP $status)。原始返回: " . substr($res, 0, 100)
            ]);
            exit;
        }
    }

    echo $res;
    exit;
}

// ==========================================
// 2. 处理数据请求 (Nodes, Edges)
// ==========================================
if ($action === 'data') {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        echo json_encode(['ok' => false, 'error' => '数据库连接失败: ' . $e->getMessage()]);
        exit;
    }

    try {
        $nodes = $pdo->query("SELECT * FROM node")->fetchAll();
        foreach ($nodes as &$n) {
            $n['id'] = (int)$n['id']; 
            $n['type'] = (int)$n['type'];
            $n['parant'] = (int)$n['parant']; 
            $n['layer'] = (int)$n['layer'];
            $n['posx'] = (float)$n['posx']; 
            $n['posy'] = (float)$n['posy'];
        }
        unset($n);

        $edges = $pdo->query("SELECT * FROM edge WHERE enable = 1")->fetchAll();
        foreach ($edges as &$e) {
            $e['id'] = (int)$e['id']; 
            $e['node1'] = (int)$e['node1'];
            $e['node2'] = (int)$e['node2']; 
            $e['weight'] = (int)$e['weight']; 
            $e['edgetype'] = (int)$e['edgetype'];
        }
        unset($e);

        $floors = $pdo->query("SELECT * FROM node WHERE type = 2")->fetchAll();
        foreach ($floors as &$f) { $f['id'] = (int)$f['id']; }
        unset($f);
        
        echo json_encode([
            'nodes' => $nodes, 
            'edges' => $edges, 
            'floors' => $floors
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
    } catch (\PDOException $e) {
        echo json_encode(['ok' => false, 'error' => '数据库查询失败: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => '未知的 action 参数']);