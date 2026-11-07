import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { geoDiscovery } from './geo-discovery';
import { priceForm } from './price-form';
import { typeahead } from './typeahead';
import { ensureLocationInUrl } from './location';

const fallback = { lat: -37.8136, lng: 144.9631 };
window.Beercheck = { ensureLocationInUrl: () => ensureLocationInUrl(fallback) };

window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.data('geoDiscovery', geoDiscovery);
Alpine.data('priceForm', priceForm);
Alpine.data('typeahead', typeahead);
Alpine.start();
