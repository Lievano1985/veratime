import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(
    BarController,
    BarElement,
    CategoryScale,
    Legend,
    Filler,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
);

const DEFAULT_MAP_CENTER = [19.4326, -99.1332];
const DEFAULT_MAP_ZOOM = 13;

const dashboardChartData = (container, selector) => {
    const element = container.querySelector(selector);

    return element ? JSON.parse(element.textContent) : null;
};

const replaceDashboardChart = (canvas, config) => {
    canvas.__veraChart?.destroy();
    canvas.__veraChart = new Chart(canvas, config);
};

const hexWithOpacity = (color, opacity) => {
    const value = color.replace('#', '');
    const red = Number.parseInt(value.slice(0, 2), 16);
    const green = Number.parseInt(value.slice(2, 4), 16);
    const blue = Number.parseInt(value.slice(4, 6), 16);

    return `rgba(${red}, ${green}, ${blue}, ${opacity})`;
};

const verticalGradient = (context, topColor, bottomColor) => {
    const {chart, chartArea} = context;

    if (!chartArea) {
        return topColor;
    }

    const gradient = chart.ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
    gradient.addColorStop(0, topColor);
    gradient.addColorStop(1, bottomColor);

    return gradient;
};

const initializeDashboardCharts = () => {
    document.querySelectorAll('[data-vera-dashboard-charts]').forEach((container) => {
        const compliance = dashboardChartData(container, '[data-dashboard-compliance]');
        const trends = dashboardChartData(container, '[data-dashboard-trends]');
        const complianceCanvas = container.querySelector('[data-dashboard-compliance-chart]');
        const trendsCanvas = container.querySelector('[data-dashboard-trends-chart]');
        const textColor = document.documentElement.classList.contains('dark') ? '#D4D4D4' : '#525252';
        const gridColor = document.documentElement.classList.contains('dark') ? '#404040' : '#E4EAF2';

        if (compliance && complianceCanvas) {
            replaceDashboardChart(complianceCanvas, {
                type: 'bar',
                data: {
                    labels: compliance.weeks.map((week) => week.label.replace('Sem. ', 'S')),
                    datasets: [{
                        label: 'Cumplimiento',
                        data: compliance.weeks.map((week) => week.percentage),
                        backgroundColor: (context) => verticalGradient(context, '#0C86FF', '#0067E4'),
                        borderRadius: 8,
                        borderSkipped: false,
                        maxBarThickness: 26,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: {display: false},
                            border: {display: false},
                            ticks: {color: textColor, font: {weight: 600}},
                        },
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: {display: false},
                            border: {display: false},
                            ticks: {color: textColor, precision: 0, padding: 10},
                        },
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            backgroundColor: '#021939',
                            padding: 12,
                            cornerRadius: 10,
                            displayColors: false,
                            callbacks: {
                                title: (items) => compliance.weeks[items[0].dataIndex].label,
                                label: (context) => {
                                    const week = compliance.weeks[context.dataIndex];

                                    return `${week.percentage}% de cumplimiento`;
                                },
                                afterBody: (items) => {
                                    const week = compliance.weeks[items[0].dataIndex];

                                    return `${week.closed} cerradas · ${week.total - week.closed} por atender`;
                                },
                            },
                        },
                    },
                },
            });
        }

        if (trends && trendsCanvas && trends.series.length > 0) {
            replaceDashboardChart(trendsCanvas, {
                type: 'line',
                data: {
                    labels: trends.weeks.map((week) => week.label),
                    datasets: trends.series.map((series, index) => ({
                        label: series.label,
                        data: series.values,
                        borderColor: series.color,
                        backgroundColor: (context) => index === 0 ? verticalGradient(
                            context,
                            hexWithOpacity(series.color, 0.22),
                            hexWithOpacity(series.color, 0),
                        ) : hexWithOpacity(series.color, 0),
                        borderWidth: 2.5,
                        pointRadius: (context) => context.dataIndex === series.values.length - 1 ? 4 : 0,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#FFFFFF',
                        pointBorderWidth: 2.5,
                        tension: 0.42,
                        fill: index === 0,
                        spanGaps: true,
                    })),
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {mode: 'index', intersect: false},
                    scales: {
                        x: {
                            grid: {display: false},
                            border: {display: false},
                            ticks: {color: textColor, maxTicksLimit: 10, font: {weight: 600}},
                        },
                        y: {
                            beginAtZero: true,
                            grid: {color: gridColor, drawBorder: false},
                            border: {display: false},
                            ticks: {color: textColor, precision: 0, padding: 10},
                        },
                    },
                    plugins: {
                        legend: {display: false},
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: '#021939',
                            padding: 12,
                            cornerRadius: 10,
                            displayColors: true,
                        },
                    },
                },
            });
        }
    });
};

document.addEventListener('DOMContentLoaded', initializeDashboardCharts);
document.addEventListener('livewire:navigated', initializeDashboardCharts);

window.veraMapPicker = () => ({
    isOpen: false,
    locationError: '',
    map: null,
    marker: null,
    circle: null,

    init() {
        this.$watch('radius', () => this.refreshCircle());
    },

    open() {
        this.locationError = '';
        this.isOpen = true;

        this.$nextTick(() => {
            window.requestAnimationFrame(() => this.initializeMap());
        });
    },

    close() {
        this.isOpen = false;
    },

    initializeMap() {
        const coordinates = this.coordinates();

        if (!this.map) {
            this.map = L.map(this.$refs.map, {scrollWheelZoom: true}).setView(coordinates, coordinates === DEFAULT_MAP_CENTER ? DEFAULT_MAP_ZOOM : 16);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">OpenStreetMap</a> contributors',
            }).addTo(this.map);

            this.marker = L.marker(coordinates, {
                draggable: true,
                icon: L.divIcon({
                    className: 'vera-map-marker',
                    html: '<span aria-hidden="true"></span>',
                    iconSize: [28, 28],
                    iconAnchor: [14, 28],
                }),
            }).addTo(this.map);

            this.marker.on('dragend', () => this.setCoordinates(this.marker.getLatLng()));
            this.map.on('click', (event) => this.setCoordinates(event.latlng));
        } else {
            this.marker.setLatLng(coordinates);
            this.map.setView(coordinates, 16);
        }

        this.refreshCircle();
        this.map.invalidateSize();
    },

    coordinates() {
        const latitude = Number.parseFloat(this.latitude);
        const longitude = Number.parseFloat(this.longitude);

        return Number.isFinite(latitude) && Number.isFinite(longitude)
            ? [latitude, longitude]
            : DEFAULT_MAP_CENTER;
    },

    previewRadius() {
        const radius = Number.parseInt(this.radius, 10);

        return Number.isFinite(radius) && radius > 0 ? radius : 100;
    },

    setCoordinates(latLng) {
        this.latitude = Number(latLng.lat).toFixed(7);
        this.longitude = Number(latLng.lng).toFixed(7);
        this.marker.setLatLng(latLng);
        this.refreshCircle();
    },

    refreshCircle() {
        if (!this.map || !this.marker) {
            return;
        }

        const options = {
            color: '#0067e4',
            fillColor: '#29b6f6',
            fillOpacity: 0.16,
            weight: 2,
        };

        if (!this.circle) {
            this.circle = L.circle(this.marker.getLatLng(), {radius: this.previewRadius(), ...options}).addTo(this.map);

            return;
        }

        this.circle.setLatLng(this.marker.getLatLng());
        this.circle.setRadius(this.previewRadius());
    },

    useCurrentLocation() {
        this.locationError = '';

        if (!navigator.geolocation) {
            this.locationError = 'Este navegador no permite obtener la ubicación actual.';

            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const latLng = L.latLng(position.coords.latitude, position.coords.longitude);

                this.setCoordinates(latLng);
                this.map.setView(latLng, 17);
            },
            () => {
                this.locationError = 'No fue posible obtener tu ubicación. Revisa el permiso del navegador o coloca el pin manualmente.';
            },
            {enableHighAccuracy: true, timeout: 10000, maximumAge: 0},
        );
    },
});
