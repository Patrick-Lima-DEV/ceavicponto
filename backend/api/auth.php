<?php
require_once '../config/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$security = $GLOBALS['security'];
$db = $GLOBALS['db']->getConnection();

try {
    switch ($method) {
        case 'POST':
            handlePost($input);
            break;
            
        case 'GET':
            handleGet();
            break;
            
        case 'DELETE':
            handleLogout();
            break;
            
        default:
            jsonResponse(false, 'Método não permitido', null, 405);
    }
    
} catch (Exception $e) {
    $security->logAudit(
        null,
        'ERROR',
        'auth',
        null,
        null,
        ['error' => $e->getMessage()]
    );
    
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}

function handlePost($input) {
    global $security, $db;
    
    $action = $input['action'] ?? 'login';
    
    switch ($action) {
        case 'login_admin':
            loginAdmin($input, $security, $db);
            break;
            
        case 'login_funcionario':
            loginFuncionario($input, $security, $db);
            break;
            
        default:
            loginAdmin($input, $security, $db);
    }
}

function loginAdmin($input, $security, $db) {
    $login = $security->sanitizeInput($input['login'] ?? '');
    $senha = $input['senha'] ?? '';
    $ip = $security->getClientIP();
    
    if (empty($login) || empty($senha)) {
        jsonResponse(false, 'Login e senha são obrigatórios');
    }
    
    // Rate limiting para tentativas de login
    if (!$security->checkRateLimit($ip, $login, 'admin', 5, 900)) { // 5 tentativas em 15 min
        jsonResponse(false, 'Muitas tentativas de login. Tente novamente em 15 minutos.', null, 429);
    }
    
    // Buscar usuário admin
    $stmt = $db->prepare("
        SELECT id, nome, login, senha, ativo 
        FROM usuarios 
        WHERE login = ? AND tipo = 'admin'
    ");
    $stmt->execute([$login]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $sucesso = false;
    
    if ($usuario && $usuario['ativo'] && password_verify($senha, $usuario['senha'])) {
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
            'usuario' => [
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
}

function loginFuncionario($input, $security, $db) {
    $identificador = $security->sanitizeInput($input['identificador'] ?? '');
    $pin = $security->sanitizeInput($input['pin'] ?? '');
    $ip = $security->getClientIP();
    
    if (empty($identificador) || empty($pin)) {
        jsonResponse(false, 'CPF/Matrícula e PIN são obrigatórios');
    }
    
    // Rate limiting mais restritivo para funcionários
    if (!$security->checkRateLimit($ip, $identificador, 'funcionario', 3, 300)) { // 3 tentativas em 5 min
        jsonResponse(false, 'Muitas tentativas. Tente novamente em 5 minutos.', null, 429);
    }
    
    // Buscar funcionário
    $stmt = $db->prepare("
        SELECT u.id, u.nome, u.cpf, u.matricula, u.pin, u.pin_reset, u.ativo,
               d.nome as departamento_nome
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        WHERE (u.cpf = ? OR u.matricula = ?) AND u.tipo = 'funcionario'
    ");
    $stmt->execute([$identificador, $identificador]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $sucesso = false;
    
    if ($funcionario && $funcionario['ativo']) {
        if ($funcionario['pin_reset']) {
            // Funcionário precisa definir novo PIN
            jsonResponse(false, 'Primeiro acesso. Defina seu PIN de 4 dígitos.', [
                'pin_reset' => true,
                'funcionario_id' => $funcionario['id'],
                'nome' => $funcionario['nome']
            ]);
        }
        
        if ($funcionario['pin'] && $security->verifyPin($pin, $funcionario['pin'])) {
            $sucesso = true;
            
            // Criar sessão do funcionário
            session_regenerate_id(true);
            $_SESSION['funcionario_id'] = $funcionario['id'];
            $_SESSION['funcionario_nome'] = $funcionario['nome'];
            $_SESSION['funcionario_cpf'] = $funcionario['cpf'];
            $_SESSION['funcionario_matricula'] = $funcionario['matricula'];
            $_SESSION['funcionario_departamento'] = $funcionario['departamento_nome'];
            $_SESSION['funcionario_auth_time'] = time();
            
            // Log de auditoria
            $security->logAudit(
                $funcionario['id'],
                'LOGIN_FUNCIONARIO',
                'usuarios',
                $funcionario['id'],
                null,
                [
                    'identificador' => $identificador,
                    'ip' => $ip,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            );
            
            jsonResponse(true, 'Login realizado com sucesso', [
                'funcionario' => [
                    'id' => $funcionario['id'],
                    'nome' => $funcionario['nome'],
                    'departamento' => $funcionario['departamento_nome']
                ]
            ]);
        }
    }
    
    // Registrar tentativa
    $security->logLoginAttempt($ip, $identificador, 'funcionario', $sucesso);
    
    if (!$sucesso) {
        // Aguardar para prevenir ataques de força bruta
        usleep(rand(1000000, 2000000)); // 1 a 2 segundos
        jsonResponse(false, 'CPF/Matrícula ou PIN incorretos');
    }
}

function handleGet() {
    global $security;
    
    $action = $_GET['action'] ?? 'status';
    
    switch ($action) {
        case 'status':
            verificarStatusAuth();
            break;
            
        case 'csrf_token':
            gerarCSRFToken();
            break;
            
        default:
            jsonResponse(false, 'Ação não encontrada');
    }
}

function verificarStatusAuth() {
    // Verificar se há sessão ativa de admin
    if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'admin') {
        // Verificar se sessão não expirou (4 horas - reduzido para melhor segurança)
        $tempoExpiracao = 4 * 3600; // 4 horas em segundos
        if ((time() - $_SESSION['login_time']) < $tempoExpiracao) {
            jsonResponse(true, 'Sessão admin ativa', [
                'tipo' => 'admin',
                'usuario' => [
                    'id' => $_SESSION['user_id'],
                    'nome' => $_SESSION['user_name'],
                    'login' => $_SESSION['user_login']
                ],
                'tempo_restante' => $tempoExpiracao - (time() - $_SESSION['login_time'])
            ]);
        } else {
            // Sessão expirada
            session_destroy();
            jsonResponse(false, 'Sessão expirada');
        }
    }
    
    // Verificar se há sessão ativa de funcionário
    elseif (isset($_SESSION['funcionario_id'])) {
        // Verificar se sessão não expirou (1 hora para funcionários)
        $tempoExpiracao = 3600; // 1 hora em segundos
        if ((time() - $_SESSION['funcionario_auth_time']) < $tempoExpiracao) {
            jsonResponse(true, 'Sessão funcionário ativa', [
                'tipo' => 'funcionario',
                'funcionario' => [
                    'id' => $_SESSION['funcionario_id'],
                    'nome' => $_SESSION['funcionario_nome'],
                    'departamento' => $_SESSION['funcionario_departamento']
                ],
                'tempo_restante' => $tempoExpiracao - (time() - $_SESSION['funcionario_auth_time'])
            ]);
        } else {
            // Sessão expirada
            session_destroy();
            jsonResponse(false, 'Sessão expirada');
        }
    }
    
    else {
        jsonResponse(false, 'Nenhuma sessão ativa');
    }
}

function gerarCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    jsonResponse(true, 'Token CSRF gerado', [
        'csrf_token' => $_SESSION['csrf_token']
    ]);
}

function handleLogout() {
    global $security;
    
    $tipoUsuario = 'desconhecido';
    $usuarioId = null;
    
    // Identificar tipo de usuário logado
    if (isset($_SESSION['user_id'])) {
        $tipoUsuario = 'admin';
        $usuarioId = $_SESSION['user_id'];
    } elseif (isset($_SESSION['funcionario_id'])) {
        $tipoUsuario = 'funcionario';
        $usuarioId = $_SESSION['funcionario_id'];
    }
    
    // Log de auditoria do logout
    if ($usuarioId) {
        $security->logAudit(
            $usuarioId,
            'LOGOUT',
            'usuarios',
            $usuarioId,
            null,
            [
                'tipo_usuario' => $tipoUsuario,
                'ip' => $security->getClientIP()
            ]
        );
    }
    
    // Destruir sessão
    session_destroy();
    
    jsonResponse(true, 'Logout realizado com sucesso');
}
?>