import { onReady } from '../base/init.js';

const ymId = window.appConfig && window.appConfig.YANDEX_METRIC_ID;

function reachGoal(goal, params) {
  if (!ymId || typeof window.ym !== 'function') return;

  if (params) {
    window.ym(ymId, 'reachGoal', goal, params);
  } else {
    window.ym(ymId, 'reachGoal', goal);
  }
}

function sourceOf(element) {
  if (!element || !element.closest) return 'page';
  if (element.closest('.card-dealer')) return 'dealer';
  if (element.closest('.header')) return 'header';
  if (element.closest('.footer')) return 'footer';
  if (element.closest('.contacts')) return 'contacts';

  return 'page';
}

onReady(function () {
  if (!ymId) return;

  if (document.querySelector('.error')) {
    reachGoal('page_404', { path: window.location.pathname + window.location.search });
  }

  document.addEventListener('click', function (event) {
    const link = event.target.closest && event.target.closest('a[href]');
    if (!link) return;

    const href = link.getAttribute('href') || '';

    if (href.indexOf('tel:') === 0) {
      reachGoal('phone_click', { source: sourceOf(link) });
      return;
    }

    if (href.indexOf('mailto:') === 0) {
      reachGoal('email_click', { source: sourceOf(link) });
      return;
    }

    if (link.closest('.card-dealer') && /^https?:/i.test(href)) {
      reachGoal('dealer_site');
      return;
    }

    if (/\.pdf(\?|$)/i.test(href)) {
      reachGoal('doc_download');
    }
  });

  document.addEventListener('form-callback:success', function (event) {
    reachGoal('lead_form', { source: sourceOf(event.target) });
  });

  let filterUsed = false;
  let selectorStarted = false;
  let selectorSizeUsed = false;

  document.addEventListener('change', function (event) {
    if (!event.target.closest) return;

    if (!filterUsed && event.target.closest('.filter')) {
      filterUsed = true;
      reachGoal('catalog_filter');
      return;
    }

    if (!selectorSizeUsed && event.target.closest('.js-selector-size')) {
      selectorSizeUsed = true;
      reachGoal('podbor_size');
    }
  });

  // Конфигуратор подбора: воронка от первого выбора до перехода в каталог.
  // Слушаем здесь, а не в секции, чтобы все цели сайта лежали в одном месте.
  document.addEventListener('click', function (event) {
    const target = event.target;
    if (!target || !target.closest) return;

    const selector = target.closest('.js-tire-selector');

    if (selector) {
      const page = selector.closest('.tire-selector--plate') ? 'index' : 'podbor';

      if (target.closest('.js-selector-option')) {
        if (!selectorStarted) {
          selectorStarted = true;
          reachGoal('podbor_start', { page: page });
        }
      } else if (target.closest('.js-selector-next')) {
        const step = target.closest('.js-selector-step');
        reachGoal('podbor_step', { step: (step && step.dataset.key) || 'unknown' });
      } else if (target.closest('.js-selector-submit')) {
        reachGoal('podbor_result', { page: page });
      } else if (target.closest('.js-selector-catalog')) {
        reachGoal('podbor_catalog');
      } else if (target.closest('.js-selector-restart')) {
        reachGoal('podbor_restart');
      } else if (target.closest('.js-selector-card')) {
        const title = target.closest('.js-selector-card').querySelector('.card__title');
        reachGoal('podbor_model', { model: title ? title.textContent.trim() : '' });
      }

      return;
    }

    // Страница модели: переход к дилерам и клик по похожей модели
    if (target.closest('.tire-detail__actions a')) {
      reachGoal('tire_buy');
      return;
    }

    if (target.closest('.tire-detail .tires .card-tire')) {
      const title = target.closest('.card-tire').querySelector('.card__title');
      reachGoal('tire_related', { model: title ? title.textContent.trim() : '' });
    }
  });
});
