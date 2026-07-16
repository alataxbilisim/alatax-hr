import React from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import {
  BsHouseDoor,
  BsCalendarCheck,
  BsQrCodeScan,
  BsInboxes,
  BsPersonCircle,
  BsGrid,
} from 'react-icons/bs';
import { useTranslation } from '@shared/i18n';

type DesktopRailProps = {
  onMoreClick: () => void;
};

const DesktopRail: React.FC<DesktopRailProps> = ({ onMoreClick }) => {
  const { t } = useTranslation('common');
  const location = useLocation();

  const isActive = (path: string) =>
    location.pathname === path || location.pathname.startsWith(`${path}/`);

  return (
    <aside className="portal-rail" aria-label={t('portalShell.rail')}>
      <div className="portal-rail__brand">AX</div>

      <NavLink
        to="/dashboard"
        className={`portal-rail__item ${isActive('/dashboard') ? 'portal-rail__item--active' : ''}`}
      >
        <BsHouseDoor size={22} />
        <span>{t('portalShell.nav.home')}</span>
      </NavLink>

      <NavLink
        to="/leaves"
        className={`portal-rail__item ${isActive('/leaves') ? 'portal-rail__item--active' : ''}`}
      >
        <BsCalendarCheck size={22} />
        <span>{t('portalShell.nav.leaves')}</span>
      </NavLink>

      <NavLink
        to="/timesheet/qr"
        className="portal-rail__qr"
        aria-label={t('portalShell.nav.qr')}
        title={t('portalShell.nav.qr')}
      >
        <BsQrCodeScan size={24} />
      </NavLink>

      <NavLink
        to="/requests"
        className={`portal-rail__item ${isActive('/requests') ? 'portal-rail__item--active' : ''}`}
      >
        <BsInboxes size={22} />
        <span>{t('portalShell.nav.requests')}</span>
      </NavLink>

      <NavLink
        to="/profile"
        className={`portal-rail__item ${isActive('/profile') ? 'portal-rail__item--active' : ''}`}
      >
        <BsPersonCircle size={22} />
        <span>{t('portalShell.nav.profile')}</span>
      </NavLink>

      <button
        type="button"
        className="portal-rail__item portal-rail__more"
        onClick={onMoreClick}
      >
        <BsGrid size={22} />
        <span>{t('portalShell.nav.more')}</span>
      </button>
    </aside>
  );
};

export default DesktopRail;
