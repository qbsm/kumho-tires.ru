import { onReady } from '../base/init.js';

onReady(() => {
  const root = document.querySelector('.tires');
  if (!root) return;

  const includesToken = (source, token) => {
    if (!token) return true;
    return (source || '').includes(`|${token}|`);
  };

  const parseSizes = (sizesStr) => {
    return (sizesStr || '')
      .split('|')
      .filter(Boolean)
      .map((label) => {
        const m = label.match(/^(\d+(?:\.\d+)?)\/(\d+(?:\.\d+)?)R(\d+)/);
        if (!m) return null;
        return { width: m[1], profile: m[2], diameter: m[3] };
      })
      .filter(Boolean);
  };

  const matchesSizes = (sizes, width, profile, diameter) => {
    if (!width && !profile && !diameter) return true;
    return sizes.some((s) => {
      if (width && s.width !== width) return false;
      if (profile && s.profile !== profile) return false;
      if (diameter && s.diameter !== diameter) return false;
      return true;
    });
  };

  const updateSelectOptions = (select, availableValues, currentValue) => {
    if (!select) return;
    const options = Array.from(select.options);
    options.forEach((opt) => {
      if (!opt.value) return; // skip "Все"
      const available = availableValues.has(opt.value);
      opt.disabled = !available;
      opt.style.display = available ? '' : 'none';
    });
    // If current value is no longer available, reset
    if (currentValue && !availableValues.has(currentValue)) {
      select.value = '';
    }
  };

  /* Подмена списка на месте: разделы каталога остаются отдельными страницами со своим адресом,
     заголовком и серверной фильтрацией, но при переходе меняются только секции каталога и
     лонгрида, без полной перезагрузки. Без JS и при любой ошибке запроса работает обычный
     переход по ссылке — разметка для этого не меняется. */
  const supportsSwap =
    typeof window.fetch === 'function' &&
    typeof window.DOMParser === 'function' &&
    !!window.history &&
    typeof window.history.pushState === 'function';

  const documentCache = new Map();

  const loadDocument = (url) => {
    if (documentCache.has(url)) return documentCache.get(url);
    const request = fetch(url, { credentials: 'same-origin' })
      .then((response) => {
        if (!response.ok) throw new Error(String(response.status));
        return response.text();
      })
      .catch((error) => {
        documentCache.delete(url);
        throw error;
      });
    documentCache.set(url, request);
    return request;
  };

  const copyHeadValue = (nextDoc, selector, attribute) => {
    const current = document.head.querySelector(selector);
    const next = nextDoc.head.querySelector(selector);
    if (current && next) current.setAttribute(attribute, next.getAttribute(attribute) || '');
  };

  let swapToken = null;

  const swap = (url, push) => {
    const token = {};
    swapToken = token;
    root.classList.add('is-swapping');
    root.setAttribute('aria-busy', 'true');

    return loadDocument(url)
      .then((html) => {
        if (swapToken !== token) return;
        const nextDoc = new DOMParser().parseFromString(html, 'text/html');
        const nextTires = nextDoc.querySelector('.tires');
        if (!nextTires) throw new Error('no-section');

        root.innerHTML = nextTires.innerHTML;
        ['data-catalog-path', 'data-catalog-root'].forEach((name) => {
          const value = nextTires.getAttribute(name);
          if (value === null) root.removeAttribute(name);
          else root.setAttribute(name, value);
        });

        // Лонгрид под каталогом свой у каждого раздела — иначе остался бы текст прежнего
        const currentContent = document.querySelector('.content-container');
        const nextContent = nextDoc.querySelector('.content-container');
        if (currentContent && nextContent) currentContent.innerHTML = nextContent.innerHTML;

        document.title = nextDoc.title;
        copyHeadValue(nextDoc, 'link[rel="canonical"]', 'href');
        copyHeadValue(nextDoc, 'meta[name="description"]', 'content');

        if (push) window.history.pushState({}, '', url);
        bind();

        // Ссылки на размеры стоят под списком: после подмены фильтр должен остаться на виду
        if (root.getBoundingClientRect().top < 0) {
          root.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      })
      .catch(() => {
        window.location.assign(url);
      })
      .finally(() => {
        if (swapToken !== token) return;
        root.classList.remove('is-swapping');
        root.removeAttribute('aria-busy');
      });
  };

  const go = (url) => {
    if (!url) return;
    const target = new URL(url, window.location.href);
    if (target.href === window.location.href) return;
    if (!supportsSwap || target.origin !== window.location.origin) {
      window.location.assign(target.href);
      return;
    }
    swap(target.pathname + target.search, true);
  };

  const prefetch = (url) => {
    if (!supportsSwap || !url) return;
    const target = new URL(url, window.location.href);
    if (target.origin !== window.location.origin) return;
    loadDocument(target.pathname + target.search).catch(() => {});
  };

  const bind = () => {
    const cards = Array.from(root.querySelectorAll('.js-filter-card'));
    const seasonButtons = Array.from(root.querySelectorAll('.js-select-season'));
    const diameterSelect = root.querySelector('.js-select-diameter');
    const profileSelect = root.querySelector('.js-select-profile');
    const widthSelect = root.querySelector('.js-select-width');
    const emptyState = root.querySelector('.js-filter-empty');

    const cardData = cards.map((card) => ({
      el: card,
      season: card.dataset.season || '|',
      sizes: parseSizes(card.dataset.sizes),
    }));

    const applyFilter = () => {
      const activeSeasonBtn = seasonButtons.find((btn) => btn.classList.contains('active'));
      const season = activeSeasonBtn ? activeSeasonBtn.dataset.season : '';
      const diameter = diameterSelect ? diameterSelect.value : '';
      const profile = profileSelect ? profileSelect.value : '';
      const width = widthSelect ? widthSelect.value : '';

      let visibleCount = 0;

      cardData.forEach((cd) => {
        let visible = true;
        if (season && !includesToken(cd.season, season)) visible = false;
        if (visible && (width || profile || diameter)) {
          visible = matchesSizes(cd.sizes, width, profile, diameter);
        }
        cd.el.classList.toggle('hidden', !visible);
        cd.visible = visible;
        if (visible) visibleCount += 1;
      });

      if (emptyState) {
        emptyState.classList.toggle('hidden', visibleCount > 0);
      }

      const seasonMatchCards = cardData.filter((cd) => !season || includesToken(cd.season, season));

      const availDiameters = new Set();
      const availProfiles = new Set();
      const availWidths = new Set();

      seasonMatchCards.forEach((cd) => {
        cd.sizes.forEach((s) => {
          const matchW = !width || s.width === width;
          const matchP = !profile || s.profile === profile;
          const matchD = !diameter || s.diameter === diameter;

          if (matchW && matchP) availDiameters.add(s.diameter);
          if (matchW && matchD) availProfiles.add(s.profile);
          if (matchP && matchD) availWidths.add(s.width);
        });
      });

      updateSelectOptions(diameterSelect, availDiameters, diameter);
      updateSelectOptions(profileSelect, availProfiles, profile);
      updateSelectOptions(widthSelect, availWidths, width);
    };

    const catalogBase = (root.dataset.catalogPath || '').replace(/\/$/, '');
    const catalogRoot = (root.dataset.catalogRoot || '').replace(/\/$/, '');

    // Сезонная кнопка без ссылки (витрина на главной) фильтрует на месте и правит адрес.
    const syncSeasonUrl = (season) => {
      const base = (root.dataset.catalogPath || window.location.pathname).replace(
        /\/(summer|allseason|winter|studded|friction)$/,
        ''
      );
      const next = season ? `${base.replace(/\/$/, '')}/${season}` : base.replace(/\/$/, '') || '/';
      if (window.history && typeof window.history.pushState === 'function' && next !== window.location.pathname) {
        window.history.pushState({}, '', next + window.location.search);
      }
    };

    seasonButtons.forEach((btn) => {
      btn.addEventListener('click', (event) => {
        if (btn.disabled || btn.classList.contains('disabled')) {
          event.preventDefault();
          return;
        }
        // Сезонный раздел — отдельная страница: список моделей режет сервер. Повторный клик по
        // активному сезону снимает выборку и возвращает к текущему размеру или в общий каталог.
        if (btn.tagName === 'A' && btn.getAttribute('href')) {
          event.preventDefault();
          go(btn.classList.contains('active') && catalogBase ? catalogBase : btn.href);
          return;
        }
        const wasActive = btn.classList.contains('active');
        seasonButtons.forEach((item) => item.classList.remove('active'));
        if (!wasActive) btn.classList.add('active');
        // Reset selects when changing season
        if (diameterSelect) diameterSelect.value = '';
        if (profileSelect) profileSelect.value = '';
        if (widthSelect) widthSelect.value = '';
        applyFilter();
        syncSeasonUrl(wasActive ? '' : btn.dataset.season);
      });
      if (btn.tagName === 'A') {
        btn.addEventListener('mouseenter', () => prefetch(btn.href));
      }
    });

    // Полный типоразмер — отдельная страница: список режет сервер, поэтому при выборе всех трёх
    // значений переходим на неё. Частичный выбор фильтрует на месте и адрес не трогает.
    const syncSizeUrl = () => {
      if (!catalogRoot) return false;
      const width = widthSelect ? widthSelect.value : '';
      const profile = profileSelect ? profileSelect.value : '';
      const diameter = diameterSelect ? diameterSelect.value : '';
      const target = width && profile && diameter ? `${catalogRoot}/${width}-${profile}-r${diameter}` : catalogRoot;
      if (target === window.location.pathname) return false;
      go(target);
      return true;
    };

    [diameterSelect, profileSelect, widthSelect].forEach((select) => {
      if (!select) return;
      select.addEventListener('change', () => {
        if (syncSizeUrl()) return;
        applyFilter();
      });
    });

    root.querySelectorAll('.tires__sizes-link').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        go(link.href);
      });
      link.addEventListener('mouseenter', () => prefetch(link.href));
      link.addEventListener('focus', () => prefetch(link.href));
    });

    // Пресет фильтра: человечный адрес (/tires/summer/205-55-r16) или query-параметры
    const presetEl = root.querySelector('.js-filter-preset');
    const presetParams = new URLSearchParams(window.location.search);
    const presetValue = (key) => (presetEl && presetEl.dataset[key]) || presetParams.get(key) || '';
    const presetSeason = presetValue('season');
    if (presetSeason) {
      const presetButton = seasonButtons.find((btn) => btn.dataset.season === presetSeason);
      if (presetButton && !presetButton.disabled && !presetButton.classList.contains('disabled')) {
        presetButton.classList.add('active');
      }
    }

    [
      [widthSelect, 'width'],
      [profileSelect, 'profile'],
      [diameterSelect, 'diameter'],
    ].forEach(([select, param]) => {
      const value = presetValue(param);
      if (!select || !value) return;
      if (Array.from(select.options).some((option) => option.value === value)) {
        select.value = value;
      }
    });

    // Initial run to set available options
    applyFilter();
  };

  if (supportsSwap) {
    window.addEventListener('popstate', () => {
      swap(window.location.pathname + window.location.search, false);
    });
  }

  bind();
});
