<v-charts-doughnut {{ $attributes }}></v-charts-doughnut>

@pushOnce('scripts')
    <!-- SEO Vue Component Template -->
    <script type="text/x-template" id="v-charts-doughnut-template">
            <canvas
                :id="$.uid + '_chart'"
                class="flex w-full max-w-full items-end"
            ></canvas>
        </script>

    <script type="module">
        app.component('v-charts-doughnut', {
            template: '#v-charts-doughnut-template',

            props: {
                labels: {
                    type: Array,
                    default: [],
                },

                datasets: {
                    type: Array,
                    default: true,
                },
            },

            data() {
                return {
                    chart: undefined,
                }
            },

            mounted() {
                this.prepare();
            },

            methods: {
                prepare() {
                    if (this.chart) {
                        this.chart.destroy();
                    }

                    const ctx = document.getElementById(this.$.uid + '_chart');

                    if (!ctx) {
                        return;
                    }

                    this.chart = new Chart(ctx, {
                        type: 'doughnut',

                        data: {
                            labels: this.labels,

                            datasets: this.datasets,
                        },

                        options: {
                            plugins: {
                                legend: {
                                    display: false
                                },
                            },
                        }
                    });
                }
            }
        });
    </script>
@endPushOnce