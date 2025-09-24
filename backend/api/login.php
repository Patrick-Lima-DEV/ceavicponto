<?php
require_once '../config/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$security = $GLOBALS['security'];
$db = $GLOBALS['db']->getConnection();

try {
    if ($method !== 'POST') {
        jsonResponse(false, 'Método não permitido', null, 405);
    }
    
    $login = $security->sanitizeInput($input['login'] ?? '');
    $senha = $input['senha'] ?? '';
    $ip = $security->getClientIP();
    
    if (empty($login) || empty($senha)) {
        jsonResponse(false, 'Login e senha são obrigatórios');
    }
    
    // Rate limiting para tentativas de login admin
    if (!$security->checkRateLimit($ip, $login, 'admin', 5, 900)) { // 5 tentativas em 15 min
        jsonResponse(false, 'Muitas tentativas de login. Tente novamente em 15 minutos.', null, 429);
    }
    
    // Buscar usuário admin
    $stmt = $db->prepare("
        SELECT id, nome, login, senha, ativo 
        FROM usuarios 
        WHERE login = ? AND tipo = 'admin' AND ativo = 1
    ");
    $stmt->execute([$login]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $sucesso = false;
    
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $sucesso = true;
        
        // Criar sessão do admin
        session_regenerate_id(true);
        $_SESSION['user_id'] = $usuario['id'];
        $_SESSION['user_name'] = $usuario['nome'];
        $_SESSION['user_login'] = $usuario['login'];
        $_SESSION['user_type'] = 'admin';
        $_SESSION['login_time'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        // Log de auditoria
        $security->logAudit(
            $usuario['id'],
            'LOGIN_ADMIN',
            'usuarios',
            $usuario['id'],
            null,
            [
                'login' => $login,
                'ip' => $ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );
        
        jsonResponse(true, 'Login realizado com sucesso', [
            'user' => [
                'id' => $usuario['id'],
                'nome' => $usuario['nome'],
                'login' => $usuario['login'],
                'tipo' => 'admin'
            ],
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    }
    
    // Registrar tentativa de login
    $security->logLoginAttempt($ip, $login, 'admin', $sucesso);
    
    if (!$sucesso) {
        // Aguardar para prevenir ataques de força bruta
        usleep(rand(500000, 1500000)); // 0.5 a 1.5 segundos
        jsonResponse(false, 'Login ou senha incorretos');
    }
    
} catch (Exception $e) {
    $security->logAudit(
        null,
        'ERROR',
        'login',
        null,
        null,
        ['error' => $e->getMessage()]
    );
    
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}
?>