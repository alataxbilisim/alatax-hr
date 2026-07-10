import React, { useCallback, useEffect, useState } from 'react';
import { portalApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsClipboardData, BsCheckCircle, BsX, BsChevronRight } from 'react-icons/bs';

interface Survey {
  id: number;
  title: string;
  description: string;
  type: string;
  is_anonymous: boolean;
  start_date: string | null;
  end_date: string | null;
  questions_count: number;
  submission_status: string;
  started_at: string | null;
  completed_at: string | null;
  is_completed: boolean;
  questions?: SurveyQuestion[];
}

interface SurveyQuestion {
  id: number;
  question_text: string;
  question_type: string;
  is_required: boolean;
  options: string[];
  order: number;
}

const SurveysPage: React.FC = () => {
  const [surveys, setSurveys] = useState<Survey[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<'all' | 'pending' | 'completed'>('all');
  const [selectedSurvey, setSelectedSurvey] = useState<Survey | null>(null);
  const [responses, setResponses] = useState<Record<number, unknown>>({});
  const [submitting, setSubmitting] = useState(false);

  const loadSurveys = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = {};
      if (filter === 'pending') {
        params.pending_only = true;
      } else if (filter === 'completed') {
        params.completed_only = true;
      }
      const response = await portalApi.surveys.list(params);
      setSurveys(response.data.data.data || []);
    } catch {
      toast.error('Anketler yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [filter]);

  useEffect(() => {
    loadSurveys();
  }, [loadSurveys]);

  const getStatusBadge = (survey: Survey) => {
    if (survey.is_completed) {
      return <span className="request-status approved">Tamamlandı</span>;
    }
    if (survey.submission_status === 'started') {
      return <span className="request-status pending">Devam Ediyor</span>;
    }
    return <span className="request-status pending">Başlanmadı</span>;
  };

  const handleStartSurvey = async (surveyId: number) => {
    try {
      const response = await portalApi.surveys.get(surveyId);
      setSelectedSurvey(response.data.data);
      setResponses({});
    } catch {
      toast.error('Anket yüklenemedi');
    }
  };

  const handleResponseChange = (questionId: number, value: unknown) => {
    setResponses((prev) => ({
      ...prev,
      [questionId]: value,
    }));
  };

  const handleSubmitSurvey = async () => {
    if (!selectedSurvey) return;

    // Check required questions
    const requiredQuestions = selectedSurvey.questions?.filter((q) => q.is_required) || [];
    const missingRequired = requiredQuestions.filter((q) => !responses[q.id]);
    if (missingRequired.length > 0) {
      toast.error(`Lütfen zorunlu soruları cevaplayın`);
      return;
    }

    setSubmitting(true);
    try {
      const formattedResponses = Object.entries(responses).map(([questionId, value]) => {
        const question = selectedSurvey.questions?.find((q) => q.id === Number(questionId));
        return {
          question_id: Number(questionId),
          answer_text: question?.question_type === 'text' ? String(value) : undefined,
          answer_numeric: question?.question_type === 'rating' ? Number(value) : undefined,
          answer_array: question?.question_type === 'multiple_choice' ? (Array.isArray(value) ? value : [value]) : undefined,
        };
      });

      await portalApi.surveys.submit(selectedSurvey.id, { responses: formattedResponses });
      toast.success('Anket gönderildi');
      setSelectedSurvey(null);
      setResponses({});
      loadSurveys();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err.response?.data?.message || 'Anket gönderilemedi');
    } finally {
      setSubmitting(false);
    }
  };

  const renderQuestion = (question: SurveyQuestion) => {
    const value = responses[question.id];

    switch (question.question_type) {
      case 'text':
        return (
          <textarea
            className="form-control"
            rows={3}
            value={(value as string) || ''}
            onChange={(e) => handleResponseChange(question.id, e.target.value)}
            placeholder="Cevabınızı yazın..."
          />
        );

      case 'rating':
        return (
          <div className="d-flex gap-2 flex-wrap">
            {[1, 2, 3, 4, 5].map((rating) => (
              <button
                key={rating}
                type="button"
                className={`btn ${value === rating ? 'btn-primary' : 'btn-outline-primary'}`}
                style={{ minWidth: '48px', minHeight: '48px' }}
                onClick={() => handleResponseChange(question.id, rating)}
              >
                {rating}
              </button>
            ))}
          </div>
        );

      case 'single_choice':
        return (
          <div className="d-flex flex-column gap-2">
            {question.options.map((option, index) => (
              <label
                key={index}
                className="mobile-card"
                style={{ cursor: 'pointer', padding: '0.75rem 1rem' }}
              >
                <input
                  type="radio"
                  name={`question-${question.id}`}
                  checked={value === option}
                  onChange={() => handleResponseChange(question.id, option)}
                  style={{ marginRight: '0.75rem' }}
                />
                {option}
              </label>
            ))}
          </div>
        );

      case 'multiple_choice':
        return (
          <div className="d-flex flex-column gap-2">
            {question.options.map((option, index) => (
              <label
                key={index}
                className="mobile-card"
                style={{ cursor: 'pointer', padding: '0.75rem 1rem' }}
              >
                <input
                  type="checkbox"
                  checked={Array.isArray(value) && value.includes(option)}
                  onChange={(e) => {
                    const current = Array.isArray(value) ? value : [];
                    if (e.target.checked) {
                      handleResponseChange(question.id, [...current, option]);
                    } else {
                      handleResponseChange(question.id, current.filter((v) => v !== option));
                    }
                  }}
                  style={{ marginRight: '0.75rem' }}
                />
                {option}
              </label>
            ))}
          </div>
        );

      default:
        return (
          <input
            type="text"
            className="form-control"
            value={(value as string) || ''}
            onChange={(e) => handleResponseChange(question.id, e.target.value)}
          />
        );
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Anketler</h1>
          <p className="page-subtitle">Şirket anketlerine katılın ve görüşlerinizi paylaşın</p>
        </div>
      </div>

      {/* Filters - Mobile Tabs */}
      <div className="nav-tabs-mobile mb-4">
        <button
          className={`nav-tab-mobile ${filter === 'all' ? 'active' : ''}`}
          onClick={() => setFilter('all')}
        >
          Tümü
        </button>
        <button
          className={`nav-tab-mobile ${filter === 'pending' ? 'active' : ''}`}
          onClick={() => setFilter('pending')}
        >
          Bekleyen
        </button>
        <button
          className={`nav-tab-mobile ${filter === 'completed' ? 'active' : ''}`}
          onClick={() => setFilter('completed')}
        >
          Tamamlanan
        </button>
      </div>

      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="page-loading">
              <div className="loading-spinner"></div>
            </div>
          ) : surveys.length > 0 ? (
            <>
              {/* Desktop Table */}
              <div className="table-responsive desktop-only">
                <table className="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>Anket Adı</th>
                      <th>Tür</th>
                      <th>Soru Sayısı</th>
                      <th>Durum</th>
                      <th>Bitiş Tarihi</th>
                      <th>İşlemler</th>
                    </tr>
                  </thead>
                  <tbody>
                    {surveys.map((survey) => (
                      <tr key={survey.id}>
                        <td>
                          <strong>{survey.title}</strong>
                          {survey.is_anonymous && (
                            <span className="badge bg-info text-dark ms-2">Anonim</span>
                          )}
                          {survey.description && (
                            <div className="text-muted small mt-1">{survey.description}</div>
                          )}
                        </td>
                        <td>
                          <span className="badge bg-secondary">{survey.type}</span>
                        </td>
                        <td>{survey.questions_count} soru</td>
                        <td>{getStatusBadge(survey)}</td>
                        <td>
                          {survey.end_date
                            ? new Date(survey.end_date).toLocaleDateString('tr-TR')
                            : '-'}
                        </td>
                        <td>
                          {survey.is_completed ? (
                            <span className="text-success">
                              <BsCheckCircle size={20} title="Tamamlandı" />
                            </span>
                          ) : (
                            <button
                              className="btn btn-sm btn-primary"
                              onClick={() => handleStartSurvey(survey.id)}
                            >
                              {survey.submission_status === 'started' ? 'Devam Et' : 'Başlat'}
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Mobile Cards */}
              <div className="mobile-card-list has-data">
                {surveys.map((survey) => (
                  <div
                    key={survey.id}
                    className="mobile-card"
                    onClick={() => !survey.is_completed && handleStartSurvey(survey.id)}
                    style={{ cursor: survey.is_completed ? 'default' : 'pointer' }}
                  >
                    <div className="mobile-card-header">
                      <div>
                        <div className="mobile-card-title">
                          {survey.title}
                          {survey.is_anonymous && (
                            <span className="badge bg-info text-dark ms-2" style={{ fontSize: '0.65rem' }}>Anonim</span>
                          )}
                        </div>
                        <div className="mobile-card-subtitle">
                          {survey.questions_count} soru • {survey.type}
                        </div>
                      </div>
                      <div className="d-flex align-items-center gap-2">
                        {getStatusBadge(survey)}
                        {!survey.is_completed && <BsChevronRight size={16} className="text-muted" />}
                      </div>
                    </div>
                    {survey.description && (
                      <div className="mobile-card-body">
                        <p className="text-muted small mb-0">{survey.description}</p>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </>
          ) : (
            <div className="empty-state">
              <BsClipboardData size={64} className="text-muted mb-3" />
              <h3>Henüz anket yok</h3>
              <p>Size atanan anketler burada görünecektir</p>
            </div>
          )}
        </div>
      </div>

      {/* Survey Modal */}
      {selectedSurvey && (
        <div className="modal-mobile open">
          <div className="modal-mobile-header">
            <h3 className="modal-mobile-title">{selectedSurvey.title}</h3>
            <button className="modal-mobile-close" onClick={() => setSelectedSurvey(null)}>
              <BsX size={24} />
            </button>
          </div>
          <div className="modal-mobile-body">
            {selectedSurvey.description && (
              <p className="text-muted mb-4">{selectedSurvey.description}</p>
            )}
            {selectedSurvey.questions?.map((question, index) => (
              <div key={question.id} className="mb-4">
                <label className="form-label">
                  {index + 1}. {question.question_text}
                  {question.is_required && <span className="text-danger ms-1">*</span>}
                </label>
                {renderQuestion(question)}
              </div>
            ))}
          </div>
          <div className="modal-mobile-footer">
            <button
              className="btn btn-outline-primary"
              onClick={() => setSelectedSurvey(null)}
            >
              İptal
            </button>
            <button
              className="btn btn-primary"
              onClick={handleSubmitSurvey}
              disabled={submitting}
            >
              {submitting ? 'Gönderiliyor...' : 'Anketi Gönder'}
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default SurveysPage;
