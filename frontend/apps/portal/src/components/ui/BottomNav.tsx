import React from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import {
  BsHouseDoor,
  BsCalendarCheck,
  BsQrCodeScan,
  BsInboxes,
  BsPersonCircle,
} from 'react-icons/bs';
import { useTranslation } from '@shared/i18n';

const BottomNav: React.FC = () => {
  const { t } = useTranslation('common');
  const location = useLocation();

  const isActive = (path: string) =>
    location.pathname === path || location.pathname.startsWith(`${path}/`);

  return (
    <nav className="portal-bottom-nav" aria-label={t('portalShell.bottomNav')}>
      <div className="portal-bottom-nav__inner">
        <NavLink
          to="/dashboard"
          className={`portal-bottom-nav__item ${isActive('/dashboard') ? 'portal-bottom-nav__item--active' : ''}`}
        >
          <BsHouseDoor size={22} />
          <span>{t('portalShell.nav.home')}</span>
        </NavLink>

        <NavLink
          to="/leaves"
          className={`portal-bottom-nav__item ${isActive('/leaves') ? 'portal-bottom-nav__item--active' : ''}`}
        >
          <BsCalendarCheck size={22} />
          <span>{t('portalShell.nav.leaves')}</span>
        </NavLink>

        <div className="portal-bottom-nav__qr-slot">
          <NavLink
            to="/timesheet/qr"
            className="portal-bottom-nav__qr"
            aria-label={t('portalShell.nav.qr')}
          >
            <BsQrCodeScan size={26} />
          </NavLink>
        </div>

        <NavLink
          to="/requests"
          className={`portal-bottom-nav__item ${isActive('/requests') ? 'portal-bottom-nav__item--active' : ''}`}
        >
          <BsInboxes size={22} />
          <span>{t('portalShell.nav.requests')}</span>
        </NavLink>

        <NavLink
          to="/profile"
          className={`portal-bottom-nav__item ${isActive('/profile') ? 'portal-bottom-nav__item--active' : ''}`}
        >
          <BsPersonCircle size={22} />
          <span>{t('portalShell.nav.profile')}</span>
        </NavLink>
      </div>
    </nav>
  );
};

export default BottomNav;
