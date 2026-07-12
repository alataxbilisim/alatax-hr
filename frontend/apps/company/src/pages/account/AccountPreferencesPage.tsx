import React, { useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { useTranslation } from '@shared/i18n';
import { authApi } from '@shared/services/api';
import { updateUserPreferences, setUser } from '@shared/store/slices/authSlice';
import { setTheme, setDensity, type ThemeMode, type DensityMode } from '@shared/store/slices/themeSlice';
import toast from 'react-hot-toast';
import { RootState, AppDispatch } from '../../store';

const AccountPreferencesPage: React.FC = () => {
  const { t } = useTranslation('common');
  const dispatch = useDispatch<AppDispatch>();
  const { user } = useSelector((state: RootState) => state.auth);
  const { mode, density } = useSelector((state: RootState) => state.theme);

  const [theme, setThemeLocal] = useState<ThemeMode>(
    (user?.preferences?.theme as ThemeMode) || mode
  );
  const [densityLocal, setDensityLocal] = useState<DensityMode>(
    (user?.preferences?.density as DensityMode) || density
  );
  const [locale, setLocale] = useState(user?.preferences?.locale || 'tr');
  const [saving, setSaving] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      const response = await authApi.updateProfile({
        preferences: {
          theme,
          density: densityLocal,
          locale,
        },
      });
      const updated = response.data.data.user;
      dispatch(setUser(updated));
      localStorage.setItem('user', JSON.stringify(updated));
      dispatch(updateUserPreferences({ theme, density: densityLocal, locale }));
      dispatch(setTheme(theme));
      dispatch(setDensity(densityLocal));
      toast.success(t('account.preferencesSuccess'));
    } catch {
      toast.error(t('account.preferencesError'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header" style={{ marginBottom: 'var(--space-4)' }}>
        <h1 className="page-title">{t('account.preferencesTitle')}</h1>
        <p className="page-subtitle">{t('account.preferencesSubtitle')}</p>
      </div>

      <div className="card" style={{ maxWidth: 480 }}>
        <div className="card-body">
          <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-3)' }}>
            <div className="form-group">
              <label className="form-label" htmlFor="pref-theme">{t('account.theme')}</label>
              <select
                id="pref-theme"
                className="form-control"
                value={theme}
                onChange={(e) => setThemeLocal(e.target.value as ThemeMode)}
              >
                <option value="dark">{t('account.themeDark')}</option>
                <option value="light">{t('account.themeLight')}</option>
              </select>
            </div>

            <div className="form-group">
              <label className="form-label" htmlFor="pref-density">{t('account.density')}</label>
              <select
                id="pref-density"
                className="form-control"
                value={densityLocal}
                onChange={(e) => setDensityLocal(e.target.value as DensityMode)}
              >
                <option value="comfortable">{t('account.densityComfortable')}</option>
                <option value="compact">{t('account.densityCompact')}</option>
              </select>
            </div>

            <div className="form-group">
              <label className="form-label" htmlFor="pref-locale">{t('account.locale')}</label>
              <select
                id="pref-locale"
                className="form-control"
                value={locale}
                onChange={(e) => setLocale(e.target.value)}
              >
                <option value="tr">{t('account.localeTr')}</option>
                <option value="en">{t('account.localeEn')}</option>
              </select>
            </div>

            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? t('loading') : t('save')}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
};

export default AccountPreferencesPage;
