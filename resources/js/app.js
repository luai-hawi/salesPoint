import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

if (document.querySelector('canvas[data-chart]')) {
    import('./charts.js').then((module) => module.initCharts()).catch((error) => console.error(error));
}
