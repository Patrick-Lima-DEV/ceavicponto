<?php
require_once '../config/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$security = $GLOBALS['security'];
$database = $GLOBALS['db'];
$db = $database->getConnection();

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
            
        case 'POST':
            handlePost($input);
            break;
            
        case 'PUT':
            handlePut($input);
            break;
            
        case 'DELETE':
            handleDelete($input);
            break;
            
        default:
            jsonResponse(false, 'Método não permitido', null, 405);
    }
    
} catch (Exception $e) {
    $security->logAudit(
        $_SESSION['funcionario_id'] ?? $_SESSION['user_id'] ?? null,
        'ERROR',
        'pontos',
        null,
        null,
        ['error' => $e->getMessage()]
    );
    
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}

function handleGet() {
    global $security;
    $db = $GLOBALS['db']->getConnection();
    
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'jornada_funcionario':
                return buscarJornadaFuncionario();
                
            case 'pontos_funcionario':
                return buscarPontosFuncionario();
                
            default:
                jsonResponse(false, 'Ação não encontrada');
        }
    }
    
    // Para admins - listar pontos
    requireAdmin();
    listarPontos();
}

function buscarJornadaFuncionario() {
    global $security;
    $db = $GLOBALS['db']->getConnection();
    
    // Verificar se funcionário está autenticado
    if (!isset($_SESSION['funcionario_id'])) {
        jsonResponse(false, 'Funcionário não autenticado');
    }
    
    $funcionario_id = $_SESSION['funcionario_id'];
    $data = $_GET['data'] ?? date('Y-m-d');
    
    // Buscar dados do funcionário
    $stmt = $db->prepare("
        SELECT u.nome, u.departamento_id, u.grupo_jornada_id,
               d.nome as departamento_nome,
               gj.nome as grupo_jornada_nome, gj.entrada_manha, gj.saida_almoco,
               gj.volta_almoco, gj.saida_tarde, gj.carga_diaria_minutos, 
               gj.tolerancia_minutos
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
        WHERE u.id = ? AND u.ativo = 1
    ");
    $stmt->execute([$funcionario_id]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$funcionario) {
        jsonResponse(false, 'Funcionário não encontrado ou inativo');
    }
    
    // Verificar se há override de jornada ativo para a data
    $stmt = $db->prepare("
        SELECT * FROM usuario_jornada_override
        WHERE usuario_id = ? AND ativo = 1
        AND data_inicio <= ? 
        AND (data_fim IS NULL OR data_fim >= ?)
        ORDER BY data_inicio DESC
        LIMIT 1
    ");
    $stmt->execute([$funcionario_id, $data, $data]);
    $override = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se há override, usar seus dados
    if ($override) {
        $jornada = [
            'entrada_manha' => $override['entrada_manha'] ?: $funcionario['entrada_manha'],
            'saida_almoco' => $override['saida_almoco'] ?: $funcionario['saida_almoco'],
            'volta_almoco' => $override['volta_almoco'] ?: $funcionario['volta_almoco'],
            'saida_tarde' => $override['saida_tarde'] ?: $funcionario['saida_tarde'],
            'carga_diaria_minutos' => $override['carga_diaria_minutos'] ?: $funcionario['carga_diaria_minutos'],
            'tolerancia_minutos' => $override['tolerancia_minutos'] ?: $funcionario['tolerancia_minutos'],
            'override' => true,
            'motivo_override' => $override['motivo']
        ];
    } else {
        $jornada = [
            'entrada_manha' => $funcionario['entrada_manha'],
            'saida_almaco' => $funcionario['saida_almoco'],
            'volta_almoco' => $funcionario['volta_almoco'],
            'saida_tarde' => $funcionario['saida_tarde'],
            'carga_diaria_minutos' => $funcionario['carga_diaria_minutos'],
            'tolerancia_minutos' => $funcionario['tolerancia_minutos'],
            'override' => false
        ];
    }
    
    // Buscar pontos já registrados na data
    $stmt = $db->prepare("
        SELECT tipo_ponto, horario, latitude, longitude, ip_address
        FROM pontos 
        WHERE usuario_id = ? AND DATE(horario) = ?
        ORDER BY horario ASC
    ");
    $stmt->execute([$funcionario_id, $data]);
    $pontos_registrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(true, 'Jornada do funcionário', [
        'funcionario' => [
            'nome' => $funcionario['nome'],
            'departamento' => $funcionario['departamento_nome']
        ],
        'jornada' => $jornada,
        'pontos_registrados' => $pontos_registrados,
        'data' => $data
    ]);
}

function buscarPontosFuncionario() {
    $db = $GLOBALS['db']->getConnection();
    
    // Verificar se funcionário está autenticado
    if (!isset($_SESSION['funcionario_id'])) {
        jsonResponse(false, 'Funcionário não autenticado');
    }
    
    $funcionario_id = $_SESSION['funcionario_id'];
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $data_fim = $_GET['data_fim'] ?? date('Y-m-t');
    
    $stmt = $db->prepare("
        SELECT DATE(horario) as data, tipo_ponto, horario, observacao
        FROM pontos 
        WHERE usuario_id = ? 
        AND DATE(horario) BETWEEN ? AND ?
        ORDER BY horario ASC
    ");
    $stmt->execute([$funcionario_id, $data_inicio, $data_fim]);
    $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por data
    $pontos_agrupados = [];
    foreach ($pontos as $ponto) {
        $data = $ponto['data'];
        if (!isset($pontos_agrupados[$data])) {
            $pontos_agrupados[$data] = [];
        }
        $pontos_agrupados[$data][] = $ponto;
    }
    
    jsonResponse(true, 'Pontos do funcionário', [
        'pontos' => $pontos_agrupados,
        'periodo' => ['inicio' => $data_inicio, 'fim' => $data_fim]
    ]);
}

function listarPontos() {
    $db = $GLOBALS['db']->getConnection();
    
    $usuario_id = $_GET['usuario_id'] ?? null;
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $data_fim = $_GET['data_fim'] ?? date('Y-m-t');
    $departamento_id = $_GET['departamento_id'] ?? null;
    
    $where = ['1=1'];
    $params = [];
    
    if ($usuario_id) {
        $where[] = 'p.usuario_id = ?';
        $params[] = $usuario_id;
    }
    
    if ($departamento_id) {
        $where[] = 'u.departamento_id = ?';
        $params[] = $departamento_id;
    }
    
    $where[] = 'DATE(p.horario) BETWEEN ? AND ?';
    $params[] = $data_inicio;
    $params[] = $data_fim;
    
    $whereClause = implode(' AND ', $where);
    
    $stmt = $db->prepare("
        SELECT p.*, u.nome as funcionario_nome, u.cpf, u.matricula,
               d.nome as departamento_nome
        FROM pontos p
        INNER JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        WHERE {$whereClause}
        ORDER BY p.horario DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(true, 'Pontos listados', [
        'pontos' => $pontos,
        'filtros' => [
            'data_inicio' => $data_inicio,
            'data_fim' => $data_fim,
            'usuario_id' => $usuario_id,
            'departamento_id' => $departamento_id
        ]
    ]);
}

function handlePost($input) {
    global $security, $db;
    
    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'registrar_ponto':
                return registrarPonto($input);
                
            default:
                jsonResponse(false, 'Ação não encontrada');
        }
    }
    
    // Ação padrão: registrar ponto
    registrarPonto($input);
}

function registrarPonto($input) {
    global $security;
    $db = $GLOBALS['db']->getConnection();
    
    // Verificar se funcionário está autenticado
    if (!isset($_SESSION['funcionario_id'])) {
        jsonResponse(false, 'Funcionário não autenticado');
    }
    
    $funcionario_id = $_SESSION['funcionario_id'];
    $tipo_ponto = $security->sanitizeInput($input['tipo_ponto'] ?? '');
    $latitude = isset($input['latitude']) ? (float) $input['latitude'] : null;
    $longitude = isset($input['longitude']) ? (float) $input['longitude'] : null;
    $observacao = $security->sanitizeInput($input['observacao'] ?? '');
    $ip_address = $security->getClientIP();
    
    // Validar tipo de ponto
    $tipos_validos = ['entrada_manha', 'saida_almoco', 'volta_almoco', 'saida_tarde'];
    if (!in_array($tipo_ponto, $tipos_validos)) {
        jsonResponse(false, 'Tipo de ponto inválido');
    }
    
    // Verificar se funcionário está ativo
    $stmt = $db->prepare("SELECT ativo FROM usuarios WHERE id = ?");
    $stmt->execute([$funcionario_id]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$funcionario || !$funcionario['ativo']) {
        jsonResponse(false, 'Funcionário inativo');
    }
    
    $data_hoje = date('Y-m-d');
    $horario_atual = date('Y-m-d H:i:s');
    
    // Verificar se já existe ponto do mesmo tipo na data
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM pontos 
        WHERE usuario_id = ? AND tipo_ponto = ? AND DATE(horario) = ?
    ");
    $stmt->execute([$funcionario_id, $tipo_ponto, $data_hoje]);
    
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Ponto já registrado para este tipo hoje');
    }
    
    // Verificar sequência lógica de pontos
    if (!validarSequenciaPonto($funcionario_id, $tipo_ponto, $data_hoje, $db)) {
        jsonResponse(false, 'Sequência de pontos inválida. Registre os pontos na ordem correta.');
    }
    
    // Registrar o ponto
    $stmt = $db->prepare("
        INSERT INTO pontos (
            usuario_id, tipo_ponto, horario, latitude, longitude, 
            ip_address, observacao, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'))
    ");
    
    if ($stmt->execute([
        $funcionario_id, $tipo_ponto, $horario_atual, 
        $latitude, $longitude, $ip_address, $observacao
    ])) {
        $ponto_id = $db->lastInsertId();
        
        // Log de auditoria
        $security->logAudit(
            $funcionario_id,
            'REGISTRAR_PONTO',
            'pontos',
            $ponto_id,
            null,
            [
                'tipo_ponto' => $tipo_ponto,
                'horario' => $horario_atual,
                'ip' => $ip_address,
                'latitude' => $latitude,
                'longitude' => $longitude
            ]
        );
        
        jsonResponse(true, 'Ponto registrado com sucesso', [
            'ponto_id' => $ponto_id,
            'tipo_ponto' => $tipo_ponto,
            'horario' => $horario_atual
        ]);
    } else {
        jsonResponse(false, 'Erro ao registrar ponto');
    }
}

function validarSequenciaPonto($funcionario_id, $novo_tipo, $data, $db) {
    // Buscar pontos já registrados no dia
    $stmt = $db->prepare("
        SELECT tipo_ponto FROM pontos 
        WHERE usuario_id = ? AND DATE(horario) = ?
        ORDER BY horario ASC
    ");
    $stmt->execute([$funcionario_id, $data]);
    $pontos_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Definir sequência esperada
    $sequencia_esperada = ['entrada_manha', 'saida_almoco', 'volta_almoco', 'saida_tarde'];
    
    // Encontrar posição do novo tipo
    $posicao_novo = array_search($novo_tipo, $sequencia_esperada);
    
    if ($posicao_novo === false) {
        return false;
    }
    
    // Verificar se todos os tipos anteriores foram registrados
    for ($i = 0; $i < $posicao_novo; $i++) {
        if (!in_array($sequencia_esperada[$i], $pontos_existentes)) {
            return false;
        }
    }
    
    return true;
}

function handlePut($input) {
    requireAdmin();
    global $security;
    $db = $GLOBALS['db']->getConnection();
    
    $ponto_id = (int) ($input['id'] ?? 0);
    
    if (!$ponto_id) {
        jsonResponse(false, 'ID do ponto é obrigatório');
    }
    
    // Buscar ponto existente
    $stmt = $db->prepare("SELECT * FROM pontos WHERE id = ?");
    $stmt->execute([$ponto_id]);
    $ponto_antigo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ponto_antigo) {
        jsonResponse(false, 'Ponto não encontrado', null, 404);
    }
    
    // Atualizar apenas campos permitidos
    $observacao = $security->sanitizeInput($input['observacao'] ?? $ponto_antigo['observacao']);
    $justificativa = $security->sanitizeInput($input['justificativa'] ?? '');
    
    if (empty($justificativa)) {
        jsonResponse(false, 'Justificativa é obrigatória para alteração de ponto');
    }
    
    $stmt = $db->prepare("
        UPDATE pontos 
        SET observacao = ?, justificativa_alteracao = ?, 
            alterado_por = ?, alterado_em = datetime('now')
        WHERE id = ?
    ");
    
    if ($stmt->execute([$observacao, $justificativa, $_SESSION['user_id'], $ponto_id])) {
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'ALTERAR_PONTO',
            'pontos',
            $ponto_id,
            $ponto_antigo,
            [
                'observacao_nova' => $observacao,
                'justificativa' => $justificativa
            ]
        );
        
        jsonResponse(true, 'Ponto alterado com sucesso');
    } else {
        jsonResponse(false, 'Erro ao alterar ponto');
    }
}

function handleDelete($input) {
    requireAdmin();
    global $security;
    $db = $GLOBALS['db']->getConnection();
    
    $ponto_id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    
    if (!$ponto_id) {
        jsonResponse(false, 'ID do ponto é obrigatório');
    }
    
    // Buscar ponto
    $stmt = $db->prepare("SELECT * FROM pontos WHERE id = ?");
    $stmt->execute([$ponto_id]);
    $ponto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ponto) {
        jsonResponse(false, 'Ponto não encontrado', null, 404);
    }
    
    $justificativa = $input['justificativa'] ?? '';
    if (empty($justificativa)) {
        jsonResponse(false, 'Justificativa é obrigatória para exclusão de ponto');
    }
    
    // Excluir ponto
    $stmt = $db->prepare("DELETE FROM pontos WHERE id = ?");
    
    if ($stmt->execute([$ponto_id])) {
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'EXCLUIR_PONTO',
            'pontos',
            $ponto_id,
            $ponto,
            ['justificativa' => $justificativa]
        );
        
        jsonResponse(true, 'Ponto excluído com sucesso');
    } else {
        jsonResponse(false, 'Erro ao excluir ponto');
    }
}
?>