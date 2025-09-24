<?php
require_once '../config/config.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Método não permitido');
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $userId = $_SESSION['user_id'];
    $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d');
    $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
    
    // Buscar registros do período
    $stmt = $conn->prepare("
        SELECT r.*, u.nome as usuario_nome 
        FROM registros r 
        JOIN usuarios u ON r.usuario_id = u.id 
        WHERE r.usuario_id = ? AND r.data BETWEEN ? AND ? 
        ORDER BY r.data DESC, r.hora ASC
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
        
        $stats = [
            'data' => $data,
            'registros' => $registrosDia,
            'completo' => count($registrosDia) >= 4,
            'horas_trabalhadas' => '00:00:00',
            'horas_esperadas' => $jornada['carga_diaria'] ?? '08:00:00',
            'saldo' => '00:00:00',
            'status' => 'incompleto'
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
            $stats['horas_trabalhadas'] = $horasTrabalhadas;
            
            // Calcular saldo baseado na diferença total de horas trabalhadas vs esperadas
            // Usar a mesma lógica do frontend (time-calculator.js)
            
            // Determinar carga baseada no dia da semana (mesma lógica do frontend)
            $diaSemana = date('w', strtotime($data)); // 0 = domingo, 6 = sábado
            $cargaDiariaMinutos = ($diaSemana == 6) ? 240 : 480; // 4h para sábado, 8h para outros dias
            
            $horasTrabalhadasMinutos = $trabalhado / 60; // Converter segundos para minutos
            $diferenca = $horasTrabalhadasMinutos - $cargaDiariaMinutos;
            
            $extrasMinutos = 0;
            $faltasMinutos = 0;
            
            if ($diferenca > 0) {
                $extrasMinutos = $diferenca;
            } else if ($diferenca < 0) {
                $faltasMinutos = abs($diferenca);
            }
            
            // Calcular saldo final: extras - faltas (mesma lógica do frontend)
            $saldoMinutos = $extrasMinutos - $faltasMinutos;
            $saldoAbsoluto = abs($saldoMinutos);
            
            // Sempre mostrar o saldo real, mesmo dentro da tolerância
            $saldoSegundos = $saldoMinutos * 60;
            if ($saldoMinutos >= 0) {
                $stats['saldo'] = '+' . gmdate('H:i:s', $saldoSegundos);
                $stats['status'] = 'extras';
            } else {
                $stats['saldo'] = '-' . gmdate('H:i:s', abs($saldoSegundos));
                $stats['status'] = 'faltas';
            }
            
            // Apenas marcar como normal se estiver dentro da tolerância
            // Usar tolerância específica para sábados (mesma lógica do frontend)
            $toleranciaMinutos = ($diaSemana == 6) ? 5 : $jornada['tolerancia']; // 5min para sábado, padrão para outros dias
            if ($saldoAbsoluto <= $toleranciaMinutos) {
                $stats['status'] = 'normal';
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