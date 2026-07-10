import React, { useEffect, useState } from 'react';
import { portalApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsMegaphone } from 'react-icons/bs';

interface Announcement {
  id: number;
  title: string;
  summary: string | null;
  type: string;
  type_label: string;
  category: string | null;
  is_pinned: boolean;
  published_at: string;
  is_read: boolean;
}

const AnnouncementsPage: React.FC = () => {
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadAnnouncements();
  }, []);

  const loadAnnouncements = async () => {
    try {
      const response = await portalApi.announcements.list();
      setAnnouncements(response.data.data.data || []);
    } catch {
      toast.error('Duyurular yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Duyurular</h1>
          <p className="page-subtitle">Şirket duyurularını takip edin</p>
        </div>
      </div>

      {loading ? (
        <div className="page-loading">
          <div className="loading-spinner"></div>
        </div>
      ) : announcements.length > 0 ? (
        <div className="row">
          {announcements.map((announcement) => (
            <div key={announcement.id} className="col-12 mb-3">
              <div
                className={`announcement-item ${!announcement.is_read ? 'unread' : ''} ${announcement.type === 'urgent' ? 'urgent' : ''}`}
              >
                <div className="announcement-header">
                  <span className="announcement-title">
                    {announcement.is_pinned && '📌 '}
                    {announcement.title}
                  </span>
                  <span className="announcement-date">
                    {new Date(announcement.published_at).toLocaleDateString('tr-TR')}
                  </span>
                </div>
                <div className="announcement-summary">
                  {announcement.summary || ''}
                </div>
                <div className="mt-2">
                  <span className={`badge bg-${announcement.type === 'urgent' ? 'danger' : 'secondary'}`}>
                    {announcement.type_label}
                  </span>
                  {!announcement.is_read && (
                    <span className="badge bg-primary ms-1">Yeni</span>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="card">
          <div className="card-body empty-state">
            <BsMegaphone size={64} className="text-muted mb-3" />
            <h3>Henüz duyuru yok</h3>
            <p>Yeni duyurular burada görünecektir</p>
          </div>
        </div>
      )}
    </div>
  );
};

export default AnnouncementsPage;

