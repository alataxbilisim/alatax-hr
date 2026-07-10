import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { recruitmentApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { DataTable } from '../../components/ui';
import {
  BsArrowLeft,
  BsPersonBadge,
  BsDownload,
  BsTag,
  BsStarFill,
  BsStar,
  BsEnvelope,
  BsTelephone,
  BsGeoAlt,
  BsX,
} from 'react-icons/bs';

interface Candidate {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  city: string | null;
  experience_years: number | null;
  education_level: string | null;
  skills: string[];
  tags: string[];
  rating: number | null;
  status: string;
  cv_path: string | null;
  last_application_date: string;
  created_at: string;
}

const statusLabels: Record<string, { label: string; class: string }> = {
  new: { label: 'Yeni', class: 'badge-secondary' },
  reviewing: { label: 'İnceleniyor', class: 'badge-warning' },
  shortlisted: { label: 'Ön Seçim', class: 'badge-info' },
  interview_scheduled: { label: 'Mülakat Planlandı', class: 'badge-info' },
  interviewed: { label: 'Mülakat Yapıldı', class: 'badge-primary' },
  offer_sent: { label: 'Teklif Gönderildi', class: 'badge-primary' },
  hired: { label: 'İşe Alındı', class: 'badge-success' },
  rejected: { label: 'Reddedildi', class: 'badge-danger' },
  withdrawn: { label: 'Çekildi', class: 'badge-secondary' },
};

const CvPoolPage: React.FC = () => {
  const navigate = useNavigate();
  const [candidates, setCandidates] = useState<Candidate[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  
  // Tag modal
  const [tagModalOpen, setTagModalOpen] = useState(false);
  const [newTag, setNewTag] = useState('');
  const [tagLoading, setTagLoading] = useState(false);

  const loadCandidates = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = {};
      if (search) params.search = search;

      const response = await recruitmentApi.cvPool.list(params);
      setCandidates(response.data.data || []);
    } catch {
      toast.error('CV havuzu yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [search]);

  useEffect(() => {
    loadCandidates();
  }, [loadCandidates]);

  

  const handleRate = async (candidate: Candidate, rating: number) => {
    try {
      await recruitmentApi.cvPool.rate(candidate.id, rating);
      toast.success('Puan kaydedildi');
      loadCandidates();
    } catch {
      toast.error('Puanlama başarısız');
    }
  };

  const handleBulkTag = async () => {
    if (!newTag.trim() || selectedIds.length === 0) return;

    setTagLoading(true);
    try {
      await recruitmentApi.cvPool.bulkTag(selectedIds, newTag.trim());
      toast.success(`${selectedIds.length} adaya etiket eklendi`);
      setTagModalOpen(false);
      setNewTag('');
      setSelectedIds([]);
      loadCandidates();
    } catch {
      toast.error('Etiket eklenemedi');
    } finally {
      setTagLoading(false);
    }
  };

  const handleRemoveTag = async (candidateId: number, tag: string) => {
    try {
      await recruitmentApi.cvPool.removeTag(candidateId, tag);
      toast.success('Etiket kaldırıldı');
      loadCandidates();
    } catch {
      toast.error('Etiket kaldırılamadı');
    }
  };

  const handleSelectOne = (id: number, checked: boolean) => {
    if (checked) {
      setSelectedIds([...selectedIds, id]);
    } else {
      setSelectedIds(selectedIds.filter(i => i !== id));
    }
  };

  const columns = [
    {
      key: 'select',
      title: '',
      width: '40px',
      render: (c: Candidate) => (
        <input
          type="checkbox"
          checked={selectedIds.includes(c.id)}
          onChange={(e) => handleSelectOne(c.id, e.target.checked)}
        />
      ),
    },
    {
      key: 'name',
      title: 'Aday',
      render: (c: Candidate) => (
        <div>
          <div style={{ fontWeight: 500, color: 'var(--text-primary)' }}>{c.name}</div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <BsEnvelope size={10} /> {c.email}
          </div>
          {c.phone && (
            <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <BsTelephone size={10} /> {c.phone}
            </div>
          )}
        </div>
      ),
    },
    {
      key: 'location',
      title: 'Lokasyon',
      width: '120px',
      render: (c: Candidate) => c.city ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.25rem', color: 'var(--text-secondary)', fontSize: '0.875rem' }}>
          <BsGeoAlt size={12} /> {c.city}
        </div>
      ) : '-',
    },
    {
      key: 'experience',
      title: 'Deneyim',
      width: '100px',
      render: (c: Candidate) => c.experience_years ? `${c.experience_years} yıl` : '-',
    },
    {
      key: 'tags',
      title: 'Etiketler',
      render: (c: Candidate) => (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.25rem' }}>
          {(c.tags || []).map((tag, idx) => (
            <span
              key={idx}
              className="badge badge-info"
              style={{ display: 'flex', alignItems: 'center', gap: '0.25rem', fontSize: '0.6875rem' }}
            >
              {tag}
              <button
                onClick={(e) => {
                  e.stopPropagation();
                  handleRemoveTag(c.id, tag);
                }}
                style={{ background: 'none', border: 'none', padding: 0, cursor: 'pointer', display: 'flex' }}
              >
                <BsX size={12} />
              </button>
            </span>
          ))}
        </div>
      ),
    },
    {
      key: 'rating',
      title: 'Puan',
      width: '120px',
      render: (c: Candidate) => (
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.125rem' }}>
          {[1, 2, 3, 4, 5].map((star) => (
            <button
              key={star}
              onClick={() => handleRate(c, star)}
              style={{
                background: 'none',
                border: 'none',
                padding: 0,
                cursor: 'pointer',
                color: star <= (c.rating || 0) ? '#f59e0b' : 'var(--text-muted)',
              }}
            >
              {star <= (c.rating || 0) ? <BsStarFill size={14} /> : <BsStar size={14} />}
            </button>
          ))}
        </div>
      ),
    },
    {
      key: 'status',
      title: 'Son Durum',
      width: '140px',
      render: (c: Candidate) => {
        const s = statusLabels[c.status] || { label: c.status, class: 'badge-secondary' };
        return <span className={`badge ${s.class}`}>{s.label}</span>;
      },
    },
    {
      key: 'cv',
      title: 'CV',
      width: '60px',
      align: 'center' as const,
      render: (c: Candidate) => c.cv_path ? (
        <a
          href={c.cv_path}
          target="_blank"
          rel="noopener noreferrer"
          className="btn btn-ghost btn-icon btn-sm"
          title="CV İndir"
        >
          <BsDownload />
        </a>
      ) : '-',
    },
    {
      key: 'date',
      title: 'Son Başvuru',
      width: '120px',
      render: (c: Candidate) => new Date(c.last_application_date).toLocaleDateString('tr-TR'),
    },
  ];

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div className="page-header-content">
          <button
            className="btn btn-ghost btn-sm"
            onClick={() => navigate('/recruitment/positions')}
            style={{ marginBottom: '0.5rem' }}
          >
            <BsArrowLeft /> Geri
          </button>
          <h1>CV Havuzu</h1>
          <p>Tüm adayların merkezi yönetimi</p>
        </div>
        <div className="page-header-actions">
          {selectedIds.length > 0 && (
            <button className="btn btn-secondary" onClick={() => setTagModalOpen(true)}>
              <BsTag size={16} /> Etiket Ekle ({selectedIds.length})
            </button>
          )}
        </div>
      </div>

      {/* Data Table */}
      <DataTable
        columns={columns}
        data={candidates}
        loading={loading}
        emptyMessage="Aday bulunamadı"
        emptyIcon={<BsPersonBadge size={32} />}
        searchValue={search}
        onSearchChange={(val) => setSearch(val)}
        searchPlaceholder="Aday ara..."
      />

      {/* Tag Modal */}
      {tagModalOpen && (
        <div className="modal-overlay" onClick={() => setTagModalOpen(false)}>
          <div className="modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '400px' }}>
            <div className="modal-header">
              <h3>Etiket Ekle</h3>
              <button className="btn btn-ghost btn-icon" onClick={() => setTagModalOpen(false)}>
                <BsX size={20} />
              </button>
            </div>
            <div className="modal-body">
              <p style={{ marginBottom: '1rem', color: 'var(--text-secondary)' }}>
                {selectedIds.length} adaya etiket eklenecek
              </p>
              <div className="form-group">
                <label className="form-label">Etiket</label>
                <input
                  type="text"
                  className="form-control"
                  value={newTag}
                  onChange={(e) => setNewTag(e.target.value)}
                  placeholder="Örn: Yazılım, İstanbul, Kıdemli"
                  onKeyPress={(e) => e.key === 'Enter' && handleBulkTag()}
                />
              </div>
            </div>
            <div className="modal-footer">
              <button className="btn btn-secondary" onClick={() => setTagModalOpen(false)}>
                İptal
              </button>
              <button className="btn btn-primary" onClick={handleBulkTag} disabled={tagLoading || !newTag.trim()}>
                {tagLoading ? 'Ekleniyor...' : 'Ekle'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default CvPoolPage;

