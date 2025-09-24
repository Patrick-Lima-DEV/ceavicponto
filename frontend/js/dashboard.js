// Dashboard do Funcionário
let usuario = null;
let registrosHoje = [];
let intervaloAtualizacao = null;

// Inicialização
document.addEventListener('DOMContentLoaded', async function() {
    // Verificar se é funcionário
    const userCheck = await verificarTipoUsuario('funcionario');
    if (!userCheck) {
        return;
    }
    
    usuario = await verificarAutenticacao();
    if (!usuario) return;
    
    // Configurar interface
    configurarInterface();
    
    // Carregar dados iniciais
    await carregarDados();
    
    // Configurar atualizações automáticas
    iniciarAtualizacoes();
    
    // Configurar filtros de data
    configurarFiltrosData();
});

function configurarInterface() {
    // Mostrar nome do usuário
    document.getElementById('userName').textContent = usuario.nome;
    
    // Configurar tolerância no calculador
    if (usuario.tolerancia) {
        timeCalculator.toleranciaMinutos = usuario.tolerancia;
    }
    
    // Mostrar carga horária esperada
    document.getElementById('horasEsperadas').textContent = 
        usuario.carga_diaria ? usuario.carga_diaria.substring(0, 5) : '08:00';
}

function iniciarAtualizacoes() {
    // Atualizar relógio a cada segundo
    atualizarRelogio();
    setInterval(atualizarRelogio, 1000);
    
    // Atualizar dados a cada 30 segundos
    intervaloAtualizacao = setInterval(async () => {
        await carregarRegistrosHoje();
        atualizarCalculos();
    }, 30000);
}

function atualizarRelogio() {
    const agora = timeCalculator.obterDataHoraAtual();
    document.getElementById('currentDate').textContent = agora.data;
    document.getElementById('currentTime').textContent = agora.hora;
}

async function carregarDados() {
    await Promise.all([
        carregarRegistrosHoje(),
        carregarHistorico()
    ]);
}

async function carregarRegistrosHoje() {
    try {
        const hoje = new Date().toISOString().split('T')[0];
        const response = await fazerRequisicao(`listar.php?data_inicio=${hoje}&data_fim=${hoje}`);
        
        if (response.success) {
            registrosHoje = response.data.registros || [];
            atualizarCalculos();
            atualizarBotoesPonto();
        }
    } catch (error) {
        console.error('Erro ao carregar registros de hoje:', error);
        mostrarNotificacao('Erro ao carregar registros de hoje', 'error');
    }
}

function atualizarCalculos() {
    // Calcular horas trabalhadas
    const horasTrabalhadasStr = timeCalculator.calcularHorasTrabalhadas(registrosHoje);
    document.getElementById('horasTrabalhadasHoje').textContent = horasTrabalhadasStr.substring(0, 5);
    
    // Calcular saldo
    const cargaDiaria = usuario.carga_diaria || '08:00:00';
    const saldoInfo = timeCalculator.calcularSaldo(horasTrabalhadasStr, cargaDiaria, usuario.tolerancia);
    
    const saldoElement = document.getElementById('saldoDia');
    const statusElement = document.getElementById('statusDia');
    
    saldoElement.textContent = saldoInfo.saldo;
    statusElement.textContent = saldoInfo.tipo;
    
    // Atualizar classes CSS baseado no status
    saldoElement.className = `status-value ${saldoInfo.status}`;
    
    // Calcular tempo restante se ainda não completou a jornada
    if (registrosHoje.length > 0 && !timeCalculator.diaCompleto(registrosHoje)) {
        const tempoRestante = timeCalculator.calcularTempoRestante(registrosHoje, cargaDiaria);
        if (!tempoRestante.concluido) {
            statusElement.textContent = `Restam ${tempoRestante.tempo.substring(0, 5)}`;
        }
    }
}

function atualizarBotoesPunto() {
    const botoes = {
        entrada: document.querySelector('.punch-btn.entrada'),
        almoco_saida: document.querySelector('.punch-btn.almoco-saida'),
        almoco_volta: document.querySelector('.punch-btn.almoco-volta'),
        saida: document.querySelector('.punch-btn.saida')
    };
    
    // Desabilitar todos primeiro
    Object.values(botoes).forEach(btn => {
        if (btn) {
            btn.disabled = true;
            btn.style.opacity = '0.6';
        }
    });
    
    // Determinar próximo registro
    const proximo = timeCalculator.obterProximoRegistro(registrosHoje);
    
    if (proximo) {
        const proximoBotao = botoes[proximo.tipo];
        if (proximoBotao) {
            proximoBotao.disabled = false;
            proximoBotao.style.opacity = '1';
            
            // Adicionar efeito de destaque
            proximoBotao.style.boxShadow = '0 0 20px rgba(37, 99, 235, 0.4)';
            proximoBotao.style.transform = 'scale(1.05)';
        }
    }
    
    // Se todos os registros foram feitos, mostrar mensagem
    if (!proximo) {
        mostrarNotificacao('Todos os registros do dia foram realizados', 'success');
    }
}

// Corrigir nome da função
function atualizarBotoesPonto() {
    atualizarBotoesPunto();
}

async function registrarPonto(tipo) {
    const botao = document.querySelector(`.punch-btn.${tipo.replace('_', '-')}`);
    
    if (!botao || botao.disabled) {
        return;
    }
    
    // Confirmar ação
    const nomes = {
        'entrada': 'Entrada',
        'almoco_saida': 'Saída para Almoço',
        'almoco_volta': 'Volta do Almoço',
        'saida': 'Saída'
    };
    
    if (!confirm(`Confirma o registro de ${nomes[tipo]}?`)) {
        return;
    }
    
    mostrarLoading(botao);
    
    try {
        const response = await fazerRequisicao('registrar.php', {
            method: 'POST',
            body: JSON.stringify({ tipo })
        });
        
        if (response.success) {
            mostrarNotificacao(`${nomes[tipo]} registrada com sucesso!`, 'success');
            
            // Recarregar dados
            await carregarRegistrosHoje();
            await carregarHistorico();
        }
    } catch (error) {
        console.error('Erro ao registrar ponto:', error);
        mostrarNotificacao(error.message, 'error');
    } finally {
        mostrarLoading(botao, false);
    }
}

function configurarFiltrosData() {
    const hoje = new Date().toISOString().split('T')[0];
    const inicioMes = new Date(new Date().getFullYear(), new Date().getMonth(), 1)
        .toISOString().split('T')[0];
    
    document.getElementById('dataInicio').value = inicioMes;
    document.getElementById('dataFim').value = hoje;
}

async function carregarHistorico() {
    const dataInicio = document.getElementById('dataInicio').value;
    const dataFim = document.getElementById('dataFim').value;
    
    try {
        const response = await fazerRequisicao(`listar.php?data_inicio=${dataInicio}&data_fim=${dataFim}`);
        
        if (response.success) {
            exibirHistorico(response.data.estatisticas || []);
        }
    } catch (error) {
        console.error('Erro ao carregar histórico:', error);
        mostrarNotificacao('Erro ao carregar histórico', 'error');
    }
}

function exibirHistorico(estatisticas) {
    const tbody = document.getElementById('historyTableBody');
    
    if (!estatisticas || estatisticas.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                    Nenhum registro encontrado para o período selecionado
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = estatisticas.map(stat => {
        const registros = stat.registros || [];
        const tipos = {};
        registros.forEach(reg => tipos[reg.tipo] = reg.hora);
        
        return `
            <tr>
                <td>${timeCalculator.formatarData(stat.data)}</td>
                <td>${tipos.entrada || '--'}</td>
                <td>${tipos.almoco_saida || '--'}</td>
                <td>${tipos.almoco_volta || '--'}</td>
                <td>${tipos.saida || '--'}</td>
                <td>${stat.horas_trabalhadas ? stat.horas_trabalhadas.substring(0, 5) : '00:00'}</td>
                <td class="${stat.status}">${stat.saldo || '00:00'}</td>
                <td>
                    <span class="status-badge ${stat.completo ? 'completo' : 'incompleto'}">
                        ${stat.completo ? 'Completo' : 'Incompleto'}
                    </span>
                </td>
            </tr>
        `;
    }).join('');
}

async function filtrarRegistros() {
    const btnFiltrar = document.querySelector('.btn-filter');
    mostrarLoading(btnFiltrar);
    
    try {
        await carregarHistorico();
    } finally {
        mostrarLoading(btnFiltrar, false);
    }
}

// Limpeza ao sair da página
window.addEventListener('beforeunload', function() {
    if (intervaloAtualizacao) {
        clearInterval(intervaloAtualizacao);
    }
});

// Expor funções globalmente
window.registrarPonto = registrarPonto;
window.filtrarRegistros = filtrarRegistros;