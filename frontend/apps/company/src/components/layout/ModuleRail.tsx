import React from 'react';
import { useLocation } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { BsBuilding } from 'react-icons/bs';
import { RootState } from '../../store';
import { moduleGroups, type ModuleGroup, type MenuItem } from './moduleNav';

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
  const { user } = useSelector((state: RootState) => state.auth);

  // Yetki kontrolü helper
  const hasPageAccess = (module: string, page: string): boolean => {
    if (!user) return false;
    
    // Company Admin ve Super Admin her şeye erişebilir
    if (user.type === 'company_admin' || user.type === 'super_admin') {
      return true;
    }

    const permissions = user.permissions || [];

    // Wildcard kontrolleri
    if (permissions.includes('*')) return true;
    if (permissions.includes(`${module}.*`)) return true;
    if (permissions.includes(`${module}.${page}.*`)) return true;

    // Sayfaya ait herhangi bir aksiyon yetkisi var mı?
    return permissions.some((p: string) => p.startsWith(`${module}.${page}.`));
  };

  // Modül erişimi kontrolü
  const hasModuleAccess = (module: ModuleGroup): boolean => {
    if (!user) return false;
    
    // Company Admin ve Super Admin her şeye erişebilir
    if (user.type === 'company_admin' || user.type === 'super_admin') {
      return true;
    }

    // Modül için yetki modülü tanımlı değilse (dashboard gibi) erişime izin ver
    if (!module.permissionModule) return true;

    const permissions = user.permissions || [];
    const permModule = module.permissionModule;

    // Wildcard kontrolü
    if (permissions.includes('*') || permissions.includes(`${permModule}.*`)) {
      return true;
    }

    // Modüle ait herhangi bir yetki var mı?
    return permissions.some((p: string) => p.startsWith(`${permModule}.`));
  };

  // Menü öğelerini yetkilere göre filtrele
  const getFilteredItems = (module: ModuleGroup): MenuItem[] => {
    if (!user) return [];
    
    // Company Admin ve Super Admin her şeyi görebilir
    if (user.type === 'company_admin' || user.type === 'super_admin') {
      return module.items;
    }

    return module.items.filter(item => {
      // Yetki tanımı yoksa göster
      if (!item.permission) return true;
      
      return hasPageAccess(item.permission.module, item.permission.page);
    });
  };

  // Filter modules based on:
  // 1. Company's active modules (license)
  // 2. User's permissions
  const filteredModules = moduleGroups.filter((module) => {
    // Lisans kontrolü
    if (module.moduleKey && !activeModules.includes(module.moduleKey)) {
      return false;
    }
    
    // Yetki kontrolü
    if (!hasModuleAccess(module)) {
      return false;
    }

    // En az bir görüntülenebilir menü öğesi var mı?
    const visibleItems = getFilteredItems(module);
    return visibleItems.length > 0;
  });

  // Check if current path matches module
  const isModuleActive = (module: ModuleGroup): boolean => {
    if (module.basePath) {
      return location.pathname.startsWith(module.basePath);
    }
    return module.items.some(item => location.pathname === item.path);
  };

  return (
    <div className="module-rail">
      {/* Brand Logo */}
      <div className="rail-brand">
        <div className="rail-brand-icon">
          <BsBuilding />
        </div>
      </div>

      {/* Module Icons */}
      <nav className="rail-nav">
        {filteredModules.map((module) => {
          const isActive = activeModule === module.id || isModuleActive(module);
          const Icon = module.icon;
          
          return (
            <button
              key={module.id}
              className={`rail-item ${isActive ? 'active' : ''}`}
              onClick={() => onModuleClick(module.id)}
              title={module.label}
              style={{
                '--module-color': module.color,
              } as React.CSSProperties}
            >
              <span className="rail-item-icon">
                <Icon />
              </span>
              <span className="rail-item-label">{module.label}</span>
              {isActive && <span className="rail-item-indicator" />}
            </button>
          );
        })}
      </nav>

      {/* Company Initial at Bottom */}
      <div className="rail-footer">
        <div className="rail-company-badge" title={companyName}>
          {companyName?.charAt(0).toUpperCase() || 'C'}
        </div>
      </div>
    </div>
  );
};

export default ModuleRail;
