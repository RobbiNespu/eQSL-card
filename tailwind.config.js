/**
 * Tailwind + DaisyUI config.
 *
 * Build command:
 *   npm run build:css
 *
 * Produces webroot/css/dist.css, which is committed to git so the
 * shared host (no Node) can serve it directly via FTP.
 *
 * Content paths tell PurgeCSS which class names are actually used.
 * Anything not appearing in these files is stripped from dist.css.
 * The safelist below covers Alpine-driven dynamic classes that never
 * appear in static markup.
 */
module.exports = {
  content: [
    './templates/**/*.php',
    './webroot/js/**/*.js',
    './webroot/css/theme.css',
  ],
  safelist: [
    'show',
    'is-active',
    'btn-active',
    'hidden',
    {
      pattern: /^(alert|btn|badge|card|nav-link)-(primary|secondary|success|info|warning|danger|light|dark)$/,
    },
  ],
  theme: {
    extend: {
      colors: {
        ink:   '#09090b',
        paper: '#fafafa',
      },
      fontFamily: {
        sans: ['"Inter Variable"', 'Inter', 'system-ui', 'sans-serif'],
        mono: ['"Geist Mono Variable"', 'ui-monospace', 'monospace'],
      },
    },
  },
  plugins: [require('daisyui')],
  daisyui: {
    themes: [
      {
        eqsl: {
          'color-scheme':         'light',
          'primary':              '#18181b',
          'primary-content':      '#ffffff',
          'secondary':            '#e4e4e7',
          'secondary-content':    '#18181b',
          'accent':               '#059669',
          'accent-content':       '#ffffff',
          'neutral':              '#52525b',
          'neutral-content':      '#fafafa',
          'base-100':             '#ffffff',
          'base-200':             '#f4f4f5',
          'base-300':             '#e4e4e7',
          'base-content':         '#09090b',
          'info':                 '#0284c7',
          'success':              '#059669',
          'warning':              '#d97706',
          'error':                '#dc2626',
          '--rounded-box':        '0.75rem',
          '--rounded-btn':        '0.5rem',
          '--rounded-badge':      '999px',
          '--border-btn':         '1px',
        },
      },
      {
        'eqsl-dark': {
          'color-scheme':         'dark',
          'primary':              '#fafafa',
          'primary-content':      '#18181b',
          'secondary':            '#3f3f46',
          'secondary-content':    '#fafafa',
          'accent':               '#10b981',
          'accent-content':       '#ffffff',
          'neutral':              '#a1a1aa',
          'neutral-content':      '#18181b',
          'base-100':             '#18181b',
          'base-200':             '#27272a',
          'base-300':             '#3f3f46',
          'base-content':         '#fafafa',
          'info':                 '#38bdf8',
          'success':              '#34d399',
          'warning':              '#fbbf24',
          'error':                '#f87171',
          '--rounded-box':        '0.75rem',
          '--rounded-btn':        '0.5rem',
          '--rounded-badge':      '999px',
          '--border-btn':         '1px',
        },
      },
    ],
  },
};
