<?php
// Configurar sessão com segurança
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Alterar para 1 em HTTPS
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 14400); // 4 horas - reduzido para melhor segurança
    
    session_start();
    
    // Regenerar ID da sessão periodicamente para segurança
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutos
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Configurações gerais
define('DB_PATH', dirname(__DIR__) . '/data/techponto.db');
define('TIMEZONE', 'America/Sao_Paulo');
define('MAX_LOGIN_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW', 300); // 5 minutos

// Configurar timezone
date_default_timezone_set(TIMEZONE);

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Headers para API (somente se necessário)
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
}

// Função para resposta JSON padronizada
function jsonResponse($success, $message, $data = null, $httpCode = 200) {
    // Garantir que o content-type seja JSON
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    http_response_code($httpCode);
    
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'csrf_token' => isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : null
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Função para resposta de erro de segurança
function securityErrorResponse($message = 'Acesso negado') {
    jsonResponse(false, $message, null, 403);
}

// Função para verificar autenticação admin
function requireAdmin() {
    // Verificar se sessão está ativa
    if (session_status() !== PHP_SESSION_ACTIVE) {
        securityErrorResponse('Sessão inválida');
    }
    
    // Verificar se usuário está logado
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        securityErrorResponse('Usuário não autenticado');
    }
    
    // Verificar se é admin
    if ($_SESSION['user_type'] !== 'admin') {
        securityErrorResponse('Acesso restrito a administradores');
    }
    
    // Verificar timeout de sessão (4 horas - reduzido para melhor segurança)
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > 14400) {
            session_destroy();
            securityErrorResponse('Sessão expirada');
        }
    }
    
    $_SESSION['last_activity'] = time();
}

// Função para verificar se usuário está ativo
function requireActiveUser() {
    if (!isset($_SESSION['user_id'])) {
        securityErrorResponse('Usuário não autenticado');
    }
}

// Autoload das classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/' . strtolower($class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Handler global para erros não capturados
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'data' => null,
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => $_ENV['APP_DEBUG'] ?? false ? [
            'error' => $message,
            'file' => $file,
            'line' => $line
        ] : null
    ], JSON_UNESCAPED_UNICODE);
    
    exit;
});

// Handler para exceções não capturadas
set_exception_handler(function($exception) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'data' => null,
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => $_ENV['APP_DEBUG'] ?? false ? [
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ] : null
    ], JSON_UNESCAPED_UNICODE);
    
    exit;
});

// Instanciar classes de sistema
require_once 'database.php';
require_once 'security.php';

$database = new Database();
$security = new SecurityManager($database);

// Disponibilizar globalmente
$GLOBALS['db'] = $database;
$GLOBALS['security'] = $security;
?>