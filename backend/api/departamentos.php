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
        'departamentos',
        null,
        null,
        ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
    );
    
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}

function handleGet() {
    global $db, $security;
    
    if (isset($_GET['id'])) {
        // Buscar departamento específico
        $id = (int) $_GET['id'];
        $stmt = $db->prepare("
            SELECT d.*, 
                   (SELECT COUNT(*) FROM usuarios WHERE departamento_id = d.id AND ativo = 1) as total_funcionarios
            FROM departamentos d 
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $departamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$departamento) {
            jsonResponse(false, 'Departamento não encontrado', null, 404);
        }
        
        jsonResponse(true, 'Departamento encontrado', ['departamento' => $departamento]);
        
    } else {
        // Listar todos os departamentos
        $stmt = $db->prepare("
            SELECT d.*, 
                   (SELECT COUNT(*) FROM usuarios WHERE departamento_id = d.id AND ativo = 1) as total_funcionarios
            FROM departamentos d 
            ORDER BY d.nome ASC
        ");
        $stmt->execute();
        $departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(true, 'Departamentos listados com sucesso', ['departamentos' => $departamentos]);
    }
}

function handlePost($input) {
    global $db, $security;
    
    // Validar dados obrigatórios
    $nome = $security->sanitizeInput($input['nome'] ?? '');
    $codigo = strtoupper($security->sanitizeInput($input['codigo'] ?? ''));
    $descricao = $security->sanitizeInput($input['descricao'] ?? '');
    
    if (empty($nome)) {
        jsonResponse(false, 'Nome é obrigatório');
    }
    
    if (empty($codigo)) {
        jsonResponse(false, 'Código é obrigatório');
    }
    
    // Validar formato do código (apenas letras e números)
    if (!preg_match('/^[A-Z0-9]{2,10}$/', $codigo)) {
        jsonResponse(false, 'Código deve ter entre 2 e 10 caracteres (apenas letras e números)');
    }
    
    // Verificar unicidade do nome
    $stmt = $db->prepare("SELECT COUNT(*) FROM departamentos WHERE UPPER(nome) = UPPER(?)");
    $stmt->execute([$nome]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Já existe um departamento com este nome');
    }
    
    // Verificar unicidade do código
    $stmt = $db->prepare("SELECT COUNT(*) FROM departamentos WHERE codigo = ?");
    $stmt->execute([$codigo]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Já existe um departamento com este código');
    }
    
    // Inserir departamento
    $stmt = $db->prepare("
        INSERT INTO departamentos (nome, codigo, descricao, created_at, updated_at) 
        VALUES (?, ?, ?, datetime('now'), datetime('now'))
    ");
    
    if ($stmt->execute([$nome, $codigo, $descricao])) {
        $departamentoId = $db->lastInsertId();
        
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'CRIAR',
            'departamentos',
            $departamentoId,
            null,
            ['nome' => $nome, 'codigo' => $codigo, 'descricao' => $descricao]
        );
        
        // Buscar departamento criado para retorno
        $stmt = $db->prepare("SELECT * FROM departamentos WHERE id = ?");
        $stmt->execute([$departamentoId]);
        $departamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse(true, 'Departamento criado com sucesso', [
            'departamento' => $departamento
        ]);
    } else {
        jsonResponse(false, 'Erro ao criar departamento');
    }
}

function handlePut($input) {
    global $db, $security;
    
    $id = (int) ($input['id'] ?? 0);
    
    if (!$id) {
        jsonResponse(false, 'ID do departamento é obrigatório');
    }
    
    // Buscar dados atuais para auditoria
    $stmt = $db->prepare("SELECT * FROM departamentos WHERE id = ?");
    $stmt->execute([$id]);
    $dadosAntigos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dadosAntigos) {
        jsonResponse(false, 'Departamento não encontrado', null, 404);
    }
    
    // Validar dados de entrada
    $nome = $security->sanitizeInput($input['nome'] ?? $dadosAntigos['nome']);
    $codigo = strtoupper($security->sanitizeInput($input['codigo'] ?? $dadosAntigos['codigo']));
    $descricao = $security->sanitizeInput($input['descricao'] ?? $dadosAntigos['descricao']);
    $ativo = isset($input['ativo']) ? (bool) $input['ativo'] : (bool) $dadosAntigos['ativo'];
    
    if (empty($nome)) {
        jsonResponse(false, 'Nome é obrigatório');
    }
    
    if (empty($codigo)) {
        jsonResponse(false, 'Código é obrigatório');
    }
    
    // Validar formato do código
    if (!preg_match('/^[A-Z0-9]{2,10}$/', $codigo)) {
        jsonResponse(false, 'Código deve ter entre 2 e 10 caracteres (apenas letras e números)');
    }
    
    // Verificar unicidade do nome (exceto para o próprio registro)
    $stmt = $db->prepare("SELECT COUNT(*) FROM departamentos WHERE UPPER(nome) = UPPER(?) AND id != ?");
    $stmt->execute([$nome, $id]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Já existe outro departamento com este nome');
    }
    
    // Verificar unicidade do código (exceto para o próprio registro)
    $stmt = $db->prepare("SELECT COUNT(*) FROM departamentos WHERE codigo = ? AND id != ?");
    $stmt->execute([$codigo, $id]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Já existe outro departamento com este código');
    }
    
    // Verificar se pode desativar (não pode ter funcionários ativos)
    if (!$ativo) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE departamento_id = ? AND ativo = 1");
        $stmt->execute([$id]);
        $funcionariosAtivos = $stmt->fetchColumn();
        
        if ($funcionariosAtivos > 0) {
            jsonResponse(false, "Não é possível desativar. Existem {$funcionariosAtivos} funcionário(s) ativo(s) neste departamento.");
        }
    }
    
    // Atualizar departamento
    $stmt = $db->prepare("
        UPDATE departamentos 
        SET nome = ?, codigo = ?, descricao = ?, ativo = ?, updated_at = datetime('now')
        WHERE id = ?
    ");
    
    if ($stmt->execute([$nome, $codigo, $descricao, $ativo ? 1 : 0, $id])) {
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'ATUALIZAR',
            'departamentos',
            $id,
            $dadosAntigos,
            ['nome' => $nome, 'codigo' => $codigo, 'descricao' => $descricao, 'ativo' => $ativo]
        );
        
        // Buscar departamento atualizado para retorno
        $stmt = $db->prepare("
            SELECT d.*, 
                   (SELECT COUNT(*) FROM usuarios WHERE departamento_id = d.id AND ativo = 1) as total_funcionarios
            FROM departamentos d 
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $departamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse(true, 'Departamento atualizado com sucesso', [
            'departamento' => $departamento
        ]);
    } else {
        jsonResponse(false, 'Erro ao atualizar departamento');
    }
}

function handleDelete($input) {
    global $db, $security;
    
    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    
    if (!$id) {
        jsonResponse(false, 'ID do departamento é obrigatório');
    }
    
    // Buscar dados atuais para auditoria
    $stmt = $db->prepare("SELECT * FROM departamentos WHERE id = ?");
    $stmt->execute([$id]);
    $departamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$departamento) {
        jsonResponse(false, 'Departamento não encontrado', null, 404);
    }
    
    // Verificar se pode excluir (não pode ter funcionários)
    $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE departamento_id = ?");
    $stmt->execute([$id]);
    $totalFuncionarios = $stmt->fetchColumn();
    
    if ($totalFuncionarios > 0) {
        jsonResponse(false, "Não é possível excluir. Existem {$totalFuncionarios} funcionário(s) vinculado(s) a este departamento. Desative o departamento ou mova os funcionários.");
    }
    
    // Excluir departamento
    $stmt = $db->prepare("DELETE FROM departamentos WHERE id = ?");
    
    if ($stmt->execute([$id])) {
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'EXCLUIR',
            'departamentos',
            $id,
            $departamento,
            null
        );
        
        jsonResponse(true, 'Departamento excluído com sucesso');
    } else {
        jsonResponse(false, 'Erro ao excluir departamento');
    }
}
?>