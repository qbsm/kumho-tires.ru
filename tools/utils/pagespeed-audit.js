// PageSpeed аудит — вставить в консоль браузера (DevTools → Console)
// Показывает: Web Vitals, CLS-элементы, изображения без размеров,
// некэшированные ресурсы, блокирующие ресурсы, размеры бандлов

(function () {
  const log = (title, data) => {
    console.group('%c' + title, 'color:#e4002b;font-size:14px;font-weight:bold');
    if (Array.isArray(data)) {
      if (data.length === 0) console.log('✓ Нет проблем');
      else console.table(data);
    } else {
      console.log(data);
    }
    console.groupEnd();
  };

  // 1. Web Vitals
  const navEntry = performance.getEntriesByType('navigation')[0];
  const paintEntries = performance.getEntriesByType('paint');
  const fcp = paintEntries.find((e) => e.name === 'first-contentful-paint');

  log('Web Vitals (Navigation)', {
    'TTFB': Math.round(navEntry?.responseStart || 0) + ' ms',
    'FCP': Math.round(fcp?.startTime || 0) + ' ms',
    'DOM Interactive': Math.round(navEntry?.domInteractive || 0) + ' ms',
    'DOM Complete': Math.round(navEntry?.domComplete || 0) + ' ms',
    'Load': Math.round(navEntry?.loadEventEnd || 0) + ' ms',
    'Transfer Size': Math.round((navEntry?.transferSize || 0) / 1024) + ' KB',
  });

  // 2. CLS — элементы, вызывающие сдвиги
  const clsEntries = [];
  try {
    new PerformanceObserver((list) => {
      list.getEntries().forEach((entry) => {
        if (entry.value > 0.001) {
          const sources = entry.sources || [];
          sources.forEach((s) => {
            clsEntries.push({
              'Сдвиг': entry.value.toFixed(4),
              'Элемент': s.node ? s.node.outerHTML.slice(0, 120) : '?',
              'Селектор': s.node ? cssPath(s.node) : '?',
            });
          });
        }
      });
    }).observe({ type: 'layout-shift', buffered: true });
  } catch (e) {}
  setTimeout(() => log('CLS — смещения макета', clsEntries), 500);

  // 3. LCP элемент
  try {
    new PerformanceObserver((list) => {
      const entries = list.getEntries();
      const last = entries[entries.length - 1];
      log('LCP — Largest Contentful Paint', {
        'Время': Math.round(last.startTime) + ' ms',
        'Размер': last.size,
        'Элемент': last.element ? last.element.outerHTML.slice(0, 150) : '?',
        'URL': last.url || '—',
      });
    }).observe({ type: 'largest-contentful-paint', buffered: true });
  } catch (e) {}

  // 4. Изображения без width/height (вызывают CLS)
  const imgsNoSize = [];
  document.querySelectorAll('img').forEach((img) => {
    if (!img.getAttribute('width') || !img.getAttribute('height')) {
      imgsNoSize.push({
        'src': (img.src || '').split('/').slice(-2).join('/'),
        'class': img.className,
        'rendered': img.offsetWidth + 'x' + img.offsetHeight,
        'natural': img.naturalWidth + 'x' + img.naturalHeight,
      });
    }
  });
  log('Изображения без width/height', imgsNoSize);

  // 5. Oversized изображения (natural > 2x rendered)
  const oversized = [];
  document.querySelectorAll('img').forEach((img) => {
    if (img.naturalWidth > 0 && img.offsetWidth > 0) {
      const ratio = img.naturalWidth / img.offsetWidth;
      if (ratio > 2.2) {
        oversized.push({
          'src': (img.src || '').split('/').slice(-3).join('/'),
          'natural': img.naturalWidth + 'x' + img.naturalHeight,
          'rendered': img.offsetWidth + 'x' + img.offsetHeight,
          'ratio': ratio.toFixed(1) + 'x',
          'экономия': Math.round((1 - 1 / (ratio * ratio)) * 100) + '%',
        });
      }
    }
  });
  log('Oversized изображения (>2x)', oversized);

  // 6. Ресурсы — размеры и кэширование
  const resources = performance.getEntriesByType('resource');
  const byType = {};
  resources.forEach((r) => {
    const ext = (r.name.split('?')[0].split('.').pop() || '').toLowerCase();
    const type = { js: 'JS', css: 'CSS', woff2: 'Font', woff: 'Font', webp: 'Image', avif: 'Image', svg: 'Image', jpg: 'Image', png: 'Image' }[ext] || 'Other';
    if (!byType[type]) byType[type] = { count: 0, size: 0 };
    byType[type].count++;
    byType[type].size += r.transferSize || 0;
  });
  const summary = Object.entries(byType).map(([type, d]) => ({
    'Тип': type,
    'Файлов': d.count,
    'Размер': (d.size / 1024).toFixed(0) + ' KB',
  }));
  log('Ресурсы по типам', summary);

  // 7. Render-blocking ресурсы
  const blocking = resources
    .filter((r) => r.renderBlockingStatus === 'blocking')
    .map((r) => ({
      'URL': r.name.split('/').slice(-2).join('/'),
      'Размер': Math.round((r.transferSize || 0) / 1024) + ' KB',
      'Время': Math.round(r.duration) + ' ms',
    }));
  log('Render-blocking ресурсы', blocking);

  // 8. Долгие задачи (Long Tasks > 50ms)
  const longTasks = [];
  try {
    new PerformanceObserver((list) => {
      list.getEntries().forEach((entry) => {
        longTasks.push({
          'Длительность': Math.round(entry.duration) + ' ms',
          'Начало': Math.round(entry.startTime) + ' ms',
        });
      });
    }).observe({ type: 'longtask', buffered: true });
  } catch (e) {}
  setTimeout(() => {
    if (longTasks.length > 0) log('Long Tasks (>50ms)', longTasks);
  }, 1000);

  // 9. Шрифты
  const fonts = resources
    .filter((r) => /woff2?|ttf|otf/.test(r.name))
    .map((r) => ({
      'Файл': r.name.split('/').pop(),
      'Размер': Math.round((r.transferSize || 0) / 1024) + ' KB',
      'Время': Math.round(r.duration) + ' ms',
    }));
  log('Загруженные шрифты', fonts);

  // Утилита: CSS-путь элемента
  function cssPath(el) {
    if (!el || el === document.body) return 'body';
    const parts = [];
    while (el && el !== document.body) {
      let sel = el.tagName.toLowerCase();
      if (el.id) { sel += '#' + el.id; parts.unshift(sel); break; }
      if (el.className && typeof el.className === 'string') sel += '.' + el.className.trim().split(/\s+/).join('.');
      parts.unshift(sel);
      el = el.parentElement;
    }
    return parts.join(' > ');
  }

  console.log('%c✓ PageSpeed аудит завершён', 'color:green;font-size:12px');
})();
