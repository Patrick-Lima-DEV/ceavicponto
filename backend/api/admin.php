<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/time_utils.php';

// Headers para evitar cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Sistema unificado: JavaScript responsável por todos os cálculos
// PHP apenas retorna dados brutos e gera relatórios

function gerarRelatorioCompleto($usuario, $pontos, $dataInicio, $dataFim) {
    // ✅ Extrair dados do usuário do primeiro ponto (todos têm os mesmos dados do usuário)
    $dadosUsuario = [];
    if (!empty($pontos)) {
        $primeiroPonto = $pontos[0];
        $dadosUsuario = [
            'usuario_nome' => $primeiroPonto['usuario_nome'] ?? $usuario['nome'],
            'cpf' => $primeiroPonto['cpf'] ?? $usuario['cpf'],
            'matricula' => $primeiroPonto['matricula'] ?? $usuario['matricula'],
            'cargo' => $primeiroPonto['cargo'] ?? $usuario['cargo'],
            'departamento_nome' => $primeiroPonto['departamento_nome'] ?? $usuario['departamento_nome'],
            'carga_diaria_minutos' => $primeiroPonto['carga_diaria_minutos'] ?? 480,
            'tolerancia_minutos' => $primeiroPonto['tolerancia_minutos'] ?? 10,
            'jornada_entrada_manha' => $primeiroPonto['jornada_entrada_manha'] ?? $usuario['entrada_manha'] ?? '08:00:00',
            'jornada_saida_almoco' => $primeiroPonto['jornada_saida_almoco'] ?? $usuario['saida_almoco'] ?? '12:00:00',
            'jornada_volta_almoco' => $primeiroPonto['jornada_volta_almoco'] ?? $usuario['volta_almoco'] ?? '13:00:00',
            'jornada_saida_tarde' => $primeiroPonto['jornada_saida_tarde'] ?? $usuario['saida_tarde'] ?? '18:00:00'
        ];
    } else {
        // Se não há pontos, usar dados do usuário passado como parâmetro
        $dadosUsuario = [
            'usuario_nome' => $usuario['nome'],
            'cpf' => $usuario['cpf'],
            'matricula' => $usuario['matricula'],
            'cargo' => $usuario['cargo'],
            'departamento_nome' => $usuario['departamento_nome'],
            'carga_diaria_minutos' => 480,
            'tolerancia_minutos' => 10,
            'jornada_entrada_manha' => $usuario['entrada_manha'] ?? '08:00:00',
            'jornada_saida_almoco' => $usuario['saida_almoco'] ?? '12:00:00',
            'jornada_volta_almoco' => $usuario['volta_almoco'] ?? '13:00:00',
            'jornada_saida_tarde' => $usuario['saida_tarde'] ?? '18:00:00'
        ];
    }
    
    // Agrupar pontos por data
    $registrosPorData = [];
    foreach ($pontos as $ponto) {
        $data = $ponto['data'];
        
        if (!isset($registrosPorData[$data])) {
            $registrosPorData[$data] = [
                'data' => $data,
                'entrada_manha' => '--',
                'saida_almoco' => '--',
                'volta_almoco' => '--',
                'saida_tarde' => '--',
                'observacoes' => '',
                'justificativa' => null,
                // ✅ ADICIONAR: Incluir dados do usuário em cada registro
                'usuario_nome' => $dadosUsuario['usuario_nome'],
                'cpf' => $dadosUsuario['cpf'],
                'matricula' => $dadosUsuario['matricula'],
                'cargo' => $dadosUsuario['cargo'],
                'departamento_nome' => $dadosUsuario['departamento_nome'],
                'carga_diaria_minutos' => $dadosUsuario['carga_diaria_minutos'],
                'tolerancia_minutos' => $dadosUsuario['tolerancia_minutos'],
                'entrada_manha_jornada' => $dadosUsuario['jornada_entrada_manha'],
                'saida_almoco_jornada' => $dadosUsuario['jornada_saida_almoco'],
                'volta_almoco_jornada' => $dadosUsuario['jornada_volta_almoco'],
                'saida_tarde_jornada' => $dadosUsuario['jornada_saida_tarde'],
                // ✅ ADICIONAR: Dados de ajuste para cada registro
                'tem_edicao' => false,
                'detalhes_ajuste' => []
            ];
        }
        
        // Mapear tipos de ponto
        switch ($ponto['tipo']) {
            case 'entrada_manha':
                $registrosPorData[$data]['entrada_manha'] = arredondarHorario($ponto['hora']);
                break;
            case 'saida_almoco':
                $registrosPorData[$data]['saida_almoco'] = arredondarHorario($ponto['hora']);
                break;
            case 'volta_almoco':
                $registrosPorData[$data]['volta_almoco'] = arredondarHorario($ponto['hora']);
                break;
            case 'saida_tarde':
                $registrosPorData[$data]['saida_tarde'] = arredondarHorario($ponto['hora']);
                break;
        }
        
        // ✅ MELHORAR: Capturar detalhes completos do ajuste
        if ($ponto['editado']) {
            $registrosPorData[$data]['tem_edicao'] = true;
            
            // Adicionar detalhes do ajuste
            $detalhesAjuste = [
                'tipo' => $ponto['tipo'],
                'hora_original' => $ponto['hora'], // Hora atual (já ajustada)
                'editado_em' => $ponto['editado_em'],
                'editado_por_nome' => $ponto['editado_por_nome'] ?? 'Sistema',
                'motivo_ajuste' => $ponto['motivo_ajuste'] ?? 'Não informado',
                'tempo_ajustado_minutos' => $ponto['tempo_ajustado_minutos'] ?? 0,
                'observacao' => $ponto['observacao'] ?? ''
            ];
            
            $registrosPorData[$data]['detalhes_ajuste'][] = $detalhesAjuste;
            
            // Atualizar observações com informações do ajuste
            $observacaoAjuste = "Ajustado por {$detalhesAjuste['editado_por_nome']}";
            if ($detalhesAjuste['motivo_ajuste'] !== 'Não informado') {
                $observacaoAjuste .= " - Motivo: " . ucfirst(str_replace('_', ' ', $detalhesAjuste['motivo_ajuste']));
            }
            if ($detalhesAjuste['observacao']) {
                $observacaoAjuste .= " - " . $detalhesAjuste['observacao'];
            }
            
            $registrosPorData[$data]['observacoes'] = $observacaoAjuste;
        }
        
        // Capturar informações de justificativa (se houver)
        if ($ponto['justificativa_id'] && !$registrosPorData[$data]['justificativa']) {
            $registrosPorData[$data]['justificativa'] = [
                'id' => $ponto['justificativa_id'],
                'codigo' => $ponto['justificativa_codigo'],
                'tipo_nome' => $ponto['justificativa_tipo_nome'],
                'periodo_parcial' => $ponto['periodo_parcial'],
                'motivo' => $ponto['justificativa_motivo'],
                'status' => $ponto['justificativa_status']
            ];
        }
    }
    
    // ✅ FILTRAR: Remover APENAS registros completamente vazios (sem horários E sem justificativa)
    $registrosFiltrados = [];
    foreach ($registrosPorData as $data => $registro) {
        // Incluir registro se:
        // 1. Tem pelo menos UM horário batido (diferente de '--'), OU
        // 2. Tem justificativa ativa
        $temHorario = ($registro['entrada_manha'] !== '--') || 
                      ($registro['saida_almoco'] !== '--') || 
                      ($registro['volta_almoco'] !== '--') || 
                      ($registro['saida_tarde'] !== '--');
        
        $temJustificativa = !empty($registro['justificativa']);
        
        if ($temHorario || $temJustificativa) {
            $registrosFiltrados[] = $registro;
        }
    }
    
    // JavaScript fará todos os cálculos - PHP apenas retorna dados brutos
    
    return [
        'usuario' => array_merge($usuario, $dadosUsuario), // ✅ Incluir dados extraídos dos pontos
        'periodo' => [
            'inicio' => $dataInicio,
            'fim' => $dataFim
        ],
        'registros' => $registrosFiltrados
    ];
}

// Função para gerar HTML do cartão de ponto
function gerarCartaoPontoHTML($usuario, $pontos, $dataInicio, $dataFim) {
    // Obter dados brutos - JavaScript fará os cálculos
    $dadosProcessados = gerarRelatorioCompleto($usuario, $pontos, $dataInicio, $dataFim);
    $registros = $dadosProcessados['registros'];
    
    // Buscar configurações da empresa do banco de dados
    $db = $GLOBALS['db']->getConnection();
    $stmt = $db->prepare("SELECT chave, valor FROM configuracoes_empresa WHERE chave LIKE 'empresa_%'");
    $stmt->execute();
    $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Informações da empresa (do banco de dados)
    $empresa = [
        'nome' => $configs['empresa_nome'] ?? 'Tech-Ponto Sistemas',
        'cnpj' => $configs['empresa_cnpj'] ?? '00.000.000/0001-00',
        'endereco' => $configs['empresa_endereco'] ?? 'Rua da Inovação, 123 - Centro',
        'cidade' => $configs['empresa_cidade'] ?? 'São Paulo - SP',
        'telefone' => $configs['empresa_telefone'] ?? '(11) 1234-5678',
        'email' => $configs['empresa_email'] ?? 'contato@techponto.com',
        'logo' => $configs['empresa_logo'] ?? ''
    ];
    
    // Dados do funcionário (alguns simulados)
    $funcionario = [
        'nome' => $usuario['nome'],
        'matricula' => $usuario['matricula'] ?? '0001',
        'cpf' => $usuario['cpf'] ?? '000.000.000-00',
        'cargo' => $usuario['cargo'] ?? 'Desenvolvedor',
        'departamento' => $usuario['departamento_nome'] ?? 'Tecnologia',
        'pis' => '000.00000.00-0'
    ];
    
    // Verificar se é período mensal completo
    $inicioObj = new DateTime($dataInicio);
    $fimObj = new DateTime($dataFim);
    $isMonthlyComplete = (
        $inicioObj->format('d') === '01' && 
        $inicioObj->format('Y-m') === $fimObj->format('Y-m') &&
        $fimObj->format('d') === $fimObj->format('t')
    );
    
    if ($isMonthlyComplete) {
        $periodoTitulo = $inicioObj->format('F/Y');
        $periodoSubtitulo = 'Período Mensal Completo';
    } else {
        $periodoTitulo = $inicioObj->format('d/m/Y') . ' a ' . $fimObj->format('d/m/Y');
        $periodoSubtitulo = 'Período Personalizado';
    }
    
    // Agrupar pontos por data
    $registrosPorData = [];
    foreach ($pontos as $ponto) {
        $data = $ponto['data'];
        if (!isset($registrosPorData[$data])) {
            $registrosPorData[$data] = [
                'data' => $data,
                'entrada_manha' => '--',
                'saida_almoco' => '--',
                'volta_almoco' => '--',
                'saida_tarde' => '--',
                'observacoes' => ''
            ];
        }
        
        // Mapear tipos de ponto
        switch ($ponto['tipo']) {
            case 'entrada_manha':
                $registrosPorData[$data]['entrada_manha'] = arredondarHorario($ponto['hora']);
                break;
            case 'saida_almoco':
                $registrosPorData[$data]['saida_almoco'] = arredondarHorario($ponto['hora']);
                break;
            case 'volta_almoco':
                $registrosPorData[$data]['volta_almoco'] = arredondarHorario($ponto['hora']);
                break;
            case 'saida_tarde':
                $registrosPorData[$data]['saida_tarde'] = arredondarHorario($ponto['hora']);
                break;
        }
        
        // ✅ MELHORAR: Capturar detalhes completos do ajuste
        if ($ponto['editado']) {
            $registrosPorData[$data]['tem_edicao'] = true;
            
            // Adicionar detalhes do ajuste
            $detalhesAjuste = [
                'tipo' => $ponto['tipo'],
                'hora_original' => $ponto['hora'], // Hora atual (já ajustada)
                'editado_em' => $ponto['editado_em'],
                'editado_por_nome' => $ponto['editado_por_nome'] ?? 'Sistema',
                'motivo_ajuste' => $ponto['motivo_ajuste'] ?? 'Não informado',
                'tempo_ajustado_minutos' => $ponto['tempo_ajustado_minutos'] ?? 0,
                'observacao' => $ponto['observacao'] ?? ''
            ];
            
            if (!isset($registrosPorData[$data]['detalhes_ajuste'])) {
                $registrosPorData[$data]['detalhes_ajuste'] = [];
            }
            $registrosPorData[$data]['detalhes_ajuste'][] = $detalhesAjuste;
            
            // Atualizar observações com informações do ajuste
            $observacaoAjuste = "Ajustado por {$detalhesAjuste['editado_por_nome']}";
            if ($detalhesAjuste['motivo_ajuste'] !== 'Não informado') {
                $observacaoAjuste .= " - Motivo: " . ucfirst(str_replace('_', ' ', $detalhesAjuste['motivo_ajuste']));
            }
            if ($detalhesAjuste['observacao']) {
                $observacaoAjuste .= " - " . $detalhesAjuste['observacao'];
            }
            
            $registrosPorData[$data]['observacoes'] = $observacaoAjuste;
        }
    }
    
    // JavaScript fará todos os cálculos
    
    // Gerar HTML
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Cartão de Ponto - <?php echo htmlspecialchars($funcionario['nome']); ?> - <?php echo $periodoTitulo; ?></title>
        <style>
            body { 
                font-family: 'Arial', sans-serif; 
                font-size: 11px; 
                margin: 15px;
                color: #333;
            }
            .header { 
                text-align: center; 
                margin-bottom: 20px;
                border-bottom: 3px solid #2c3e50;
                padding-bottom: 15px;
            }
            .company-title { 
                font-size: 18px; 
                font-weight: bold; 
                color: #2c3e50;
                margin: 0;
            }
            .document-title { 
                font-size: 14px; 
                color: #34495e; 
                margin: 5px 0;
            }
            .info-section { 
                display: grid; 
                grid-template-columns: 1fr 1fr; 
                gap: 20px; 
                margin-bottom: 20px;
            }
            .company-info, .employee-info { 
                border: 1px solid #bdc3c7; 
                padding: 10px; 
                border-radius: 5px;
            }
            .section-title { 
                font-weight: bold; 
                color: #2c3e50; 
                margin-bottom: 8px;
                border-bottom: 1px solid #ecf0f1;
                padding-bottom: 3px;
            }
            .info-line { 
                margin: 3px 0; 
            }
            .warning-box {
                background: #fff3cd; 
                border: 1px solid #ffeaa7; 
                border-radius: 5px; 
                padding: 10px; 
                margin-bottom: 15px; 
                text-align: center;
            }
            .timesheet-table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 15px;
                font-size: 10px;
            }
            .timesheet-table th { 
                background: #2c3e50; 
                color: white; 
                padding: 8px 4px; 
                text-align: center;
                font-size: 9px;
            }
            .timesheet-table td { 
                border: 1px solid #bdc3c7; 
                padding: 6px 4px; 
                text-align: center;
            }
            .timesheet-table tr:nth-child(even) { 
                background: #f8f9fa; 
            }
            .totals-section { 
                display: grid; 
                grid-template-columns: repeat(3, 1fr); 
                gap: 15px; 
                margin-top: 20px;
            }
            .total-card { 
                border: 1px solid #bdc3c7; 
                padding: 10px; 
                text-align: center;
                border-radius: 5px;
            }
            .total-label { 
                font-size: 9px; 
                color: #7f8c8d; 
                margin-bottom: 5px;
            }
            .total-value { 
                font-size: 14px; 
                font-weight: bold; 
                color: #2c3e50;
            }
            .signatures { 
                display: grid; 
                grid-template-columns: 1fr 1fr; 
                gap: 40px; 
                margin-top: 30px; 
                border-top: 1px solid #bdc3c7; 
                padding-top: 20px;
            }
            .signature-line { 
                text-align: center; 
                border-bottom: 1px solid #333; 
                padding-bottom: 30px; 
                margin-bottom: 10px;
            }
            .signature-label { 
                font-size: 10px; 
                color: #7f8c8d;
            }
            .footer-info { 
                margin-top: 20px; 
                font-size: 8px; 
                color: #7f8c8d; 
                text-align: center;
            }
            @media print {
                body { margin: 0; font-size: 10px; }
                .header { page-break-inside: avoid; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <?php if (!empty($empresa['logo'])): ?>
            <div style="text-align: center; margin-bottom: 10px;">
                <img src="<?php echo htmlspecialchars($empresa['logo']); ?>" alt="Logo da Empresa" style="max-height: 60px; max-width: 200px;">
            </div>
            <?php endif; ?>
            <h1 class="company-title"><?php echo htmlspecialchars($empresa['nome']); ?></h1>
            <p class="document-title">CARTÃO DE PONTO - CONTROLE DE FREQUÊNCIA</p>
            <p><strong>Período:</strong> <?php echo $periodoTitulo; ?></p>
            <p><small style="color: #7f8c8d;"><?php echo $periodoSubtitulo; ?></small></p>
        </div>
        
        <div class="info-section">
            <div class="company-info">
                <div class="section-title">DADOS DA EMPRESA</div>
                <div class="info-line"><strong>CNPJ:</strong> <?php echo $empresa['cnpj']; ?></div>
                <div class="info-line"><strong>Endereço:</strong> <?php echo $empresa['endereco']; ?></div>
                <div class="info-line"><strong>Cidade:</strong> <?php echo $empresa['cidade']; ?></div>
                <div class="info-line"><strong>Telefone:</strong> <?php echo $empresa['telefone']; ?></div>
            </div>
            
            <div class="employee-info">
                <div class="section-title">DADOS DO FUNCIONÁRIO</div>
                <div class="info-line"><strong>Nome:</strong> <?php echo htmlspecialchars($funcionario['nome']); ?></div>
                <div class="info-line"><strong>Matrícula:</strong> <?php echo $funcionario['matricula']; ?></div>
                <div class="info-line"><strong>CPF:</strong> <?php echo $funcionario['cpf']; ?></div>
                <div class="info-line"><strong>Cargo:</strong> <?php echo $funcionario['cargo']; ?></div>
                <div class="info-line"><strong>Departamento:</strong> <?php echo $funcionario['departamento']; ?></div>
                <div class="info-line"><strong>PIS:</strong> <?php echo $funcionario['pis']; ?></div>
            </div>
        </div>
        
        <?php if (!$isMonthlyComplete): ?>
        <div class="warning-box">
            <strong style="color: #856404;">⚠️ Período Personalizado</strong><br>
            <small style="color: #856404;">Este cartão contém apenas os dias do período selecionado. Para relatório mensal completo, selecione o período de 01 a 31 do mês.</small>
        </div>
        <?php endif; ?>
        
        <table class="timesheet-table">
            <thead>
                <tr>
                    <th rowspan="2">Data</th>
                    <th rowspan="2">Dia</th>
                    <th colspan="4">Horários</th>
                    <th rowspan="2">Horas<br>Trabalhadas</th>
                    <th rowspan="2">Horas<br>Extras</th>
                    <th rowspan="2">Observações</th>
                    <th rowspan="2">Detalhes<br>do Ajuste</th>
                </tr>
                <tr>
                    <th>Entrada</th>
                    <th>Saída<br>Almoço</th>
                    <th>Volta<br>Almoço</th>
                    <th>Saída</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registrosPorData as $registro): 
                    $dataObj = new DateTime($registro['data']);
                    $diaSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'][$dataObj->format('w')];
                ?>
                <tr>
                    <td><?php echo $dataObj->format('d/m'); ?></td>
                    <td><?php echo $diaSemana; ?></td>
                    <td><?php echo $registro['entrada_manha']; ?></td>
                    <td><?php echo $registro['saida_almoco']; ?></td>
                    <td><?php echo $registro['volta_almoco']; ?></td>
                    <td><?php echo $registro['saida_tarde']; ?></td>
                    <td class="horas-trabalhadas">--</td>
                    <td class="horas-extras">--</td>
                    <td><?php echo $registro['observacoes']; ?></td>
                    <td style="font-size: 9px; color: #666;">
                        <?php if (isset($registro['detalhes_ajuste']) && !empty($registro['detalhes_ajuste'])): ?>
                            <?php foreach ($registro['detalhes_ajuste'] as $ajuste): ?>
                                <div style="margin-bottom: 2px; border-left: 2px solid #f59e0b; padding-left: 4px;">
                                    <strong><?php echo strtoupper(str_replace('_', ' ', $ajuste['tipo'])); ?>:</strong><br>
                                    <?php echo $ajuste['editado_por_nome']; ?> 
                                    (<?php echo ucfirst(str_replace('_', ' ', $ajuste['motivo_ajuste'])); ?>)<br>
                                    <?php if ($ajuste['tempo_ajustado_minutos'] > 0): ?>
                                        <span style="color: #dc2626;">+<?php echo $ajuste['tempo_ajustado_minutos']; ?>min</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="totals-section">
            <div class="total-card">
                <div class="total-label">TOTAL HORAS TRABALHADAS</div>
                <div class="total-value" id="total-horas-trabalhadas">--:--</div>
            </div>
            <div class="total-card">
                <div class="total-label">TOTAL HORAS EXTRAS</div>
                <div class="total-value" id="total-horas-extras">--:--</div>
            </div>
            <div class="total-card">
                <div class="total-label">SALDO FINAL</div>
                <div class="total-value" id="saldo-final">--:--</div>
            </div>
            <div class="total-card">
                <div class="total-label">DIAS TRABALHADOS</div>
                <div class="total-value" id="total-dias">--</div>
            </div>
            <div class="total-card">
                <div class="total-label">DIAS COMPLETOS</div>
                <div class="total-value" id="dias-completos">--</div>
            </div>
            <div class="total-card">
                <div class="total-label">FALTAS/ATRASOS</div>
                <div class="total-value" id="faltas-atrasos">--</div>
            </div>
        </div>
        
        <div class="signatures">
            <div>
                <div class="signature-line"></div>
                <div class="signature-label">Assinatura do Funcionário</div>
            </div>
            <div>
                <div class="signature-line"></div>
                <div class="signature-label">Assinatura do Responsável de RH</div>
            </div>
        </div>
        
        <div class="footer-info">
            <p>Relatório gerado automaticamente pelo sistema Tech-Ponto em <?php echo date('d/m/Y \à\s H:i'); ?></p>
            <p>Este documento é válido apenas com as assinaturas do funcionário e do responsável de RH</p>
        </div>
    </body>
    </html>
    <?php
    
    return ob_get_clean();
}

// Validar método HTTP
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'])) {
    jsonResponse(false, 'Método HTTP inválido', null, 405);
}

// Tratar OPTIONS para CORS
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Decodificar input JSON com validação
$inputRaw = file_get_contents('php://input');
$input = [];

if (!empty($inputRaw)) {
    $input = json_decode($inputRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, 'JSON inválido: ' . json_last_error_msg(), null, 400);
    }
}

$security = $GLOBALS['security'];
$db = $GLOBALS['db']->getConnection();

try {
    requireAdmin();
    switch ($method) {
        case 'GET':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'usuarios':
                        // Listar todos os usuários
                        $stmt = $db->prepare("
                            SELECT u.id, u.nome, u.cpf, u.matricula, u.login, u.tipo, u.cargo, u.ativo, u.created_at,
                                   u.departamento_id, u.grupo_jornada_id,
                                   d.nome as departamento_nome, gj.nome as grupo_jornada_nome,
                                   gj.carga_diaria_minutos, gj.tolerancia_minutos
                            FROM usuarios u 
                            LEFT JOIN departamentos d ON u.departamento_id = d.id
                            LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
                            ORDER BY u.nome
                        ");
                        $stmt->execute();
                        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        jsonResponse(true, 'Usuários recuperados com sucesso', ['usuarios' => $usuarios]);
                        break;
                        
                    case 'relatorio':
                        $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
                        $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
                        $usuarioId = $_GET['usuario_id'] ?? null;
                        
                        $where = "WHERE p.data BETWEEN ? AND ?";
                        $params = [$dataInicio, $dataFim];
                        
                        if ($usuarioId) {
                            $where .= " AND p.usuario_id = ?";
                            $params[] = $usuarioId;
                        }
                        
                        $stmt = $db->prepare("
                            SELECT p.*, u.nome as usuario_nome, u.cpf, u.matricula, u.cargo,
                                   d.nome as departamento_nome, u.grupo_jornada_id,
                                   gj.carga_diaria_minutos, gj.entrada_manha, gj.saida_almoco, 
                                   gj.volta_almoco, gj.saida_tarde,
                                   j.id as justificativa_id, j.tipo_justificativa_id, j.periodo_parcial,
                                   j.motivo as justificativa_motivo, j.status as justificativa_status,
                                   tj.codigo as justificativa_codigo, tj.nome as justificativa_tipo_nome
                            FROM pontos p 
                            JOIN usuarios u ON p.usuario_id = u.id 
                            LEFT JOIN departamentos d ON u.departamento_id = d.id
                            LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
                            LEFT JOIN justificativas j ON (
                                j.funcionario_id = p.usuario_id 
                                AND j.data_inicio <= p.data
                                AND (j.data_fim >= p.data OR j.data_fim IS NULL)
                                AND j.status = 'ativa'
                            )
                            LEFT JOIN tipos_justificativa tj ON j.tipo_justificativa_id = tj.id
                            $where
                            ORDER BY u.nome, p.data, p.hora
                        ");
                        $stmt->execute($params);
                        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Buscar dados completos do usuário se houver filtro específico
                        $dadosUsuario = null;
                        if ($usuarioId) {
                            $userStmt = $db->prepare("
                                SELECT u.nome as usuario_nome, u.cpf, u.matricula, u.cargo,
                                       d.nome as departamento_nome,
                                       gj.carga_diaria_minutos, gj.entrada_manha, gj.saida_almoco, 
                                       gj.volta_almoco, gj.saida_tarde
                                FROM usuarios u
                                LEFT JOIN departamentos d ON u.departamento_id = d.id
                                LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
                                WHERE u.id = ?
                            ");
                            $userStmt->execute([$usuarioId]);
                            $dadosUsuario = $userStmt->fetch(PDO::FETCH_ASSOC);
                        }

                        // Buscar justificativas ativas no período para gerar registros para todos os dias
                        $justificativasStmt = $db->prepare("
                            SELECT DISTINCT j.*, tj.codigo as justificativa_codigo, tj.nome as justificativa_tipo_nome,
                                   u.nome as usuario_nome, u.cpf, u.matricula, u.cargo, d.nome as departamento_nome,
                                   gj.carga_diaria_minutos, gj.entrada_manha, gj.saida_almoco, 
                                   gj.volta_almoco, gj.saida_tarde
                            FROM justificativas j
                            JOIN tipos_justificativa tj ON j.tipo_justificativa_id = tj.id
                            JOIN usuarios u ON j.funcionario_id = u.id
                            LEFT JOIN departamentos d ON u.departamento_id = d.id
                            LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
                            WHERE j.status = 'ativa'
                            AND j.data_inicio <= ? 
                            AND (j.data_fim >= ? OR j.data_fim IS NULL)
                        ");
                        $justificativasStmt->execute([$dataFim, $dataInicio]);
                        $justificativas = $justificativasStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Gerar registros para todos os dias das justificativas
                        $registrosJustificativas = [];
                        foreach ($justificativas as $justificativa) {
                            $inicio = new DateTime($justificativa['data_inicio']);
                            $fim = $justificativa['data_fim'] ? new DateTime($justificativa['data_fim']) : $inicio;
                            
                            // Limitar ao período do relatório
                            $inicioRelatorio = new DateTime($dataInicio);
                            $fimRelatorio = new DateTime($dataFim);
                            
                            if ($inicio < $inicioRelatorio) $inicio = $inicioRelatorio;
                            if ($fim > $fimRelatorio) $fim = $fimRelatorio;
                            
                            // Filtrar por usuário se especificado
                            if ($usuarioId && $justificativa['funcionario_id'] != $usuarioId) {
                                continue;
                            }
                            
                            $dataAtual = clone $inicio;
                            while ($dataAtual <= $fim) {
                                $dataStr = $dataAtual->format('Y-m-d');
                                
                                // Verificar se já existe registro para esta data
                                $jaExiste = false;
                                foreach ($registros as $registro) {
                                    // ✅ CORRIGIDO: Comparar por ID em vez de nome (evita conflito com nomes duplicados)
                                    if ($registro['usuario_id'] == $justificativa['funcionario_id'] && 
                                        $registro['data'] === $dataStr) {
                                        $jaExiste = true;
                                        break;
                                    }
                                }
                                
                                // Se não existe, criar registro fictício para a justificativa
                                if (!$jaExiste) {
                                    $registrosJustificativas[] = [
                                        'id' => null,
                                        'usuario_id' => $justificativa['funcionario_id'],
                                        'data' => $dataStr,
                                        'hora' => null,
                                        'tipo' => null,
                                        'editado' => 0,
                                        'editado_por' => null,
                                        'created_at' => null,
                                        'updated_at' => null,
                                        'usuario_nome' => $justificativa['usuario_nome'],
                                        'cpf' => $justificativa['cpf'],
                                        'matricula' => $justificativa['matricula'],
                                        'departamento_nome' => $justificativa['departamento_nome'],
                                        'carga_diaria_minutos' => $justificativa['carga_diaria_minutos'],
                                        'entrada_manha' => $justificativa['entrada_manha'],
                                        'saida_almoco' => $justificativa['saida_almoco'],
                                        'volta_almoco' => $justificativa['volta_almoco'],
                                        'saida_tarde' => $justificativa['saida_tarde'],
                                        'justificativa_id' => $justificativa['id'],
                                        'tipo_justificativa_id' => $justificativa['tipo_justificativa_id'],
                                        'periodo_parcial' => $justificativa['periodo_parcial'],
                                        'justificativa_motivo' => $justificativa['motivo'],
                                        'justificativa_status' => $justificativa['status'],
                                        'justificativa_codigo' => $justificativa['justificativa_codigo'],
                                        'justificativa_tipo_nome' => $justificativa['justificativa_tipo_nome']
                                    ];
                                }
                                
                                $dataAtual->add(new DateInterval('P1D'));
                            }
                        }
                        
                        // Combinar registros de pontos com registros de justificativas
                        $registros = array_merge($registros, $registrosJustificativas);
                        
                        // Gerar registros para domingos (FOLGA) no período
                        $registrosDomingos = [];
                        $inicio = new DateTime($dataInicio);
                        $fim = new DateTime($dataFim);
                        
                        // Buscar todos os usuários únicos do período
                        $usuariosUnicos = [];
                        foreach ($registros as $registro) {
                            $key = $registro['usuario_id'];
                            if (!isset($usuariosUnicos[$key])) {
                                $usuariosUnicos[$key] = [
                                    'usuario_id' => $registro['usuario_id'],
                                    'usuario_nome' => $registro['usuario_nome'],
                                    'cpf' => $registro['cpf'],
                                    'matricula' => $registro['matricula'],
                                    'cargo' => $registro['cargo'],
                                    'departamento_nome' => $registro['departamento_nome'],
                                    'grupo_jornada_id' => $registro['grupo_jornada_id'] ?? null,
                                    'carga_diaria_minutos' => $registro['carga_diaria_minutos'],
                                    'entrada_manha' => $registro['entrada_manha'],
                                    'saida_almoco' => $registro['saida_almoco'],
                                    'volta_almoco' => $registro['volta_almoco'],
                                    'saida_tarde' => $registro['saida_tarde']
                                ];
                            }
                        }
                        
                        // Se não há usuários únicos (período sem registros), buscar usuários ativos
                        if (empty($usuariosUnicos)) {
                            $usuariosStmt = $db->prepare("
                                SELECT u.id as usuario_id, u.nome as usuario_nome, u.cpf, u.matricula, u.cargo,
                                       d.nome as departamento_nome, u.grupo_jornada_id,
                                       gj.carga_diaria_minutos, gj.entrada_manha, gj.saida_almoco, 
                                       gj.volta_almoco, gj.saida_tarde
                                FROM usuarios u
                                LEFT JOIN departamentos d ON u.departamento_id = d.id
                                LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
                                WHERE u.tipo = 'funcionario' AND u.ativo = 1
                            ");
                            $usuariosStmt->execute();
                            $usuariosAtivos = $usuariosStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($usuariosAtivos as $usuario) {
                                $usuariosUnicos[$usuario['usuario_id']] = $usuario;
                            }
                        }
                        
                        // Se há filtro por usuário específico, garantir que ele esteja na lista
                        if ($usuarioId && !isset($usuariosUnicos[$usuarioId])) {
                            $userStmt = $db->prepare("
                                SELECT u.id as usuario_id, u.nome as usuario_nome, u.cpf, u.matricula, u.cargo,
                                       d.nome as departamento_nome, u.grupo_jornada_id,
                                       gj.carga_diaria_minutos, gj.entrada_manha, gj.saida_almoco, 
                                       gj.volta_almoco, gj.saida_tarde
                                FROM usuarios u
                                LEFT JOIN departamentos d ON u.departamento_id = d.id
                                LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
                                WHERE u.id = ? AND u.tipo = 'funcionario' AND u.ativo = 1
                            ");
                            $userStmt->execute([$usuarioId]);
                            $usuarioEspecifico = $userStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($usuarioEspecifico) {
                                $usuariosUnicos[$usuarioId] = $usuarioEspecifico;
                            }
                        }
                        
                        // ✅ OTIMIZAÇÃO: Buscar configurações de todos os grupos de jornada de uma vez (evitar N+1 queries)
                        $gruposJornadaConfig = [];
                        if (!empty($usuariosUnicos)) {
                            $gruposIds = [];
                            foreach ($usuariosUnicos as $usuario) {
                                if (isset($usuario['grupo_jornada_id']) && $usuario['grupo_jornada_id']) {
                                    $gruposIds[] = $usuario['grupo_jornada_id'];
                                }
                            }
                            
                            if (!empty($gruposIds)) {
                                $gruposIds = array_unique($gruposIds);
                                $placeholders = str_repeat('?,', count($gruposIds) - 1) . '?';
                                
                                $grupoStmt = $db->prepare("
                                    SELECT id, domingo_folga, sabado_ativo 
                                    FROM grupos_jornada 
                                    WHERE id IN ($placeholders)
                                ");
                                $grupoStmt->execute($gruposIds);
                                $gruposResultados = $grupoStmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Indexar por ID para acesso rápido O(1)
                                foreach ($gruposResultados as $grupo) {
                                    $gruposJornadaConfig[$grupo['id']] = $grupo;
                                }
                            }
                        }
                        
                        // Para cada usuário, verificar domingos no período
                        foreach ($usuariosUnicos as $usuario) {
                            $dataAtual = clone $inicio;
                            while ($dataAtual <= $fim) {
                                $diaSemana = (int) $dataAtual->format('w'); // 0 = domingo
                                $dataStr = $dataAtual->format('Y-m-d');
                                
                                // Se é domingo, verificar se o grupo de jornada considera domingo como folga
                                if ($diaSemana === 0) {
                                    // Verificar se domingo é folga para este usuário
                                    $domingoFolga = true; // Padrão: domingo é folga
                                    
                                    // ✅ OTIMIZADO: Usar cache em vez de query por usuário
                                    if (isset($usuario['grupo_jornada_id']) && 
                                        isset($gruposJornadaConfig[$usuario['grupo_jornada_id']])) {
                                        $domingoFolga = (bool) $gruposJornadaConfig[$usuario['grupo_jornada_id']]['domingo_folga'];
                                    }
                                    
                                    // Só criar registro de domingo se for configurado como folga
                                    if ($domingoFolga) {
                                        $jaExiste = false;
                                        // Verificar em todos os registros (pontos + justificativas)
                                        foreach ($registros as $registro) {
                                            if ($registro['usuario_id'] == $usuario['usuario_id'] && $registro['data'] == $dataStr) {
                                                $jaExiste = true;
                                                break;
                                            }
                                        }
                                        // Verificar também nos registros de domingos já criados
                                        foreach ($registrosDomingos as $domingo) {
                                            if ($domingo['usuario_id'] == $usuario['usuario_id'] && $domingo['data'] == $dataStr) {
                                                $jaExiste = true;
                                                break;
                                            }
                                        }
                                        
                                        // Se não existe, criar registro fictício para domingo (FOLGA)
                                        if (!$jaExiste) {
                                            $registrosDomingos[] = [
                                                'id' => null,
                                                'usuario_id' => $usuario['usuario_id'],
                                                'data' => $dataStr,
                                                'hora' => null,
                                                'tipo' => null,
                                                'editado' => 0,
                                                'editado_por' => null,
                                                'created_at' => null,
                                                'updated_at' => null,
                                                'usuario_nome' => $usuario['usuario_nome'],
                                                'cpf' => $usuario['cpf'],
                                                'matricula' => $usuario['matricula'],
                                                'cargo' => $usuario['cargo'],
                                                'departamento_nome' => $usuario['departamento_nome'],
                                                'carga_diaria_minutos' => $usuario['carga_diaria_minutos'],
                                                'entrada_manha' => $usuario['entrada_manha'],
                                                'saida_almoco' => $usuario['saida_almoco'],
                                                'volta_almoco' => $usuario['volta_almoco'],
                                                'saida_tarde' => $usuario['saida_tarde'],
                                                'justificativa_id' => null,
                                                'tipo_justificativa_id' => null,
                                                'periodo_parcial' => null,
                                                'justificativa_motivo' => null,
                                                'justificativa_status' => null,
                                                'justificativa_codigo' => null,
                                                'justificativa_tipo_nome' => null,
                                                'is_domingo' => true, // Flag para identificar domingos
                                                'domingo_folga' => $domingoFolga // Flag para indicar se é folga
                                            ];
                                        }
                                    }
                                }
                                
                                $dataAtual->add(new DateInterval('P1D'));
                            }
                        }
                        
                        // Combinar todos os registros (pontos + justificativas + domingos)
                        $registros = array_merge($registros, $registrosDomingos);
                        
                        // Ordenar por usuário e data
                        usort($registros, function($a, $b) {
                            $cmp = strcmp($a['usuario_nome'], $b['usuario_nome']);
                            if ($cmp === 0) {
                                return strcmp($a['data'], $b['data']);
                            }
                            return $cmp;
                        });
                        
                        // [REMOVIDO] - Lógica antiga substituída por busca direta do usuário
                        
                        // Usar dados do usuário buscados diretamente (se disponível) ou buscar nos registros
                        $usuarioDataFinal = $dadosUsuario;
                        if (!$usuarioDataFinal) {
                            // Buscar nos registros existentes
                            foreach ($registros as $registro) {
                                if ($registro['usuario_nome'] && $registro['matricula'] && $registro['departamento_nome'] && !$registro['justificativa_codigo']) {
                                    $usuarioDataFinal = $registro;
                                    break;
                                }
                            }
                        }
                        
                        // Se ainda não encontrou, pegar do primeiro registro
                        if (!$usuarioDataFinal && !empty($registros)) {
                            $usuarioDataFinal = $registros[0];
                        }
                        
                        // Atualizar registros com dados completos do usuário
                        if ($usuarioDataFinal) {
                            // ✅ CORRIGIDO: Preencher APENAS dados vazios (não sobrescrever horários existentes)
                            // Isso respeita o histórico de mudanças de jornada
                            
                            foreach ($registros as &$registro) {
                                // Atualizar dados pessoais se vazios
                                if (empty($registro['usuario_nome'])) {
                                    $registro['usuario_nome'] = $usuarioDataFinal['usuario_nome'];
                                }
                                if (empty($registro['matricula'])) {
                                    $registro['matricula'] = $usuarioDataFinal['matricula'];
                                }
                                if (empty($registro['cargo'])) {
                                    $registro['cargo'] = $usuarioDataFinal['cargo'];
                                }
                                if (empty($registro['departamento_nome'])) {
                                    $registro['departamento_nome'] = $usuarioDataFinal['departamento_nome'];
                                }
                                
                                // ✅ HORÁRIOS: Preencher APENAS se NULL (registros fictícios de justificativas/domingos)
                                // NÃO sobrescrever horários de registros reais (respeitar mudanças de grupo de jornada)
                                if (!isset($registro['entrada_manha']) || $registro['entrada_manha'] === null) {
                                    $registro['entrada_manha'] = $usuarioDataFinal['entrada_manha'];
                                }
                                if (!isset($registro['saida_almoco']) || $registro['saida_almoco'] === null) {
                                    $registro['saida_almoco'] = $usuarioDataFinal['saida_almoco'];
                                }
                                if (!isset($registro['volta_almoco']) || $registro['volta_almoco'] === null) {
                                    $registro['volta_almoco'] = $usuarioDataFinal['volta_almoco'];
                                }
                                if (!isset($registro['saida_tarde']) || $registro['saida_tarde'] === null) {
                                    $registro['saida_tarde'] = $usuarioDataFinal['saida_tarde'];
                                }
                            }
                        }
                        
                        // Adicionar timestamp único para forçar atualização do cache
                        $response_data = [
                            'relatorio' => $registros,
                            'timestamp' => microtime(true),
                            'cache_buster' => uniqid('rel_', true)
                        ];
                        
                        jsonResponse(true, 'Relatório gerado com sucesso', $response_data);
                        break;
                        
                    case 'cartao_ponto':
                        $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
                        $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
                        $usuarioId = $_GET['usuario_id'] ?? null;
                        
                        if (!$usuarioId) {
                            jsonResponse(false, 'Usuário é obrigatório para cartão de ponto');
                            return;
                        }
                        
                        // Buscar dados do usuário com horários do grupo de jornada
                        $stmt = $db->prepare("
                            SELECT u.*, d.nome as departamento_nome, gj.nome as grupo_jornada_nome,
                                   gj.entrada_manha, gj.saida_almoco, gj.volta_almoco, gj.saida_tarde
                            FROM usuarios u 
                            LEFT JOIN departamentos d ON u.departamento_id = d.id
                            LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
                            WHERE u.id = ?
                        ");
                        $stmt->execute([$usuarioId]);
                        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$usuario) {
                            jsonResponse(false, 'Usuário não encontrado');
                            return;
                        }
                        
                        // ✅ CORRIGIDO: Buscar pontos COM dados completos do usuário
                        $stmt = $db->prepare("
                            SELECT p.*, 
                                   u.nome as usuario_nome, u.cpf, u.matricula, u.cargo,
                                   d.nome as departamento_nome,
                                   gj.carga_diaria_minutos, gj.tolerancia_minutos,
                                   gj.entrada_manha as jornada_entrada_manha, 
                                   gj.saida_almoco as jornada_saida_almoco,
                                   gj.volta_almoco as jornada_volta_almoco, 
                                   gj.saida_tarde as jornada_saida_tarde,
                                   u_editor.nome as editado_por_nome,
                                   j.id as justificativa_id, j.tipo_justificativa_id, j.periodo_parcial,
                                   j.motivo as justificativa_motivo, j.status as justificativa_status,
                                   tj.codigo as justificativa_codigo, tj.nome as justificativa_tipo_nome
                            FROM pontos p
                            JOIN usuarios u ON p.usuario_id = u.id
                            LEFT JOIN departamentos d ON u.departamento_id = d.id
                            LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
                            LEFT JOIN usuarios u_editor ON p.editado_por = u_editor.id
                            LEFT JOIN justificativas j ON (
                                j.funcionario_id = p.usuario_id 
                                AND j.data_inicio <= p.data
                                AND (j.data_fim >= p.data OR j.data_fim IS NULL)
                                AND j.status = 'ativa'
                            )
                            LEFT JOIN tipos_justificativa tj ON j.tipo_justificativa_id = tj.id
                            WHERE p.usuario_id = ? 
                            AND p.data BETWEEN ? AND ?
                            ORDER BY p.data ASC, p.hora ASC
                        ");
                        $stmt->execute([$usuarioId, $dataInicio, $dataFim]);
                        $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // ✅ CORRIGIDO: Buscar justificativas COM dados do usuário
                        $justificativasStmt = $db->prepare("
                            SELECT j.*, tj.codigo as justificativa_codigo, tj.nome as justificativa_tipo_nome,
                                   u.nome as usuario_nome, u.cpf, u.matricula, u.cargo,
                                   d.nome as departamento_nome,
                                   gj.carga_diaria_minutos, gj.tolerancia_minutos,
                                   gj.entrada_manha as jornada_entrada_manha,
                                   gj.saida_almoco as jornada_saida_almoco,
                                   gj.volta_almoco as jornada_volta_almoco,
                                   gj.saida_tarde as jornada_saida_tarde
                            FROM justificativas j
                            JOIN tipos_justificativa tj ON j.tipo_justificativa_id = tj.id
                            JOIN usuarios u ON j.funcionario_id = u.id
                            LEFT JOIN departamentos d ON u.departamento_id = d.id
                            LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
                            WHERE j.funcionario_id = ?
                            AND j.status = 'ativa'
                            AND j.data_inicio <= ? 
                            AND (j.data_fim >= ? OR j.data_fim IS NULL)
                        ");
                        $justificativasStmt->execute([$usuarioId, $dataFim, $dataInicio]);
                        $justificativas = $justificativasStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Gerar pontos fictícios para todos os dias das justificativas
                        $pontosJustificativas = [];
                        foreach ($justificativas as $justificativa) {
                            $inicio = new DateTime($justificativa['data_inicio']);
                            $fim = $justificativa['data_fim'] ? new DateTime($justificativa['data_fim']) : $inicio;
                            
                            // Limitar ao período do cartão
                            $inicioCartao = new DateTime($dataInicio);
                            $fimCartao = new DateTime($dataFim);
                            
                            if ($inicio < $inicioCartao) $inicio = $inicioCartao;
                            if ($fim > $fimCartao) $fim = $fimCartao;
                            
                            $dataAtual = clone $inicio;
                            while ($dataAtual <= $fim) {
                                $dataStr = $dataAtual->format('Y-m-d');
                                
                                // Verificar se já existe ponto para esta data
                                $jaExiste = false;
                                foreach ($pontos as $ponto) {
                                    if ($ponto['data'] === $dataStr) {
                                        $jaExiste = true;
                                        break;
                                    }
                                }
                                
                                // Se não existe, criar ponto fictício para a justificativa
                                if (!$jaExiste) {
                                    $pontosJustificativas[] = [
                                        'id' => null,
                                        'usuario_id' => $usuarioId,
                                        'data' => $dataStr,
                                        'hora' => null,
                                        'tipo' => null,
                                        'editado' => 0,
                                        'editado_por' => null,
                                        'created_at' => null,
                                        'updated_at' => null,
                                        'editado_por_nome' => null,
                                        // ✅ ADICIONAR: Dados do usuário da justificativa
                                        'usuario_nome' => $justificativa['usuario_nome'],
                                        'cpf' => $justificativa['cpf'],
                                        'matricula' => $justificativa['matricula'],
                                        'cargo' => $justificativa['cargo'],
                                        'departamento_nome' => $justificativa['departamento_nome'],
                                        'carga_diaria_minutos' => $justificativa['carga_diaria_minutos'],
                                        'tolerancia_minutos' => $justificativa['tolerancia_minutos'],
                                        'jornada_entrada_manha' => $justificativa['jornada_entrada_manha'],
                                        'jornada_saida_almoco' => $justificativa['jornada_saida_almoco'],
                                        'jornada_volta_almoco' => $justificativa['jornada_volta_almoco'],
                                        'jornada_saida_tarde' => $justificativa['jornada_saida_tarde'],
                                        'justificativa_id' => $justificativa['id'],
                                        'tipo_justificativa_id' => $justificativa['tipo_justificativa_id'],
                                        'periodo_parcial' => $justificativa['periodo_parcial'],
                                        'justificativa_motivo' => $justificativa['motivo'],
                                        'justificativa_status' => $justificativa['status'],
                                        'justificativa_codigo' => $justificativa['justificativa_codigo'],
                                        'justificativa_tipo_nome' => $justificativa['justificativa_tipo_nome']
                                    ];
                                }
                                
                                $dataAtual->add(new DateInterval('P1D'));
                            }
                        }
                        
                        // Combinar pontos reais com pontos de justificativas
                        $pontos = array_merge($pontos, $pontosJustificativas);
                        
                        // Ordenar por data
                        usort($pontos, function($a, $b) {
                            return strcmp($a['data'], $b['data']);
                        });
                        
                        // Retornar dados brutos - JavaScript fará os cálculos
                        $dadosBrutos = gerarRelatorioCompleto($usuario, $pontos, $dataInicio, $dataFim);
                        
                        // Buscar configurações da empresa para incluir no retorno
                        $stmt = $db->prepare("SELECT chave, valor FROM configuracoes_empresa WHERE chave LIKE 'empresa_%'");
                        $stmt->execute();
                        $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                        
                        // Adicionar configurações da empresa aos dados
                        $empresa = [
                            'nome' => $configs['empresa_nome'] ?? 'Tech-Ponto Sistemas',
                            'cnpj' => $configs['empresa_cnpj'] ?? '00.000.000/0001-00',
                            'endereco' => $configs['empresa_endereco'] ?? 'Rua da Inovação, 123 - Centro',
                            'cidade' => $configs['empresa_cidade'] ?? 'São Paulo - SP',
                            'telefone' => $configs['empresa_telefone'] ?? '(11) 1234-5678',
                            'email' => $configs['empresa_email'] ?? 'contato@techponto.com',
                            'logo' => $configs['empresa_logo'] ?? ''
                        ];
                        
                        // ✅ ADICIONAR: Enriquecer dados do usuário no retorno para facilitar acesso do frontend
                        $dadosBrutos['usuario']['entrada_manha_jornada'] = $usuario['entrada_manha'] ?? '08:00:00';
                        $dadosBrutos['usuario']['saida_almoco_jornada'] = $usuario['saida_almoco'] ?? '12:00:00';
                        $dadosBrutos['usuario']['volta_almoco_jornada'] = $usuario['volta_almoco'] ?? '13:00:00';
                        $dadosBrutos['usuario']['saida_tarde_jornada'] = $usuario['saida_tarde'] ?? '18:00:00';
                        
                        // Adicionar timestamp único para forçar atualização do cache
                        $response_data = [
                            'dados' => $dadosBrutos,
                            'empresa' => $empresa,
                            'timestamp' => microtime(true),
                            'cache_buster' => uniqid('cartao_', true)
                        ];
                        
                        jsonResponse(true, 'Dados do cartão de ponto carregados', $response_data);
                        break;
                        
                    case 'configuracoes_empresa':
                        // Buscar configurações da empresa
                        $stmt = $db->prepare("SELECT chave, valor, descricao FROM configuracoes_empresa ORDER BY chave");
                        $stmt->execute();
                        $configuracoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Organizar em array associativo
                        $configArray = [];
                        foreach ($configuracoes as $config) {
                            $configArray[$config['chave']] = [
                                'valor' => $config['valor'],
                                'descricao' => $config['descricao']
                            ];
                        }
                        
                        jsonResponse(true, 'Configurações carregadas com sucesso', ['configuracoes' => $configArray]);
                        break;
                        
                    default:
                        jsonResponse(false, 'Ação não encontrada');
                }
            } else {
                jsonResponse(false, 'Ação não especificada');
            }
            break;
            
        case 'POST':
            if (!$input || !isset($input['action'])) {
                jsonResponse(false, 'Ação não especificada');
            }
            
            switch ($input['action']) {
                case 'salvar_configuracoes_empresa':
                    // Salvar configurações da empresa
                    if (!isset($input['configuracoes']) || !is_array($input['configuracoes'])) {
                        jsonResponse(false, 'Configurações inválidas');
                        return;
                    }
                    
                    $db->beginTransaction();
                    
                    try {
                        $stmt = $db->prepare("UPDATE configuracoes_empresa SET valor = ?, updated_at = CURRENT_TIMESTAMP WHERE chave = ?");
                        
                        foreach ($input['configuracoes'] as $chave => $valor) {
                            $stmt->execute([$valor, $chave]);
                        }
                        
                        $db->commit();
                        
                        // Log da ação
                        $security->logAudit(
                            $_SESSION['user_id'],
                            'UPDATE',
                            'configuracoes_empresa',
                            null,
                            null,
                            ['configuracoes_atualizadas' => array_keys($input['configuracoes'])]
                        );
                        
                        jsonResponse(true, 'Configurações salvas com sucesso');
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        throw $e;
                    }
                    break;
                    
                case 'criar_usuario':
                    $nome = $security->sanitizeInput($input['nome'] ?? '');
                    $cpf = preg_replace('/[^0-9]/', '', $input['cpf'] ?? '');
                    $matricula = $security->sanitizeInput($input['matricula'] ?? '');
                    $login = $security->sanitizeInput($input['login'] ?? '');
                    $senha = $input['senha'] ?? '';
                    $tipo = $security->sanitizeInput($input['tipo'] ?? 'funcionario');
                    $cargo = $security->sanitizeInput($input['cargo'] ?? '');
                    $departamento_id = (int) ($input['departamento_id'] ?? 0);
                    $grupo_jornada_id = (int) ($input['grupo_jornada_id'] ?? 0);
                    
                    if (!$nome) {
                        jsonResponse(false, 'Nome é obrigatório');
                    }
                    
                    if ($tipo === 'funcionario') {
                        // CPF e matrícula são opcionais para funcionários
                        // Removido a validação obrigatória
                        
                        if (!empty($cpf) && !$security->validateCPF($cpf)) {
                            jsonResponse(false, 'CPF inválido');
                        }
                        
                        if (!$departamento_id || !$grupo_jornada_id) {
                            jsonResponse(false, 'Departamento e grupo de jornada são obrigatórios para funcionários');
                        }
                    } else {
                        if (!$login || !$senha) {
                            jsonResponse(false, 'Login e senha são obrigatórios para administradores');
                        }
                        
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
                    
                    $db->beginTransaction();
                    
                    // Criar usuário
                    $senhaHash = !empty($senha) ? password_hash($senha, PASSWORD_DEFAULT) : null;
                    $stmt = $db->prepare("
                        INSERT INTO usuarios (
                            nome, cpf, matricula, login, senha, tipo, cargo, 
                            departamento_id, grupo_jornada_id, pin_reset,
                            created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
                    ");
                    $stmt->execute([
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
                    ]);
                    
                    $userId = $db->lastInsertId();
                    
                    // Log de auditoria
                    $security->logAudit(
                        $_SESSION['user_id'],
                        'CRIAR',
                        'usuarios',
                        $userId,
                        null,
                        [
                            'nome' => $nome,
                            'tipo' => $tipo,
                            'cpf' => $cpf,
                            'matricula' => $matricula,
                            'cargo' => $cargo
                        ]
                    );
                    
                    $db->commit();
                    
                    jsonResponse(true, 'Usuário criado com sucesso', ['usuario_id' => $userId]);
                    break;
                    
                case 'atualizar_usuario':
                    $id = (int) ($input['id'] ?? 0);
                    $nome = $security->sanitizeInput($input['nome'] ?? '');
                    $cpf = preg_replace('/[^0-9]/', '', $input['cpf'] ?? '');
                    $matricula = $security->sanitizeInput($input['matricula'] ?? '');
                    $login = $security->sanitizeInput($input['login'] ?? '');
                    $cargo = $security->sanitizeInput($input['cargo'] ?? '');
                    $tipo = $security->sanitizeInput($input['tipo'] ?? 'funcionario');
                    $departamento_id = (int) ($input['departamento_id'] ?? 0);
                    $grupo_jornada_id = (int) ($input['grupo_jornada_id'] ?? 0);
                    $pin = $input['pin'] ?? '';
                    $senha = $input['senha'] ?? '';
                    $ativo = (int) ($input['ativo'] ?? 1);
                    
                    if (!$id || !$nome) {
                        jsonResponse(false, 'ID e nome são obrigatórios');
                    }
                    
                    if (!empty($cpf) && !$security->validateCPF($cpf)) {
                        jsonResponse(false, 'CPF inválido');
                    }
                    
                    // Buscar dados atuais para auditoria
                    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
                    $stmt->execute([$id]);
                    $dadosAntigos = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$dadosAntigos) {
                        jsonResponse(false, 'Usuário não encontrado');
                    }
                    
                    $db->beginTransaction();
                    
                    // Preparar dados para atualização
                    $senhaHash = null;
                    if (!empty($senha)) {
                        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                    }
                    
                    $pinHash = null;
                    if (!empty($pin)) {
                        $pinHash = $security->hashPin($pin);
                    }
                    
                    // Atualizar usuário
                    $stmt = $db->prepare("
                        UPDATE usuarios 
                        SET nome = ?, cpf = ?, matricula = ?, login = ?, cargo = ?, tipo = ?,
                            departamento_id = ?, grupo_jornada_id = ?,
                            senha = COALESCE(?, senha), pin = COALESCE(?, pin), ativo = ?, updated_at = datetime('now')
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $nome, $cpf, $matricula, $login, $cargo, $tipo,
                        $departamento_id > 0 ? $departamento_id : null,
                        $grupo_jornada_id > 0 ? $grupo_jornada_id : null,
                        $senhaHash, $pinHash, $ativo, $id
                    ]);
                    
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
                    
                    $db->commit();
                    
                    jsonResponse(true, 'Usuário atualizado com sucesso');
                    break;
                    
                case 'resetar_senha':
                    $id = (int) ($input['id'] ?? 0);
                    $novaSenha = $input['nova_senha'] ?? '123456';
                    
                    if (!$id) {
                        jsonResponse(false, 'ID do usuário é obrigatório');
                    }
                    
                    $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE usuarios SET senha = ?, updated_at = datetime('now') WHERE id = ?");
                    $stmt->execute([$senhaHash, $id]);
                    
                    // Log de auditoria
                    $security->logAudit(
                        $_SESSION['user_id'],
                        'RESETAR_SENHA',
                        'usuarios',
                        $id,
                        null,
                        ['nova_senha' => '***']
                    );
                    
                    jsonResponse(true, 'Senha resetada com sucesso');
                    break;
                    
                default:
                    jsonResponse(false, 'Ação não encontrada');
            }
            break;
            
        default:
            jsonResponse(false, 'Método não permitido');
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollback();
    }
    
    $security->logAudit(
        $_SESSION['user_id'] ?? null,
        'ERROR',
        'admin',
        null,
        null,
        ['error' => $e->getMessage()]
    );
    
    jsonResponse(false, 'Erro interno do servidor: ' . $e->getMessage());
}
?>
