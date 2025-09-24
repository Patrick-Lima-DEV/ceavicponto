<?php
require_once '../config/config.php';

requireAdmin(); // Apenas admins podem resetar PIN

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$security = $GLOBALS['security'];
$db = $GLOBALS['db']->getConnection();

try {
    if ($method !== 'POST') {
        jsonResponse(false, 'Método não permitido', null, 405);
    }
    
    $usuario_id = (int) ($input['usuario_id'] ?? 0);
    
    if (!$usuario_id) {
        jsonResponse(false, 'ID do usuário é obrigatório');
    }
    
    // Verificar se usuário existe e é funcionário
    $stmt = $db->prepare("
        SELECT id, nome, tipo, ativo 
        FROM usuarios 
        WHERE id = ? AND tipo = 'funcionario'
    ");
    $stmt->execute([$usuario_id]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$funcionario) {
        jsonResponse(false, 'Funcionário não encontrado');
    }
    
    if (!$funcionario['ativo']) {
        jsonResponse(false, 'Funcionário está inativo');
    }
    
    // Reset do PIN - definir como NULL e marcar pin_reset = 1
    $stmt = $db->prepare("
        UPDATE usuarios 
        SET pin = NULL, pin_reset = 1, updated_at = datetime('now')
        WHERE id = ?
    ");
    
    if ($stmt->execute([$usuario_id])) {
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'RESET_PIN',
            'usuarios',
            $usuario_id,
            null,
            [
                'funcionario' => $funcionario['nome'],
                'acao' => 'PIN resetado pelo admin'
            ]
        );
        
        jsonResponse(true, 'PIN resetado com sucesso. Funcionário deve definir novo PIN no próximo acesso.', [
            'funcionario' => [
                'id' => $funcionario['id'],
                'nome' => $funcionario['nome']
            ]
        ]);
    } else {
        jsonResponse(false, 'Erro ao resetar PIN');
    }
    
} catch (Exception $e) {
    $security->logAudit(
        $_SESSION['user_id'] ?? null,
        'ERROR',
        'reset_pin',
        null,
        null,
        ['error' => $e->getMessage()]
    );
    
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}
?>
