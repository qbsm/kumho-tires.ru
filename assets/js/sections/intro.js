// JavaScript для intro
import { onReady } from '../base/init.js';

onReady(() => {
  initIntroSlider();
});

function initIntroSlider() {
  // Проверяем, доступен ли Swiper в глобальном контексте
  if (typeof window.Swiper === 'undefined') {
    console.warn('Swiper не доступен для слайдера introSlider. Пробуем инициализировать позже...');
    // Пробуем снова через 200 мс
    setTimeout(initIntroSlider, 200);
    return;
  }

  const sliderElement = document.getElementById('introSlider') || document.getElementById('projectSlider');

  if (sliderElement && !sliderElement.swiperInstance) {
    try {
      // Получение настроек из data-атрибута
      let settings = {};
      try {
        const dataSettings = sliderElement.getAttribute('data-settings');
        if (dataSettings) {
          settings = JSON.parse(dataSettings);
        }
      } catch (error) {
        console.error('Ошибка при парсинге настроек introSlider:', error);
      }

      // Создаем базовые настройки
      const swiperOptions = {
        direction: 'horizontal',
        loop: settings.loop !== undefined ? settings.loop : true,
        allowTouchMove: settings.allowTouchMove !== undefined ? settings.allowTouchMove : true,
        slidesPerView: 1,
        spaceBetween: 0,
        effect: 'fade',
        fadeEffect: {
          crossFade: true,
        },
        speed: 800,
        autoplay:
          settings.autoplay !== false
            ? {
                delay: 5000,
                disableOnInteraction: false,
              }
            : false,
        pagination: {
          el: '.swiper-pagination',
          clickable: true,
        },
        navigation: {
          nextEl: '.swiper-button-next',
          prevEl: '.swiper-button-prev',
        },
      };

      // Объединяем с настройками из data-атрибута
      if (settings.pagination) {
        if (settings.pagination.enabled) {
          swiperOptions.pagination = {
            el: '.swiper-pagination',
            clickable: true,
            ...settings.pagination,
          };
        } else {
          delete swiperOptions.pagination;
        }
      }

      if (settings.navigation) {
        if (settings.navigation.enabled) {
          swiperOptions.navigation = {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
            ...settings.navigation,
          };
        } else {
          delete swiperOptions.navigation;
        }
      }

      if (settings.autoplay) {
        swiperOptions.autoplay = settings.autoplay;
      }

      if (settings.effect) {
        swiperOptions.effect = settings.effect;
      }

      if (settings.breakpoints) {
        swiperOptions.breakpoints = settings.breakpoints;
      }

      const introSlider = new window.Swiper(sliderElement, swiperOptions);
      // Сохраняем экземпляр слайдера
      sliderElement.swiperInstance = introSlider;
    } catch (error) {
      console.error('Ошибка при инициализации introSlider в секции intro:', error);
    }
  } else if (!sliderElement) {
    console.warn('Элемент слайдера #introSlider не найден на странице');
  }
}
