import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { useTranslation } from '@shared/i18n';
import { portalApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import {
  BsCalendarPlus,
  BsReceipt,
  BsInboxes,
  BsCalendarCheck,
  BsMegaphone,
  BsArrowRight,
} from 'react-icons/bs';
import { RootState } from '../store';
import {
  AppButton,
  AppCard,
  EmptyState,
  SkeletonLoader,
  StatusBadge,
} from '../components/ui';

interface LeaveBalanceRow {
  type: string;
  remaining: number;
}

interface AnnouncementRow {
  id: number;
  title: string;
  summary?: string | null;
  type: string;
  published_at: string;
  is_pinned?: boolean;
}

interface RecentRequestRow {
  id: number;
  title: string;
  status: string;
  created_at: string;
  request_type?: { name?: string } | null;
}

interface TodayStatus {
  is_clocked_in: boolean;
  is_clocked_out: boolean;
  clock_in: string | null;
  working_duration: string | null;
}

interface TodayShift {
  start_time?: string;
  end_time?: string;
  name?: string;
}

function greetingKey(hour: number): 'morning' | 'afternoon' | 'evening' {
  if (hour < 12) return 'morning';
  if (hour < 18) return 'afternoon';
  return 'evening';
}

const DashboardPage: React.FC = () => {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const { user } = useSelector((state: RootState) => state.auth);

  const [loading, setLoading] = useState(true);
  const [leaveBalances, setLeaveBalances] = useState<LeaveBalanceRow[]>([]);
  const [pendingRequests, setPendingRequests] = useState(0);
  const [announcements, setAnnouncements] = useState<AnnouncementRow[]>([]);
  const [recentRequests, setRecentRequests] = useState<RecentRequestRow[]>([]);
  const [today, setToday] = useState<TodayStatus | null>(null);
  const [shift, setShift] = useState<TodayShift | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        setLoading(true);
        const [dashRes, todayRes, shiftsRes] = await Promise.all([
          portalApi.dashboard(),
          portalApi.timesheet.todayStatus().catch(() => null),
          portalApi.timesheet.shifts().catch(() => null),
        ]);

        const dash: {
          stats?: { leave_balance?: LeaveBalanceRow[]; pending_requests?: number };
          announcements?: AnnouncementRow[];
          recent_requests?: RecentRequestRow[];
        } = dashRes.data.data;

        setLeaveBalances(dash.stats?.leave_balance ?? []);
        setPendingRequests(dash.stats?.pending_requests ?? 0);
        setAnnouncements((dash.announcements ?? []).slice(0, 3));
        setRecentRequests(
          (dash.recent_requests ?? [])
            .filter((r) => r.status === 'pending')
            .slice(0, 3),
        );

        if (todayRes) {
          const status: TodayStatus = todayRes.data.data;
          setToday(status);
        }

        if (shiftsRes) {
          const todayStr = new Date().toISOString().slice(0, 10);
          const shiftData: {
            shifts?: Array<{ date: string; shift?: TodayShift | null }>;
          } = shiftsRes.data.data;
          const found = (shiftData.shifts ?? []).find((s) => s.date === todayStr);
          setShift(found?.shift ?? null);
        }
      } catch (error: unknown) {
        toast.error(getErrorMessage(error, t('portalDash.loadError')));
      } finally {
        setLoading(false);
      }
    };
    void load();
  }, [t]);

  const firstName = useMemo(() => {
    const name = user?.name?.trim() || '';
    return name.split(/\s+/)[0] || name;
  }, [user?.name]);

  const greet = t(`portalDash.greeting.${greetingKey(new Date().getHours())}`, {
    name: firstName,
  });

  const dateLabel = new Date().toLocaleDateString('tr-TR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  });

  const remainingDays = leaveBalances.reduce((sum, b) => sum + (b.remaining || 0), 0);

  const qrLabel = today?.is_clocked_in && !today.is_clocked_out
    ? t('portalDash.qrOut')
    : t('portalDash.qrIn');

  if (loading) {
    return (
      <div className="portal-dash">
        <SkeletonLoader height={28} width="60%" />
        <SkeletonLoader height={14} width="40%" />
        <SkeletonLoader height={160} count={1} />
        <div className="portal-dash__grid">
          <SkeletonLoader height={96} />
          <SkeletonLoader height={96} />
          <SkeletonLoader height={96} />
          <SkeletonLoader height={96} />
        </div>
      </div>
    );
  }

  return (
    <div className="portal-dash animate-fade-in">
      <section className="portal-dash__hello">
        <h1>{greet}</h1>
        <p className="portal-dash__meta">
          {dateLabel}
          {shift?.start_time && shift?.end_time
            ? ` · ${t('portalDash.shift', { start: shift.start_time, end: shift.end_time })}`
            : ''}
        </p>
      </section>

      <AppCard title={t('portalDash.todayTitle')}>
        <p className="portal-dash__today-status">
          {today?.is_clocked_in && !today.is_clocked_out && today.clock_in
            ? t('portalDash.clockedInSince', {
                time: today.clock_in,
                duration: today.working_duration || '—',
              })
            : today?.is_clocked_out
              ? t('portalDash.clockedOut')
              : t('portalDash.notClockedIn')}
        </p>
        <AppButton size="lg" fullWidth onClick={() => navigate('/timesheet/qr')}>
          {qrLabel}
        </AppButton>
      </AppCard>

      <section>
        <h2 className="portal-card__title">{t('portalDash.quickActions')}</h2>
        <div className="portal-dash__grid">
          <button type="button" className="portal-dash__action" onClick={() => navigate('/leaves')}>
            <span className="portal-dash__action-icon"><BsCalendarPlus size={18} /></span>
            <span className="portal-dash__action-label">{t('portalDash.actionLeave')}</span>
          </button>
          <button type="button" className="portal-dash__action" onClick={() => navigate('/expenses')}>
            <span className="portal-dash__action-icon"><BsReceipt size={18} /></span>
            <span className="portal-dash__action-label">{t('portalDash.actionExpense')}</span>
          </button>
          <button type="button" className="portal-dash__action" onClick={() => navigate('/requests')}>
            <span className="portal-dash__action-icon"><BsInboxes size={18} /></span>
            <span className="portal-dash__action-label">{t('portalDash.actionRequest')}</span>
          </button>
          <button type="button" className="portal-dash__action" onClick={() => navigate('/leaves')}>
            <span className="portal-dash__action-icon"><BsCalendarCheck size={18} /></span>
            <span className="portal-dash__action-label">{t('portalDash.actionBalance')}</span>
            <span className="portal-dash__action-meta">
              {t('portalDash.balanceDays', { count: remainingDays })}
            </span>
          </button>
        </div>
      </section>

      <AppCard
        title={t('portalDash.announcements')}
        action={(
          <button
            type="button"
            className="portal-btn portal-btn--ghost"
            style={{ minHeight: 36, padding: '0 var(--sp-3)' }}
            onClick={() => navigate('/announcements')}
          >
            {t('portalDash.seeAll')} <BsArrowRight />
          </button>
        )}
      >
        {announcements.length === 0 ? (
          <EmptyState title={t('portalDash.noAnnouncements')} icon={<BsMegaphone size={32} />} />
        ) : (
          announcements.map((a) => (
            <button
              key={a.id}
              type="button"
              className="portal-dash__list-item"
              onClick={() => navigate('/announcements')}
            >
              <span className="portal-dash__list-title">
                {a.is_pinned ? '📌 ' : ''}
                {a.title}
              </span>
              <span className="portal-dash__list-meta">
                {new Date(a.published_at).toLocaleDateString('tr-TR')}
                {a.type === 'urgent' ? ` · ${t('portalDash.urgent')}` : ''}
              </span>
            </button>
          ))
        )}
      </AppCard>

      <AppCard
        title={t('portalDash.pendingWork')}
        action={
          pendingRequests > 0 ? (
            <StatusBadge tone="warning">{pendingRequests}</StatusBadge>
          ) : undefined
        }
      >
        {recentRequests.length === 0 && pendingRequests === 0 ? (
          <EmptyState title={t('portalDash.noPending')} />
        ) : (
          <>
            {recentRequests.map((r) => (
              <button
                key={r.id}
                type="button"
                className="portal-dash__list-item"
                onClick={() => navigate('/requests')}
              >
                <span className="portal-dash__list-title">{r.title}</span>
                <span className="portal-dash__list-meta">
                  {r.request_type?.name || t('portalDash.request')}
                  {' · '}
                  {new Date(r.created_at).toLocaleDateString('tr-TR')}
                </span>
              </button>
            ))}
            {recentRequests.length === 0 && pendingRequests > 0 && (
              <button
                type="button"
                className="portal-dash__list-item"
                onClick={() => navigate('/requests')}
              >
                <span className="portal-dash__list-title">
                  {t('portalDash.pendingCount', { count: pendingRequests })}
                </span>
              </button>
            )}
          </>
        )}
      </AppCard>
    </div>
  );
};

export default DashboardPage;
