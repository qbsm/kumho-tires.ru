import { onReady } from '../base/init.js';

onReady(() => {
  const root = document.querySelector('.tires');
  if (!root) return;

  const cards = Array.from(root.querySelectorAll('.js-filter-card'));
  const seasonButtons = Array.from(root.querySelectorAll('.js-select-season'));
  const diameterSelect = root.querySelector('.js-select-diameter');
  const profileSelect = root.querySelector('.js-select-profile');
  const widthSelect = root.querySelector('.js-select-width');
  const emptyState = root.querySelector('.js-filter-empty');

  const includesToken = (source, token) => {
    if (!token) return true;
    return (source || '').includes(`|${token}|`);
  };

  const matchesSizes = (sizesStr, width, profile, diameter) => {
    if (!width && !profile && !diameter) return true;
    const sizes = (sizesStr || '').split('|').filter(Boolean);
    return sizes.some((label) => {
      const m = label.match(/^(\d+)\/(\d+)R(\d+)/);
      if (!m) return false;
      if (width && m[1] !== width) return false;
      if (profile && m[2] !== profile) return false;
      if (diameter && m[3] !== diameter) return false;
      return true;
    });
  };

  const applyFilter = () => {
    const activeSeasonBtn = seasonButtons.find((btn) => btn.classList.contains('active'));
    const season = activeSeasonBtn ? activeSeasonBtn.dataset.season : '';
    const diameter = diameterSelect ? diameterSelect.value : '';
    const profile = profileSelect ? profileSelect.value : '';
    const width = widthSelect ? widthSelect.value : '';

    let visibleCount = 0;

    cards.forEach((card) => {
      let visible = true;
      const cardSeason = card.dataset.season || '|';

      if (season && !includesToken(cardSeason, season)) visible = false;
      if (visible && (width || profile || diameter)) {
        visible = matchesSizes(card.dataset.sizes || '', width, profile, diameter);
      }

      card.classList.toggle('hidden', !visible);
      if (visible) visibleCount += 1;
    });

    if (emptyState) {
      emptyState.classList.toggle('hidden', visibleCount > 0);
    }
  };

  seasonButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      if (btn.disabled || btn.classList.contains('disabled')) return;
      const wasActive = btn.classList.contains('active');
      seasonButtons.forEach((item) => item.classList.remove('active'));
      if (!wasActive) btn.classList.add('active');
      applyFilter();
    });
  });

  [diameterSelect, profileSelect, widthSelect].forEach((select) => {
    if (!select) return;
    select.addEventListener('change', applyFilter);
  });
});
