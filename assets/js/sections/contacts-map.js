import { onReady } from '../base/init.js';
import { loadYandexMaps } from './dealers.js';

// Карта на странице контактов: одна точка с фирменной SVG-меткой.
// Тот же JS API, что у карты дилеров, — виджет-iframe своей метки не поддерживает.
onReady(() => {
  const el = document.querySelector('.js-contacts-map');
  if (!el) return;

  const coords = (el.dataset.location || '')
    .split(',')
    .map((part) => Number(part.trim()))
    .filter((value) => !Number.isNaN(value));
  if (coords.length !== 2) return;

  const zoom = Number(el.dataset.zoom) || 16;
  const icon = el.dataset.icon || '';

  loadYandexMaps()
    .then((ymaps) => {
      ymaps.ready(() => {
        const map = new ymaps.Map(
          el,
          { center: coords, zoom, controls: ['zoomControl'] },
          { suppressMapOpenBlock: true }
        );
        // Колесо прокручивает страницу, а не масштабирует карту
        map.behaviors.disable('scrollZoom');

        const placemark = new ymaps.Placemark(
          coords,
          { hintContent: el.dataset.hint || '' },
          icon
            ? {
                iconLayout: 'default#image',
                iconImageHref: icon,
                iconImageSize: [48, 60],
                iconImageOffset: [-24, -60],
              }
            : { preset: 'islands#redIcon' }
        );
        map.geoObjects.add(placemark);
      });
    })
    .catch(() => {});
});
