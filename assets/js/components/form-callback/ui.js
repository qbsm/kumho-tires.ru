import { DEFAULT_ERROR_TEXTS, SUCCESS_RESTORE_DELAY_MS } from './constants.js';

export class FormUI {
  constructor(formElement, i18n) {
    this.form = formElement;
    this.i18n = i18n;
    this.submitButton = formElement.querySelector('button[type="submit"]');
    this.errorContainer = this._resolveErrorContainer();
    this.errorMessageNode = this.errorContainer ? this.errorContainer.querySelector('span') : null;
    this.errorId = this.errorMessageNode ? this.errorMessageNode.id : '';
    this.originalButtonNodes = this.submitButton ? Array.from(this.submitButton.childNodes).map((node) => node.cloneNode(true)) : [];
    this.restoreTimer = null;
    const lang = i18n.lang || 'ru';
    this.defaults = DEFAULT_ERROR_TEXTS[lang] || DEFAULT_ERROR_TEXTS.ru;
  }

  setLoadingState() {
    if (!this.submitButton) {
      return;
    }

    this._clearRestoreTimer();
    this.submitButton.disabled = true;
    this.submitButton.classList.add('loading');
    this.submitButton.classList.add('no-pseudo-icon');
    this._setButtonText(this.i18n.get('error', 'sending', this.defaults.sending));
  }

  setSuccessState() {
    if (!this.submitButton) {
      return;
    }

    this.submitButton.disabled = false;
    this.submitButton.classList.remove('loading');
    this.submitButton.classList.add('no-pseudo-icon');
    this._setButtonText(this.i18n.get('error', 'success', this.defaults.success));

    this._clearRestoreTimer();
    this.restoreTimer = window.setTimeout(() => {
      this.restoreButton();
    }, SUCCESS_RESTORE_DELAY_MS);
  }

  setErrorState() {
    this.restoreButton();
  }

  restoreButton() {
    if (!this.submitButton) {
      return;
    }

    this._clearRestoreTimer();
    this.submitButton.disabled = false;
    this.submitButton.classList.remove('loading');
    this.submitButton.classList.remove('no-pseudo-icon');
    this.submitButton.textContent = '';
    this.originalButtonNodes.forEach((node) => {
      this.submitButton.appendChild(node.cloneNode(true));
    });
  }

  markFieldAsError(field) {
    const container = field.closest('.form-callback__item') || field;
    container.classList.add('error');

    if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
      field.setAttribute('aria-invalid', 'true');
      if (this.errorId) {
        field.setAttribute('aria-describedby', this.errorId);
      }
    }
  }

  clearFieldError(field) {
    const container = field.closest('.form-callback__item') || field;
    container.classList.remove('error');

    if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
      field.removeAttribute('aria-invalid');
      if (this.errorId && field.getAttribute('aria-describedby') === this.errorId) {
        field.removeAttribute('aria-describedby');
      }
    }

    const hasErrors = this.form.querySelector('.form-callback__item.error, input.error, textarea.error, select.error');
    if (!hasErrors && this.errorContainer) {
      this.errorContainer.classList.add('hidden');
      if (this.errorMessageNode) {
        this.errorMessageNode.textContent = '';
      }
    }
  }

  showFormError(message) {
    if (!this.errorContainer || !this.errorMessageNode) {
      return;
    }

    this.errorMessageNode.textContent = message;
    this.errorContainer.classList.remove('hidden');
    this.errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  clearErrors() {
    this.form
      .querySelectorAll('.form-callback__item.error, input.error, textarea.error, select.error')
      .forEach((element) => element.classList.remove('error'));

    this.form.querySelectorAll('[aria-invalid="true"]').forEach((element) => {
      element.removeAttribute('aria-invalid');
      if (this.errorId && element.getAttribute('aria-describedby') === this.errorId) {
        element.removeAttribute('aria-describedby');
      }
    });

    if (this.errorContainer) {
      this.errorContainer.classList.add('hidden');
    }
    if (this.errorMessageNode) {
      this.errorMessageNode.textContent = '';
    }
  }

  _resolveErrorContainer() {
    let container = this.form.querySelector('.form-callback__item.form-callback__error');
    if (container) {
      return container;
    }

    container = document.createElement('div');
    container.className = 'form-callback__item form-callback__error hidden';
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-atomic', 'true');
    const span = document.createElement('span');
    span.className = 'form-callback__error-text';
    span.setAttribute('role', 'alert');
    span.setAttribute('aria-live', 'assertive');
    container.appendChild(span);

    const formContentWrapper = this.form.querySelector('.form-callback__container') || this.form;
    const submitButton = formContentWrapper.querySelector('button[type="submit"]');
    if (submitButton && submitButton.parentNode !== formContentWrapper) {
      submitButton.parentNode.insertBefore(container, submitButton);
    } else {
      formContentWrapper.appendChild(container);
    }

    return container;
  }

  _setButtonText(text) {
    if (!this.submitButton) {
      return;
    }
    this.submitButton.textContent = text;
  }

  _clearRestoreTimer() {
    if (this.restoreTimer) {
      clearTimeout(this.restoreTimer);
      this.restoreTimer = null;
    }
  }
}
