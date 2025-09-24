/**
 * JavaScript para Conformidade MTP 671/2021
 * Geração de AFD, AEJ, Atestados e Relatórios
 */

// Variáveis globais
let funcionariosCompliance = [];
let arquivosGerados = [];

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    carregarFuncionariosCompliance();
    configurarEventListenersCompliance();
});

/**
 * Carrega lista de funcionários para os modais
 */
async function carregarFuncionariosCompliance() {
    try {
        const response = await fetch('../backend/api/compliance.php?action=funcionarios', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            funcionariosCompliance = data.data.funcionarios;
            popularSelectsFuncionarios();
        } else {
            mostrarNotificacaoComplianceCompliance('Erro ao carregar funcionários: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao carregar funcionários:', error);
        mostrarNotificacaoComplianceCompliance('Erro ao carregar funcionários', 'error');
    }
}

/**
 * Popula os selects de funcionários nos modais
 */
function popularSelectsFuncionarios() {
    const selects = ['afdFuncionario', 'aejFuncionario', 'espelhoFuncionario'];
    
    selects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (select) {
            // Limpar opções existentes (exceto a primeira)
            while (select.children.length > 1) {
                select.removeChild(select.lastChild);
            }
            
            // Adicionar funcionários
            funcionariosCompliance.forEach(funcionario => {
                const option = document.createElement('option');
                option.value = funcionario.id;
                option.textContent = `${funcionario.nome} (${funcionario.matricula || 'Sem matrícula'})`;
                select.appendChild(option);
            });
        }
    });
}

/**
 * Configura event listeners para conformidade
 */
function configurarEventListenersCompliance() {
    // Form AFD
    const afdForm = document.getElementById('afdForm');
    if (afdForm) {
        afdForm.addEventListener('submit', function(e) {
            e.preventDefault();
            gerarAFD();
        });
    }
    
    // Form AEJ
    const aejForm = document.getElementById('aejForm');
    if (aejForm) {
        aejForm.addEventListener('submit', function(e) {
            e.preventDefault();
            gerarAEJ();
        });
    }
    
    // Form Espelho
    const espelhoForm = document.getElementById('espelhoForm');
    if (espelhoForm) {
        espelhoForm.addEventListener('submit', function(e) {
            e.preventDefault();
            gerarEspelhoEletronico();
        });
    }
    
    // Form Compactação
    const compactacaoForm = document.getElementById('compactacaoForm');
    if (compactacaoForm) {
        compactacaoForm.addEventListener('submit', function(e) {
            e.preventDefault();
            compactarArquivos();
        });
    }
}

// ===== MODAIS =====

/**
 * Abre modal AFD
 */
function abrirModalAFD() {
    const modal = document.getElementById('afdModal');
    const form = document.getElementById('afdForm');
    
    form.reset();
    
    // Definir datas padrão (último mês)
    const hoje = new Date();
    const mesPassado = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
    const ultimoDiaMesPassado = new Date(hoje.getFullYear(), hoje.getMonth(), 0);
    
    document.getElementById('afdDataInicio').value = formatarDataParaInput(mesPassado);
    document.getElementById('afdDataFim').value = formatarDataParaInput(ultimoDiaMesPassado);
    
    modal.style.display = 'block';
}

/**
 * Fecha modal AFD
 */
function fecharModalAFD() {
    document.getElementById('afdModal').style.display = 'none';
}

/**
 * Abre modal AEJ
 */
function abrirModalAEJ() {
    const modal = document.getElementById('aejModal');
    const form = document.getElementById('aejForm');
    
    form.reset();
    
    // Definir datas padrão (último mês)
    const hoje = new Date();
    const mesPassado = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
    const ultimoDiaMesPassado = new Date(hoje.getFullYear(), hoje.getMonth(), 0);
    
    document.getElementById('aejDataInicio').value = formatarDataParaInput(mesPassado);
    document.getElementById('aejDataFim').value = formatarDataParaInput(ultimoDiaMesPassado);
    
    modal.style.display = 'block';
}

/**
 * Fecha modal AEJ
 */
function fecharModalAEJ() {
    document.getElementById('aejModal').style.display = 'none';
}

/**
 * Abre modal Espelho Eletrônico
 */
function abrirModalEspelho() {
    const modal = document.getElementById('espelhoModal');
    const form = document.getElementById('espelhoForm');
    
    form.reset();
    
    // Definir datas padrão (último mês)
    const hoje = new Date();
    const mesPassado = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
    const ultimoDiaMesPassado = new Date(hoje.getFullYear(), hoje.getMonth(), 0);
    
    document.getElementById('espelhoDataInicio').value = formatarDataParaInput(mesPassado);
    document.getElementById('espelhoDataFim').value = formatarDataParaInput(ultimoDiaMesPassado);
    
    modal.style.display = 'block';
}

/**
 * Fecha modal Espelho Eletrônico
 */
function fecharModalEspelho() {
    document.getElementById('espelhoModal').style.display = 'none';
}

/**
 * Abre modal Compactação
 */
function abrirModalCompactacao() {
    const modal = document.getElementById('compactacaoModal');
    const form = document.getElementById('compactacaoForm');
    
    form.reset();
    
    // Definir nome padrão
    const hoje = new Date();
    const nomePadrao = `arquivos_det_${hoje.getFullYear()}${(hoje.getMonth() + 1).toString().padStart(2, '0')}`;
    document.getElementById('compactacaoNome').value = nomePadrao;
    
    // Mostrar arquivos disponíveis
    mostrarArquivosDisponiveis();
    
    modal.style.display = 'block';
}

/**
 * Fecha modal Compactação
 */
function fecharModalCompactacao() {
    document.getElementById('compactacaoModal').style.display = 'none';
}

// ===== GERAÇÃO DE ARQUIVOS =====

/**
 * Gera AFD
 */
async function gerarAFD() {
    const dataInicio = document.getElementById('afdDataInicio').value;
    const dataFim = document.getElementById('afdDataFim').value;
    const funcionarioId = document.getElementById('afdFuncionario').value;
    const nomeArquivo = document.getElementById('afdNomeArquivo').value;
    
    if (!dataInicio || !dataFim) {
        mostrarNotificacaoComplianceCompliance('Data início e fim são obrigatórias', 'error');
        return;
    }
    
    try {
        mostrarLoadingCompliance('Gerando AFD...');
        
        const response = await fetch('../backend/api/compliance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'gerar_afd',
                data_inicio: dataInicio,
                data_fim: dataFim,
                funcionario_id: funcionarioId || null,
                nome_arquivo: nomeArquivo
            })
        });
        
        const data = await response.json();
        
        esconderLoadingCompliance();
        
        if (data.success) {
            mostrarNotificacaoComplianceCompliance('AFD gerado com sucesso!', 'success');
            
            // Adicionar à lista de arquivos gerados
            arquivosGerados.push({
                tipo: 'AFD',
                arquivo: data.data.arquivo,
                caminho: data.data.caminho,
                tamanho: data.data.tamanho,
                data: new Date().toLocaleString()
            });
            
            // Fechar modal e baixar arquivo
            fecharModalAFD();
            downloadArquivo(data.data.arquivo, 'afd');
            
            // Atualizar histórico
            atualizarHistoricoCompliance();
        } else {
            mostrarNotificacaoCompliance('Erro ao gerar AFD: ' + data.message, 'error');
        }
    } catch (error) {
        esconderLoadingCompliance();
        console.error('Erro ao gerar AFD:', error);
        mostrarNotificacaoCompliance('Erro ao gerar AFD', 'error');
    }
}

/**
 * Gera AEJ
 */
async function gerarAEJ() {
    const dataInicio = document.getElementById('aejDataInicio').value;
    const dataFim = document.getElementById('aejDataFim').value;
    const funcionarioId = document.getElementById('aejFuncionario').value;
    const nomeArquivo = document.getElementById('aejNomeArquivo').value;
    
    if (!dataInicio || !dataFim) {
        mostrarNotificacaoComplianceCompliance('Data início e fim são obrigatórias', 'error');
        return;
    }
    
    try {
        mostrarLoadingCompliance('Gerando AEJ...');
        
        const response = await fetch('../backend/api/compliance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'gerar_aej',
                data_inicio: dataInicio,
                data_fim: dataFim,
                funcionario_id: funcionarioId || null,
                nome_arquivo: nomeArquivo
            })
        });
        
        const data = await response.json();
        
        esconderLoadingCompliance();
        
        if (data.success) {
            mostrarNotificacaoCompliance('AEJ gerado com sucesso!', 'success');
            
            // Adicionar à lista de arquivos gerados
            arquivosGerados.push({
                tipo: 'AEJ',
                arquivo: data.data.arquivo,
                caminho: data.data.caminho,
                tamanho: data.data.tamanho,
                data: new Date().toLocaleString()
            });
            
            // Fechar modal e baixar arquivo
            fecharModalAEJ();
            downloadArquivo(data.data.arquivo, 'aej');
            
            // Atualizar histórico
            atualizarHistoricoCompliance();
        } else {
            mostrarNotificacaoCompliance('Erro ao gerar AEJ: ' + data.message, 'error');
        }
    } catch (error) {
        esconderLoadingCompliance();
        console.error('Erro ao gerar AEJ:', error);
        mostrarNotificacaoCompliance('Erro ao gerar AEJ', 'error');
    }
}

/**
 * Gera Atestado PTRP
 */
async function gerarAtestadoPTRP() {
    try {
        mostrarLoadingCompliance('Gerando Atestado PTRP...');
        
        const response = await fetch('../backend/api/compliance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'gerar_atestado_ptrp'
            })
        });
        
        const data = await response.json();
        
        esconderLoadingCompliance();
        
        if (data.success) {
            mostrarNotificacaoCompliance('Atestado PTRP gerado com sucesso!', 'success');
            downloadArquivo(data.data.arquivo, 'html');
        } else {
            mostrarNotificacaoCompliance('Erro ao gerar Atestado PTRP: ' + data.message, 'error');
        }
    } catch (error) {
        esconderLoadingCompliance();
        console.error('Erro ao gerar Atestado PTRP:', error);
        mostrarNotificacaoCompliance('Erro ao gerar Atestado PTRP', 'error');
    }
}

/**
 * Gera Atestado REPs
 */
async function gerarAtestadoREPs() {
    try {
        mostrarLoadingCompliance('Gerando Atestado REPs...');
        
        const response = await fetch('../backend/api/compliance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'gerar_atestado_reps'
            })
        });
        
        const data = await response.json();
        
        esconderLoadingCompliance();
        
        if (data.success) {
            mostrarNotificacaoCompliance('Atestado REPs gerado com sucesso!', 'success');
            downloadArquivo(data.data.arquivo, 'html');
        } else {
            mostrarNotificacaoCompliance('Erro ao gerar Atestado REPs: ' + data.message, 'error');
        }
    } catch (error) {
        esconderLoadingCompliance();
        console.error('Erro ao gerar Atestado REPs:', error);
        mostrarNotificacaoCompliance('Erro ao gerar Atestado REPs', 'error');
    }
}

/**
 * Gera Espelho Eletrônico
 */
async function gerarEspelhoEletronico() {
    const funcionarioId = document.getElementById('espelhoFuncionario').value;
    const dataInicio = document.getElementById('espelhoDataInicio').value;
    const dataFim = document.getElementById('espelhoDataFim').value;
    
    if (!funcionarioId || !dataInicio || !dataFim) {
        mostrarNotificacaoCompliance('Todos os campos são obrigatórios', 'error');
        return;
    }
    
    try {
        mostrarLoadingCompliance('Gerando Espelho Eletrônico...');
        
        const response = await fetch('../backend/api/compliance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'gerar_espelho',
                funcionario_id: funcionarioId,
                data_inicio: dataInicio,
                data_fim: dataFim
            })
        });
        
        const data = await response.json();
        
        esconderLoadingCompliance();
        
        if (data.success) {
            mostrarNotificacaoCompliance('Espelho Eletrônico gerado com sucesso!', 'success');
            
            // Fechar modal e baixar arquivo
            fecharModalEspelho();
            downloadArquivo(data.data.arquivo, 'html');
        } else {
            mostrarNotificacaoCompliance('Erro ao gerar Espelho Eletrônico: ' + data.message, 'error');
        }
    } catch (error) {
        esconderLoadingCompliance();
        console.error('Erro ao gerar Espelho Eletrônico:', error);
        mostrarNotificacaoCompliance('Erro ao gerar Espelho Eletrônico', 'error');
    }
}

/**
 * Compacta arquivos
 */
async function compactarArquivos() {
    const nomeZip = document.getElementById('compactacaoNome').value;
    const arquivosSelecionados = obterArquivosSelecionados();
    
    if (arquivosSelecionados.length === 0) {
        mostrarNotificacaoCompliance('Selecione pelo menos um arquivo para compactar', 'error');
        return;
    }
    
    try {
        mostrarLoadingCompliance('Compactando arquivos...');
        
        const response = await fetch('../backend/api/compliance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'compactar_arquivos',
                nome_zip: nomeZip,
                arquivos: arquivosSelecionados
            })
        });
        
        const data = await response.json();
        
        esconderLoadingCompliance();
        
        if (data.success) {
            mostrarNotificacaoCompliance('Arquivos compactados com sucesso!', 'success');
            
            // Fechar modal e baixar arquivo
            fecharModalCompactacao();
            downloadArquivo(data.data.arquivo, 'zip');
        } else {
            mostrarNotificacaoCompliance('Erro ao compactar arquivos: ' + data.message, 'error');
        }
    } catch (error) {
        esconderLoadingCompliance();
        console.error('Erro ao compactar arquivos:', error);
        mostrarNotificacaoCompliance('Erro ao compactar arquivos', 'error');
    }
}

// ===== FUNÇÕES AUXILIARES =====

/**
 * Mostra loading para compliance
 */
function mostrarLoadingCompliance(mensagem = 'Carregando...') {
    // Criar overlay de loading se não existir
    let loadingOverlay = document.getElementById('loadingOverlay');
    if (!loadingOverlay) {
        loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'loadingOverlay';
        loadingOverlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        `;
        
        const loadingContent = document.createElement('div');
        loadingContent.style.cssText = `
            background: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        `;
        
        loadingContent.innerHTML = `
            <div style="margin-bottom: 15px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #007bff;"></i>
            </div>
            <div id="loadingMessage" style="color: #333; font-weight: 500;">${mensagem}</div>
        `;
        
        loadingOverlay.appendChild(loadingContent);
        document.body.appendChild(loadingOverlay);
    } else {
        document.getElementById('loadingMessage').textContent = mensagem;
        loadingOverlay.style.display = 'flex';
    }
}

/**
 * Esconde loading para compliance
 */
function esconderLoadingCompliance() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'none';
    }
}

/**
 * Mostra notificação para compliance
 */
function mostrarNotificacaoComplianceCompliance(mensagem, tipo = 'info') {
    // Criar notificação se não existir
    let notification = document.getElementById('notification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'notification';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            z-index: 10001;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            cursor: pointer;
            user-select: none;
        `;
        document.body.appendChild(notification);
    }
    
    // Definir cor baseada no tipo
    const cores = {
        'success': '#28a745',
        'error': '#dc3545',
        'warning': '#ffc107',
        'info': '#17a2b8'
    };
    
    notification.style.backgroundColor = cores[tipo] || cores['info'];
    notification.innerHTML = `
        <span style="float: right; margin-left: 10px; font-weight: bold; opacity: 0.7;">×</span>
        ${mensagem}
    `;
    
    // Adicionar evento de clique para fechar
    notification.addEventListener('click', () => {
        fecharNotificacao(notification);
    });
    
    // Mostrar notificação
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Esconder após 5 segundos
    setTimeout(() => {
        fecharNotificacao(notification);
    }, 5000);
}

/**
 * Fecha notificação com animação
 */
function fecharNotificacao(notification) {
    if (!notification || !notification.parentNode) return;
    
    notification.style.transform = 'translateX(100%)';
    
    // Remover elemento do DOM após a animação
    setTimeout(() => {
        if (notification && notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 300); // Aguardar a transição de 0.3s
}

/**
 * Baixa arquivo
 */
function downloadArquivo(nomeArquivo, tipo) {
    const url = `../backend/api/compliance.php?action=download&arquivo=${encodeURIComponent(nomeArquivo)}&tipo=${tipo}`;
    window.open(url, '_blank');
}

/**
 * Mostra arquivos disponíveis para compactação
 */
function mostrarArquivosDisponiveis() {
    const container = document.getElementById('arquivosSelecionados');
    
    if (arquivosGerados.length === 0) {
        container.innerHTML = '<p style="color: #666; text-align: center;">Nenhum arquivo gerado ainda</p>';
        return;
    }
    
    let html = '<div style="max-height: 200px; overflow-y: auto;">';
    
    arquivosGerados.forEach((arquivo, index) => {
        html += `
            <div style="display: flex; align-items: center; padding: 5px; border-bottom: 1px solid #eee;">
                <input type="checkbox" id="arquivo_${index}" value="${index}" style="margin-right: 10px;">
                <label for="arquivo_${index}" style="flex: 1; cursor: pointer;">
                    <strong>${arquivo.tipo}</strong> - ${arquivo.arquivo}
                    <br><small style="color: #666;">${arquivo.data} (${formatarTamanho(arquivo.tamanho)})</small>
                </label>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

/**
 * Obtém arquivos selecionados para compactação
 */
function obterArquivosSelecionados() {
    const checkboxes = document.querySelectorAll('#arquivosSelecionados input[type="checkbox"]:checked');
    const selecionados = [];
    
    checkboxes.forEach(checkbox => {
        const index = parseInt(checkbox.value);
        const arquivo = arquivosGerados[index];
        if (arquivo) {
            selecionados.push({
                caminho: arquivo.caminho,
                nome: arquivo.arquivo
            });
        }
    });
    
    return selecionados;
}

/**
 * Atualiza histórico de compliance
 */
function atualizarHistoricoCompliance() {
    const tbody = document.getElementById('complianceHistoryTableBody');
    
    if (arquivosGerados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px;">Nenhum arquivo gerado ainda</td></tr>';
        return;
    }
    
    let html = '';
    arquivosGerados.forEach(arquivo => {
        html += `
            <tr>
                <td>${arquivo.data}</td>
                <td><span class="badge badge-${getBadgeClass(arquivo.tipo)}">${arquivo.tipo}</span></td>
                <td>-</td>
                <td>-</td>
                <td>${arquivo.arquivo}</td>
                <td><span class="badge badge-success">Gerado</span></td>
                <td>
                    <button class="btn-icon" onclick="downloadArquivo('${arquivo.arquivo}', '${getTipoArquivo(arquivo.tipo)}')" title="Download">
                        <i class="fas fa-download"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

/**
 * Obtém classe do badge baseada no tipo
 */
function getBadgeClass(tipo) {
    const classes = {
        'AFD': 'primary',
        'AEJ': 'info',
        'PTRP': 'warning',
        'REPs': 'warning',
        'Espelho': 'success'
    };
    return classes[tipo] || 'secondary';
}

/**
 * Obtém tipo de arquivo para download
 */
function getTipoArquivo(tipo) {
    const tipos = {
        'AFD': 'txt',
        'AEJ': 'txt',
        'PTRP': 'html',
        'REPs': 'html',
        'Espelho': 'html'
    };
    return tipos[tipo] || 'txt';
}

/**
 * Formata tamanho de arquivo
 */
function formatarTamanho(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Formata data para input
 */
function formatarDataParaInput(data) {
    return data.toISOString().split('T')[0];
}
