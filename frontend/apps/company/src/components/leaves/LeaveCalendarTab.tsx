import React, { useEffect, useState, useMemo, useCallback } from 'react';
import { leavesApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsChevronLeft, BsChevronRight, BsCalendar3 } from 'react-icons/bs';

interface CalendarEvent {
  id: number;
  title: string;
  start: string;
  end: string;
  color: string;
  extendedProps: {
    user_id: number;
    user_name: string;
    leave_type: string;
    total_days: number;
    status: string;
  };
}

interface Holiday {
  id: number;
  name: string;
  date: string;
  end_date?: string;
  type: string;
  is_half_day: boolean;
}

const LeaveCalendarTab: React.FC = () => {
  const [currentDate, setCurrentDate] = useState(new Date());
  const [events, setEvents] = useState<CalendarEvent[]>([]);
  const [holidays, setHolidays] = useState<Holiday[]>([]);
  const [loading, setLoading] = useState(true);

  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();

  const loadCalendarData = useCallback(async () => {
    try {
      setLoading(true);
      const startDate = new Date(year, month, 1);
      const endDate = new Date(year, month + 1, 0);

      const [eventsRes, holidaysRes] = await Promise.all([
        leavesApi.calendar.get({
          start_date: startDate.toISOString().split('T')[0],
          end_date: endDate.toISOString().split('T')[0],
        }),
        leavesApi.holidays.getRange({
          start_date: startDate.toISOString().split('T')[0],
          end_date: endDate.toISOString().split('T')[0],
        }),
      ]);

      setEvents(eventsRes.data.data || []);
      setHolidays(holidaysRes.data.data || []);
    } catch {
      toast.error('Takvim verileri yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [year, month]);

  useEffect(() => {
    loadCalendarData();
  }, [loadCalendarData]);

  

  const goToPreviousMonth = () => {
    setCurrentDate(new Date(year, month - 1, 1));
  };

  const goToNextMonth = () => {
    setCurrentDate(new Date(year, month + 1, 1));
  };

  const goToToday = () => {
    setCurrentDate(new Date());
  };

  const monthNames = [
    'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
    'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'
  ];

  const dayNames = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];

  const calendarDays = useMemo(() => {
    const firstDayOfMonth = new Date(year, month, 1);
    const lastDayOfMonth = new Date(year, month + 1, 0);
    const daysInMonth = lastDayOfMonth.getDate();
    
    // Pazartesi = 0, Pazar = 6 için ayarlama
    let startDay = firstDayOfMonth.getDay() - 1;
    if (startDay < 0) startDay = 6;

    const days: Array<{ date: Date | null; isCurrentMonth: boolean }> = [];

    // Previous month days
    const prevMonthLastDay = new Date(year, month, 0).getDate();
    for (let i = startDay - 1; i >= 0; i--) {
      days.push({
        date: new Date(year, month - 1, prevMonthLastDay - i),
        isCurrentMonth: false,
      });
    }

    // Current month days
    for (let i = 1; i <= daysInMonth; i++) {
      days.push({
        date: new Date(year, month, i),
        isCurrentMonth: true,
      });
    }

    // Next month days
    const remainingDays = 42 - days.length; // 6 weeks * 7 days
    for (let i = 1; i <= remainingDays; i++) {
      days.push({
        date: new Date(year, month + 1, i),
        isCurrentMonth: false,
      });
    }

    return days;
  }, [year, month]);

  const getEventsForDate = (date: Date | null): CalendarEvent[] => {
    if (!date) return [];
    const dateStr = date.toISOString().split('T')[0];
    return events.filter((event) => {
      const start = event.start;
      const end = event.end;
      return dateStr >= start && dateStr < end;
    });
  };

  const getHolidayForDate = (date: Date | null): Holiday | undefined => {
    if (!date) return undefined;
    const dateStr = date.toISOString().split('T')[0];
    return holidays.find((holiday) => {
      const start = holiday.date;
      const end = holiday.end_date || holiday.date;
      return dateStr >= start && dateStr <= end;
    });
  };

  const isToday = (date: Date | null): boolean => {
    if (!date) return false;
    const today = new Date();
    return (
      date.getDate() === today.getDate() &&
      date.getMonth() === today.getMonth() &&
      date.getFullYear() === today.getFullYear()
    );
  };

  const isWeekend = (date: Date | null): boolean => {
    if (!date) return false;
    const day = date.getDay();
    return day === 0 || day === 6;
  };

  return (
    <div>
      {/* Calendar Header */}
      <div className="card mb-3">
        <div className="card-body" style={{ padding: 'var(--sp-2) var(--sp-3)' }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--sp-2)' }}>
              <button type="button" className="btn btn-ghost btn-icon btn-sm" onClick={goToPreviousMonth}>
                <BsChevronLeft />
              </button>
              <h3 style={{ margin: 0, fontSize: 'var(--fs-section)', fontWeight: 600, minWidth: 150, textAlign: 'center' }}>
                {monthNames[month]} {year}
              </h3>
              <button type="button" className="btn btn-ghost btn-icon btn-sm" onClick={goToNextMonth}>
                <BsChevronRight />
              </button>
            </div>
            <button type="button" className="btn btn-secondary btn-sm" onClick={goToToday}>
              Bugün
            </button>
          </div>
        </div>
      </div>

      {/* Calendar Grid */}
      {loading ? (
        <div className="page-loading">
          <div className="loading-spinner" />
        </div>
      ) : (
        <div className="card">
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: '700px' }}>
              <thead>
                <tr>
                  {dayNames.map((day, index) => (
                    <th
                      key={day}
                      style={{
                        padding: 'var(--sp-2) var(--sp-1)',
                        textAlign: 'center',
                        fontWeight: 500,
                        fontSize: 'var(--fs-caption)',
                        color: index >= 5 ? 'var(--danger)' : 'var(--text-secondary)',
                        borderBottom: '1px solid var(--border-primary)',
                        background: 'var(--bg-secondary)',
                      }}
                    >
                      {day}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {Array.from({ length: 6 }).map((_, weekIndex) => (
                  <tr key={weekIndex}>
                    {calendarDays.slice(weekIndex * 7, weekIndex * 7 + 7).map((day, dayIndex) => {
                      const dayEvents = getEventsForDate(day.date);
                      const holiday = getHolidayForDate(day.date);
                      const today = isToday(day.date);
                      const weekend = isWeekend(day.date);

                      return (
                        <td
                          key={dayIndex}
                          style={{
                            padding: 2,
                            verticalAlign: 'top',
                            height: 88,
                            borderBottom: '1px solid var(--border-primary)',
                            borderRight: '1px solid var(--border-primary)',
                            background: holiday
                              ? 'rgba(244, 63, 94, 0.05)'
                              : weekend
                              ? 'var(--bg-secondary)'
                              : day.isCurrentMonth
                              ? 'var(--bg-primary)'
                              : 'var(--bg-tertiary)',
                            opacity: day.isCurrentMonth ? 1 : 0.5,
                          }}
                        >
                          <div
                            style={{
                              display: 'flex',
                              flexDirection: 'column',
                              height: '100%',
                              gap: '0.25rem',
                            }}
                          >
                            {/* Date number */}
                            <div
                              style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                padding: '0.25rem',
                              }}
                            >
                              <span
                                style={{
                                  display: 'inline-flex',
                                  alignItems: 'center',
                                  justifyContent: 'center',
                                  width: '24px',
                                  height: '24px',
                                  borderRadius: '50%',
                                  fontSize: '0.75rem',
                                  fontWeight: today ? 600 : 400,
                                  background: today ? 'var(--primary)' : 'transparent',
                                  color: today ? 'white' : weekend ? 'var(--danger)' : 'var(--text-primary)',
                                }}
                              >
                                {day.date?.getDate()}
                              </span>
                              {holiday && (
                                <span
                                  style={{
                                    fontSize: '0.625rem',
                                    color: 'var(--danger)',
                                    fontWeight: 500,
                                  }}
                                  title={holiday.name}
                                >
                                  Tatil
                                </span>
                              )}
                            </div>

                            {/* Events */}
                            <div
                              style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: '2px',
                                overflow: 'hidden',
                                flex: 1,
                              }}
                            >
                              {dayEvents.slice(0, 3).map((event) => (
                                <div
                                  key={event.id}
                                  style={{
                                    fontSize: '0.625rem',
                                    padding: '2px 4px',
                                    borderRadius: '2px',
                                    background: event.color || 'var(--primary)',
                                    color: 'white',
                                    whiteSpace: 'nowrap',
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis',
                                  }}
                                  title={`${event.extendedProps.user_name} - ${event.extendedProps.leave_type}`}
                                >
                                  {event.extendedProps.user_name}
                                </div>
                              ))}
                              {dayEvents.length > 3 && (
                                <div
                                  style={{
                                    fontSize: '0.625rem',
                                    color: 'var(--text-tertiary)',
                                    padding: '0 4px',
                                  }}
                                >
                                  +{dayEvents.length - 3} daha
                                </div>
                              )}
                            </div>
                          </div>
                        </td>
                      );
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Legend */}
      <div className="card mt-3">
        <div className="card-body" style={{ padding: '0.75rem 1rem' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '1.5rem', flexWrap: 'wrap' }}>
            <span style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', fontWeight: 500 }}>Gösterge:</span>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <span style={{ width: 12, height: 12, borderRadius: 2, background: '#10b981' }} />
              <span style={{ fontSize: '0.75rem' }}>Yıllık İzin</span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <span style={{ width: 12, height: 12, borderRadius: 2, background: '#f59e0b' }} />
              <span style={{ fontSize: '0.75rem' }}>Mazeret İzni</span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <span style={{ width: 12, height: 12, borderRadius: 2, background: '#ef4444' }} />
              <span style={{ fontSize: '0.75rem' }}>Hastalık İzni</span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <span style={{ width: 12, height: 12, borderRadius: 2, background: '#8b5cf6' }} />
              <span style={{ fontSize: '0.75rem' }}>Diğer</span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <BsCalendar3 size={12} style={{ color: 'var(--danger)' }} />
              <span style={{ fontSize: '0.75rem' }}>Tatil</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default LeaveCalendarTab;

