<?php
require_once '../config/config.php';

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$security = $GLOBALS['security'];
$db = $GLOBALS['db'];

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
        'usuarios',
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
            case 'funcionario':
                // Buscar funcionário por CPF ou matrícula
                $identificador = $_GET['id'] ?? '';
                if (empty($identificador)) {
                    jsonResponse(false, 'CPF ou matrícula é obrigatório');
                }
                
                $stmt = $db->prepare("
                    SELECT u.id, u.nome, u.cpf, u.matricula, u.cargo, u.pin_reset,
                           d.nome as departamento_nome, gj.nome as grupo_jornada_nome,
                           gj.tolerancia_minutos, gj.carga_diaria_minutos
                    FROM usuarios u
                    LEFT JOIN departamentos d ON u.departamento_id = d.id
                    LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
                    WHERE (u.cpf = ? OR u.matricula = ?) 
                    AND u.tipo = 'funcionario' AND u.ativo = 1
                ");
                $stmt->execute([$identificador, $identificador]);
                $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$funcionario) {
                    jsonResponse(false, 'Funcionário não encontrado ou inativo');
                }
                
                jsonResponse(true, 'Funcionário encontrado', ['funcionario' => $funcionario]);
                break;
                
            default:
                jsonResponse(false, 'Ação não encontrada');
        }
    } elseif (isset($_GET['id'])) {
        // Buscar usuário específico
        $id = (int) $_GET['id'];
        $stmt = $db->prepare("
            SELECT u.*, d.nome as departamento_nome, gj.nome as grupo_jornada_nome,
                   gj.tolerancia_minutos, gj.carga_diaria_minutos,
                   (SELECT COUNT(*) FROM pontos WHERE usuario_id = u.id) as total_pontos
            FROM usuarios u
            LEFT JOIN departamentos d ON u.departamento_id = d.id
            LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            jsonResponse(false, 'Usuário não encontrado', null, 404);
        }
        
        // Remover dados sensíveis
        unset($usuario['senha'], $usuario['pin']);
        
        // Buscar overrides ativos
        $stmt = $db->prepare("
            SELECT * FROM usuario_jornada_override
            WHERE usuario_id = ? AND ativo = 1
            ORDER BY data_inicio DESC
        ");
        $stmt->execute([$id]);
        $overrides = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $usuario['overrides'] = $overrides;
        
        jsonResponse(true, 'Usuário encontrado', ['usuario' => $usuario]);
        
    } else {
        // Listar todos os usuários
        $tipo = $_GET['tipo'] ?? 'all';
        $where = '';
        $params = [];
        
        if ($tipo !== 'all') {
            $where = 'WHERE u.tipo = ?';
            $params[] = $tipo;
        }
        
        $stmt = $db->prepare("
            SELECT u.id, u.nome, u.cpf, u.matricula, u.login, u.tipo, u.cargo, u.ativo,
                   u.pin_reset, u.created_at, u.updated_at,
                   d.nome as departamento_nome, gj.nome as grupo_jornada_nome,
                   gj.tolerancia_minutos, gj.carga_diaria_minutos,
                   (SELECT COUNT(*) FROM pontos WHERE usuario_id = u.id) as total_pontos
            FROM usuarios u
            LEFT JOIN departamentos d ON u.departamento_id = d.id
            LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
            $where
            ORDER BY u.nome ASC
        ");
        $stmt->execute($params);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(true, 'Usuários listados com sucesso', ['usuarios' => $usuarios]);
    }
}

function handlePost($input) {
    global $security;
    $database = $GLOBALS['db'];
    
    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'autenticar_funcionario':
                return autenticarFuncionario($input, $security, $database);
                
            case 'definir_pin':
                return definirPin($input, $security, $database);
                
            case 'reset_pin':
                return resetPin($input, $security, $database);
                
            case 'criar_override':
                return criarOverrideJornada($input, $security, $database);
                
            default:
                // Criar usuário normal
                return criarUsuario($input, $security, $database);
        }
    } else {
        return criarUsuario($input, $security, $database);
    }
}

function criarUsuario($input, $security, $database) {
    $db = $database->getConnection();
    
    // Sanitizar dados
    $nome = $security->sanitizeInput($input['nome'] ?? '');
    $cpf = preg_replace('/[^0-9]/', '', $input['cpf'] ?? '');
    $matricula = $security->sanitizeInput($input['matricula'] ?? '');
    $login = $security->sanitizeInput($input['login'] ?? '');
    $cargo = $security->sanitizeInput($input['cargo'] ?? '');
    $tipo = $security->sanitizeInput($input['tipo'] ?? 'funcionario');
    $departamento_id = (int) ($input['departamento_id'] ?? 0);
    $grupo_jornada_id = (int) ($input['grupo_jornada_id'] ?? 0);
    $senha = $input['senha'] ?? '';
    
    // Validações básicas
    if (empty($nome)) {
        jsonResponse(false, 'Nome é obrigatório');
    }
    
    if ($tipo === 'funcionario') {
        // Para funcionários, CPF ou matrícula é obrigatório
        if (empty($cpf) && empty($matricula)) {
            jsonResponse(false, 'CPF ou matrícula é obrigatório para funcionários');
        }
        
        if (!empty($cpf) && !$security->validateCPF($cpf)) {
            jsonResponse(false, 'CPF inválido');
        }
        
        if (!$departamento_id || !$grupo_jornada_id) {
            jsonResponse(false, 'Departamento e grupo de jornada são obrigatórios para funcionários');
        }
    } else {
        // Para admins, login e senha são obrigatórios
        if (empty($login) || empty($senha)) {
            jsonResponse(false, 'Login e senha são obrigatórios para administradores');
        }
        
        // Validar força da senha admin
        $validacao = $security->validateAdminPassword($senha);
        if (!$validacao['valid']) {
            jsonResponse(false, 'Senha fraca: ' . implode(', ', $validacao['errors']));
        }
    }
    
    // Verificar unicidade
    if (!empty($cpf)) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE cpf = ?");
        $stmt->execute([$cpf]);
        if ($stmt->fetchColumn() > 0) {
            jsonResponse(false, 'CPF já cadastrado');
        }
    }
    
    if (!empty($matricula)) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE matricula = ?");
        $stmt->execute([$matricula]);
        if ($stmt->fetchColumn() > 0) {
            jsonResponse(false, 'Matrícula já cadastrada');
        }
    }
    
    if (!empty($login)) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE login = ?");
        $stmt->execute([$login]);
        if ($stmt->fetchColumn() > 0) {
            jsonResponse(false, 'Login já cadastrado');
        }
    }
    
    // Verificar se departamento e grupo existem
    if ($departamento_id) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM departamentos WHERE id = ? AND ativo = 1");
        $stmt->execute([$departamento_id]);
        if ($stmt->fetchColumn() == 0) {
            jsonResponse(false, 'Departamento não encontrado ou inativo');
        }
    }
    
    if ($grupo_jornada_id) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM grupos_jornada WHERE id = ? AND ativo = 1");
        $stmt->execute([$grupo_jornada_id]);
        if ($stmt->fetchColumn() == 0) {
            jsonResponse(false, 'Grupo de jornada não encontrado ou inativo');
        }
    }
    
    // Preparar dados para inserção
    $senhaHash = null;
    if (!empty($senha)) {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    }
    
    // Inserir usuário
    $stmt = $db->prepare("
        INSERT INTO usuarios (
            nome, cpf, matricula, login, senha, tipo, cargo, 
            departamento_id, grupo_jornada_id, pin_reset,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
    ");
    
    if ($stmt->execute([
        $nome, 
        !empty($cpf) ? $cpf : null,
        !empty($matricula) ? $matricula : null,
        !empty($login) ? $login : null,
        $senhaHash,
        $tipo, 
        $cargo,
        $departamento_id > 0 ? $departamento_id : null,
        $grupo_jornada_id > 0 ? $grupo_jornada_id : null,
        $tipo === 'funcionario' ? 1 : 0  // Funcionários precisam definir PIN
    ])) {
        $usuarioId = $db->lastInsertId();
        
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'CRIAR',
            'usuarios',
            $usuarioId,
            null,
            [
                'nome' => $nome,
                'tipo' => $tipo,
                'cpf' => $cpf,
                'matricula' => $matricula,
                'cargo' => $cargo
            ]
        );
        
        jsonResponse(true, 'Usuário criado com sucesso', ['usuario_id' => $usuarioId]);
    } else {
        jsonResponse(false, 'Erro ao criar usuário');
    }
}

function autenticarFuncionario($input, $security, $database) {
    $db = $database->getConnection();
    $identificador = $security->sanitizeInput($input['identificador'] ?? '');
    $pin = $security->sanitizeInput($input['pin'] ?? '');
    $ip = $security->getClientIP();
    
    if (empty($identificador) || empty($pin)) {
        jsonResponse(false, 'CPF/Matrícula e PIN são obrigatórios');
    }
    
    // Rate limiting
    if (!$security->checkRateLimit($ip, $identificador, 'funcionario', 3, 300)) {
        jsonResponse(false, 'Muitas tentativas. Tente novamente em 5 minutos.', null, 429);
    }
    
    // Buscar funcionário
    $stmt = $db->prepare("
        SELECT id, nome, pin, pin_reset, ativo
        FROM usuarios 
        WHERE (cpf = ? OR matricula = ?) AND tipo = 'funcionario'
    ");
    $stmt->execute([$identificador, $identificador]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $sucesso = false;
    
    if ($funcionario && $funcionario['ativo']) {
        if ($funcionario['pin_reset']) {
            // Funcionário precisa definir novo PIN
            jsonResponse(false, 'Primeiro acesso. Defina seu PIN de 4 dígitos.', ['pin_reset' => true]);
        }
        
        if ($funcionario['pin'] && $security->verifyPin($pin, $funcionario['pin'])) {
            $sucesso = true;
            
            // Criar sessão do funcionário
            $_SESSION['funcionario_id'] = $funcionario['id'];
            $_SESSION['funcionario_nome'] = $funcionario['nome'];
            $_SESSION['funcionario_auth_time'] = time();
            
            // Log de auditoria
            $security->logAudit(
                $funcionario['id'],
                'LOGIN_FUNCIONARIO',
                'usuarios',
                $funcionario['id'],
                null,
                ['identificador' => $identificador]
            );
            
            jsonResponse(true, 'Autenticação realizada com sucesso', [
                'funcionario' => [
                    'id' => $funcionario['id'],
                    'nome' => $funcionario['nome']
                ]
            ]);
        }
    }
    
    // Registrar tentativa
    $security->logLoginAttempt($ip, $identificador, 'funcionario', $sucesso);
    
    if (!$sucesso) {
        jsonResponse(false, 'CPF/Matrícula ou PIN incorretos');
    }
}

function definirPin($input, $security, $database) {
    $db = $database->getConnection();
    $identificador = $security->sanitizeInput($input['identificador'] ?? '');
    $pin = $security->sanitizeInput($input['pin'] ?? '');
    $confirmar_pin = $security->sanitizeInput($input['confirmar_pin'] ?? '');
    
    if (empty($identificador) || empty($pin) || empty($confirmar_pin)) {
        jsonResponse(false, 'Todos os campos são obrigatórios');
    }
    
    if ($pin !== $confirmar_pin) {
        jsonResponse(false, 'PINs não coincidem');
    }
    
    // Validar PIN
    $validacao = $security->validatePin($pin);
    if (!$validacao['valid']) {
        jsonResponse(false, $validacao['message']);
    }
    
    // Buscar funcionário
    $stmt = $db->prepare("
        SELECT id, nome FROM usuarios 
        WHERE (cpf = ? OR matricula = ?) AND tipo = 'funcionario' AND pin_reset = 1
    ");
    $stmt->execute([$identificador, $identificador]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$funcionario) {
        jsonResponse(false, 'Funcionário não encontrado ou PIN já definido');
    }
    
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
}

function resetPin($input, $security, $database) {
    $db = $database->getConnection();
    $usuario_id = (int) ($input['usuario_id'] ?? 0);
    
    if (!$usuario_id) {
        jsonResponse(false, 'ID do usuário é obrigatório');
    }
    
    // Verificar se usuário existe e é funcionário
    $stmt = $db->prepare("SELECT nome, tipo FROM usuarios WHERE id = ? AND tipo = 'funcionario'");
    $stmt->execute([$usuario_id]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$funcionario) {
        jsonResponse(false, 'Funcionário não encontrado');
    }
    
    // Reset do PIN
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
            ['funcionario' => $funcionario['nome']]
        );
        
        jsonResponse(true, 'PIN resetado. Funcionário deve definir novo PIN no próximo acesso.');
    } else {
        jsonResponse(false, 'Erro ao resetar PIN');
    }
}

function criarOverrideJornada($input, $security, $database) {
    $db = $database->getConnection();
    $usuario_id = (int) ($input['usuario_id'] ?? 0);
    $motivo = $security->sanitizeInput($input['motivo'] ?? '');
    $data_inicio = $security->sanitizeInput($input['data_inicio'] ?? '');
    $data_fim = $security->sanitizeInput($input['data_fim'] ?? '');
    
    if (!$usuario_id || empty($motivo) || empty($data_inicio)) {
        jsonResponse(false, 'Usuário, motivo e data de início são obrigatórios');
    }
    
    // Verificar se usuário existe
    $stmt = $db->prepare("SELECT nome FROM usuarios WHERE id = ? AND tipo = 'funcionario'");
    $stmt->execute([$usuario_id]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$funcionario) {
        jsonResponse(false, 'Funcionário não encontrado');
    }
    
    // Desativar overrides existentes que possam conflitar
    if (!empty($data_fim)) {
        $stmt = $db->prepare("
            UPDATE usuario_jornada_override 
            SET ativo = 0 
            WHERE usuario_id = ? AND ativo = 1 
            AND ((data_inicio <= ? AND (data_fim IS NULL OR data_fim >= ?))
                 OR (data_inicio <= ? AND (data_fim IS NULL OR data_fim >= ?)))
        ");
        $stmt->execute([$usuario_id, $data_inicio, $data_inicio, $data_fim, $data_fim]);
    } else {
        // Override indefinido, desativar todos os outros
        $stmt = $db->prepare("
            UPDATE usuario_jornada_override 
            SET ativo = 0 
            WHERE usuario_id = ? AND ativo = 1
        ");
        $stmt->execute([$usuario_id]);
    }
    
    // Preparar dados do override (usar valores do input ou manter padrão)
    $override_data = [
        'usuario_id' => $usuario_id,
        'motivo' => $motivo,
        'data_inicio' => $data_inicio,
        'data_fim' => !empty($data_fim) ? $data_fim : null,
        'entrada_manha' => $input['entrada_manha'] ?? null,
        'saida_almoco' => $input['saida_almoco'] ?? null,
        'volta_almoco' => $input['volta_almoco'] ?? null,
        'saida_tarde' => $input['saida_tarde'] ?? null,
        'carga_diaria_minutos' => (int) ($input['carga_diaria_minutos'] ?? 0),
        'tolerancia_minutos' => (int) ($input['tolerancia_minutos'] ?? 0),
        'created_by' => $_SESSION['user_id']
    ];
    
    // Inserir override
    $stmt = $db->prepare("
        INSERT INTO usuario_jornada_override (
            usuario_id, motivo, data_inicio, data_fim,
            entrada_manha, saida_almoco, volta_almoco, saida_tarde,
            carga_diaria_minutos, tolerancia_minutos, created_by,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
    ");
    
    if ($stmt->execute([
        $override_data['usuario_id'],
        $override_data['motivo'],
        $override_data['data_inicio'],
        $override_data['data_fim'],
        $override_data['entrada_manha'],
        $override_data['saida_almoco'],
        $override_data['volta_almoco'],
        $override_data['saida_tarde'],
        $override_data['carga_diaria_minutos'],
        $override_data['tolerancia_minutos'],
        $override_data['created_by']
    ])) {
        $overrideId = $db->lastInsertId();
        
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'CRIAR_OVERRIDE_JORNADA',
            'usuario_jornada_override',
            $overrideId,
            null,
            $override_data
        );
        
        jsonResponse(true, 'Override de jornada criado com sucesso', ['override_id' => $overrideId]);
    } else {
        jsonResponse(false, 'Erro ao criar override de jornada');
    }
}

function handlePut($input) {
    global $security;
    $db = $GLOBALS['db']->getConnection();
    
    $id = (int) ($input['id'] ?? 0);
    
    if (!$id) {
        jsonResponse(false, 'ID do usuário é obrigatório');
    }
    
    // Buscar dados atuais
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $dadosAntigos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dadosAntigos) {
        jsonResponse(false, 'Usuário não encontrado', null, 404);
    }
    
    // Preparar dados para atualização (manter valores atuais se não fornecidos)
    $nome = $security->sanitizeInput($input['nome'] ?? $dadosAntigos['nome']);
    $cpf = !empty($input['cpf']) ? preg_replace('/[^0-9]/', '', $input['cpf']) : $dadosAntigos['cpf'];
    $matricula = $security->sanitizeInput($input['matricula'] ?? $dadosAntigos['matricula']);
    $login = $security->sanitizeInput($input['login'] ?? $dadosAntigos['login']);
    $cargo = $security->sanitizeInput($input['cargo'] ?? $dadosAntigos['cargo']);
    $departamento_id = isset($input['departamento_id']) ? (int) $input['departamento_id'] : $dadosAntigos['departamento_id'];
    $grupo_jornada_id = isset($input['grupo_jornada_id']) ? (int) $input['grupo_jornada_id'] : $dadosAntigos['grupo_jornada_id'];
    $ativo = isset($input['ativo']) ? (bool) $input['ativo'] : (bool) $dadosAntigos['ativo'];
    
    // Validações
    if (empty($nome)) {
        jsonResponse(false, 'Nome é obrigatório');
    }
    
    if (!empty($cpf) && !$security->validateCPF($cpf)) {
        jsonResponse(false, 'CPF inválido');
    }
    
    // Verificar unicidade (exceto próprio registro)
    if (!empty($cpf) && $cpf !== $dadosAntigos['cpf']) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE cpf = ? AND id != ?");
        $stmt->execute([$cpf, $id]);
        if ($stmt->fetchColumn() > 0) {
            jsonResponse(false, 'CPF já cadastrado');
        }
    }
    
    if (!empty($matricula) && $matricula !== $dadosAntigos['matricula']) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE matricula = ? AND id != ?");
        $stmt->execute([$matricula, $id]);
        if ($stmt->fetchColumn() > 0) {
            jsonResponse(false, 'Matrícula já cadastrada');
        }
    }
    
    if (!empty($login) && $login !== $dadosAntigos['login']) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE login = ? AND id != ?");
        $stmt->execute([$login, $id]);
        if ($stmt->fetchColumn() > 0) {
            jsonResponse(false, 'Login já cadastrado');
        }
    }
    
    // Atualizar usuário
    $stmt = $db->prepare("
        UPDATE usuarios 
        SET nome = ?, cpf = ?, matricula = ?, login = ?, cargo = ?,
            departamento_id = ?, grupo_jornada_id = ?, ativo = ?,
            updated_at = datetime('now')
        WHERE id = ?
    ");
    
    if ($stmt->execute([
        $nome, $cpf, $matricula, $login, $cargo,
        $departamento_id, $grupo_jornada_id, $ativo ? 1 : 0, $id
    ])) {
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'ATUALIZAR',
            'usuarios',
            $id,
            $dadosAntigos,
            [
                'nome' => $nome,
                'cpf' => $cpf,
                'matricula' => $matricula,
                'cargo' => $cargo,
                'ativo' => $ativo
            ]
        );
        
        jsonResponse(true, 'Usuário atualizado com sucesso');
    } else {
        jsonResponse(false, 'Erro ao atualizar usuário');
    }
}

function handleDelete($input) {
    global $security;
    $db = $GLOBALS['db']->getConnection();
    
    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
    
    if (!$id) {
        jsonResponse(false, 'ID do usuário é obrigatório');
    }
    
    // Buscar dados atuais
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        jsonResponse(false, 'Usuário não encontrado', null, 404);
    }
    
    // Não permitir excluir admin principal
    if ($usuario['login'] === 'admin') {
        jsonResponse(false, 'Não é possível excluir o administrador principal');
    }
    
    // Verificar se pode excluir (não pode ter pontos registrados)
    $stmt = $db->prepare("SELECT COUNT(*) FROM pontos WHERE usuario_id = ?");
    $stmt->execute([$id]);
    $totalPontos = $stmt->fetchColumn();
    
    if ($totalPontos > 0) {
        jsonResponse(false, "Não é possível excluir. Usuário possui {$totalPontos} registro(s) de ponto. Desative o usuário.");
    }
    
    // Excluir usuário e dados relacionados
    $db->beginTransaction();
    
    try {
        // Excluir overrides de jornada
        $stmt = $db->prepare("DELETE FROM usuario_jornada_override WHERE usuario_id = ?");
        $stmt->execute([$id]);
        
        // Excluir usuário
        $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        
        // Log de auditoria
        $security->logAudit(
            $_SESSION['user_id'],
            'EXCLUIR',
            'usuarios',
            $id,
            $usuario,
            null
        );
        
        $db->commit();
        jsonResponse(true, 'Usuário excluído com sucesso');
        
    } catch (Exception $e) {
        $db->rollback();
        jsonResponse(false, 'Erro ao excluir usuário: ' . $e->getMessage());
    }
}
?>