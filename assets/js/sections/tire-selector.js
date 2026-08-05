import { onReady } from '../base/init.js';

const SIZE_KEYS = ['width', 'profile', 'diameter'];

const hasToken = (source, token) => (source || '').includes(`|${token}|`);

const parseSizes = (raw) =>
  (raw || '')
    .split('|')
    .filter(Boolean)
    .map((label) => {
      const parts = label.match(/^(\d+(?:\.\d+)?)\/(\d+(?:\.\d+)?)R(\d+)/);
      if (!parts) return null;
      return { width: parts[1], profile: parts[2], diameter: parts[3] };
    })
    .filter(Boolean);

const matchesSize = (sizes, selected) => {
  if (!SIZE_KEYS.some((key) => selected[key])) return true;
  return sizes.some((size) => SIZE_KEYS.every((key) => !selected[key] || size[key] === selected[key]));
};

const initSelector = (root) => {
  const stepEls = Array.from(root.querySelectorAll('.js-selector-step'));
  const resultEl = root.querySelector('.js-selector-result');
  const cardsEl = root.querySelector('.js-selector-cards');
  const emptyEl = root.querySelector('.js-selector-empty');
  const summaryEl = root.querySelector('.js-selector-summary');
  const backEls = Array.from(root.querySelectorAll('.js-selector-back'));
  const catalogEl = root.querySelector('.js-selector-catalog');
  const restartEl = root.querySelector('.js-selector-restart');
  const sizeSelects = Array.from(root.querySelectorAll('.js-selector-size'));
  const submitEl = root.querySelector('.js-selector-submit');

  if (!stepEls.length || !resultEl || !cardsEl) return;

  const limit = Number(root.dataset.limit) || 6;
  const catalogHref = root.dataset.catalogHref || '';

  const cards = Array.from(root.querySelectorAll('.js-selector-card')).map((el) => ({
    el,
    season: el.dataset.season || '|',
    vehicle: el.dataset.vehicle || '|',
    priority: el.dataset.priority || '|',
    sizes: parseSizes(el.dataset.sizes),
  }));

  const answers = { vehicle: [], season: [], priority: [] };
  const labels = {};
  const size = { width: '', profile: '', diameter: '' };
  let current = 0;

  const matchesGroup = (values, test) => values.length === 0 || values.some(test);

  const matchesAnswers = (card, options = {}) => {
    if (!matchesGroup(answers.vehicle, (value) => hasToken(card.vehicle, value))) return false;
    if (!matchesGroup(answers.season, (value) => hasToken(card.season, value))) return false;
    if (!options.ignoreSize && !matchesSize(card.sizes, size)) return false;
    return true;
  };

  const priorityScore = (card) => answers.priority.filter((value) => hasToken(card.priority, value)).length;

  const updateNextState = (step) => {
    const nextButton = step.querySelector('.js-selector-next');
    if (!nextButton) return;
    const chosen = step.querySelectorAll('.js-selector-option.active').length;
    nextButton.classList.toggle('disabled', chosen === 0);
    nextButton.disabled = chosen === 0;
  };

  const showStep = (index) => {
    current = index;
    stepEls.forEach((el, i) => el.classList.toggle('hidden', i !== index));
    resultEl.classList.toggle('hidden', index < stepEls.length);

    const header = document.querySelector('.header');
    const offset = header ? header.offsetHeight : 0;
    const top = root.getBoundingClientRect().top + window.pageYOffset - offset - 16;
    window.scrollTo({ top: Math.max(top, 0), behavior: 'smooth' });
  };

  const fillSelect = (select, values, selected) => {
    const placeholder = select.options[0] ? select.options[0].textContent : 'Любая';
    select.innerHTML = '';
    const anyOption = document.createElement('option');
    anyOption.value = '';
    anyOption.textContent = placeholder;
    select.appendChild(anyOption);
    values.forEach((value) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = value;
      select.appendChild(option);
    });
    select.value = values.includes(selected) ? selected : '';
  };

  const refreshSizeSelects = () => {
    const pool = cards.filter((card) => matchesAnswers(card, { ignoreSize: true }));
    const available = { width: new Set(), profile: new Set(), diameter: new Set() };

    pool.forEach((card) => {
      card.sizes.forEach((entry) => {
        SIZE_KEYS.forEach((key) => {
          const others = SIZE_KEYS.filter((other) => other !== key);
          if (others.every((other) => !size[other] || entry[other] === size[other])) {
            available[key].add(entry[key]);
          }
        });
      });
    });

    sizeSelects.forEach((select) => {
      const key = select.dataset.sizeKey;
      if (!available[key]) return;
      const values = Array.from(available[key]).sort((a, b) => Number(a) - Number(b));
      fillSelect(select, values, size[key]);
      size[key] = select.value;
    });
  };

  const buildSummary = () => {
    const parts = ['vehicle', 'season', 'priority'].map((key) => (labels[key] || []).join(', ')).filter(Boolean);
    const section = [size.width, size.profile].filter(Boolean).join('/');
    const diameter = size.diameter ? `R${size.diameter}` : '';
    const sizeLabel = [section, diameter].filter(Boolean).join(' ');
    if (sizeLabel) parts.push(sizeLabel);
    return parts.join(' · ');
  };

  // Каталог использует токен allseason, данные моделей — all-season
  const catalogSeason = (value) => (value === 'all-season' ? 'allseason' : value);

  const updateCatalogLink = () => {
    if (!catalogEl || !catalogHref) return;
    const params = new URLSearchParams();
    // Фильтр каталога принимает один сезон — передаём только при однозначном выборе
    if (answers.season.length === 1) params.set('season', catalogSeason(answers.season[0]));
    SIZE_KEYS.forEach((key) => {
      if (size[key]) params.set(key, size[key]);
    });
    const query = params.toString();
    catalogEl.href = query ? `${catalogHref}?${query}` : catalogHref;
  };

  const renderResult = () => {
    const matched = cards
      .filter((card) => matchesAnswers(card))
      .map((card) => ({ card, score: priorityScore(card) }))
      .sort((a, b) => b.score - a.score)
      .slice(0, limit);

    const visible = new Set(matched.map((entry) => entry.card.el));
    cards.forEach((card) => card.el.classList.toggle('hidden', !visible.has(card.el)));
    matched.forEach((entry, index) => {
      entry.card.el.style.order = String(index);
    });

    if (emptyEl) emptyEl.classList.toggle('hidden', matched.length > 0);
    if (summaryEl) summaryEl.textContent = buildSummary();
    updateCatalogLink();
  };

  const goNext = () => {
    const next = current + 1;
    if (next < stepEls.length && stepEls[next].dataset.type === 'size') refreshSizeSelects();
    if (next >= stepEls.length) renderResult();
    showStep(next);
  };

  root.querySelectorAll('.js-selector-option').forEach((button) => {
    button.addEventListener('click', () => {
      const step = button.closest('.js-selector-step');
      if (!step) return;
      const key = step.dataset.key;
      const multiple = step.dataset.multiple === '1';

      if (multiple) {
        button.classList.toggle('active');
      } else {
        step.querySelectorAll('.js-selector-option').forEach((item) => item.classList.remove('active'));
        button.classList.add('active');
      }

      const chosen = Array.from(step.querySelectorAll('.js-selector-option.active'));
      chosen.forEach((item) => item.setAttribute('aria-pressed', 'true'));
      step.querySelectorAll('.js-selector-option:not(.active)').forEach((item) => {
        item.setAttribute('aria-pressed', 'false');
      });

      answers[key] = chosen.map((item) => item.dataset.value || '');
      labels[key] = chosen.map((item) => item.dataset.label || '');
      SIZE_KEYS.forEach((sizeKey) => {
        size[sizeKey] = '';
      });

      if (multiple) {
        updateNextState(step);
      } else {
        goNext();
      }
    });
  });

  root.querySelectorAll('.js-selector-next').forEach((button) => {
    button.addEventListener('click', () => {
      if (button.classList.contains('disabled')) return;
      goNext();
    });
  });

  sizeSelects.forEach((select) => {
    select.addEventListener('change', () => {
      size[select.dataset.sizeKey] = select.value;
      refreshSizeSelects();
    });
  });

  if (submitEl) {
    submitEl.addEventListener('click', () => {
      renderResult();
      showStep(stepEls.length);
    });
  }

  backEls.forEach((button) => {
    button.addEventListener('click', () => {
      if (current === 0) return;
      showStep(current - 1);
    });
  });

  if (restartEl) {
    restartEl.addEventListener('click', () => {
      Object.keys(answers).forEach((key) => {
        answers[key] = [];
      });
      Object.keys(labels).forEach((key) => delete labels[key]);
      SIZE_KEYS.forEach((key) => {
        size[key] = '';
      });
      root.querySelectorAll('.js-selector-option').forEach((item) => {
        item.classList.remove('active');
        item.setAttribute('aria-pressed', 'false');
      });
      stepEls.forEach(updateNextState);
      showStep(0);
    });
  }

  stepEls.forEach(updateNextState);
};

onReady(() => {
  document.querySelectorAll('.js-tire-selector').forEach(initSelector);
});
