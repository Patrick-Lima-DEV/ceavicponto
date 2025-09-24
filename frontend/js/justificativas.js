/**
 * JavaScript para gerenciamento de Justificativas
 * Módulo integrado ao controle de ponto
 */

// Variáveis globais
let justificativas = [];
let funcionariosJustificativas = [];
let tiposJustificativa = [];
let paginacaoJustificativas = {
    page: 1,
    limit: 20,
    total: 0,
    pages: 0
};

// ===== INICIALIZAÇÃO =====

/**
 * Inicializa o módulo de justificativas
 */
function inicializarJustificativas() {
    carregarDadosIniciaisJustificativas();
    configurarEventListenersJustificativas();
}

/**
 * Carrega dados iniciais necessários
 */
async function carregarDadosIniciaisJustificativas() {
    try {
        await Promise.all([
            carregarFuncionariosJustificativas(),
            carregarTiposJustificativa()
        ]);
        
        popularSelectsJustificativas();
        configurarFiltrosJustificativas();
    } catch (error) {
        console.error('Erro ao carregar dados iniciais:', error);
        mostrarNotificacao('Erro ao carregar dados iniciais', 'error');
    }
}

/**
 * Carrega lista de funcionários
 */
async function carregarFuncionariosJustificativas() {
    try {
        const response = await fetch('../backend/api/justificativas.php?action=funcionarios');
        const data = await response.json();
        
        if (data.success) {
            funcionariosJustificativas = data.data.funcionarios;
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Erro ao carregar funcionários:', error);
        throw error;
    }
}

/**
 * Carrega tipos de justificativa
 */
async function carregarTiposJustificativa() {
    try {
        const response = await fetch('../backend/api/justificativas.php?action=tipos');
        const data = await response.json();
        
        if (data.success) {
            tiposJustificativa = data.data.tipos;
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Erro ao carregar tipos de justificativa:', error);
        throw error;
    }
}

/**
 * Popula os selects com dados carregados
 */
function popularSelectsJustificativas() {
    // Select de funcionários
    const selectFuncionario = document.getElementById('filtroFuncionarioJust');
    const selectFuncionarioModal = document.getElementById('funcionarioJustificativa');
    const selectFuncionarioEdicao = document.getElementById('editarFuncionarioJustificativa');
    
    [selectFuncionario, selectFuncionarioModal, selectFuncionarioEdicao].forEach(select => {
        if (select) {
            select.innerHTML = '<option value="">Selecione um funcionário</option>';
            funcionariosJustificativas.forEach(funcionario => {
                const option = document.createElement('option');
                option.value = funcionario.id;
                option.textContent = `${funcionario.nome} (${funcionario.matricula})`;
                select.appendChild(option);
            });
        }
    });
    
    // Select de tipos
    const selectTipo = document.getElementById('filtroTipoJust');
    const selectTipoModal = document.getElementById('tipoJustificativa');
    const selectTipoEdicao = document.getElementById('editarTipoJustificativa');
    
    [selectTipo, selectTipoModal, selectTipoEdicao].forEach(select => {
        if (select) {
            select.innerHTML = '<option value="">Selecione o tipo</option>';
            tiposJustificativa.forEach(tipo => {
                const option = document.createElement('option');
                option.value = tipo.id;
                option.textContent = tipo.nome;
                option.dataset.codigo = tipo.codigo;
                option.dataset.abateFalta = tipo.abate_falta;
                option.dataset.bloqueiaPonto = tipo.bloqueia_ponto;
                select.appendChild(option);
            });
        }
    });
}

/**
 * Configura event listeners
 */
function configurarEventListenersJustificativas() {
    // Filtros
    const filtros = [
        'filtroFuncionarioJust',
        'filtroDataInicioJust',
        'filtroDataFimJust',
        'filtroTipoJust',
        'filtroStatusJust'
    ];
    
    filtros.forEach(id => {
        const elemento = document.getElementById(id);
        if (elemento) {
            elemento.addEventListener('change', () => {
                if (id === 'filtroDataInicioJust' || id === 'filtroDataFimJust') {
                    validarFiltrosDatas();
                }
            });
        }
    });
}

/**
 * Configura filtros padrão
 */
function configurarFiltrosJustificativas() {
    // Definir datas padrão (período mais amplo para incluir dados de teste)
    const hoje = new Date();
    const primeiroDia = new Date(2024, 0, 1); // 1º de janeiro de 2024
    const ultimoDia = new Date(hoje.getFullYear() + 1, 11, 31); // 31 de dezembro do próximo ano
    
    document.getElementById('filtroDataInicioJust').value = primeiroDia.toISOString().split('T')[0];
    document.getElementById('filtroDataFimJust').value = ultimoDia.toISOString().split('T')[0];
}

// ===== FILTROS E BUSCA =====

/**
 * Valida filtros de data
 */
function validarFiltrosDatas() {
    const dataInicio = document.getElementById('filtroDataInicioJust').value;
    const dataFim = document.getElementById('filtroDataFimJust').value;
    
    if (dataInicio && dataFim && dataFim < dataInicio) {
        mostrarNotificacao('Data fim não pode ser anterior à data início', 'warning');
        document.getElementById('filtroDataFimJust').value = dataInicio;
    }
}

/**
 * Busca justificativas com filtros
 */
async function buscarJustificativas() {
    try {
        
        const funcionarioId = document.getElementById('filtroFuncionarioJust').value;
        const dataInicio = document.getElementById('filtroDataInicioJust').value;
        const dataFim = document.getElementById('filtroDataFimJust').value;
        const tipoId = document.getElementById('filtroTipoJust').value;
        const status = document.getElementById('filtroStatusJust').value;
        
        if (!dataInicio || !dataFim) {
            mostrarNotificacao('Selecione o período para buscar as justificativas', 'warning');
            return;
        }
        
        const btnBuscar = document.querySelector('button[onclick="buscarJustificativas()"]');
        mostrarLoading(btnBuscar);
        
        let url = `../backend/api/justificativas.php?action=listar&data_inicio=${dataInicio}&data_fim=${dataFim}&page=${paginacaoJustificativas.page}&limit=${paginacaoJustificativas.limit}`;
        
        if (funcionarioId) url += `&funcionario_id=${funcionarioId}`;
        if (tipoId) url += `&tipo_id=${tipoId}`;
        if (status) url += `&status=${status}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        mostrarLoading(btnBuscar, false);
        
        if (data.success) {
            justificativas = data.data.justificativas;
            paginacaoJustificativas = data.data.pagination;
            
            exibirJustificativas();
            exibirEstatisticasJustificativas();
            atualizarPaginacaoJustificativas();
        } else {
            mostrarNotificacao('Erro ao buscar justificativas: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao buscar justificativas:', error);
        mostrarNotificacao('Erro ao buscar justificativas', 'error');
    }
}

/**
 * Exibe justificativas na tabela
 */
function exibirJustificativas() {
    const tbody = document.getElementById('justificativasTableBody');
    
    if (justificativas.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px;">
                    Nenhuma justificativa encontrada para os filtros selecionados
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = justificativas.map(justificativa => `
        <tr>
            <td>
                <div>
                    <strong>${justificativa.funcionario_nome}</strong><br>
                    <small>${justificativa.matricula}</small>
                </div>
            </td>
            <td>
                <span class="badge badge-${getCorTipoJustificativa(justificativa.tipo_codigo)}">
                    ${justificativa.tipo_nome}
                </span>
            </td>
            <td>${formatarData(justificativa.data_inicio)}</td>
            <td>${justificativa.data_fim ? formatarData(justificativa.data_fim) : '-'}</td>
            <td>
                <span class="badge badge-info">
                    ${getTextoPeriodo(justificativa.periodo_parcial)}
                </span>
            </td>
            <td>
                <span class="badge badge-${getCorStatusJustificativa(justificativa.status)}">
                    ${getTextoStatusJustificativa(justificativa.status)}
                </span>
            </td>
            <td>${formatarDataHora(justificativa.criado_em)}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn-action btn-info" onclick="visualizarJustificativa(${justificativa.id})" title="Visualizar">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${justificativa.status === 'ativa' ? `
                        <button class="btn-action btn-warning" onclick="editarJustificativaModal(${justificativa.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action btn-danger" onclick="cancelarJustificativa(${justificativa.id})" title="Cancelar">
                            <i class="fas fa-ban"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

/**
 * Exibe estatísticas de justificativas
 */
function exibirEstatisticasJustificativas() {
    const statsContainer = document.getElementById('statsJustificativas');
    
    if (justificativas.length === 0) {
        statsContainer.style.display = 'none';
        return;
    }
    
    const stats = calcularEstatisticasJustificativas();
    
    document.getElementById('totalJustificativas').textContent = stats.total;
    document.getElementById('justificativasAtivas').textContent = stats.ativas;
    document.getElementById('totalFerias').textContent = stats.ferias;
    document.getElementById('totalAtestados').textContent = stats.atestados;
    
    statsContainer.style.display = 'grid';
}

/**
 * Calcula estatísticas das justificativas
 */
function calcularEstatisticasJustificativas() {
    return justificativas.reduce((stats, justificativa) => {
        stats.total++;
        if (justificativa.status === 'ativa') stats.ativas++;
        if (justificativa.tipo_codigo === 'FER') stats.ferias++;
        if (justificativa.tipo_codigo === 'ATM') stats.atestados++;
        return stats;
    }, { total: 0, ativas: 0, ferias: 0, atestados: 0 });
}

/**
 * Atualiza controles de paginação
 */
function atualizarPaginacaoJustificativas() {
    const pagination = document.getElementById('paginationJustificativas');
    const info = document.getElementById('paginationInfoJustificativas');
    const btnPrev = pagination.querySelector('button:first-child');
    const btnNext = pagination.querySelector('button:last-child');
    
    if (paginacaoJustificativas.pages <= 1) {
        pagination.style.display = 'none';
        return;
    }
    
    pagination.style.display = 'flex';
    info.textContent = `Página ${paginacaoJustificativas.page} de ${paginacaoJustificativas.pages}`;
    
    btnPrev.disabled = paginacaoJustificativas.page <= 1;
    btnNext.disabled = paginacaoJustificativas.page >= paginacaoJustificativas.pages;
}

/**
 * Navega entre páginas
 */
function paginarJustificativas(direcao) {
    if (direcao === 'prev' && paginacaoJustificativas.page > 1) {
        paginacaoJustificativas.page--;
    } else if (direcao === 'next' && paginacaoJustificativas.page < paginacaoJustificativas.pages) {
        paginacaoJustificativas.page++;
    }
    
    buscarJustificativas();
}

/**
 * Limpa filtros
 */
function limparFiltrosJustificativas() {
    document.getElementById('filtroFuncionarioJust').value = '';
    document.getElementById('filtroTipoJust').value = '';
    document.getElementById('filtroStatusJust').value = '';
    configurarFiltrosJustificativas();
    
    // Limpar tabela
    const tbody = document.getElementById('justificativasTableBody');
    tbody.innerHTML = `
        <tr>
            <td colspan="8" style="text-align: center; padding: 40px;">
                Selecione os filtros e clique em "Buscar" para visualizar as justificativas
            </td>
        </tr>
    `;
    
    document.getElementById('statsJustificativas').style.display = 'none';
    document.getElementById('paginationJustificativas').style.display = 'none';
}

// ===== MODAIS =====

/**
 * Abre modal para nova justificativa
 */
function abrirModalNovaJustificativa() {
    
    // Limpar formulário
    document.getElementById('novaJustificativaForm').reset();
    document.getElementById('infoTipoJustificativa').style.display = 'none';
    document.getElementById('conflitosJustificativa').style.display = 'none';
    
    // Definir data padrão (hoje)
    document.getElementById('dataInicioJustificativa').value = new Date().toISOString().split('T')[0];
    
    document.getElementById('novaJustificativaModal').style.display = 'flex';
}

/**
 * Fecha modal de nova justificativa
 */
function fecharModalNovaJustificativa() {
    document.getElementById('novaJustificativaModal').style.display = 'none';
}

/**
 * Abre modal para editar justificativa
 */
async function editarJustificativaModal(id) {
    try {
        
        const response = await fetch(`../backend/api/justificativas.php?action=detalhes&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            const justificativa = data.data.justificativa;
            
            // Preencher formulário
            document.getElementById('editarJustificativaId').value = justificativa.id;
            document.getElementById('editarFuncionarioJustificativa').value = justificativa.funcionario_id;
            document.getElementById('editarTipoJustificativa').value = justificativa.tipo_justificativa_id;
            document.getElementById('editarDataInicioJustificativa').value = justificativa.data_inicio;
            document.getElementById('editarDataFimJustificativa').value = justificativa.data_fim || '';
            document.getElementById('editarPeriodoJustificativa').value = justificativa.periodo_parcial;
            document.getElementById('editarMotivoJustificativa').value = justificativa.motivo;
            
            // Atualizar informações do tipo
            atualizarCamposTipoEdicao();
            
            document.getElementById('editarJustificativaModal').style.display = 'flex';
        } else {
            mostrarNotificacao('Erro ao carregar justificativa: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao abrir modal de edição:', error);
        mostrarNotificacao('Erro ao carregar justificativa', 'error');
    }
}

/**
 * Fecha modal de editar justificativa
 */
function fecharModalEditarJustificativa() {
    document.getElementById('editarJustificativaModal').style.display = 'none';
}

/**
 * Visualiza detalhes da justificativa
 */
async function visualizarJustificativa(id) {
    try {
        
        const response = await fetch(`../backend/api/justificativas.php?action=detalhes&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            const justificativa = data.data.justificativa;
            
            const content = `
                <div class="justificativa-detalhes">
                    <div class="detail-section">
                        <h4>Informações Básicas</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Funcionário:</label>
                                <span>${justificativa.funcionario_nome} (${justificativa.matricula})</span>
                            </div>
                            <div class="detail-item">
                                <label>Departamento:</label>
                                <span>${justificativa.departamento_nome || 'Não informado'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Tipo:</label>
                                <span class="badge badge-${getCorTipoJustificativa(justificativa.tipo_codigo)}">
                                    ${justificativa.tipo_nome}
                                </span>
                            </div>
                            <div class="detail-item">
                                <label>Status:</label>
                                <span class="badge badge-${getCorStatusJustificativa(justificativa.status)}">
                                    ${getTextoStatusJustificativa(justificativa.status)}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4>Período</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Data Início:</label>
                                <span>${formatarData(justificativa.data_inicio)}</span>
                            </div>
                            <div class="detail-item">
                                <label>Data Fim:</label>
                                <span>${justificativa.data_fim ? formatarData(justificativa.data_fim) : 'Período de um dia'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Período:</label>
                                <span class="badge badge-info">
                                    ${getTextoPeriodo(justificativa.periodo_parcial)}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4>Motivo</h4>
                        <div class="motivo-text">
                            ${justificativa.motivo}
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4>Informações do Sistema</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Criado por:</label>
                                <span>${justificativa.criado_por_nome}</span>
                            </div>
                            <div class="detail-item">
                                <label>Criado em:</label>
                                <span>${formatarDataHora(justificativa.criado_em)}</span>
                            </div>
                            ${justificativa.atualizado_por_nome ? `
                                <div class="detail-item">
                                    <label>Atualizado por:</label>
                                    <span>${justificativa.atualizado_por_nome}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Atualizado em:</label>
                                    <span>${formatarDataHora(justificativa.atualizado_em)}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4>Impacto no Sistema</h4>
                        <div class="impacto-info">
                            <div class="impacto-item">
                                <i class="fas fa-${justificativa.abate_falta ? 'check-circle text-success' : 'times-circle text-danger'}"></i>
                                <span>Abate falta: ${justificativa.abate_falta ? 'Sim' : 'Não'}</span>
                            </div>
                            <div class="impacto-item">
                                <i class="fas fa-${justificativa.bloqueia_ponto ? 'check-circle text-success' : 'times-circle text-danger'}"></i>
                                <span>Bloqueia ponto: ${justificativa.bloqueia_ponto ? 'Sim' : 'Não'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('detalhesJustificativaContent').innerHTML = content;
            document.getElementById('detalhesJustificativaModal').style.display = 'flex';
        } else {
            mostrarNotificacao('Erro ao carregar detalhes: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao visualizar justificativa:', error);
        mostrarNotificacao('Erro ao carregar detalhes', 'error');
    }
}

/**
 * Fecha modal de detalhes
 */
function fecharModalDetalhesJustificativa() {
    document.getElementById('detalhesJustificativaModal').style.display = 'none';
}

// ===== CRUD OPERATIONS =====

/**
 * Cria nova justificativa
 */
async function criarJustificativa(event) {
    event.preventDefault();
    
    try {
        
        const formData = {
            funcionario_id: document.getElementById('funcionarioJustificativa').value,
            tipo_justificativa_id: document.getElementById('tipoJustificativa').value,
            data_inicio: document.getElementById('dataInicioJustificativa').value,
            data_fim: document.getElementById('dataFimJustificativa').value || null,
            periodo_parcial: document.getElementById('periodoJustificativa').value,
            motivo: document.getElementById('motivoJustificativa').value
        };
        
        // Validar dados
        if (!formData.funcionario_id || !formData.tipo_justificativa_id || !formData.data_inicio || !formData.motivo) {
            mostrarNotificacao('Todos os campos obrigatórios devem ser preenchidos', 'error');
            return;
        }
        
        const btnSubmit = event.target.querySelector('button[type="submit"]');
        mostrarLoading(btnSubmit);
        
        const response = await fetch('../backend/api/justificativas.php?action=criar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        mostrarLoading(btnSubmit, false);
        
        if (data.success) {
            mostrarNotificacao('Justificativa criada com sucesso!', 'success');
            fecharModalNovaJustificativa();
            buscarJustificativas(); // Recarregar lista
        } else {
            mostrarNotificacao('Erro ao criar justificativa: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao criar justificativa:', error);
        mostrarNotificacao('Erro ao criar justificativa', 'error');
    }
}

/**
 * Edita justificativa existente
 */
async function editarJustificativa(event) {
    event.preventDefault();
    
    try {
        
        const formData = {
            id: document.getElementById('editarJustificativaId').value,
            funcionario_id: document.getElementById('editarFuncionarioJustificativa').value,
            tipo_justificativa_id: document.getElementById('editarTipoJustificativa').value,
            data_inicio: document.getElementById('editarDataInicioJustificativa').value,
            data_fim: document.getElementById('editarDataFimJustificativa').value || null,
            periodo_parcial: document.getElementById('editarPeriodoJustificativa').value,
            motivo: document.getElementById('editarMotivoJustificativa').value
        };
        
        const btnSubmit = event.target.querySelector('button[type="submit"]');
        mostrarLoading(btnSubmit);
        
        const response = await fetch('../backend/api/justificativas.php?action=editar', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        mostrarLoading(btnSubmit, false);
        
        if (data.success) {
            mostrarNotificacao('Justificativa atualizada com sucesso!', 'success');
            fecharModalEditarJustificativa();
            buscarJustificativas(); // Recarregar lista
        } else {
            mostrarNotificacao('Erro ao atualizar justificativa: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao editar justificativa:', error);
        mostrarNotificacao('Erro ao atualizar justificativa', 'error');
    }
}

/**
 * Cancela justificativa
 */
async function cancelarJustificativa(id) {
    
    if (!confirm('Tem certeza que deseja cancelar esta justificativa?')) {
        return;
    }
    
    try {
        const motivo = prompt('Motivo do cancelamento (opcional):') || 'Cancelada pelo administrador';
        
        const response = await fetch('../backend/api/justificativas.php?action=cancelar', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id, motivo })
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarNotificacao('Justificativa cancelada com sucesso!', 'success');
            buscarJustificativas(); // Recarregar lista
        } else {
            mostrarNotificacao('Erro ao cancelar justificativa: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao cancelar justificativa:', error);
        mostrarNotificacao('Erro ao cancelar justificativa', 'error');
    }
}

// ===== VALIDAÇÕES E AUXILIARES =====

/**
 * Atualiza campos baseados no tipo selecionado
 */
function atualizarCamposTipo() {
    const tipoSelect = document.getElementById('tipoJustificativa');
    const infoBox = document.getElementById('infoTipoJustificativa');
    const textoInfo = document.getElementById('textoInfoTipo');
    
    const selectedOption = tipoSelect.options[tipoSelect.selectedIndex];
    
    if (selectedOption.value) {
        const codigo = selectedOption.dataset.codigo;
        const abateFalta = selectedOption.dataset.abateFalta === '1';
        const bloqueiaPonto = selectedOption.dataset.bloqueiaPonto === '1';
        
        let info = '';
        if (abateFalta) info += '• Abate faltas automaticamente\n';
        if (bloqueiaPonto) info += '• Bloqueia lançamento de ponto\n';
        
        if (info) {
            textoInfo.textContent = info.trim();
            infoBox.style.display = 'block';
        } else {
            infoBox.style.display = 'none';
        }
        
        // Validar conflitos
        validarConflitosJustificativa();
    } else {
        infoBox.style.display = 'none';
    }
}

/**
 * Atualiza campos baseados no tipo selecionado (edição)
 */
function atualizarCamposTipoEdicao() {
    const tipoSelect = document.getElementById('editarTipoJustificativa');
    const infoBox = document.getElementById('editarInfoTipoJustificativa');
    const textoInfo = document.getElementById('editarTextoInfoTipo');
    
    const selectedOption = tipoSelect.options[tipoSelect.selectedIndex];
    
    if (selectedOption.value) {
        const codigo = selectedOption.dataset.codigo;
        const abateFalta = selectedOption.dataset.abateFalta === '1';
        const bloqueiaPonto = selectedOption.dataset.bloqueiaPonto === '1';
        
        let info = '';
        if (abateFalta) info += '• Abate faltas automaticamente\n';
        if (bloqueiaPonto) info += '• Bloqueia lançamento de ponto\n';
        
        if (info) {
            textoInfo.textContent = info.trim();
            infoBox.style.display = 'block';
        } else {
            infoBox.style.display = 'none';
        }
        
        // Validar conflitos
        validarConflitosJustificativaEdicao();
    } else {
        infoBox.style.display = 'none';
    }
}

/**
 * Valida datas da justificativa
 */
function validarDatasJustificativa() {
    const dataInicio = document.getElementById('dataInicioJustificativa').value;
    const dataFim = document.getElementById('dataFimJustificativa').value;
    
    if (dataInicio && dataFim && dataFim < dataInicio) {
        mostrarNotificacao('Data fim não pode ser anterior à data início', 'warning');
        document.getElementById('dataFimJustificativa').value = dataInicio;
    }
    
    // Se data fim for igual à data início, limpar data fim (tratar como um dia só)
    if (dataInicio && dataFim && dataFim === dataInicio) {
        document.getElementById('dataFimJustificativa').value = '';
        mostrarNotificacao('Data fim removida - será tratado como período de um dia', 'info');
    }
    
    validarConflitosJustificativa();
}

/**
 * Valida datas da justificativa (edição)
 */
function validarDatasJustificativaEdicao() {
    const dataInicio = document.getElementById('editarDataInicioJustificativa').value;
    const dataFim = document.getElementById('editarDataFimJustificativa').value;
    
    if (dataInicio && dataFim && dataFim < dataInicio) {
        mostrarNotificacao('Data fim não pode ser anterior à data início', 'warning');
        document.getElementById('editarDataFimJustificativa').value = dataInicio;
    }
    
    // Se data fim for igual à data início, limpar data fim (tratar como um dia só)
    if (dataInicio && dataFim && dataFim === dataInicio) {
        document.getElementById('editarDataFimJustificativa').value = '';
        mostrarNotificacao('Data fim removida - será tratado como período de um dia', 'info');
    }
    
    validarConflitosJustificativaEdicao();
}

/**
 * Valida conflitos de justificativa
 */
async function validarConflitosJustificativa() {
    const funcionarioId = document.getElementById('funcionarioJustificativa').value;
    const dataInicio = document.getElementById('dataInicioJustificativa').value;
    const dataFim = document.getElementById('dataFimJustificativa').value;
    const periodo = document.getElementById('periodoJustificativa').value;
    
    if (!funcionarioId || !dataInicio) {
        return;
    }
    
    try {
        const response = await fetch('../backend/api/justificativas.php?action=validar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                funcionario_id: funcionarioId,
                data_inicio: dataInicio,
                data_fim: dataFim,
                periodo_parcial: periodo
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.conflitos && data.conflitos.length > 0) {
            const conflitosBox = document.getElementById('conflitosJustificativa');
            const listaConflitos = document.getElementById('listaConflitos');
            
            listaConflitos.innerHTML = data.conflitos.map(conflito => 
                `<li>${conflito.tipo_nome} de ${formatarData(conflito.data_inicio)} a ${conflito.data_fim ? formatarData(conflito.data_fim) : formatarData(conflito.data_inicio)}</li>`
            ).join('');
            
            conflitosBox.style.display = 'block';
        } else {
            document.getElementById('conflitosJustificativa').style.display = 'none';
        }
    } catch (error) {
        console.error('Erro ao validar conflitos:', error);
    }
}

/**
 * Valida conflitos de justificativa (edição)
 */
async function validarConflitosJustificativaEdicao() {
    const funcionarioId = document.getElementById('editarFuncionarioJustificativa').value;
    const dataInicio = document.getElementById('editarDataInicioJustificativa').value;
    const dataFim = document.getElementById('editarDataFimJustificativa').value;
    const periodo = document.getElementById('editarPeriodoJustificativa').value;
    const id = document.getElementById('editarJustificativaId').value;
    
    if (!funcionarioId || !dataInicio) {
        return;
    }
    
    try {
        const response = await fetch('../backend/api/justificativas.php?action=validar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                funcionario_id: funcionarioId,
                data_inicio: dataInicio,
                data_fim: dataFim,
                periodo_parcial: periodo,
                id: id
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.conflitos && data.conflitos.length > 0) {
            const conflitosBox = document.getElementById('editarConflitosJustificativa');
            const listaConflitos = document.getElementById('editarListaConflitos');
            
            listaConflitos.innerHTML = data.conflitos.map(conflito => 
                `<li>${conflito.tipo_nome} de ${formatarData(conflito.data_inicio)} a ${conflito.data_fim ? formatarData(conflito.data_fim) : formatarData(conflito.data_inicio)}</li>`
            ).join('');
            
            conflitosBox.style.display = 'block';
        } else {
            document.getElementById('editarConflitosJustificativa').style.display = 'none';
        }
    } catch (error) {
        console.error('Erro ao validar conflitos:', error);
    }
}

// ===== FUNÇÕES AUXILIARES =====

/**
 * Obtém cor do badge baseada no tipo
 */
function getCorTipoJustificativa(codigo) {
    const cores = {
        'FER': 'primary',
        'ATM': 'warning',
        'AJP': 'info',
        'LIC': 'success',
        'FOL': 'secondary'
    };
    return cores[codigo] || 'secondary';
}

/**
 * Obtém cor do badge baseada no status
 */
function getCorStatusJustificativa(status) {
    const cores = {
        'ativa': 'success',
        'cancelada': 'danger',
        'expirada': 'warning'
    };
    return cores[status] || 'secondary';
}

/**
 * Obtém texto do status
 */
function getTextoStatusJustificativa(status) {
    const textos = {
        'ativa': 'Ativa',
        'cancelada': 'Cancelada',
        'expirada': 'Expirada'
    };
    return textos[status] || status;
}

/**
 * Obtém texto do período
 */
function getTextoPeriodo(periodo) {
    const textos = {
        'integral': 'Integral',
        'manha': 'Manhã',
        'tarde': 'Tarde'
    };
    return textos[periodo] || periodo;
}

// ===== INICIALIZAÇÃO AUTOMÁTICA =====

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se estamos na aba de justificativas
    if (window.location.hash === '#justificativas' || document.getElementById('justificativas-tab')) {
        inicializarJustificativas();
    }
});

// Inicializar quando a aba for ativada
document.addEventListener('click', function(e) {
    if (e.target && e.target.onclick && e.target.onclick.toString().includes('switchTab(\'justificativas\')')) {
        setTimeout(inicializarJustificativas, 100);
    }
});
