<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.dashboard.index.title')
    </x-slot:title>

    <!-- Content -->
    <div class="flex gap-4 max-lg:flex-wrap">
        <!-- Left Section -->
        <div class="flex flex-1 flex-col gap-4 max-lg:flex-auto">
            <!-- Header -->
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-base font-bold text-gray-600 dark:text-gray-300">
                    @lang('admin::app.dashboard.index.title')
                </p>

                <!-- Actions -->
                {!! view_render_event('admin.dashboard.index.header.right.before') !!}

                <div class="flex items-center gap-1.5 focus:outline-none">
                    <span class="icon-calendar text-2xl text-gray-600 dark:text-gray-300"></span>
                    
                    <!-- Date Filters -->
                    <v-dashboard-filters>
                        <!-- Shimmer -->
                        <div class="flex gap-1.5">
                            <div class="light-shimmer-bg dark:shimmer h-[39px] w-[140px] rounded-md"></div>
                            <div class="light-shimmer-bg dark:shimmer h-[39px] w-[140px] rounded-md"></div>
                        </div>
                    </v-dashboard-filters>
                </div>

                {!! view_render_event('admin.dashboard.index.header.right.after') !!}
            </div>

            {!! view_render_event('admin.dashboard.index.content.left.after') !!}

            <!-- Cards Section -->
            <div class="flex flex-col gap-4">
                {!! view_render_event('admin.dashboard.index.revenue.before') !!}

                <!-- Revenue Stats -->
                @include('admin::dashboard.index.revenue')

                <!-- Over All Stats -->
                {{-- @include('admin::dashboard.index.over-all') --}}

                <!-- Total Leads Stats -->
                {{-- @include('admin::dashboard.index.total-leads') --}}

                <div class="flex gap-4 max-lg:flex-wrap">
                    <!-- Total Products -->
                    {{-- @include('admin::dashboard.index.top-selling-products') --}}

                    <!-- Total Persons -->
                    {{-- @include('admin::dashboard.index.top-persons') --}}
                </div>
            </div>

            {!! view_render_event('admin.dashboard.index.content.left.after') !!}
        </div>

        <!-- Right Section -->
        {!! view_render_event('admin.dashboard.index.content.right.before') !!}

        <div class="flex w-[378px] max-w-full flex-col gap-4 max-sm:w-full">
            <!-- Revenue by Types -->
            {{-- @include('admin::dashboard.index.open-leads-by-states') --}}

            <!-- Revenue by Sources (Faturamento Atual / Meta) -->
            @include('admin::dashboard.index.revenue-by-sources')

            <!-- Revenue by Types (Clientes Positivados) -->
            @include('admin::dashboard.index.revenue-by-types')
        </div>

        {!! view_render_event('admin.dashboard.index.content.right.after') !!}
    </div>

    @pushOnce('scripts')
        {{-- app.js is already included in layout, removing redundant 404 script --}}
         
        <script
            type="module"
            src="https://cdn.jsdelivr.net/npm/chart.js/dist/chart.umd.min.js"
        >
        </script>

        <script type="module" src="https://cdn.jsdelivr.net/npm/chartjs-chart-funnel@4.2.1/build/index.umd.min.js">
        </script>

        <script type="text/x-template" id="v-dashboard-filters-template">
            {!! view_render_event('admin.dashboard.index.date_filters.before') !!}

            <div class="flex gap-1.5 relative" ref="userDropdown">
                @if (!empty($users) && $users->isNotEmpty())
                    <!-- Custom Dropdown Trigger -->
                    <button
                        type="button"
                        class="flex min-h-[39px] w-full items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                        @click="toggleUserDropdown"
                    >
                        <span class="truncate">@{{ selectedUsersLabel }}</span>
                        <span class="icon-arrow-down text-2xl"></span>
                    </button>
                @endif

                @if (!empty($managers))
                    <!-- Manager Filter (Admins only) -->
                    <div class="relative" ref="managerDropdown">
                        <button
                            class="flex min-h-[39px] w-[180px] items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                            @click="toggleManagerDropdown"
                        >
                            <span class="truncate">@{{ selectedManagerLabel }}</span>
                            <span class="icon-arrow-down text-2xl"></span>
                        </button>

                        <div
                            v-if="showManagerDropdown"
                            class="absolute top-full z-10 mt-1 w-[200px] rounded-md border bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
                        >
                            <div class="max-h-[300px] overflow-y-auto p-2">
                                <div 
                                    class="flex items-center gap-2 p-2 hover:bg-gray-100 dark:hover:bg-gray-950 rounded cursor-pointer" 
                                    @click="selectManager('')"
                                >
                                    <div class="h-4 w-4 rounded border border-gray-300 dark:border-gray-600 flex items-center justify-center p-0.5" :class="{'bg-brandColor border-brandColor': !filters.manager_email}">
                                        <span v-if="!filters.manager_email" class="icon-check text-white text-[10px] font-bold"></span>
                                    </div>
                                    <span class="text-sm text-gray-600 dark:text-gray-300">@lang('All Managers')</span>
                                </div>

                                <div 
                                    class="flex items-center gap-2 p-2 hover:bg-gray-100 dark:hover:bg-gray-950 rounded cursor-pointer" 
                                    v-for="manager in managers" 
                                    :key="manager.email" 
                                    @click="selectManager(manager.email)"
                                >
                                    <div class="h-4 w-4 rounded border border-gray-300 dark:border-gray-600 flex items-center justify-center p-0.5" :class="{'bg-brandColor border-brandColor': filters.manager_email === manager.email}">
                                        <span v-if="filters.manager_email === manager.email" class="icon-check text-white text-[10px] font-bold"></span>
                                    </div>
                                    <span class="text-sm text-gray-600 dark:text-gray-300">@{{ manager.name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                @if (!empty($users) && $users->isNotEmpty())
                    <!-- Dropdown Content -->
                    <div
                        v-if="showUserDropdown"
                        class="absolute top-full z-10 mt-1 w-[200px] rounded-md border bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
                    >
                            <div class="max-h-[300px] overflow-y-auto p-2">
                            <div class="flex items-center gap-2 p-2 hover:bg-gray-100 dark:hover:bg-gray-950 rounded cursor-pointer" @click="filters.user_id = []">
                                <div class="h-4 w-4 rounded border border-gray-300 dark:border-gray-600 flex items-center justify-center p-0.5" :class="{'bg-brandColor border-brandColor': filters.user_id.length === 0}">
                                    <span v-if="filters.user_id.length === 0" class="icon-check text-white text-[10px] font-bold"></span>
                                </div>
                                <span class="text-sm text-gray-600 dark:text-gray-300">@lang('All Users')</span>
                            </div>

                            <div class="flex items-center gap-2 p-2 hover:bg-gray-100 dark:hover:bg-gray-950 rounded cursor-pointer" v-for="user in users" :key="user.id" @click="toggleUser(user.id)">
                                <div class="h-4 w-4 rounded border border-gray-300 dark:border-gray-600 flex items-center justify-center p-0.5" :class="{'bg-brandColor border-brandColor': filters.user_id.includes(user.id)}">
                                    <span v-if="filters.user_id.includes(user.id)" class="icon-check text-white text-[10px] font-bold"></span>
                                </div>
                                <span class="text-sm text-gray-600 dark:text-gray-300">@{{ user.name }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                <x-admin::flat-picker.date
                    class="!w-[140px]"
                    ::allow-input="false"
                    ::max-date="filters.end"
                >
                    <input
                        class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                        v-model="filters.start"
                        placeholder="@lang('admin::app.dashboard.index.start-date')"
                    />
                </x-admin::flat-picker.date>

                <x-admin::flat-picker.date
                    class="!w-[140px]"
                    ::allow-input="false"
                    ::max-date="filters.end"
                >
                    <input
                        class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                        v-model="filters.end"
                        placeholder="@lang('admin::app.dashboard.index.end-date')"
                    />
                </x-admin::flat-picker.date>
            </div>

            {!! view_render_event('admin.dashboard.index.date_filters.after') !!}
        </script>

        <script type="module">
            window.dashboardUsers = @json($users);
            window.dashboardManagers = @json($managers);
            window.defaultUserId = @json($defaultUserId ? [$defaultUserId] : []);
            window.strAllUsers = @json(__('All Users'));
            window.strAllManagers = @json(__('All Managers'));
            window.strManager = @json(__('Manager'));

            app.component('v-dashboard-filters', {
                template: '#v-dashboard-filters-template',

                data() {
                    return {
                        filters: {
                            user_id: window.defaultUserId,
                            manager_email: '',
                            start: "{{ $startDate->format('Y-m-d') }}",
                            end: "{{ $endDate->format('Y-m-d') }}",
                        },
                        showUserDropdown: false,
                        showManagerDropdown: false,
                        users: window.dashboardUsers || [],
                        managers: window.dashboardManagers || [],
                    }
                },

                computed: {
                    selectedUsersLabel() {
                        if (!this.filters.user_id || this.filters.user_id.length === 0) {
                            return window.strAllUsers;
                        }

                        if (this.filters.user_id.length === 1) {
                            const userId = this.filters.user_id[0];
                            const user = this.users.find(u => u.id == userId);
                            return user ? user.name : '1 User';
                        }

                        return this.filters.user_id.length + " Users";
                    },

                    selectedManagerLabel() {
                        if (!this.filters.manager_email) {
                            return window.strAllManagers;
                        }

                        const manager = this.managers.find(m => m.email === this.filters.manager_email);
                        return manager ? manager.name : window.strManager;
                    }
                },

                mounted() {
                    document.addEventListener('click', this.handleClickOutside);
                },

                unmounted() {
                    document.removeEventListener('click', this.handleClickOutside);
                },

                methods: {
                    toggleUserDropdown() {
                        this.showUserDropdown = !this.showUserDropdown;
                        this.showManagerDropdown = false;
                    },

                    toggleManagerDropdown() {
                        this.showManagerDropdown = !this.showManagerDropdown;
                        this.showUserDropdown = false;
                    },

                    handleClickOutside(event) {
                        const userDropdown = this.$refs.userDropdown;
                        if (userDropdown && !userDropdown.contains(event.target)) {
                            this.showUserDropdown = false;
                        }

                        const managerDropdown = this.$refs.managerDropdown;
                        if (managerDropdown && !managerDropdown.contains(event.target)) {
                            this.showManagerDropdown = false;
                        }
                    },

                    selectManager(email) {
                        this.filters.manager_email = email;
                        this.showManagerDropdown = false;
                    },

                    toggleUser(userId) {
                        const index = this.filters.user_id.indexOf(userId);
                        if (index > -1) {
                            this.filters.user_id.splice(index, 1);
                        } else {
                            this.filters.user_id.push(userId);
                        }
                    }
                },

                watch: {
                    filters: {
                        handler(newValue) {
                            this.$emitter.emit('reporting-filter-updated', newValue);
                        },

                        deep: true,
                        immediate: true
                    }
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>