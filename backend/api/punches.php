<?php
require_once '../config/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$security = $GLOBALS['security'];
$db = $GLOBALS['db']->getConnection();

try {
    if ($method !== 'GET') {
        jsonResponse(false, 'Método não permitido', null, 405);
    }
    
    // Parâmetros obrigatórios
    $user_id = $_GET['user_id'] ?? null;
    $date = $_GET['date'] ?? date('Y-m-d');
    
    if (!$user_id) {
        jsonResponse(false, 'user_id é obrigatório');
    }
    
    // Validar formato da data
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        jsonResponse(false, 'Formato de data inválido. Use YYYY-MM-DD');
    }
    
    // Buscar pontos do usuário na data especificada
    $stmt = $db->prepare("
        SELECT 
            id,
            data,
            hora,
            tipo,
            created_at
        FROM pontos 
        WHERE usuario_id = ? AND data = ?
        ORDER BY hora ASC
    ");
    $stmt->execute([$user_id, $date]);
    $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar informações do usuário
    $stmt = $db->prepare("
        SELECT u.id, u.nome, u.cpf, u.matricula,
               d.nome as departamento_nome,
               gj.entrada_manha, gj.saida_almoco, gj.volta_almoco, gj.saida_tarde,
               gj.carga_diaria_minutos, gj.tolerancia_minutos
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
        WHERE u.id = ? AND u.ativo = 1
    ");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        jsonResponse(false, 'Usuário não encontrado ou inativo');
    }
    
    // Verificar se há override de jornada ativo
    $stmt = $db->prepare("
        SELECT * FROM usuario_jornada_override
        WHERE usuario_id = ? AND ativo = 1
        AND data_inicio <= ? 
        AND (data_fim IS NULL OR data_fim >= ?)
        ORDER BY data_inicio DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id, $date, $date]);
    $override = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Preparar dados de jornada (override tem prioridade)
    if ($override) {
        $jornada = [
            'entrada_manha' => $override['entrada_manha'] ?: $usuario['entrada_manha'],
            'saida_almoco' => $override['saida_almoco'] ?: $usuario['saida_almoco'],
            'volta_almoco' => $override['volta_almoco'] ?: $usuario['volta_almoco'],
            'saida_tarde' => $override['saida_tarde'] ?: $usuario['saida_tarde'],
            'carga_diaria_minutos' => $override['carga_diaria_minutos'] ?: $usuario['carga_diaria_minutos'],
            'tolerancia_minutos' => $override['tolerancia_minutos'] ?: $usuario['tolerancia_minutos'],
            'tipo' => 'override',
            'motivo' => $override['motivo']
        ];
    } else {
        $jornada = [
            'entrada_manha' => $usuario['entrada_manha'],
            'saida_almoco' => $usuario['saida_almoco'],
            'volta_almoco' => $usuario['volta_almoco'],
            'saida_tarde' => $usuario['saida_tarde'],
            'carga_diaria_minutos' => $usuario['carga_diaria_minutos'],
            'tolerancia_minutos' => $usuario['tolerancia_minutos'],
            'tipo' => 'padrao'
        ];
    }
    
    jsonResponse(true, 'Pontos recuperados com sucesso', [
        'usuario' => [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'cpf' => $usuario['cpf'],
            'matricula' => $usuario['matricula'],
            'departamento' => $usuario['departamento_nome']
        ],
        'jornada' => $jornada,
        'pontos' => $pontos,
        'data' => $date,
        'total_pontos' => count($pontos)
    ]);
    
} catch (Exception $e) {
    $security->logAudit(
        null,
        'ERROR',
        'punches',
        null,
        null,
        ['error' => $e->getMessage()]
    );
    
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}
?>
