import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';

interface Category {
  id?: number;
  name: string;
  description?: string;
}

interface CategoryFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: Omit<Category, 'id'>) => Promise<void>;
  category?: Category | null;
}

const CategoryForm: React.FC<CategoryFormProps> = ({
  isOpen,
  onClose,
  onSubmit,
  category,
}) => {
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    description: '',
  });

  useEffect(() => {
    if (isOpen) {
      if (category) {
        setFormData({
          name: category.name,
          description: category.description || '',
        });
      } else {
        setFormData({
          name: '',
          description: '',
        });
      }
    }
  }, [isOpen, category]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
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
      title={category ? 'Kategoriyi Düzenle' : 'Yeni Kategori'}
      size="sm"
    >
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label className="form-label">Kategori Adı *</label>
          <input
            type="text"
            name="name"
            value={formData.name}
            onChange={handleChange}
            className="form-input"
            placeholder="Örn: Bilgisayar"
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

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1.5rem', paddingTop: '1rem', borderTop: '1px solid var(--border-color)' }}>
          <button type="button" className="btn btn-ghost" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button type="submit" className="btn btn-primary" disabled={loading}>
            {loading ? 'Kaydediliyor...' : category ? 'Güncelle' : 'Oluştur'}
          </button>
        </div>
      </form>
    </Modal>
  );
};

export default CategoryForm;

