import React from 'react';
import { NavLink } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { BsChevronLeft, BsChevronRight } from 'react-icons/bs';
import { useTranslation } from '@shared/i18n';
import {
  type ModuleGroup,
  type MenuItem,
  getFilteredMenuItems,
  getVisibleGroups,
} from './moduleNav';
import { RootState } from '../../store';

interface ContextSidebarProps {
  module: ModuleGroup | null;
  /** true = 216px geniş; false = 48px daraltılmış */
  expanded: boolean;
  onToggle: () => void;
}

const ContextSidebar: React.FC<ContextSidebarProps> = ({
  module,
  expanded,
  onToggle,
}) => {
  const { t } = useTranslation('common');
  const { user } = useSelector((state: RootState) => state.auth);
  const activeModules = user?.company?.active_modules || [];

  if (!module) return null;

  const filteredItems = getFilteredMenuItems(
    module,
    user
      ? { type: user.type, permissions: user.permissions || [] }
      : null,
    activeModules
  );

  if (filteredItems.length === 0) return null;

  const Icon = module.icon;
  const collapsed = !expanded;
  const moduleLabel = t(module.labelKey);
  const visibleGroups = getVisibleGroups(filteredItems);
  const ungrouped = filteredItems.filter((i) => !i.group);

  const renderItem = (item: MenuItem) => {
    const label = t(item.labelKey);
    const initial = label.trim().charAt(0).toLocaleUpperCase('tr-TR');

    return (
      <NavLink
        key={item.path}
        to={item.path}
        title={label}
        className={({ isActive }) =>
          `context-nav-item ${isActive ? 'active' : ''}`
        }
        style={{ '--module-color': module.color } as React.CSSProperties}
      >
        {collapsed ? (
          <span className="context-nav-icon" aria-hidden>
            {initial}
          </span>
        ) : (
          <>
            <span className="context-nav-text">{label}</span>
            {item.badge != null && item.badge > 0 && (
              <span className="context-nav-badge">{item.badge}</span>
            )}
          </>
        )}
      </NavLink>
    );
  };

  return (
    <aside
      className={`context-sidebar ${expanded ? 'expanded' : 'collapsed'}`}
      aria-expanded={expanded}
    >
      <div
        className="context-sidebar-header"
        style={{ '--module-color': module.color } as React.CSSProperties}
      >
        <div className="context-header-content" title={moduleLabel}>
          <span className="context-header-icon" aria-hidden>
            <Icon />
          </span>
          {!collapsed && (
            <span className="context-header-title">{moduleLabel}</span>
          )}
        </div>
        <button
          type="button"
          className="context-close-btn"
          onClick={onToggle}
          title={expanded ? t('nav.collapseMenu') : t('nav.expandMenu')}
          aria-label={expanded ? t('nav.collapseMenu') : t('nav.expandMenu')}
        >
          {expanded ? <BsChevronLeft /> : <BsChevronRight />}
        </button>
      </div>

      <nav className="context-nav">
        {visibleGroups.length > 0
          ? visibleGroups.map(({ group, items }) => (
              <div key={group} className="context-nav-group">
                {!collapsed && (
                  <div className="context-nav-group-label">
                    {t(`studio.groups.${group}`)}
                  </div>
                )}
                {items.map(renderItem)}
              </div>
            ))
          : ungrouped.map(renderItem)}
        {visibleGroups.length > 0 && ungrouped.map(renderItem)}
      </nav>

      {!collapsed && (
        <div className="context-sidebar-footer">
          <div className="context-module-info">
            <span
              className="module-color-dot"
              style={{ background: module.color }}
            />
            <span>{t('nav.subMenuCount', { count: filteredItems.length })}</span>
          </div>
        </div>
      )}
    </aside>
  );
};

export default ContextSidebar;
