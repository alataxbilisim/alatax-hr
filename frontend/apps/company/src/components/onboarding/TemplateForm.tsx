import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';
import { Select } from '@shared/components';
import { useTranslation } from '@shared/i18n';
import { BsPlus, BsTrash, BsGripVertical } from 'react-icons/bs';

interface Task {
  title: string;
  description: string;
  type: string;
  is_required: boolean;
  days_offset: number;
  action_key?: string;
}

interface TemplateFormValues {
  id?: number;
  name: string;
  description: string;
  process_type: 'onboarding' | 'offboarding';
  tasks: Task[];
  estimated_days: number;
  is_active: boolean;
  is_default: boolean;
}

interface TemplateFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: Omit<TemplateFormValues, 'id'>) => Promise<void>;
  template?: {
    id?: number;
    name?: string;
    description?: string;
    process_type?: 'onboarding' | 'offboarding';
    tasks?: Task[];
    estimated_days?: number;
    is_active?: boolean;
    is_default?: boolean;
  } | null;
  defaultProcessType?: 'onboarding' | 'offboarding';
}

/** Motor dışı — lookup değil; sabit seçenekler + Select */
const TASK_TYPE_OPTIONS = [
  { value: 'document_upload', label: 'Evrak Yükleme' },
  { value: 'document_fill', label: 'Form Doldurma' },
  { value: 'training', label: 'Eğitim' },
  { value: 'meeting', label: 'Toplantı' },
  { value: 'system_setup', label: 'Sistem Kurulumu' },
  { value: 'quiz', label: 'Quiz/Sınav' },
  { value: 'custom', label: 'Özel Görev' },
];

const TemplateForm: React.FC<TemplateFormProps> = ({
  isOpen,
  onClose,
  onSubmit,
  template,
  defaultProcessType = 'onboarding',
}) => {
  const { t } = useTranslation('common');
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState<Omit<TemplateFormValues, 'id'>>({
    name: '',
    description: '',
    process_type: defaultProcessType,
    tasks: [{ title: '', description: '', type: 'custom', is_required: true, days_offset: 0 }],
    estimated_days: 7,
    is_active: true,
    is_default: false,
  });

  const processTypeOptions = [
    { value: 'onboarding', label: t('offboarding.processTypeOnboarding') },
    { value: 'offboarding', label: t('offboarding.processTypeOffboarding') },
  ];

  useEffect(() => {
    if (isOpen) {
      if (template) {
        setFormData({
          name: template.name || '',
          description: template.description || '',
          process_type: template.process_type || defaultProcessType,
          tasks: template.tasks && template.tasks.length > 0
            ? template.tasks
            : [{ title: '', description: '', type: 'custom', is_required: true, days_offset: 0 }],
          estimated_days: template.estimated_days || 7,
          is_active: template.is_active ?? true,
          is_default: template.is_default ?? false,
        });
      } else {
        setFormData({
          name: '',
          description: '',
          process_type: defaultProcessType,
          tasks: [{ title: '', description: '', type: 'custom', is_required: true, days_offset: 0 }],
          estimated_days: 7,
          is_active: true,
          is_default: false,
        });
      }
    }
  }, [isOpen, template, defaultProcessType]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value, type } = e.target;
    const checked = (e.target as HTMLInputElement).checked;
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : type === 'number' ? Number(value) : value,
    }));
  };

  const handleTaskChange = (index: number, field: keyof Task, value: string | boolean | number) => {
    const newTasks = [...formData.tasks];
    newTasks[index] = { ...newTasks[index], [field]: value };
    setFormData(prev => ({ ...prev, tasks: newTasks }));
  };

  const addTask = () => {
    setFormData(prev => ({
      ...prev,
      tasks: [...prev.tasks, { title: '', description: '', type: 'custom', is_required: true, days_offset: 0 }],
    }));
  };

  const removeTask = (index: number) => {
    if (formData.tasks.length <= 1) return;
    const newTasks = formData.tasks.filter((_, i) => i !== index);
    setFormData(prev => ({ ...prev, tasks: newTasks }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await onSubmit(formData);
      onClose();
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={template ? t('offboarding.templateEditTitle') : t('offboarding.templateCreateTitle')}
      size="lg"
    >
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label className="form-label">{t('offboarding.templateName')} *</label>
          <input
            type="text"
            name="name"
            value={formData.name}
            onChange={handleChange}
            className="form-input"
            placeholder={t('offboarding.templateNamePlaceholder')}
            required
          />
        </div>

        <div className="form-group">
          <label className="form-label">{t('offboarding.processType')}</label>
          <Select
            value={formData.process_type}
            onChange={(v) => setFormData((prev) => ({
              ...prev,
              process_type: v === 'offboarding' ? 'offboarding' : 'onboarding',
            }))}
            options={processTypeOptions}
            placeholder={t('offboarding.processType')}
            aria-label={t('offboarding.processType')}
            disabled={Boolean(template?.id)}
          />
        </div>

        <div className="form-group">
          <label className="form-label">{t('offboarding.templateDescription')}</label>
          <textarea
            name="description"
            value={formData.description}
            onChange={handleChange}
            className="form-input"
            rows={2}
            placeholder={t('offboarding.templateDescriptionPlaceholder')}
          />
        </div>

        <div className="form-row">
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Tahmini Süre (Gün)</label>
            <input
              type="number"
              name="estimated_days"
              value={formData.estimated_days}
              onChange={handleChange}
              className="form-input"
              min={1}
            />
          </div>
          <div className="form-group" style={{ flex: 1, display: 'flex', alignItems: 'center', gap: '1rem', paddingTop: '1.5rem' }}>
            <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
              <input
                type="checkbox"
                name="is_active"
                checked={formData.is_active}
                onChange={handleChange}
              />
              <span>Aktif</span>
            </label>
            <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
              <input
                type="checkbox"
                name="is_default"
                checked={formData.is_default}
                onChange={handleChange}
              />
              <span>Varsayılan</span>
            </label>
          </div>
        </div>

        <div className="form-group" style={{ marginTop: '1.5rem' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem' }}>
            <label className="form-label" style={{ margin: 0 }}>Görevler *</label>
            <button type="button" className="btn btn-ghost btn-sm" onClick={addTask}>
              <BsPlus size={18} /> Görev Ekle
            </button>
          </div>
          
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
            {formData.tasks.map((task, index) => (
              <div
                key={index}
                style={{
                  padding: '1rem',
                  background: 'var(--bg-tertiary)',
                  borderRadius: 'var(--radius-md)',
                  border: '1px solid var(--border-color)',
                }}
              >
                <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'flex-start' }}>
                  <BsGripVertical size={16} style={{ marginTop: '0.75rem', color: 'var(--text-tertiary)', cursor: 'grab' }} />
                  <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                    <div style={{ display: 'flex', gap: '0.5rem' }}>
                      <input
                        type="text"
                        value={task.title}
                        onChange={(e) => handleTaskChange(index, 'title', e.target.value)}
                        className="form-input"
                        placeholder="Görev başlığı"
                        required
                        style={{ flex: 2 }}
                      />
                      <div style={{ flex: 1 }}>
                        <Select
                          value={task.type}
                          onChange={(v) => handleTaskChange(index, 'type', v)}
                          options={TASK_TYPE_OPTIONS}
                          placeholder="Görev tipi"
                          aria-label={`Görev tipi ${index + 1}`}
                        />
                      </div>
                    </div>
                    <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
                      <input
                        type="text"
                        value={task.description}
                        onChange={(e) => handleTaskChange(index, 'description', e.target.value)}
                        className="form-input"
                        placeholder="Açıklama (opsiyonel)"
                        style={{ flex: 2 }}
                      />
                      <input
                        type="number"
                        value={task.days_offset}
                        onChange={(e) => handleTaskChange(index, 'days_offset', Number(e.target.value))}
                        className="form-input"
                        min={0}
                        style={{ width: '80px' }}
                        title="Gün ofset"
                      />
                      <label style={{ display: 'flex', alignItems: 'center', gap: '0.25rem', whiteSpace: 'nowrap' }}>
                        <input
                          type="checkbox"
                          checked={task.is_required}
                          onChange={(e) => handleTaskChange(index, 'is_required', e.target.checked)}
                        />
                        <span style={{ fontSize: '0.75rem' }}>Zorunlu</span>
                      </label>
                    </div>
                  </div>
                  <button
                    type="button"
                    className="btn btn-ghost btn-icon btn-sm"
                    onClick={() => removeTask(index)}
                    disabled={formData.tasks.length <= 1}
                    style={{ color: 'var(--danger)' }}
                  >
                    <BsTrash size={14} />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1.5rem', paddingTop: '1rem', borderTop: '1px solid var(--border-color)' }}>
          <button type="button" className="btn btn-ghost" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button type="submit" className="btn btn-primary" disabled={loading}>
            {loading ? 'Kaydediliyor...' : template ? 'Güncelle' : 'Oluştur'}
          </button>
        </div>
      </form>
    </Modal>
  );
};

export default TemplateForm;

