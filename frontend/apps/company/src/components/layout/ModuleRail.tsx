import React from 'react';
import { useLocation } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { BsBuilding } from 'react-icons/bs';
import { useTranslation } from '@shared/i18n';
import { RootState } from '../../store';
import {
  operationalModuleGroups,
  pinnedModuleGroups,
  getFilteredMenuItems,
  type ModuleGroup,
} from './moduleNav';

interface ModuleRailProps {
  activeModule: string;
  onModuleClick: (moduleId: string) => void;
  activeModules: string[];
  companyName?: string;
}

const ModuleRail: React.FC<ModuleRailProps> = ({
  activeModule,
  onModuleClick,
  activeModules,
  companyName,
}) => {
  const location = useLocation();
  const { t } = useTranslation('common');
  const { user } = useSelector((state: RootState) => state.auth);

  const hasModuleAccess = (module: ModuleGroup): boolean => {
    if (!user) return false;

    if (user.type === 'company_admin' || user.type === 'super_admin') {
      return true;
    }

    if (!module.permissionModule) return true;

    const permissions = user.permissions || [];
    const permModule = module.permissionModule;

    if (permissions.includes('*') || permissions.includes(`${permModule}.*`)) {
      return true;
    }

    return permissions.some((p: string) => p.startsWith(`${permModule}.`));
  };

  const filterByLicenseAndPerm = (modules: ModuleGroup[]): ModuleGroup[] =>
    modules.filter((module) => {
      if (module.moduleKey && !activeModules.includes(module.moduleKey)) {
        return false;
      }
      if (!hasModuleAccess(module)) {
        return false;
      }
      const visibleItems = getFilteredMenuItems(
        module,
        user
          ? { type: user.type, permissions: user.permissions || [] }
          : null,
        activeModules
      );
      return visibleItems.length > 0;
    });

  const operational = filterByLicenseAndPerm(operationalModuleGroups);

  // Ayarlar: giriş yapmış herkes; Yönetim: modül yetkisi
  const pinned = pinnedModuleGroups.filter((module) => {
    if (module.id === 'account') {
      return !!user;
    }
    if (!hasModuleAccess(module)) {
      return false;
    }
    return (
      getFilteredMenuItems(
        module,
        user
          ? { type: user.type, permissions: user.permissions || [] }
          : null,
        activeModules
      ).length > 0
    );
  });

  const isModuleActive = (module: ModuleGroup): boolean => {
    if (module.basePath) {
      return location.pathname.startsWith(module.basePath);
    }
    return module.items.some(
      (item) =>
        location.pathname === item.path ||
        location.pathname.startsWith(`${item.path}/`)
    );
  };

  const renderRailItem = (module: ModuleGroup) => {
    const isActive = activeModule === module.id || isModuleActive(module);
    const Icon = module.icon;
    const label = t(module.labelKey);

    return (
      <button
        key={module.id}
        type="button"
        className={`rail-item ${isActive ? 'active' : ''}`}
        onClick={() => onModuleClick(module.id)}
        title={label}
        style={
          {
            '--module-color': module.color,
          } as React.CSSProperties
        }
      >
        <span className="rail-item-icon">
          <Icon />
        </span>
        <span className="rail-item-label">{label}</span>
        {isActive && <span className="rail-item-indicator" />}
      </button>
    );
  };

  return (
    <div className="module-rail">
      <div className="rail-brand">
        <div className="rail-brand-icon">
          <BsBuilding />
        </div>
      </div>

      <nav className="rail-nav" aria-label={t('nav.menu')}>
        {operational.map(renderRailItem)}
      </nav>

      {pinned.length > 0 && (
        <>
          <div className="rail-pinned-separator" aria-hidden />
          <nav className="rail-pinned" aria-label={t('nav.pinnedSection')}>
            {pinned.map(renderRailItem)}
          </nav>
        </>
      )}

      <div className="rail-footer">
        <div className="rail-company-badge" title={companyName}>
          {companyName?.charAt(0).toUpperCase() || 'C'}
        </div>
      </div>
    </div>
  );
};

export default ModuleRail;
