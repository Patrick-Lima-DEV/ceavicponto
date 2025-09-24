<?php
/**
 * API para conformidade com Portaria MTP 671/2021
 * Geração de AFD, AEJ, Atestados e Relatórios
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../compliance/ComplianceMTP671.php';

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$security = $GLOBALS['security'];

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
            
        case 'POST':
            handlePost($input);
            break;
            
        default:
            jsonResponse(false, 'Método não permitido', null, 405);
    }
    
} catch (Exception $e) {
    $security->logAudit(
        $_SESSION['user_id'] ?? null,
        'ERROR',
        'compliance',
        null,
        null,
        ['error' => $e->getMessage()]
    );
    
    jsonResponse(false, 'Erro interno: ' . $e->getMessage(), null, 500);
}

function handleGet() {
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'funcionarios':
                listarFuncionarios();
                break;
                
            case 'download':
                downloadArquivo();
                break;
                
            default:
                jsonResponse(false, 'Ação não encontrada');
        }
    } else {
        jsonResponse(false, 'Ação não especificada');
    }
}

function handlePost($input) {
    if (!isset($input['action'])) {
        jsonResponse(false, 'Ação não especificada');
    }
    
    switch ($input['action']) {
        case 'gerar_afd':
            gerarAFD($input);
            break;
            
        case 'gerar_aej':
            gerarAEJ($input);
            break;
            
        case 'gerar_atestado_ptrp':
            gerarAtestadoPTRP($input);
            break;
            
        case 'gerar_atestado_reps':
            gerarAtestadoREPs($input);
            break;
            
        case 'gerar_espelho':
            gerarEspelhoEletronico($input);
            break;
            
        case 'compactar_arquivos':
            compactarArquivos($input);
            break;
            
        default:
            jsonResponse(false, 'Ação não encontrada');
    }
}

/**
 * Lista funcionários para seleção
 */
function listarFuncionarios() {
    global $security;
    $db = $GLOBALS['db']->getConnection();
    
    $stmt = $db->prepare("
        SELECT u.id, u.nome, u.cpf, u.matricula,
               d.nome as departamento_nome
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        WHERE u.tipo = 'funcionario' AND u.ativo = 1
        ORDER BY u.nome
    ");
    
    $stmt->execute();
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(true, 'Funcionários listados com sucesso', ['funcionarios' => $funcionarios]);
}

/**
 * Gera AFD (Arquivo Fonte de Dados)
 */
function gerarAFD($input) {
    global $security;
    
    $dataInicio = $security->sanitizeInput($input['data_inicio'] ?? '');
    $dataFim = $security->sanitizeInput($input['data_fim'] ?? '');
    $funcionarioId = isset($input['funcionario_id']) ? (int) $input['funcionario_id'] : null;
    $nomeArquivo = $security->sanitizeInput($input['nome_arquivo'] ?? '');
    
    if (!$dataInicio || !$dataFim) {
        jsonResponse(false, 'Data início e fim são obrigatórias');
    }
    
    try {
        $compliance = new ComplianceMTP671();
        
        // Gerar conteúdo do AFD
        $conteudoAFD = $compliance->gerarAFD($dataInicio, $dataFim, $funcionarioId);
        
        // Definir nome do arquivo
        if (empty($nomeArquivo)) {
            $nomeArquivo = 'AFD_' . str_replace('-', '', $dataInicio) . '_' . str_replace('-', '', $dataFim);
        }
        
        // Salvar arquivo temporário
        $caminhoArquivo = $compliance->salvarArquivoTemporario($conteudoAFD, $nomeArquivo . '.txt');
        
        // Log da ação
        $security->logAudit(
            $_SESSION['user_id'],
            'GERAR_AFD',
            'compliance',
            null,
            null,
            [
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'funcionario_id' => $funcionarioId,
                'arquivo' => $nomeArquivo . '.txt'
            ]
        );
        
        jsonResponse(true, 'AFD gerado com sucesso', [
            'arquivo' => $nomeArquivo . '.txt',
            'caminho' => $caminhoArquivo,
            'tamanho' => strlen($conteudoAFD)
        ]);
        
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao gerar AFD: ' . $e->getMessage());
    }
}

/**
 * Gera AEJ (Arquivo Eletrônico de Jornada)
 */
function gerarAEJ($input) {
    global $security;
    
    $dataInicio = $security->sanitizeInput($input['data_inicio'] ?? '');
    $dataFim = $security->sanitizeInput($input['data_fim'] ?? '');
    $funcionarioId = isset($input['funcionario_id']) ? (int) $input['funcionario_id'] : null;
    $nomeArquivo = $security->sanitizeInput($input['nome_arquivo'] ?? '');
    
    if (!$dataInicio || !$dataFim) {
        jsonResponse(false, 'Data início e fim são obrigatórias');
    }
    
    try {
        $compliance = new ComplianceMTP671();
        
        // Gerar conteúdo do AEJ
        $conteudoAEJ = $compliance->gerarAEJ($dataInicio, $dataFim, $funcionarioId);
        
        // Definir nome do arquivo
        if (empty($nomeArquivo)) {
            $nomeArquivo = 'AEJ_' . str_replace('-', '', $dataInicio) . '_' . str_replace('-', '', $dataFim);
        }
        
        // Salvar arquivo temporário
        $caminhoArquivo = $compliance->salvarArquivoTemporario($conteudoAEJ, $nomeArquivo . '.txt');
        
        // Log da ação
        $security->logAudit(
            $_SESSION['user_id'],
            'GERAR_AEJ',
            'compliance',
            null,
            null,
            [
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'funcionario_id' => $funcionarioId,
                'arquivo' => $nomeArquivo . '.txt'
            ]
        );
        
        jsonResponse(true, 'AEJ gerado com sucesso', [
            'arquivo' => $nomeArquivo . '.txt',
            'caminho' => $caminhoArquivo,
            'tamanho' => strlen($conteudoAEJ)
        ]);
        
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao gerar AEJ: ' . $e->getMessage());
    }
}

/**
 * Gera Atestado Técnico do PTRP
 */
function gerarAtestadoPTRP($input) {
    try {
        require_once __DIR__ . '/../compliance/PDFGenerator.php';
        
        $pdfGenerator = new PDFGenerator();
        $caminhoArquivo = $pdfGenerator->gerarAtestadoPTRP();
        
        // Log da ação
        $security = $GLOBALS['security'];
        $security->logAudit(
            $_SESSION['user_id'],
            'GERAR_ATESTADO_PTRP',
            'compliance',
            null,
            null,
            ['arquivo' => basename($caminhoArquivo)]
        );
        
        jsonResponse(true, 'Atestado PTRP gerado com sucesso', [
            'arquivo' => basename($caminhoArquivo),
            'caminho' => $caminhoArquivo
        ]);
        
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao gerar Atestado PTRP: ' . $e->getMessage());
    }
}

/**
 * Gera Atestado Técnico dos REPs
 */
function gerarAtestadoREPs($input) {
    try {
        require_once __DIR__ . '/../compliance/PDFGenerator.php';
        
        $pdfGenerator = new PDFGenerator();
        $caminhoArquivo = $pdfGenerator->gerarAtestadoREPs();
        
        // Log da ação
        $security = $GLOBALS['security'];
        $security->logAudit(
            $_SESSION['user_id'],
            'GERAR_ATESTADO_REPS',
            'compliance',
            null,
            null,
            ['arquivo' => basename($caminhoArquivo)]
        );
        
        jsonResponse(true, 'Atestado REPs gerado com sucesso', [
            'arquivo' => basename($caminhoArquivo),
            'caminho' => $caminhoArquivo
        ]);
        
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao gerar Atestado REPs: ' . $e->getMessage());
    }
}

/**
 * Gera Espelho Eletrônico de Ponto
 */
function gerarEspelhoEletronico($input) {
    global $security;
    
    $funcionarioId = (int) ($input['funcionario_id'] ?? 0);
    $dataInicio = $security->sanitizeInput($input['data_inicio'] ?? '');
    $dataFim = $security->sanitizeInput($input['data_fim'] ?? '');
    
    if (!$funcionarioId || !$dataInicio || !$dataFim) {
        jsonResponse(false, 'Funcionário, data início e fim são obrigatórios');
    }
    
    try {
        require_once __DIR__ . '/../compliance/PDFGenerator.php';
        
        $pdfGenerator = new PDFGenerator();
        $caminhoArquivo = $pdfGenerator->gerarEspelhoEletronico($funcionarioId, $dataInicio, $dataFim);
        
        // Log da ação
        $security->logAudit(
            $_SESSION['user_id'],
            'GERAR_ESPELHO_ELETRONICO',
            'compliance',
            null,
            null,
            [
                'funcionario_id' => $funcionarioId,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'arquivo' => basename($caminhoArquivo)
            ]
        );
        
        jsonResponse(true, 'Espelho eletrônico gerado com sucesso', [
            'arquivo' => basename($caminhoArquivo),
            'caminho' => $caminhoArquivo
        ]);
        
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao gerar Espelho Eletrônico: ' . $e->getMessage());
    }
}

/**
 * Compacta arquivos em ZIP
 */
function compactarArquivos($input) {
    global $security;
    
    $arquivos = $input['arquivos'] ?? [];
    $nomeZip = $security->sanitizeInput($input['nome_zip'] ?? 'arquivos_compliance');
    
    if (empty($arquivos)) {
        jsonResponse(false, 'Nenhum arquivo especificado para compactação');
    }
    
    try {
        $compliance = new ComplianceMTP671();
        
        // Preparar arquivos para compactação
        $arquivosParaZip = [];
        foreach ($arquivos as $arquivo) {
            $arquivosParaZip[] = [
                'caminho' => $arquivo['caminho'],
                'nome' => $arquivo['nome']
            ];
        }
        
        // Compactar arquivos
        $caminhoZip = $compliance->compactarArquivos($arquivosParaZip, $nomeZip);
        
        // Log da ação
        $security->logAudit(
            $_SESSION['user_id'],
            'COMPACTAR_ARQUIVOS',
            'compliance',
            null,
            null,
            [
                'arquivos' => count($arquivos),
                'zip' => $nomeZip . '.zip'
            ]
        );
        
        jsonResponse(true, 'Arquivos compactados com sucesso', [
            'arquivo' => $nomeZip . '.zip',
            'caminho' => $caminhoZip
        ]);
        
    } catch (Exception $e) {
        jsonResponse(false, 'Erro ao compactar arquivos: ' . $e->getMessage());
    }
}

/**
 * Download de arquivo
 */
function downloadArquivo() {
    global $security;
    
    $arquivo = $security->sanitizeInput($_GET['arquivo'] ?? '');
    $tipo = $security->sanitizeInput($_GET['tipo'] ?? '');
    
    if (empty($arquivo) || empty($tipo)) {
        jsonResponse(false, 'Arquivo ou tipo não especificado');
    }
    
    $caminhoArquivo = __DIR__ . "/../compliance/temp/{$arquivo}";
    
    if (!file_exists($caminhoArquivo)) {
        jsonResponse(false, 'Arquivo não encontrado');
    }
    
    // Definir headers para download baseado no tipo
    if (strpos($arquivo, '.html') !== false) {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $arquivo . '"');
    } else {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $arquivo . '"');
    }
    header('Content-Length: ' . filesize($caminhoArquivo));
    
    // Enviar arquivo
    readfile($caminhoArquivo);
    
    // Log da ação
    $security->logAudit(
        $_SESSION['user_id'],
        'DOWNLOAD_ARQUIVO',
        'compliance',
        null,
        null,
        ['arquivo' => $arquivo, 'tipo' => $tipo]
    );
    
    exit;
}
?>
