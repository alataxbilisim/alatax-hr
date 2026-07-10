import React, { useState } from 'react';
import { FiX, FiSave, FiShare2 } from 'react-icons/fi';
import type { Dashboard } from './types';

interface SaveDashboardModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (data: { name: string; description?: string; is_shared: boolean }) => void;
  dashboard?: Dashboard | null;
  isUpdate?: boolean;
}

const SaveDashboardModal: React.FC<SaveDashboardModalProps> = ({
  isOpen,
  onClose,
  onSave,
  dashboard,
  isUpdate = false,
}) => {
  // Parent key={dashboard?.id ?? 'new'} ile remount — effect ile prop sync yok
  const [name, setName] = useState(dashboard?.name ?? '');
  const [description, setDescription] = useState(dashboard?.description ?? '');
  const [isShared, setIsShared] = useState(dashboard?.is_shared ?? false);

  if (!isOpen) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;

    onSave({
      name: name.trim(),
      description: description.trim() || undefined,
      is_shared: isShared,
    });
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 480 }}>
        <div className="modal-header">
          <h3>{isUpdate ? 'Dashboard Güncelle' : 'Dashboard Kaydet'}</h3>
          <button className="modal-close" onClick={onClose}>
            <FiX />
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="modal-body">
            <div className="form-group">
              <label>İsim *</label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Dashboard adı"
                autoFocus
                required
              />
            </div>
            <div className="form-group">
              <label>Açıklama</label>
              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Opsiyonel açıklama"
                rows={3}
              />
            </div>
            <div className="form-group">
              <label className="checkbox-label">
                <input
                  type="checkbox"
                  checked={isShared}
                  onChange={(e) => setIsShared(e.target.checked)}
                />
                <FiShare2 size={14} />
                <span>Ekiple paylaş</span>
              </label>
            </div>
          </div>

          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={onClose}>
              İptal
            </button>
            <button type="submit" className="btn btn-primary" disabled={!name.trim()}>
              <FiSave /> {isUpdate ? 'Güncelle' : 'Kaydet'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default SaveDashboardModal;
