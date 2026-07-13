// Export all constants
// MODULE_LABELS: modules.ts kanonik (ModuleKey); permissions.ts barrel'da PERMISSION_MODULE_LABELS
export * from './modules';
export * from './routes';
export * from './actions';
export {
  ACTIONS,
  MODULES,
  PAGES,
  PAGE_ACTIONS,
  MODULE_LABELS as PERMISSION_MODULE_LABELS,
  PAGE_LABELS,
  ACTION_LABELS,
  createPermission,
  matchesPermission,
  generateAllPermissions,
  generateModuleWildcards,
  getPermissionFromPath,
  PORTAL_SELF_PERMISSIONS,
  hasPanelAccess,
} from './permissions';
export type { ActionType, ModuleType, PanelAccessUser } from './permissions';
