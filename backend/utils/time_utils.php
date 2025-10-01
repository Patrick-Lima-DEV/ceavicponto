<?php
/**
 * Utilitários para processamento de tempo e arredondamento
 */

/**
 * Arredondar horário para o minuto mais próximo (lógica generosa)
 * 0-16 segundos: arredondar para baixo
 * 17-59 segundos: arredondar para cima
 * 
 * @param string $timeString Horário no formato HH:MM:SS ou HH:MM
 * @return string Horário arredondado no formato HH:MM
 */
function arredondarHorario($timeString) {
    if (empty($timeString) || $timeString === '--') {
        return $timeString;
    }
    
    $parts = explode(':', $timeString);
    $hours = (int) $parts[0];
    $minutes = (int) $parts[1];
    $seconds = isset($parts[2]) ? (int) $parts[2] : 0;
    
    // Arredondar segundos para minutos (lógica generosa)
    if ($seconds >= 17) {
        $minutes += 1;
    }
    
    // Ajustar se passou de 59 minutos
    if ($minutes >= 60) {
        $hours += 1;
        $minutes = 0;
    }
    
    // Ajustar se passou de 23 horas
    if ($hours >= 24) {
        $hours = 0;
    }
    
    return sprintf('%02d:%02d', $hours, $minutes);
}

/**
 * Converter horário para minutos totais (com arredondamento)
 * 
 * @param string $timeString Horário no formato HH:MM:SS ou HH:MM
 * @return int Total de minutos
 */
function timeToMinutes($timeString) {
    if (empty($timeString) || $timeString === '--') {
        return 0;
    }
    
    // Primeiro arredondar o horário
    $horarioArredondado = arredondarHorario($timeString);
    
    $parts = explode(':', $horarioArredondado);
    $hours = (int) $parts[0];
    $minutes = (int) $parts[1];
    
    return $hours * 60 + $minutes;
}

/**
 * Converter minutos para string de tempo
 * 
 * @param int $totalMinutes Total de minutos
 * @return string Horário no formato HH:MM
 */
function minutesToTime($totalMinutes) {
    if ($totalMinutes < 0) {
        return '-' . minutesToTime(abs($totalMinutes));
    }
    
    $hours = floor($totalMinutes / 60);
    $minutes = floor($totalMinutes % 60);
    
    return sprintf('%02d:%02d', $hours, $minutes);
}

/**
 * Calcular diferença entre dois horários em minutos
 * 
 * @param string $horaInicio Horário de início
 * @param string $horaFim Horário de fim
 * @return int Diferença em minutos
 */
function calcularDiferencaTempo($horaInicio, $horaFim) {
    $inicioMinutos = timeToMinutes($horaInicio);
    $fimMinutos = timeToMinutes($horaFim);
    
    $diferenca = $fimMinutos - $inicioMinutos;
    
    // Se a hora final for menor que a inicial (passou da meia-noite)
    if ($diferenca < 0) {
        $diferenca += 24 * 60; // Adicionar 24 horas em minutos
    }
    
    return $diferenca;
}

/**
 * Processar array de horários aplicando arredondamento
 * 
 * @param array $horarios Array de horários
 * @return array Array com horários arredondados
 */
function processarHorariosComArredondamento($horarios) {
    $horariosProcessados = [];
    
    foreach ($horarios as $key => $horario) {
        if (is_string($horario)) {
            $horariosProcessados[$key] = arredondarHorario($horario);
        } else {
            $horariosProcessados[$key] = $horario;
        }
    }
    
    return $horariosProcessados;
}

/**
 * Aplicar tolerância diária ao saldo de horas
 * Se o saldo estiver dentro da tolerância, retorna 00:00
 * 
 * @param int $saldoMinutos Saldo em minutos (pode ser negativo)
 * @param int $toleranciaMinutos Tolerância em minutos (padrão: 10)
 * @param string $horarioSaidaEsperado Horário de saída esperado (formato HH:MM) - opcional
 * @return array Array com saldo formatado, status e informações
 */
function aplicarToleranciaDiaria($saldoMinutos, $toleranciaMinutos = 10, $horarioSaidaEsperado = null) {
    $saldoAbsoluto = abs($saldoMinutos);
    
    if ($saldoAbsoluto <= $toleranciaMinutos) {
        // Dentro da tolerância - zerar o saldo
        $resultado = [
            'saldo_formatado' => '00:00',
            'status' => 'normal',
            'tipo' => 'dentro_da_tolerancia',
            'saldo_bruto' => $saldoMinutos,
            'tolerancia_aplicada' => $toleranciaMinutos,
            'dentro_tolerancia' => true
        ];
        
        // Se funcionário trabalhou mais que o esperado (saldo positivo) e temos horário de saída esperado,
        // sugerir que pode sair no horário padrão
        if ($saldoMinutos > 0 && $horarioSaidaEsperado) {
            $resultado['sugestao_saida'] = $horarioSaidaEsperado;
            $resultado['pode_sair_antes'] = true;
        }
        
        return $resultado;
    } else {
        // Fora da tolerância - manter saldo real
        $saldoFormatado = $saldoMinutos >= 0 ? 
            '+' . sprintf('%02d:%02d', floor($saldoMinutos / 60), $saldoMinutos % 60) :
            '-' . sprintf('%02d:%02d', floor(abs($saldoMinutos) / 60), abs($saldoMinutos) % 60);
            
        return [
            'saldo_formatado' => $saldoFormatado,
            'status' => $saldoMinutos >= 0 ? 'extras' : 'faltas',
            'tipo' => $saldoMinutos >= 0 ? 'extra' : 'falta',
            'saldo_bruto' => $saldoMinutos,
            'tolerancia_aplicada' => $toleranciaMinutos,
            'dentro_tolerancia' => false
        ];
    }
}
?>
