import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import commonTr from './locales/tr/common.json';
import authTr from './locales/tr/auth.json';
import validationTr from './locales/tr/validation.json';

/**
 * ALATAX HR i18n — tek kaynak (@alatax/shared).
 * Varsayılan dil: tr. Dil değiştirici UI yok; ileride preferences + switcher eklenecek.
 * Yeni UI metni: t('namespace:key') — hardcode yasak (.cursorrules).
 */
void i18n.use(initReactI18next).init({
  lng: 'tr',
  fallbackLng: 'tr',
  defaultNS: 'common',
  ns: ['common', 'auth', 'validation'],
  resources: {
    tr: {
      common: commonTr,
      auth: authTr,
      validation: validationTr,
    },
  },
  interpolation: {
    escapeValue: false,
  },
  returnNull: false,
});

export default i18n;
export { i18n };
export { useTranslation, Trans, I18nextProvider } from 'react-i18next';
