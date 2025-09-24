<?php
/**
 * API para gerenciamento de Justificativas
 * Módulo integrado ao controle de ponto
 */

require_once __DIR__ . '/../config/config.php';

// Verificar se há sessão ativa, se não houver, retornar erro de autenticação
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    jsonResponse(false, 'Acesso restrito a administradores. Faça login primeiro.', null, 401);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$security = $GLOBALS['security'];
$db = $GLOBALS['db'];
$pdo = $db->getConnection();
$userId = $_SESSION['user_id'] ?? 1;

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
        'justificativas',
        null,
        null,
        ['error' => $e->getMessage()]
    );
    
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}

/**
 * Manipula requisições GET
 */
function handleGet() {
    global $pdo, $userId;
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'tipos':
            listarTiposJustificativa();
            break;
        case 'funcionarios':
            listarFuncionarios();
            break;
        case 'listar':
            listarJustificativas();
            break;
        case 'buscar':
            buscarJustificativas();
            break;
        case 'detalhes':
            obterDetalhesJustificativa();
            break;
        case 'historico':
            obterHistoricoJustificativa();
            break;
        case 'relatorio':
            gerarRelatorioJustificativas();
            break;
        default:
            jsonResponse(false, 'Ação não especificada', null, 400);
    }
}

/**
 * Manipula requisições POST
 */
function handlePost($input) {
    global $pdo, $userId;
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'criar':
            criarJustificativa($input);
            break;
        case 'validar':
            validarJustificativa($input);
            break;
        default:
            jsonResponse(false, 'Ação não especificada', null, 400);
    }
}

/**
 * Manipula requisições PUT
 */
function handlePut($input) {
    global $pdo, $userId;
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'editar':
            editarJustificativa($input);
            break;
        case 'cancelar':
            cancelarJustificativa($input);
            break;
        default:
            jsonResponse(false, 'Ação não especificada', null, 400);
    }
}

/**
 * Manipula requisições DELETE
 */
function handleDelete($input) {
    global $pdo, $userId;
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'excluir':
            excluirJustificativa($input);
            break;
        default:
            jsonResponse(false, 'Ação não especificada', null, 400);
    }
}

/**
 * Lista tipos de justificativa disponíveis
 */
function listarTiposJustificativa() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT id, codigo, nome, descricao, abate_falta, bloqueia_ponto, ativo
            FROM tipos_justificativa 
            WHERE ativo = 1 
            ORDER BY nome
        ");
        
        $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(true, 'Tipos carregados com sucesso', ['tipos' => $tipos]);
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao listar tipos: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Lista funcionários para seleção
 */
function listarFuncionarios() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT u.id, u.nome, u.cpf, u.matricula, u.cargo,
                   d.nome as departamento_nome
            FROM usuarios u
            LEFT JOIN departamentos d ON u.departamento_id = d.id
            WHERE u.tipo = 'funcionario' AND u.ativo = 1
            ORDER BY u.nome
        ");
        
        $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(true, 'Funcionários carregados com sucesso', ['funcionarios' => $funcionarios]);
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao listar funcionários: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Lista justificativas com filtros
 */
function listarJustificativas() {
    global $pdo, $userId;
    
    try {
        $funcionarioId = $_GET['funcionario_id'] ?? '';
        $dataInicio = $_GET['data_inicio'] ?? '';
        $dataFim = $_GET['data_fim'] ?? '';
        $status = $_GET['status'] ?? '';
        $tipoId = $_GET['tipo_id'] ?? '';
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        // Construir WHERE clause
        $where = ['1=1'];
        $params = [];
        
        if ($funcionarioId) {
            $where[] = 'j.funcionario_id = ?';
            $params[] = $funcionarioId;
        }
        
        if ($dataInicio) {
            $where[] = 'j.data_inicio >= ?';
            $params[] = $dataInicio;
        }
        
        if ($dataFim) {
            $where[] = '(j.data_fim IS NULL OR j.data_fim <= ?)';
            $params[] = $dataFim;
        }
        
        if ($status) {
            $where[] = 'j.status = ?';
            $params[] = $status;
        }
        
        if ($tipoId) {
            $where[] = 'j.tipo_justificativa_id = ?';
            $params[] = $tipoId;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Query principal
        $sql = "
            SELECT j.*,
                   u.nome as funcionario_nome, u.cpf, u.matricula,
                   tj.codigo as tipo_codigo, tj.nome as tipo_nome,
                   tj.abate_falta, tj.bloqueia_ponto,
                   uc.nome as criado_por_nome,
                   ua.nome as atualizado_por_nome
            FROM justificativas j
            JOIN usuarios u ON j.funcionario_id = u.id
            JOIN tipos_justificativa tj ON j.tipo_justificativa_id = tj.id
            LEFT JOIN usuarios uc ON j.criado_por = uc.id
            LEFT JOIN usuarios ua ON j.atualizado_por = ua.id
            WHERE $whereClause
            ORDER BY j.data_inicio DESC, j.criado_em DESC
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $justificativas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contar total
        $countSql = "
            SELECT COUNT(*) as total
            FROM justificativas j
            WHERE $whereClause
        ";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        jsonResponse(true, 'Justificativas carregadas com sucesso', [
            'justificativas' => $justificativas,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao listar justificativas: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Busca justificativas com filtros avançados
 */
function buscarJustificativas() {
    global $pdo, $userId;
    
    try {
        $funcionarioId = $_GET['funcionario_id'] ?? '';
        $dataInicio = $_GET['data_inicio'] ?? '';
        $dataFim = $_GET['data_fim'] ?? '';
        $periodo = $_GET['periodo'] ?? '';
        
        if (!$funcionarioId || !$dataInicio || !$dataFim) {
            jsonResponse(false, 'Funcionário, data início e data fim são obrigatórios', null, 400);
            return;
        }
        
        $sql = "
            SELECT j.*,
                   tj.codigo as tipo_codigo, tj.nome as tipo_nome,
                   tj.abate_falta, tj.bloqueia_ponto
            FROM justificativas j
            JOIN tipos_justificativa tj ON j.tipo_justificativa_id = tj.id
            WHERE j.funcionario_id = ? 
            AND j.status = 'ativa'
            AND (
                (j.data_inicio <= ? AND (j.data_fim >= ? OR j.data_fim IS NULL))
                OR (j.data_inicio <= ? AND (j.data_fim >= ? OR j.data_fim IS NULL))
            )
        ";
        
        $params = [$funcionarioId, $dataFim, $dataInicio, $dataInicio, $dataFim];
        
        if ($periodo && $periodo !== 'integral') {
            $sql .= " AND (j.periodo_parcial = ? OR j.periodo_parcial = 'integral')";
            $params[] = $periodo;
        }
        
        $sql .= " ORDER BY j.data_inicio, j.periodo_parcial";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $justificativas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(true, 'Justificativas encontradas', ['justificativas' => $justificativas]);
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao buscar justificativas: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Obtém detalhes de uma justificativa
 */
function obterDetalhesJustificativa() {
    global $pdo, $userId;
    
    try {
        $id = $_GET['id'] ?? '';
        
        if (!$id) {
            jsonResponse(false, 'ID da justificativa é obrigatório', null, 400);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT j.*,
                   u.nome as funcionario_nome, u.cpf, u.matricula, u.cargo,
                   d.nome as departamento_nome,
                   tj.codigo as tipo_codigo, tj.nome as tipo_nome,
                   tj.descricao as tipo_descricao, tj.abate_falta, tj.bloqueia_ponto,
                   uc.nome as criado_por_nome,
                   ua.nome as atualizado_por_nome
            FROM justificativas j
            JOIN usuarios u ON j.funcionario_id = u.id
            LEFT JOIN departamentos d ON u.departamento_id = d.id
            JOIN tipos_justificativa tj ON j.tipo_justificativa_id = tj.id
            LEFT JOIN usuarios uc ON j.criado_por = uc.id
            LEFT JOIN usuarios ua ON j.atualizado_por = ua.id
            WHERE j.id = ?
        ");
        
        $stmt->execute([$id]);
        $justificativa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$justificativa) {
            jsonResponse(false, 'Justificativa não encontrada', null, 404);
            return;
        }
        
        jsonResponse(true, 'Detalhes carregados com sucesso', ['justificativa' => $justificativa]);
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao obter detalhes: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Obtém histórico de uma justificativa
 */
function obterHistoricoJustificativa() {
    global $pdo, $userId;
    
    try {
        $id = $_GET['id'] ?? '';
        
        if (!$id) {
            jsonResponse(false, 'ID da justificativa é obrigatório', null, 400);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT jl.*, u.nome as usuario_nome
            FROM justificativas_log jl
            JOIN usuarios u ON jl.usuario_id = u.id
            WHERE jl.justificativa_id = ?
            ORDER BY jl.data_acao DESC
        ");
        
        $stmt->execute([$id]);
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(true, 'Histórico carregado com sucesso', ['historico' => $historico]);
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao obter histórico: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Gera relatório de justificativas
 */
function gerarRelatorioJustificativas() {
    global $pdo, $userId;
    
    try {
        $funcionarioId = $_GET['funcionario_id'] ?? '';
        $dataInicio = $_GET['data_inicio'] ?? '';
        $dataFim = $_GET['data_fim'] ?? '';
        $tipoId = $_GET['tipo_id'] ?? '';
        
        if (!$dataInicio || !$dataFim) {
            jsonResponse(false, 'Data início e data fim são obrigatórios', null, 400);
            return;
        }
        
        // Construir WHERE clause
        $where = ['j.data_inicio >= ?', 'j.data_fim <= ?'];
        $params = [$dataInicio, $dataFim];
        
        if ($funcionarioId) {
            $where[] = 'j.funcionario_id = ?';
            $params[] = $funcionarioId;
        }
        
        if ($tipoId) {
            $where[] = 'j.tipo_justificativa_id = ?';
            $params[] = $tipoId;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "
            SELECT j.*,
                   u.nome as funcionario_nome, u.matricula,
                   d.nome as departamento_nome,
                   tj.codigo as tipo_codigo, tj.nome as tipo_nome
            FROM justificativas j
            JOIN usuarios u ON j.funcionario_id = u.id
            LEFT JOIN departamentos d ON u.departamento_id = d.id
            JOIN tipos_justificativa tj ON j.tipo_justificativa_id = tj.id
            WHERE $whereClause
            ORDER BY u.nome, j.data_inicio
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $justificativas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Estatísticas
        $statsSql = "
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN j.status = 'ativa' THEN 1 END) as ativas,
                COUNT(CASE WHEN j.status = 'cancelada' THEN 1 END) as canceladas,
                COUNT(CASE WHEN j.status = 'expirada' THEN 1 END) as expiradas,
                COUNT(CASE WHEN tj.codigo = 'FER' THEN 1 END) as ferias,
                COUNT(CASE WHEN tj.codigo = 'ATM' THEN 1 END) as atestados,
                COUNT(CASE WHEN tj.codigo = 'AJP' THEN 1 END) as ausencias_parciais,
                COUNT(CASE WHEN tj.codigo = 'LIC' THEN 1 END) as licencas,
                COUNT(CASE WHEN tj.codigo = 'FOL' THEN 1 END) as folgas
            FROM justificativas j
            JOIN tipos_justificativa tj ON j.tipo_justificativa_id = tj.id
            WHERE $whereClause
        ";
        
        $statsStmt = $pdo->prepare($statsSql);
        $statsStmt->execute($params);
        $estatisticas = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse(true, 'Relatório gerado com sucesso', [
            'justificativas' => $justificativas,
            'estatisticas' => $estatisticas,
            'periodo' => [
                'inicio' => $dataInicio,
                'fim' => $dataFim
            ]
        ]);
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao gerar relatório: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Cria nova justificativa
 */
function criarJustificativa($input) {
    global $pdo, $userId;
    
    try {
        // Validar dados obrigatórios
        $required = ['funcionario_id', 'tipo_justificativa_id', 'data_inicio', 'motivo'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                jsonResponse(false, "Campo obrigatório: $field", null, 400);
                return;
            }
        }
        
        // Validar datas
        $dataInicio = $input['data_inicio'];
        $dataFim = $input['data_fim'] ?? null;
        
        if ($dataFim && $dataFim < $dataInicio) {
            jsonResponse(false, 'Data fim não pode ser anterior à data início', null, 400);
            return;
        }
        
        // Verificar conflitos
        $conflitos = verificarConflitosJustificativa($input['funcionario_id'], $dataInicio, $dataFim, $input['periodo_parcial'] ?? 'integral');
        if (!empty($conflitos)) {
            jsonResponse(false, 'Conflito com justificativas existentes', ['conflitos' => $conflitos], 400);
            return;
        }
        
        // Inserir justificativa
        $stmt = $pdo->prepare("
            INSERT INTO justificativas (
                funcionario_id, tipo_justificativa_id, data_inicio, data_fim,
                periodo_parcial, motivo, status, criado_por
            ) VALUES (?, ?, ?, ?, ?, ?, 'ativa', ?)
        ");
        
        $stmt->execute([
            $input['funcionario_id'],
            $input['tipo_justificativa_id'],
            $dataInicio,
            $dataFim,
            $input['periodo_parcial'] ?? 'integral',
            $input['motivo'],
            $userId
        ]);
        
        $justificativaId = $pdo->lastInsertId();
        
        // Log de auditoria
        logAuditoriaJustificativa($justificativaId, 'criada', null, $input, $userId);
        
        jsonResponse(true, 'Justificativa criada com sucesso', ['id' => $justificativaId]);
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao criar justificativa: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Valida justificativa antes de criar
 */
function validarJustificativa($input) {
    global $pdo, $userId;
    
    try {
        $errors = [];
        
        // Validar dados obrigatórios
        $required = ['funcionario_id', 'tipo_justificativa_id', 'data_inicio', 'motivo'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $errors[] = "Campo obrigatório: $field";
            }
        }
        
        if (!empty($errors)) {
            jsonResponse(false, 'Dados inválidos', ['errors' => $errors], 400);
            return;
        }
        
        // Validar datas
        $dataInicio = $input['data_inicio'];
        $dataFim = $input['data_fim'] ?? null;
        
        if ($dataFim && $dataFim < $dataInicio) {
            $errors[] = 'Data fim não pode ser anterior à data início';
        }
        
        // Verificar conflitos
        $conflitos = verificarConflitosJustificativa($input['funcionario_id'], $dataInicio, $dataFim, $input['periodo_parcial'] ?? 'integral');
        if (!empty($conflitos)) {
            $errors[] = 'Conflito com justificativas existentes';
        }
        
        jsonResponse(empty($errors), empty($errors) ? 'Justificativa válida' : 'Justificativa inválida', [
            'errors' => $errors,
            'conflitos' => $conflitos ?? []
        ]);
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao validar justificativa: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Edita justificativa existente
 */
function editarJustificativa($input) {
    global $pdo, $userId;
    
    try {
        $id = $input['id'] ?? '';
        
        if (!$id) {
            jsonResponse(false, 'ID da justificativa é obrigatório', null, 400);
            return;
        }
        
        // Buscar justificativa atual
        $stmt = $pdo->prepare("SELECT * FROM justificativas WHERE id = ?");
        $stmt->execute([$id]);
        $justificativaAtual = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$justificativaAtual) {
            jsonResponse(false, 'Justificativa não encontrada', null, 404);
            return;
        }
        
        if ($justificativaAtual['status'] !== 'ativa') {
            jsonResponse(false, 'Apenas justificativas ativas podem ser editadas', null, 400);
            return;
        }
        
        // Validar dados
        $required = ['funcionario_id', 'tipo_justificativa_id', 'data_inicio', 'motivo'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                jsonResponse(false, "Campo obrigatório: $field", null, 400);
                return;
            }
        }
        
        // Verificar conflitos (excluindo a própria justificativa)
        $conflitos = verificarConflitosJustificativa($input['funcionario_id'], $input['data_inicio'], $input['data_fim'] ?? null, $input['periodo_parcial'] ?? 'integral', $id);
        if (!empty($conflitos)) {
            jsonResponse(false, 'Conflito com justificativas existentes', ['conflitos' => $conflitos], 400);
            return;
        }
        
        // Atualizar justificativa
        $stmt = $pdo->prepare("
            UPDATE justificativas SET
                funcionario_id = ?, tipo_justificativa_id = ?, data_inicio = ?, data_fim = ?,
                periodo_parcial = ?, motivo = ?, atualizado_por = ?, atualizado_em = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            $input['funcionario_id'],
            $input['tipo_justificativa_id'],
            $input['data_inicio'],
            $input['data_fim'] ?? null,
            $input['periodo_parcial'] ?? 'integral',
            $input['motivo'],
            $userId,
            $id
        ]);
        
        // Log de auditoria
        logAuditoriaJustificativa($id, 'editada', $justificativaAtual, $input, $userId);
        
        jsonResponse(true, 'Justificativa atualizada com sucesso');
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao editar justificativa: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Cancela justificativa
 */
function cancelarJustificativa($input) {
    global $pdo, $userId;
    
    try {
        $id = $input['id'] ?? '';
        $motivo = $input['motivo'] ?? 'Cancelada pelo administrador';
        
        if (!$id) {
            jsonResponse(false, 'ID da justificativa é obrigatório', null, 400);
            return;
        }
        
        // Buscar justificativa
        $stmt = $pdo->prepare("SELECT * FROM justificativas WHERE id = ?");
        $stmt->execute([$id]);
        $justificativa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$justificativa) {
            jsonResponse(false, 'Justificativa não encontrada', null, 404);
            return;
        }
        
        if ($justificativa['status'] !== 'ativa') {
            jsonResponse(false, 'Apenas justificativas ativas podem ser canceladas', null, 400);
            return;
        }
        
        // Cancelar justificativa
        $stmt = $pdo->prepare("
            UPDATE justificativas SET
                status = 'cancelada', motivo = ?, atualizado_por = ?, atualizado_em = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([$motivo, $userId, $id]);
        
        // Log de auditoria
        $dadosNovos = $justificativa;
        $dadosNovos['status'] = 'cancelada';
        $dadosNovos['motivo'] = $motivo;
        
        logAuditoriaJustificativa($id, 'cancelada', $justificativa, $dadosNovos, $userId);
        
        jsonResponse(true, 'Justificativa cancelada com sucesso');
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao cancelar justificativa: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Exclui justificativa
 */
function excluirJustificativa($input) {
    global $pdo, $userId;
    
    try {
        $id = $input['id'] ?? '';
        
        if (!$id) {
            jsonResponse(false, 'ID da justificativa é obrigatório', null, 400);
            return;
        }
        
        // Buscar justificativa
        $stmt = $pdo->prepare("SELECT * FROM justificativas WHERE id = ?");
        $stmt->execute([$id]);
        $justificativa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$justificativa) {
            jsonResponse(false, 'Justificativa não encontrada', null, 404);
            return;
        }
        
        // Verificar se pode ser excluída (apenas canceladas ou expiradas)
        if ($justificativa['status'] === 'ativa') {
            jsonResponse(false, 'Apenas justificativas canceladas ou expiradas podem ser excluídas', null, 400);
            return;
        }
        
        // Excluir justificativa
        $stmt = $pdo->prepare("DELETE FROM justificativas WHERE id = ?");
        $stmt->execute([$id]);
        
        // Log de auditoria
        logAuditoriaJustificativa($id, 'excluida', $justificativa, null, $userId);
        
        jsonResponse(true, 'Justificativa excluída com sucesso');
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao excluir justificativa: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Verifica conflitos de justificativas
 */
function verificarConflitosJustificativa($funcionarioId, $dataInicio, $dataFim, $periodoParcial, $excluirId = null) {
    global $pdo;
    
    try {
        $where = ['funcionario_id = ?', 'status = ?'];
        $params = [$funcionarioId, 'ativa'];
        
        if ($excluirId) {
            $where[] = 'id != ?';
            $params[] = $excluirId;
        }
        
        // Verificar sobreposição de datas
        if ($dataFim) {
            $where[] = '(data_inicio <= ? AND (data_fim >= ? OR data_fim IS NULL))';
            $params[] = $dataFim;
            $params[] = $dataInicio;
        } else {
            $where[] = '(data_inicio <= ? AND (data_fim >= ? OR data_fim IS NULL))';
            $params[] = $dataInicio;
            $params[] = $dataInicio;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "
            SELECT j.*, tj.nome as tipo_nome, tj.codigo as tipo_codigo
            FROM justificativas j
            JOIN tipos_justificativa tj ON j.tipo_justificativa_id = tj.id
            WHERE $whereClause
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $conflitos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Filtrar conflitos por período parcial
        $conflitosFiltrados = [];
        foreach ($conflitos as $conflito) {
            if ($periodoParcial === 'integral' || $conflito['periodo_parcial'] === 'integral') {
                $conflitosFiltrados[] = $conflito;
            } elseif ($periodoParcial === $conflito['periodo_parcial']) {
                $conflitosFiltrados[] = $conflito;
            }
        }
        
        return $conflitosFiltrados;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Registra log de auditoria para justificativas
 */
function logAuditoriaJustificativa($justificativaId, $acao, $dadosAnteriores, $dadosNovos, $usuarioId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO justificativas_log (
                justificativa_id, acao, dados_anteriores, dados_novos, usuario_id, ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $justificativaId,
            $acao,
            $dadosAnteriores ? json_encode($dadosAnteriores) : null,
            $dadosNovos ? json_encode($dadosNovos) : null,
            $usuarioId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Log silencioso - não deve interromper o fluxo principal
        error_log("Erro ao registrar log de auditoria: " . $e->getMessage());
    }
}
?>