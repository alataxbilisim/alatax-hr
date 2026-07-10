import React, { useEffect, useState, useCallback } from 'react';
import { leavesApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { ConfirmDialog } from '../ui';
import HolidayForm from './HolidayForm';
import { BsPlus, BsPencil, BsTrash, BsCalendar3, BsFilter } from 'react-icons/bs';

interface Holiday {
  id: number;
  name: string;
  date: string;
  end_date?: string;
  type: 'national' | 'company' | 'regional';
  country_code?: string;
  is_recurring: boolean;
  is_half_day: boolean;
  description?: string;
  is_active: boolean;
}

const HolidaysTab: React.FC = () => {
  const [holidays, setHolidays] = useState<Holiday[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
  const [typeFilter, setTypeFilter] = useState<string>('');

  // Form modal
  const [formOpen, setFormOpen] = useState(false);
  const [selectedHoliday, setSelectedHoliday] = useState<Holiday | undefined>();

  // Delete dialog
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [holidayToDelete, setHolidayToDelete] = useState<Holiday | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const loadHolidays = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = { year: selectedYear };
      if (typeFilter) params.type = typeFilter;
      
      const response = await leavesApi.holidays.list(params);
      setHolidays(response.data.data || []);
    } catch {
      toast.error('Tatiller yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [selectedYear, typeFilter]);

  useEffect(() => {
    loadHolidays();
  }, [loadHolidays]);

  

  const handleCreate = () => {
    setSelectedHoliday(undefined);
    setFormOpen(true);
  };

  const handleEdit = (holiday: Holiday) => {
    setSelectedHoliday(holiday);
    setFormOpen(true);
  };

  const handleDelete = (holiday: Holiday) => {
    setHolidayToDelete(holiday);
    setDeleteDialogOpen(true);
  };

  const confirmDelete = async () => {
    if (!holidayToDelete) return;

    setDeleteLoading(true);
    try {
      await leavesApi.holidays.delete(holidayToDelete.id);
      toast.success('Tatil silindi');
      setDeleteDialogOpen(false);
      setHolidayToDelete(null);
      loadHolidays();
    } catch {
      toast.error('Tatil silinemedi');
    } finally {
      setDeleteLoading(false);
    }
  };

  const getTypeBadge = (type: string) => {
    const typeMap: Record<string, { label: string; class: string }> = {
      national: { label: 'Resmi', class: 'badge-danger' },
      company: { label: 'Şirket', class: 'badge-primary' },
      regional: { label: 'Bölgesel', class: 'badge-warning' },
    };
    const t = typeMap[type] || { label: type, class: 'badge-secondary' };
    return <span className={`badge ${t.class}`}>{t.label}</span>;
  };

  const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('tr-TR', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
    });
  };

  const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - 2 + i);

  return (
    <div>
      {/* Header */}
      <div className="card mb-3">
        <div className="card-body" style={{ padding: '0.75rem 1rem' }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '1rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <BsFilter size={16} style={{ color: 'var(--text-tertiary)' }} />
                <select
                  className="form-select"
                  style={{ width: 'auto', minWidth: '100px' }}
                  value={selectedYear}
                  onChange={(e) => setSelectedYear(Number(e.target.value))}
                >
                  {years.map((year) => (
                    <option key={year} value={year}>{year}</option>
                  ))}
                </select>
              </div>
              <select
                className="form-select"
                style={{ width: 'auto', minWidth: '150px' }}
                value={typeFilter}
                onChange={(e) => setTypeFilter(e.target.value)}
              >
                <option value="">Tüm Tipler</option>
                <option value="national">Resmi Tatil</option>
                <option value="company">Şirket Tatili</option>
                <option value="regional">Bölgesel</option>
              </select>
            </div>
            <button className="btn btn-primary" onClick={handleCreate}>
              <BsPlus size={18} />
              Tatil Ekle
            </button>
          </div>
        </div>
      </div>

      {/* Content */}
      {loading ? (
        <div className="page-loading">
          <div className="loading-spinner" />
        </div>
      ) : holidays.length === 0 ? (
        <div className="card">
          <div className="card-body empty-state">
            <BsCalendar3 size={48} style={{ color: 'var(--text-muted)' }} />
            <h3 className="empty-state-title mt-3">Tatil Bulunamadı</h3>
            <p className="empty-state-text">
              {selectedYear} yılı için tanımlı tatil yok.
            </p>
            <button className="btn btn-primary mt-2" onClick={handleCreate}>
              <BsPlus /> Tatil Ekle
            </button>
          </div>
        </div>
      ) : (
        <div className="card">
          <div className="table-container">
            <table className="table">
              <thead>
                <tr>
                  <th>Tatil Adı</th>
                  <th>Tarih</th>
                  <th>Tip</th>
                  <th>Tekrarlayan</th>
                  <th>Yarım Gün</th>
                  <th>Durum</th>
                  <th style={{ textAlign: 'right' }}>İşlemler</th>
                </tr>
              </thead>
              <tbody>
                {holidays.map((holiday) => (
                  <tr key={holiday.id}>
                    <td>
                      <div>
                        <span style={{ fontWeight: 500, color: 'var(--text-primary)' }}>{holiday.name}</span>
                        {holiday.description && (
                          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                            {holiday.description}
                          </div>
                        )}
                      </div>
                    </td>
                    <td>
                      <div style={{ fontSize: '0.8125rem' }}>
                        <div>{formatDate(holiday.date)}</div>
                        {holiday.end_date && holiday.end_date !== holiday.date && (
                          <div style={{ color: 'var(--text-tertiary)' }}>
                            → {formatDate(holiday.end_date)}
                          </div>
                        )}
                      </div>
                    </td>
                    <td>{getTypeBadge(holiday.type)}</td>
                    <td>
                      <span className={`badge ${holiday.is_recurring ? 'badge-success' : 'badge-secondary'}`}>
                        {holiday.is_recurring ? 'Evet' : 'Hayır'}
                      </span>
                    </td>
                    <td>
                      <span className={`badge ${holiday.is_half_day ? 'badge-warning' : 'badge-secondary'}`}>
                        {holiday.is_half_day ? 'Evet' : 'Hayır'}
                      </span>
                    </td>
                    <td>
                      <span className={`badge ${holiday.is_active ? 'badge-success' : 'badge-secondary'}`}>
                        {holiday.is_active ? 'Aktif' : 'Pasif'}
                      </span>
                    </td>
                    <td style={{ textAlign: 'right' }}>
                      <div style={{ display: 'flex', gap: '0.25rem', justifyContent: 'flex-end' }}>
                        <button
                          className="btn btn-ghost btn-icon btn-sm"
                          onClick={() => handleEdit(holiday)}
                          title="Düzenle"
                        >
                          <BsPencil />
                        </button>
                        {holiday.type !== 'national' && (
                          <button
                            className="btn btn-ghost btn-icon btn-sm"
                            onClick={() => handleDelete(holiday)}
                            title="Sil"
                            style={{ color: 'var(--danger)' }}
                          >
                            <BsTrash />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Form Modal */}
      <HolidayForm
        isOpen={formOpen}
        onClose={() => setFormOpen(false)}
        onSuccess={loadHolidays}
        holiday={selectedHoliday}
      />

      {/* Delete Dialog */}
      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
        onConfirm={confirmDelete}
        title="Tatili Sil"
        message={`"${holidayToDelete?.name}" tatilini silmek istediğinize emin misiniz?`}
        confirmText="Sil"
        variant="danger"
        loading={deleteLoading}
      />
    </div>
  );
};

export default HolidaysTab;

