import { DEFAULT_ERROR_TEXTS, MIN_PHONE_LENGTH_AFTER_CODE } from './constants.js';

export function normalizePhone(value) {
  return String(value || '').replace(/\D/g, '');
}

export function isRequired(value) {
  return String(value || '').trim().length > 0;
}

export function isMinLength(value, minLength) {
  return String(value || '').trim().length >= minLength;
}

export function isValidEmail(value) {
  if (!isRequired(value)) {
    return true;
  }
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value));
}

export function isValidPhone(digits, countryCodeDigits = '') {
  if (!digits || digits.length <= 1) {
    return false;
  }
  if (countryCodeDigits) {
    return digits.length >= countryCodeDigits.length + MIN_PHONE_LENGTH_AFTER_CODE;
  }
  return digits.length >= 7;
}

export function isFieldVisible(field) {
  if (!field) {
    return false;
  }

  const fieldItem = field.closest('.form-callback__item');
  if (!fieldItem) {
    return true;
  }

  const styles = window.getComputedStyle(fieldItem);
  if (styles.display === 'none') {
    return false;
  }
  if (styles.visibility === 'hidden') {
    return false;
  }
  if (parseFloat(styles.opacity) === 0) {
    return false;
  }

  return true;
}

export class FormValidator {
  constructor(form, i18n) {
    this.form = form;
    this.i18n = i18n;
    const lang = this.i18n.lang || 'ru';
    this.defaults = DEFAULT_ERROR_TEXTS[lang] || DEFAULT_ERROR_TEXTS.ru;
  }

  validate() {
    const errors = {};
    let firstError = '';

    const phoneField = this.form.querySelector('input[name="phone"]');
    if (phoneField) {
      const value = phoneField.value.trim();
      const digits = normalizePhone(value);
      const countryCodeDigits = normalizePhone(phoneField.getAttribute('data-country-code') || '');

      if (!isRequired(value)) {
        this._setError(errors, 'phone', this._text('phone_required'), (msg) => {
          firstError = firstError || msg;
        });
      } else if (!isValidPhone(digits, countryCodeDigits)) {
        this._setError(errors, 'phone', this._text('phone_invalid'), (msg) => {
          firstError = firstError || msg;
        });
      }
    }

    const nameField = this.form.querySelector('input[name="name"]');
    if (nameField && isFieldVisible(nameField)) {
      const name = nameField.value.trim();
      if (!isRequired(name)) {
        this._setError(errors, 'name', this._text('name_required'), (msg) => {
          firstError = firstError || msg;
        });
      } else if (!isMinLength(name, 2)) {
        this._setError(errors, 'name', this._text('name_min_length'), (msg) => {
          firstError = firstError || msg;
        });
      }
    }

    const squareField = this.form.querySelector('input[name="square"]');
    if (squareField && isFieldVisible(squareField) && !isRequired(squareField.value)) {
      this._setError(errors, 'square', this._text('square_required'), (msg) => {
        firstError = firstError || msg;
      });
    }

    const emailField = this.form.querySelector('input[name="email"]');
    if (emailField && !isValidEmail(emailField.value.trim())) {
      this._setError(errors, 'email', this._text('email_invalid'), (msg) => {
        firstError = firstError || msg;
      });
    }

    const policyField = this.form.querySelector('input[name="policy"]');
    if (policyField && isFieldVisible(policyField) && !policyField.checked) {
      this._setError(errors, 'policy', this._text('policy_required'), (msg) => {
        firstError = firstError || msg;
      });
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors,
      firstError,
    };
  }

  _text(key) {
    return this.i18n.get('error', key, this.defaults[key] || '');
  }

  _setError(errors, fieldName, message, setFirstError) {
    errors[fieldName] = message;
    setFirstError(message);
  }
}
