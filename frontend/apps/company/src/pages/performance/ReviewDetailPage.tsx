import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { performanceApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import {
  BsArrowLeft,
  BsPersonFill,
  BsStar,
  BsStarFill,
  BsSend,
  BsCheckCircle,
  BsXCircle,
} from 'react-icons/bs';

interface Criteria {
  id: number;
  name: string;
  description?: string;
  weight: number;
  max_score: number;
}

interface Score {
  id: number;
  criteria_id: number;
  criteria: Criteria;
  score: number;
  comment?: string;
}

interface Review {
  id: number;
  period: { id: number; name: string };
  employee: { id: number; name: string; email: string };
  reviewer: { id: number; name: string };
  status: 'draft' | 'submitted' | 'approved' | 'rejected';
  overall_score?: number;
  strengths?: string;
  improvements?: string;
  goals?: string;
  reviewer_comments?: string;
  employee_comments?: string;
  scores: Score[];
  submitted_at?: string;
  approved_at?: string;
  created_at: string;
}

const statusLabels: Record<string, string> = {
  draft: 'Taslak',
  submitted: 'Gönderildi',
  approved: 'Onaylandı',
  rejected: 'Reddedildi',
};

const statusColors: Record<string, string> = {
  draft: 'var(--warning)',
  submitted: 'var(--info)',
  approved: 'var(--success)',
  rejected: 'var(--danger)',
};

const ReviewDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [review, setReview] = useState<Review | null>(null);
  const [loading, setLoading] = useState(true);
  const [allCriteria, setAllCriteria] = useState<Criteria[]>([]);
  const [scores, setScores] = useState<Record<number, { score: number; comment: string }>>({});
  const [comments, setComments] = useState({
    strengths: '',
    improvements: '',
    goals: '',
    reviewer_comments: '',
  });
  const [saving, setSaving] = useState(false);

  const loadData = useCallback(async () => {
    try {
      setLoading(true);
      const [reviewRes, criteriaRes] = await Promise.all([
        performanceApi.reviews.get(Number(id)),
        performanceApi.criteria.list({ active_only: true }),
      ]);
      
      const reviewData = reviewRes.data.data;
      setReview(reviewData);
      setAllCriteria(criteriaRes.data.data || []);
      
      // Init scores from existing data
      const existingScores: Record<number, { score: number; comment: string }> = {};
      reviewData.scores?.forEach((s: Score) => {
        existingScores[s.criteria_id] = { score: s.score, comment: s.comment || '' };
      });
      setScores(existingScores);
      
      setComments({
        strengths: reviewData.strengths || '',
        improvements: reviewData.improvements || '',
        goals: reviewData.goals || '',
        reviewer_comments: reviewData.reviewer_comments || '',
      });
    } catch {
      toast.error('Değerlendirme yüklenemedi');
      navigate('/performance');
    } finally {
      setLoading(false);
    }
  }, [id, navigate]);

  useEffect(() => {
    if (id) {
      loadData();
    }
  }, [id, loadData]);

  

  const handleScoreChange = (criteriaId: number, score: number) => {
    setScores(prev => ({
      ...prev,
      [criteriaId]: { ...prev[criteriaId], score, comment: prev[criteriaId]?.comment || '' },
    }));
  };

  const handleCommentChange = (criteriaId: number, comment: string) => {
    setScores(prev => ({
      ...prev,
      [criteriaId]: { ...prev[criteriaId], score: prev[criteriaId]?.score || 0, comment },
    }));
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      const scoreData = Object.entries(scores).map(([criteriaId, data]) => ({
        criteria_id: Number(criteriaId),
        score: data.score,
        comment: data.comment,
      }));

      await performanceApi.reviews.update(Number(id), {
        ...comments,
        scores: scoreData,
      });
      
      toast.success('Değerlendirme kaydedildi');
      loadData();
    } catch {
      // Error handled by interceptor
    } finally {
      setSaving(false);
    }
  };

  const handleSubmit = async () => {
    try {
      await performanceApi.reviews.submit(Number(id));
      toast.success('Değerlendirme gönderildi');
      loadData();
    } catch {
      // Error handled by interceptor
    }
  };

  const handleApprove = async () => {
    try {
      await performanceApi.reviews.approve(Number(id));
      toast.success('Değerlendirme onaylandı');
      loadData();
    } catch {
      // Error handled by interceptor
    }
  };

  const handleReject = async () => {
    const reason = prompt('Red sebebi:');
    if (reason === null) return;
    try {
      await performanceApi.reviews.reject(Number(id), reason);
      toast.success('Değerlendirme reddedildi');
      loadData();
    } catch {
      // Error handled by interceptor
    }
  };

  if (loading) {
    return <div className="loading-container"><div className="loading-spinner" /></div>;
  }

  if (!review) return null;

  const canEdit = review.status === 'draft';
  const canSubmit = review.status === 'draft' && Object.keys(scores).length > 0;
  const canApprove = review.status === 'submitted';

  return (
    <div className="animate-fade-in">
      {/* Header */}
      <div style={{ marginBottom: '1.5rem' }}>
        <button
          className="btn btn-ghost btn-sm"
          onClick={() => navigate('/performance')}
          style={{ marginBottom: '1rem' }}
        >
          <BsArrowLeft size={16} />
          Geri
        </button>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
          <div>
            <h1 className="page-title">Performans Değerlendirmesi</h1>
            <p className="page-subtitle">{review.employee?.name} • {review.period?.name}</p>
          </div>
          <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
            <div
              style={{
                padding: '0.5rem 1rem',
                borderRadius: 'var(--radius-md)',
                background: statusColors[review.status],
                color: 'white',
                fontWeight: 500,
              }}
            >
              {statusLabels[review.status]}
            </div>
          </div>
        </div>
      </div>

      {/* Info Cards */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem', marginBottom: '1.5rem' }}>
        <div className="card">
          <div className="card-body" style={{ padding: '1rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <div style={{ padding: '0.5rem', background: 'var(--accent-bg)', borderRadius: 'var(--radius-md)' }}>
                <BsPersonFill size={20} style={{ color: 'var(--accent)' }} />
              </div>
              <div>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Çalışan</div>
                <div style={{ fontWeight: 500 }}>{review.employee?.name}</div>
              </div>
            </div>
          </div>
        </div>
        <div className="card">
          <div className="card-body" style={{ padding: '1rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <div style={{ padding: '0.5rem', background: 'var(--info-bg)', borderRadius: 'var(--radius-md)' }}>
                <BsPersonFill size={20} style={{ color: 'var(--info)' }} />
              </div>
              <div>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Değerlendiren</div>
                <div style={{ fontWeight: 500 }}>{review.reviewer?.name}</div>
              </div>
            </div>
          </div>
        </div>
        <div className="card">
          <div className="card-body" style={{ padding: '1rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <div style={{ padding: '0.5rem', background: 'var(--warning-bg)', borderRadius: 'var(--radius-md)' }}>
                <BsStar size={20} style={{ color: 'var(--warning)' }} />
              </div>
              <div>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Genel Puan</div>
                <div style={{ fontWeight: 500, fontSize: '1.25rem' }}>{review.overall_score?.toFixed(1) || '-'}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Criteria Scoring */}
      <div className="card" style={{ marginBottom: '1.5rem' }}>
        <div className="card-header">
          <h3 className="card-title">Kriter Puanlama</h3>
        </div>
        <div className="card-body">
          {allCriteria.length === 0 ? (
            <p style={{ color: 'var(--text-tertiary)', textAlign: 'center', padding: '2rem' }}>
              Henüz kriter tanımlanmamış. Performans sayfasından kriterler oluşturun.
            </p>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              {allCriteria.map(criteria => (
                <div
                  key={criteria.id}
                  style={{
                    padding: '1rem',
                    background: 'var(--bg-tertiary)',
                    borderRadius: 'var(--radius-md)',
                    border: '1px solid var(--border-color)',
                  }}
                >
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '0.5rem' }}>
                    <div>
                      <div style={{ fontWeight: 500 }}>{criteria.name}</div>
                      {criteria.description && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{criteria.description}</div>
                      )}
                    </div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                      Ağırlık: %{criteria.weight}
                    </div>
                  </div>
                  
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.5rem' }}>
                    <span style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Puan:</span>
                    {[...Array(criteria.max_score)].map((_, i) => (
                      <button
                        key={i}
                        type="button"
                        onClick={() => canEdit && handleScoreChange(criteria.id, i + 1)}
                        style={{
                          background: 'none',
                          border: 'none',
                          cursor: canEdit ? 'pointer' : 'default',
                          padding: '0.25rem',
                        }}
                        disabled={!canEdit}
                      >
                        {(scores[criteria.id]?.score || 0) >= i + 1 ? (
                          <BsStarFill size={20} style={{ color: 'var(--warning)' }} />
                        ) : (
                          <BsStar size={20} style={{ color: 'var(--text-tertiary)' }} />
                        )}
                      </button>
                    ))}
                    <span style={{ marginLeft: '0.5rem', fontWeight: 500 }}>
                      {scores[criteria.id]?.score || 0}/{criteria.max_score}
                    </span>
                  </div>

                  {canEdit && (
                    <input
                      type="text"
                      value={scores[criteria.id]?.comment || ''}
                      onChange={(e) => handleCommentChange(criteria.id, e.target.value)}
                      className="form-input"
                      placeholder="Yorum ekleyin (opsiyonel)"
                      style={{ fontSize: '0.875rem' }}
                    />
                  )}
                  {!canEdit && scores[criteria.id]?.comment && (
                    <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', margin: 0 }}>
                      {scores[criteria.id]?.comment}
                    </p>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Comments */}
      <div className="card" style={{ marginBottom: '1.5rem' }}>
        <div className="card-header">
          <h3 className="card-title">Değerlendirme Notları</h3>
        </div>
        <div className="card-body">
          <div className="form-group">
            <label className="form-label">Güçlü Yönler</label>
            <textarea
              value={comments.strengths}
              onChange={(e) => setComments(prev => ({ ...prev, strengths: e.target.value }))}
              className="form-input"
              rows={2}
              disabled={!canEdit}
              placeholder="Çalışanın güçlü yönleri..."
            />
          </div>
          <div className="form-group">
            <label className="form-label">Geliştirilmesi Gerekenler</label>
            <textarea
              value={comments.improvements}
              onChange={(e) => setComments(prev => ({ ...prev, improvements: e.target.value }))}
              className="form-input"
              rows={2}
              disabled={!canEdit}
              placeholder="Geliştirilmesi gereken alanlar..."
            />
          </div>
          <div className="form-group">
            <label className="form-label">Hedefler</label>
            <textarea
              value={comments.goals}
              onChange={(e) => setComments(prev => ({ ...prev, goals: e.target.value }))}
              className="form-input"
              rows={2}
              disabled={!canEdit}
              placeholder="Gelecek dönem hedefleri..."
            />
          </div>
          <div className="form-group">
            <label className="form-label">Genel Yorum</label>
            <textarea
              value={comments.reviewer_comments}
              onChange={(e) => setComments(prev => ({ ...prev, reviewer_comments: e.target.value }))}
              className="form-input"
              rows={2}
              disabled={!canEdit}
              placeholder="Genel değerlendirme yorumu..."
            />
          </div>
        </div>
      </div>

      {/* Actions */}
      <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem' }}>
        {canEdit && (
          <>
            <button className="btn btn-ghost" onClick={handleSave} disabled={saving}>
              {saving ? 'Kaydediliyor...' : 'Kaydet'}
            </button>
            <button className="btn btn-primary" onClick={handleSubmit} disabled={!canSubmit}>
              <BsSend size={16} />
              Gönder
            </button>
          </>
        )}
        {canApprove && (
          <>
            <button className="btn btn-danger" onClick={handleReject}>
              <BsXCircle size={16} />
              Reddet
            </button>
            <button className="btn btn-success" onClick={handleApprove}>
              <BsCheckCircle size={16} />
              Onayla
            </button>
          </>
        )}
      </div>
    </div>
  );
};

export default ReviewDetailPage;

