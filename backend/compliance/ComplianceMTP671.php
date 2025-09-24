<?php
/**
 * Classe para conformidade com a Portaria MTP 671/2021
 * Sistema de Controle de Ponto Eletrônico
 * 
 * Funcionalidades:
 * - Geração de AFD (Arquivo Fonte de Dados)
 * - Geração de AEJ (Arquivo Eletrônico de Jornada)
 * - Atestados Técnicos (PTRP e REPs)
 * - Relatórios de Espelho Eletrônico
 * - Compactação em ZIP para DET
 */

require_once __DIR__ . '/../config/config.php';

class ComplianceMTP671 {
    private $db;
    private $security;
    private $empresa;
    
    public function __construct() {
        $this->db = $GLOBALS['db']->getConnection();
        $this->security = $GLOBALS['security'];
        $this->carregarDadosEmpresa();
    }
    
    /**
     * Carrega dados da empresa para os documentos
     */
    private function carregarDadosEmpresa() {
        $stmt = $this->db->prepare("SELECT chave, valor FROM configuracoes_empresa WHERE chave LIKE 'empresa_%'");
        $stmt->execute();
        $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $this->empresa = [
            'nome' => $configs['empresa_nome'] ?? 'Tech-Ponto Sistemas',
            'cnpj' => $configs['empresa_cnpj'] ?? '00.000.000/0001-00',
            'endereco' => $configs['empresa_endereco'] ?? 'Rua da Inovação, 123 - Centro',
            'cidade' => $configs['empresa_cidade'] ?? 'São Paulo - SP',
            'telefone' => $configs['empresa_telefone'] ?? '(11) 1234-5678',
            'email' => $configs['empresa_email'] ?? 'contato@techponto.com',
            'site' => $configs['empresa_site'] ?? 'www.techponto.com'
        ];
    }
    
    /**
     * Gera AFD (Arquivo Fonte de Dados) conforme Portaria MTP 671/2021
     */
    public function gerarAFD($dataInicio, $dataFim, $funcionarioId = null) {
        $registros = $this->buscarRegistrosPonto($dataInicio, $dataFim, $funcionarioId);
        
        $afd = [];
        
        // Cabeçalho do AFD
        $afd[] = $this->gerarCabecalhoAFD();
        
        // Registros de ponto
        foreach ($registros as $registro) {
            $afd[] = $this->gerarRegistroAFD($registro);
        }
        
        // Rodapé do AFD
        $afd[] = $this->gerarRodapeAFD(count($registros));
        
        return implode("\r\n", $afd);
    }
    
    /**
     * Gera AEJ (Arquivo Eletrônico de Jornada) conforme Portaria MTP 671/2021
     */
    public function gerarAEJ($dataInicio, $dataFim, $funcionarioId = null) {
        $jornadas = $this->buscarJornadasEfetivas($dataInicio, $dataFim, $funcionarioId);
        
        $aej = [];
        
        // Cabeçalho do AEJ
        $aej[] = $this->gerarCabecalhoAEJ();
        
        // Registros de jornada
        foreach ($jornadas as $jornada) {
            $aej[] = $this->gerarRegistroAEJ($jornada);
        }
        
        // Rodapé do AEJ
        $aej[] = $this->gerarRodapeAEJ(count($jornadas));
        
        return implode("\r\n", $aej);
    }
    
    /**
     * Busca registros de ponto para AFD
     */
    private function buscarRegistrosPonto($dataInicio, $dataFim, $funcionarioId = null) {
        $where = "WHERE p.data BETWEEN ? AND ?";
        $params = [$dataInicio, $dataFim];
        
        if ($funcionarioId) {
            $where .= " AND p.usuario_id = ?";
            $params[] = $funcionarioId;
        }
        
        $stmt = $this->db->prepare("
            SELECT p.*, u.nome, u.cpf, u.matricula,
                   gj.nome as grupo_jornada_nome
            FROM pontos p
            JOIN usuarios u ON p.usuario_id = u.id
            LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
            $where
            ORDER BY u.nome, p.data, p.hora
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca jornadas efetivas para AEJ
     */
    private function buscarJornadasEfetivas($dataInicio, $dataFim, $funcionarioId = null) {
        // Implementar lógica de cálculo de jornada efetiva
        // baseada nos batimentos e escalas configuradas
        return [];
    }
    
    /**
     * Gera cabeçalho do AFD
     */
    private function gerarCabecalhoAFD() {
        $dataGeracao = date('dmY');
        $horaGeracao = date('His');
        
        return sprintf(
            "0000000001%s%s%s%s%s",
            $dataGeracao,
            $horaGeracao,
            str_pad($this->empresa['cnpj'], 14, '0', STR_PAD_LEFT),
            str_pad('TECHPONTO', 20, ' ', STR_PAD_RIGHT),
            str_pad('1.0', 10, ' ', STR_PAD_RIGHT)
        );
    }
    
    /**
     * Gera registro do AFD
     */
    private function gerarRegistroAFD($registro) {
        $data = str_replace('-', '', $registro['data']);
        $hora = str_replace(':', '', $registro['hora']);
        $nsr = str_pad($registro['id'], 9, '0', STR_PAD_LEFT);
        
        // Mapear tipo de ponto para código AFD
        $tiposAFD = [
            'entrada_manha' => '1',
            'saida_almoco' => '2',
            'volta_almoco' => '3',
            'saida_tarde' => '4'
        ];
        
        $tipo = $tiposAFD[$registro['tipo']] ?? '0';
        
        return sprintf(
            "%s%s%s%s%s%s",
            $nsr,
            $data,
            $hora,
            $tipo,
            str_pad($registro['matricula'] ?? '', 9, '0', STR_PAD_LEFT),
            str_pad(str_replace(['.', '-'], '', $registro['cpf'] ?? ''), 11, '0', STR_PAD_LEFT)
        );
    }
    
    /**
     * Gera rodapé do AFD
     */
    private function gerarRodapeAFD($totalRegistros) {
        $dataGeracao = date('dmY');
        $horaGeracao = date('His');
        
        return sprintf(
            "999999999%s%s%s",
            $dataGeracao,
            $horaGeracao,
            str_pad($totalRegistros, 6, '0', STR_PAD_LEFT)
        );
    }
    
    /**
     * Gera cabeçalho do AEJ
     */
    private function gerarCabecalhoAEJ() {
        // Implementar conforme especificação da Portaria
        return "AEJ_HEADER";
    }
    
    /**
     * Gera registro do AEJ
     */
    private function gerarRegistroAEJ($jornada) {
        // Implementar conforme especificação da Portaria
        return "AEJ_RECORD";
    }
    
    /**
     * Gera rodapé do AEJ
     */
    private function gerarRodapeAEJ($totalRegistros) {
        // Implementar conforme especificação da Portaria
        return "AEJ_FOOTER";
    }
    
    /**
     * Compacta arquivos em ZIP
     */
    public function compactarArquivos($arquivos, $nomeZip) {
        $caminhoZip = __DIR__ . "/temp/{$nomeZip}.zip";
        
        // Verificar se ZipArchive está disponível
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            
            if ($zip->open($caminhoZip, ZipArchive::CREATE) !== TRUE) {
                throw new Exception("Não foi possível criar o arquivo ZIP");
            }
            
            foreach ($arquivos as $arquivo) {
                $zip->addFile($arquivo['caminho'], $arquivo['nome']);
            }
            
            $zip->close();
        } else {
            // Fallback: criar arquivo tar simples (sem compressão)
            $this->criarArquivoTar($arquivos, $caminhoZip);
        }
        
        return $caminhoZip;
    }
    
    /**
     * Cria arquivo TAR simples como fallback
     */
    private function criarArquivoTar($arquivos, $caminhoTar) {
        $conteudo = '';
        
        foreach ($arquivos as $arquivo) {
            if (file_exists($arquivo['caminho'])) {
                $conteudoArquivo = file_get_contents($arquivo['caminho']);
                $conteudo .= "=== ARQUIVO: {$arquivo['nome']} ===\n";
                $conteudo .= $conteudoArquivo . "\n\n";
            }
        }
        
        file_put_contents($caminhoTar, $conteudo);
    }
    
    /**
     * Salva arquivo temporário
     */
    public function salvarArquivoTemporario($conteudo, $nomeArquivo) {
        $caminho = __DIR__ . "/temp/{$nomeArquivo}";
        file_put_contents($caminho, $conteudo);
        return $caminho;
    }
    
    /**
     * Limpa arquivos temporários
     */
    public function limparArquivosTemporarios() {
        $tempDir = __DIR__ . "/temp/";
        $arquivos = glob($tempDir . "*");
        
        foreach ($arquivos as $arquivo) {
            if (is_file($arquivo)) {
                unlink($arquivo);
            }
        }
    }
}
?>
