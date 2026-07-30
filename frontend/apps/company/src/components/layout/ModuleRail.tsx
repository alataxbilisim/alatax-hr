import React, { useState } from 'react';
import { useLocation } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { BsBuilding, BsSearch } from 'react-icons/bs';
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
  const [query, setQuery] = useState('');

  const userCtx = user
    ? { type: user.type, permissions: user.permissions || [] }
    : null;

  const filterVisible = (modules: ModuleGroup[]): ModuleGroup[] =>
    modules.filter((module) => {
      if (module.hidden) return false;
      if (module.moduleKey && !activeModules.includes(module.moduleKey)) {
        return false;
      }
      return getFilteredMenuItems(module, userCtx, activeModules).length > 0;
    });

  const operational = filterVisible(operationalModuleGroups);

  const pinned = pinnedModuleGroups.filter((module) => {
    if (module.id === 'account') return !!user;
    if (module.hidden) return false;
    return getFilteredMenuItems(module, userCtx, activeModules).length > 0;
  });

  const matchesQuery = (module: ModuleGroup): boolean => {
    const q = query.trim().toLocaleLowerCase('tr');
    if (!q) return true;
    const label = t(module.labelKey).toLocaleLowerCase('tr');
    if (label.includes(q)) return true;
    return getFilteredMenuItems(module, userCtx, activeModules).some((item) =>
      t(item.labelKey).toLocaleLowerCase('tr').includes(q)
    );
  };

  const filteredOperational = operational.filter(matchesQuery);
  const filteredPinned = pinned.filter(matchesQuery);

  const isModuleActive = (module: ModuleGroup): boolean => {
    if (module.basePath && location.pathname.startsWith(module.basePath)) {
      return true;
    }
    if (
      module.matchPrefixes?.some(
        (p) => location.pathname === p || location.pathname.startsWith(`${p}/`)
      )
    ) {
      return true;
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
    const label = t(module.shortLabelKey ?? module.labelKey);
    const fullLabel = t(module.labelKey);

    return (
      <button
        key={module.id}
        type="button"
        className={`rail-item ${isActive ? 'active' : ''}`}
        onClick={() => onModuleClick(module.id)}
        title={fullLabel}
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

      <div className="rail-search" role="search">
        <BsSearch aria-hidden className="rail-search-icon" />
        <input
          type="search"
          className="rail-search-input"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={t('nav.railSearchPlaceholder')}
          aria-label={t('nav.railSearchPlaceholder')}
        />
      </div>

      <nav className="rail-nav" aria-label={t('nav.menu')}>
        {filteredOperational.map(renderRailItem)}
      </nav>

      {filteredPinned.length > 0 && (
        <>
          <div className="rail-pinned-separator" aria-hidden />
          <nav className="rail-pinned" aria-label={t('nav.pinnedSection')}>
            {filteredPinned.map(renderRailItem)}
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
