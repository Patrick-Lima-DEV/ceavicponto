<?php
/**
 * Teste do Sistema de Atualizações
 * Tech-Ponto - Sistema de Controle de Ponto Eletrônico
 * 
 * Este arquivo testa se o sistema de atualizações está funcionando corretamente
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/config.php';

echo "<h1>🔧 Teste do Sistema de Atualizações</h1>\n";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} .info{color:blue;}</style>\n";

// Teste 1: Verificar configurações
echo "<h2>1. Verificação de Configurações</h2>\n";

$configErrors = validateUpdateConfig();
if (empty($configErrors)) {
    echo "<p class='success'>✅ Configurações válidas</p>\n";
} else {
    echo "<p class='error'>❌ Erros de configuração:</p>\n";
    foreach ($configErrors as $error) {
        echo "<p class='error'>- {$error}</p>\n";
    }
}

// Teste 2: Verificar diretórios
echo "<h2>2. Verificação de Diretórios</h2>\n";

$directories = [
    'BACKUP_PATH' => BACKUP_PATH,
    'TEMP_PATH' => TEMP_PATH,
    'LOG_PATH' => LOG_PATH
];

foreach ($directories as $name => $path) {
    if (is_dir($path)) {
        if (is_writable($path)) {
            echo "<p class='success'>✅ {$name}: {$path} (existe e é gravável)</p>\n";
        } else {
            echo "<p class='warning'>⚠️ {$name}: {$path} (existe mas não é gravável)</p>\n";
        }
    } else {
        echo "<p class='error'>❌ {$name}: {$path} (não existe)</p>\n";
    }
}

// Teste 3: Verificar arquivos críticos
echo "<h2>3. Verificação de Arquivos Críticos</h2>\n";

$criticalFiles = getUpdateConfig('critical_files');
foreach ($criticalFiles as $file) {
    $fullPath = dirname(__DIR__) . '/' . $file;
    if (file_exists($fullPath)) {
        echo "<p class='success'>✅ {$file}</p>\n";
    } else {
        echo "<p class='error'>❌ {$file} (não encontrado)</p>\n";
    }
}

// Teste 4: Testar classes
echo "<h2>4. Teste de Classes</h2>\n";

try {
    require_once __DIR__ . '/update_checker.php';
    $checker = new UpdateChecker();
    echo "<p class='success'>✅ UpdateChecker carregada com sucesso</p>\n";
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro ao carregar UpdateChecker: " . $e->getMessage() . "</p>\n";
}

try {
    require_once __DIR__ . '/backup_manager.php';
    $backupManager = new BackupManager();
    echo "<p class='success'>✅ BackupManager carregada com sucesso</p>\n";
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro ao carregar BackupManager: " . $e->getMessage() . "</p>\n";
}

try {
    require_once __DIR__ . '/update_installer.php';
    $installer = new UpdateInstaller();
    echo "<p class='success'>✅ UpdateInstaller carregada com sucesso</p>\n";
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro ao carregar UpdateInstaller: " . $e->getMessage() . "</p>\n";
}

// Teste 5: Verificar conectividade GitHub
echo "<h2>5. Teste de Conectividade GitHub</h2>\n";

$repoOwner = getUpdateConfig('github_repo_owner');
$repoName = getUpdateConfig('github_repo_name');

if (empty($repoOwner) || empty($repoName)) {
    echo "<p class='warning'>⚠️ Repositório GitHub não configurado</p>\n";
} else {
    $url = "https://api.github.com/repos/{$repoOwner}/{$repoName}";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'User-Agent: TechPonto-Test',
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        if (isset($data['name'])) {
            echo "<p class='success'>✅ Repositório GitHub acessível: {$data['name']}</p>\n";
        } else {
            echo "<p class='warning'>⚠️ Repositório GitHub acessível mas resposta inválida</p>\n";
        }
    } else {
        echo "<p class='error'>❌ Não foi possível acessar o repositório GitHub</p>\n";
    }
}

// Teste 6: Verificar banco de dados
echo "<h2>6. Teste de Banco de Dados</h2>\n";

try {
    $dbPath = dirname(__DIR__) . '/data/techponto.db';
    if (file_exists($dbPath)) {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->query('SELECT COUNT(*) FROM usuarios');
        $count = $stmt->fetchColumn();
        
        echo "<p class='success'>✅ Banco de dados acessível ({$count} usuários)</p>\n";
    } else {
        echo "<p class='error'>❌ Banco de dados não encontrado</p>\n";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro ao acessar banco de dados: " . $e->getMessage() . "</p>\n";
}

// Teste 7: Verificar logs
echo "<h2>7. Teste de Sistema de Logs</h2>\n";

try {
    logUpdateOperation('TEST_SYSTEM', 'Teste do sistema de atualizações', 'INFO');
    echo "<p class='success'>✅ Sistema de logs funcionando</p>\n";
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro no sistema de logs: " . $e->getMessage() . "</p>\n";
}

// Resumo
echo "<h2>📊 Resumo do Teste</h2>\n";
echo "<p class='info'>Teste concluído em: " . date('Y-m-d H:i:s') . "</p>\n";

if (empty($configErrors)) {
    echo "<p class='success'><strong>✅ Sistema de atualizações está pronto para uso!</strong></p>\n";
} else {
    echo "<p class='warning'><strong>⚠️ Corrija os erros acima antes de usar o sistema de atualizações</strong></p>\n";
}

echo "<hr>\n";
echo "<p><a href='../admin.html'>← Voltar ao Painel Admin</a></p>\n";
?>
