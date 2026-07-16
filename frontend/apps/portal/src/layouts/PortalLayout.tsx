import React, { useState } from 'react';
import { Link, Outlet, useNavigate } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import { NotificationBell } from '@shared/components';
import { useTranslation } from '@shared/i18n';
import { logout } from '@shared/store/slices/authSlice';
import { BsBoxArrowRight } from 'react-icons/bs';
import { AppDispatch, RootState } from '../store';
import { BottomNav, BottomSheet } from '../components/ui';
import DesktopRail from '../components/shell/DesktopRail';
import { PORTAL_MORE_LINKS } from '../nav/portalMoreLinks';

/**
 * PORTAL-1 AppShell — mobil alt nav + masaüstü rail.
 * Eski sayfalar Outlet ile Bootstrap stillerinde çalışmaya devam eder.
 */
const PortalLayout: React.FC = () => {
  const { t } = useTranslation('common');
  const dispatch = useDispatch<AppDispatch>();
  const navigate = useNavigate();
  const { user } = useSelector((state: RootState) => state.auth);
  const [moreOpen, setMoreOpen] = useState(false);

  const handleLogout = async () => {
    await dispatch(logout());
    navigate('/login');
  };

  return (
    <div className="portal-shell">
      <DesktopRail onMoreClick={() => setMoreOpen(true)} />

      <div className="portal-shell__main">
        <div className="portal-shell__content">
          <div className="portal-shell-top">
            <span className="portal-shell-top__brand">ALATAX</span>
            <div className="portal-shell-top__actions">
              <NotificationBell />
              <button
                type="button"
                className="portal-page-header__back"
                onClick={() => void handleLogout()}
                title={t('portalShell.logout')}
                aria-label={t('portalShell.logout')}
              >
                <BsBoxArrowRight size={18} />
              </button>
            </div>
          </div>

          <Outlet />
        </div>
      </div>

      <BottomNav />

      <BottomSheet
        open={moreOpen}
        onClose={() => setMoreOpen(false)}
        title={t('portalShell.moreTitle')}
      >
        {PORTAL_MORE_LINKS.map((item) => (
          <Link
            key={item.path}
            to={item.path}
            className="portal-more-link"
            onClick={() => setMoreOpen(false)}
          >
            <item.icon size={20} className="portal-more-link__icon" />
            <span>{t(item.labelKey)}</span>
          </Link>
        ))}
        {user?.name && (
          <p className="portal-dash__meta" style={{ marginTop: 'var(--sp-4)' }}>
            {user.name}
          </p>
        )}
      </BottomSheet>
    </div>
  );
};

export default PortalLayout;
