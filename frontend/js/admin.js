// Painel Administrativo
let usuarios = [];
let relatorios = [];

// Cálculos agora são feitos no PHP - JavaScript simplificado

// Função auxiliar para verificar se as funções do auth.js estão disponíveis
function verificarFuncoesAuth() {
    if (typeof fazerRequisicao !== 'function') {
        console.error('fazerRequisicao não está disponível - auth.js não foi carregado');
        return false;
    }
    if (typeof mostrarNotificacao !== 'function') {
        console.error('mostrarNotificacao não está disponível - auth.js não foi carregado');
        return false;
    }
    return true;
}

// Inicialização
document.addEventListener('DOMContentLoaded', async function() {
    // Aguardar um pouco para garantir que auth.js foi carregado
    setTimeout(async () => {
        // Verificar se é admin
        const user = await verificarTipoUsuario('admin');
        if (!user) {
            return; // Será redirecionado automaticamente
        }
        
        // Carregar dados iniciais
        await carregarUsuarios();
        configurarFiltrosRelatorio();
        configurarFormularios();
    }, 100);
});

function switchTab(tabName) {
    try {
        // Remover classe active de todas as abas
        document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        // Ativar aba selecionada
        const navTab = document.querySelector(`.nav-tab[onclick="switchTab('${tabName}')"]`);
        const tabContent = document.getElementById(`${tabName}-tab`);
        
        if (navTab) {
            navTab.classList.add('active');
        }
        
        if (tabContent) {
            tabContent.classList.add('active');
        }
        
        // Carregar dados específicos da aba
        if (tabName === 'usuarios') {
            carregarUsuarios();
        } else if (tabName === 'departamentos') {
            carregarDepartamentos();
        } else if (tabName === 'jornadas') {
            carregarJornadas();
        } else if (tabName === 'registros') {
            // Inicializar aba de registros de ponto
            if (typeof inicializarRegistrosPonto === 'function') {
                inicializarRegistrosPonto();
            }
        } else if (tabName === 'justificativas') {
            // Inicializar aba de justificativas
            if (typeof inicializarJustificativas === 'function') {
                inicializarJustificativas();
            }
        } else if (tabName === 'configuracoes') {
            // Inicializar aba de configurações
            inicializarConfiguracoes();
            carregarConfiguracoes();
        } else if (tabName === 'relatorios') {
            // ✅ Inicializar aba do Cartão de Ponto
            preencherSelectUsuarios();
            
            // Preencher datas padrão (mês atual)
            const hoje = new Date();
            const primeiroDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
            const ultimoDia = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
            
            document.getElementById('relDataInicio').value = primeiroDia.toISOString().split('T')[0];
            document.getElementById('relDataFim').value = ultimoDia.toISOString().split('T')[0];
        }
    } catch (error) {
        console.error('Erro ao mudar aba:', error);
    }
}

async function carregarUsuarios() {
    try {
        if (!verificarFuncoesAuth()) {
            return;
        }
        
        const response = await fazerRequisicao('admin.php?action=usuarios');
        
        if (response.success) {
            usuarios = response.data.usuarios || [];
            exibirUsuarios();
        } else {
            mostrarNotificacao('Erro ao carregar usuários: ' + response.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao carregar usuários:', error);
        mostrarNotificacao('Erro ao carregar usuários: ' + error.message, 'error');
    }
}

function exibirUsuarios() {
    const tbody = document.getElementById('usuariosTableBody');
    
    if (usuarios.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                    Nenhum usuário cadastrado
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = usuarios.map(usuario => `
        <tr>
            <td>${usuario.nome}</td>
            <td>${usuario.cpf ? formatarCPF(usuario.cpf) : '-'}</td>
            <td>${usuario.matricula || '-'}</td>
            <td>${usuario.cargo || '-'}</td>
            <td>${usuario.login || '-'}</td>
            <td>
                <span class="status-badge ${usuario.tipo}">
                    ${usuario.tipo === 'admin' ? 'Admin' : 'Funcionário'}
                </span>
            </td>
            <td>${usuario.departamento_nome || '-'}</td>
            <td>${usuario.grupo_jornada_nome || '-'}</td>
            <td>${usuario.carga_diaria_minutos ? formatarTempo(usuario.carga_diaria_minutos) : '08:00'}</td>
            <td>${usuario.tolerancia_minutos || 10} min</td>
            <td>
                <span class="status-badge ${usuario.ativo == 1 ? 'completo' : 'incompleto'}">
                    ${usuario.ativo == 1 ? 'Ativo' : 'Inativo'}
                </span>
            </td>
            <td>
                <div class="action-buttons">
                    <div class="action-group primary">
                        <button class="btn-action btn-edit" onclick="editarUsuario(${usuario.id})" title="Editar usuário">
                            <i class="fas fa-edit"></i>
                            <span>Editar</span>
                        </button>
                    </div>
                    
                    <div class="action-group secondary">
                        ${usuario.tipo === 'admin' ? `
                            <button class="btn-action btn-reset-password" onclick="resetarSenha(${usuario.id})" title="Resetar senha para 123456">
                                <i class="fas fa-key"></i>
                                <span>Reset Senha</span>
                            </button>
                        ` : ''}
                        ${usuario.tipo === 'funcionario' ? `
                            <button class="btn-action btn-reset-pin" onclick="resetarPin(${usuario.id})" title="Resetar PIN do funcionário">
                                <i class="fas fa-unlock"></i>
                                <span>Reset PIN</span>
                            </button>
                        ` : ''}
                    </div>
                    
                    ${usuario.login !== 'admin' ? `
                        <div class="action-group danger">
                            <button class="btn-action btn-toggle-status ${usuario.ativo == 1 ? 'btn-deactivate' : 'btn-activate'}" 
                                    onclick="alternarStatusUsuario(${usuario.id}, ${usuario.ativo})" 
                                    title="${usuario.ativo == 1 ? 'Desativar usuário' : 'Ativar usuário'}">
                                <i class="fas fa-${usuario.ativo == 1 ? 'ban' : 'check'}"></i>
                                <span>${usuario.ativo == 1 ? 'Desativar' : 'Ativar'}</span>
                            </button>
                        </div>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

// Função para controlar visibilidade dos campos baseado no tipo de usuário
function toggleUserFields() {
    const userType = document.getElementById('userType').value;
    const loginGroup = document.getElementById('loginGroup');
    const passwordGroup = document.getElementById('passwordGroup');
    const pinGroup = document.getElementById('pinGroup');
    const userLogin = document.getElementById('userLogin');
    const userPassword = document.getElementById('userPassword');
    const userPin = document.getElementById('userPin');
    
    if (userType === 'funcionario') {
        // Para funcionários: ocultar login/senha, mostrar PIN
        loginGroup.style.display = 'none';
        passwordGroup.style.display = 'none';
        pinGroup.style.display = 'block';
        
        // Remover required dos campos ocultos
        userLogin.required = false;
        userPassword.required = false;
        userPin.required = true;
        
        // Limpar campos ocultos
        userLogin.value = '';
        userPassword.value = '';
    } else {
        // Para administradores: mostrar login/senha, ocultar PIN
        loginGroup.style.display = 'block';
        passwordGroup.style.display = 'block';
        pinGroup.style.display = 'none';
        
        // Adicionar required nos campos visíveis
        userLogin.required = true;
        userPassword.required = false; // Senha não é obrigatória na edição
        userPin.required = false;
        
        // Limpar campo PIN
        userPin.value = '';
    }
}

async function abrirModalUsuario(usuarioId = null) {
    const modal = document.getElementById('userModal');
    const form = document.getElementById('userForm');
    const title = document.getElementById('modalTitle');
    const passwordHelp = document.getElementById('passwordHelp');
    
    // Reset do formulário
    form.reset();
    document.getElementById('userId').value = '';
    document.getElementById('userCpf').value = '';
    document.getElementById('userMatricula').value = '';
    document.getElementById('userCargo').value = '';
    document.getElementById('userCarga').value = '08:00';
    document.getElementById('userTolerancia').value = '10';
    document.getElementById('userAtivo').value = '1';
    
    // Carregar departamentos e grupos de jornada
    await carregarSelectsUsuario();
    
    // Aplicar visibilidade dos campos baseado no tipo padrão
    toggleUserFields();
    
    // Configurar event listener para preenchimento automático ANTES de preencher os campos
    configurarPreenchimentoAutomatico();
    
    if (usuarioId) {
        // Modo edição
        const usuario = usuarios.find(u => u.id == usuarioId);
        if (usuario) {
            title.textContent = 'Editar Usuário';
            document.getElementById('userId').value = usuario.id;
            document.getElementById('userNameInput').value = usuario.nome;
            document.getElementById('userCpf').value = usuario.cpf || '';
            document.getElementById('userMatricula').value = usuario.matricula || '';
            document.getElementById('userCargo').value = usuario.cargo || '';
            document.getElementById('userLogin').value = usuario.login;
            document.getElementById('userType').value = usuario.tipo;
            document.getElementById('userDepartamento').value = usuario.departamento_id || '';
            document.getElementById('userGrupoJornada').value = usuario.grupo_jornada_id || '';
            document.getElementById('userCarga').value = usuario.carga_diaria_minutos ? formatarTempo(usuario.carga_diaria_minutos) : '08:00';
            document.getElementById('userTolerancia').value = usuario.tolerancia_minutos || 10;
            document.getElementById('userAtivo').value = usuario.ativo;
            
            // Preencher PIN se for funcionário (não mostrar o valor real por segurança)
            if (usuario.tipo === 'funcionario') {
                document.getElementById('userPin').placeholder = 'PIN atual definido';
                document.getElementById('userPin').value = ''; // Não mostrar o PIN real
            }
            
            passwordHelp.style.display = 'block';
            document.getElementById('userPassword').placeholder = 'Deixe em branco para manter atual';
            
            // Aplicar visibilidade dos campos baseado no tipo do usuário
            toggleUserFields();
        }
    } else {
        // Modo criação
        title.textContent = 'Novo Usuário';
        passwordHelp.style.display = 'none';
        document.getElementById('userPassword').placeholder = 'Digite a senha';
        document.getElementById('userPassword').required = true;
    }
    
    // Configurar validação do PIN
    const pinInput = document.getElementById('userPin');
    if (pinInput) {
        pinInput.addEventListener('input', function(e) {
            // Permitir apenas números
            e.target.value = e.target.value.replace(/\D/g, '');
            
            // Limitar a 4 dígitos
            if (e.target.value.length > 4) {
                e.target.value = e.target.value.substring(0, 4);
            }
        });
    }
    
    modal.classList.add('active');
}

function fecharModal() {
    const modal = document.getElementById('userModal');
    modal.classList.remove('active');
    
    // Reset validações
    document.getElementById('userPassword').required = false;
}

// Função para formatar CPF
function formatarCPF(cpf) {
    // Remove tudo que não é dígito
    cpf = cpf.replace(/\D/g, '');
    
    // Aplica a máscara
    cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
    cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
    cpf = cpf.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    
    return cpf;
}

// Função para validar CPF
function validarCPF(cpf) {
    cpf = cpf.replace(/\D/g, '');
    
    if (cpf.length !== 11) return false;
    if (/^(\d)\1{10}$/.test(cpf)) return false;
    
    let soma = 0;
    for (let i = 0; i < 9; i++) {
        soma += parseInt(cpf.charAt(i)) * (10 - i);
    }
    let resto = 11 - (soma % 11);
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.charAt(9))) return false;
    
    soma = 0;
    for (let i = 0; i < 10; i++) {
        soma += parseInt(cpf.charAt(i)) * (11 - i);
    }
    resto = 11 - (soma % 11);
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.charAt(10))) return false;
    
    return true;
}

function configurarFormularios() {
    // Formatação automática do CPF
    const cpfInput = document.getElementById('userCpf');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            e.target.value = formatarCPF(e.target.value);
        });
        
        cpfInput.addEventListener('blur', function(e) {
            const cpf = e.target.value.replace(/\D/g, '');
            if (cpf.length === 11 && !validarCPF(cpf)) {
                e.target.style.borderColor = '#ef4444';
                e.target.title = 'CPF inválido';
            } else {
                e.target.style.borderColor = '';
                e.target.title = '';
            }
        });
    }
    
    // Formulário de usuário
    document.getElementById('userForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        if (!verificarFuncoesAuth()) {
            return;
        }
        
        const formData = new FormData(this);
        const userId = document.getElementById('userId').value;
        const isEdit = !!userId;
        
        const userType = document.getElementById('userType').value;
        
        const userData = {
            action: isEdit ? 'atualizar_usuario' : 'criar_usuario',
            nome: formData.get('userName') || document.getElementById('userNameInput').value,
            cpf: document.getElementById('userCpf').value.replace(/\D/g, '') || null,
            matricula: document.getElementById('userMatricula').value || null,
            cargo: document.getElementById('userCargo').value || null,
            tipo: userType,
            departamento_id: document.getElementById('userDepartamento').value || null,
            grupo_jornada_id: document.getElementById('userGrupoJornada').value || null,
            ativo: parseInt(formData.get('userAtivo') || document.getElementById('userAtivo').value)
        };
        
        
        // Adicionar campos específicos baseado no tipo
        if (userType === 'admin') {
            // Para administradores: login e senha
            userData.login = formData.get('userLogin') || document.getElementById('userLogin').value;
            
            const senha = formData.get('userPassword') || document.getElementById('userPassword').value;
            if (senha) {
                userData.senha = senha;
            }
        } else if (userType === 'funcionario') {
            // Para funcionários: PIN
            const pin = document.getElementById('userPin').value;
            if (pin) {
                // Validar PIN
                if (!/^\d{4}$/.test(pin)) {
                    mostrarNotificacao('PIN deve conter exatamente 4 dígitos numéricos', 'error');
                    return;
                }
                userData.pin = pin;
            } else if (!isEdit) {
                // PIN é obrigatório para novos funcionários
                mostrarNotificacao('PIN é obrigatório para funcionários', 'error');
                return;
            }
        }
        
        if (isEdit) {
            userData.id = parseInt(userId);
        }
        
        const btnSubmit = document.querySelector('#userForm button[type="submit"]');
        if (btnSubmit) {
            mostrarLoading(btnSubmit);
        }
        
        try {
            const response = await fazerRequisicao('admin.php', {
                method: 'POST',
                body: JSON.stringify(userData)
            });
            
            if (response.success) {
                mostrarNotificacao(`Usuário ${isEdit ? 'atualizado' : 'criado'} com sucesso!`, 'success');
                fecharModal();
                await carregarUsuarios();
            } else {
                mostrarNotificacao(response.message, 'error');
            }
        } catch (error) {
            console.error('Erro ao salvar usuário:', error);
            mostrarNotificacao('Erro ao salvar usuário: ' + error.message, 'error');
        } finally {
            if (btnSubmit) {
                mostrarLoading(btnSubmit, false);
            }
        }
    });
    
    // Formulário de departamento
    document.getElementById('departamentoForm').addEventListener('submit', salvarDepartamento);
    
    // Formulário de jornada
    document.getElementById('jornadaForm').addEventListener('submit', salvarJornada);
}

function editarUsuario(usuarioId) {
    abrirModalUsuario(usuarioId);
}

async function resetarSenha(usuarioId) {
    if (!verificarFuncoesAuth()) {
        return;
    }
    
    const usuario = usuarios.find(u => u.id == usuarioId);
    
    if (!confirm(`Confirma o reset da senha do usuário "${usuario.nome}"?\nA nova senha será: 123456`)) {
        return;
    }
    
    try {
        const response = await fazerRequisicao('admin.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'resetar_senha',
                id: usuarioId,
                nova_senha: '123456'
            })
        });
        
        if (response.success) {
            mostrarNotificacao('Senha resetada com sucesso! Nova senha: 123456', 'success');
        }
    } catch (error) {
        console.error('Erro ao resetar senha:', error);
        mostrarNotificacao(error.message, 'error');
    }
}

async function resetarPin(usuarioId) {
    if (!verificarFuncoesAuth()) {
        return;
    }
    
    const usuario = usuarios.find(u => u.id == usuarioId);
    
    if (!confirm(`Confirma o reset do PIN do usuário "${usuario.nome}"?\nO funcionário precisará definir um novo PIN no próximo acesso.`)) {
        return;
    }
    
    try {
        const response = await fazerRequisicao('reset_pin.php', {
            method: 'POST',
            body: JSON.stringify({
                usuario_id: usuarioId
            })
        });
        
        if (response.success) {
            mostrarNotificacao('PIN resetado com sucesso!', 'success');
            await carregarUsuarios();
        }
    } catch (error) {
        console.error('Erro ao resetar PIN:', error);
        mostrarNotificacao(error.message, 'error');
    }
}

async function alternarStatusUsuario(usuarioId, statusAtual) {
    if (!verificarFuncoesAuth()) {
        return;
    }
    
    const usuario = usuarios.find(u => u.id == usuarioId);
    const novoStatus = statusAtual == 1 ? 0 : 1;
    const acao = novoStatus ? 'ativar' : 'desativar';
    
    if (!confirm(`Confirma ${acao} o usuário "${usuario.nome}"?`)) {
        return;
    }
    
    try {
        const response = await fazerRequisicao('admin.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'atualizar_usuario',
                id: usuarioId,
                nome: usuario.nome,
                login: usuario.login,
                tipo: usuario.tipo,
                ativo: novoStatus,
                carga_diaria: usuario.carga_diaria || '08:00:00',
                tolerancia: usuario.tolerancia || 10
            })
        });
        
        if (response.success) {
            mostrarNotificacao(`Usuário ${acao}do com sucesso!`, 'success');
            await carregarUsuarios();
        }
    } catch (error) {
        console.error('Erro ao alterar status:', error);
        mostrarNotificacao(error.message, 'error');
    }
}

function configurarFiltrosRelatorio() {
    const hoje = new Date();
    const inicioMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    
    document.getElementById('relDataInicio').value = inicioMes.toISOString().split('T')[0];
    document.getElementById('relDataFim').value = hoje.toISOString().split('T')[0];
}

function preencherSelectUsuarios() {
    const select = document.getElementById('relUsuario');
    select.innerHTML = '<option value="">Todos os usuários</option>';
    
    usuarios.forEach(usuario => {
        if (usuario.tipo === 'funcionario' && usuario.ativo == 1) {
            select.innerHTML += `<option value="${usuario.id}">${usuario.nome}</option>`;
        }
    });
}

// ⚠️ FUNÇÃO DEPRECIADA: Relatório Padrão removido
// Sistema unificado usa apenas Cartão de Ponto
async function buscarRelatorio() {
    mostrarNotificacao('Use o botão "Gerar Cartão de Ponto" para visualizar relatórios', 'info');
    return;
}

// ⚠️ FUNÇÃO DEPRECIADA: Relatório Padrão removido
// Sistema unificado usa apenas Cartão de Ponto
function exibirRelatorio() {
    const tbody = document.getElementById('relatorioTableBody');
    
    // Não fazer nada - função depreciada
    if (!tbody) return;
    
    if (relatorios.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                    Nenhum registro encontrado para os filtros selecionados
                </td>
            </tr>
        `;
        return;
    }
    
    // Agrupar por usuário e data
    const relatorioAgrupado = {};
    relatorios.forEach(reg => {
        const chave = `${reg.usuario_nome}-${reg.data}`;
        if (!relatorioAgrupado[chave]) {
            relatorioAgrupado[chave] = {
                usuario_nome: reg.usuario_nome,
                data: reg.data,
                carga_diaria_minutos: reg.carga_diaria_minutos,
                registros: [],
                justificativa: null
            };
        }
        relatorioAgrupado[chave].registros.push(reg);
        
        // Capturar informações de justificativa (se houver) - priorizar a primeira encontrada
        if (reg.justificativa_id && !relatorioAgrupado[chave].justificativa) {
            relatorioAgrupado[chave].justificativa = {
                id: reg.justificativa_id,
                codigo: reg.justificativa_codigo,
                tipo_nome: reg.justificativa_tipo_nome,
                periodo_parcial: reg.periodo_parcial,
                motivo: reg.justificativa_motivo,
                status: reg.justificativa_status
            };
            
        }
    });
    
    
    // Usar função centralizada para calcular estatísticas
    const registrosParaCalculo = Object.values(relatorioAgrupado).map(grupo => {
        const registroUnificado = {
            entrada_manha: '--',
            saida_almoco: '--',
            volta_almoco: '--',
            saida_tarde: '--'
        };
        
        // Mapear registros para estrutura unificada
        grupo.registros.forEach(reg => {
            if (reg.tipo === 'entrada_manha') registroUnificado.entrada_manha = reg.hora;
            else if (reg.tipo === 'saida_almoco') registroUnificado.saida_almoco = reg.hora;
            else if (reg.tipo === 'volta_almoco') registroUnificado.volta_almoco = reg.hora;
            else if (reg.tipo === 'saida_tarde') registroUnificado.saida_tarde = reg.hora;
        });
        
        return {
            data: grupo.data,
            entrada_manha: registroUnificado.entrada_manha,
            saida_almoco: registroUnificado.saida_almoco,
            volta_almoco: registroUnificado.volta_almoco,
            saida_tarde: registroUnificado.saida_tarde,
            justificativa: grupo.justificativa
        };
    });
    
    // Horários cadastrados (usar do primeiro registro)
    const primeiroGrupo = Object.values(relatorioAgrupado)[0];
    const horariosCadastrados = primeiroGrupo ? {
        entrada_manha: primeiroGrupo.registros[0]?.entrada_manha || '08:00:00',
        saida_almoco: primeiroGrupo.registros[0]?.saida_almoco || '12:00:00',
        volta_almoco: primeiroGrupo.registros[0]?.volta_almoco || '13:00:00',
        saida_tarde: primeiroGrupo.registros[0]?.saida_tarde || '18:00:00'
    } : null;
    
    // Calcular estatísticas usando função centralizada
    const estatisticas = timeCalculator.calcularEstatisticasPeriodo(registrosParaCalculo, horariosCadastrados);
    
    const registrosProcessados = Object.values(relatorioAgrupado).map(grupo => {
        // Unificar estrutura de dados com cartão de ponto
        const primeiroRegistro = grupo.registros[0];
        
        // Converter estrutura agrupada para estrutura do cartão de ponto
        const registroUnificado = {
            entrada_manha: '--',
            saida_almoco: '--',
            volta_almoco: '--',
            saida_tarde: '--'
        };
        
        // Mapear registros para estrutura unificada
        grupo.registros.forEach(reg => {
            if (reg.tipo === 'entrada_manha') registroUnificado.entrada_manha = reg.hora;
            else if (reg.tipo === 'saida_almoco') registroUnificado.saida_almoco = reg.hora;
            else if (reg.tipo === 'volta_almoco') registroUnificado.volta_almoco = reg.hora;
            else if (reg.tipo === 'saida_tarde') registroUnificado.saida_tarde = reg.hora;
        });
        
        // Converter carga_diaria_minutos para formato de tempo (HH:MM:SS)
        let cargaDiaria = '08:00:00'; // Padrão
        if (grupo.carga_diaria_minutos) {
            const horas = Math.floor(grupo.carga_diaria_minutos / 60);
            const minutos = grupo.carga_diaria_minutos % 60;
            cargaDiaria = `${horas.toString().padStart(2, '0')}:${minutos.toString().padStart(2, '0')}:00`;
        }
        
        // Usar horários cadastrados do grupo de jornada (exatamente como o cartão ponto)
        const horariosCadastrados = {
            entrada_manha: primeiroRegistro.entrada_manha || '08:00:00',
            saida_almoco: primeiroRegistro.saida_almoco || '12:00:00',
            volta_almoco: primeiroRegistro.volta_almoco || '13:00:00',
            saida_tarde: primeiroRegistro.saida_tarde || '18:00:00'
        };
        
        // Preparar batidas reais - EXATAMENTE como no cartão de ponto
        const batidasReais = [
            { tipo: 'entrada_manha', hora: registroUnificado.entrada_manha.includes(':') ? registroUnificado.entrada_manha : registroUnificado.entrada_manha + ':00' },
            { tipo: 'saida_almoco', hora: registroUnificado.saida_almoco.includes(':') ? registroUnificado.saida_almoco : registroUnificado.saida_almoco + ':00' },
            { tipo: 'volta_almoco', hora: registroUnificado.volta_almoco.includes(':') ? registroUnificado.volta_almoco : registroUnificado.volta_almoco + ':00' },
            { tipo: 'saida_tarde', hora: registroUnificado.saida_tarde.includes(':') ? registroUnificado.saida_tarde : registroUnificado.saida_tarde + ':00' }
        ].filter(p => p.hora !== '--:00' && p.hora !== '--');
        
        const horasTrabalhadasStr = timeCalculator.calcularHorasTrabalhadas(batidasReais);
        
        // Usar a mesma lógica do cartão de ponto com tolerância
        const toleranciaMinutos = grupo.tolerancia_minutos || 10;
        const saldoJornada = timeCalculator.calcularSaldoJornada(batidasReais, horariosCadastrados, grupo.data, toleranciaMinutos);
        
        // Aplicar tolerância às horas trabalhadas se necessário
        let horasTrabalhadasFinal = horasTrabalhadasStr;
        if (saldoJornada.saldoBruto > 0 && saldoJornada.status === 'normal') {
            // Se funcionário trabalhou mais que o esperado e está dentro da tolerância,
            // mostrar apenas a carga diária (8:00)
            horasTrabalhadasFinal = '08:00';
        }
        const saldoInfo = {
            saldo: saldoJornada.saldoFormatado,
            status: saldoJornada.status,
            tipo: saldoJornada.tipo,
            toleranciaAplicada: saldoJornada.toleranciaAplicada,
            dentroTolerancia: saldoJornada.status === 'normal'
        };
        const completo = timeCalculator.diaCompleto(grupo.registros, grupo.data, grupo.justificativa);
        
        // Totais já calculados pela função centralizada
        
        // Determinar indicador de justificativa
        
        let indicadorJustificativa = '';
        if (grupo.justificativa) {
            const codigo = grupo.justificativa.codigo;
            const periodo = grupo.justificativa.periodo_parcial;
            
            switch (codigo) {
                case 'FER':
                    indicadorJustificativa = '<span class="justificativa ferias">FÉRIAS</span>';
                    break;
                case 'ATM':
                    indicadorJustificativa = '<span class="justificativa atestado">ATESTADO</span>';
                    break;
                case 'AJP':
                    indicadorJustificativa = `<span class="justificativa ausencia">AUSÊNCIA ${periodo.toUpperCase()}</span>`;
                    break;
                case 'LIC':
                    indicadorJustificativa = '<span class="justificativa licenca">LICENÇA</span>';
                    break;
                case 'FOL':
                    indicadorJustificativa = '<span class="justificativa folga">FOLGA</span>';
                    break;
                default:
                    indicadorJustificativa = '<span class="justificativa">JUSTIFICADO</span>';
            }
        }

        // Verificar se é domingo (folga) - usar flag do backend ou calcular
        const isDomingo = primeiroRegistro.is_domingo || (() => {
            const [ano, mes, dia] = grupo.data.split('-');
            const dataObj = new Date(parseInt(ano), parseInt(mes) - 1, parseInt(dia));
            return dataObj.getDay() === 0; // 0 = domingo
        })();

        // Verificar se domingo é folga (usar configuração do grupo de jornada)
        const domingoFolga = primeiroRegistro.domingo_folga !== false; // Padrão: true (folga)

        // Adicionar indicador para domingo se não há justificativa e é configurado como folga
        if (isDomingo && domingoFolga && !grupo.justificativa) {
            indicadorJustificativa = '<span class="justificativa domingo-folga">FOLGA</span>';
        }

        // Se há justificativa ou é domingo configurado como folga, não mostrar horários nem horas trabalhadas
        const mostrarHorarios = !grupo.justificativa && !(isDomingo && domingoFolga);
        
        
        return {
            html: `
            <tr>
                <td>${grupo.usuario_nome}</td>
                <td>${timeCalculator.formatarData(grupo.data)}</td>
                    <td>${mostrarHorarios ? (registroUnificado.entrada_manha || '--') : '--'}</td>
                    <td>${mostrarHorarios ? (registroUnificado.saida_almoco || '--') : '--'}</td>
                    <td>${mostrarHorarios ? (registroUnificado.volta_almoco || '--') : '--'}</td>
                    <td>${mostrarHorarios ? (registroUnificado.saida_tarde || '--') : '--'}</td>
                    <td>${mostrarHorarios ? (horasTrabalhadasFinal ? horasTrabalhadasFinal.substring(0, 5) : '00:00') : '--'}</td>
                    <td>
                        ${grupo.justificativa ? indicadorJustificativa : (
                            saldoInfo.status !== 'normal' ? `
                                <span class="${saldoInfo.saldo.startsWith('+') ? 'extra' : saldoInfo.saldo.startsWith('-') ? 'falta' : saldoInfo.status}">${saldoInfo.saldo}</span>
                            ` : '--'
                        )}
                    </td>
                    <td>
                        ${grupo.tem_edicao ? `
                            <div class="ajuste-detalhes">
                                <div class="ajuste-info">
                                    <i class="fas fa-edit" style="color: #f59e0b; margin-right: 5px;"></i>
                                    <span style="font-size: 0.85rem; color: #6b7280;">
                                        ${grupo.detalhes_ajuste && grupo.detalhes_ajuste.length > 0 ? 
                                            grupo.detalhes_ajuste.map(ajuste => `
                                                <div style="margin-bottom: 3px;">
                                                    <strong>${ajuste.tipo.replace('_', ' ').toUpperCase()}:</strong> 
                                                    ${ajuste.editado_por_nome} 
                                                    (${ajuste.motivo_ajuste.replace('_', ' ')})
                                                    ${ajuste.tempo_ajustado_minutos > 0 ? `+${ajuste.tempo_ajustado_minutos}min` : ''}
                                                </div>
                                            `).join('') : 'Ajustado'
                                        }
                                    </span>
                                </div>
                            </div>
                        ` : '--'}
                    </td>
                </tr>
            `,
            saldoInfo
        };
    });
    
    // Gerar HTML da tabela com totais
    const htmlTabela = registrosProcessados.map(r => r.html).join('');
    
    // Usar estatísticas centralizadas
    const totalHorasStr = estatisticas.totalHorasTrabalhadas;
    const totalExtrasStr = estatisticas.totalExtras;
    const totalFaltasStr = estatisticas.totalFaltas;
    const saldoFinal = estatisticas.saldoFinalMinutos;
    const saldoFinalStr = estatisticas.saldoFinal;
    const diasCompletos = estatisticas.diasCompletos;
    const diasIncompletos = estatisticas.diasIncompletos;
    
    const htmlTotais = `
        <tr style="background-color: #f8f9fa; font-weight: bold; border-top: 2px solid #333;">
            <td colspan="7" style="text-align: right; padding: 10px;">
                <strong>TOTAIS DO PERÍODO:</strong>
            </td>
            <td style="text-align: center; padding: 10px;">
                <strong>${totalHorasStr.substring(0, 5)}</strong>
            </td>
            <td style="text-align: center; padding: 10px;">
                <div style="font-size: 12px;">
                    <div>Dias Completos: <strong>${diasCompletos}</strong></div>
                    <div>Dias Incompletos: <strong>${diasIncompletos}</strong></div>
                    ${estatisticas.saldoFinalMinutos > 0 ? `<div class="extra">Extras: <strong>+${totalExtrasStr.substring(0, 5)}</strong></div>` : ''}
                    ${estatisticas.saldoFinalMinutos < 0 ? `<div class="falta">Faltas: <strong>-${totalFaltasStr.substring(0, 5)}</strong></div>` : ''}
                    ${saldoFinal !== 0 ? `
                        <div class="${saldoFinal > 0 ? 'extra' : 'falta'}">
                            Saldo Final: <strong>${saldoFinal > 0 ? '+' : '-'}${saldoFinalStr.substring(0, 5)}</strong>
                        </div>
                    ` : ''}
                </div>
            </td>
            <td style="text-align: center; padding: 10px;">
                <div style="font-size: 11px; color: #6b7280;">
                    ${estatisticas.totalAjustes > 0 ? `
                        <div style="margin-bottom: 3px;">
                            <i class="fas fa-edit" style="color: #f59e0b;"></i>
                            <strong>${estatisticas.totalAjustes}</strong> ajustes
                        </div>
                        <div style="font-size: 10px;">
                            Tempo: <strong>${estatisticas.tempoTotalAjustes}</strong>
                        </div>
                    ` : '--'}
                </div>
            </td>
            </tr>
        `;
    
    tbody.innerHTML = htmlTabela + htmlTotais;
}

// ⚠️ FUNÇÃO DEPRECIADA: Relatório Padrão removido em favor do Cartão de Ponto
// Mantida apenas para compatibilidade, mas não é mais usada
async function gerarRelatorio() {
    // Redirecionar para Cartão de Ponto
    mostrarNotificacao('Use o "Cartão de Ponto" para gerar relatórios oficiais', 'info');
    return;
}

async function gerarCartaoPonto() {
    if (!verificarFuncoesAuth()) {
        return;
    }
    
    const dataInicio = document.getElementById('relDataInicio').value;
    const dataFim = document.getElementById('relDataFim').value;
    const usuarioId = document.getElementById('relUsuario').value;
    
    // ✅ Validações aprimoradas
    if (!usuarioId) {
        mostrarNotificacao('⚠️ Por favor, selecione um funcionário', 'warning');
        document.getElementById('relUsuario').focus();
        document.getElementById('relUsuario').style.borderColor = '#dc2626';
        setTimeout(() => {
            document.getElementById('relUsuario').style.borderColor = '';
        }, 2000);
        return;
    }
    
    if (!dataInicio || !dataFim) {
        mostrarNotificacao('⚠️ Selecione o período (data início e fim)', 'warning');
        return;
    }
    
    // Validar que data fim não é anterior à data início
    if (new Date(dataFim) < new Date(dataInicio)) {
        mostrarNotificacao('⚠️ Data fim não pode ser anterior à data início', 'warning');
        return;
    }
    
    // Detectar se é período mensal completo ou personalizado
    const inicio = new Date(dataInicio);
    const fim = new Date(dataFim);
    
    const isMonthlyComplete = (
        inicio.getDate() === 1 && 
        inicio.getMonth() === fim.getMonth() && 
        inicio.getFullYear() === fim.getFullYear() &&
        fim.getDate() === new Date(fim.getFullYear(), fim.getMonth() + 1, 0).getDate()
    );
    
    // Se não for mensal completo, mostrar aviso mas permitir
    if (!isMonthlyComplete) {
        const confirmar = confirm(
            '⚠️ Período Personalizado Detectado\n\n' +
            'O período selecionado não é um mês completo.\n' +
            'O cartão será gerado apenas para os dias selecionados.\n\n' +
            'Deseja continuar mesmo assim?'
        );
        
        if (!confirmar) {
            return;
        }
    }
    
    const year = inicio.getFullYear();
    const month = inicio.getMonth() + 1;
    
    const btnCartao = document.querySelector('button[onclick="gerarCartaoPonto()"]');
    mostrarLoading(btnCartao);
    
    try {
        // Usar diretamente o fallback PHP (mais confiável)
        console.log('Usando API PHP para cartão de ponto...');
        return await gerarCartaoPontoFallback(year, month, usuarioId, dataInicio, dataFim, isMonthlyComplete);
        
    } catch (error) {
        console.error('Erro ao gerar cartão de ponto:', error);
        mostrarNotificacao(error.message || 'Erro ao gerar cartão de ponto', 'error');
    } finally {
        mostrarLoading(btnCartao, false);
    }
}

function sugerirPeriodoMensal() {
    try {
        const dataInicio = document.getElementById('relDataInicio').value;
        const dataFim = document.getElementById('relDataFim').value;
        
        if (!dataInicio || !dataFim) return;
        
        const inicio = new Date(dataInicio);
        const fim = new Date(dataFim);
        
        // Verificar se é período mensal completo
        const isMonthlyComplete = (
            inicio.getDate() === 1 && 
            inicio.getMonth() === fim.getMonth() && 
            inicio.getFullYear() === fim.getFullYear() &&
            fim.getDate() === new Date(fim.getFullYear(), fim.getMonth() + 1, 0).getDate()
        );
        
        // Remover sugestões anteriores
        const existingSuggestion = document.getElementById('periodo-suggestion');
        if (existingSuggestion) {
            existingSuggestion.remove();
        }
        
        if (!isMonthlyComplete) {
            // Criar sugestão de período mensal
            const suggestionDiv = document.createElement('div');
            suggestionDiv.id = 'periodo-suggestion';
            suggestionDiv.style.cssText = `
                background: #e3f2fd; 
                border: 1px solid #2196f3; 
                border-radius: 5px; 
                padding: 10px; 
                margin: 10px 0; 
                font-size: 12px;
                color: #1976d2;
            `;
            
            const firstDay = new Date(inicio.getFullYear(), inicio.getMonth(), 1);
            const lastDay = new Date(inicio.getFullYear(), inicio.getMonth() + 1, 0);
            
            suggestionDiv.innerHTML = `
                <strong>💡 Sugestão para Cartão de Ponto:</strong><br>
                Para gerar um cartão mensal completo, use: 
                <strong>${firstDay.toISOString().split('T')[0]}</strong> a 
                <strong>${lastDay.toISOString().split('T')[0]}</strong>
                <br><small>Ou clique <a href="#" onclick="aplicarPeriodoMensal('${firstDay.toISOString().split('T')[0]}', '${lastDay.toISOString().split('T')[0]}')">aqui</a> para aplicar automaticamente.</small>
            `;
            
            // Inserir após os campos de data
            const formContainer = document.querySelector('#relDataFim').closest('div[style*="display: flex"]');
            if (formContainer) {
                formContainer.parentNode.insertBefore(suggestionDiv, formContainer.nextSibling);
            } else {
                // Fallback: inserir após o campo de data fim
                const dataFimField = document.getElementById('relDataFim');
                if (dataFimField && dataFimField.parentNode) {
                    dataFimField.parentNode.parentNode.insertBefore(suggestionDiv, dataFimField.parentNode.nextSibling);
                }
            }
        }
    } catch (error) {
        console.error('Erro na função sugerirPeriodoMensal:', error);
        // Silenciosamente ignorar o erro para não interromper a UX
    }
}

function aplicarPeriodoMensal(dataInicio, dataFim) {
    document.getElementById('relDataInicio').value = dataInicio;
    document.getElementById('relDataFim').value = dataFim;
    
    // Remover sugestão
    const suggestion = document.getElementById('periodo-suggestion');
    if (suggestion) {
        suggestion.remove();
    }
    
    mostrarNotificacao('Período mensal aplicado automaticamente!', 'success');
}

// Funções para gerenciar configurações da empresa

function preencherConfiguracoes(configs) {
    // Mapeamento de campos com valores padrão
    const camposEmpresa = {
        'empresa_nome': 'Tech-Ponto Sistemas',
        'empresa_cnpj': '00.000.000/0001-00',
        'empresa_endereco': 'Rua da Inovação, 123 - Centro',
        'empresa_cidade': 'São Paulo - SP',
        'empresa_telefone': '(11) 1234-5678',
        'empresa_email': 'contato@techponto.com',
        'empresa_logo': ''
    };
    
    // Preencher cada campo
    Object.keys(camposEmpresa).forEach(chave => {
        const input = document.getElementById(chave);
        
        if (input) {
            if (chave === 'empresa_logo') {
                // Para logo, se for base64, mostrar preview
                const valor = configs[chave]?.valor || camposEmpresa[chave];
                if (valor && valor.startsWith('data:image/')) {
                    const preview = document.getElementById('logo-preview');
                    const previewImage = document.getElementById('preview-image');
                    if (preview && previewImage) {
                        previewImage.src = valor;
                        preview.style.display = 'block';
                    }
                }
                // Não definir valor para input file
            } else {
                // Obter valor dos dados do banco
                const valor = configs[chave]?.valor || '';
                input.value = valor;
            }
        }
    });
}

async function carregarConfiguracoes() {
    if (!verificarFuncoesAuth()) {
        return;
    }
    
    try {
        const response = await fazerRequisicao('admin.php?action=configuracoes_empresa');
        
        if (response.success && response.data?.configuracoes) {
            const configs = response.data.configuracoes;
            
            // Função robusta para preencher configurações
            preencherConfiguracoes(configs);
            
            // Atualizar preview
            atualizarPreview();
            
            mostrarNotificacao('Configurações carregadas com sucesso!', 'success');
        } else {
            throw new Error(response.message || 'Erro ao carregar configurações');
        }
    } catch (error) {
        console.error('Erro ao carregar configurações:', error);
        console.log('🔄 Aplicando valores padrão como fallback...');
        
        // Fallback: preencher com valores padrão se a API falhar
        preencherConfiguracoes({});
        
        mostrarNotificacao('Erro ao carregar configurações. Usando valores padrão.', 'warning');
    }
}

async function salvarConfiguracoes(event) {
    event.preventDefault();
    
    if (!verificarFuncoesAuth()) {
        return;
    }
    
    const formData = new FormData(event.target);
    const configuracoes = {};
    
    // Coletar dados do formulário (exceto arquivo)
    for (const [key, value] of formData.entries()) {
        if (key !== 'empresa_logo') {
            configuracoes[key] = value;
        }
    }
    
    // Lidar com upload do logo
    const logoFile = document.getElementById('empresa_logo').files[0];
    if (logoFile) {
        // Converter arquivo para base64 para salvar no banco
        const base64 = await fileToBase64(logoFile);
        configuracoes['empresa_logo'] = base64;
    }
    
    try {
        const response = await fazerRequisicao('admin.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'salvar_configuracoes_empresa',
                configuracoes: configuracoes
            })
        });
        
        if (response.success) {
            mostrarNotificacao('Configurações salvas com sucesso!', 'success');
            atualizarPreview();
        } else {
            throw new Error(response.message || 'Erro ao salvar configurações');
        }
    } catch (error) {
        console.error('Erro ao salvar configurações:', error);
        mostrarNotificacao('Erro ao salvar configurações: ' + error.message, 'error');
    }
}

// Função auxiliar para converter arquivo para base64
function fileToBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = () => resolve(reader.result);
        reader.onerror = error => reject(error);
    });
}

// Função para preview do logo
function previewLogo(input) {
    const preview = document.getElementById('logo-preview');
    const previewImage = document.getElementById('preview-image');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            preview.style.display = 'block';
        };
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}

function atualizarPreview() {
    try {
        // Atualizar preview em tempo real
        const nome = document.getElementById('empresa_nome')?.value || 'Nome da Empresa';
        const cnpj = document.getElementById('empresa_cnpj')?.value || '00.000.000/0001-00';
        const endereco = document.getElementById('empresa_endereco')?.value || 'Rua da Empresa, 123';
        const cidade = document.getElementById('empresa_cidade')?.value || 'São Paulo - SP';
        const telefone = document.getElementById('empresa_telefone')?.value || '(11) 1234-5678';
        
        const previewNome = document.getElementById('preview-nome');
        const previewCnpj = document.getElementById('preview-cnpj');
        const previewEndereco = document.getElementById('preview-endereco');
        const previewCidade = document.getElementById('preview-cidade');
        const previewTelefone = document.getElementById('preview-telefone');
        const previewLogo = document.getElementById('preview-logo');
        
        if (previewNome) previewNome.textContent = nome;
        if (previewCnpj) previewCnpj.textContent = cnpj;
        if (previewEndereco) previewEndereco.textContent = endereco;
        if (previewCidade) previewCidade.textContent = cidade;
        if (previewTelefone) previewTelefone.textContent = telefone;
        
        // Atualizar logo no preview
        if (previewLogo) {
            const logoFile = document.getElementById('empresa_logo').files[0];
            if (logoFile) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewLogo.src = e.target.result;
                    previewLogo.style.display = 'block';
                };
                reader.readAsDataURL(logoFile);
            } else {
                previewLogo.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Erro ao atualizar preview:', error);
    }
}

function inicializarConfiguracoes() {
    try {
        // Verificar se o formulário existe
        const form = document.getElementById('configEmpresaForm');
        if (!form) {
            return;
        }
        
        // Adicionar event listeners para preview em tempo real
        const inputs = document.querySelectorAll('#configEmpresaForm input');
        if (inputs.length > 0) {
            inputs.forEach(input => {
                input.addEventListener('input', atualizarPreview);
            });
        }
        
        // Adicionar event listener para o formulário
        form.addEventListener('submit', salvarConfiguracoes);
        
        // Carregar dados imediatamente
        setTimeout(() => {
            carregarConfiguracoes();
        }, 100);
        
    } catch (error) {
        console.error('Erro ao inicializar configurações:', error);
    }
}

async function gerarCartaoPontoFallback(year, month, usuarioId, dataInicio, dataFim, isMonthlyComplete) {
    try {
        // Usar a API específica do cartão de ponto
        let url = `admin.php?action=cartao_ponto&data_inicio=${dataInicio}&data_fim=${dataFim}`;
        if (usuarioId) {
            url += `&usuario_id=${usuarioId}`;
        }
        
        const response = await fazerRequisicao(url);
        
        if (response.success && response.data.dados) {
            // Usar os dados do cartão de ponto (estrutura: {usuario, periodo, registros})
            const dadosCompletos = response.data.dados;
            const relatorioData = dadosCompletos.registros || [];
            
            // Usar dados da empresa retornados pela API (já incluídos)
            const empresa = response.data.empresa || {
                nome: 'Tech-Ponto Sistemas',
                cnpj: '00.000.000/0001-00',
                endereco: 'Rua da Inovação, 123 - Centro',
                cidade: 'São Paulo - SP',
                telefone: '(11) 1234-5678',
                email: 'contato@techponto.com',
                logo: ''
            };
            
            // ✅ CORRIGIDO: Usar dados do usuário retornados pela API diretamente
            const dados = converterRelatorioParaCartaoPonto(
                relatorioData, 
                usuarioId, 
                dataInicio, 
                dataFim, 
                dadosCompletos.usuario  // ✅ Passar objeto usuario da API
            );
            const html = gerarCartaoPontoHTML(dados, empresa, isMonthlyComplete);
            
            // Abrir HTML em nova janela
            const janela = window.open('', '_blank');
            
            if (janela && janela.document) {
                janela.document.write(html);
                janela.document.close();
                janela.focus();
                
                if (!isMonthlyComplete) {
                    mostrarNotificacao('Cartão de ponto (período personalizado) gerado! Use Ctrl+P para imprimir.', 'success');
                } else {
                    mostrarNotificacao('Cartão de ponto gerado! Use Ctrl+P para imprimir ou salvar como PDF.', 'success');
                }
            } else {
                // Se não conseguir abrir popup, fazer download como HTML
                const blob = new Blob([html], { type: 'text/html' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `cartao_ponto_${year}_${month.toString().padStart(2, '0')}.html`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                mostrarNotificacao('Popup bloqueado. Cartão baixado como HTML!', 'warning');
            }
        } else {
            throw new Error(response.message || 'Erro ao carregar dados do cartão de ponto');
        }
        
    } catch (error) {
        console.error('Erro ao gerar cartão de ponto:', error);
        throw error;
    }
}

// Converter estrutura do relatório para estrutura do cartão de ponto
function converterRelatorioParaCartaoPonto(relatorioData, usuarioId, dataInicio, dataFim, usuarioFromAPI = null) {
    // ✅ CORRIGIDO: Os dados JÁ VÊM AGRUPADOS do backend!
    // Backend retorna: [{data: '2025-09-29', entrada_manha: '08:05', saida_almoco: '13:22', ...}]
    // Não precisa reagrupar, apenas validar e usar
    
    // Verificar se os dados já vêm agrupados (tem campos entrada_manha, saida_almoco, etc)
    const jaAgrupado = relatorioData.length > 0 && 
                       relatorioData[0].hasOwnProperty('entrada_manha');
    
    let registros;
    
    if (jaAgrupado) {
        // ✅ Dados já vêm agrupados do backend - usar diretamente
        registros = relatorioData.map(reg => {
            // ✅ DEBUG: Verificar dados de ajuste
            console.log('Registro com ajuste:', reg);
            console.log('Tem edição:', reg.tem_edicao);
            console.log('Detalhes ajuste:', reg.detalhes_ajuste);
            
            return {
                data: reg.data,
                entrada_manha: reg.entrada_manha || '--',
                saida_almoco: reg.saida_almoco || '--',
                volta_almoco: reg.volta_almoco || '--',
                saida_tarde: reg.saida_tarde || '--',
                justificativa: reg.justificativa,
                // ✅ ADICIONAR: Dados de ajuste
                tem_edicao: reg.tem_edicao || false,
                detalhes_ajuste: reg.detalhes_ajuste || []
            };
        });
    } else {
        // Dados vêm no formato antigo (não agrupados) - fazer agrupamento
        const relatorioAgrupado = {};
        
        relatorioData.forEach(reg => {
            const data = reg.data;
            if (!relatorioAgrupado[data]) {
                relatorioAgrupado[data] = {
                    data: data,
                    registros: [],
                    entrada_manha: null,
                    saida_almoco: null,
                    volta_almoco: null,
                    saida_tarde: null,
                    justificativa: null
                };
            }
            
            relatorioAgrupado[data].registros.push(reg);
            
            // Mapear registros para horários
            if (reg.tipo === 'entrada_manha') relatorioAgrupado[data].entrada_manha = reg.hora;
            else if (reg.tipo === 'saida_almoco') relatorioAgrupado[data].saida_almoco = reg.hora;
            else if (reg.tipo === 'volta_almoco') relatorioAgrupado[data].volta_almoco = reg.hora;
            else if (reg.tipo === 'saida_tarde') relatorioAgrupado[data].saida_tarde = reg.hora;
            
            // Capturar justificativa se existir
            if (reg.justificativa_codigo) {
                relatorioAgrupado[data].justificativa = {
                    codigo: reg.justificativa_codigo,
                    motivo: reg.justificativa_motivo,
                    status: reg.justificativa_status
                };
            }
        });
        
        // Converter para estrutura do cartão de ponto
        registros = Object.values(relatorioAgrupado).map(grupo => ({
            data: grupo.data,
            entrada_manha: grupo.entrada_manha || '--',
            saida_almoco: grupo.saida_almoco || '--',
            volta_almoco: grupo.volta_almoco || '--',
            saida_tarde: grupo.saida_tarde || '--',
            justificativa: grupo.justificativa
        }));
    }
    
    // ✅ PRIORIZAR: Usar dados do usuário retornados pela API
    let usuario;
    
    if (usuarioFromAPI && usuarioFromAPI.nome) {
        // Usar dados vindos diretamente da API (mais confiável)
        usuario = {
            id: usuarioId,
            nome: usuarioFromAPI.nome || usuarioFromAPI.usuario_nome || 'Funcionário',
            cpf: usuarioFromAPI.cpf || 'Não informado',
            matricula: usuarioFromAPI.matricula || 'Não informada',
            cargo: usuarioFromAPI.cargo || 'Não informado',
            departamento: usuarioFromAPI.departamento_nome || usuarioFromAPI.departamento || 'Não informado',
            entrada_manha: usuarioFromAPI.entrada_manha_jornada || usuarioFromAPI.entrada_manha || '08:00:00',
            saida_almoco: usuarioFromAPI.saida_almoco_jornada || usuarioFromAPI.saida_almoco || '12:00:00',
            volta_almoco: usuarioFromAPI.volta_almoco_jornada || usuarioFromAPI.volta_almoco || '13:00:00',
            saida_tarde: usuarioFromAPI.saida_tarde_jornada || usuarioFromAPI.saida_tarde || '18:00:00',
            tolerancia_minutos: usuarioFromAPI.tolerancia_minutos || 10
        };
    } else {
        // Fallback: Pegar do primeiro registro que tenha dados completos
        const primeiroRegistro = relatorioData.find(reg => 
            reg.usuario_nome && 
            reg.matricula && 
            reg.departamento_nome &&
            !reg.justificativa_codigo
        ) || relatorioData.find(reg => reg.usuario_nome && reg.matricula) || relatorioData[0];
        
        // Se ainda não temos dados corretos, buscar em todos os registros
        let usuarioData = primeiroRegistro;
        if (!usuarioData?.matricula || usuarioData.matricula === '0000' || usuarioData.matricula === 'Não informada') {
            usuarioData = relatorioData.find(reg => 
                reg.usuario_nome && 
                reg.matricula && 
                reg.matricula !== '0000' &&
                reg.matricula !== 'Não informada' &&
                reg.departamento_nome
            ) || primeiroRegistro;
        }
        
        usuario = {
            id: usuarioId,
            nome: usuarioData?.usuario_nome || 'Funcionário',
            cpf: usuarioData?.cpf || 'Não informado',
            matricula: usuarioData?.matricula || 'Não informada',
            cargo: usuarioData?.cargo || 'Não informado',
            departamento: usuarioData?.departamento_nome || 'Não informado',
            entrada_manha: usuarioData?.entrada_manha_jornada || usuarioData?.jornada_entrada_manha || usuarioData?.entrada_manha || '08:00:00',
            saida_almoco: usuarioData?.saida_almoco_jornada || usuarioData?.jornada_saida_almoco || usuarioData?.saida_almoco || '12:00:00',
            volta_almoco: usuarioData?.volta_almoco_jornada || usuarioData?.jornada_volta_almoco || usuarioData?.volta_almoco || '13:00:00',
            saida_tarde: usuarioData?.saida_tarde_jornada || usuarioData?.jornada_saida_tarde || usuarioData?.saida_tarde || '18:00:00',
            tolerancia_minutos: usuarioData?.tolerancia_minutos || 10
        };
    }
    
    // Usar as datas selecionadas pelo usuário (não as datas dos registros)
    const periodo = {
        inicio: dataInicio,
        fim: dataFim
    };
    
    return {
        usuario: usuario,
        periodo: periodo,
        registros: registros
    };
}

// Função para gerar HTML do cartão de ponto com cálculos em JavaScript
function gerarCartaoPontoHTML(dados, empresa, isMonthlyComplete) {
    const { usuario, periodo, registros } = dados;
    
    // Usar horários cadastrados do grupo de jornada (vindos da API do usuário)
    const horariosCadastrados = {
        entrada_manha: usuario.entrada_manha || '08:00:00',
        saida_almoco: usuario.saida_almoco || '12:00:00',
        volta_almoco: usuario.volta_almoco || '13:00:00',
        saida_tarde: usuario.saida_tarde || '18:00:00'
    };
    
    // Usar função centralizada para calcular estatísticas com tolerância
    const toleranciaMinutos = usuario.tolerancia_minutos || 10;
    const estatisticas = timeCalculator.calcularEstatisticasPeriodo(registros, horariosCadastrados, toleranciaMinutos);
    
    // Processar cada registro e calcular com nova lógica (incluindo justificativas)
    const registrosProcessados = registros.map(registro => {
        // ✅ CORRIGIDO: Verificar se horário existe antes de chamar includes()
        const preparaHora = (hora) => {
            if (!hora || hora === '--' || hora === 'null') return '--';
            return hora.includes(':') ? hora : hora + ':00';
        };
        
        // Preparar batidas reais (com arredondamento generoso)
        const batidasReais = [
            { tipo: 'entrada_manha', hora: preparaHora(registro.entrada_manha) },
            { tipo: 'saida_almoco', hora: preparaHora(registro.saida_almoco) },
            { tipo: 'volta_almoco', hora: preparaHora(registro.volta_almoco) },
            { tipo: 'saida_tarde', hora: preparaHora(registro.saida_tarde) }
        ].filter(p => p.hora !== '--' && p.hora !== 'null')
         .map(p => ({
             tipo: p.tipo,
             hora: timeCalculator.arredondarHorarioGeneroso(p.hora)
         }));
        
        // Calcular horas trabalhadas
        const horasTrabalhadas = timeCalculator.calcularHorasTrabalhadas(batidasReais);
        
        // Usar nova lógica de saldo com horários cadastrados e tolerância
        const toleranciaMinutos = usuario.tolerancia_minutos || 10;
        const saldoJornada = timeCalculator.calcularSaldoJornada(batidasReais, horariosCadastrados, registro.data, toleranciaMinutos);
        
        // Aplicar tolerância às horas trabalhadas se necessário
        let horasTrabalhadasFinal = horasTrabalhadas;
        if (saldoJornada.saldoBruto > 0 && saldoJornada.status === 'normal') {
            // Se funcionário trabalhou mais que o esperado e está dentro da tolerância,
            // mostrar apenas a carga diária (8:00)
            horasTrabalhadasFinal = '08:00';
        }
        
        // Verificar se é domingo (folga) - corrigir problema de fuso horário
        const [ano, mes, dia] = registro.data.split('-');
        const dataObj = new Date(parseInt(ano), parseInt(mes) - 1, parseInt(dia));
        const diaSemana = dataObj.getDay(); // 0 = domingo
        const isDomingo = diaSemana === 0;
        
        // Verificar se domingo é folga (usar configuração do grupo de jornada)
        const domingoFolga = usuario.domingo_folga !== false; // Padrão: true (folga)
        
        // Determinar indicador de justificativa (mesma lógica do relatório)
        let indicadorJustificativa = '';
        let tipoFinal = '';
        let saldoFinal = '';
        
        if (registro.justificativa) {
            const codigo = registro.justificativa.codigo;
            const periodo = registro.justificativa.periodo_parcial;
            
            switch (codigo) {
                case 'FER':
                    indicadorJustificativa = 'FÉRIAS';
                    tipoFinal = 'ferias';
                    break;
                case 'ATM':
                    indicadorJustificativa = 'ATESTADO';
                    tipoFinal = 'atestado';
                    break;
                case 'AJP':
                    indicadorJustificativa = `AUSÊNCIA ${periodo.toUpperCase()}`;
                    tipoFinal = 'ausencia';
                    break;
                case 'LIC':
                    indicadorJustificativa = 'LICENÇA';
                    tipoFinal = 'licenca';
                    break;
                case 'FOL':
                    indicadorJustificativa = 'FOLGA';
                    tipoFinal = 'folga';
                    break;
                default:
                    indicadorJustificativa = 'JUSTIFICADO';
                    tipoFinal = 'justificado';
            }
            saldoFinal = indicadorJustificativa;
        } else if (isDomingo && domingoFolga) {
            indicadorJustificativa = 'FOLGA';
            tipoFinal = 'domingo_folga';
            saldoFinal = '00:00:00';
        } else {
            tipoFinal = saldoJornada.tipo;
            saldoFinal = saldoJornada.saldoFormatado;
        }
        
        // Totais já calculados pela função centralizada
        
        // Se há justificativa ou é domingo configurado como folga, não mostrar horários
        const mostrarHorarios = !registro.justificativa && !(isDomingo && domingoFolga);
        
        return {
            ...registro,
            entrada_manha: mostrarHorarios ? timeCalculator.arredondarHorarioGeneroso(registro.entrada_manha) : '--',
            saida_almoco: mostrarHorarios ? timeCalculator.arredondarHorarioGeneroso(registro.saida_almoco) : '--',
            volta_almoco: mostrarHorarios ? timeCalculator.arredondarHorarioGeneroso(registro.volta_almoco) : '--',
            saida_tarde: mostrarHorarios ? timeCalculator.arredondarHorarioGeneroso(registro.saida_tarde) : '--',
            horas_trabalhadas: mostrarHorarios ? horasTrabalhadasFinal.substring(0, 5) : '--',
            saldo: saldoFinal,
            tipo: tipoFinal,
            isDomingo: isDomingo,
            tem_justificativa: !!registro.justificativa,
            indicador_justificativa: indicadorJustificativa,
            detalhes_saldo: saldoJornada.detalhes
        };
    });
    
    // Usar estatísticas centralizadas
    const totais = {
        horas_trabalhadas: estatisticas.totalHorasTrabalhadas.substring(0, 5),
        horas_extras: estatisticas.totalExtras.substring(0, 5),
        faltas: estatisticas.totalFaltas.substring(0, 5),
        dias_completos: estatisticas.diasCompletos,
        total_dias: estatisticas.totalDias
    };
    
    // Determinar período - corrigir problema de fuso horário
    const [anoInicio, mesInicio, diaInicio] = periodo.inicio.split('-');
    const [anoFim, mesFim, diaFim] = periodo.fim.split('-');
    const inicioObj = new Date(parseInt(anoInicio), parseInt(mesInicio) - 1, parseInt(diaInicio));
    const fimObj = new Date(parseInt(anoFim), parseInt(mesFim) - 1, parseInt(diaFim));
    
    let periodoTitulo, periodoSubtitulo;
    if (isMonthlyComplete) {
        periodoTitulo = inicioObj.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
        periodoSubtitulo = 'Período Mensal Completo';
    } else {
        periodoTitulo = `${inicioObj.toLocaleDateString('pt-BR')} a ${fimObj.toLocaleDateString('pt-BR')}`;
        periodoSubtitulo = 'Período Personalizado';
    }
    
    // Gerar HTML completo
    return `
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Cartão de Ponto - ${usuario.nome} - ${periodoTitulo}</title>
        <style>
            body { 
                font-family: 'Arial', sans-serif; 
                font-size: 11px; 
                margin: 15px;
                line-height: 1.3;
            }
            .header { 
                text-align: center; 
                margin-bottom: 20px; 
                border-bottom: 2px solid #333; 
                padding-bottom: 10px;
            }
            .empresa { 
                font-size: 14px; 
                font-weight: bold; 
                margin-bottom: 5px;
            }
            .logo {
                max-height: 60px;
                max-width: 120px;
                margin-bottom: 10px;
                display: block;
                margin-left: auto;
                margin-right: auto;
            }
            .funcionario { 
                margin: 15px 0; 
                padding: 10px; 
                background: #f5f5f5; 
                border-radius: 5px;
            }
            .periodo { 
                text-align: center; 
                font-size: 12px; 
                font-weight: bold; 
                margin: 10px 0;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 10px 0;
            }
            th, td { 
                border: 1px solid #333; 
                padding: 4px; 
                text-align: center;
            }
            th { 
                background: #e0e0e0; 
                font-weight: bold;
            }
            .totais { 
                margin-top: 15px; 
                padding: 10px; 
                background: #f0f0f0; 
                border-radius: 5px;
            }
            .extra { color: #006400; font-weight: bold; }
            .falta { color: #dc143c; font-weight: bold; }
            .normal { color: #000; }
            .domingo_folga { 
                color: #8b5cf6; 
                font-weight: bold; 
                background-color: #f3f4f6;
            }
        </style>
    </head>
    <body>
        <div class="header">
            ${empresa.logo ? `<img src="${empresa.logo}" alt="Logo da empresa" class="logo">` : ''}
            <div class="empresa">${empresa.nome}</div>
            <div>CNPJ: ${empresa.cnpj}</div>
            <div>${empresa.endereco} - ${empresa.cidade}</div>
        </div>
        
        <div class="funcionario">
            <strong>FUNCIONÁRIO:</strong> ${usuario.nome}<br>
            <strong>CPF:</strong> ${usuario.cpf && usuario.cpf !== 'Não informado' ? formatarCPF(usuario.cpf) : 'Não informado'}<br>
            <strong>MATRÍCULA:</strong> ${usuario.matricula || 'Não informada'}<br>
            <strong>CARGO:</strong> ${usuario.cargo || 'Não informado'}<br>
            <strong>DEPARTAMENTO:</strong> ${usuario.departamento || 'Não informado'}
        </div>
        
        <div class="periodo">
            <strong>${periodoTitulo.toUpperCase()}</strong><br>
            ${periodoSubtitulo}
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Dia</th>
                    <th>Entrada</th>
                    <th>Saída Almoço</th>
                    <th>Volta Almoço</th>
                    <th>Saída</th>
                    <th>Horas Trabalhadas</th>
                    <th>Saldo</th>
                    <th>Observações</th>
                    <th>Detalhes do Ajuste</th>
                </tr>
            </thead>
            <tbody>
                ${registrosProcessados.map(registro => {
                    // Corrigir problema de fuso horário - usar data local
                    const [ano, mes, dia] = registro.data.split('-');
                    const dataObj = new Date(parseInt(ano), parseInt(mes) - 1, parseInt(dia));
                    const diaSemana = dataObj.toLocaleDateString('pt-BR', { weekday: 'short' });
                    
                    return `
                    <tr>
                        <td>${dataObj.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })}</td>
                        <td>${diaSemana}</td>
                        <td>${registro.entrada_manha}</td>
                        <td>${registro.saida_almoco}</td>
                        <td>${registro.volta_almoco}</td>
                        <td>${registro.saida_tarde}</td>
                        <td>${registro.horas_trabalhadas}</td>
                        <td class="${registro.isDomingo ? '' : (registro.saldo.startsWith('+') ? 'extra' : registro.saldo.startsWith('-') ? 'falta' : '')}">${registro.isDomingo ? 'FOLGA' : registro.saldo}</td>
                        <td>${registro.observacoes || (registro.isDomingo ? 'Domingo - Folga' : '')}</td>
                        <td style="font-size: 9px; color: #666;">
                            ${registro.detalhes_ajuste && registro.detalhes_ajuste.length > 0 ? 
                                registro.detalhes_ajuste.map(ajuste => `
                                    <div style="margin-bottom: 2px; border-left: 2px solid #f59e0b; padding-left: 4px;">
                                        <strong>${ajuste.tipo.replace('_', ' ').toUpperCase()}:</strong><br>
                                        ${ajuste.editado_por_nome} (${ajuste.motivo_ajuste.replace('_', ' ')})<br>
                                        ${ajuste.tempo_ajustado_minutos > 0 ? `<span style="color: #dc2626;">+${ajuste.tempo_ajustado_minutos}min</span>` : ''}
                                    </div>
                                `).join('') : '--'
                            }
                        </td>
                    </tr>
                    `;
                }).join('')}
            </tbody>
        </table>
        
        <div class="totais">
            <strong>RESUMO DO PERÍODO:</strong><br>
            Total de Horas Trabalhadas: ${totais.horas_trabalhadas}<br>
            Total de Horas Extras: <span class="extra">${totais.horas_extras}</span><br>
            Total de Faltas: <span class="falta">${totais.faltas}</span><br>
            Dias Completos: ${totais.dias_completos} de ${totais.total_dias}<br>
            <br>
            <strong>Data de Geração:</strong> ${new Date().toLocaleString('pt-BR')}
        </div>
        
        <div class="assinaturas" style="margin-top: 40px; display: flex; justify-content: space-between; border-top: 1px solid #ccc; padding-top: 20px;">
            <div class="assinatura-funcionario" style="text-align: center; width: 45%;">
                <div style="border-bottom: 1px solid #000; height: 40px; margin-bottom: 5px;"></div>
                <strong>Assinatura do Funcionário</strong><br>
                <small>${usuario.nome}</small>
            </div>
            
            <div class="assinatura-rh" style="text-align: center; width: 45%;">
                <div style="border-bottom: 1px solid #000; height: 40px; margin-bottom: 5px;"></div>
                <strong>Assinatura do Responsável RH</strong><br>
                <small>Data: ___/___/_______</small>
            </div>
        </div>
    </body>
    </html>
    `;
}

function gerarHtmlRelatorio(dataInicio, dataFim, nomeUsuario) {
    const tbody = document.getElementById('relatorioTableBody').innerHTML;
    
    return `
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Relatório de Ponto - Tech-Ponto</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 20px;
                    font-size: 12px;
                }
                .header { 
                    text-align: center; 
                    margin-bottom: 30px;
                    border-bottom: 2px solid #2563eb;
                    padding-bottom: 20px;
                }
                .header h1 { 
                    color: #2563eb; 
                    margin: 0;
                }
                .info { 
                    margin-bottom: 20px;
                    background: #f3f4f6;
                    padding: 15px;
                    border-radius: 5px;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-bottom: 20px;
                }
                th, td { 
                    border: 1px solid #ddd; 
                    padding: 8px; 
                    text-align: left;
                }
                th { 
                    background-color: #2563eb; 
                    color: white;
                    font-weight: bold;
                }
                .status-badge {
                    padding: 2px 8px;
                    border-radius: 10px;
                    font-size: 10px;
                    font-weight: bold;
                }
                .status-badge.completo { background: #d1fae5; color: #065f46; }
                .status-badge.incompleto { background: #fef3c7; color: #92400e; }
                .extras { color: #1e40af; }
                .faltas { color: #991b1b; }
                .footer {
                    margin-top: 30px;
                    text-align: center;
                    font-size: 10px;
                    color: #6b7280;
                    border-top: 1px solid #e5e7eb;
                    padding-top: 15px;
                }
                @media print {
                    body { margin: 0; font-size: 11px; }
                    .header { page-break-inside: avoid; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🕒 Tech-Ponto</h1>
                <h2>Relatório de Controle de Ponto</h2>
            </div>
            
            <div class="info">
                <strong>Período:</strong> ${timeCalculator.formatarData(dataInicio)} até ${timeCalculator.formatarData(dataFim)}<br>
                <strong>Usuário:</strong> ${nomeUsuario}<br>
                <strong>Data de Geração:</strong> ${new Date().toLocaleString('pt-BR')}<br>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Data</th>
                        <th>Entrada</th>
                        <th>Saída Almoço</th>
                        <th>Volta Almoço</th>
                        <th>Saída</th>
                        <th>Horas Trabalhadas</th>
                        <th>Extras/Faltas</th>
                    </tr>
                </thead>
                <tbody>
                    ${tbody}
                </tbody>
            </table>
            
            <div class="footer">
                <p>Relatório gerado automaticamente pelo sistema Tech-Ponto</p>
                <p>© 2025 Tech-Ponto - Sistema de Controle de Ponto Eletrônico</p>
            </div>
        </body>
        </html>
    `;
}

// Expor funções globalmente
// ===== FUNÇÕES PARA DEPARTAMENTOS =====

async function carregarDepartamentos() {
    try {
        if (!verificarFuncoesAuth()) {
            return;
        }
        
        const response = await fazerRequisicao('departamentos.php', { method: 'GET' });
        
        if (response.success) {
            const tbody = document.getElementById('departamentosTableBody');
            tbody.innerHTML = '';
            
            if (response.data.departamentos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px;">Nenhum departamento encontrado</td></tr>';
                return;
            }
            
            response.data.departamentos.forEach(dept => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${dept.id}</td>
                    <td>${dept.nome}</td>
                    <td>${dept.codigo}</td>
                    <td>${dept.descricao || '-'}</td>
                    <td>
                        <span class="status-badge ${dept.ativo ? 'active' : 'inactive'}">
                            ${dept.ativo ? 'Ativo' : 'Inativo'}
                        </span>
                    </td>
                    <td>
                        <button class="btn-edit" onclick="editarDepartamento(${dept.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-delete" onclick="alternarStatusDepartamento(${dept.id}, ${dept.ativo})" title="${dept.ativo ? 'Desativar' : 'Ativar'}">
                            <i class="fas fa-${dept.ativo ? 'ban' : 'check'}"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        } else {
            mostrarNotificacao(response.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao carregar departamentos:', error);
        mostrarNotificacao('Erro ao carregar departamentos', 'error');
    }
}

function abrirModalDepartamento(id = null) {
    const modal = document.getElementById('departamentoModal');
    const form = document.getElementById('departamentoForm');
    const title = document.getElementById('departamentoModalTitle');
    
    form.reset();
    
    if (id) {
        title.textContent = 'Editar Departamento';
        // Carregar dados do departamento
        carregarDepartamento(id);
    } else {
        title.textContent = 'Novo Departamento';
        document.getElementById('departamentoId').value = '';
    }
    
    modal.style.display = 'block';
}

function fecharModalDepartamento() {
    document.getElementById('departamentoModal').style.display = 'none';
}

async function carregarDepartamento(id) {
    try {
        const response = await fazerRequisicao(`departamentos.php?id=${id}`, { method: 'GET' });
        
        if (response.success) {
            const dept = response.data.departamento;
            document.getElementById('departamentoId').value = dept.id;
            document.getElementById('departamentoNome').value = dept.nome;
            document.getElementById('departamentoCodigo').value = dept.codigo;
            document.getElementById('departamentoDescricao').value = dept.descricao || '';
            document.getElementById('departamentoAtivo').checked = dept.ativo;
        }
    } catch (error) {
        console.error('Erro ao carregar departamento:', error);
    }
}

async function salvarDepartamento(event) {
    event.preventDefault();
    
    try {
        const formData = {
            nome: document.getElementById('departamentoNome').value,
            codigo: document.getElementById('departamentoCodigo').value,
            descricao: document.getElementById('departamentoDescricao').value,
            ativo: document.getElementById('departamentoAtivo').checked
        };
        
        const id = document.getElementById('departamentoId').value;
        const method = id ? 'PUT' : 'POST';
        const url = id ? `departamentos.php?id=${id}` : 'departamentos.php';
        
        if (id) {
            formData.id = id;
        }
        
        const response = await fazerRequisicao(url, {
            method: method,
            body: JSON.stringify(formData)
        });
        
        if (response.success) {
            mostrarNotificacao(response.message, 'success');
            fecharModalDepartamento();
            carregarDepartamentos();
        } else {
            mostrarNotificacao(response.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao salvar departamento:', error);
        mostrarNotificacao('Erro ao salvar departamento', 'error');
    }
}

async function editarDepartamento(id) {
    abrirModalDepartamento(id);
}

async function alternarStatusDepartamento(id, ativo) {
    try {
        const response = await fazerRequisicao(`departamentos.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify({
                id: id,
                ativo: !ativo
            })
        });
        
        if (response.success) {
            mostrarNotificacao(response.message, 'success');
            carregarDepartamentos();
        } else {
            mostrarNotificacao(response.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao alterar status do departamento:', error);
        mostrarNotificacao('Erro ao alterar status do departamento', 'error');
    }
}

// ===== FUNÇÕES PARA JORNADAS =====

async function carregarJornadas() {
    try {
        if (!verificarFuncoesAuth()) {
            return;
        }
        
        const response = await fazerRequisicao('grupos-jornada.php', { method: 'GET' });
        
        if (response.success) {
            const tbody = document.getElementById('jornadasTableBody');
            tbody.innerHTML = '';
            
            if (response.data.grupos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 40px;">Nenhuma jornada encontrada</td></tr>';
                return;
            }
            
            response.data.grupos.forEach(jornada => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${jornada.id}</td>
                    <td>${jornada.nome}</td>
                    <td>${jornada.codigo}</td>
                    <td>${jornada.entrada_manha}</td>
                    <td>${jornada.saida_almoco}</td>
                    <td>${jornada.volta_almoco}</td>
                    <td>${jornada.saida_tarde}</td>
                    <td>${jornada.carga_diaria_minutos}min</td>
                    <td>
                        <span class="status-badge ${jornada.ativo ? 'active' : 'inactive'}">
                            ${jornada.ativo ? 'Ativo' : 'Inativo'}
                        </span>
                    </td>
                    <td>
                        <button class="btn-edit" onclick="editarJornada(${jornada.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-delete" onclick="alternarStatusJornada(${jornada.id}, ${jornada.ativo})" title="${jornada.ativo ? 'Desativar' : 'Ativar'}">
                            <i class="fas fa-${jornada.ativo ? 'ban' : 'check'}"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        } else {
            mostrarNotificacao(response.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao carregar jornadas:', error);
        mostrarNotificacao('Erro ao carregar jornadas', 'error');
    }
}

function abrirModalJornada(id = null) {
    const modal = document.getElementById('jornadaModal');
    const form = document.getElementById('jornadaForm');
    const title = document.getElementById('jornadaModalTitle');
    
    form.reset();
    
    if (id) {
        title.textContent = 'Editar Jornada';
        carregarJornada(id);
    } else {
        title.textContent = 'Nova Jornada';
        document.getElementById('jornadaId').value = '';
        // Valores padrão
        document.getElementById('jornadaEntrada').value = '08:00';
        document.getElementById('jornadaSaidaAlmoco').value = '12:00';
        document.getElementById('jornadaVoltaAlmoco').value = '13:00';
        document.getElementById('jornadaSaidaTarde').value = '18:00';
        document.getElementById('jornadaCarga').value = '480';
        document.getElementById('jornadaTolerancia').value = '10';
        document.getElementById('jornadaIntervaloAlmoco').value = '60';
        
        // Valores padrão para sábado e domingo
        document.getElementById('jornadaSabadoAtivo').checked = false;
        document.getElementById('jornadaEntradaSabado').value = '08:00';
        document.getElementById('jornadaSaidaSabado').value = '12:00';
        document.getElementById('jornadaCargaSabado').value = '240';
        document.getElementById('jornadaDomingoFolga').checked = true;
    }
    
    // Configurar event listeners para preview em tempo real
    configurarPreviewJornada();
    
    // Configurar toggle do sábado
    configurarToggleSabado();
    
    modal.style.display = 'block';
}

function fecharModalJornada() {
    document.getElementById('jornadaModal').style.display = 'none';
}

async function carregarJornada(id) {
    try {
        const response = await fazerRequisicao(`grupos-jornada.php?id=${id}`, { method: 'GET' });
        
        if (response.success) {
            const jornada = response.data.grupo;
            document.getElementById('jornadaId').value = jornada.id;
            document.getElementById('jornadaNome').value = jornada.nome;
            document.getElementById('jornadaCodigo').value = jornada.codigo;
            document.getElementById('jornadaEntrada').value = jornada.entrada_manha;
            document.getElementById('jornadaSaidaAlmoco').value = jornada.saida_almoco;
            document.getElementById('jornadaVoltaAlmoco').value = jornada.volta_almoco;
            document.getElementById('jornadaSaidaTarde').value = jornada.saida_tarde;
            document.getElementById('jornadaCarga').value = jornada.carga_diaria_minutos;
            document.getElementById('jornadaTolerancia').value = jornada.tolerancia_minutos;
            document.getElementById('jornadaIntervaloAlmoco').value = jornada.intervalo_almoco_minutos || 60;
            document.getElementById('jornadaVigencia').value = jornada.data_vigencia || '';
            document.getElementById('jornadaAtivo').checked = jornada.ativo;
            
            // Carregar configurações de sábado e domingo
            document.getElementById('jornadaSabadoAtivo').checked = jornada.sabado_ativo || false;
            document.getElementById('jornadaEntradaSabado').value = jornada.entrada_sabado || '08:00';
            document.getElementById('jornadaSaidaSabado').value = jornada.saida_sabado || '12:00';
            document.getElementById('jornadaCargaSabado').value = jornada.carga_sabado_minutos || 240;
            document.getElementById('jornadaDomingoFolga').checked = jornada.domingo_folga !== false;
            
            // Configurar toggle do sábado após carregar dados
            configurarToggleSabado();
        }
    } catch (error) {
        console.error('Erro ao carregar jornada:', error);
    }
}

async function salvarJornada(event) {
    event.preventDefault();
    
    try {
        // Verificar se as funções estão disponíveis
        if (!verificarFuncoesAuth()) {
            console.error('Funções de auth não disponíveis');
            return;
        }
        
        const formData = {
            nome: document.getElementById('jornadaNome').value,
            codigo: document.getElementById('jornadaCodigo').value,
            entrada_manha: document.getElementById('jornadaEntrada').value,
            saida_almoco: document.getElementById('jornadaSaidaAlmoco').value,
            volta_almoco: document.getElementById('jornadaVoltaAlmoco').value,
            saida_tarde: document.getElementById('jornadaSaidaTarde').value,
            carga_diaria_minutos: parseInt(document.getElementById('jornadaCarga').value),
            tolerancia_minutos: parseInt(document.getElementById('jornadaTolerancia').value),
            intervalo_almoco_minutos: parseInt(document.getElementById('jornadaIntervaloAlmoco').value),
            data_vigencia: document.getElementById('jornadaVigencia').value || null,
            ativo: document.getElementById('jornadaAtivo').checked,
            sabado_ativo: document.getElementById('jornadaSabadoAtivo').checked,
            entrada_sabado: document.getElementById('jornadaEntradaSabado').value,
            saida_sabado: document.getElementById('jornadaSaidaSabado').value,
            carga_sabado_minutos: parseInt(document.getElementById('jornadaCargaSabado').value),
            domingo_folga: document.getElementById('jornadaDomingoFolga').checked
        };
        
        const id = document.getElementById('jornadaId').value;
        const method = id ? 'PUT' : 'POST';
        const url = id ? `grupos-jornada.php?id=${id}` : 'grupos-jornada.php';
        
        if (id) {
            formData.id = id;
        }
        
        const response = await fazerRequisicao(url, {
            method: method,
            body: JSON.stringify(formData)
        });
        
        if (response.success) {
            mostrarNotificacao(response.message, 'success');
            fecharModalJornada();
            carregarJornadas();
        } else {
            mostrarNotificacao(response.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao salvar jornada:', error);
        mostrarNotificacao('Erro ao salvar jornada: ' + error.message, 'error');
    }
}

async function editarJornada(id) {
    abrirModalJornada(id);
}

async function alternarStatusJornada(id, ativo) {
    try {
        const response = await fazerRequisicao(`grupos-jornada.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify({
                id: id,
                ativo: !ativo
            })
        });
        
        if (response.success) {
            mostrarNotificacao(response.message, 'success');
            carregarJornadas();
        } else {
            mostrarNotificacao(response.message, 'error');
        }
    } catch (error) {
        console.error('Erro ao alterar status da jornada:', error);
        mostrarNotificacao('Erro ao alterar status da jornada', 'error');
    }
}

// ===== FUNÇÕES DE PREVIEW =====

function configurarPreviewJornada() {
    // Remover event listeners existentes
    const campos = ['jornadaEntrada', 'jornadaSaidaAlmoco', 'jornadaVoltaAlmoco', 'jornadaSaidaTarde', 'jornadaCarga', 'jornadaIntervaloAlmoco', 'jornadaEntradaSabado', 'jornadaSaidaSabado', 'jornadaSabadoAtivo', 'jornadaDomingoFolga'];
    
    campos.forEach(campoId => {
        const campo = document.getElementById(campoId);
        if (campo) {
            // Clonar o elemento para remover todos os event listeners
            const novoCampo = campo.cloneNode(true);
            campo.parentNode.replaceChild(novoCampo, campo);
            
            // Adicionar novo event listener
            novoCampo.addEventListener('input', atualizarPreviewJornada);
            novoCampo.addEventListener('change', atualizarPreviewJornada);
        }
    });
    
    // Atualizar preview inicial
    atualizarPreviewJornada();
}

function configurarToggleSabado() {
    const sabadoAtivo = document.getElementById('jornadaSabadoAtivo');
    const sabadoConfig = document.getElementById('sabadoConfig');
    
    if (sabadoAtivo && sabadoConfig) {
        // Função para toggle
        const toggleSabado = () => {
            if (sabadoAtivo.checked) {
                sabadoConfig.style.display = 'block';
                // Calcular carga do sábado automaticamente
                calcularCargaSabado();
            } else {
                sabadoConfig.style.display = 'none';
                // Resetar valores
                document.getElementById('jornadaEntradaSabado').value = '08:00';
                document.getElementById('jornadaSaidaSabado').value = '12:00';
                document.getElementById('jornadaCargaSabado').value = '240';
            }
            atualizarPreviewJornada();
        };
        
        // Configurar event listener
        sabadoAtivo.addEventListener('change', toggleSabado);
        
        // Configurar event listeners para horários do sábado
        const entradaSabado = document.getElementById('jornadaEntradaSabado');
        const saidaSabado = document.getElementById('jornadaSaidaSabado');
        
        if (entradaSabado && saidaSabado) {
            entradaSabado.addEventListener('change', calcularCargaSabado);
            saidaSabado.addEventListener('change', calcularCargaSabado);
        }
        
        // Aplicar estado inicial
        toggleSabado();
    }
}

function calcularCargaSabado() {
    const entrada = document.getElementById('jornadaEntradaSabado').value;
    const saida = document.getElementById('jornadaSaidaSabado').value;
    const cargaSabado = document.getElementById('jornadaCargaSabado');
    
    if (entrada && saida && cargaSabado) {
        const entradaMinutos = tempoParaMinutos(entrada);
        const saidaMinutos = tempoParaMinutos(saida);
        const diferenca = saidaMinutos - entradaMinutos;
        
        if (diferenca > 0) {
            cargaSabado.value = diferenca;
            atualizarPreviewJornada();
        }
    }
}

function atualizarPreviewJornada() {
    try {
        const entrada = document.getElementById('jornadaEntrada').value;
        const saidaAlmoco = document.getElementById('jornadaSaidaAlmoco').value;
        const voltaAlmoco = document.getElementById('jornadaVoltaAlmoco').value;
        const saidaTarde = document.getElementById('jornadaSaidaTarde').value;
        const cargaMinutos = parseInt(document.getElementById('jornadaCarga').value) || 0;
        const intervaloMinutos = parseInt(document.getElementById('jornadaIntervaloAlmoco').value) || 60;
        
        // Configurações de sábado e domingo
        const sabadoAtivo = document.getElementById('jornadaSabadoAtivo').checked;
        const entradaSabado = document.getElementById('jornadaEntradaSabado').value;
        const saidaSabado = document.getElementById('jornadaSaidaSabado').value;
        const cargaSabadoMinutos = parseInt(document.getElementById('jornadaCargaSabado').value) || 0;
        const domingoFolga = document.getElementById('jornadaDomingoFolga').checked;
        
        if (!entrada || !saidaAlmoco || !voltaAlmoco || !saidaTarde) {
            return;
        }
        
        // Calcular tempo de almoço
        const tempoAlmoco = calcularDiferencaTempo(saidaAlmoco, voltaAlmoco);
        
        // Calcular tempo total (entrada até saída)
        const tempoTotal = calcularDiferencaTempo(entrada, saidaTarde);
        
        // Atualizar preview básico
        document.getElementById('previewCarga').textContent = formatarTempo(cargaMinutos);
        document.getElementById('previewAlmoco').textContent = tempoAlmoco;
        document.getElementById('previewIntervalo').textContent = formatarTempo(intervaloMinutos);
        document.getElementById('previewTotal').textContent = tempoTotal;
        
        // Atualizar preview do sábado
        const previewSabadoItem = document.getElementById('previewSabadoItem');
        const previewCargaSabado = document.getElementById('previewCargaSabado');
        
        if (sabadoAtivo) {
            previewSabadoItem.style.display = 'block';
            previewCargaSabado.textContent = formatarTempo(cargaSabadoMinutos);
        } else {
            previewSabadoItem.style.display = 'none';
        }
        
        // Atualizar preview do domingo
        const previewDomingo = document.getElementById('previewDomingo');
        previewDomingo.textContent = domingoFolga ? 'Folga' : 'Trabalha';
        
        // Calcular e atualizar carga semanal total
        const cargaSemanalTotal = (cargaMinutos * 5) + (sabadoAtivo ? cargaSabadoMinutos : 0);
        const previewCargaSemanal = document.getElementById('previewCargaSemanal');
        previewCargaSemanal.textContent = formatarTempo(cargaSemanalTotal);
        
        // Validar se totaliza 44 horas (2640 minutos)
        if (cargaSemanalTotal === 2640) {
            previewCargaSemanal.style.color = '#27ae60'; // Verde
        } else {
            previewCargaSemanal.style.color = '#e74c3c'; // Vermelho
        }
        
        // Atualizar campo de carga automaticamente apenas se estiver vazio ou for 0
        if (cargaMinutos === 0) {
            const cargaCalculada = calcularCargaMinutos(entrada, saidaAlmoco, voltaAlmoco, saidaTarde);
            document.getElementById('jornadaCarga').value = cargaCalculada;
            document.getElementById('previewCarga').textContent = formatarTempo(cargaCalculada);
        } else {
            // Se já tem valor, apenas atualizar o preview
            document.getElementById('previewCarga').textContent = formatarTempo(cargaMinutos);
        }
        
    } catch (error) {
        console.error('Erro ao atualizar preview:', error);
    }
}

function calcularDiferencaTempo(inicio, fim) {
    const inicioMinutos = tempoParaMinutos(inicio);
    const fimMinutos = tempoParaMinutos(fim);
    const diferenca = fimMinutos - inicioMinutos;
    
    return formatarTempo(diferenca);
}

function calcularCargaMinutos(entrada, saidaAlmoco, voltaAlmoco, saidaTarde) {
    const entradaMin = tempoParaMinutos(entrada);
    const saidaAlmocoMin = tempoParaMinutos(saidaAlmoco);
    const voltaAlmocoMin = tempoParaMinutos(voltaAlmoco);
    const saidaTardeMin = tempoParaMinutos(saidaTarde);
    
    // Validar sequência lógica dos horários
    if (entradaMin >= saidaAlmocoMin || saidaAlmocoMin >= voltaAlmocoMin || voltaAlmocoMin >= saidaTardeMin) {
        console.warn('Horários inválidos para cálculo de carga:', {entrada, saidaAlmoco, voltaAlmoco, saidaTarde});
        return 0; // Retornar 0 se horários inválidos
    }
    
    const manha = saidaAlmocoMin - entradaMin;
    const tarde = saidaTardeMin - voltaAlmocoMin;
    
    return manha + tarde;
}

function tempoParaMinutos(tempo) {
    const [horas, minutos] = tempo.split(':').map(Number);
    return horas * 60 + minutos;
}

function formatarTempo(minutos) {
    const horas = Math.floor(minutos / 60);
    const mins = minutos % 60;
    return `${horas.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
}

// ===== FUNÇÕES PARA FORMULÁRIO DE USUÁRIO =====

async function carregarSelectsUsuario() {
    try {
        // Preservar valores selecionados
        const selectDepartamento = document.getElementById('userDepartamento');
        const selectJornada = document.getElementById('userGrupoJornada');
        const departamentoSelecionado = selectDepartamento.value;
        const jornadaSelecionada = selectJornada.value;
        
        // Carregar departamentos
        const responseDepartamentos = await fazerRequisicao('departamentos.php', { method: 'GET' });
        if (responseDepartamentos.success) {
            selectDepartamento.innerHTML = '<option value="">Selecione um departamento</option>';
            
            responseDepartamentos.data.departamentos.forEach(dept => {
                if (dept.ativo) {
                    const option = document.createElement('option');
                    option.value = dept.id;
                    option.textContent = `${dept.nome} (${dept.codigo})`;
                    selectDepartamento.appendChild(option);
                }
            });
            
            // Restaurar valor selecionado se existir
            if (departamentoSelecionado) {
                selectDepartamento.value = departamentoSelecionado;
            }
        }
        
        // Carregar grupos de jornada
        const responseJornadas = await fazerRequisicao('grupos-jornada.php', { method: 'GET' });
        if (responseJornadas.success) {
            selectJornada.innerHTML = '<option value="">Selecione um grupo de jornada</option>';
            
            responseJornadas.data.grupos.forEach(jornada => {
                if (jornada.ativo) {
                    const option = document.createElement('option');
                    option.value = jornada.id;
                    option.textContent = `${jornada.nome} (${jornada.codigo}) - ${formatarTempo(jornada.carga_diaria_minutos)}`;
                    option.dataset.carga = jornada.carga_diaria_minutos;
                    option.dataset.tolerancia = jornada.tolerancia_minutos;
                    selectJornada.appendChild(option);
                }
            });
            
            // Restaurar valor selecionado se existir
            if (jornadaSelecionada) {
                selectJornada.value = jornadaSelecionada;
            }
        }
    } catch (error) {
        console.error('Erro ao carregar selects:', error);
    }
}

function configurarPreenchimentoAutomatico() {
    const selectJornada = document.getElementById('userGrupoJornada');
    const inputCarga = document.getElementById('userCarga');
    const inputTolerancia = document.getElementById('userTolerancia');
    
    // Remover event listeners existentes
    const novoSelect = selectJornada.cloneNode(true);
    selectJornada.parentNode.replaceChild(novoSelect, selectJornada);
    
    // Adicionar novo event listener
    novoSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (selectedOption.value && selectedOption.dataset.carga && selectedOption.dataset.tolerancia) {
            // Preencher automaticamente os campos
            const cargaMinutos = parseInt(selectedOption.dataset.carga);
            const tolerancia = parseInt(selectedOption.dataset.tolerancia);
            
            // Converter minutos para formato HH:MM
            const horas = Math.floor(cargaMinutos / 60);
            const minutos = cargaMinutos % 60;
            const cargaFormatada = `${horas.toString().padStart(2, '0')}:${minutos.toString().padStart(2, '0')}`;
            
            inputCarga.value = cargaFormatada;
            inputTolerancia.value = tolerancia;
            
            // Mostrar notificação
            mostrarNotificacao('info', `Carga horária e tolerância preenchidas automaticamente do grupo "${selectedOption.textContent}"`);
        }
    });
}

// ===== EXPORTAR FUNÇÕES =====

window.switchTab = switchTab;
window.abrirModalUsuario = abrirModalUsuario;
window.fecharModal = fecharModal;
window.editarUsuario = editarUsuario;
window.resetarSenha = resetarSenha;
window.resetarPin = resetarPin;
window.alternarStatusUsuario = alternarStatusUsuario;
window.buscarRelatorio = buscarRelatorio;
window.gerarRelatorio = gerarRelatorio;

// Departamentos
window.abrirModalDepartamento = abrirModalDepartamento;
window.fecharModalDepartamento = fecharModalDepartamento;
window.editarDepartamento = editarDepartamento;
window.alternarStatusDepartamento = alternarStatusDepartamento;

// Jornadas
window.abrirModalJornada = abrirModalJornada;
window.fecharModalJornada = fecharModalJornada;
window.editarJornada = editarJornada;
window.alternarStatusJornada = alternarStatusJornada;

// Configurações
window.carregarConfiguracoes = carregarConfiguracoes;
window.salvarConfiguracoes = salvarConfiguracoes;
window.atualizarPreview = atualizarPreview;
window.inicializarConfiguracoes = inicializarConfiguracoes;