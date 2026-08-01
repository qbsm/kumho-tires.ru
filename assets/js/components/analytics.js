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

  document.addEventListener('change', function (event) {
    if (filterUsed) return;
    if (!event.target.closest || !event.target.closest('.filter')) return;

    filterUsed = true;
    reachGoal('catalog_filter');
  });
});
