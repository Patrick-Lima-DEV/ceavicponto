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
    
    $pin = $security->sanitizeInput($input['pin'] ?? '');
    $confirmar_pin = $security->sanitizeInput($input['confirmar_pin'] ?? '');
    
    if (empty($pin) || empty($confirmar_pin)) {
        jsonResponse(false, 'PIN e confirmação são obrigatórios');
    }
    
    if ($pin !== $confirmar_pin) {
        jsonResponse(false, 'PINs não coincidem');
    }
    
    // Validar PIN
    $validacao = $security->validatePin($pin);
    if (!$validacao['valid']) {
        jsonResponse(false, $validacao['message']);
    }
    
    // Buscar funcionário que precisa definir PIN
    $stmt = $db->prepare("
        SELECT id, nome FROM usuarios 
        WHERE tipo = 'funcionario' AND pin_reset = 1 AND ativo = 1
    ");
    $stmt->execute();
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($funcionarios)) {
        jsonResponse(false, 'Nenhum funcionário precisa definir PIN');
    }
    
    // Por enquanto, usar o primeiro funcionário que precisa definir PIN
    // Em um sistema real, você poderia usar uma sessão ou token
    $funcionario = $funcionarios[0];
    
    // Hash do PIN
    $pinHash = $security->hashPin($pin);
    
    // Atualizar PIN
    $stmt = $db->prepare("
        UPDATE usuarios 
        SET pin = ?, pin_reset = 0, updated_at = datetime('now')
        WHERE id = ?
    ");
    
    if ($stmt->execute([$pinHash, $funcionario['id']])) {
        // Log de auditoria
        $security->logAudit(
            $funcionario['id'],
            'DEFINIR_PIN',
            'usuarios',
            $funcionario['id'],
            null,
            ['acao' => 'PIN definido pelo funcionário']
        );
        
        jsonResponse(true, 'PIN definido com sucesso');
    } else {
        jsonResponse(false, 'Erro ao definir PIN');
    }
    
} catch (Exception $e) {
    $security->logAudit(
        null,
        'ERROR',
        'definir_pin',
        null,
        null,
        ['error' => $e->getMessage()]
    );
    
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}
?>
