import React, { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { useTranslation } from '@shared/i18n';
import { BsArrowRight } from 'react-icons/bs';
import { RootState } from '../../store';

interface CfLink {
  path: string;
  labelKey: string;
  module: string;
  page: string;
}

const CF_LINKS: CfLink[] = [
  { path: '/employees/custom-fields', labelKey: 'studio.cfEmployees', module: 'employees', page: 'custom_fields' },
  { path: '/leaves/custom-fields', labelKey: 'studio.cfLeaves', module: 'leaves', page: 'custom_fields' },
  { path: '/documents/custom-fields', labelKey: 'studio.cfDocuments', module: 'documents', page: 'custom_fields' },
  { path: '/recruitment/custom-fields', labelKey: 'studio.cfRecruitment', module: 'recruitment', page: 'custom_fields' },
  { path: '/performance/custom-fields', labelKey: 'studio.cfPerformance', module: 'performance', page: 'custom_fields' },
  { path: '/training/custom-fields', labelKey: 'studio.cfTraining', module: 'training', page: 'custom_fields' },
  { path: '/assets/custom-fields', labelKey: 'studio.cfAssets', module: 'assets', page: 'custom_fields' },
];

const CustomFieldsIndexPage: React.FC = () => {
  const { t } = useTranslation('common');
  const { user } = useSelector((state: RootState) => state.auth);

  const visible = useMemo(() => {
    if (!user) return [];
    if (user.type === 'company_admin' || user.type === 'super_admin') {
      return CF_LINKS;
    }
    const permissions = user.permissions || [];
    return CF_LINKS.filter((link) => {
      if (permissions.includes('*')) return true;
      if (permissions.includes(`${link.module}.*`)) return true;
      if (permissions.includes(`${link.module}.${link.page}.*`)) return true;
      return permissions.some((p) => p.startsWith(`${link.module}.${link.page}.`));
    });
  }, [user]);

  return (
    <div className="animate-fade-in">
      <div className="page-header" style={{ marginBottom: 'var(--space-4)' }}>
        <h1 className="page-title">{t('studio.customFieldsIndexTitle')}</h1>
        <p className="page-subtitle">{t('studio.customFieldsIndexSubtitle')}</p>
      </div>

      {visible.length === 0 ? (
        <div className="card">
          <div className="card-body">
            <p style={{ color: 'var(--text-tertiary)' }}>{t('studio.customFieldsIndexEmpty')}</p>
          </div>
        </div>
      ) : (
        <div
          style={{
            display: 'grid',
            gap: 'var(--space-3)',
            gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))',
          }}
        >
          {visible.map((link) => (
            <Link
              key={link.path}
              to={link.path}
              className="card"
              style={{
                textDecoration: 'none',
                color: 'inherit',
                padding: 'var(--space-4)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                transition: 'border-color var(--transition-fast, 0.15s)',
              }}
            >
              <span style={{ fontWeight: 500 }}>{t(link.labelKey)}</span>
              <BsArrowRight style={{ color: 'var(--text-tertiary)' }} />
            </Link>
          ))}
        </div>
      )}
    </div>
  );
};

export default CustomFieldsIndexPage;
