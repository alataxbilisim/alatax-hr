import React, { useEffect, useState, useCallback } from 'react';
import { surveysApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { Select } from '@shared/components';
import toast from 'react-hot-toast';
import { DataTable, ConfirmDialog, EmptyState, Modal } from '../../components/ui';
import {
  BsPlus,
  BsClipboardData,
  BsPencil,
  BsTrash,
  BsBarChart,
  BsX,
  BsArrowUp,
  BsArrowDown,
} from 'react-icons/bs';

interface Survey {
  id: number;
  title: string;
  description?: string;
  type: string;
  is_anonymous: boolean;
  is_active: boolean;
  start_date: string | null;
  end_date: string | null;
  questions_count?: number;
  submissions_count?: number;
  created_at: string;
}

interface SurveyQuestion {
  id?: number;
  question_text: string;
  question_type: string;
  is_required: boolean;
  options: string[];
  order: number;
}

const SurveysPage: React.FC = () => {
  const [surveys, setSurveys] = useState<Survey[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  
  // Delete state
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [itemToDelete, setItemToDelete] = useState<Survey | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  // Create/Edit Modal
  const [showModal, setShowModal] = useState(false);
  const [editingSurvey, setEditingSurvey] = useState<Survey | null>(null);
  const [submitting, setSubmitting] = useState(false);

  // Form state
  const [formData, setFormData] = useState({
    title: '',
    description: '',
    type: 'custom',
    is_anonymous: false,
    is_active: true,
    start_date: '',
    end_date: '',
  });
  const [questions, setQuestions] = useState<SurveyQuestion[]>([]);

  // Results Modal
  const [showResultsModal, setShowResultsModal] = useState(false);
  const [selectedSurveyResults, setSelectedSurveyResults] = useState<{
    survey: Survey;
    results: Record<string, unknown>;
  } | null>(null);

  const [typeOptions, setTypeOptions] = useState<LookupItem[]>([]);
  const [questionTypeOptions, setQuestionTypeOptions] = useState<LookupItem[]>([]);

  const loadLookups = useCallback(async () => {
    try {
      const [typeRes, questionTypeRes] = await Promise.all([
        lookupsApi.forType('survey_type'),
        lookupsApi.forType('survey_question_type'),
      ]);
      setTypeOptions(typeRes.data.data ?? []);
      setQuestionTypeOptions(questionTypeRes.data.data ?? []);
    } catch {
      console.error('Anket lookup listeleri yüklenemedi');
    }
  }, []);

  const typeLabel = (value: string) =>
    typeOptions.find((o) => o.value === value)?.label || value;

  const loadSurveys = useCallback(async () => {
    try {
      setLoading(true);
      const response = await surveysApi.list({ page });
      const data = response.data.data;
      setSurveys(data.data || []);
      setTotalPages(data.last_page || 1);
    } catch {
      toast.error('Anketler yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [page]);

  useEffect(() => {
    loadSurveys();
  }, [loadSurveys]);

  useEffect(() => {
    void loadLookups();
  }, [loadLookups]);

  const handleDelete = (survey: Survey) => {
    setItemToDelete(survey);
    setDeleteDialogOpen(true);
  };

  const confirmDelete = async () => {
    if (!itemToDelete) return;
    setDeleteLoading(true);
    try {
      await surveysApi.delete(itemToDelete.id);
      toast.success('Anket silindi');
      loadSurveys();
    } catch {
      toast.error('Anket silinemedi');
    } finally {
      setDeleteLoading(false);
      setDeleteDialogOpen(false);
      setItemToDelete(null);
    }
  };

  const openCreateModal = () => {
    setEditingSurvey(null);
    setFormData({
      title: '',
      description: '',
      type: 'custom',
      is_anonymous: false,
      is_active: true,
      start_date: '',
      end_date: '',
    });
    setQuestions([{
      question_text: '',
      question_type: 'text',
      is_required: true,
      options: [],
      order: 1,
    }]);
    setShowModal(true);
  };

  const openEditModal = async (survey: Survey) => {
    try {
      const response = await surveysApi.get(survey.id);
      const fullSurvey = response.data.data;
      setEditingSurvey(fullSurvey);
      setFormData({
        title: fullSurvey.title || '',
        description: fullSurvey.description || '',
        type: fullSurvey.type || 'custom',
        is_anonymous: fullSurvey.is_anonymous || false,
        is_active: fullSurvey.is_active ?? true,
        start_date: fullSurvey.start_date?.split('T')[0] || '',
        end_date: fullSurvey.end_date?.split('T')[0] || '',
      });
      setQuestions(fullSurvey.questions?.map((q: SurveyQuestion, i: number) => ({
        id: q.id,
        question_text: q.question_text,
        question_type: q.question_type,
        is_required: q.is_required,
        options: q.options || [],
        order: q.order || i + 1,
      })) || []);
      setShowModal(true);
    } catch {
      toast.error('Anket yüklenemedi');
    }
  };

  const addQuestion = () => {
    setQuestions([...questions, {
      question_text: '',
      question_type: 'text',
      is_required: true,
      options: [],
      order: questions.length + 1,
    }]);
  };

  const removeQuestion = (index: number) => {
    if (questions.length > 1) {
      const updated = questions.filter((_, i) => i !== index).map((q, i) => ({ ...q, order: i + 1 }));
      setQuestions(updated);
    }
  };

  const moveQuestion = (index: number, direction: 'up' | 'down') => {
    if (
      (direction === 'up' && index === 0) ||
      (direction === 'down' && index === questions.length - 1)
    ) return;

    const newQuestions = [...questions];
    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    [newQuestions[index], newQuestions[targetIndex]] = [newQuestions[targetIndex], newQuestions[index]];
    setQuestions(newQuestions.map((q, i) => ({ ...q, order: i + 1 })));
  };

  const updateQuestion = (index: number, field: keyof SurveyQuestion, value: unknown) => {
    const updated = [...questions];
    updated[index] = { ...updated[index], [field]: value };
    setQuestions(updated);
  };

  const addOption = (questionIndex: number) => {
    const updated = [...questions];
    updated[questionIndex].options = [...updated[questionIndex].options, ''];
    setQuestions(updated);
  };

  const updateOption = (questionIndex: number, optionIndex: number, value: string) => {
    const updated = [...questions];
    updated[questionIndex].options[optionIndex] = value;
    setQuestions(updated);
  };

  const removeOption = (questionIndex: number, optionIndex: number) => {
    const updated = [...questions];
    updated[questionIndex].options = updated[questionIndex].options.filter((_, i) => i !== optionIndex);
    setQuestions(updated);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.title) {
      toast.error('Anket başlığı zorunludur');
      return;
    }

    const validQuestions = questions.filter(q => q.question_text.trim());
    if (validQuestions.length === 0) {
      toast.error('En az bir soru eklemelisiniz');
      return;
    }

    // Validate choice questions have options
    for (const q of validQuestions) {
      if (['single_choice', 'multiple_choice'].includes(q.question_type) && q.options.filter(o => o.trim()).length < 2) {
        toast.error('Seçim soruları için en az 2 seçenek gereklidir');
        return;
      }
    }

    setSubmitting(true);
    try {
      const data = {
        ...formData,
        questions: validQuestions.map(q => ({
          ...q,
          options: q.options.filter(o => o.trim()),
        })),
      };

      if (editingSurvey) {
        await surveysApi.update(editingSurvey.id, data);
        toast.success('Anket güncellendi');
      } else {
        await surveysApi.create(data);
        toast.success('Anket oluşturuldu');
      }
      setShowModal(false);
      loadSurveys();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err.response?.data?.message || 'İşlem başarısız');
    } finally {
      setSubmitting(false);
    }
  };

  const viewResults = async (survey: Survey) => {
    try {
      const response = await surveysApi.results(survey.id);
      setSelectedSurveyResults({ survey, results: response.data.data });
      setShowResultsModal(true);
    } catch {
      toast.error('Sonuçlar yüklenemedi');
    }
  };

  const columns = [
    {
      key: 'title',
      title: 'Anket Adı',
      render: (s: Survey) => (
        <div>
          <strong>{s.title}</strong>
          {s.description && <div className="text-muted small mt-1">{s.description.substring(0, 50)}...</div>}
        </div>
      ),
    },
    {
      key: 'type',
      title: 'Tür',
      render: (s: Survey) => (
        <span className="badge bg-info">{typeLabel(s.type)}</span>
      ),
    },
    {
      key: 'questions',
      title: 'Sorular',
      render: (s: Survey) => s.questions_count || 0,
    },
    {
      key: 'submissions',
      title: 'Yanıtlar',
      render: (s: Survey) => s.submissions_count || 0,
    },
    {
      key: 'status',
      title: 'Durum',
      render: (s: Survey) => (
        <span className={`badge ${s.is_active ? 'bg-success' : 'bg-secondary'}`}>
          {s.is_active ? 'Aktif' : 'Pasif'}
        </span>
      ),
    },
    {
      key: 'end_date',
      title: 'Bitiş Tarihi',
      render: (s: Survey) => 
        s.end_date ? new Date(s.end_date).toLocaleDateString('tr-TR') : '-',
    },
    {
      key: 'actions',
      title: 'İşlemler',
      render: (s: Survey) => (
        <div className="d-flex gap-2">
          <button 
            className="btn btn-sm btn-outline-primary" 
            title="Sonuçlar"
            onClick={() => viewResults(s)}
          >
            <BsBarChart size={14} />
          </button>
          <button 
            className="btn btn-sm btn-outline-secondary" 
            title="Düzenle"
            onClick={() => openEditModal(s)}
          >
            <BsPencil size={14} />
          </button>
          <button 
            className="btn btn-sm btn-outline-danger" 
            onClick={() => handleDelete(s)}
            title="Sil"
          >
            <BsTrash size={14} />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Anket Yönetimi</h1>
          <p className="page-subtitle">Çalışan anketleri oluşturun ve yönetin</p>
        </div>
        <div className="page-header-actions">
          <button className="btn btn-primary" onClick={openCreateModal}>
            <BsPlus size={18} className="me-2" />
            Yeni Anket
          </button>
        </div>
      </div>

      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="loading-container">
              <div className="loading-spinner" />
            </div>
          ) : surveys.length > 0 ? (
            <DataTable 
              columns={columns} 
              data={surveys} 
              currentPage={page}
              totalPages={totalPages}
              onPageChange={setPage}
            />
          ) : (
            <EmptyState
              icon={<BsClipboardData size={48} />}
              title="Henüz anket yok"
              description="Yeni bir anket oluşturarak başlayın."
              action={
                <button className="btn btn-primary" onClick={openCreateModal}>
                  <BsPlus size={18} className="me-2" />
                  İlk Anketi Oluştur
                </button>
              }
            />
          )}
        </div>
      </div>

      {/* Create/Edit Modal */}
      <Modal
        isOpen={showModal}
        onClose={() => setShowModal(false)}
        title={editingSurvey ? 'Anketi Düzenle' : 'Yeni Anket Oluştur'}
        size="lg"
      >
        <form onSubmit={handleSubmit}>
          <div className="row">
            <div className="col-md-8 mb-3">
              <label className="form-label">Anket Başlığı *</label>
              <input
                type="text"
                className="form-control"
                value={formData.title}
                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                placeholder="Örn: Çalışan Memnuniyet Anketi 2025"
                required
              />
            </div>
            <div className="col-md-4 mb-3">
              <label className="form-label">Anket Türü</label>
              <Select
                value={formData.type}
                onChange={(v) => setFormData({ ...formData, type: v })}
                options={typeOptions.map((o) => ({
                  value: o.value,
                  label: o.label,
                  color: o.color,
                }))}
                placeholder="Tür seçin"
                aria-label="Anket türü"
              />
            </div>
          </div>
          
          <div className="mb-3">
            <label className="form-label">Açıklama</label>
            <textarea
              className="form-control"
              rows={2}
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              placeholder="Anket hakkında kısa açıklama"
            />
          </div>

          <div className="row">
            <div className="col-md-4 mb-3">
              <label className="form-label">Başlangıç Tarihi</label>
              <input
                type="date"
                className="form-control"
                value={formData.start_date}
                onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
              />
            </div>
            <div className="col-md-4 mb-3">
              <label className="form-label">Bitiş Tarihi</label>
              <input
                type="date"
                className="form-control"
                value={formData.end_date}
                onChange={(e) => setFormData({ ...formData, end_date: e.target.value })}
              />
            </div>
            <div className="col-md-4 mb-3">
              <label className="form-label d-block">&nbsp;</label>
              <div className="d-flex gap-3">
                <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
                  <input
                    type="checkbox"
                    checked={formData.is_anonymous}
                    onChange={(e) => setFormData({ ...formData, is_anonymous: e.target.checked })}
                  />
                  Anonim
                </label>
                <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
                  <input
                    type="checkbox"
                    checked={formData.is_active}
                    onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                  />
                  Aktif
                </label>
              </div>
            </div>
          </div>

          <hr />
          <div className="d-flex justify-content-between align-items-center mb-3">
            <h5 className="mb-0">Sorular</h5>
            <button type="button" className="btn btn-sm btn-outline-primary" onClick={addQuestion}>
              <BsPlus /> Soru Ekle
            </button>
          </div>

          <div style={{ maxHeight: '400px', overflowY: 'auto' }}>
            {questions.map((question, qIndex) => (
              <div key={qIndex} className="card mb-3" style={{ background: 'var(--background)' }}>
                <div className="card-body">
                  <div className="d-flex justify-content-between align-items-start mb-2">
                    <strong>Soru {qIndex + 1}</strong>
                    <div className="d-flex gap-1">
                      <button
                        type="button"
                        className="btn btn-sm btn-ghost"
                        onClick={() => moveQuestion(qIndex, 'up')}
                        disabled={qIndex === 0}
                      >
                        <BsArrowUp />
                      </button>
                      <button
                        type="button"
                        className="btn btn-sm btn-ghost"
                        onClick={() => moveQuestion(qIndex, 'down')}
                        disabled={qIndex === questions.length - 1}
                      >
                        <BsArrowDown />
                      </button>
                      {questions.length > 1 && (
                        <button
                          type="button"
                          className="btn btn-sm btn-ghost text-danger"
                          onClick={() => removeQuestion(qIndex)}
                        >
                          <BsTrash />
                        </button>
                      )}
                    </div>
                  </div>

                  <div className="row">
                    <div className="col-md-8 mb-2">
                      <input
                        type="text"
                        className="form-control"
                        value={question.question_text}
                        onChange={(e) => updateQuestion(qIndex, 'question_text', e.target.value)}
                        placeholder="Soru metni"
                        required
                      />
                    </div>
                    <div className="col-md-4 mb-2">
                      <Select
                        value={question.question_type}
                        onChange={(v) => updateQuestion(qIndex, 'question_type', v)}
                        options={questionTypeOptions.map((t) => ({
                          value: t.value,
                          label: t.label,
                          color: t.color,
                        }))}
                        placeholder="Soru tipi"
                        aria-label="Soru tipi"
                      />
                    </div>
                  </div>

                  <div className="mb-2">
                    <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
                      <input
                        type="checkbox"
                        checked={question.is_required}
                        onChange={(e) => updateQuestion(qIndex, 'is_required', e.target.checked)}
                      />
                      Zorunlu soru
                    </label>
                  </div>

                  {/* Options for choice questions */}
                  {['single_choice', 'multiple_choice'].includes(question.question_type) && (
                    <div className="mt-2">
                      <label className="form-label small">Seçenekler</label>
                      {question.options.map((option, oIndex) => (
                        <div key={oIndex} className="d-flex gap-2 mb-1">
                          <input
                            type="text"
                            className="form-control form-control-sm"
                            value={option}
                            onChange={(e) => updateOption(qIndex, oIndex, e.target.value)}
                            placeholder={`Seçenek ${oIndex + 1}`}
                          />
                          <button
                            type="button"
                            className="btn btn-sm btn-ghost text-danger"
                            onClick={() => removeOption(qIndex, oIndex)}
                          >
                            <BsX />
                          </button>
                        </div>
                      ))}
                      <button
                        type="button"
                        className="btn btn-sm btn-outline-secondary mt-1"
                        onClick={() => addOption(qIndex)}
                      >
                        <BsPlus /> Seçenek Ekle
                      </button>
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>

          <div className="d-flex justify-content-end gap-2 mt-4">
            <button type="button" className="btn btn-outline-secondary" onClick={() => setShowModal(false)}>
              İptal
            </button>
            <button type="submit" className="btn btn-primary" disabled={submitting}>
              {submitting ? 'Kaydediliyor...' : (editingSurvey ? 'Güncelle' : 'Oluştur')}
            </button>
          </div>
        </form>
      </Modal>

      {/* Results Modal */}
      <Modal
        isOpen={showResultsModal}
        onClose={() => setShowResultsModal(false)}
        title={`Sonuçlar: ${selectedSurveyResults?.survey.title}`}
        size="lg"
      >
        {selectedSurveyResults?.results ? (
          <div>
            <div className="row mb-4">
              <div className="col-4 text-center">
                <h3>{(selectedSurveyResults.results as Record<string, number>).total_submissions || 0}</h3>
                <p className="text-muted mb-0">Toplam Yanıt</p>
              </div>
              <div className="col-4 text-center">
                <h3>{(selectedSurveyResults.results as Record<string, number>).completion_rate?.toFixed(0) || 0}%</h3>
                <p className="text-muted mb-0">Tamamlanma Oranı</p>
              </div>
              <div className="col-4 text-center">
                <h3>{(selectedSurveyResults.results as Record<string, number>).avg_score?.toFixed(1) || '-'}</h3>
                <p className="text-muted mb-0">Ortalama Puan</p>
              </div>
            </div>
            <p className="text-muted text-center">Detaylı sonuçlar için raporlama modülünü kullanın.</p>
          </div>
        ) : (
          <p className="text-muted">Sonuç bulunamadı</p>
        )}
      </Modal>

      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => {
          setDeleteDialogOpen(false);
          setItemToDelete(null);
        }}
        onConfirm={confirmDelete}
        title="Anketi Sil"
        message={`"${itemToDelete?.title}" anketini silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.`}
        confirmText="Sil"
        cancelText="İptal"
        loading={deleteLoading}
        variant="danger"
      />
    </div>
  );
};

export default SurveysPage;
