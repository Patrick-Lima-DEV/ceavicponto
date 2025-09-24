<?php
/**
 * API para integração de Pontos com Justificativas
 * Verifica justificativas antes de permitir lançamento de ponto
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/JustificativaIntegrator.php';

// Verificar se há sessão ativa
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Usuário não autenticado. Faça login primeiro.', null, 401);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$integrator = new JustificativaIntegrator();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'GET':
            handleGet($integrator, $action);
            break;
        case 'POST':
            handlePost($integrator, $action);
            break;
        default:
            jsonResponse(false, 'Método não permitido', null, 405);
    }
} catch (Exception $e) {
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}

/**
 * Manipula requisições GET
 */
function handleGet($integrator, $action) {
    switch ($action) {
        case 'verificar':
            verificarJustificativa($integrator);
            break;
        case 'indicador':
            obterIndicadorJustificativa($integrator);
            break;
        case 'estatisticas':
            obterEstatisticasJustificativas($integrator);
            break;
        case 'validar':
            validarLancamentoPonto($integrator);
            break;
        default:
            jsonResponse(false, 'Ação não especificada', null, 400);
    }
}

/**
 * Manipula requisições POST
 */
function handlePost($integrator, $action) {
    switch ($action) {
        case 'processar':
            processarJustificativasLote($integrator);
            break;
        default:
            jsonResponse(false, 'Ação não especificada', null, 400);
    }
}

/**
 * Verifica se há justificativa para funcionário em data/período específico
 */
function verificarJustificativa($integrator) {
    try {
        $funcionarioId = $_GET['funcionario_id'] ?? '';
        $data = $_GET['data'] ?? '';
        $periodo = $_GET['periodo'] ?? 'integral';
        
        if (!$funcionarioId || !$data) {
            jsonResponse(false, 'Funcionário e data são obrigatórios', null, 400);
            return;
        }
        
        $justificativa = $integrator->verificarJustificativa($funcionarioId, $data, $periodo);
        
        if ($justificativa) {
            jsonResponse(true, 'Justificativa encontrada', [
                'tem_justificativa' => true,
                'justificativa' => $justificativa
            ]);
        } else {
            jsonResponse(true, 'Nenhuma justificativa encontrada', [
                'tem_justificativa' => false,
                'justificativa' => null
            ]);
        }
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao verificar justificativa: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Obtém indicador de justificativa para exibição
 */
function obterIndicadorJustificativa($integrator) {
    try {
        $funcionarioId = $_GET['funcionario_id'] ?? '';
        $data = $_GET['data'] ?? '';
        
        if (!$funcionarioId || !$data) {
            jsonResponse(false, 'Funcionário e data são obrigatórios', null, 400);
            return;
        }
        
        $indicador = $integrator->obterIndicadorJustificativa($funcionarioId, $data);
        
        jsonResponse(true, 'Indicador obtido com sucesso', [
            'indicador' => $indicador
        ]);
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao obter indicador: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Obtém estatísticas de justificativas para período
 */
function obterEstatisticasJustificativas($integrator) {
    try {
        $funcionarioId = $_GET['funcionario_id'] ?? '';
        $dataInicio = $_GET['data_inicio'] ?? '';
        $dataFim = $_GET['data_fim'] ?? '';
        
        if (!$funcionarioId || !$dataInicio || !$dataFim) {
            jsonResponse(false, 'Funcionário, data início e data fim são obrigatórios', null, 400);
            return;
        }
        
        $estatisticas = $integrator->obterEstatisticasJustificativas($funcionarioId, $dataInicio, $dataFim);
        
        jsonResponse(true, 'Estatísticas obtidas com sucesso', [
            'estatisticas' => $estatisticas
        ]);
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao obter estatísticas: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Valida se é possível lançar ponto considerando justificativas
 */
function validarLancamentoPonto($integrator) {
    try {
        $funcionarioId = $_GET['funcionario_id'] ?? '';
        $data = $_GET['data'] ?? '';
        $periodo = $_GET['periodo'] ?? 'integral';
        
        if (!$funcionarioId || !$data) {
            jsonResponse(false, 'Funcionário e data são obrigatórios', null, 400);
            return;
        }
        
        $validacao = $integrator->validarLancamentoPonto($funcionarioId, $data, $periodo);
        
        if ($validacao['permitido']) {
            jsonResponse(true, 'Lançamento de ponto permitido', [
                'permitido' => true,
                'motivo' => $validacao['motivo'] ?? null
            ]);
        } else {
            jsonResponse(false, 'Lançamento de ponto não permitido', [
                'permitido' => false,
                'motivo' => $validacao['motivo']
            ]);
        }
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao validar lançamento: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Processa justificativas em lote
 */
function processarJustificativasLote($integrator) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $funcionarioId = $input['funcionario_id'] ?? '';
        $dataInicio = $input['data_inicio'] ?? '';
        $dataFim = $input['data_fim'] ?? '';
        
        if (!$funcionarioId || !$dataInicio || !$dataFim) {
            jsonResponse(false, 'Funcionário, data início e data fim são obrigatórios', null, 400);
            return;
        }
        
        $resultado = $integrator->processarJustificativasLote($funcionarioId, $dataInicio, $dataFim);
        
        jsonResponse(true, 'Justificativas processadas com sucesso', [
            'resultado' => $resultado
        ]);
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao processar justificativas: ' . $e->getMessage(), null, 500);
    }
}
?>