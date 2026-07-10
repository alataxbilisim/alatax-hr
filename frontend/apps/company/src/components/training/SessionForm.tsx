import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';

interface Training {
  id: number;
  title: string;
}

interface Session {
  id?: number;
  training_id: number;
  title: string;
  description?: string;
  instructor_name?: string;
  location?: string;
  start_date: string;
  end_date?: string;
  max_participants?: number;
  status: 'scheduled' | 'in_progress' | 'completed' | 'cancelled';
}

interface SessionFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: Omit<Session, 'id'>) => Promise<void>;
  session?: Session | null;
  trainings: Training[];
}

const statusLabels: Record<string, string> = {
  scheduled: 'Planlandı',
  in_progress: 'Devam Ediyor',
  completed: 'Tamamlandı',
  cancelled: 'İptal Edildi',
};

const SessionForm: React.FC<SessionFormProps> = ({
  isOpen,
  onClose,
  onSubmit,
  session,
  trainings,
}) => {
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    training_id: '',
    title: '',
    description: '',
    instructor_name: '',
    location: '',
    start_date: '',
    end_date: '',
    max_participants: '',
    status: 'scheduled' as Session['status'],
  });

  useEffect(() => {
    if (isOpen) {
      if (session) {
        setFormData({
          training_id: String(session.training_id),
          title: session.title,
          description: session.description || '',
          instructor_name: session.instructor_name || '',
          location: session.location || '',
          start_date: session.start_date?.slice(0, 16) || '',
          end_date: session.end_date?.slice(0, 16) || '',
          max_participants: session.max_participants ? String(session.max_participants) : '',
          status: session.status,
        });
      } else {
        setFormData({
          training_id: '',
          title: '',
          description: '',
          instructor_name: '',
          location: '',
          start_date: '',
          end_date: '',
          max_participants: '',
          status: 'scheduled',
        });
      }
    }
  }, [isOpen, session]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));

    // Eğitim seçildiğinde başlık otomatik oluştur
    if (name === 'training_id' && value && !formData.title) {
      const training = trainings.find(t => t.id === Number(value));
      if (training) {
        const date = new Date().toLocaleDateString('tr-TR');
        setFormData(prev => ({
          ...prev,
          training_id: value,
          title: `${training.title} - ${date}`,
        }));
      }
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await onSubmit({
        training_id: Number(formData.training_id),
        title: formData.title,
        description: formData.description || undefined,
        instructor_name: formData.instructor_name || undefined,
        location: formData.location || undefined,
        start_date: formData.start_date,
        end_date: formData.end_date || undefined,
        max_participants: formData.max_participants ? Number(formData.max_participants) : undefined,
        status: formData.status,
      });
      onClose();
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={session ? 'Oturumu Düzenle' : 'Yeni Oturum'}
      size="md"
    >
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label className="form-label">Eğitim *</label>
          <select
            name="training_id"
            value={formData.training_id}
            onChange={handleChange}
            className="form-input"
            required
          >
            <option value="">Eğitim seçin</option>
            {trainings.map(t => (
              <option key={t.id} value={t.id}>{t.title}</option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label className="form-label">Oturum Başlığı *</label>
          <input
            type="text"
            name="title"
            value={formData.title}
            onChange={handleChange}
            className="form-input"
            required
          />
        </div>

        <div className="form-group">
          <label className="form-label">Açıklama</label>
          <textarea
            name="description"
            value={formData.description}
            onChange={handleChange}
            className="form-input"
            rows={2}
          />
        </div>

        <div className="form-row">
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Eğitmen</label>
            <input
              type="text"
              name="instructor_name"
              value={formData.instructor_name}
              onChange={handleChange}
              className="form-input"
              placeholder="Eğitmen adı"
            />
          </div>
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Konum</label>
            <input
              type="text"
              name="location"
              value={formData.location}
              onChange={handleChange}
              className="form-input"
              placeholder="Toplantı odası veya link"
            />
          </div>
        </div>

        <div className="form-row">
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Başlangıç *</label>
            <input
              type="datetime-local"
              name="start_date"
              value={formData.start_date}
              onChange={handleChange}
              className="form-input"
              required
            />
          </div>
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Bitiş</label>
            <input
              type="datetime-local"
              name="end_date"
              value={formData.end_date}
              onChange={handleChange}
              className="form-input"
            />
          </div>
        </div>

        <div className="form-row">
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Maks. Katılımcı</label>
            <input
              type="number"
              name="max_participants"
              value={formData.max_participants}
              onChange={handleChange}
              className="form-input"
              min={1}
              placeholder="Sınırsız"
            />
          </div>
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Durum</label>
            <select
              name="status"
              value={formData.status}
              onChange={handleChange}
              className="form-input"
            >
              {Object.entries(statusLabels).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </div>
        </div>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1.5rem', paddingTop: '1rem', borderTop: '1px solid var(--border-color)' }}>
          <button type="button" className="btn btn-ghost" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button type="submit" className="btn btn-primary" disabled={loading}>
            {loading ? 'Kaydediliyor...' : session ? 'Güncelle' : 'Oluştur'}
          </button>
        </div>
      </form>
    </Modal>
  );
};

export default SessionForm;

