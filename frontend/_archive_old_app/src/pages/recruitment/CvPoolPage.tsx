import React, { useState, useEffect, useCallback } from 'react';
import toast from 'react-hot-toast';
import api from '../../services/api';

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
  last_application_date: string | null;
  created_at: string;
}

const CvPoolPage: React.FC = () => {
  const [candidates, setCandidates] = useState<Candidate[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [cityFilter, setCityFilter] = useState('');
  const [experienceFilter, setExperienceFilter] = useState('');
  const [selectedCandidates, setSelectedCandidates] = useState<number[]>([]);
  const [showTagModal, setShowTagModal] = useState(false);
  const [newTag, setNewTag] = useState('');

  const loadCandidates = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, string> = {};
      if (searchQuery) params.search = searchQuery;
      if (cityFilter) params.city = cityFilter;
      if (experienceFilter) params.experience = experienceFilter;
      
      const response = await api.get('/api/v1/recruitment/cv-pool', { params });
      setCandidates(response.data.data || []);
    } catch (error) {
      console.error('Adaylar yüklenemedi:', error);
    } finally {
      setLoading(false);
    }
  }, [searchQuery, cityFilter, experienceFilter]);

  useEffect(() => {
    loadCandidates();
  }, [loadCandidates]);

  const handleSelectCandidate = (id: number) => {
    setSelectedCandidates((prev) =>
      prev.includes(id) ? prev.filter((c) => c !== id) : [...prev, id]
    );
  };

  const handleSelectAll = () => {
    if (selectedCandidates.length === candidates.length) {
      setSelectedCandidates([]);
    } else {
      setSelectedCandidates(candidates.map((c) => c.id));
    }
  };

  const handleAddTag = async () => {
    if (!newTag.trim() || selectedCandidates.length === 0) return;
    
    try {
      await api.post('/api/v1/recruitment/cv-pool/bulk-tag', {
        candidate_ids: selectedCandidates,
        tag: newTag.trim(),
      });
      toast.success('Etiket eklendi');
      setShowTagModal(false);
      setNewTag('');
      setSelectedCandidates([]);
      loadCandidates();
    } catch (error) {
      toast.error('Etiket eklenemedi');
    }
  };

  const handleRemoveTag = async (candidateId: number, tag: string) => {
    try {
      await api.delete(`/api/v1/recruitment/cv-pool/${candidateId}/tag`, {
        data: { tag },
      });
      toast.success('Etiket kaldırıldı');
      loadCandidates();
    } catch (error) {
      toast.error('Etiket kaldırılamadı');
    }
  };

  const handleRateCandidate = async (candidateId: number, rating: number) => {
    try {
      await api.put(`/api/v1/recruitment/cv-pool/${candidateId}/rate`, { rating });
      toast.success('Puan güncellendi');
      loadCandidates();
    } catch (error) {
      toast.error('Puan güncellenemedi');
    }
  };

  const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('tr-TR');
  };

  const tagColors = [
    'bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-red-500',
    'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-teal-500',
  ];

  const getTagColor = (tag: string) => {
    const index = tag.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
    return tagColors[index % tagColors.length];
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">CV Havuzu</h1>
          <p className="page-subtitle">{candidates.length} aday</p>
        </div>
        {selectedCandidates.length > 0 && (
          <div className="flex gap-2">
            <button
              className="btn btn-outline-primary"
              onClick={() => setShowTagModal(true)}
            >
              <i className="bi bi-tag me-2"></i>
              Etiketle ({selectedCandidates.length})
            </button>
          </div>
        )}
      </div>

      {/* Filters */}
      <div className="card mb-4">
        <div className="card-body">
          <div className="row g-3">
            <div className="col-md-4">
              <div className="input-icon">
                <i className="bi bi-search"></i>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Ad, e-posta, beceri..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
            </div>
            <div className="col-md-3">
              <select
                className="form-select"
                value={cityFilter}
                onChange={(e) => setCityFilter(e.target.value)}
              >
                <option value="">Tüm Şehirler</option>
                <option value="istanbul">İstanbul</option>
                <option value="ankara">Ankara</option>
                <option value="izmir">İzmir</option>
                <option value="bursa">Bursa</option>
                <option value="antalya">Antalya</option>
              </select>
            </div>
            <div className="col-md-3">
              <select
                className="form-select"
                value={experienceFilter}
                onChange={(e) => setExperienceFilter(e.target.value)}
              >
                <option value="">Tüm Deneyimler</option>
                <option value="0-1">0-1 Yıl</option>
                <option value="2-5">2-5 Yıl</option>
                <option value="5-10">5-10 Yıl</option>
                <option value="10+">10+ Yıl</option>
              </select>
            </div>
            <div className="col-md-2">
              <button className="btn btn-outline-secondary w-100" onClick={loadCandidates}>
                <i className="bi bi-arrow-clockwise me-2"></i>
                Yenile
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Candidates Grid */}
      {loading ? (
        <div className="text-center py-8">
          <div className="spinner-border text-primary" role="status">
            <span className="visually-hidden">Yükleniyor...</span>
          </div>
        </div>
      ) : candidates.length === 0 ? (
        <div className="card">
          <div className="card-body text-center py-12">
            <div className="text-6xl mb-4">👥</div>
            <h3 className="text-xl font-semibold mb-2">CV havuzu boş</h3>
            <p className="text-[var(--text-secondary)]">
              Başvurular geldiğinde adaylar burada listelenecek
            </p>
          </div>
        </div>
      ) : (
        <>
          {/* Select All */}
          <div className="mb-3">
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={selectedCandidates.length === candidates.length}
                onChange={handleSelectAll}
                className="form-check-input"
              />
              <span className="text-sm">Tümünü Seç</span>
            </label>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {candidates.map((candidate) => (
              <div
                key={candidate.id}
                className={`card transition-all ${
                  selectedCandidates.includes(candidate.id)
                    ? 'ring-2 ring-[var(--primary)]'
                    : ''
                }`}
              >
                <div className="card-body">
                  <div className="flex items-start gap-3">
                    <input
                      type="checkbox"
                      checked={selectedCandidates.includes(candidate.id)}
                      onChange={() => handleSelectCandidate(candidate.id)}
                      className="form-check-input mt-1"
                    />
                    <div
                      className="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-lg flex-shrink-0"
                      style={{ background: 'var(--primary)' }}
                    >
                      {candidate.name.charAt(0).toUpperCase()}
                    </div>
                    <div className="flex-1 min-w-0">
                      <h3 className="font-semibold truncate">{candidate.name}</h3>
                      <p className="text-sm text-[var(--text-secondary)] truncate">
                        {candidate.email}
                      </p>
                      {candidate.city && (
                        <p className="text-sm text-[var(--text-muted)]">
                          <i className="bi bi-geo-alt me-1"></i>
                          {candidate.city}
                        </p>
                      )}
                    </div>
                  </div>

                  {/* Rating */}
                  <div className="flex items-center gap-1 mt-3">
                    {[1, 2, 3, 4, 5].map((star) => (
                      <button
                        key={star}
                        className="text-lg hover:scale-110 transition-transform"
                        onClick={() => handleRateCandidate(candidate.id, star)}
                      >
                        <i
                          className={`bi bi-star${
                            star <= (candidate.rating || 0) ? '-fill text-yellow-500' : ' text-gray-400'
                          }`}
                        ></i>
                      </button>
                    ))}
                  </div>

                  {/* Skills */}
                  {candidate.skills && candidate.skills.length > 0 && (
                    <div className="flex flex-wrap gap-1 mt-3">
                      {candidate.skills.slice(0, 3).map((skill, i) => (
                        <span
                          key={i}
                          className="text-xs px-2 py-1 rounded bg-[var(--surface-secondary)]"
                        >
                          {skill}
                        </span>
                      ))}
                      {candidate.skills.length > 3 && (
                        <span className="text-xs px-2 py-1 text-[var(--text-muted)]">
                          +{candidate.skills.length - 3}
                        </span>
                      )}
                    </div>
                  )}

                  {/* Tags */}
                  {candidate.tags && candidate.tags.length > 0 && (
                    <div className="flex flex-wrap gap-1 mt-2">
                      {candidate.tags.map((tag, i) => (
                        <span
                          key={i}
                          className={`text-xs px-2 py-1 rounded text-white ${getTagColor(tag)} flex items-center gap-1`}
                        >
                          {tag}
                          <button
                            className="hover:text-red-200"
                            onClick={() => handleRemoveTag(candidate.id, tag)}
                          >
                            <i className="bi bi-x"></i>
                          </button>
                        </span>
                      ))}
                    </div>
                  )}

                  {/* Meta */}
                  <div className="flex items-center justify-between mt-3 pt-3 border-t border-[var(--border-primary)]">
                    <span className="text-xs text-[var(--text-muted)]">
                      {candidate.experience_years
                        ? `${candidate.experience_years} yıl deneyim`
                        : 'Deneyim belirtilmemiş'}
                    </span>
                    {candidate.cv_path && (
                      <a
                        href={candidate.cv_path}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="btn btn-sm btn-outline-primary"
                      >
                        <i className="bi bi-download"></i>
                      </a>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </>
      )}

      {/* Tag Modal */}
      {showTagModal && (
        <div className="modal-backdrop show" onClick={() => setShowTagModal(false)}>
          <div className="modal show d-block" tabIndex={-1}>
            <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">Etiket Ekle</h5>
                  <button type="button" className="btn-close" onClick={() => setShowTagModal(false)}></button>
                </div>
                <div className="modal-body">
                  <p className="text-[var(--text-secondary)] mb-3">
                    {selectedCandidates.length} adaya etiket ekle
                  </p>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="Etiket adı"
                    value={newTag}
                    onChange={(e) => setNewTag(e.target.value)}
                    onKeyPress={(e) => e.key === 'Enter' && handleAddTag()}
                  />
                  <div className="mt-3">
                    <p className="text-sm text-[var(--text-muted)] mb-2">Önerilen etiketler:</p>
                    <div className="flex flex-wrap gap-2">
                      {['Yazılım', 'Pazarlama', 'Satış', 'İK', 'Finans', 'Mühendislik'].map((tag) => (
                        <button
                          key={tag}
                          className="text-xs px-2 py-1 rounded border border-[var(--border-primary)] hover:bg-[var(--surface-secondary)]"
                          onClick={() => setNewTag(tag)}
                        >
                          {tag}
                        </button>
                      ))}
                    </div>
                  </div>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-secondary" onClick={() => setShowTagModal(false)}>
                    İptal
                  </button>
                  <button
                    type="button"
                    className="btn btn-primary"
                    onClick={handleAddTag}
                    disabled={!newTag.trim()}
                  >
                    <i className="bi bi-tag me-2"></i>
                    Ekle
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      <style>{`
        .modal-backdrop.show {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.5);
          backdrop-filter: blur(4px);
          z-index: 1050;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .modal.show { position: relative; z-index: 1051; }
        .modal-dialog { margin: 0; }
        .modal-content {
          background: var(--surface-primary);
          border: 1px solid var(--border-primary);
          border-radius: 12px;
        }
        .modal-header { border-bottom: 1px solid var(--border-primary); }
        .modal-footer { border-top: 1px solid var(--border-primary); }
      `}</style>
    </div>
  );
};

export default CvPoolPage;

