<?php
require_once '../config/config.php';

requireAdmin();

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
        $_SESSION['user_id'] ?? null,
        'ERROR',
        'grupos_jornada',
        null,
        null,
        ['error' => $e->getMessage()]
    );
    
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}

function handleGet() {
    global $db;
    
    if (isset($_GET['id'])) {
        // Buscar grupo específico
        $id = (int) $_GET['id'];
        $stmt = $db->prepare("
            SELECT gj.*, 
                   (SELECT COUNT(*) FROM usuarios WHERE grupo_jornada_id = gj.id AND ativo = 1) as total_funcionarios
            FROM grupos_jornada gj 
            WHERE gj.id = ?
        ");
        $stmt->execute([$id]);
        $grupo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$grupo) {
            jsonResponse(false, 'Grupo de jornada não encontrado', null, 404);
        }
        
        // Calcular preview da jornada
        $grupo['preview'] = calcularPreviewJornada($grupo);
        
        jsonResponse(true, 'Grupo encontrado', ['grupo' => $grupo]);
        
    } else {
        // Listar todos os grupos
        $stmt = $db->prepare("
            SELECT gj.*, 
                   (SELECT COUNT(*) FROM usuarios WHERE grupo_jornada_id = gj.id AND ativo = 1) as total_funcionarios
            FROM grupos_jornada gj 
            ORDER BY gj.nome ASC
        ");
        $stmt->execute();
        $grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Adicionar preview para cada grupo
        foreach ($grupos as &$grupo) {
            $grupo['preview'] = calcularPreviewJornada($grupo);
        }
        
        jsonResponse(true, 'Grupos listados com sucesso', ['grupos' => $grupos]);
    }
}

function handlePost($input) {
    global $db, $security;
    
    // Sanitizar e validar dados
    $dadosJornada = validarDadosJornada($input, $security);
    
    if ($dadosJornada['error']) {
        jsonResponse(false, $dadosJornada['message']);
    }
    
    extract($dadosJornada['data']);
    
    // Verificar unicidade do nome
    $stmt = $db->prepare("SELECT COUNT(*) FROM grupos_jornada WHERE UPPER(nome) = UPPER(?)");
    $stmt->execute([$nome]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Já existe um grupo com este nome');
    }
    
    // Verificar unicidade do código
    $stmt = $db->prepare("SELECT COUNT(*) FROM grupos_jornada WHERE codigo = ?");
    $stmt->execute([$codigo]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Já existe um grupo com este código');
    }
    
    // Validar sequência de horários
    if (!validarSequenciaHorarios($entrada_manha, $saida_almoco, $volta_almoco, $saida_tarde)) {
        jsonResponse(false, 'Horários inválidos: entrada_manha < saida_almoco < volta_almoco < saida_tarde');
    }
    
    // Inserir grupo
    $stmt = $db->prepare("
        INSERT INTO grupos_jornada (
            nome, codigo, entrada_manha, saida_almoco, volta_almoco, saida_tarde,
            carga_diaria_minutos, tolerancia_minutos, intervalo_almoco_minutos, data_vigencia,
            sabado_ativo, entrada_sabado, saida_sabado, carga_sabado_minutos, domingo_folga,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
    ");
    
    if ($stmt->execute([
        $nome, $codigo, $entrada_manha, $saida_almoco, $volta_almoco, 
        $saida_tarde, $carga_diaria_minutos, $tolerancia_minutos, $intervalo_almoco_minutos, $data_vigencia,
        $sabado_ativo, $entrada_sabado, $saida_sabado, $carga_sabado_minutos, $domingo_folga
    ])) {
        $grupoId = $db->lastInsertId();
        
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'CRIAR',
            'grupos_jornada',
            $grupoId,
            null,
            $dadosJornada['data']
        );
        
        // Buscar grupo criado para retorno
        $stmt = $db->prepare("SELECT * FROM grupos_jornada WHERE id = ?");
        $stmt->execute([$grupoId]);
        $grupo = $stmt->fetch(PDO::FETCH_ASSOC);
        $grupo['preview'] = calcularPreviewJornada($grupo);
        
        jsonResponse(true, 'Grupo de jornada criado com sucesso', ['grupo' => $grupo]);
    } else {
        jsonResponse(false, 'Erro ao criar grupo de jornada');
    }
}

function handlePut($input) {
    global $db, $security;
    
    $id = (int) ($input['id'] ?? 0);
    
    if (!$id) {
        jsonResponse(false, 'ID do grupo é obrigatório');
    }
    
    // Buscar dados atuais
    $stmt = $db->prepare("SELECT * FROM grupos_jornada WHERE id = ?");
    $stmt->execute([$id]);
    $dadosAntigos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dadosAntigos) {
        jsonResponse(false, 'Grupo de jornada não encontrado', null, 404);
    }
    
    // Mesclar dados atuais com novos dados
    $inputMerged = array_merge($dadosAntigos, $input);
    
    // Validar dados
    $dadosJornada = validarDadosJornada($inputMerged, $security);
    
    if ($dadosJornada['error']) {
        jsonResponse(false, $dadosJornada['message']);
    }
    
    extract($dadosJornada['data']);
    
    // Verificar unicidade do nome (exceto próprio registro)
    $stmt = $db->prepare("SELECT COUNT(*) FROM grupos_jornada WHERE UPPER(nome) = UPPER(?) AND id != ?");
    $stmt->execute([$nome, $id]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Já existe outro grupo com este nome');
    }
    
    // Verificar unicidade do código (exceto próprio registro)
    $stmt = $db->prepare("SELECT COUNT(*) FROM grupos_jornada WHERE codigo = ? AND id != ?");
    $stmt->execute([$codigo, $id]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Já existe outro grupo com este código');
    }
    
    // Validar sequência de horários
    if (!validarSequenciaHorarios($entrada_manha, $saida_almoco, $volta_almoco, $saida_tarde)) {
        jsonResponse(false, 'Horários inválidos: entrada_manha < saida_almoco < volta_almoco < saida_tarde');
    }
    
    // Verificar se pode desativar
    if (!$ativo) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE grupo_jornada_id = ? AND ativo = 1");
        $stmt->execute([$id]);
        $funcionariosAtivos = $stmt->fetchColumn();
        
        if ($funcionariosAtivos > 0) {
            jsonResponse(false, "Não é possível desativar. Existem {$funcionariosAtivos} funcionário(s) ativo(s) com este grupo.");
        }
    }
    
    // Se mudança significativa, incrementar versão
    $incrementarVersao = false;
    $camposSignificativos = ['entrada_manha', 'saida_almoco', 'volta_almoco', 'saida_tarde', 'carga_diaria_minutos'];
    
    foreach ($camposSignificativos as $campo) {
        if ($dadosAntigos[$campo] != $dadosJornada['data'][$campo]) {
            $incrementarVersao = true;
            break;
        }
    }
    
    $versao = $dadosAntigos['versao'];
    if ($incrementarVersao) {
        $versao++;
    }
    
    // Atualizar grupo
    $stmt = $db->prepare("
        UPDATE grupos_jornada 
        SET nome = ?, codigo = ?, entrada_manha = ?, saida_almoco = ?, volta_almoco = ?, 
            saida_tarde = ?, carga_diaria_minutos = ?, tolerancia_minutos = ?, 
            intervalo_almoco_minutos = ?, ativo = ?, versao = ?, data_vigencia = ?,
            sabado_ativo = ?, entrada_sabado = ?, saida_sabado = ?, carga_sabado_minutos = ?, domingo_folga = ?,
            updated_at = datetime('now')
        WHERE id = ?
    ");
    
    if ($stmt->execute([
        $nome, $codigo, $entrada_manha, $saida_almoco, $volta_almoco,
        $saida_tarde, $carga_diaria_minutos, $tolerancia_minutos, $intervalo_almoco_minutos,
        $ativo ? 1 : 0, $versao, $data_vigencia,
        $sabado_ativo, $entrada_sabado, $saida_sabado, $carga_sabado_minutos, $domingo_folga,
        $id
    ])) {
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'ATUALIZAR',
            'grupos_jornada',
            $id,
            $dadosAntigos,
            $dadosJornada['data']
        );
        
        // Buscar grupo atualizado
        $stmt = $db->prepare("
            SELECT gj.*, 
                   (SELECT COUNT(*) FROM usuarios WHERE grupo_jornada_id = gj.id AND ativo = 1) as total_funcionarios
            FROM grupos_jornada gj 
            WHERE gj.id = ?
        ");
        $stmt->execute([$id]);
        $grupo = $stmt->fetch(PDO::FETCH_ASSOC);
        $grupo['preview'] = calcularPreviewJornada($grupo);
        
        jsonResponse(true, 'Grupo de jornada atualizado com sucesso', ['grupo' => $grupo]);
    } else {
        jsonResponse(false, 'Erro ao atualizar grupo de jornada');
    }
}

function handleDelete($input) {
    global $db, $security;
    
    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    
    if (!$id) {
        jsonResponse(false, 'ID do grupo é obrigatório');
    }
    
    // Buscar dados atuais
    $stmt = $db->prepare("SELECT * FROM grupos_jornada WHERE id = ?");
    $stmt->execute([$id]);
    $grupo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$grupo) {
        jsonResponse(false, 'Grupo de jornada não encontrado', null, 404);
    }
    
    // Verificar se pode excluir
    $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE grupo_jornada_id = ?");
    $stmt->execute([$id]);
    $totalFuncionarios = $stmt->fetchColumn();
    
    if ($totalFuncionarios > 0) {
        jsonResponse(false, "Não é possível excluir. Existem {$totalFuncionarios} funcionário(s) vinculado(s) a este grupo.");
    }
    
    // Excluir grupo
    $stmt = $db->prepare("DELETE FROM grupos_jornada WHERE id = ?");
    
    if ($stmt->execute([$id])) {
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'EXCLUIR',
            'grupos_jornada',
            $id,
            $grupo,
            null
        );
        
        jsonResponse(true, 'Grupo de jornada excluído com sucesso');
    } else {
        jsonResponse(false, 'Erro ao excluir grupo de jornada');
    }
}

function validarDadosJornada($input, $security) {
    // Sanitizar dados
    $nome = $security->sanitizeInput($input['nome'] ?? '');
    $codigo = strtoupper($security->sanitizeInput($input['codigo'] ?? ''));
    $entrada_manha = $security->sanitizeInput($input['entrada_manha'] ?? '08:00:00');
    $saida_almoco = $security->sanitizeInput($input['saida_almoco'] ?? '12:00:00');
    $volta_almoco = $security->sanitizeInput($input['volta_almoco'] ?? '13:00:00');
    $saida_tarde = $security->sanitizeInput($input['saida_tarde'] ?? '18:00:00');
    $tolerancia_minutos = (int) ($input['tolerancia_minutos'] ?? 10);
    $intervalo_almoco_minutos = (int) ($input['intervalo_almoco_minutos'] ?? 60);
    $data_vigencia = $security->sanitizeInput($input['data_vigencia'] ?? date('Y-m-d'));
    $ativo = isset($input['ativo']) ? (bool) $input['ativo'] : true;
    
    // Novos campos para sábado e domingo
    $sabado_ativo = isset($input['sabado_ativo']) ? (bool) $input['sabado_ativo'] : false;
    $entrada_sabado = $security->sanitizeInput($input['entrada_sabado'] ?? '08:00:00');
    $saida_sabado = $security->sanitizeInput($input['saida_sabado'] ?? '12:00:00');
    $carga_sabado_minutos = (int) ($input['carga_sabado_minutos'] ?? 240);
    $domingo_folga = isset($input['domingo_folga']) ? (bool) $input['domingo_folga'] : true;
    
    // Validações básicas
    if (empty($nome)) {
        return ['error' => true, 'message' => 'Nome é obrigatório'];
    }
    
    if (empty($codigo)) {
        return ['error' => true, 'message' => 'Código é obrigatório'];
    }
    
    if (!preg_match('/^[A-Z0-9]{2,10}$/', $codigo)) {
        return ['error' => true, 'message' => 'Código deve ter entre 2 e 10 caracteres (apenas letras e números)'];
    }
    
    // Validar formato dos horários (aceita HH:MM ou HH:MM:SS)
    $horarios = ['entrada_manha', 'saida_almoco', 'volta_almoco', 'saida_tarde'];
    foreach ($horarios as $horario) {
        $valor = ${$horario};
        // Aceitar formato HH:MM ou HH:MM:SS
        if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $valor)) {
            return ['error' => true, 'message' => "Formato de horário inválido para {$horario}"];
        }
        
        // Normalizar para HH:MM:SS se necessário
        if (strlen($valor) === 5) { // HH:MM
            ${$horario} = $valor . ':00';
        }
    }
    
    // Validar sequência lógica dos horários
    if (!validarSequenciaHorarios($entrada_manha, $saida_almoco, $volta_almoco, $saida_tarde)) {
        return ['error' => true, 'message' => 'Horários inválidos: entrada_manha < saida_almoco < volta_almoco < saida_tarde'];
    }
    
    // Calcular carga diária em minutos
    $carga_diaria_minutos = calcularCargaDiaria($entrada_manha, $saida_almoco, $volta_almoco, $saida_tarde);
    
    if ($tolerancia_minutos < 0 || $tolerancia_minutos > 60) {
        return ['error' => true, 'message' => 'Tolerância deve estar entre 0 e 60 minutos'];
    }
    
    if ($intervalo_almoco_minutos < 0 || $intervalo_almoco_minutos > 180) {
        return ['error' => true, 'message' => 'Intervalo de almoço deve estar entre 0 e 180 minutos'];
    }
    
    // Validar horários do sábado se ativo
    if ($sabado_ativo) {
        // Validar formato dos horários do sábado
        if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $entrada_sabado)) {
            return ['error' => true, 'message' => 'Formato de horário inválido para entrada do sábado'];
        }
        
        if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $saida_sabado)) {
            return ['error' => true, 'message' => 'Formato de horário inválido para saída do sábado'];
        }
        
        // Normalizar para HH:MM:SS se necessário
        if (strlen($entrada_sabado) === 5) {
            $entrada_sabado = $entrada_sabado . ':00';
        }
        if (strlen($saida_sabado) === 5) {
            $saida_sabado = $saida_sabado . ':00';
        }
        
        // Validar sequência lógica dos horários do sábado
        if (strtotime($entrada_sabado) >= strtotime($saida_sabado)) {
            return ['error' => true, 'message' => 'Horários do sábado inválidos: entrada deve ser anterior à saída'];
        }
        
        // Validar carga do sábado (máximo 8 horas)
        if ($carga_sabado_minutos < 60 || $carga_sabado_minutos > 480) {
            return ['error' => true, 'message' => 'Carga do sábado deve estar entre 60 e 480 minutos (1 a 8 horas)'];
        }
    }
    
    // Validar carga semanal total (44 horas = 2640 minutos)
    $carga_semanal_total = ($carga_diaria_minutos * 5) + ($sabado_ativo ? $carga_sabado_minutos : 0);
    if ($carga_semanal_total !== 2640) {
        return ['error' => true, 'message' => "Carga semanal deve totalizar exatamente 44 horas (2640 minutos). Atual: " . floor($carga_semanal_total / 60) . "h " . ($carga_semanal_total % 60) . "min"];
    }
    
    return [
        'error' => false,
        'data' => [
            'nome' => $nome,
            'codigo' => $codigo,
            'entrada_manha' => $entrada_manha,
            'saida_almoco' => $saida_almoco,
            'volta_almoco' => $volta_almoco,
            'saida_tarde' => $saida_tarde,
            'carga_diaria_minutos' => $carga_diaria_minutos,
            'tolerancia_minutos' => $tolerancia_minutos,
            'intervalo_almoco_minutos' => $intervalo_almoco_minutos,
            'data_vigencia' => $data_vigencia,
            'ativo' => $ativo,
            'sabado_ativo' => $sabado_ativo,
            'entrada_sabado' => $entrada_sabado,
            'saida_sabado' => $saida_sabado,
            'carga_sabado_minutos' => $carga_sabado_minutos,
            'domingo_folga' => $domingo_folga
        ]
    ];
}


function calcularCargaDiaria($entrada, $saida_almoco, $volta_almoco, $saida) {
    $manha = strtotime($saida_almoco) - strtotime($entrada);
    $tarde = strtotime($saida) - strtotime($volta_almoco);
    
    return ($manha + $tarde) / 60; // Converter para minutos
}

function calcularPreviewJornada($grupo) {
    $carga_horas = floor($grupo['carga_diaria_minutos'] / 60);
    $carga_minutos = $grupo['carga_diaria_minutos'] % 60;
    
    // Calcular carga semanal
    $carga_semanal_total = ($grupo['carga_diaria_minutos'] * 5) + (($grupo['sabado_ativo'] ?? 0) ? ($grupo['carga_sabado_minutos'] ?? 0) : 0);
    $carga_semanal_horas = floor($carga_semanal_total / 60);
    $carga_semanal_minutos = $carga_semanal_total % 60;
    
    // Calcular carga do sábado se ativo
    $carga_sabado_formatada = '';
    if ($grupo['sabado_ativo'] ?? 0) {
        $sabado_horas = floor(($grupo['carga_sabado_minutos'] ?? 0) / 60);
        $sabado_minutos = ($grupo['carga_sabado_minutos'] ?? 0) % 60;
        $carga_sabado_formatada = sprintf('%02d:%02d', $sabado_horas, $sabado_minutos);
    }
    
    return [
        'carga_diaria_formatada' => sprintf('%02d:%02d', $carga_horas, $carga_minutos),
        'carga_semanal_formatada' => sprintf('%02d:%02d', $carga_semanal_horas, $carga_semanal_minutos),
        'carga_sabado_formatada' => $carga_sabado_formatada,
        'entrada_manha' => substr($grupo['entrada_manha'], 0, 5),
        'saida_almoco' => substr($grupo['saida_almoco'], 0, 5),
        'volta_almoco' => substr($grupo['volta_almoco'], 0, 5),
        'saida_tarde' => substr($grupo['saida_tarde'], 0, 5),
        'entrada_sabado' => isset($grupo['entrada_sabado']) ? substr($grupo['entrada_sabado'], 0, 5) : '',
        'saida_sabado' => isset($grupo['saida_sabado']) ? substr($grupo['saida_sabado'], 0, 5) : '',
        'tolerancia_formatada' => $grupo['tolerancia_minutos'] . ' min',
        'sabado_ativo' => $grupo['sabado_ativo'] ?? 0,
        'domingo_folga' => $grupo['domingo_folga'] ?? 1
    ];
}

/**
 * Valida se a sequência de horários está correta
 * entrada_manha < saida_almoco < volta_almoco < saida_tarde
 */
function validarSequenciaHorarios($entrada_manha, $saida_almoco, $volta_almoco, $saida_tarde) {
    $entrada = strtotime($entrada_manha);
    $saida_almoco_time = strtotime($saida_almoco);
    $volta_almoco_time = strtotime($volta_almoco);
    $saida = strtotime($saida_tarde);
    
    // Verificar se entrada_manha < saida_almoco
    if ($entrada >= $saida_almoco_time) {
        return false;
    }
    
    // Verificar se saida_almoco < volta_almoco
    if ($saida_almoco_time >= $volta_almoco_time) {
        return false;
    }
    
    // Verificar se volta_almoco < saida_tarde
    if ($volta_almoco_time >= $saida) {
        return false;
    }
    
    return true;
}
?>