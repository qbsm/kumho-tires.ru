import js from '@eslint/js';
import globals from 'globals';

export default [
  js.configs.recommended,
  {
    ignores: ['assets/js/build/**', 'node_modules/**', 'vendor/**'],
  },
  {
    files: ['assets/js/**/*.js'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.browser,
        jQuery: 'readonly',
        $: 'readonly',
        ym: 'readonly',
      },
    },
  },
  {
    files: ['tests/js/**/*.js'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.vitest,
      },
    },
  },
  {
    files: ['webpack.config.js', 'postcss.config.js', 'tools/**/*.js', 'tests/smoke/**/*.js'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'commonjs',
      globals: {
        ...globals.node,
      },
    },
  },
];
