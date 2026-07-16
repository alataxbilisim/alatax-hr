import React from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import { BsArrowRight } from 'react-icons/bs';

interface FormEntityLink {
  entity: string;
  titleKey: string;
  path: string;
}

const FORM_ENTITIES: FormEntityLink[] = [
  { entity: 'employee', titleKey: 'formEngine.editorTitle', path: '/settings/forms/employee' },
  { entity: 'leave_request', titleKey: 'formEngine.editorTitleLeave', path: '/settings/forms/leave_request' },
  { entity: 'job_application', titleKey: 'formEngine.editorTitleJobApplication', path: '/settings/forms/job_application' },
  { entity: 'expense', titleKey: 'formEngine.editorTitleExpense', path: '/settings/forms/expense' },
  { entity: 'asset', titleKey: 'formEngine.editorTitleAsset', path: '/settings/forms/asset' },
];

/**
 * Stüdyo — Form Düzenleri indeksi (ortak editöre linkler).
 */
const FormsIndexPage: React.FC = () => {
  const { t } = useTranslation('common');

  return (
    <div className="animate-fade-in">
      <div className="page-header" style={{ marginBottom: 'var(--space-4)' }}>
        <h1 className="page-title">{t('studio.formLayouts')}</h1>
        <p className="page-subtitle">{t('studio.formLayoutsSubtitle')}</p>
      </div>

      <div
        style={{
          display: 'grid',
          gap: 'var(--space-3)',
          gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))',
        }}
      >
        {FORM_ENTITIES.map((item) => (
          <Link
            key={item.entity}
            to={item.path}
            className="card"
            style={{
              textDecoration: 'none',
              color: 'inherit',
              padding: 'var(--space-4)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
            }}
          >
            <span style={{ fontWeight: 500 }}>{t(item.titleKey)}</span>
            <BsArrowRight style={{ color: 'var(--text-tertiary)' }} />
          </Link>
        ))}
      </div>
    </div>
  );
};

export default FormsIndexPage;
