<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../utils/time_utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Método não permitido');
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $userId = $_SESSION['user_id'];
    $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d');
    $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
    
    // ✅ MELHORAR: Buscar registros do período com dados de ajuste
    $stmt = $conn->prepare("
        SELECT p.*, u.nome as usuario_nome,
               u_editor.nome as editado_por_nome
        FROM pontos p 
        JOIN usuarios u ON p.usuario_id = u.id 
        LEFT JOIN usuarios u_editor ON p.editado_por = u_editor.id
        WHERE p.usuario_id = ? AND p.data BETWEEN ? AND ? 
        ORDER BY p.data DESC, p.hora ASC
    ");
    $stmt->execute([$userId, $dataInicio, $dataFim]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar dados da jornada
    $stmt = $conn->prepare("
        SELECT carga_diaria, tolerancia 
        FROM jornadas 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$userId]);
    $jornada = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar horário de saída esperado do grupo de jornada
    $stmt = $conn->prepare("
        SELECT gj.saida_tarde 
        FROM usuarios u
        LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $grupoJornada = $stmt->fetch(PDO::FETCH_ASSOC);
    $horarioSaidaEsperado = $grupoJornada['saida_tarde'] ?? '18:00:00';
    
    // Agrupar registros por data
    $registrosPorData = [];
    foreach ($registros as $registro) {
        $data = $registro['data'];
        if (!isset($registrosPorData[$data])) {
            $registrosPorData[$data] = [];
        }
        $registrosPorData[$data][] = $registro;
    }
    
    // Calcular estatísticas para cada dia
    $estatisticas = [];
    foreach ($registrosPorData as $data => $registrosDia) {
        $tipos = array_column($registrosDia, 'tipo');
        $horas = array_combine($tipos, array_column($registrosDia, 'hora'));
        
        // ✅ ADICIONAR: Verificar se há ajustes no dia
        $temAjustes = false;
        $detalhesAjustes = [];
        foreach ($registrosDia as $reg) {
            if ($reg['editado']) {
                $temAjustes = true;
                $detalhesAjustes[] = [
                    'tipo' => $reg['tipo'],
                    'hora' => $reg['hora'],
                    'editado_por_nome' => $reg['editado_por_nome'] ?? 'Sistema',
                    'motivo_ajuste' => $reg['motivo_ajuste'] ?? 'Não informado',
                    'tempo_ajustado_minutos' => $reg['tempo_ajustado_minutos'] ?? 0,
                    'observacao' => $reg['observacao'] ?? ''
                ];
            }
        }
        
        $stats = [
            'data' => $data,
            'registros' => $registrosDia,
            'completo' => count($registrosDia) >= 4,
            'horas_trabalhadas' => '00:00:00',
            'horas_esperadas' => $jornada['carga_diaria'] ?? '08:00:00',
            'saldo' => '00:00:00',
            'status' => 'incompleto',
            // ✅ ADICIONAR: Dados de ajuste
            'tem_ajustes' => $temAjustes,
            'detalhes_ajustes' => $detalhesAjustes
        ];
        
        // Calcular horas trabalhadas se tiver pelo menos entrada e saída
        if (isset($horas['entrada_manha']) && isset($horas['saida_tarde'])) {
            $entrada = new DateTime($horas['entrada_manha']);
            $saida = new DateTime($horas['saida_tarde']);
            
            $almoco_saida = isset($horas['saida_almoco']) ? new DateTime($horas['saida_almoco']) : null;
            $almoco_volta = isset($horas['volta_almoco']) ? new DateTime($horas['volta_almoco']) : null;
            
            $trabalhado = 0;
            
            if ($almoco_saida && $almoco_volta) {
                // Com intervalo de almoço
                $manhã = $almoco_saida->getTimestamp() - $entrada->getTimestamp();
                $tarde = $saida->getTimestamp() - $almoco_volta->getTimestamp();
                $trabalhado = $manhã + $tarde;
            } else {
                // Sem intervalo de almoço
                $trabalhado = $saida->getTimestamp() - $entrada->getTimestamp();
            }
            
            $horasTrabalhadas = gmdate('H:i:s', $trabalhado);
            
            // Aplicar tolerância às horas trabalhadas se necessário
            $horasTrabalhadasMinutos = $trabalhado / 60; // Converter segundos para minutos
            $cargaDiariaMinutos = ($diaSemana == 6) ? 240 : 480; // 4h para sábado, 8h para outros dias
            $diferenca = $horasTrabalhadasMinutos - $cargaDiariaMinutos;
            $toleranciaMinutos = ($diaSemana == 6) ? 5 : $jornada['tolerancia']; // 5min para sábado, padrão para outros dias
            
            // Se funcionário trabalhou mais que o esperado e está dentro da tolerância,
            // mostrar apenas a carga diária (horário padrão)
            if ($diferenca > 0 && $diferenca <= $toleranciaMinutos) {
                $stats['horas_trabalhadas'] = gmdate('H:i:s', $cargaDiariaMinutos * 60); // Mostrar carga diária
            } else {
                $stats['horas_trabalhadas'] = $horasTrabalhadas; // Mostrar tempo real
            }
            
            // Calcular saldo baseado na diferença total de horas trabalhadas vs esperadas
            // Usar a mesma lógica do frontend (time-calculator.js)
            
            $extrasMinutos = 0;
            $faltasMinutos = 0;
            
            if ($diferenca > 0) {
                $extrasMinutos = $diferenca;
            } else if ($diferenca < 0) {
                $faltasMinutos = abs($diferenca);
            }
            
            // Calcular saldo final: extras - faltas (mesma lógica do frontend)
            $saldoMinutos = $extrasMinutos - $faltasMinutos;
            
            // Aplicar tolerância diária
            // Usar tolerância específica para sábados (mesma lógica do frontend)
            $toleranciaMinutos = ($diaSemana == 6) ? 5 : $jornada['tolerancia']; // 5min para sábado, padrão para outros dias
            $toleranciaInfo = aplicarToleranciaDiaria($saldoMinutos, $toleranciaMinutos, $horarioSaidaEsperado);
            
            $stats['saldo'] = $toleranciaInfo['saldo_formatado'];
            $stats['status'] = $toleranciaInfo['status'];
            $stats['tipo'] = $toleranciaInfo['tipo'];
            $stats['saldo_bruto'] = $toleranciaInfo['saldo_bruto'];
            $stats['tolerancia_aplicada'] = $toleranciaInfo['tolerancia_aplicada'];
            $stats['dentro_tolerancia'] = $toleranciaInfo['dentro_tolerancia'];
            
            // Adicionar informações de sugestão de saída se aplicável
            if (isset($toleranciaInfo['sugestao_saida'])) {
                $stats['sugestao_saida'] = $toleranciaInfo['sugestao_saida'];
                $stats['pode_sair_antes'] = $toleranciaInfo['pode_sair_antes'];
            }
        }
        
        $estatisticas[] = $stats;
    }
    
    jsonResponse(true, 'Registros recuperados com sucesso', [
        'registros' => $registros,
        'estatisticas' => $estatisticas,
        'jornada' => $jornada,
        'usuario' => [
            'id' => $_SESSION['user_id'],
            'nome' => $_SESSION['user_name']
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse(false, 'Erro interno do servidor: ' . $e->getMessage());
}
?>