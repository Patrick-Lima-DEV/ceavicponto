<?php
/**
 * Classe para integração de justificativas com o sistema de ponto
 */

class JustificativaIntegrator {
    private $pdo;
    
    public function __construct($pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            // Usar a conexão global se disponível
            global $db;
            if ($db) {
                $this->pdo = $db->getConnection();
            } else {
                require_once __DIR__ . '/../config/database.php';
                $database = new Database();
                $this->pdo = $database->getConnection();
            }
        }
    }
    
    /**
     * Verifica se um funcionário tem justificativa para uma data/período específico
     */
    public function verificarJustificativa($funcionarioId, $data, $periodo = 'integral') {
        try {
            $sql = "
                SELECT j.*, tj.codigo as tipo_codigo, tj.nome as tipo_nome,
                       tj.abate_falta, tj.bloqueia_ponto
                FROM justificativas j
                JOIN tipos_justificativa tj ON j.tipo_justificativa_id = tj.id
                WHERE j.funcionario_id = ? 
                AND j.status = 'ativa'
                AND (
                    (j.data_inicio <= ? AND (j.data_fim >= ? OR j.data_fim IS NULL))
                    OR (j.data_inicio = ? AND j.data_fim IS NULL)
                )
            ";
            
            $params = [$funcionarioId, $data, $data, $data];
            
            if ($periodo !== 'integral') {
                $sql .= " AND (j.periodo_parcial = ? OR j.periodo_parcial = 'integral')";
                $params[] = $periodo;
            }
            
            $sql .= " ORDER BY j.periodo_parcial DESC LIMIT 1";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao verificar justificativa: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica se o lançamento de ponto deve ser bloqueado
     */
    public function deveBloquearPonto($funcionarioId, $data, $periodo = 'integral') {
        $justificativa = $this->verificarJustificativa($funcionarioId, $data, $periodo);
        
        if ($justificativa && $justificativa['bloqueia_ponto']) {
            return [
                'bloquear' => true,
                'tipo' => $justificativa['tipo_codigo'],
                'motivo' => $justificativa['motivo']
            ];
        }
        
        return ['bloquear' => false];
    }
    
    /**
     * Verifica se uma falta deve ser abatida
     */
    public function deveAbaterFalta($funcionarioId, $data, $periodo = 'integral') {
        $justificativa = $this->verificarJustificativa($funcionarioId, $data, $periodo);
        
        if ($justificativa && $justificativa['abate_falta']) {
            return [
                'abater' => true,
                'tipo' => $justificativa['tipo_codigo'],
                'motivo' => $justificativa['motivo']
            ];
        }
        
        return ['abater' => false];
    }
    
    /**
     * Obtém indicador para espelho de ponto
     */
    public function obterIndicadorEspelho($funcionarioId, $data, $periodo = 'integral') {
        $justificativa = $this->verificarJustificativa($funcionarioId, $data, $periodo);
        
        if (!$justificativa) {
            return null;
        }
        
        $indicadores = [
            'FER' => 'FA', // Férias
            'ATM' => 'AT', // Atestado Médico
            'AJP' => 'FJ', // Ausência Justificada Parcial
            'LIC' => 'LC', // Licença CLT
            'FOL' => 'FG'  // Folga Autorizada
        ];
        
        $indicador = $indicadores[$justificativa['tipo_codigo']] ?? 'FJ';
        
        return [
            'indicador' => $indicador,
            'tipo' => $justificativa['tipo_codigo'],
            'motivo' => $justificativa['motivo'],
            'periodo' => $justificativa['periodo_parcial']
        ];
    }
    
    /**
     * Processa justificativas para um período específico
     */
    public function processarJustificativasPeriodo($funcionarioId, $dataInicio, $dataFim) {
        try {
            $sql = "
                SELECT j.*, tj.codigo as tipo_codigo, tj.nome as tipo_nome,
                       tj.abate_falta, tj.bloqueia_ponto
                FROM justificativas j
                JOIN tipos_justificativa tj ON j.tipo_justificativa_id = tj.id
                WHERE j.funcionario_id = ? 
                AND j.status = 'ativa'
                AND (
                    (j.data_inicio <= ? AND (j.data_fim >= ? OR j.data_fim IS NULL))
                    OR (j.data_inicio <= ? AND (j.data_fim >= ? OR j.data_fim IS NULL))
                )
                ORDER BY j.data_inicio, j.periodo_parcial
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$funcionarioId, $dataFim, $dataInicio, $dataInicio, $dataFim]);
            
            $justificativas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Processar cada data do período
            $resultado = [];
            $dataAtual = new DateTime($dataInicio);
            $dataFimObj = new DateTime($dataFim);
            
            while ($dataAtual <= $dataFimObj) {
                $dataStr = $dataAtual->format('Y-m-d');
                $resultado[$dataStr] = [
                    'manha' => null,
                    'tarde' => null,
                    'integral' => null
                ];
                
                // Verificar justificativas para esta data
                foreach ($justificativas as $justificativa) {
                    $justInicio = new DateTime($justificativa['data_inicio']);
                    $justFim = $justificativa['data_fim'] ? new DateTime($justificativa['data_fim']) : $justInicio;
                    
                    if ($dataAtual >= $justInicio && $dataAtual <= $justFim) {
                        $periodo = $justificativa['periodo_parcial'];
                        
                        if ($periodo === 'integral') {
                            $resultado[$dataStr]['manha'] = $justificativa;
                            $resultado[$dataStr]['tarde'] = $justificativa;
                            $resultado[$dataStr]['integral'] = $justificativa;
                        } else {
                            $resultado[$dataStr][$periodo] = $justificativa;
                        }
                    }
                }
                
                $dataAtual->add(new DateInterval('P1D'));
            }
            
            return $resultado;
        } catch (Exception $e) {
            error_log("Erro ao processar justificativas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calcula estatísticas de justificativas para um funcionário
     */
    public function calcularEstatisticasJustificativas($funcionarioId, $dataInicio, $dataFim) {
        try {
            $sql = "
                SELECT 
                    tj.codigo as tipo_codigo,
                    tj.nome as tipo_nome,
                    COUNT(*) as quantidade,
                    SUM(CASE 
                        WHEN j.data_fim IS NULL THEN 1
                        ELSE (julianday(j.data_fim) - julianday(j.data_inicio) + 1)
                    END) as total_dias
                FROM justificativas j
                JOIN tipos_justificativa tj ON j.tipo_justificativa_id = tj.id
                WHERE j.funcionario_id = ? 
                AND j.status = 'ativa'
                AND j.data_inicio <= ?
                AND (j.data_fim >= ? OR j.data_fim IS NULL)
                GROUP BY tj.id, tj.codigo, tj.nome
                ORDER BY tj.nome
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$funcionarioId, $dataFim, $dataInicio]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao calcular estatísticas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verifica se há justificativas pendentes de expiração
     */
    public function verificarJustificativasExpiradas() {
        try {
            $sql = "
                SELECT j.*, u.nome as funcionario_nome
                FROM justificativas j
                JOIN usuarios u ON j.funcionario_id = u.id
                WHERE j.status = 'ativa'
                AND j.data_fim < DATE('now')
            ";
            
            $stmt = $this->pdo->query($sql);
            $expiradas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Marcar como expiradas
            if (!empty($expiradas)) {
                $ids = array_column($expiradas, 'id');
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                
                $updateStmt = $this->pdo->prepare("
                    UPDATE justificativas 
                    SET status = 'expirada', atualizado_em = CURRENT_TIMESTAMP
                    WHERE id IN ($placeholders)
                ");
                $updateStmt->execute($ids);
                
                // Log de auditoria
                foreach ($expiradas as $justificativa) {
                    $this->logAuditoria($justificativa['id'], 'expirada', $justificativa, null, 1); // Sistema
                }
            }
            
            return $expiradas;
        } catch (Exception $e) {
            error_log("Erro ao verificar justificativas expiradas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém resumo de justificativas para dashboard
     */
    public function obterResumoJustificativas($dataInicio = null, $dataFim = null) {
        try {
            if (!$dataInicio) $dataInicio = date('Y-m-01'); // Primeiro dia do mês
            if (!$dataFim) $dataFim = date('Y-m-t'); // Último dia do mês
            
            $sql = "
                SELECT 
                    COUNT(*) as total_justificativas,
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
                WHERE j.data_inicio <= ? AND (j.data_fim >= ? OR j.data_fim IS NULL)
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$dataFim, $dataInicio]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao obter resumo: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Registra log de auditoria
     */
    private function logAuditoria($justificativaId, $acao, $dadosAnteriores, $dadosNovos, $usuarioId) {
        try {
            $stmt = $this->pdo->prepare("
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
            error_log("Erro ao registrar log de auditoria: " . $e->getMessage());
        }
    }
}
?>
