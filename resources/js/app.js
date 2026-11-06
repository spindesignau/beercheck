import './bootstrap';
import Alpine from 'alpinejs';
import { geoDiscovery } from './geo-discovery';
import { priceForm } from './price-form';
import { typeahead } from './typeahead';

window.Alpine = Alpine;
Alpine.data('geoDiscovery', geoDiscovery);
Alpine.data('priceForm', priceForm);
Alpine.data('typeahead', typeahead);
Alpine.start();
