import React, { useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { useTranslation } from '@shared/i18n';
import { authApi } from '@shared/services/api';
import { setUser } from '@shared/store/slices/authSlice';
import toast from 'react-hot-toast';
import { RootState, AppDispatch } from '../../store';

const AccountProfilePage: React.FC = () => {
  const { t } = useTranslation('common');
  const dispatch = useDispatch<AppDispatch>();
  const { user } = useSelector((state: RootState) => state.auth);

  const [name, setName] = useState(user?.name ?? '');
  const [phone, setPhone] = useState(user?.phone ?? '');
  const [title, setTitle] = useState(user?.title ?? '');
  const [avatarFile, setAvatarFile] = useState<File | null>(null);
  const [saving, setSaving] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      let response;
      if (avatarFile) {
        const form = new FormData();
        form.append('name', name);
        if (phone) form.append('phone', phone);
        if (title) form.append('title', title);
        form.append('avatar', avatarFile);
        response = await authApi.updateProfile(form);
      } else {
        response = await authApi.updateProfile({
          name,
          phone: phone || null,
          title: title || null,
        });
      }
      const updated = response.data.data.user;
      dispatch(setUser(updated));
      localStorage.setItem('user', JSON.stringify(updated));
      setAvatarFile(null);
      toast.success(t('account.saveSuccess'));
    } catch {
      toast.error(t('account.saveError'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header" style={{ marginBottom: 'var(--space-4)' }}>
        <h1 className="page-title">{t('account.profileTitle')}</h1>
        <p className="page-subtitle">{t('account.profileSubtitle')}</p>
      </div>

      <div className="card" style={{ maxWidth: 560 }}>
        <div className="card-body">
          <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-3)' }}>
            <div className="form-group">
              <label className="form-label" htmlFor="account-name">{t('account.name')}</label>
              <input
                id="account-name"
                className="form-control"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
              />
            </div>

            <div className="form-group">
              <label className="form-label" htmlFor="account-email">{t('account.email')}</label>
              <input
                id="account-email"
                className="form-control"
                value={user?.email ?? ''}
                readOnly
                disabled
              />
              <small className="form-hint">{t('account.emailReadOnly')}</small>
            </div>

            <div className="form-group">
              <label className="form-label" htmlFor="account-phone">{t('account.phone')}</label>
              <input
                id="account-phone"
                className="form-control"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
              />
            </div>

            <div className="form-group">
              <label className="form-label" htmlFor="account-title">{t('account.title')}</label>
              <input
                id="account-title"
                className="form-control"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
              />
            </div>

            <div className="form-group">
              <label className="form-label" htmlFor="account-avatar">{t('account.avatar')}</label>
              <input
                id="account-avatar"
                type="file"
                accept="image/jpeg,image/png"
                className="form-control"
                onChange={(e) => setAvatarFile(e.target.files?.[0] ?? null)}
              />
              <small className="form-hint">{t('account.avatarHint')}</small>
            </div>

            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? t('loading') : t('save')}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
};

export default AccountProfilePage;
