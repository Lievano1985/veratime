import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const DEFAULT_MAP_CENTER = [19.4326, -99.1332];
const DEFAULT_MAP_ZOOM = 13;

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
