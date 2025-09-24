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
    $type = $security->sanitizeInput($input['type'] ?? '');
    
    if (empty($pin) || empty($type)) {
        jsonResponse(false, 'PIN e tipo são obrigatórios');
    }
    
    // Mapear tipos da API para tipos do banco
    $mapeamento_tipos = [
        'entrada' => 'entrada_manha',
        'almoco_saida' => 'saida_almoco', 
        'almoco_volta' => 'volta_almoco',
        'saida' => 'saida_tarde'
    ];
    
    if (!isset($mapeamento_tipos[$type])) {
        jsonResponse(false, 'Tipo de ponto inválido');
    }
    
    $type = $mapeamento_tipos[$type];
    
    // Rate limiting para endpoint de ponto
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
    
    // Verificar se já existe ponto do mesmo tipo no mesmo dia
    $dataAtual = date('Y-m-d');
    
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM pontos 
        WHERE usuario_id = ? AND data = ? AND tipo = ?
    ");
    $stmt->execute([$funcionario['id'], $dataAtual, $type]);
    
    if ($stmt->fetchColumn() > 0) {
        $nomesTipos = [
            'entrada_manha' => 'Entrada',
            'saida_almoco' => 'Saída para Almoço',
            'volta_almoco' => 'Volta do Almoço',
            'saida_tarde' => 'Saída'
        ];
        jsonResponse(false, $nomesTipos[$type] . ' já registrada hoje');
    }
    
    // Verificar sequência lógica básica
    if (!validarSequenciaBasica($funcionario['id'], $type, $dataAtual, $db)) {
        $diaSemana = date('w', strtotime($dataAtual)); // 0 = domingo, 6 = sábado
        
        if ($diaSemana == 0) { // Domingo - folga
            $mensagem = 'Não é possível registrar pontos aos domingos (folga).';
        } elseif ($diaSemana == 6) { // Sábado - meio período
            $mensagem = 'Sequência de pontos inválida. Registre na ordem: Entrada → Saída (sábado é meio período)';
        } else { // Segunda a sexta - período completo
            $mensagem = 'Sequência de pontos inválida. Registre na ordem: Entrada → Saída Almoço → Volta Almoço → Saída';
        }
        
        jsonResponse(false, $mensagem);
    }
    
    // Registrar ponto
    $stmt = $db->prepare("
        INSERT INTO pontos (
            usuario_id, data, hora, tipo, ip_address, user_agent, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
    ");
    
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if ($stmt->execute([
        $funcionario['id'], 
        $dataAtual, 
        date('H:i:s'), 
        $type, 
        $ip, 
        $userAgent
    ])) {
        $pontoId = $db->lastInsertId();
        
        // Log de auditoria
        $security->logAudit(
            $funcionario['id'],
            'REGISTRAR_PONTO',
            'pontos',
            $pontoId,
            null,
            [
                'tipo' => $type,
                'data' => $dataAtual,
                'hora' => date('H:i:s'),
                'ip' => $ip
            ]
        );
        
        jsonResponse(true, 'Ponto registrado com sucesso', [
            'ponto_id' => $pontoId,
            'funcionario' => [
                'id' => $funcionario['id'],
                'nome' => $funcionario['nome'],
                'departamento' => $funcionario['departamento_nome']
            ],
            'ponto' => [
                'tipo' => $type,
                'data' => $dataAtual,
                'hora' => date('H:i:s')
            ]
        ]);
    } else {
        jsonResponse(false, 'Erro ao registrar ponto');
    }
    
} catch (Exception $e) {
    $security->logAudit(
        null,
        'ERROR',
        'punch',
        null,
        null,
        ['error' => $e->getMessage()]
    );
    
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}

/**
 * Validação básica de sequência de pontos
 * Apenas previne registros óbvios (ex: saída antes de entrada)
 */
function validarSequenciaBasica($usuarioId, $novoTipo, $data, $db) {
    // Buscar pontos já registrados no dia
    $stmt = $db->prepare("
        SELECT tipo FROM pontos 
        WHERE usuario_id = ? AND data = ?
        ORDER BY hora ASC
    ");
    $stmt->execute([$usuarioId, $data]);
    $pontosExistentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Determinar sequência baseada no dia da semana
    $diaSemana = date('w', strtotime($data)); // 0 = domingo, 6 = sábado
    
    if ($diaSemana == 0) { // Domingo - folga
        return false; // Não permite nenhum ponto no domingo
    } elseif ($diaSemana == 6) { // Sábado - meio período
        $sequencia = ['entrada_manha', 'saida_tarde'];
    } else { // Segunda a sexta - período completo
        $sequencia = ['entrada_manha', 'saida_almoco', 'volta_almoco', 'saida_tarde'];
    }
    
    $posicaoNovo = array_search($novoTipo, $sequencia);
    
    if ($posicaoNovo === false) {
        return false;
    }
    
    // Verificar se todos os tipos anteriores foram registrados
    for ($i = 0; $i < $posicaoNovo; $i++) {
        if (!in_array($sequencia[$i], $pontosExistentes)) {
            return false;
        }
    }
    
    return true;
}
?>
