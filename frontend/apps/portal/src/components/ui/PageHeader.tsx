import React from 'react';
import { useNavigate } from 'react-router-dom';
import { BsArrowLeft } from 'react-icons/bs';
import { useTranslation } from '@shared/i18n';

export interface PageHeaderProps {
  title: string;
  showBack?: boolean;
  onBack?: () => void;
  actions?: React.ReactNode;
}

const PageHeader: React.FC<PageHeaderProps> = ({
  title,
  showBack = false,
  onBack,
  actions,
}) => {
  const navigate = useNavigate();
  const { t } = useTranslation('common');

  const handleBack = () => {
    if (onBack) {
      onBack();
      return;
    }
    navigate(-1);
  };

  return (
    <header className="portal-page-header">
      {showBack && (
        <button
          type="button"
          className="portal-page-header__back"
          onClick={handleBack}
          aria-label={t('portalShell.back')}
        >
          <BsArrowLeft size={20} />
        </button>
      )}
      <h1 className="portal-page-header__title">{title}</h1>
      {actions && <div className="portal-page-header__actions">{actions}</div>}
    </header>
  );
};

export default PageHeader;
