import React from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import { operationalModuleGroups, getFilteredMenuItems } from '../components/layout/moduleNav';
import { useSelector } from 'react-redux';
import { RootState } from '../store';

/**
 * E0 — Bilinmeyen rota: yardımcı 404 (modül listesine yönlendirir).
 */
const NotFoundPage: React.FC = () => {
  const { t } = useTranslation('common');
  const { user } = useSelector((state: RootState) => state.auth);
  const activeModules = user?.company?.active_modules ?? [];

  const modules = operationalModuleGroups.filter((m) => {
    if (m.hidden) return false;
    if (m.moduleKey && !activeModules.includes(m.moduleKey)) return false;
    return (
      getFilteredMenuItems(
        m,
        user ? { type: user.type, permissions: user.permissions || [] } : null,
        activeModules
      ).length > 0
    );
  });

  return (
    <div className="page-container" style={{ padding: 'var(--space-6)', maxWidth: 640 }}>
      <h1 style={{ marginBottom: 'var(--space-2)' }}>{t('nav.notFoundTitle')}</h1>
      <p style={{ color: 'var(--text-secondary)', marginBottom: 'var(--space-4)' }}>
        {t('nav.notFoundHint')}
      </p>
      <ul style={{ listStyle: 'none', padding: 0, display: 'flex', flexDirection: 'column', gap: 'var(--space-2)' }}>
        {modules.map((m) => {
          const items = getFilteredMenuItems(
            m,
            user ? { type: user.type, permissions: user.permissions || [] } : null,
            activeModules
          );
          const href = items[0]?.path ?? m.basePath ?? '/dashboard';
          return (
            <li key={m.id}>
              <Link to={href} className="btn btn-secondary btn-sm">
                {t(m.labelKey)}
              </Link>
            </li>
          );
        })}
      </ul>
      <p style={{ marginTop: 'var(--space-4)' }}>
        <Link to="/dashboard">{t('nav.dashboard')}</Link>
      </p>
    </div>
  );
};

export default NotFoundPage;
