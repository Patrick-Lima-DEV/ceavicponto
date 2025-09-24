<?php
require_once '../config/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$security = $GLOBALS['security'];

try {
    if ($method !== 'POST') {
        jsonResponse(false, 'Método não permitido', null, 405);
    }
    
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
    
} catch (Exception $e) {
    $security->logAudit(
        null,
        'ERROR',
        'logout',
        null,
        null,
        ['error' => $e->getMessage()]
    );
    
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}
?>