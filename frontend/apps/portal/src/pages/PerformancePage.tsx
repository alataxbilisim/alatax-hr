import React, { useCallback, useEffect, useState } from 'react';
import { portalApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsGraphUp, BsListCheck, BsChatDots, BsPlus, BsX } from 'react-icons/bs';

interface Review {
  id: number;
  period: {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
  };
  reviewer: {
    id: number;
    name: string;
  };
  status: string;
  overall_score: number | null;
  submitted_at: string | null;
  approved_at: string | null;
  created_at: string;
}

interface Objective {
  id: number;
  title: string;
  description: string;
  status: string;
  progress: number;
  key_results: Array<{
    id: number;
    title: string;
    current_value: number;
    target_value: number;
    status: string;
  }>;
}

interface Feedback {
  id: number;
  from_user: { name: string } | null;
  type: string;
  content: string;
  is_anonymous: boolean;
  created_at: string;
}

const PerformancePage: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'reviews' | 'okrs' | 'feedbacks'>('reviews');
  const [reviews, setReviews] = useState<Review[]>([]);
  const [okrs, setOkrs] = useState<Objective[]>([]);
  const [feedbacks, setFeedbacks] = useState<Feedback[]>([]);
  const [loading, setLoading] = useState(true);
  
  // Feedback form
  const [showFeedbackModal, setShowFeedbackModal] = useState(false);
  const [feedbackForm, setFeedbackForm] = useState({
    employee_id: '',
    type: 'appreciation',
    content: '',
    is_anonymous: false,
  });
  const [submitting, setSubmitting] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      if (activeTab === 'reviews') {
        const response = await portalApi.performance.reviews.list();
        setReviews(response.data.data.data || []);
      } else if (activeTab === 'okrs') {
        const response = await portalApi.performance.okrs.list({ active_only: true });
        setOkrs(response.data.data.data || []);
      } else if (activeTab === 'feedbacks') {
        const response = await portalApi.performance.feedbacks.list();
        setFeedbacks(response.data.data.data || []);
      }
    } catch {
      toast.error('Veriler yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [activeTab]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const getStatusBadge = (status: string) => {
    const statusMap: Record<string, { label: string; class: string }> = {
      draft: { label: 'Taslak', class: 'pending' },
      submitted: { label: 'Gönderildi', class: 'pending' },
      approved: { label: 'Onaylandı', class: 'approved' },
      rejected: { label: 'Reddedildi', class: 'rejected' },
      in_progress: { label: 'Devam Ediyor', class: 'pending' },
      completed: { label: 'Tamamlandı', class: 'approved' },
    };
    const s = statusMap[status] || { label: status, class: '' };
    return <span className={`request-status ${s.class}`}>{s.label}</span>;
  };

  const getFeedbackTypeBadge = (type: string) => {
    const typeMap: Record<string, { label: string; color: string }> = {
      appreciation: { label: 'Takdir', color: 'success' },
      suggestion: { label: 'Öneri', color: 'info' },
      concern: { label: 'Endişe', color: 'warning' },
      other: { label: 'Diğer', color: 'secondary' },
    };
    const t = typeMap[type] || { label: type, color: 'secondary' };
    return <span className={`badge bg-${t.color}`}>{t.label}</span>;
  };

  const handleSubmitFeedback = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!feedbackForm.content) {
      toast.error('Lütfen geri bildirim içeriği yazın');
      return;
    }

    setSubmitting(true);
    try {
      await portalApi.performance.feedbacks.create({
        employee_id: feedbackForm.employee_id ? Number(feedbackForm.employee_id) : 0,
        type: feedbackForm.type,
        content: feedbackForm.content,
        is_anonymous: feedbackForm.is_anonymous,
      });
      toast.success('Geri bildirim gönderildi');
      setShowFeedbackModal(false);
      setFeedbackForm({ employee_id: '', type: 'appreciation', content: '', is_anonymous: false });
      if (activeTab === 'feedbacks') loadData();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err.response?.data?.message || 'Geri bildirim gönderilemedi');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Performansım</h1>
          <p className="page-subtitle">Performans değerlendirmeleriniz ve hedefleriniz</p>
        </div>
      </div>

      {/* Tabs - Mobile Friendly */}
      <div className="nav-tabs-mobile mb-4">
        <button
          className={`nav-tab-mobile ${activeTab === 'reviews' ? 'active' : ''}`}
          onClick={() => setActiveTab('reviews')}
        >
          <BsListCheck className="me-2" />
          Değerlendirmeler
        </button>
        <button
          className={`nav-tab-mobile ${activeTab === 'okrs' ? 'active' : ''}`}
          onClick={() => setActiveTab('okrs')}
        >
          <BsGraphUp className="me-2" />
          Hedefler
        </button>
        <button
          className={`nav-tab-mobile ${activeTab === 'feedbacks' ? 'active' : ''}`}
          onClick={() => setActiveTab('feedbacks')}
        >
          <BsChatDots className="me-2" />
          Geri Bildirimler
        </button>
      </div>

      {/* Content */}
      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="page-loading">
              <div className="loading-spinner"></div>
            </div>
          ) : activeTab === 'reviews' ? (
            reviews.length > 0 ? (
              <>
                {/* Desktop Table */}
                <div className="table-responsive desktop-only">
                  <table className="table table-hover mb-0">
                    <thead>
                      <tr>
                        <th>Dönem</th>
                        <th>Değerlendiren</th>
                        <th>Durum</th>
                        <th>Genel Puan</th>
                        <th>Tarih</th>
                      </tr>
                    </thead>
                    <tbody>
                      {reviews.map((review) => (
                        <tr key={review.id}>
                          <td>{review.period.name}</td>
                          <td>{review.reviewer.name}</td>
                          <td>{getStatusBadge(review.status)}</td>
                          <td>
                            {review.overall_score !== null ? (
                              <strong>{review.overall_score.toFixed(1)}</strong>
                            ) : (
                              '-'
                            )}
                          </td>
                          <td>
                            {review.approved_at
                              ? new Date(review.approved_at).toLocaleDateString('tr-TR')
                              : review.submitted_at
                              ? new Date(review.submitted_at).toLocaleDateString('tr-TR')
                              : new Date(review.created_at).toLocaleDateString('tr-TR')}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Mobile Cards */}
                <div className="mobile-card-list has-data">
                  {reviews.map((review) => (
                    <div key={review.id} className="mobile-card">
                      <div className="mobile-card-header">
                        <div>
                          <div className="mobile-card-title">{review.period.name}</div>
                          <div className="mobile-card-subtitle">Değerlendiren: {review.reviewer.name}</div>
                        </div>
                        {getStatusBadge(review.status)}
                      </div>
                      <div className="mobile-card-body">
                        {review.overall_score !== null && (
                          <div className="mobile-card-row">
                            <span className="mobile-card-label">Genel Puan</span>
                            <span className="mobile-card-value text-primary fw-semibold">
                              {review.overall_score.toFixed(1)} / 5
                            </span>
                          </div>
                        )}
                        <div className="mobile-card-row">
                          <span className="mobile-card-label">Dönem</span>
                          <span className="mobile-card-value">
                            {new Date(review.period.start_date).toLocaleDateString('tr-TR')} - {new Date(review.period.end_date).toLocaleDateString('tr-TR')}
                          </span>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </>
            ) : (
              <div className="empty-state">
                <BsListCheck size={64} className="text-muted mb-3" />
                <h3>Henüz değerlendirme yok</h3>
                <p>Size ait performans değerlendirmeleri burada görünecektir</p>
              </div>
            )
          ) : activeTab === 'okrs' ? (
            okrs.length > 0 ? (
              <>
                {/* Desktop Table */}
                <div className="table-responsive desktop-only">
                  <table className="table table-hover mb-0">
                    <thead>
                      <tr>
                        <th>Hedef</th>
                        <th>İlerleme</th>
                        <th>Key Results</th>
                        <th>Durum</th>
                      </tr>
                    </thead>
                    <tbody>
                      {okrs.map((okr) => (
                        <tr key={okr.id}>
                          <td>
                            <strong>{okr.title}</strong>
                            {okr.description && (
                              <div className="text-muted small mt-1">{okr.description}</div>
                            )}
                          </td>
                          <td style={{ minWidth: '150px' }}>
                            <div className="progress" style={{ height: '20px' }}>
                              <div
                                className="progress-bar"
                                style={{ width: `${okr.progress}%` }}
                              >
                                {okr.progress}%
                              </div>
                            </div>
                          </td>
                          <td>{okr.key_results.length} Key Result</td>
                          <td>{getStatusBadge(okr.status)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Mobile Cards */}
                <div className="mobile-card-list has-data">
                  {okrs.map((okr) => (
                    <div key={okr.id} className="mobile-card">
                      <div className="mobile-card-header">
                        <div>
                          <div className="mobile-card-title">{okr.title}</div>
                          <div className="mobile-card-subtitle">{okr.key_results.length} Key Result</div>
                        </div>
                        {getStatusBadge(okr.status)}
                      </div>
                      <div className="mobile-card-body">
                        {okr.description && (
                          <p className="text-muted small mb-2">{okr.description}</p>
                        )}
                        <div className="mb-2">
                          <span className="small text-muted">İlerleme</span>
                          <div className="progress mt-1" style={{ height: '24px' }}>
                            <div
                              className="progress-bar"
                              style={{ width: `${okr.progress}%` }}
                            >
                              {okr.progress}%
                            </div>
                          </div>
                        </div>
                        {okr.key_results.map((kr) => (
                          <div key={kr.id} className="mobile-card-row" style={{ fontSize: '0.85rem' }}>
                            <span className="mobile-card-label">{kr.title}</span>
                            <span className="mobile-card-value">{kr.current_value} / {kr.target_value}</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              </>
            ) : (
              <div className="empty-state">
                <BsGraphUp size={64} className="text-muted mb-3" />
                <h3>Henüz hedef yok</h3>
                <p>Size atanan hedefler burada görünecektir</p>
              </div>
            )
          ) : (
            // Feedbacks Tab
            feedbacks.length > 0 ? (
              <div className="mobile-card-list has-data" style={{ display: 'flex' }}>
                {feedbacks.map((feedback) => (
                  <div key={feedback.id} className="mobile-card">
                    <div className="mobile-card-header">
                      <div>
                        <div className="mobile-card-title">
                          {feedback.is_anonymous ? 'Anonim' : feedback.from_user?.name || 'Bilinmeyen'}
                        </div>
                        <div className="mobile-card-subtitle">
                          {new Date(feedback.created_at).toLocaleDateString('tr-TR')}
                        </div>
                      </div>
                      {getFeedbackTypeBadge(feedback.type)}
                    </div>
                    <div className="mobile-card-body">
                      <p className="mb-0">{feedback.content}</p>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="empty-state">
                <BsChatDots size={64} className="text-muted mb-3" />
                <h3>Henüz geri bildirim yok</h3>
                <p>Size gelen geri bildirimler burada görünecektir</p>
                <button className="btn btn-primary" onClick={() => setShowFeedbackModal(true)}>
                  <BsPlus /> Geri Bildirim Gönder
                </button>
              </div>
            )
          )}
        </div>
      </div>

      {/* FAB for Feedbacks */}
      {activeTab === 'feedbacks' && (
        <button className="fab" onClick={() => setShowFeedbackModal(true)} aria-label="Geri Bildirim Gönder">
          <BsPlus size={24} />
        </button>
      )}

      {/* Feedback Modal */}
      {showFeedbackModal && (
        <div className="modal-mobile open">
          <div className="modal-mobile-header">
            <h3 className="modal-mobile-title">Geri Bildirim Gönder</h3>
            <button className="modal-mobile-close" onClick={() => setShowFeedbackModal(false)}>
              <BsX size={24} />
            </button>
          </div>
          <form onSubmit={handleSubmitFeedback}>
            <div className="modal-mobile-body">
              <div className="mb-3">
                <label className="form-label">Geri Bildirim Türü</label>
                <select
                  className="form-control"
                  value={feedbackForm.type}
                  onChange={(e) => setFeedbackForm({ ...feedbackForm, type: e.target.value })}
                >
                  <option value="appreciation">Takdir</option>
                  <option value="suggestion">Öneri</option>
                  <option value="concern">Endişe</option>
                  <option value="other">Diğer</option>
                </select>
              </div>
              <div className="mb-3">
                <label className="form-label">Geri Bildirim *</label>
                <textarea
                  className="form-control"
                  rows={5}
                  value={feedbackForm.content}
                  onChange={(e) => setFeedbackForm({ ...feedbackForm, content: e.target.value })}
                  placeholder="Geri bildiriminizi yazın..."
                  required
                />
              </div>
              <div className="mb-3">
                <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
                  <input
                    type="checkbox"
                    checked={feedbackForm.is_anonymous}
                    onChange={(e) => setFeedbackForm({ ...feedbackForm, is_anonymous: e.target.checked })}
                  />
                  Anonim olarak gönder
                </label>
              </div>
            </div>
            <div className="modal-mobile-footer">
              <button
                type="button"
                className="btn btn-outline-primary"
                onClick={() => setShowFeedbackModal(false)}
              >
                İptal
              </button>
              <button type="submit" className="btn btn-primary" disabled={submitting}>
                {submitting ? 'Gönderiliyor...' : 'Gönder'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
};

export default PerformancePage;
