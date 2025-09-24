<?php
/**
 * API para gerenciamento de registros de ponto por funcionário
 * Permite visualizar, editar e gerenciar registros de ponto
 */

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
        'registros_ponto',
        null,
        null,
        ['error' => $e->getMessage()]
    );
    
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}

function handleGet() {
    global $db;
    
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'funcionarios':
                return listarFuncionarios();
                
            case 'registros_funcionario':
                return listarRegistrosFuncionario();
                
            default:
                jsonResponse(false, 'Ação não encontrada');
        }
    }
    
    // Ação padrão: listar registros com filtros
    listarRegistros();
}

/**
 * Lista funcionários ativos para seleção
 */
function listarFuncionarios() {
    global $db;
    
    $stmt = $db->prepare("
        SELECT u.id, u.nome, u.cpf, u.matricula,
               d.nome as departamento_nome,
               gj.nome as grupo_jornada_nome
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
        WHERE u.tipo = 'funcionario' AND u.ativo = 1
        ORDER BY u.nome ASC
    ");
    $stmt->execute();
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(true, 'Funcionários listados com sucesso', [
        'funcionarios' => $funcionarios
    ]);
}

/**
 * Lista registros de ponto de um funcionário específico
 */
function listarRegistrosFuncionario() {
    global $db;
    
    $funcionario_id = (int) ($_GET['funcionario_id'] ?? 0);
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $data_fim = $_GET['data_fim'] ?? date('Y-m-t');
    
    if (!$funcionario_id) {
        jsonResponse(false, 'ID do funcionário é obrigatório');
    }
    
    // Buscar dados do funcionário
    $stmt = $db->prepare("
        SELECT u.nome, u.cpf, u.matricula,
               d.nome as departamento_nome,
               gj.nome as grupo_jornada_nome, gj.carga_diaria_minutos
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
        WHERE u.id = ? AND u.tipo = 'funcionario' AND u.ativo = 1
    ");
    $stmt->execute([$funcionario_id]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$funcionario) {
        jsonResponse(false, 'Funcionário não encontrado ou inativo', null, 404);
    }
    
    // Buscar registros de ponto no período
    $stmt = $db->prepare("
        SELECT p.*, 
               u_editor.nome as editado_por_nome
        FROM pontos p
        LEFT JOIN usuarios u_editor ON p.editado_por = u_editor.id
        WHERE p.usuario_id = ? 
        AND p.data BETWEEN ? AND ?
        ORDER BY p.data DESC, p.hora ASC
    ");
    $stmt->execute([$funcionario_id, $data_inicio, $data_fim]);
    $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar justificativas ativas no período
    $stmt_justificativas = $db->prepare("
        SELECT j.*, tj.codigo as justificativa_codigo, tj.nome as justificativa_tipo_nome
        FROM justificativas j
        JOIN tipos_justificativa tj ON j.tipo_justificativa_id = tj.id
        WHERE j.funcionario_id = ? 
        AND j.status = 'ativa'
        AND j.data_inicio <= ? 
        AND (j.data_fim >= ? OR j.data_fim IS NULL)
    ");
    $stmt_justificativas->execute([$funcionario_id, $data_fim, $data_inicio]);
    $justificativas = $stmt_justificativas->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar pontos por data
    $registros_agrupados = [];
    foreach ($pontos as $ponto) {
        $data = $ponto['data'];
        if (!isset($registros_agrupados[$data])) {
            $registros_agrupados[$data] = [
                'data' => $data,
                'entrada_manha' => null,
                'saida_almoco' => null,
                'volta_almoco' => null,
                'saida_tarde' => null,
                'completo' => false,
                'tem_edicao' => false,
                'justificativa' => null
            ];
        }
        
        // Mapear tipos do banco para campos da estrutura
        $mapeamento_tipos = [
            'entrada_manha' => 'entrada_manha',
            'saida_almoco' => 'saida_almoco', 
            'volta_almoco' => 'volta_almoco',
            'saida_tarde' => 'saida_tarde'
        ];
        $tipo_campo = $mapeamento_tipos[$ponto['tipo']] ?? $ponto['tipo'];
        
        $registros_agrupados[$data][$tipo_campo] = [
            'id' => $ponto['id'],
            'hora' => $ponto['hora'],
            'editado' => (bool) $ponto['editado'],
            'editado_em' => $ponto['editado_em'],
            'editado_por_nome' => $ponto['editado_por_nome'],
            'motivo_ajuste' => $ponto['motivo_ajuste'],
            'tempo_ajustado_minutos' => (int) $ponto['tempo_ajustado_minutos'],
            'observacao' => $ponto['observacao']
        ];
        
        if ($ponto['editado']) {
            $registros_agrupados[$data]['tem_edicao'] = true;
        }
    }
    
    // Associar justificativas aos registros
    foreach ($justificativas as $justificativa) {
        $data_inicio = $justificativa['data_inicio'];
        $data_fim = $justificativa['data_fim'] ?? $data_inicio;
        
        // Gerar todas as datas do período da justificativa
        $data_atual = $data_inicio;
        while ($data_atual <= $data_fim && $data_atual <= $data_fim) {
            if ($data_atual >= $data_inicio && $data_atual <= $data_fim) {
                if (!isset($registros_agrupados[$data_atual])) {
                    $registros_agrupados[$data_atual] = [
                        'data' => $data_atual,
                        'entrada_manha' => null,
                        'saida_almoco' => null,
                        'volta_almoco' => null,
                        'saida_tarde' => null,
                        'completo' => false,
                        'tem_edicao' => false,
                        'justificativa' => null
                    ];
                }
                
                // Se não há justificativa ou se esta é mais específica (período parcial)
                if (!$registros_agrupados[$data_atual]['justificativa'] || 
                    $justificativa['periodo_parcial'] !== 'integral') {
                    $registros_agrupados[$data_atual]['justificativa'] = [
                        'id' => $justificativa['id'],
                        'tipo_codigo' => $justificativa['justificativa_codigo'],
                        'tipo_nome' => $justificativa['justificativa_tipo_nome'],
                        'periodo_parcial' => $justificativa['periodo_parcial'],
                        'motivo' => $justificativa['motivo']
                    ];
                }
            }
            $data_atual = date('Y-m-d', strtotime($data_atual . ' +1 day'));
        }
    }
    
    // Verificar completude dos registros com lógica inteligente
    foreach ($registros_agrupados as &$registro) {
        $data = $registro['data'];
        $diaSemana = date('w', strtotime($data)); // 0 = domingo, 6 = sábado
        
        // Determinar completude baseada no tipo de dia e justificativas
        if ($registro['justificativa']) {
            // Registros com justificativa são sempre considerados completos
            $registro['completo'] = true;
        } elseif ($diaSemana == 0) {
            // Domingos são folga - sempre completos
            $registro['completo'] = true;
        } elseif ($diaSemana == 6) {
            // Sábados são meio período - precisam apenas de entrada e saída
            $registro['completo'] = (
                $registro['entrada_manha'] !== null &&
                $registro['saida_tarde'] !== null
            );
        } else {
            // Dias úteis (segunda a sexta) - precisam de todos os 4 pontos
            $registro['completo'] = (
                $registro['entrada_manha'] !== null &&
                $registro['saida_almoco'] !== null &&
                $registro['volta_almoco'] !== null &&
                $registro['saida_tarde'] !== null
            );
        }
        
        // Calcular status
        $registro['status'] = determinarStatusRegistro($registro);
    }
    
    jsonResponse(true, 'Registros listados com sucesso', [
        'funcionario' => $funcionario,
        'registros' => array_values($registros_agrupados),
        'periodo' => [
            'inicio' => $data_inicio,
            'fim' => $data_fim
        ]
    ]);
}

/**
 * Lista registros com filtros gerais
 */
function listarRegistros() {
    global $db;
    
    $funcionario_id = $_GET['funcionario_id'] ?? null;
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $data_fim = $_GET['data_fim'] ?? date('Y-m-t');
    $departamento_id = $_GET['departamento_id'] ?? null;
    $apenas_editados = isset($_GET['apenas_editados']) ? (bool) $_GET['apenas_editados'] : false;
    
    $where = ['u.tipo = ?', 'u.ativo = 1'];
    $params = ['funcionario'];
    
    if ($funcionario_id) {
        $where[] = 'p.usuario_id = ?';
        $params[] = $funcionario_id;
    }
    
    if ($departamento_id) {
        $where[] = 'u.departamento_id = ?';
        $params[] = $departamento_id;
    }
    
    if ($apenas_editados) {
        $where[] = 'p.editado = 1';
    }
    
    $where[] = 'p.data BETWEEN ? AND ?';
    $params[] = $data_inicio;
    $params[] = $data_fim;
    
    $whereClause = implode(' AND ', $where);
    
    $stmt = $db->prepare("
        SELECT p.*, u.nome as funcionario_nome, u.cpf, u.matricula,
               d.nome as departamento_nome,
               u_editor.nome as editado_por_nome
        FROM pontos p
        INNER JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        LEFT JOIN usuarios u_editor ON p.editado_por = u_editor.id
        WHERE {$whereClause}
        ORDER BY p.data DESC, u.nome ASC, p.hora ASC
        LIMIT 200
    ");
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(true, 'Registros listados com sucesso', [
        'registros' => $registros,
        'filtros' => [
            'funcionario_id' => $funcionario_id,
            'data_inicio' => $data_inicio,
            'data_fim' => $data_fim,
            'departamento_id' => $departamento_id,
            'apenas_editados' => $apenas_editados
        ]
    ]);
}

function handlePost($input) {
    global $db, $security;
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'inserir_registro':
            return inserirRegistro($input);
            
        default:
            jsonResponse(false, 'Ação não especificada ou inválida');
    }
}

/**
 * Insere um novo registro de ponto manualmente
 */
function inserirRegistro($input) {
    global $db, $security;
    
    // Validar dados obrigatórios
    $funcionario_id = (int) ($input['funcionario_id'] ?? 0);
    $data = $security->sanitizeInput($input['data'] ?? '');
    $hora = $security->sanitizeInput($input['hora'] ?? '');
    $tipo = $security->sanitizeInput($input['tipo'] ?? '');
    $observacao = $security->sanitizeInput($input['observacao'] ?? '');
    
    if (!$funcionario_id || !$data || !$hora || !$tipo) {
        jsonResponse(false, 'Funcionário, data, hora e tipo são obrigatórios');
    }
    
    // Mapear e validar tipo
    $mapeamento_tipos_api = [
        'entrada' => 'entrada_manha',
        'almoco_saida' => 'saida_almoco', 
        'almoco_volta' => 'volta_almoco',
        'saida' => 'saida_tarde'
    ];
    
    if (!isset($mapeamento_tipos_api[$tipo])) {
        jsonResponse(false, 'Tipo de registro inválido');
    }
    
    $tipo_db = $mapeamento_tipos_api[$tipo];
    
    // Validar se funcionário existe e está ativo
    $stmt = $db->prepare("SELECT nome FROM usuarios WHERE id = ? AND tipo = 'funcionario' AND ativo = 1");
    $stmt->execute([$funcionario_id]);
    if (!$stmt->fetch()) {
        jsonResponse(false, 'Funcionário não encontrado ou inativo');
    }
    
    // Verificar se já existe registro do mesmo tipo na mesma data
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM pontos 
        WHERE usuario_id = ? AND data = ? AND tipo = ?
    ");
    $stmt->execute([$funcionario_id, $data, $tipo_db]);
    
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Já existe um registro deste tipo para esta data');
    }
    
    // Validar formato da hora
    if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $hora)) {
        jsonResponse(false, 'Formato de hora inválido (use HH:MM:SS)');
    }
    
    // Inserir registro
    $stmt = $db->prepare("
        INSERT INTO pontos (
            usuario_id, data, hora, tipo, observacao, 
            editado, editado_em, editado_por, created_at
        ) VALUES (?, ?, ?, ?, ?, 1, datetime('now'), ?, datetime('now'))
    ");
    
    if ($stmt->execute([
        $funcionario_id, $data, $hora, $tipo_db, $observacao, $_SESSION['user_id']
    ])) {
        $registro_id = $db->lastInsertId();
        
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'INSERIR_REGISTRO',
            'pontos',
            $registro_id,
            null,
            [
                'funcionario_id' => $funcionario_id,
                'data' => $data,
                'hora' => $hora,
                'tipo' => $tipo,
                'observacao' => $observacao
            ]
        );
        
        jsonResponse(true, 'Registro inserido com sucesso', [
            'registro_id' => $registro_id
        ]);
    } else {
        jsonResponse(false, 'Erro ao inserir registro');
    }
}

function handlePut($input) {
    global $db, $security;
    
    $registro_id = (int) ($input['id'] ?? 0);
    
    if (!$registro_id) {
        jsonResponse(false, 'ID do registro é obrigatório');
    }
    
    // Buscar registro atual
    $stmt = $db->prepare("SELECT * FROM pontos WHERE id = ?");
    $stmt->execute([$registro_id]);
    $registro_atual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registro_atual) {
        jsonResponse(false, 'Registro não encontrado', null, 404);
    }
    
    // Preparar dados para atualização
    $nova_hora = $security->sanitizeInput($input['hora'] ?? $registro_atual['hora']);
    $nova_observacao = $security->sanitizeInput($input['observacao'] ?? $registro_atual['observacao']);
    $justificativa = $security->sanitizeInput($input['justificativa'] ?? '');
    $motivo_ajuste = $security->sanitizeInput($input['motivo_ajuste'] ?? '');
    
    if (empty($justificativa)) {
        jsonResponse(false, 'Justificativa é obrigatória para edição de registro');
    }
    
    if (empty($motivo_ajuste)) {
        jsonResponse(false, 'Motivo do ajuste é obrigatório');
    }
    
    // Validar motivo do ajuste
    $motivos_validos = ['esquecimento', 'erro', 'problema_tecnico', 'justificativa_admin', 'outros'];
    if (!in_array($motivo_ajuste, $motivos_validos)) {
        jsonResponse(false, 'Motivo do ajuste inválido');
    }
    
    // Validar formato da nova hora
    if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $nova_hora)) {
        jsonResponse(false, 'Formato de hora inválido (use HH:MM:SS)');
    }
    
    // Validar sequência de horários se necessário
    if (!validarSequenciaHorariosEdicao($registro_atual['usuario_id'], $registro_atual['data'], 
                                         $registro_atual['tipo'], $nova_hora, $db)) {
        jsonResponse(false, 'Horário inválido: não respeita a sequência lógica dos registros');
    }
    
    // Calcular diferença de tempo em minutos
    $hora_original = $registro_atual['hora'];
    $tempo_ajustado_minutos = calcularDiferencaTempoMinutos($hora_original, $nova_hora);
    
    // Atualizar registro
    $stmt = $db->prepare("
        UPDATE pontos 
        SET hora = ?, observacao = ?, editado = 1, 
            editado_em = datetime('now'), editado_por = ?, motivo_ajuste = ?, tempo_ajustado_minutos = ?
        WHERE id = ?
    ");
    
    if ($stmt->execute([$nova_hora, $nova_observacao, $_SESSION['user_id'], $motivo_ajuste, $tempo_ajustado_minutos, $registro_id])) {
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'EDITAR_REGISTRO',
            'pontos',
            $registro_id,
            $registro_atual,
            [
                'hora_original' => $hora_original,
                'nova_hora' => $nova_hora,
                'tempo_ajustado_minutos' => $tempo_ajustado_minutos,
                'nova_observacao' => $nova_observacao,
                'justificativa' => $justificativa,
                'motivo_ajuste' => $motivo_ajuste
            ]
        );
        
        jsonResponse(true, 'Registro editado com sucesso');
    } else {
        jsonResponse(false, 'Erro ao editar registro');
    }
}

function handleDelete($input) {
    global $db, $security;
    
    $registro_id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    $justificativa = $security->sanitizeInput($input['justificativa'] ?? '');
    
    if (!$registro_id) {
        jsonResponse(false, 'ID do registro é obrigatório');
    }
    
    if (empty($justificativa)) {
        jsonResponse(false, 'Justificativa é obrigatória para exclusão de registro');
    }
    
    // Buscar registro
    $stmt = $db->prepare("SELECT * FROM pontos WHERE id = ?");
    $stmt->execute([$registro_id]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registro) {
        jsonResponse(false, 'Registro não encontrado', null, 404);
    }
    
    // Excluir registro
    $stmt = $db->prepare("DELETE FROM pontos WHERE id = ?");
    
    if ($stmt->execute([$registro_id])) {
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'EXCLUIR_REGISTRO',
            'pontos',
            $registro_id,
            $registro,
            ['justificativa' => $justificativa]
        );
        
        jsonResponse(true, 'Registro excluído com sucesso');
    } else {
        jsonResponse(false, 'Erro ao excluir registro');
    }
}

/**
 * Determina o status de um registro baseado na completude e edições
 */
function determinarStatusRegistro($registro) {
    // Se há justificativa, retornar status baseado na justificativa
    if ($registro['justificativa']) {
        $tipo_codigo = $registro['justificativa']['tipo_codigo'];
        if ($tipo_codigo === 'FER') {
            return 'ferias';
        } elseif ($tipo_codigo === 'ATM') {
            return 'atestado';
        } elseif ($tipo_codigo === 'LIC') {
            return 'licenca';
        } elseif ($tipo_codigo === 'FOL') {
            return 'folga';
        } else {
            return 'justificado';
        }
    }
    
    // Se não há justificativa, usar lógica original
    if ($registro['tem_edicao']) {
        return $registro['completo'] ? 'editado_completo' : 'editado_incompleto';
    } else {
        return $registro['completo'] ? 'completo' : 'incompleto';
    }
}

/**
 * Calcula diferença de tempo entre duas horas em minutos
 */
function calcularDiferencaTempoMinutos($hora_original, $nova_hora) {
    // Converter horas para minutos desde meia-noite
    $minutos_original = tempoParaMinutos($hora_original);
    $minutos_novo = tempoParaMinutos($nova_hora);
    
    // Retornar diferença (positivo = atraso, negativo = adiantamento)
    return $minutos_novo - $minutos_original;
}

/**
 * Converte tempo HH:MM:SS para minutos desde meia-noite
 */
function tempoParaMinutos($tempo) {
    $partes = explode(':', $tempo);
    $horas = (int) $partes[0];
    $minutos = (int) $partes[1];
    $segundos = isset($partes[2]) ? (int) $partes[2] : 0;
    
    return ($horas * 60) + $minutos + ($segundos / 60);
}

/**
 * Valida sequência de horários durante edição
 */
function validarSequenciaHorariosEdicao($funcionario_id, $data, $tipo_editado, $nova_hora, $db) {
    // Buscar todos os registros do dia
    $stmt = $db->prepare("
        SELECT tipo, hora FROM pontos 
        WHERE usuario_id = ? AND data = ? AND tipo != ?
        ORDER BY hora ASC
    ");
    $stmt->execute([$funcionario_id, $data, $tipo_editado]);
    $outros_registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Criar array com todos os horários incluindo o novo
    $horarios = [];
    foreach ($outros_registros as $reg) {
        $horarios[$reg['tipo']] = $reg['hora'];
    }
    $horarios[$tipo_editado] = $nova_hora;
    
    // Definir ordem esperada (tipos do banco)
    $ordem_esperada = ['entrada_manha', 'saida_almoco', 'volta_almoco', 'saida_tarde'];
    
    // Mapear tipo editado de volta para o banco se necessário
    $mapeamento_api_para_db = [
        'entrada' => 'entrada_manha',
        'almoco_saida' => 'saida_almoco',
        'almoco_volta' => 'volta_almoco', 
        'saida' => 'saida_tarde'
    ];
    
    // Se o tipo editado veio da API, converter
    if (isset($mapeamento_api_para_db[$tipo_editado])) {
        $tipo_editado = $mapeamento_api_para_db[$tipo_editado];
    }
    
    $horario_anterior = null;
    foreach ($ordem_esperada as $tipo) {
        if (isset($horarios[$tipo])) {
            if ($horario_anterior && $horarios[$tipo] <= $horario_anterior) {
                return false;
            }
            $horario_anterior = $horarios[$tipo];
        }
    }
    
    return true;
}
?>
