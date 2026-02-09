{!! view_render_event('admin.dashboard.index.client_risk_analysis.before') !!}

<!-- Client Risk Analysis Vue Component -->
<v-dashboard-client-risk-analysis>
    <!-- Shimmer -->
    <div class="grid gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="shimmer h-6 w-48"></div>
        <div class="shimmer h-64 w-full"></div>
    </div>
</v-dashboard-client-risk-analysis>

{!! view_render_event('admin.dashboard.index.client_risk_analysis.after') !!}

@pushOnce('scripts')
    <script type="text/x-template" id="v-dashboard-client-risk-analysis-template">
    <!-- Shimmer -->
    <template v-if="isLoading">
        <div class="grid gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="shimmer h-6 w-48"></div>
            <div class="shimmer h-64 w-full"></div>
        </div>
    </template>

    <!-- Main Content -->
    <template v-else>
        <div class="grid gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <!-- Header -->
            <div class="flex flex-col justify-between gap-1 sm:flex-row sm:items-center">
                <div>
                    <p class="text-base font-semibold text-gray-800 dark:text-white">
                        📊 Análise de Carteira de Clientes
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-300" v-if="report.last_updated">
                        Atualizado: @{{ formatDate(report.last_updated) }}
                    </p>
                </div>
                <button @click="refreshData"
                    class="flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-1.5 text-xs text-white hover:bg-blue-700 transition-colors"
                    :disabled="isRefreshing">
                    <span v-if="isRefreshing">⏳</span>
                    <span v-else>🔄</span>
                    @{{ isRefreshing ? 'Atualizando...' : 'Atualizar Dados' }}
                </button>
            </div>

            <!-- Check Stats Card (Daily checks tracking) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" v-if="checkHistory.today_count !== undefined">
                <div class="rounded-lg bg-gradient-to-br from-emerald-500 to-green-600 p-3 text-white">
                    <p class="text-xs opacity-80">Clientes Trabalhados Hoje</p>
                    <p class="text-2xl font-bold">@{{ checkHistory.today_count }}</p>
                </div>
                <div class="rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 p-3 text-white">
                    <p class="text-xs opacity-80">Total no Período</p>
                    <p class="text-2xl font-bold">@{{ checkHistory.total_period }}</p>
                </div>
                <div class="rounded-lg bg-gray-100 dark:bg-gray-800 p-3">
                    <p class="text-xs text-gray-600 dark:text-gray-300 mb-1">Checks por Dia (últimos dias)</p>
                    <div class="flex items-end gap-1 h-10">
                        <template v-for="(count, date) in checkHistory.daily_counts" :key="date">
                            <div 
                                class="bg-blue-500 rounded-t min-w-[6px] flex-1 transition-all"
                                :style="{ height: getBarHeight(count) + '%' }"
                                :title="date + ': ' + count + ' checks'"
                            ></div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6" v-if="report.summary">
                <div v-for="(count, key) in report.summary" :key="key" @click="toggleFilter(key)" :class="[
                    'cursor-pointer rounded-lg p-3 text-center transition-all border-2',
                    activeFilter === key ? 'ring-2 ring-offset-2 ring-blue-500' : '',
                    getCardClass(key)
                ]">
                    <p class="text-2xl font-bold">@{{ count }}</p>
                    <p class="text-xs truncate">@{{ getLabel(key) }}</p>
                </div>
            </div>

            <!-- Ticket Threshold Info -->
            <div class="rounded-lg bg-blue-50 dark:bg-blue-900/30 p-2 text-xs text-blue-700 dark:text-blue-200"
                v-if="report.ticket_threshold > 0">
                💡 Limiar de Oportunidade: <strong>R$ @{{ formatCurrency(report.ticket_threshold) }}</strong>
                (baseado no ticket médio dos clientes ativos)
            </div>

            <!-- Table Filters -->
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <!-- Check Status Filter -->
                <div class="flex rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600">
                    <button @click="checkFilter = 'all'" :class="[
                        'px-3 py-1.5 text-xs transition-colors',
                        checkFilter === 'all' 
                            ? 'bg-blue-600 text-white' 
                            : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600'
                    ]">
                        Todos
                    </button>
                    <button @click="checkFilter = 'unchecked'" :class="[
                        'px-3 py-1.5 text-xs transition-colors border-l border-gray-300 dark:border-gray-600',
                        checkFilter === 'unchecked' 
                            ? 'bg-orange-500 text-white' 
                            : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600'
                    ]">
                        📋 Pendentes
                    </button>
                    <button @click="checkFilter = 'checked'" :class="[
                        'px-3 py-1.5 text-xs transition-colors border-l border-gray-300 dark:border-gray-600',
                        checkFilter === 'checked' 
                            ? 'bg-green-600 text-white' 
                            : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600'
                    ]">
                        ✅ Trabalhados
                    </button>
                </div>

                <!-- Sort Selector -->
                <select v-model="sortBy" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-2 py-1 text-xs">
                    <option value="valor_total">💰 Total (maior)</option>
                    <option value="valor_total_asc">💰 Total (menor)</option>
                    <option value="dias_sem_compra">📅 Dias s/ Compra (maior)</option>
                    <option value="dias_sem_compra_asc">📅 Dias s/ Compra (menor)</option>
                    <option value="razao">🔤 Nome A-Z</option>
                </select>

                <span v-if="activeFilter" @click="activeFilter = null" 
                    class="cursor-pointer px-2 py-1 rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 text-xs hover:bg-red-200 dark:hover:bg-red-800">
                    ✕ Limpar filtro: @{{ getLabel(activeFilter) }}
                </span>
            </div>

            <!-- Client Table -->
            <div class="overflow-x-auto" v-if="filteredClients.length > 0">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-800">
                        <tr>
                            <th class="p-2 text-left w-10 text-gray-700 dark:text-gray-200">
                                <input type="checkbox" @change="toggleAllChecked" :checked="allChecked" class="rounded">
                            </th>
                            <th class="p-2 text-left cursor-pointer text-gray-700 dark:text-gray-200" @click="setSortBy('razao')">
                                Cliente
                                <span v-if="sortBy === 'razao'">↑</span>
                            </th>
                            <th class="p-2 text-left hidden sm:table-cell text-gray-700 dark:text-gray-200">Segmento</th>
                            <th class="p-2 text-left hidden md:table-cell text-gray-700 dark:text-gray-200">UF</th>
                            <th class="p-2 text-right cursor-pointer text-gray-700 dark:text-gray-200" @click="setSortBy('valor_total')">
                                Total
                                <span v-if="sortBy === 'valor_total'">↓</span>
                                <span v-if="sortBy === 'valor_total_asc'">↑</span>
                            </th>
                            <th class="p-2 text-center hidden sm:table-cell text-gray-700 dark:text-gray-200">Pedidos</th>
                            <th class="p-2 text-center cursor-pointer text-gray-700 dark:text-gray-200" @click="setSortBy('dias_sem_compra')">
                                Dias s/ Compra
                                <span v-if="sortBy === 'dias_sem_compra'">↓</span>
                                <span v-if="sortBy === 'dias_sem_compra_asc'">↑</span>
                            </th>
                            <th class="p-2 text-center text-gray-700 dark:text-gray-200">Status</th>
                            <th class="p-2 text-center text-gray-700 dark:text-gray-200">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(client, index) in paginatedClients" :key="client.cnpj" :class="[
                            'border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors'
                        ]">
                            <td class="p-2">
                                <input type="checkbox" :checked="checkedClients[client.cnpj]"
                                    @change="toggleClient(client)" class="rounded">
                            </td>
                            <td class="p-2">
                                <div class="flex items-center gap-1">
                                    <span v-if="checkedClients[client.cnpj]" class="text-green-500 text-lg" title="Cliente já trabalhado">✅</span>
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white truncate max-w-[150px]"
                                            :title="client.razao">
                                            @{{ client.razao || 'N/A' }}
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-300">@{{ client.cnpj }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-2 hidden sm:table-cell text-gray-700 dark:text-gray-200 text-xs">
                                @{{ client.segmento || '-' }}
                            </td>
                            <td class="p-2 hidden md:table-cell text-gray-700 dark:text-gray-200">
                                @{{ client.uf || '-' }}
                            </td>
                            <td class="p-2 text-right font-medium text-gray-900 dark:text-white">
                                R$ @{{ formatCurrency(client.valor_total) }}
                            </td>
                            <td class="p-2 text-center hidden sm:table-cell text-gray-700 dark:text-gray-200">
                                @{{ client.total_pedidos }}
                            </td>
                            <td class="p-2 text-center">
                                <span :class="getDaysClass(client.dias_sem_compra)">
                                    @{{ client.dias_sem_compra || '∞' }}
                                </span>
                            </td>
                            <td class="p-2 text-center">
                                <span :class="getStatusBadge(client.classificacao_risco)"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium">
                                    @{{ getShortLabel(client.classificacao_risco) }}
                                </span>
                            </td>
                            <td class="p-2 text-center">
                                <div class="flex justify-center gap-1">
                                    <button v-if="client.telefone" @click="callClient(client)"
                                        class="rounded p-1 text-green-600 hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors"
                                        title="Ligar">
                                        📞
                                    </button>
                                    <button v-if="client.email" @click="emailClient(client)"
                                        class="rounded p-1 text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors"
                                        title="E-mail">
                                        ✉️
                                    </button>
                                    <button @click="openModal(client)"
                                        class="rounded p-1 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        title="Ver Detalhes">
                                        👁️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="flex items-center justify-between mt-4 px-2">
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                        Mostrando @{{ paginationStart }} - @{{ paginationEnd }} de @{{ filteredClients.length }} clientes
                    </p>
                    <div class="flex gap-1">
                        <button @click="currentPage--" :disabled="currentPage <= 1"
                            class="rounded px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 disabled:opacity-50">
                            ⬅️
                        </button>
                        <span class="px-2 py-1 text-xs text-gray-700 dark:text-gray-200">@{{ currentPage }} / @{{ totalPages }}</span>
                        <button @click="currentPage++" :disabled="currentPage >= totalPages"
                            class="rounded px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 disabled:opacity-50">
                            ➡️
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-8 text-gray-600 dark:text-gray-300">
                <span class="text-4xl mb-2">📭</span>
                <p>Nenhum cliente encontrado</p>
                <p class="text-xs" v-if="activeFilter || checkFilter !== 'all'">Tente remover os filtros</p>
            </div>
        </div>
    </template>

    <!-- Client Detail Modal -->
    <div v-if="selectedClient" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        @click.self="closeModal">
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[80vh] overflow-y-auto">
            <div
                class="sticky top-0 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 p-4 flex justify-between items-center">
                <h3 class="font-semibold text-lg text-gray-900 dark:text-white">Detalhes do Cliente</h3>
                <button @click="closeModal" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 text-xl">&times;</button>
            </div>
            <div class="p-4 space-y-4">
                <!-- Client Info -->
                <div class="space-y-2">
                    <h4 class="font-medium text-gray-900 dark:text-white">@{{ selectedClient.razao }}</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-300">CNPJ: @{{ selectedClient.cnpj }}</p>

                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Segmento:</span>
                            <span class="ml-1 text-gray-800 dark:text-gray-200">@{{ selectedClient.segmento || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Localização:</span>
                            <span class="ml-1 text-gray-800 dark:text-gray-200">@{{ selectedClient.municipio }}, @{{ selectedClient.uf }}</span>
                        </div>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 space-y-2">
                    <h5 class="font-medium text-sm text-gray-800 dark:text-white">Contato</h5>
                    <div class="flex gap-2">
                        <a v-if="selectedClient.telefone" :href="'tel:' + selectedClient.telefone"
                            class="flex-1 text-center rounded bg-green-600 text-white py-2 text-sm hover:bg-green-700">
                            📞 @{{ selectedClient.telefone }}
                        </a>
                        <a v-if="selectedClient.email" :href="'mailto:' + selectedClient.email"
                            class="flex-1 text-center rounded bg-blue-600 text-white py-2 text-sm hover:bg-blue-700">
                            ✉️ E-mail
                        </a>
                    </div>
                </div>

                <!-- Purchase Stats -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-3 text-center">
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">@{{ selectedClient.total_pedidos }}</p>
                        <p class="text-xs text-blue-600/70 dark:text-blue-300">Total de Pedidos</p>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/30 rounded-lg p-3 text-center">
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">R$ @{{ formatCurrency(selectedClient.valor_total) }}
                        </p>
                        <p class="text-xs text-green-600/70 dark:text-green-300">Valor Total</p>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900/30 rounded-lg p-3 text-center">
                        <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">R$ @{{ formatCurrency(selectedClient.ticket_medio) }}
                        </p>
                        <p class="text-xs text-purple-600/70 dark:text-purple-300">Ticket Médio</p>
                    </div>
                    <div :class="[
                        'rounded-lg p-3 text-center',
                        selectedClient.dias_sem_compra > 90 ? 'bg-red-50 dark:bg-red-900/30' :
                        selectedClient.dias_sem_compra > 30 ? 'bg-yellow-50 dark:bg-yellow-900/30' :
                        'bg-green-50 dark:bg-green-900/30'
                    ]">
                        <p :class="[
                            'text-2xl font-bold',
                            selectedClient.dias_sem_compra > 90 ? 'text-red-600 dark:text-red-400' :
                            selectedClient.dias_sem_compra > 30 ? 'text-yellow-600 dark:text-yellow-400' : 'text-green-600 dark:text-green-400'
                        ]">@{{ selectedClient.dias_sem_compra || '∞' }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-300">Dias s/ Compra</p>
                    </div>
                </div>

                <!-- Classification Badge -->
                <div class="text-center">
                    <span :class="getStatusBadge(selectedClient.classificacao_risco)"
                        class="rounded-full px-4 py-1.5 text-sm font-medium inline-block">
                        @{{ getLabel(selectedClient.classificacao_risco) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
    </script>

    <script type="module">
        app.component('v-dashboard-client-risk-analysis', {
            template: '#v-dashboard-client-risk-analysis-template',

            data() {
                return {
                    report: { clients: [], summary: {}, total: 0, ticket_threshold: 0, last_updated: null },
                    checkHistory: { today_count: 0, total_period: 0, daily_counts: {} },
                    isLoading: true,
                    isRefreshing: false,
                    activeFilter: null,
                    checkFilter: 'all', // 'all', 'checked', 'unchecked'
                    sortBy: 'valor_total',
                    currentPage: 1,
                    perPage: 10,
                    checkedClients: {},
                    selectedClient: null,
                }
            },

            computed: {
                filteredClients() {
                    let clients = this.report.clients || [];

                    // Filter by classification
                    if (this.activeFilter) {
                        clients = clients.filter(c => c.classificacao_risco === this.activeFilter);
                    }

                    // Filter by check status
                    if (this.checkFilter === 'checked') {
                        clients = clients.filter(c => this.checkedClients[c.cnpj]);
                    } else if (this.checkFilter === 'unchecked') {
                        clients = clients.filter(c => !this.checkedClients[c.cnpj]);
                    }

                    // Sort
                    return clients.sort((a, b) => {
                        switch (this.sortBy) {
                            case 'valor_total':
                                return (b.valor_total || 0) - (a.valor_total || 0);
                            case 'valor_total_asc':
                                return (a.valor_total || 0) - (b.valor_total || 0);
                            case 'dias_sem_compra':
                                return (b.dias_sem_compra || 999999) - (a.dias_sem_compra || 999999);
                            case 'dias_sem_compra_asc':
                                return (a.dias_sem_compra || 0) - (b.dias_sem_compra || 0);
                            case 'razao':
                                return (a.razao || '').localeCompare(b.razao || '');
                            default:
                                return (b.valor_total || 0) - (a.valor_total || 0);
                        }
                    });
                },

                paginatedClients() {
                    const start = (this.currentPage - 1) * this.perPage;
                    return this.filteredClients.slice(start, start + this.perPage);
                },

                totalPages() {
                    return Math.ceil(this.filteredClients.length / this.perPage) || 1;
                },

                paginationStart() {
                    return Math.min((this.currentPage - 1) * this.perPage + 1, this.filteredClients.length);
                },

                paginationEnd() {
                    return Math.min(this.currentPage * this.perPage, this.filteredClients.length);
                },

                allChecked() {
                    return this.paginatedClients.length > 0 &&
                        this.paginatedClients.every(c => this.checkedClients[c.cnpj]);
                }
            },

            mounted() {
                this.getStats({});
                this.loadCheckedClients();
                this.loadCheckHistory();
                this.$emitter.on('reporting-filter-updated', this.onFilterUpdated);
            },

            methods: {
                onFilterUpdated(filters) {
                    this.getStats(filters);
                    this.loadCheckHistory();
                },

                getStats(filters) {
                    this.isLoading = true;
                    var params = Object.assign({}, filters);
                    params.type = 'client-risk-analysis';

                    this.$axios.get("{{ route('admin.dashboard.stats') }}", { params })
                        .then(response => {
                            this.report = response.data.statistics || {};
                            this.isLoading = false;
                        })
                        .catch(error => {
                            console.error('Client Risk Analysis error:', error);
                            this.isLoading = false;
                        });
                },

                loadCheckedClients() {
                    this.$axios.get("{{ route('admin.dashboard.stats') }}", { params: { type: 'checked-clients' } })
                        .then(response => {
                            const cnpjs = response.data.statistics?.checked_cnpjs || [];
                            this.checkedClients = {};
                            cnpjs.forEach(cnpj => {
                                this.checkedClients[cnpj] = true;
                            });
                        })
                        .catch(error => console.error('Load checked clients error:', error));
                },

                loadCheckHistory() {
                    this.$axios.get("{{ route('admin.dashboard.stats') }}", { params: { type: 'check-history' } })
                        .then(response => {
                            this.checkHistory = response.data.statistics || {};
                        })
                        .catch(error => console.error('Load check history error:', error));
                },

                refreshData() {
                    this.isRefreshing = true;
                    this.getStats({});
                    this.loadCheckHistory();
                    setTimeout(() => { this.isRefreshing = false; }, 1000);
                },

                toggleFilter(key) {
                    this.activeFilter = this.activeFilter === key ? null : key;
                    this.currentPage = 1;
                },

                setSortBy(column) {
                    if (this.sortBy === column) {
                        this.sortBy = column + '_asc';
                    } else if (this.sortBy === column + '_asc') {
                        this.sortBy = column;
                    } else {
                        this.sortBy = column;
                    }
                },

                toggleClient(client) {
                    const isChecked = !this.checkedClients[client.cnpj];
                    this.checkedClients[client.cnpj] = isChecked;

                    // Save to database
                    this.$axios.get("{{ route('admin.dashboard.stats') }}", {
                        params: {
                            type: 'toggle-client-check',
                            cnpj: client.cnpj,
                            client_name: client.razao,
                            classification: client.classificacao_risco,
                            is_checked: isChecked ? 1 : 0
                        }
                    }).then(response => {
                        // Update check history
                        this.loadCheckHistory();
                    }).catch(error => console.error('Toggle client error:', error));
                },

                toggleAllChecked() {
                    const newState = !this.allChecked;
                    this.paginatedClients.forEach(c => {
                        this.checkedClients[c.cnpj] = newState;
                        // Save each to database
                        this.$axios.get("{{ route('admin.dashboard.stats') }}", {
                            params: {
                                type: 'toggle-client-check',
                                cnpj: c.cnpj,
                                client_name: c.razao,
                                classification: c.classificacao_risco,
                                is_checked: newState ? 1 : 0
                            }
                        });
                    });
                    setTimeout(() => this.loadCheckHistory(), 500);
                },

                getBarHeight(count) {
                    const max = Math.max(...Object.values(this.checkHistory.daily_counts || {}), 1);
                    return Math.max((count / max) * 100, 5);
                },

                callClient(client) {
                    window.open(`tel:${client.telefone}`, '_self');
                },

                emailClient(client) {
                    window.open(`mailto:${client.email}?subject=Contato Comercial`, '_blank');
                },

                openModal(client) {
                    this.selectedClient = client;
                },

                closeModal() {
                    this.selectedClient = null;
                },

                formatCurrency(value) {
                    return (value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                formatDate(date) {
                    if (!date) return '';
                    return new Date(date).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                },

                getLabel(key) {
                    const labels = {
                        'ATIVO_FREQUENTE': 'Ativo Frequente',
                        'ATIVO_REGULAR': 'Ativo Regular',
                        'RISCO_INATIVACAO': 'Risco Inativação',
                        'OPORTUNIDADE_RECUPERACAO': 'Oportunidade',
                        'INATIVO_BAIXO_POTENCIAL': 'Inativo Baixo',
                        'SEM_HISTORICO': 'Sem Histórico'
                    };
                    return labels[key] || key;
                },

                getShortLabel(key) {
                    const labels = {
                        'ATIVO_FREQUENTE': '✅ Freq',
                        'ATIVO_REGULAR': '📊 Reg',
                        'RISCO_INATIVACAO': '⚠️ Risco',
                        'OPORTUNIDADE_RECUPERACAO': '🎯 Oport',
                        'INATIVO_BAIXO_POTENCIAL': '❌ Baixo',
                        'SEM_HISTORICO': '❓ S/Hist'
                    };
                    return labels[key] || key;
                },

                getCardClass(key) {
                    const classes = {
                        'ATIVO_FREQUENTE': 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-100 border-green-400 dark:border-green-600',
                        'ATIVO_REGULAR': 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-100 border-blue-400 dark:border-blue-600',
                        'RISCO_INATIVACAO': 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-100 border-yellow-400 dark:border-yellow-600',
                        'OPORTUNIDADE_RECUPERACAO': 'bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-100 border-purple-400 dark:border-purple-600',
                        'INATIVO_BAIXO_POTENCIAL': 'bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-white border-gray-400 dark:border-gray-500',
                        'SEM_HISTORICO': 'bg-slate-200 dark:bg-slate-600 text-slate-800 dark:text-white border-slate-400 dark:border-slate-500'
                    };
                    return classes[key] || 'bg-gray-200 text-gray-800 border-gray-400';
                },

                getStatusBadge(key) {
                    const classes = {
                        'ATIVO_FREQUENTE': 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-white',
                        'ATIVO_REGULAR': 'bg-blue-100 text-blue-800 dark:bg-blue-700 dark:text-white',
                        'RISCO_INATIVACAO': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-white',
                        'OPORTUNIDADE_RECUPERACAO': 'bg-purple-100 text-purple-800 dark:bg-purple-700 dark:text-white',
                        'INATIVO_BAIXO_POTENCIAL': 'bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-white',
                        'SEM_HISTORICO': 'bg-slate-200 text-slate-800 dark:bg-slate-600 dark:text-white'
                    };
                    return classes[key] || 'bg-gray-200 text-gray-800';
                },

                getDaysClass(days) {
                    if (!days && days !== 0) return 'text-gray-600 dark:text-gray-300';
                    if (days > 90) return 'text-red-600 dark:text-red-400 font-bold';
                    if (days > 30) return 'text-yellow-600 dark:text-yellow-400 font-medium';
                    return 'text-green-600 dark:text-green-400';
                }
            }
        });
    </script>
@endPushOnce