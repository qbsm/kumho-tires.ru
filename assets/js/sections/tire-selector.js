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

    // Отступ под липкую шапку задан в CSS (scroll-margin-top), браузер учитывает его сам:
    // ручной расчёт промахивался, потому что шапка в момент клика ещё едет.
    root.scrollIntoView({ behavior: 'smooth', block: 'start' });
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

  // Выбранные ответы показываем чипами в языке фильтра каталога: перечисление через
  // запятую читалось как случайный набор слов, а «155» без подписи вообще ничего не значило.
  const sizeChips = () => {
    const chips = [];
    if (size.width && size.profile && size.diameter) {
      chips.push({ label: `${size.width}/${size.profile} R${size.diameter}` });
      return chips;
    }
    if (size.width) chips.push({ label: `Ширина ${size.width}` });
    if (size.profile) chips.push({ label: `Профиль ${size.profile}` });
    if (size.diameter) chips.push({ label: `Диаметр R${size.diameter}` });
    return chips;
  };

  const answerChips = () => {
    const chips = ['vehicle', 'season', 'priority'].flatMap((key) => labels[key] || []);
    return chips.concat(sizeChips());
  };

  const renderSummary = () => {
    if (!summaryEl) return;
    summaryEl.innerHTML = '';
    answerChips().forEach((chip) => {
      const el = document.createElement('span');
      el.className = 'tire-selector__chip';
      if (chip.icon) {
        const icon = document.createElement('span');
        icon.className = 'tire-selector__chip-icon';
        icon.style.setProperty('--tire-selector-icon', `url('${chip.icon}')`);
        icon.setAttribute('aria-hidden', 'true');
        el.appendChild(icon);
      }
      el.appendChild(document.createTextNode(chip.label));
      summaryEl.appendChild(el);
    });
  };

  // Каталог использует токен allseason, данные моделей — all-season
  const catalogSeason = (value) => (value === 'all-season' ? 'allseason' : value);

  const updateCatalogLink = () => {
    if (!catalogEl || !catalogHref) return;
    const base = catalogHref.replace(/\/$/, '');
    // Фильтр каталога принимает один сезон — передаём только при однозначном выборе
    const season = answers.season.length === 1 ? catalogSeason(answers.season[0]) : '';
    const full = SIZE_KEYS.every((key) => size[key]);
    const segments = [];
    if (season) segments.push(season);
    if (full) segments.push(`${size.width}-${size.profile}-r${size.diameter}`);

    // Человечный адрес доступен для сезона и полного типоразмера, частичный размер — параметрами
    if (segments.length > 0 && (full || SIZE_KEYS.every((key) => !size[key]))) {
      catalogEl.href = `${base}/${segments.join('/')}`;
      return;
    }

    const params = new URLSearchParams();
    if (season) params.set('season', season);
    SIZE_KEYS.forEach((key) => {
      if (size[key]) params.set(key, size[key]);
    });
    const query = params.toString();
    catalogEl.href = query ? `${base}?${query}` : base;
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

    if (emptyEl) {
      const isEmpty = matched.length === 0;
      const note = emptyEl.querySelector('.js-selector-empty-note');
      if (isEmpty && note) {
        emptyEl.appendChild(note.content.cloneNode(true));
        note.remove();
      }
      emptyEl.classList.toggle('hidden', !isEmpty);
    }
    renderSummary();
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
      labels[key] = chosen.map((item) => ({ label: item.dataset.label || '', icon: item.dataset.icon || '' }));
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
