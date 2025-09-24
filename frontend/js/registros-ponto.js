/**
 * Gerenciamento de Registros de Ponto
 * Funcionalidades para visualizar e editar registros de ponto por funcionário
 */

let funcionarios = [];
let registrosPonto = [];
let funcionarioSelecionado = null;

// Inicialização específica para registros de ponto
async function inicializarRegistrosPonto() {
    await carregarFuncionarios();
    configurarFiltrosRegistros();
    configurarFormulariosRegistros();
}

/**
 * Carrega lista de funcionários para os filtros e selects
 */
async function carregarFuncionarios() {
    try {
        if (!verificarFuncoesAuth()) {
            return;
        }
        
        const response = await fazerRequisicao('registros-ponto.php?action=funcionarios');
        
        if (response.success) {
            funcionarios = response.data.funcionarios || [];
            preencherSelectFuncionarios();
        } else {
            mostrarNotificacao('Erro ao carregar funcionários: ' + response.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao carregar funcionários:', error);
        mostrarNotificacao('Erro ao carregar funcionários: ' + error.message, 'error');
    }
}

/**
 * Preenche os selects de funcionários
 */
function preencherSelectFuncionarios() {
    const selectFiltro = document.getElementById('filtroFuncionario');
    const selectInserir = document.getElementById('inserirFuncionario');
    
    // Limpar options existentes (exceto o primeiro)
    selectFiltro.innerHTML = '<option value="">Todos os funcionários</option>';
    selectInserir.innerHTML = '<option value="">Selecione um funcionário</option>';
    
    funcionarios.forEach(funcionario => {
        const optionFiltro = document.createElement('option');
        optionFiltro.value = funcionario.id;
        optionFiltro.textContent = `${funcionario.nome} - ${funcionario.departamento_nome || 'Sem departamento'}`;
        selectFiltro.appendChild(optionFiltro);
        
        const optionInserir = document.createElement('option');
        optionInserir.value = funcionario.id;
        optionInserir.textContent = funcionario.nome;
        optionInserir.dataset.cpf = funcionario.cpf || '';
        optionInserir.dataset.matricula = funcionario.matricula || '';
        selectInserir.appendChild(optionInserir);
    });
}

/**
 * Configura filtros padrão (mês atual)
 */
function configurarFiltrosRegistros() {
    const hoje = new Date();
    const inicioMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    
    document.getElementById('filtroDataInicio').value = inicioMes.toISOString().split('T')[0];
    document.getElementById('filtroDataFim').value = hoje.toISOString().split('T')[0];
    
    // Data padrão para inserir registro (hoje)
    document.getElementById('inserirData').value = hoje.toISOString().split('T')[0];
}

/**
 * Limpa os filtros
 */
function limparFiltrosRegistros() {
    document.getElementById('filtroFuncionario').value = '';
    document.getElementById('filtroApenasEditados').checked = false;
    configurarFiltrosRegistros();
    
    // Limpar tabela
    const tbody = document.getElementById('registrosPontoTableBody');
    tbody.innerHTML = `
        <tr>
            <td colspan="8" style="text-align: center; padding: 40px;">
                Selecione um funcionário e período para visualizar os registros
            </td>
        </tr>
    `;
}

/**
 * Busca registros de ponto com os filtros aplicados
 */
async function buscarRegistrosPonto() {
    try {
        if (!verificarFuncoesAuth()) {
            return;
        }
        
        const funcionarioId = document.getElementById('filtroFuncionario').value;
        const dataInicio = document.getElementById('filtroDataInicio').value;
        const dataFim = document.getElementById('filtroDataFim').value;
        const apenasEditados = document.getElementById('filtroApenasEditados').checked;
        
        if (!dataInicio || !dataFim) {
            mostrarNotificacao('Selecione o período para buscar os registros', 'warning');
            return;
        }
        
        const btnBuscar = document.querySelector('button[onclick="buscarRegistrosPonto()"]');
        mostrarLoading(btnBuscar);
        
        let url = `registros-ponto.php?data_inicio=${dataInicio}&data_fim=${dataFim}`;
        
        if (funcionarioId) {
            url = `registros-ponto.php?action=registros_funcionario&funcionario_id=${funcionarioId}&data_inicio=${dataInicio}&data_fim=${dataFim}`;
        } else {
            url += apenasEditados ? '&apenas_editados=1' : '';
        }
        
        const response = await fazerRequisicao(url);
        
        if (response.success) {
            if (funcionarioId) {
                // Exibir registros agrupados por funcionário
                funcionarioSelecionado = response.data.funcionario;
                registrosPonto = response.data.registros || [];
                exibirRegistrosAgrupados();
            } else {
                // Exibir registros gerais
                registrosPonto = response.data.registros || [];
                exibirRegistrosGerais();
            }
        } else {
            mostrarNotificacao('Erro ao buscar registros: ' + response.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao buscar registros:', error);
        mostrarNotificacao('Erro ao buscar registros: ' + error.message, 'error');
    } finally {
        const btnBuscar = document.querySelector('button[onclick="buscarRegistrosPonto()"]');
        mostrarLoading(btnBuscar, false);
    }
}

/**
 * Exibe registros agrupados por data (quando funcionário específico é selecionado)
 */
function exibirRegistrosAgrupados() {
    const tbody = document.getElementById('registrosPontoTableBody');
    const statsGrid = document.getElementById('statsGrid');
    
    if (registrosPonto.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px; color: var(--gray-500);">
                    <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 16px; display: block;"></i>
                    <strong>Nenhum registro encontrado para o período selecionado</strong>
                    <br><small>Tente ajustar os filtros ou selecionar outro período</small>
                </td>
            </tr>
        `;
        statsGrid.style.display = 'none';
        return;
    }
    
    // Calcular e exibir estatísticas
    calcularEExibirEstatisticas();
    statsGrid.style.display = 'grid';
    
    tbody.innerHTML = registrosPonto.map(registro => {
        const dataFormatada = formatarData(registro.data);
        const diaSemana = new Date(registro.data + 'T00:00:00').toLocaleDateString('pt-BR', { weekday: 'short' });
        const diaSemanaNum = new Date(registro.data + 'T00:00:00').getDay();
        const isSabado = diaSemanaNum === 6;
        
        // Ajustar status para sábados (meio período)
        let statusAjustado = registro.status;
        if (isSabado && registro.status === 'incompleto') {
            // Para sábados, verificar se tem entrada e saída (meio período completo)
            if (registro.entrada_manha && registro.saida_tarde) {
                statusAjustado = 'completo';
            }
        }
        
        const statusClass = getStatusClass(statusAjustado);
        const statusText = getStatusText(statusAjustado);
        
        return `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                            ${funcionarioSelecionado.nome.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <strong style="color: var(--gray-800);">${funcionarioSelecionado.nome}</strong>
                            <br><small style="color: var(--gray-500);">${funcionarioSelecionado.departamento_nome || 'Sem departamento'}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <div style="text-align: center;">
                        <div style="font-weight: 600; color: var(--gray-800);">${dataFormatada}</div>
                        <small style="color: var(--gray-500); text-transform: uppercase;">${diaSemana}</small>
                    </div>
                </td>
                <td class="ponto-cell">${formatarCelulaPonto(registro.entrada_manha, registro, 'entrada')}</td>
                <td class="ponto-cell">${formatarCelulaPonto(registro.saida_almoco, registro, 'saida_almoco')}</td>
                <td class="ponto-cell">${formatarCelulaPonto(registro.volta_almoco, registro, 'volta_almoco')}</td>
                <td class="ponto-cell">${formatarCelulaPonto(registro.saida_tarde, registro, 'saida_tarde')}</td>
                <td>
                    <div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">
                        <span class="status-badge ${statusClass}">
                            ${statusText}
                        </span>
                        ${registro.tem_edicao ? '<span class="editado-tag">📝 Editado</span>' : ''}
                    </div>
                </td>
                <td>
                    <div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">
                        ${registro.tem_edicao ? `
                            <span class="ajuste-badge" title="Registro com ajuste">
                                <i class="fas fa-cog"></i>
                                AJUSTE
                            </span>
                            ${registro.motivo_ajuste ? `
                                <small style="color: #6b7280; font-size: 0.7rem;">
                                    ${getMotivoAjusteTexto(registro.motivo_ajuste)}
                                </small>
                            ` : ''}
                            ${calcularTempoTotalAjuste(registro) ? `
                                <small style="color: #dc2626; font-size: 0.7rem; font-weight: bold;">
                                    ${formatarTempoAjuste(calcularTempoTotalAjuste(registro))}
                                </small>
                            ` : ''}
                        ` : `
                            <span class="normal-badge">
                                <i class="fas fa-check"></i>
                                NORMAL
                            </span>
                        `}
                    </div>
                </td>
                <td>
                    <div class="action-buttons" style="display: flex; flex-wrap: wrap; gap: 4px; justify-content: center;">
                        ${gerarBotoesAcao(registro)}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Exibe registros gerais (lista simples)
 */
function exibirRegistrosGerais() {
    const tbody = document.getElementById('registrosPontoTableBody');
    
    if (registrosPonto.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                    Nenhum registro encontrado para os filtros selecionados
                </td>
            </tr>
        `;
        return;
    }
    
    // Agrupar registros por funcionário e data
    const registrosAgrupados = {};
    registrosPonto.forEach(reg => {
        const chave = `${reg.funcionario_nome}-${reg.data}`;
        if (!registrosAgrupados[chave]) {
            registrosAgrupados[chave] = {
                funcionario_nome: reg.funcionario_nome,
                departamento_nome: reg.departamento_nome,
                data: reg.data,
                registros: {}
            };
        }
        registrosAgrupados[chave].registros[reg.tipo] = reg;
    });
    
    tbody.innerHTML = Object.values(registrosAgrupados).map(grupo => {
        const dataFormatada = formatarData(grupo.data);
        const temEdicao = Object.values(grupo.registros).some(r => r.editado);
        
        return `
            <tr>
                <td>
                    <strong>${grupo.funcionario_nome}</strong>
                    <br><small>${grupo.departamento_nome || 'Sem departamento'}</small>
                </td>
                <td>${dataFormatada}</td>
                <td>${formatarCelulaRegistroGeral(grupo.registros.entrada)}</td>
                <td>${formatarCelulaRegistroGeral(grupo.registros.almoco_saida)}</td>
                <td>${formatarCelulaRegistroGeral(grupo.registros.almoco_volta)}</td>
                <td>${formatarCelulaRegistroGeral(grupo.registros.saida)}</td>
                <td>
                    ${temEdicao ? '<span class="editado-tag">📝 Editado</span>' : '--'}
                </td>
                <td>--</td>
            </tr>
        `;
    }).join('');
}

/**
 * Formata célula de ponto (para registros agrupados)
 */
function formatarCelulaPonto(ponto, registro = null, tipoPonto = null) {
    if (!ponto) {
        // Se há justificativa, mostrar o tipo da justificativa
        if (registro && registro.justificativa) {
            const tipo = registro.justificativa.tipo_codigo;
            const nomes = {
                'FER': 'Férias',
                'ATM': 'Atestado',
                'LIC': 'Licença',
                'FOL': 'Folga',
                'AJP': 'Ausência'
            };
            return `
                <div class="ponto-justificado">
                    <i class="fas fa-calendar-check"></i>
                    <br><small>${nomes[tipo] || 'Justificado'}</small>
                </div>
            `;
        }
        
        // Verificar se é sábado para determinar se deve mostrar "Não registrado"
        const diaSemana = new Date(registro.data + 'T00:00:00').getDay();
        const isSabado = diaSemana === 6;
        
        // Para sábados, mostrar mensagem específica para colunas de almoço
        if (isSabado && (tipoPonto === 'saida_almoco' || tipoPonto === 'volta_almoco')) {
            return `
                <div class="ponto-ausente" style="opacity: 0.4; background: #f8f9fa;">
                    <i class="fas fa-calendar-day"></i>
                    <br><small>Meio período</small>
                </div>
            `;
        }
        
        return `
            <div class="ponto-ausente">
                <i class="fas fa-minus"></i>
                <br><small>Não registrado</small>
            </div>
        `;
    }
    
    const editadoClass = ponto.editado ? ' editado' : '';
    const tooltipInfo = ponto.editado ? 
        `title="Editado em ${formatarDataHora(ponto.editado_em)} por ${ponto.editado_por_nome}${ponto.tempo_ajustado_minutos ? ` - Ajuste: ${formatarTempoAjuste(ponto.tempo_ajustado_minutos)}` : ''}"` : '';
    
    return `
        <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
            <span class="ponto-hora${editadoClass}" ${tooltipInfo}>
                ${ponto.hora}
            </span>
            ${ponto.id ? `
                <button class="btn-edit-small" onclick="abrirModalAjuste(${ponto.id}, '${ponto.tipo}', '${registro.data}', '${funcionarioSelecionado.nome}', '${ponto.hora}')" title="Ajustar registro">
                    <i class="fas fa-cog"></i>
                    Ajustar
                </button>
            ` : ''}
        </div>
    `;
}

/**
 * Converte código do motivo do ajuste em texto legível
 */
function getMotivoAjusteTexto(motivo) {
    const motivos = {
        'esquecimento': 'Esquecimento',
        'erro': 'Erro de registro',
        'problema_tecnico': 'Problema técnico',
        'justificativa_admin': 'Justificativa administrativa',
        'outros': 'Outros'
    };
    return motivos[motivo] || motivo;
}

/**
 * Formata tempo de ajuste em minutos para texto legível
 */
function formatarTempoAjuste(minutos) {
    if (!minutos || minutos === 0) return '0min';
    
    const absMinutos = Math.abs(minutos);
    const horas = Math.floor(absMinutos / 60);
    const mins = absMinutos % 60;
    
    let resultado = '';
    if (horas > 0) {
        resultado += `${horas}h`;
    }
    if (mins > 0) {
        resultado += `${mins}min`;
    }
    
    // Adicionar sinal
    if (minutos > 0) {
        resultado = '+' + resultado;
    } else {
        resultado = '-' + resultado;
    }
    
    return resultado;
}

/**
 * Calcula o tempo total de ajuste de um registro (soma de todos os pontos ajustados)
 */
function calcularTempoTotalAjuste(registro) {
    let totalMinutos = 0;
    
    // Verificar cada ponto do registro
    const pontos = ['entrada_manha', 'saida_almoco', 'volta_almoco', 'saida_tarde'];
    
    pontos.forEach(ponto => {
        if (registro[ponto] && registro[ponto].tempo_ajustado_minutos) {
            totalMinutos += registro[ponto].tempo_ajustado_minutos;
        }
    });
    
    return totalMinutos;
}

/**
 * Formata célula de registro geral
 */
function formatarCelulaRegistroGeral(registro) {
    if (!registro) {
        return '<span class="ponto-ausente">--</span>';
    }
    
    const editadoIcon = registro.editado ? ' 📝' : '';
    const editadoClass = registro.editado ? ' editado' : '';
    
    return `
        <span class="ponto-hora${editadoClass}" 
              ${registro.editado ? `title="Editado em ${formatarDataHora(registro.editado_em)} por ${registro.editado_por_nome}"` : ''}>
            ${registro.hora}${editadoIcon}
        </span>
    `;
}

/**
 * Gera botões de ação para cada registro
 */
function gerarBotoesAcao(registro) {
    const botoes = [];
    
    // Verificar se é sábado para determinar a sequência de botões
    const diaSemana = new Date(registro.data + 'T00:00:00').getDay();
    const isSabado = diaSemana === 6;
    
    // Botões para inserir registros faltantes
    if (!registro.entrada_manha) {
        botoes.push(`
            <button class="btn-add-small" onclick="abrirModalInserirRegistroEspecifico('entrada', '${registro.data}')" title="Inserir entrada">
                <i class="fas fa-sign-in-alt"></i>
                Entrada
            </button>
        `);
    }
    
    // Para sábados, não mostrar botões de almoço
    if (!isSabado) {
        if (!registro.saida_almoco && registro.entrada_manha) {
            botoes.push(`
                <button class="btn-add-small" onclick="abrirModalInserirRegistroEspecifico('almoco_saida', '${registro.data}')" title="Inserir saída para almoço">
                    <i class="fas fa-utensils"></i>
                    Almoço
                </button>
            `);
        }
        if (!registro.volta_almoco && registro.saida_almoco) {
            botoes.push(`
                <button class="btn-add-small" onclick="abrirModalInserirRegistroEspecifico('almoco_volta', '${registro.data}')" title="Inserir volta do almoço">
                    <i class="fas fa-undo"></i>
                    Volta
                </button>
            `);
        }
        if (!registro.saida_tarde && registro.volta_almoco) {
            botoes.push(`
                <button class="btn-add-small" onclick="abrirModalInserirRegistroEspecifico('saida', '${registro.data}')" title="Inserir saída final">
                    <i class="fas fa-sign-out-alt"></i>
                    Saída
                </button>
            `);
        }
    } else {
        // Para sábados, apenas entrada e saída (meio período)
        if (!registro.saida_tarde && registro.entrada_manha) {
            botoes.push(`
                <button class="btn-add-small" onclick="abrirModalInserirRegistroEspecifico('saida', '${registro.data}')" title="Inserir saída (meio período)">
                    <i class="fas fa-sign-out-alt"></i>
                    Saída
                </button>
            `);
        }
    }
    
    // Se não há botões de inserção, mostrar mensagem
    if (botoes.length === 0) {
        return '<small style="color: var(--gray-500);">Registro completo</small>';
    }
    
    return botoes.join('');
}

/**
 * Obtém classe CSS para status
 */
function getStatusClass(status) {
    switch (status) {
        case 'completo': return 'completo';
        case 'editado_completo': return 'editado-completo';
        case 'editado_incompleto': return 'editado-incompleto';
        case 'ferias': return 'ferias';
        case 'atestado': return 'atestado';
        case 'licenca': return 'licenca';
        case 'folga': return 'folga';
        case 'justificado': return 'justificado';
        case 'incompleto':
        default: return 'incompleto';
    }
}

/**
 * Obtém texto para status
 */
function getStatusText(status) {
    switch (status) {
        case 'completo': return 'Completo';
        case 'editado_completo': return 'Completo (Editado)';
        case 'editado_incompleto': return 'Incompleto (Editado)';
        case 'ferias': return 'Férias';
        case 'atestado': return 'Atestado';
        case 'licenca': return 'Licença';
        case 'folga': return 'Folga';
        case 'justificado': return 'Justificado';
        case 'incompleto':
        default: return 'Incompleto';
    }
}

/**
 * Configura formulários de inserção e edição
 */
function configurarFormulariosRegistros() {
    // Formulário de inserir registro
    document.getElementById('inserirRegistroForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        await salvarNovoRegistro();
    });
    
    // Formulário de editar registro
    document.getElementById('editarRegistroForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        await salvarEdicaoRegistro();
    });
    
    // Configurar formulário de ajuste
    document.getElementById('ajusteForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        await salvarAjusteRegistro();
    });
}

/**
 * Abre modal para inserir novo registro
 */
function abrirModalInserirRegistro() {
    const modal = document.getElementById('inserirRegistroModal');
    const form = document.getElementById('inserirRegistroForm');
    
    // Reset do formulário
    form.reset();
    
    // Data padrão (hoje)
    document.getElementById('inserirData').value = new Date().toISOString().split('T')[0];
    
    // Carregar funcionários se necessário
    if (funcionarios.length === 0) {
        carregarFuncionarios();
    }
    
    modal.classList.add('active');
}

/**
 * Abre modal para inserir registro específico (quando clicado no botão da tabela)
 */
function abrirModalInserirRegistroEspecifico(tipo, data) {
    abrirModalInserirRegistro();
    
    // Pré-preencher campos
    document.getElementById('inserirFuncionario').value = funcionarioSelecionado ? 
        funcionarios.find(f => f.nome === funcionarioSelecionado.nome)?.id || '' : '';
    document.getElementById('inserirData').value = data;
    document.getElementById('inserirTipo').value = tipo;
}

/**
 * Fecha modal de inserir registro
 */
function fecharModalInserirRegistro() {
    document.getElementById('inserirRegistroModal').classList.remove('active');
}

/**
 * Abre modal para editar registro
 */
function abrirModalEditarRegistro(registroId, horaAtual, observacaoAtual = '') {
    const modal = document.getElementById('editarRegistroModal');
    const form = document.getElementById('editarRegistroForm');
    
    // Reset do formulário
    form.reset();
    
    // Preencher dados do registro
    document.getElementById('editarRegistroId').value = registroId;
    document.getElementById('horaAtual').textContent = horaAtual;
    document.getElementById('editarHora').value = horaAtual;
    document.getElementById('editarObservacao').value = observacaoAtual || '';
    
    // Buscar dados completos do registro
    buscarDadosRegistro(registroId);
    
    modal.classList.add('active');
}

/**
 * Busca dados completos do registro para edição
 */
async function buscarDadosRegistro(registroId) {
    try {
        // Encontrar registro nos dados já carregados
        let registro = null;
        
        if (funcionarioSelecionado && registrosPonto.length > 0) {
            // Buscar nos registros agrupados
            for (const reg of registrosPonto) {
                const pontos = [reg.entrada_manha, reg.saida_almoco, reg.volta_almoco, reg.saida_tarde];
                registro = pontos.find(p => p && p.id == registroId);
                if (registro) {
                    document.getElementById('editarFuncionarioNome').value = funcionarioSelecionado.nome;
                    document.getElementById('editarData').value = formatarData(reg.data);
                    break;
                }
            }
        }
        
        if (registro) {
            // Determinar tipo do registro
            const tipos = {
                'entrada_manha': 'Entrada (Manhã)',
                'saida_almoco': 'Saída (Almoço)', 
                'volta_almoco': 'Volta (Almoço)',
                'saida_tarde': 'Saída (Final)'
            };
            
            // Buscar tipo baseado na posição
            for (const reg of registrosPonto) {
                if (reg.entrada_manha && reg.entrada_manha.id == registroId) {
                    document.getElementById('editarTipo').value = tipos.entrada_manha;
                    break;
                } else if (reg.saida_almoco && reg.saida_almoco.id == registroId) {
                    document.getElementById('editarTipo').value = tipos.saida_almoco;
                    break;
                } else if (reg.volta_almoco && reg.volta_almoco.id == registroId) {
                    document.getElementById('editarTipo').value = tipos.volta_almoco;
                    break;
                } else if (reg.saida_tarde && reg.saida_tarde.id == registroId) {
                    document.getElementById('editarTipo').value = tipos.saida_tarde;
                    break;
                }
            }
        }
    } catch (error) {
        console.error('Erro ao buscar dados do registro:', error);
    }
}

/**
 * Fecha modal de editar registro
 */
function fecharModalEditarRegistro() {
    document.getElementById('editarRegistroModal').classList.remove('active');
}

// Função para abrir modal de ajuste
function abrirModalAjuste(registroId, tipo, data, funcionarioNome, horarioAtual) {
    const modal = document.getElementById('ajusteModal');
    const form = document.getElementById('ajusteForm');
    
    // Preencher dados do modal
    document.getElementById('ajusteRegistroId').value = registroId;
    document.getElementById('ajusteTipo').value = tipo;
    document.getElementById('ajusteData').value = data;
    document.getElementById('ajusteFuncionario').textContent = funcionarioNome;
    document.getElementById('ajusteDataDisplay').textContent = formatarData(data);
    document.getElementById('ajusteHorarioAtual').textContent = horarioAtual;
    document.getElementById('ajusteNovaHora').value = horarioAtual.substring(0, 5); // Remover segundos
    
    // Reset do formulário
    form.reset();
    document.getElementById('ajusteRegistroId').value = registroId;
    document.getElementById('ajusteTipo').value = tipo;
    document.getElementById('ajusteData').value = data;
    document.getElementById('ajusteFuncionario').textContent = funcionarioNome;
    document.getElementById('ajusteDataDisplay').textContent = formatarData(data);
    document.getElementById('ajusteHorarioAtual').textContent = horarioAtual;
    document.getElementById('ajusteNovaHora').value = horarioAtual.substring(0, 5);
    
    modal.classList.add('active');
}

// Função para fechar modal de ajuste
function fecharModalAjuste() {
    document.getElementById('ajusteModal').classList.remove('active');
    document.getElementById('ajusteForm').reset();
}

// Função para salvar ajuste de registro
async function salvarAjusteRegistro() {
    try {
        if (!verificarFuncoesAuth()) {
            return;
        }
        
        const registroId = document.getElementById('ajusteRegistroId').value;
        const novaHora = document.getElementById('ajusteNovaHora').value + ':00'; // Adicionar segundos
        const motivoAjuste = document.getElementById('ajusteMotivo').value;
        const observacao = document.getElementById('ajusteObservacao').value;
        const justificativa = document.getElementById('ajusteJustificativa').value;
        
        if (!registroId || !novaHora || !motivoAjuste || !justificativa) {
            mostrarNotificacao('Todos os campos obrigatórios devem ser preenchidos', 'error');
            return;
        }
        
        mostrarLoading(document.querySelector('#ajusteForm button[type="submit"]'), true);
        
        const response = await fazerRequisicao('registros-ponto.php', {
            method: 'PUT',
            body: JSON.stringify({
                id: registroId,
                hora: novaHora,
                observacao: observacao,
                justificativa: justificativa,
                motivo_ajuste: motivoAjuste
            })
        });
        
        if (response.success) {
            mostrarNotificacao('Ajuste realizado com sucesso!', 'success');
            fecharModalAjuste();
            await carregarRegistrosPonto(); // Recarregar dados
        } else {
            mostrarNotificacao(response.message || 'Erro ao realizar ajuste', 'error');
        }
        
    } catch (error) {
        console.error('Erro ao salvar ajuste:', error);
        mostrarNotificacao('Erro ao realizar ajuste', 'error');
    } finally {
        mostrarLoading(document.querySelector('#ajusteForm button[type="submit"]'), false);
    }
}

/**
 * Salva novo registro
 */
async function salvarNovoRegistro() {
    try {
        if (!verificarFuncoesAuth()) {
            return;
        }
        
        const dados = {
            action: 'inserir_registro',
            funcionario_id: parseInt(document.getElementById('inserirFuncionario').value),
            data: document.getElementById('inserirData').value,
            tipo: document.getElementById('inserirTipo').value,
            hora: document.getElementById('inserirHora').value + ':00', // Adicionar segundos
            observacao: document.getElementById('inserirObservacao').value
        };
        
        if (!dados.funcionario_id || !dados.data || !dados.tipo || !dados.hora) {
            mostrarNotificacao('Preencha todos os campos obrigatórios', 'error');
            return;
        }
        
        const btnSubmit = document.querySelector('#inserirRegistroForm button[type="submit"]');
        mostrarLoading(btnSubmit);
        
        const response = await fazerRequisicao('registros-ponto.php', {
            method: 'POST',
            body: JSON.stringify(dados)
        });
        
        if (response.success) {
            mostrarNotificacao('Registro inserido com sucesso!', 'success');
            fecharModalInserirRegistro();
            buscarRegistrosPonto(); // Atualizar tabela
        } else {
            mostrarNotificacao('Erro ao inserir registro: ' + response.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao salvar registro:', error);
        mostrarNotificacao('Erro ao salvar registro: ' + error.message, 'error');
    } finally {
        const btnSubmit = document.querySelector('#inserirRegistroForm button[type="submit"]');
        mostrarLoading(btnSubmit, false);
    }
}

/**
 * Salva edição de registro
 */
async function salvarEdicaoRegistro() {
    try {
        if (!verificarFuncoesAuth()) {
            return;
        }
        
        const dados = {
            id: parseInt(document.getElementById('editarRegistroId').value),
            hora: document.getElementById('editarHora').value + ':00', // Adicionar segundos
            observacao: document.getElementById('editarObservacao').value,
            justificativa: document.getElementById('editarJustificativa').value
        };
        
        if (!dados.id || !dados.hora || !dados.justificativa) {
            mostrarNotificacao('Preencha todos os campos obrigatórios', 'error');
            return;
        }
        
        const btnSubmit = document.querySelector('#editarRegistroForm button[type="submit"]');
        mostrarLoading(btnSubmit);
        
        const response = await fazerRequisicao('registros-ponto.php', {
            method: 'PUT',
            body: JSON.stringify(dados)
        });
        
        if (response.success) {
            mostrarNotificacao('Registro editado com sucesso!', 'success');
            fecharModalEditarRegistro();
            buscarRegistrosPonto(); // Atualizar tabela
        } else {
            mostrarNotificacao('Erro ao editar registro: ' + response.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao editar registro:', error);
        mostrarNotificacao('Erro ao editar registro: ' + error.message, 'error');
    } finally {
        const btnSubmit = document.querySelector('#editarRegistroForm button[type="submit"]');
        mostrarLoading(btnSubmit, false);
    }
}

/**
 * Exclui um registro (com confirmação)
 */
async function excluirRegistro(registroId) {
    const justificativa = prompt('Digite a justificativa para exclusão do registro:');
    
    if (!justificativa || justificativa.trim() === '') {
        mostrarNotificacao('Justificativa é obrigatória para exclusão', 'warning');
        return;
    }
    
    if (!confirm('Confirma a exclusão deste registro?')) {
        return;
    }
    
    try {
        if (!verificarFuncoesAuth()) {
            return;
        }
        
        const response = await fazerRequisicao('registros-ponto.php', {
            method: 'DELETE',
            body: JSON.stringify({
                id: registroId,
                justificativa: justificativa
            })
        });
        
        if (response.success) {
            mostrarNotificacao('Registro excluído com sucesso!', 'success');
            buscarRegistrosPonto(); // Atualizar tabela
        } else {
            mostrarNotificacao('Erro ao excluir registro: ' + response.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao excluir registro:', error);
        mostrarNotificacao('Erro ao excluir registro: ' + error.message, 'error');
    }
}

/**
 * Formata data para exibição
 */
function formatarData(data) {
    if (!data) return '';
    return new Date(data + 'T00:00:00').toLocaleDateString('pt-BR');
}

/**
 * Formata data e hora para exibição
 */
function formatarDataHora(dataHora) {
    if (!dataHora) return '';
    return new Date(dataHora).toLocaleString('pt-BR');
}

/**
 * Calcula e exibe estatísticas dos registros
 */
function calcularEExibirEstatisticas() {
    if (!registrosPonto || registrosPonto.length === 0) {
        return;
    }
    
    let totalRegistros = registrosPonto.length;
    let registrosCompletos = 0;
    let registrosIncompletos = 0;
    let registrosEditados = 0;
    let totalAjustes = 0;
    let tempoTotalAjustes = 0;
    let ajustesPorMotivo = {
        'esquecimento': 0,
        'erro': 0,
        'problema_tecnico': 0,
        'justificativa_admin': 0,
        'outros': 0
    };
    
    registrosPonto.forEach(registro => {
        if (registro.completo) {
            registrosCompletos++;
        } else {
            registrosIncompletos++;
        }
        
        if (registro.tem_edicao) {
            registrosEditados++;
            totalAjustes++;
            
            // Calcular tempo total de ajustes
            const tempoAjuste = calcularTempoTotalAjuste(registro);
            tempoTotalAjustes += tempoAjuste;
            
            // Contar ajustes por motivo
            if (registro.motivo_ajuste && ajustesPorMotivo.hasOwnProperty(registro.motivo_ajuste)) {
                ajustesPorMotivo[registro.motivo_ajuste]++;
            }
        }
    });
    
    // Animar os números
    animarNumero('totalRegistros', totalRegistros);
    animarNumero('registrosCompletos', registrosCompletos);
    animarNumero('registrosIncompletos', registrosIncompletos);
    animarNumero('registrosEditados', registrosEditados);
    
    // Exibir estatísticas de ajustes
    exibirEstatisticasAjustes(totalAjustes, tempoTotalAjustes, ajustesPorMotivo, totalRegistros);
}

/**
 * Exibe estatísticas de ajustes
 */
function exibirEstatisticasAjustes(totalAjustes, tempoTotalAjustes, ajustesPorMotivo, totalRegistros) {
    const frequenciaAjustes = totalRegistros > 0 ? ((totalAjustes / totalRegistros) * 100).toFixed(1) : 0;
    
    // Criar HTML das estatísticas de ajustes
    const estatisticasHTML = `
        <div class="ajustes-stats" style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #f59e0b;">
            <h4 style="margin: 0 0 10px 0; color: #92400e; font-size: 0.9rem;">
                <i class="fas fa-cog"></i> ESTATÍSTICAS DE AJUSTES
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; font-size: 0.8rem;">
                <div style="text-align: center;">
                    <div style="font-weight: bold; color: #92400e; font-size: 1.2rem;">${totalAjustes}</div>
                    <div style="color: #6b7280;">Total de Ajustes</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-weight: bold; color: #dc2626; font-size: 1.2rem;">${formatarTempoAjuste(tempoTotalAjustes)}</div>
                    <div style="color: #6b7280;">Tempo Total</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-weight: bold; color: #92400e; font-size: 1.2rem;">${frequenciaAjustes}%</div>
                    <div style="color: #6b7280;">Frequência</div>
                </div>
                ${ajustesPorMotivo.esquecimento > 0 ? `
                    <div style="text-align: center;">
                        <div style="font-weight: bold; color: #dc2626; font-size: 1.1rem;">${ajustesPorMotivo.esquecimento}</div>
                        <div style="color: #6b7280;">Esquecimento</div>
                    </div>
                ` : ''}
                ${ajustesPorMotivo.erro > 0 ? `
                    <div style="text-align: center;">
                        <div style="font-weight: bold; color: #dc2626; font-size: 1.1rem;">${ajustesPorMotivo.erro}</div>
                        <div style="color: #6b7280;">Erro</div>
                    </div>
                ` : ''}
                ${ajustesPorMotivo.problema_tecnico > 0 ? `
                    <div style="text-align: center;">
                        <div style="font-weight: bold; color: #dc2626; font-size: 1.1rem;">${ajustesPorMotivo.problema_tecnico}</div>
                        <div style="color: #6b7280;">Problema Técnico</div>
                    </div>
                ` : ''}
                ${ajustesPorMotivo.justificativa_admin > 0 ? `
                    <div style="text-align: center;">
                        <div style="font-weight: bold; color: #059669; font-size: 1.1rem;">${ajustesPorMotivo.justificativa_admin}</div>
                        <div style="color: #6b7280;">Justificativa Admin</div>
                    </div>
                ` : ''}
                ${ajustesPorMotivo.outros > 0 ? `
                    <div style="text-align: center;">
                        <div style="font-weight: bold; color: #6b7280; font-size: 1.1rem;">${ajustesPorMotivo.outros}</div>
                        <div style="color: #6b7280;">Outros</div>
                    </div>
                ` : ''}
            </div>
        </div>
    `;
    
    // Inserir estatísticas após as estatísticas existentes
    const statsContainer = document.querySelector('.stats-grid');
    if (statsContainer) {
        // Remover estatísticas de ajustes anteriores se existirem
        const existingAjustesStats = statsContainer.querySelector('.ajustes-stats');
        if (existingAjustesStats) {
            existingAjustesStats.remove();
        }
        
        // Adicionar novas estatísticas
        statsContainer.insertAdjacentHTML('afterend', estatisticasHTML);
    }
}

/**
 * Anima um número de 0 até o valor final
 */
function animarNumero(elementId, valorFinal) {
    const elemento = document.getElementById(elementId);
    if (!elemento) return;
    
    let valorAtual = 0;
    const incremento = valorFinal / 30; // 30 frames
    const duracao = 1000; // 1 segundo
    const intervalo = duracao / 30;
    
    const timer = setInterval(() => {
        valorAtual += incremento;
        if (valorAtual >= valorFinal) {
            valorAtual = valorFinal;
            clearInterval(timer);
        }
        elemento.textContent = Math.floor(valorAtual);
    }, intervalo);
}

// Exportar funções globalmente
window.inicializarRegistrosPonto = inicializarRegistrosPonto;
window.buscarRegistrosPonto = buscarRegistrosPonto;
window.limparFiltrosRegistros = limparFiltrosRegistros;
window.abrirModalInserirRegistro = abrirModalInserirRegistro;
window.abrirModalInserirRegistroEspecifico = abrirModalInserirRegistroEspecifico;
window.fecharModalInserirRegistro = fecharModalInserirRegistro;
window.abrirModalEditarRegistro = abrirModalEditarRegistro;
window.fecharModalEditarRegistro = fecharModalEditarRegistro;
window.abrirModalAjuste = abrirModalAjuste;
window.fecharModalAjuste = fecharModalAjuste;
window.excluirRegistro = excluirRegistro;
