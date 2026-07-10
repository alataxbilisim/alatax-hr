import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';

interface Category {
  id: number;
  name: string;
}

interface Asset {
  id?: number;
  name: string;
  description?: string;
  category_id?: number;
  asset_code?: string;
  serial_number?: string;
  brand?: string;
  model?: string;
  purchase_date?: string;
  purchase_price?: number;
  warranty_end_date?: string;
  status: 'available' | 'assigned' | 'maintenance' | 'retired';
  condition: 'new' | 'good' | 'fair' | 'poor';
}

interface AssetFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: Omit<Asset, 'id'>) => Promise<void>;
  asset?: Asset | null;
  categories: Category[];
}

const statusLabels: Record<string, string> = {
  available: 'Müsait',
  assigned: 'Zimmetli',
  maintenance: 'Bakımda',
  retired: 'Emekli',
};

const conditionLabels: Record<string, string> = {
  new: 'Yeni',
  good: 'İyi',
  fair: 'Orta',
  poor: 'Kötü',
};

const AssetForm: React.FC<AssetFormProps> = ({
  isOpen,
  onClose,
  onSubmit,
  asset,
  categories,
}) => {
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    category_id: '',
    asset_code: '',
    serial_number: '',
    brand: '',
    model: '',
    purchase_date: '',
    purchase_price: '',
    warranty_end_date: '',
    status: 'available' as Asset['status'],
    condition: 'new' as Asset['condition'],
  });

  useEffect(() => {
    if (isOpen) {
      if (asset) {
        setFormData({
          name: asset.name,
          description: asset.description || '',
          category_id: asset.category_id ? String(asset.category_id) : '',
          asset_code: asset.asset_code || '',
          serial_number: asset.serial_number || '',
          brand: asset.brand || '',
          model: asset.model || '',
          purchase_date: asset.purchase_date?.split('T')[0] || '',
          purchase_price: asset.purchase_price ? String(asset.purchase_price) : '',
          warranty_end_date: asset.warranty_end_date?.split('T')[0] || '',
          status: asset.status,
          condition: asset.condition,
        });
      } else {
        setFormData({
          name: '',
          description: '',
          category_id: '',
          asset_code: '',
          serial_number: '',
          brand: '',
          model: '',
          purchase_date: '',
          purchase_price: '',
          warranty_end_date: '',
          status: 'available',
          condition: 'new',
        });
      }
    }
  }, [isOpen, asset]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await onSubmit({
        name: formData.name,
        description: formData.description || undefined,
        category_id: formData.category_id ? Number(formData.category_id) : undefined,
        asset_code: formData.asset_code || undefined,
        serial_number: formData.serial_number || undefined,
        brand: formData.brand || undefined,
        model: formData.model || undefined,
        purchase_date: formData.purchase_date || undefined,
        purchase_price: formData.purchase_price ? Number(formData.purchase_price) : undefined,
        warranty_end_date: formData.warranty_end_date || undefined,
        status: formData.status,
        condition: formData.condition,
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
      title={asset ? 'Varlığı Düzenle' : 'Yeni Varlık'}
      size="lg"
    >
      <form onSubmit={handleSubmit}>
        <div className="form-row">
          <div className="form-group" style={{ flex: 2 }}>
            <label className="form-label">Varlık Adı *</label>
            <input
              type="text"
              name="name"
              value={formData.name}
              onChange={handleChange}
              className="form-input"
              placeholder="Örn: MacBook Pro 14"
              required
            />
          </div>
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Kategori</label>
            <select
              name="category_id"
              value={formData.category_id}
              onChange={handleChange}
              className="form-input"
            >
              <option value="">Seçin...</option>
              {categories.map(c => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          </div>
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
            <label className="form-label">Demirbaş Kodu</label>
            <input
              type="text"
              name="asset_code"
              value={formData.asset_code}
              onChange={handleChange}
              className="form-input"
              placeholder="DMB-001"
            />
          </div>
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Seri Numarası</label>
            <input
              type="text"
              name="serial_number"
              value={formData.serial_number}
              onChange={handleChange}
              className="form-input"
            />
          </div>
        </div>

        <div className="form-row">
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Marka</label>
            <input
              type="text"
              name="brand"
              value={formData.brand}
              onChange={handleChange}
              className="form-input"
              placeholder="Apple"
            />
          </div>
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Model</label>
            <input
              type="text"
              name="model"
              value={formData.model}
              onChange={handleChange}
              className="form-input"
              placeholder="MacBook Pro 14"
            />
          </div>
        </div>

        <div className="form-row">
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Satın Alma Tarihi</label>
            <input
              type="date"
              name="purchase_date"
              value={formData.purchase_date}
              onChange={handleChange}
              className="form-input"
            />
          </div>
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Satın Alma Fiyatı</label>
            <input
              type="number"
              name="purchase_price"
              value={formData.purchase_price}
              onChange={handleChange}
              className="form-input"
              min={0}
              step="0.01"
            />
          </div>
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Garanti Bitiş</label>
            <input
              type="date"
              name="warranty_end_date"
              value={formData.warranty_end_date}
              onChange={handleChange}
              className="form-input"
            />
          </div>
        </div>

        <div className="form-row">
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
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Kondisyon</label>
            <select
              name="condition"
              value={formData.condition}
              onChange={handleChange}
              className="form-input"
            >
              {Object.entries(conditionLabels).map(([value, label]) => (
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
            {loading ? 'Kaydediliyor...' : asset ? 'Güncelle' : 'Oluştur'}
          </button>
        </div>
      </form>
    </Modal>
  );
};

export default AssetForm;

