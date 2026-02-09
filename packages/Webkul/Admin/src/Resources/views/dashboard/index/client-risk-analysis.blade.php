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
    <!-- Chart.js for Line Chart -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
                        📊 Análise de Carteira de Clientes (Ativos)
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

            <!-- Line Chart: Daily Contacts -->
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4"
                v-if="checkHistory.labels && checkHistory.labels.length > 0">
                <h3 class="text-sm font-medium text-gray-800 dark:text-white mb-3">📈 Contatos Realizados por Dia</h3>
                <div class="relative h-48">
                    <canvas ref="lineChart"></canvas>
                </div>
            </div>

            <!-- Check Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" v-if="checkHistory.today_count !== undefined">
                <div class="rounded-lg bg-gradient-to-br from-emerald-500 to-green-600 p-3 text-white">
                    <p class="text-xs opacity-80">Contatos Realizados Hoje</p>
                    <p class="text-2xl font-bold">@{{ checkHistory.today_count }}</p>
                </div>
                <div class="rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 p-3 text-white">
                    <p class="text-xs opacity-80">Total no Período</p>
                    <p class="text-2xl font-bold">@{{ checkHistory.total_period }}</p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4" v-if="report.summary">
                <!-- Ativo Frequente - Green -->
                <div @click="toggleFilter('ATIVO_FREQUENTE')" :class="[
                            'cursor-pointer rounded-lg p-3 text-center transition-all border-2',
                            activeFilter === 'ATIVO_FREQUENTE' ? 'ring-2 ring-offset-2 ring-blue-500' : '',
                            'bg-green-500 dark:bg-green-600 text-white border-green-600'
                        ]" :title="getTooltip('ATIVO_FREQUENTE')">
                    <p class="text-2xl font-bold">@{{ report.summary.ATIVO_FREQUENTE || 0 }}</p>
                    <p class="text-xs">Ativo Frequente</p>
                </div>

                <!-- Ativo Regular - Blue -->
                <div @click="toggleFilter('ATIVO_REGULAR')" :class="[
                            'cursor-pointer rounded-lg p-3 text-center transition-all border-2',
                            activeFilter === 'ATIVO_REGULAR' ? 'ring-2 ring-offset-2 ring-blue-500' : '',
                            'bg-blue-500 dark:bg-blue-600 text-white border-blue-600'
                        ]" :title="getTooltip('ATIVO_REGULAR')">
                    <p class="text-2xl font-bold">@{{ report.summary.ATIVO_REGULAR || 0 }}</p>
                    <p class="text-xs">Ativo Regular</p>
                </div>

                <!-- Risco Inativação - Yellow/Orange with WHITE text -->
                <div @click="toggleFilter('RISCO_INATIVACAO')" :class="[
                            'cursor-pointer rounded-lg p-3 text-center transition-all border-2',
                            activeFilter === 'RISCO_INATIVACAO' ? 'ring-2 ring-offset-2 ring-blue-500' : '',
                            'bg-orange-500 dark:bg-orange-600 text-white border-orange-600'
                        ]" :title="getTooltip('RISCO_INATIVACAO')">
                    <p class="text-2xl font-bold">@{{ report.summary.RISCO_INATIVACAO || 0 }}</p>
                    <p class="text-xs">Risco Inativação</p>
                </div>

                <!-- Sem Histórico - Gray with BLACK text -->
                <div @click="toggleFilter('SEM_HISTORICO')" :class="[
                            'cursor-pointer rounded-lg p-3 text-center transition-all border-2',
                            activeFilter === 'SEM_HISTORICO' ? 'ring-2 ring-offset-2 ring-blue-500' : '',
                            'bg-gray-200 dark:bg-gray-400 text-gray-900 border-gray-400'
                        ]" :title="getTooltip('SEM_HISTORICO')">
                    <p class="text-2xl font-bold">@{{ report.summary.SEM_HISTORICO || 0 }}</p>
                    <p class="text-xs">Sem Histórico</p>
                </div>
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
                <select v-model="sortBy"
                    class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-2 py-1 text-xs">
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
                            <th class="p-2 text-left w-10 text-gray-700 dark:text-white">
                                <input type="checkbox" @change="toggleAllChecked" :checked="allChecked" class="rounded">
                            </th>
                            <th class="p-2 text-left cursor-pointer text-gray-700 dark:text-white"
                                @click="setSortBy('razao')">
                                Cliente
                                <span v-if="sortBy === 'razao'">↑</span>
                            </th>
                            <th class="p-2 text-right cursor-pointer text-gray-700 dark:text-white"
                                @click="setSortBy('valor_total')">
                                Total
                                <span v-if="sortBy === 'valor_total'">↓</span>
                                <span v-if="sortBy === 'valor_total_asc'">↑</span>
                            </th>
                            <th class="p-2 text-center cursor-pointer text-gray-700 dark:text-white"
                                @click="setSortBy('dias_sem_compra')">
                                Dias s/ Compra
                                <span v-if="sortBy === 'dias_sem_compra'">↓</span>
                                <span v-if="sortBy === 'dias_sem_compra_asc'">↑</span>
                            </th>
                            <th class="p-2 text-center text-gray-700 dark:text-white">Status</th>
                            <th class="p-2 text-center text-gray-700 dark:text-white">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(client, index) in paginatedClients" :key="client.cnpj" :class="[
                                    'border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors'
                                ]">
                            <td class="p-2">
                                <input type="checkbox" :checked="checkedClients[client.cnpj]" @change="toggleClient(client)"
                                    class="rounded">
                            </td>
                            <td class="p-2">
                                <div class="flex items-center gap-1">
                                    <span v-if="checkedClients[client.cnpj]" class="text-green-500 text-lg"
                                        title="Cliente já contatado">✅</span>
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white truncate max-w-[200px]"
                                            :title="client.razao">
                                            @{{ client.razao || 'N/A' }}
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-300">@{{ client.cnpj }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-2 text-right font-medium text-gray-900 dark:text-white">
                                R$ @{{ formatCurrency(client.valor_total) }}
                            </td>
                            <td class="p-2 text-center">
                                <span :class="getDaysClass(client.dias_sem_compra)">
                                    @{{ client.dias_sem_compra || '∞' }}
                                </span>
                            </td>
                            <td class="p-2 text-center">
                                <span :class="getStatusBadge(client.classificacao_risco)"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium cursor-help"
                                    :title="getTooltip(client.classificacao_risco)">
                                    @{{ getShortLabel(client.classificacao_risco) }}
                                </span>
                            </td>
                            <td class="p-2 text-center">
                                <div class="flex justify-center gap-1">
                                    <!-- Create Lead Button -->
                                    <button @click="createLead(client)"
                                        class="rounded px-2 py-1 text-xs bg-blue-600 hover:bg-blue-700 text-white transition-colors flex items-center gap-1"
                                        title="Criar Lead no Kanban">
                                        <span>➕</span> Lead
                                    </button>
                                    <!-- View Details Button -->
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
                        <span class="px-2 py-1 text-xs text-gray-700 dark:text-gray-200">@{{ currentPage }} / @{{ totalPages
                            }}</span>
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
                <button @click="closeModal"
                    class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 text-xl">&times;</button>
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
                            <span class="ml-1 text-gray-800 dark:text-gray-200">@{{ selectedClient.municipio }}, @{{
                                selectedClient.uf }}</span>
                        </div>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 space-y-2">
                    <h5 class="font-medium text-sm text-gray-800 dark:text-white">Contato</h5>
                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        <p v-if="selectedClient.telefone">📞 @{{ selectedClient.telefone }}</p>
                        <p v-if="selectedClient.email">✉️ @{{ selectedClient.email }}</p>
                    </div>
                    <button @click="createLead(selectedClient); closeModal();"
                        class="w-full rounded bg-blue-600 text-white py-2 text-sm hover:bg-blue-700 flex items-center justify-center gap-2">
                        ➕ Criar Lead no Kanban
                    </button>
                </div>

                <!-- Purchase Stats -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-3 text-center">
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">@{{ selectedClient.total_pedidos }}
                        </p>
                        <p class="text-xs text-blue-600/70 dark:text-blue-300">Total de Pedidos</p>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/30 rounded-lg p-3 text-center">
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">R$ @{{
                            formatCurrency(selectedClient.valor_total) }}
                        </p>
                        <p class="text-xs text-green-600/70 dark:text-green-300">Valor Total</p>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900/30 rounded-lg p-3 text-center">
                        <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">R$ @{{
                            formatCurrency(selectedClient.ticket_medio) }}
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

                <!-- Classification Badge with Tooltip -->
                <div class="text-center">
                    <span :class="getStatusBadge(selectedClient.classificacao_risco)"
                        class="rounded-full px-4 py-1.5 text-sm font-medium inline-block cursor-help"
                        :title="getTooltip(selectedClient.classificacao_risco)">
                        @{{ getLabel(selectedClient.classificacao_risco) }}
                    </span>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">@{{
                        getTooltip(selectedClient.classificacao_risco) }}</p>
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
                            checkHistory: { today_count: 0, total_period: 0, daily_counts: {}, labels: [], data: [] },
                            isLoading: true,
                            isRefreshing: false,
                            activeFilter: null,
                            checkFilter: 'all',
                            sortBy: 'valor_total',
                            currentPage: 1,
                            perPage: 10,
                            checkedClients: {},
                            selectedClient: null,
                            lineChart: null,
                        }
                    },

                    computed: {
                        filteredClients() {
                            let clients = this.report.clients || [];

                            if (this.activeFilter) {
                                clients = clients.filter(c => c.classificacao_risco === this.activeFilter);
                            }

                            if (this.checkFilter === 'checked') {
                                clients = clients.filter(c => this.checkedClients[c.cnpj]);
                            } else if (this.checkFilter === 'unchecked') {
                                clients = clients.filter(c => !this.checkedClients[c.cnpj]);
                            }

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
                                    this.$nextTick(() => this.renderLineChart());
                                })
                                .catch(error => console.error('Load check history error:', error));
                        },

                        renderLineChart() {
                            const canvas = this.$refs.lineChart;
                            if (!canvas || !this.checkHistory.labels || this.checkHistory.labels.length === 0) return;

                            if (this.lineChart) {
                                this.lineChart.destroy();
                            }

                            const ctx = canvas.getContext('2d');
                            const isDark = document.documentElement.classList.contains('dark');

                            this.lineChart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: this.checkHistory.labels.map(d => {
                                        const date = new Date(d);
                                        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                                    }),
                                    datasets: [{
                                        label: 'Contatos',
                                        data: this.checkHistory.data,
                                        borderColor: '#3B82F6',
                                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                        borderWidth: 2,
                                        fill: true,
                                        tension: 0.3,
                                        pointRadius: 4,
                                        pointBackgroundColor: '#3B82F6',
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false }
                                    },
                                    scales: {
                                        x: {
                                            grid: { color: isDark ? '#374151' : '#E5E7EB' },
                                            ticks: { color: isDark ? '#D1D5DB' : '#374151' }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            grid: { color: isDark ? '#374151' : '#E5E7EB' },
                                            ticks: { 
                                                color: isDark ? '#D1D5DB' : '#374151',
                                                stepSize: 1
                                            }
                                        }
                                    }
                                }
                            });
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
                            const isChecked = !this.checkedClients[client.cnpj];     this.checkedClients[client.cnpj] = isChecked;

                            this.$axios.get("{{ route('admin.dashboard.stats') }}", {
                                params: {
                                    type: 'toggle-client-check',
                                    cnpj: client.cnpj,
                                    client_name: client.razao,
                                    classification: client.classificacao_risco,
                                    is_checked: isChecked ? 1 : 0
                                }
                            }).then(response => {
                                this.loadCheckHistory();
                            }).catch(error => console.error('Toggle client error:', error));
                        },

                        toggleAllChecked() {
                            const newState = !this.allChecked;
                            this.paginatedClients.forEach(c => {
                                this.checkedClients[c.cnpj] = newState;
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

                        createLead(client) {
                            // Create lead via API with all client data
                            this.$axios.get("{{ route('admin.dashboard.stats') }}", {
                                params: {
                                    type: 'create-lead-from-client',
                                    cnpj: client.cnpj,
                                    razao: client.razao,
                                    telefone: client.telefone,
                                    email: client.email,
                                    segmento: client.segmento,
                                    municipio: client.municipio,
                                    uf: client.uf,
                                    valor_total: client.valor_total,
                                    ticket_medio: client.ticket_medio,
                                    total_pedidos: client.total_pedidos,
                                    dias_sem_compra: client.dias_sem_compra,
                                    classificacao_risco: client.classificacao_risco,
                                }
                            }).then(response => {
                                const result = response.data.statistics;
                                if (result.success) {
                                    this.$emitter.emit('add-flash', { type: 'success', message: result.message });
                                    // Mark as checked and redirect to lead
                                    this.checkedClients[client.cnpj] = true;
                                    setTimeout(() => {
                                        window.location.href = result.redirect_url;
                                    }, 500);
                                } else {
                                    this.$emitter.emit('add-flash', { type: 'error', message: result.message || 'Erro ao criar lead' });
                                }
                            }).catch(error => {
                                console.error('Create lead error:', error);
                                this.$emitter.emit('add-flash', { type: 'error', message: 'Erro ao criar lead: ' + (error.response?.data?.message || error.message) });
                            });
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
                                'SEM_HISTORICO': 'Sem Histórico'
                            };
                            return labels[key] || key;
                        },

                        getShortLabel(key) {
                            const labels = {
                                'ATIVO_FREQUENTE': '✅ Freq',
                                'ATIVO_REGULAR': '📊 Reg',
                                'RISCO_INATIVACAO': '⚠️ Risco',
                                'SEM_HISTORICO': '❓ S/Hist'
                            };
                            return labels[key] || key;
                        },

                        getTooltip(key) {
                            const tooltips = {
                                'ATIVO_FREQUENTE': 'Cliente comprou nos últimos 30 dias. Manter relacionamento ativo.',
                                'ATIVO_REGULAR': 'Cliente comprou entre 31-90 dias. Atenção para não perder engajamento.',
                                'RISCO_INATIVACAO': 'Mais de 90 dias sem compra. Prioridade alta para contato!',
                                'SEM_HISTORICO': 'Cliente novo ou sem histórico de compras registrado.'
                            };
                            return tooltips[key] || '';
                        },

                        getCardClass(key) {
                            const classes = {
                                'ATIVO_FREQUENTE': 'bg-green-500 text-white border-green-600',
                                'ATIVO_REGULAR': 'bg-blue-500 text-white border-blue-600',
                                'RISCO_INATIVACAO': 'bg-orange-500 text-white border-orange-600',
                                'SEM_HISTORICO': 'bg-gray-200 dark:bg-gray-400 text-gray-900 border-gray-400'
                            };
                            return classes[key] || 'bg-gray-200 text-gray-800 border-gray-400';
                        },

                        getStatusBadge(key) {
                            const classes = {
                                'ATIVO_FREQUENTE': 'bg-green-500 text-white',
                                'ATIVO_REGULAR': 'bg-blue-500 text-white',
                                'RISCO_INATIVACAO': 'bg-orange-500 text-white',
                                'SEM_HISTORICO': 'bg-gray-300 text-gray-900'
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