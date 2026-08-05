import { onReady } from '../base/init.js';

const SIZE_KEYS = ['width', 'profile', 'diameter'];

const hasToken = (source, token) => (source || '').includes(`|${token}|`);

const matchesSeason = (source, value) => {
  if (!value) return true;
  if (value === 'friction') return hasToken(source, 'winter') && !hasToken(source, 'studded');
  return hasToken(source, value);
};

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

onReady(() => {
  const root = document.querySelector('.js-tire-selector');
  if (!root) return;

  const stepEls = Array.from(root.querySelectorAll('.js-selector-step'));
  const resultEl = root.querySelector('.js-selector-result');
  const cardsEl = root.querySelector('.js-selector-cards');
  const emptyEl = root.querySelector('.js-selector-empty');
  const summaryEl = root.querySelector('.js-selector-summary');
  const progressEl = root.querySelector('.js-selector-progress');
  const counterEl = root.querySelector('.js-selector-counter');
  const backEl = root.querySelector('.js-selector-back');
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

  const answers = {};
  const labels = {};
  const size = { width: '', profile: '', diameter: '' };
  let current = 0;

  const matchesAnswers = (card, options = {}) => {
    if (answers.vehicle && !hasToken(card.vehicle, answers.vehicle)) return false;
    if (answers.season && !matchesSeason(card.season, answers.season)) return false;
    if (!options.ignoreSize && !matchesSize(card.sizes, size)) return false;
    return true;
  };

  const updateProgress = () => {
    const total = stepEls.length;
    const isResult = current >= total;
    const percent = isResult ? 100 : Math.round(((current + 1) / total) * 100);
    if (progressEl) progressEl.style.width = `${percent}%`;
    if (counterEl) {
      counterEl.textContent = isResult ? 'Подбор готов' : `Шаг ${current + 1} из ${total}`;
    }
    if (backEl) backEl.classList.toggle('hidden', current === 0);
  };

  const showStep = (index) => {
    current = index;
    stepEls.forEach((el, i) => el.classList.toggle('hidden', i !== index));
    resultEl.classList.toggle('hidden', index < stepEls.length);
    updateProgress();

    const target = index < stepEls.length ? stepEls[index] : resultEl;
    const header = document.querySelector('.header');
    const offset = header ? header.offsetHeight : 0;
    const top = root.getBoundingClientRect().top + window.pageYOffset - offset - 16;
    window.scrollTo({ top: Math.max(top, 0), behavior: 'smooth' });
    const focusable = target.querySelector('.js-selector-option, .js-selector-size');
    if (focusable) focusable.focus({ preventScroll: true });
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
    const parts = Object.keys(labels)
      .map((key) => labels[key])
      .filter(Boolean);
    const section = [size.width, size.profile].filter(Boolean).join('/');
    const diameter = size.diameter ? `R${size.diameter}` : '';
    const sizeLabel = [section, diameter].filter(Boolean).join(' ');
    if (sizeLabel) parts.push(sizeLabel);
    return parts.join(' · ');
  };

  const catalogSeason = (value) => {
    if (value === 'friction') return 'winter';
    if (value === 'all-season') return 'allseason';
    return value;
  };

  const updateCatalogLink = () => {
    if (!catalogEl || !catalogHref) return;
    const params = new URLSearchParams();
    if (answers.season) params.set('season', catalogSeason(answers.season));
    SIZE_KEYS.forEach((key) => {
      if (size[key]) params.set(key, size[key]);
    });
    const query = params.toString();
    catalogEl.href = query ? `${catalogHref}?${query}` : catalogHref;
  };

  const renderResult = () => {
    const matched = cards
      .filter((card) => matchesAnswers(card))
      .map((card) => ({
        card,
        score: answers.priority && hasToken(card.priority, answers.priority) ? 1 : 0,
      }))
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
      step.querySelectorAll('.js-selector-option').forEach((item) => item.classList.remove('active'));
      button.classList.add('active');
      answers[key] = button.dataset.value || '';
      labels[key] = button.dataset.label || '';
      SIZE_KEYS.forEach((sizeKey) => {
        size[sizeKey] = '';
      });
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

  if (backEl) {
    backEl.addEventListener('click', () => {
      if (current === 0) return;
      showStep(current - 1);
    });
  }

  if (restartEl) {
    restartEl.addEventListener('click', () => {
      Object.keys(answers).forEach((key) => delete answers[key]);
      Object.keys(labels).forEach((key) => delete labels[key]);
      SIZE_KEYS.forEach((key) => {
        size[key] = '';
      });
      root.querySelectorAll('.js-selector-option').forEach((item) => item.classList.remove('active'));
      showStep(0);
    });
  }

  updateProgress();
});
