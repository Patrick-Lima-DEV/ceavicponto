<?php
/**
 * Gerador de PDFs para conformidade MTP 671/2021
 * Atestados Técnicos e Relatórios
 */

require_once __DIR__ . '/../config/config.php';

class PDFGenerator {
    private $empresa;
    private $db;
    
    public function __construct() {
        $this->db = $GLOBALS['db']->getConnection();
        $this->carregarDadosEmpresa();
    }
    
    /**
     * Carrega dados da empresa
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
            'email' => $configs['empresa_email'] ?? 'contato@techponto.com'
        ];
    }
    
    /**
     * Gera Atestado Técnico do PTRP
     */
    public function gerarAtestadoPTRP() {
        // Gerar conteúdo do PDF
        $conteudo = $this->gerarConteudoAtestadoPTRP();
        return $this->salvarHTML($conteudo, 'Atestado_Tecnico_PTRP.html');
    }
    
    /**
     * Gera Atestado Técnico dos REPs
     */
    public function gerarAtestadoREPs() {
        // Gerar conteúdo do PDF
        $conteudo = $this->gerarConteudoAtestadoREPs();
        return $this->salvarHTML($conteudo, 'Atestado_Tecnico_REPs.html');
    }
    
    /**
     * Gera Espelho Eletrônico de Ponto
     */
    public function gerarEspelhoEletronico($funcionarioId, $dataInicio, $dataFim) {
        $dados = $this->buscarDadosEspelho($funcionarioId, $dataInicio, $dataFim);
        $conteudo = $this->gerarConteudoEspelho($dados);
        return $this->salvarHTML($conteudo, 'Espelho_Eletronico.html');
    }
    
    /**
     * Gera conteúdo do Atestado PTRP
     */
    private function gerarConteudoAtestadoPTRP() {
        $dataAtual = date('d/m/Y');
        
        return "
        ATESTADO TÉCNICO E TERMO DE RESPONSABILIDADE
        DO PROGRAMA DE TRATAMENTO DE REGISTROS DE PONTO (PTRP)
        
        Conforme Portaria MTP 671/2021
        
        DADOS DO SOFTWARE:
        Nome: Tech-Ponto Sistemas
        Versão: 1.0
        Desenvolvedor: Tech-Ponto Sistemas
        CNPJ: {$this->empresa['cnpj']}
        
        DADOS DO RESPONSÁVEL TÉCNICO:
        Nome: [NOME DO RESPONSÁVEL TÉCNICO]
        CPF: [CPF DO RESPONSÁVEL TÉCNICO]
        CREA: [NÚMERO DO CREA]
        
        DECLARAÇÃO:
        Declaro, sob as penas da lei, que o software Tech-Ponto Sistemas
        atende aos requisitos técnicos estabelecidos na Portaria MTP 671/2021
        para o tratamento de registros de ponto eletrônico.
        
        Data: {$dataAtual}
        
        _________________________________
        Assinatura do Responsável Técnico
        
        _________________________________
        Nome Completo
        
        _________________________________
        CPF
        ";
    }
    
    /**
     * Gera conteúdo do Atestado REPs
     */
    private function gerarConteudoAtestadoREPs() {
        $dataAtual = date('d/m/Y');
        
        return "
        ATESTADO TÉCNICO E TERMO DE RESPONSABILIDADE
        DOS REPOSITÓRIOS ELETRÔNICOS DE PONTO (REPs)
        
        Conforme Portaria MTP 671/2021
        
        DADOS DO SISTEMA DE CAPTURA:
        Nome: Tech-Ponto Web
        Versão: 1.0
        Tipo: Sistema Web com QR Code
        Desenvolvedor: Tech-Ponto Sistemas
        CNPJ: {$this->empresa['cnpj']}
        
        DADOS DO RESPONSÁVEL TÉCNICO:
        Nome: [NOME DO RESPONSÁVEL TÉCNICO]
        CPF: [CPF DO RESPONSÁVEL TÉCNICO]
        CREA: [NÚMERO DO CREA]
        
        DECLARAÇÃO:
        Declaro, sob as penas da lei, que o sistema de captura Tech-Ponto Web
        atende aos requisitos técnicos estabelecidos na Portaria MTP 671/2021
        para captura de registros de ponto eletrônico.
        
        Data: {$dataAtual}
        
        _________________________________
        Assinatura do Responsável Técnico
        
        _________________________________
        Nome Completo
        
        _________________________________
        CPF
        ";
    }
    
    /**
     * Busca dados para espelho eletrônico
     */
    private function buscarDadosEspelho($funcionarioId, $dataInicio, $dataFim) {
        $stmt = $this->db->prepare("
            SELECT u.nome, u.cpf, u.matricula,
                   d.nome as departamento_nome,
                   gj.nome as grupo_jornada_nome
            FROM usuarios u
            LEFT JOIN departamentos d ON u.departamento_id = d.id
            LEFT JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id
            WHERE u.id = ?
        ");
        $stmt->execute([$funcionarioId]);
        $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Buscar registros de ponto
        $stmt = $this->db->prepare("
            SELECT p.*
            FROM pontos p
            WHERE p.usuario_id = ? AND p.data BETWEEN ? AND ?
            ORDER BY p.data, p.hora
        ");
        $stmt->execute([$funcionarioId, $dataInicio, $dataFim]);
        $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'funcionario' => $funcionario,
            'pontos' => $pontos,
            'periodo' => ['inicio' => $dataInicio, 'fim' => $dataFim]
        ];
    }
    
    /**
     * Gera conteúdo do espelho eletrônico
     */
    private function gerarConteudoEspelho($dados) {
        $funcionario = $dados['funcionario'];
        $pontos = $dados['pontos'];
        $periodo = $dados['periodo'];
        
        $conteudo = "
        ESPELHO ELETRÔNICO DE PONTO
        Artigo 84 da Portaria MTP 671/2021
        
        DADOS DO FUNCIONÁRIO:
        Nome: {$funcionario['nome']}
        CPF: {$funcionario['cpf']}
        Matrícula: {$funcionario['matricula']}
        Departamento: {$funcionario['departamento_nome']}
        Grupo de Jornada: {$funcionario['grupo_jornada_nome']}
        
        PERÍODO: {$periodo['inicio']} a {$periodo['fim']}
        
        REGISTROS DE PONTO:
        ";
        
        foreach ($pontos as $ponto) {
            $conteudo .= "Data: {$ponto['data']} | Hora: {$ponto['hora']} | Tipo: {$ponto['tipo']}\n";
        }
        
        $conteudo .= "
        
        _________________________________
        Assinatura do Funcionário
        
        _________________________________
        Nome Completo
        
        Data: " . date('d/m/Y') . "
        ";
        
        return $conteudo;
    }
    
    /**
     * Salva como HTML (pode ser impresso como PDF)
     */
    private function salvarHTML($conteudo, $nomeArquivo) {
        $html = $this->gerarHTMLCompleto($conteudo);
        $caminho = __DIR__ . "/temp/{$nomeArquivo}";
        file_put_contents($caminho, $html);
        return $caminho;
    }
    
    /**
     * Gera HTML completo com estilos para impressão
     */
    private function gerarHTMLCompleto($conteudo) {
        $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento - Tech-Ponto</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .content {
            white-space: pre-line;
            font-size: 14px;
        }
        .signature-section {
            margin-top: 50px;
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }
        .signature-line {
            border-bottom: 1px solid #333;
            width: 300px;
            margin: 20px 0;
        }
        @media print {
            body { margin: 0; padding: 15px; }
            .no-print { display: none; }
        }
        .print-button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Imprimir / Salvar como PDF</button>
    
    <div class="header">
        <h1>Tech-Ponto Sistemas</h1>
        <p>Sistema de Controle de Ponto Eletrônico</p>
    </div>
    
    <div class="content">' . htmlspecialchars($conteudo) . '</div>
    
    <div class="signature-section">
        <p><strong>Data de Geração:</strong> ' . date('d/m/Y H:i:s') . '</p>
        <p><strong>Sistema:</strong> Tech-Ponto v2.0</p>
    </div>
</body>
</html>';
        
        return $html;
    }
}
?>
