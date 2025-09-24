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
    
    // Validar dados obrigatórios
    $pin = $security->sanitizeInput($input['pin'] ?? '');
    
    if (empty($pin)) {
        jsonResponse(false, 'PIN é obrigatório');
    }
    
    // Rate limiting para endpoint de validação
    $ip = $security->getClientIP();
    if (!$security->checkRateLimit($ip, $pin, 'funcionario', 10, 60)) { // 10 tentativas por minuto
        jsonResponse(false, 'Muitas tentativas. Tente novamente em 1 minuto.', null, 429);
    }
    
    // Buscar funcionário pelo PIN
    $stmt = $db->prepare("
        SELECT u.id, u.nome, u.pin, u.ativo, u.pin_reset,
               d.nome as departamento_nome
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        WHERE u.tipo = 'funcionario' AND u.ativo = 1
    ");
    $stmt->execute();
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $funcionario = null;
    foreach ($funcionarios as $func) {
        if ($security->verifyPin($pin, $func['pin'])) {
            $funcionario = $func;
            break;
        }
    }
    
    if (!$funcionario) {
        $security->logLoginAttempt($ip, $pin, 'funcionario', false);
        jsonResponse(false, 'PIN incorreto');
    }
    
    // Verificar se precisa definir PIN
    if ($funcionario['pin_reset']) {
        jsonResponse(false, 'Primeiro acesso. Defina seu PIN de 4 dígitos.', [
            'pin_reset' => true,
            'funcionario_id' => $funcionario['id'],
            'nome' => $funcionario['nome']
        ]);
    }
    
    // Registrar tentativa bem-sucedida
    $security->logLoginAttempt($ip, $pin, 'funcionario', true);
    
    // Retornar dados do funcionário sem tentar registrar ponto
    jsonResponse(true, 'PIN válido', [
        'funcionario' => [
            'id' => $funcionario['id'],
            'nome' => $funcionario['nome'],
            'departamento' => $funcionario['departamento_nome']
        ]
    ]);
    
} catch (Exception $e) {
    $security->logAudit(
        null,
        'ERROR',
        'validar_pin',
        null,
        null,
        ['error' => $e->getMessage()]
    );
    
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}
?>
