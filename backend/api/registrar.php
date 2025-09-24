<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método não permitido');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['tipo'])) {
    jsonResponse(false, 'Tipo de registro é obrigatório');
}

$tiposValidos = ['entrada_manha', 'saida_almoco', 'volta_almoco', 'saida_tarde'];
if (!in_array($input['tipo'], $tiposValidos)) {
    jsonResponse(false, 'Tipo de registro inválido');
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $userId = $_SESSION['user_id'];
    $dataHoje = date('Y-m-d');
    $horaAtual = date('H:i:s');
    $tipo = $input['tipo'];
    
    // Verificar se já existe registro do mesmo tipo hoje
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM registros 
        WHERE usuario_id = ? AND data = ? AND tipo = ?
    ");
    $stmt->execute([$userId, $dataHoje, $tipo]);
    
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Já existe um registro deste tipo para hoje');
    }
    
    // Validações de sequência
    $stmt = $conn->prepare("
        SELECT tipo FROM registros 
        WHERE usuario_id = ? AND data = ? 
        ORDER BY hora ASC
    ");
    $stmt->execute([$userId, $dataHoje]);
    $registrosHoje = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Verificar sequência lógica
    $sequenciaCorreta = ['entrada_manha', 'saida_almoco', 'volta_almoco', 'saida_tarde'];
    $proximoEsperado = $sequenciaCorreta[count($registrosHoje)];
    
    if ($tipo !== $proximoEsperado) {
        $nomesTipos = [
            'entrada_manha' => 'Entrada',
            'saida_almoco' => 'Saída para Almoço',
            'volta_almoco' => 'Volta do Almoço',
            'saida_tarde' => 'Saída'
        ];
        jsonResponse(false, "Próximo registro esperado: " . $nomesTipos[$proximoEsperado]);
    }
    
    // Inserir registro
    $stmt = $conn->prepare("
        INSERT INTO registros (usuario_id, data, hora, tipo) 
        VALUES (?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$userId, $dataHoje, $horaAtual, $tipo])) {
        jsonResponse(true, 'Ponto registrado com sucesso', [
            'registro' => [
                'data' => $dataHoje,
                'hora' => $horaAtual,
                'tipo' => $tipo
            ]
        ]);
    } else {
        jsonResponse(false, 'Erro ao registrar ponto');
    }
    
} catch (Exception $e) {
    jsonResponse(false, 'Erro interno do servidor: ' . $e->getMessage());
}
?>