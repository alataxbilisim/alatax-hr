import type { IconType } from 'react-icons';
import {
  BsCurrencyDollar,
  BsFileEarmarkText,
  BsMortarboard,
  BsGraphUp,
  BsClipboardData,
  BsReceipt,
  BsMegaphone,
  BsCashCoin,
  BsClock,
} from 'react-icons/bs';

export type PortalMoreLink = {
  path: string;
  labelKey: string;
  icon: IconType;
};

/** Profil / "Diğer" listesi — eski sayfalar yeni kabukta açılır */
export const PORTAL_MORE_LINKS: PortalMoreLink[] = [
  { path: '/payslips', labelKey: 'portalShell.more.payslips', icon: BsCurrencyDollar },
  { path: '/salary', labelKey: 'portalShell.more.salary', icon: BsCashCoin },
  { path: '/documents', labelKey: 'portalShell.more.documents', icon: BsFileEarmarkText },
  { path: '/training', labelKey: 'portalShell.more.training', icon: BsMortarboard },
  { path: '/performance', labelKey: 'portalShell.more.performance', icon: BsGraphUp },
  { path: '/surveys', labelKey: 'portalShell.more.surveys', icon: BsClipboardData },
  { path: '/expenses', labelKey: 'portalShell.more.expenses', icon: BsReceipt },
  { path: '/announcements', labelKey: 'portalShell.more.announcements', icon: BsMegaphone },
  { path: '/timesheet', labelKey: 'portalShell.more.timesheet', icon: BsClock },
];
