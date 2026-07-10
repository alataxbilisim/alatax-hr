import React, { useEffect, useState, useCallback } from 'react';
import { companyApi, apiKeysApi } from '@shared/services/api';
import type { Company, CompanySettings, ApiKey } from '@shared/types';
import toast from 'react-hot-toast';
import {
  BsBuilding,
  BsGear,
  BsGrid,
  BsCheck,
  BsX,
  BsSave,
  BsArrowClockwise,
  BsEnvelope,
  BsChatDots,
  BsShieldCheck,
  BsPlug,
  BsStar,
  BsKey,
  BsPlus,
  BsPencil,
  BsTrash,
  BsEye,
  BsEyeSlash,
  BsCopy,
} from 'react-icons/bs';
import LogoUpload from '../../components/ui/LogoUpload';
import { useFormValidation } from '@shared/hooks/useFormValidation';
import { required, email, url } from '@shared/utils/validation';
import { Modal } from '../../components/ui';
import ApiKeyForm from './ApiKeyForm';

interface Module {
  id: number;
  name: string;
  slug: string;
  description?: string;
  is_core: boolean;
  is_active: boolean;
}

type TabType = 'general' | 'smtp' | 'sms' | 'settings' | 'notifications' | 'api-keys' | 'license' | 'integrations' | 'modules';

const SettingsPage: React.FC = () => {
  const [activeTab, setActiveTab] = useState<TabType>('general');

  // Company data
  const [company, setCompany] = useState<Company | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  // Settings
  const [settings, setSettings] = useState<CompanySettings | null>(null);
  const [settingsLoading, setSettingsLoading] = useState(false);
  const [settingsSaving, setSettingsSaving] = useState(false);
  const [smtpTesting, setSmtpTesting] = useState(false);
  const [smsTesting, setSmsTesting] = useState(false);

  // Modules
  const [modules, setModules] = useState<Module[]>([]);
  const [modulesLoading, setModulesLoading] = useState(true);

  // API Keys
  const [apiKeys, setApiKeys] = useState<ApiKey[]>([]);
  const [apiKeysLoading, setApiKeysLoading] = useState(false);
  const [apiKeyFormOpen, setApiKeyFormOpen] = useState(false);
  const [selectedApiKey, setSelectedApiKey] = useState<ApiKey | null>(null);
  const [newApiKey, setNewApiKey] = useState<string | null>(null);
  const [showApiKey, setShowApiKey] = useState<Record<number, boolean>>({});

  // Form data
  const {
    values: companyValues,
    errors: companyErrors,
    touched: companyTouched,
    handleChange: handleCompanyChange,
    handleBlur: handleCompanyBlur,
    setValues: setCompanyValues,
    validate: validateCompany,
  } = useFormValidation({
    initialValues: {
      name: '',
      legal_name: '',
      tax_office: '',
      tax_number: '',
      phone: '',
      email: '',
      website: '',
      address: '',
      city: '',
      district: '',
      postal_code: '',
      country: 'Türkiye',
      sector: '',
      employee_count: '',
    },
    schema: {
      name: [required()],
      email: [email()],
      website: [url()],
    },
  });

  const {
    values: smtpValues,
    handleChange: handleSmtpChange,
    setValues: setSmtpValues,
  } = useFormValidation({
    initialValues: {
      host: '',
      port: 587,
      encryption: 'tls' as 'tls' | 'ssl' | 'none',
      username: '',
      password: '',
      from_address: '',
      from_name: '',
    },
  });

  const {
    values: smsValues,
    handleChange: handleSmsChange,
    setValues: setSmsValues,
  } = useFormValidation({
    initialValues: {
      provider: 'netgsm' as 'netgsm' | 'iletimerkezi' | 'twilio' | 'custom',
      username: '',
      password: '',
      sender: '',
      api_url: '',
    },
  });

  const {
    values: generalValues,
    handleChange: handleGeneralChange,
    setValues: setGeneralValues,
  } = useFormValidation({
    initialValues: {
      timezone: 'Europe/Istanbul',
      language: 'tr' as 'tr' | 'en',
      date_format: 'd/m/Y',
      currency: 'TRY',
      working_days: [1, 2, 3, 4, 5] as number[],
    },
  });

  const {
    values: integrationValues,
    handleChange: handleIntegrationChange,
    setValues: setIntegrationValues,
  } = useFormValidation({
    initialValues: {
      webhook_url: '',
      api_key: '',
    },
  });

  const {
    values: notificationValues,
    setValues: setNotificationValues,
    setFieldValue: setNotificationFieldValue,
  } = useFormValidation({
    initialValues: {
      email_enabled: true,
      email_leave_requests: true,
      email_approvals: true,
      email_reminders: true,
      email_reports: false,
      sms_enabled: false,
      sms_leave_requests: false,
      sms_approvals: false,
      sms_reminders: false,
      push_enabled: true,
      push_leave_requests: true,
      push_approvals: true,
      push_reminders: true,
    },
  });

  const loadCompany = useCallback(async () => {
    try {
      setLoading(true);
      const response = await companyApi.get();
      const data = response.data.data;
      setCompany(data);
      setCompanyValues({
        name: data.name || '',
        legal_name: data.legal_name || '',
        tax_office: data.tax_office || '',
        tax_number: data.tax_number || '',
        phone: data.phone || '',
        email: data.email || '',
        website: data.website || '',
        address: data.address || '',
        city: data.city || '',
        district: data.district || '',
        postal_code: data.postal_code || '',
        country: data.country || 'Türkiye',
        sector: data.sector || '',
        employee_count: data.employee_count || '',
      });
    } catch {
      toast.error('Firma bilgileri yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [setCompanyValues]);

  const loadSettings = useCallback(async () => {
    try {
      setSettingsLoading(true);
      const response = await companyApi.getSettings();
      const data = response.data.data;
      setSettings(data);

      if (data.smtp) {
        setSmtpValues({
          ...data.smtp,
          password: '', // Don't show password
        });
      }

      if (data.sms) {
        setSmsValues({
          ...data.sms,
          password: '', // Don't show password
        });
      }

      if (data.general) {
        setGeneralValues(data.general);
      }

      if (data.integrations) {
        setIntegrationValues(data.integrations);
      }

      if (data.notifications) {
        setNotificationValues({
          email_enabled: data.notifications.email_enabled ?? true,
          email_leave_requests: data.notifications.email_leave_requests ?? true,
          email_approvals: data.notifications.email_approvals ?? true,
          email_reminders: data.notifications.email_reminders ?? true,
          email_reports: data.notifications.email_reports ?? false,
          sms_enabled: data.notifications.sms_enabled ?? false,
          sms_leave_requests: data.notifications.sms_leave_requests ?? false,
          sms_approvals: data.notifications.sms_approvals ?? false,
          sms_reminders: data.notifications.sms_reminders ?? false,
          push_enabled: data.notifications.push_enabled ?? true,
          push_leave_requests: data.notifications.push_leave_requests ?? true,
          push_approvals: data.notifications.push_approvals ?? true,
          push_reminders: data.notifications.push_reminders ?? true,
        });
      }
    } catch {
      toast.error('Ayarlar yüklenemedi');
    } finally {
      setSettingsLoading(false);
    }
  }, [setSmtpValues, setSmsValues, setGeneralValues, setIntegrationValues, setNotificationValues]);

  const loadModules = useCallback(async () => {
    try {
      setModulesLoading(true);
      const response = await companyApi.modules();
      setModules(response.data.data || []);
    } catch {
      // Silent fail
    } finally {
      setModulesLoading(false);
    }
  }, []);

  const loadApiKeys = useCallback(async () => {
    try {
      setApiKeysLoading(true);
      const response = await apiKeysApi.list();
      setApiKeys(response.data.data?.data || []);
    } catch {
      toast.error('API anahtarları yüklenemedi');
    } finally {
      setApiKeysLoading(false);
    }
  }, []);

  // Hash-based navigation
  useEffect(() => {
    const hash = window.location.hash.replace('#', '');
    if (hash && ['general', 'smtp', 'sms', 'settings', 'notifications', 'api-keys', 'license', 'integrations', 'modules'].includes(hash)) {
      setActiveTab(hash as TabType);
    }
  }, []);

  // Update hash when tab changes
  useEffect(() => {
    if (activeTab) {
      window.location.hash = activeTab;
    }
  }, [activeTab]);

  // Listen for hash changes
  useEffect(() => {
    const handleHashChange = () => {
      const hash = window.location.hash.replace('#', '');
      if (hash && ['general', 'smtp', 'sms', 'settings', 'notifications', 'api-keys', 'license', 'integrations', 'modules'].includes(hash)) {
        setActiveTab(hash as TabType);
      }
    };

    window.addEventListener('hashchange', handleHashChange);
    return () => window.removeEventListener('hashchange', handleHashChange);
  }, []);

  useEffect(() => {
    const hash = window.location.hash.replace('#', '');
    if (hash && ['general', 'smtp', 'sms', 'settings', 'notifications', 'api-keys', 'license', 'integrations', 'modules'].includes(hash)) {
      setActiveTab(hash as TabType);
    }
  }, []);

  useEffect(() => {
    loadCompany();
    loadSettings();
    loadModules();
  }, [loadCompany, loadSettings, loadModules]);

  useEffect(() => {
    if (activeTab === 'smtp' || activeTab === 'sms' || activeTab === 'settings' || activeTab === 'notifications' || activeTab === 'api-keys' || activeTab === 'integrations') {
      loadSettings();
    }
    if (activeTab === 'modules') {
      loadCompany();
      loadModules();
    }
    if (activeTab === 'api-keys') {
      loadApiKeys();
    }
  }, [activeTab, loadSettings, loadCompany, loadModules, loadApiKeys]);

  const handleCompanySubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateCompany()) {
      return;
    }

    setSaving(true);
    try {
      await companyApi.update(companyValues);
      toast.success('Firma bilgileri güncellendi');
      loadCompany();
    } catch {
      toast.error('Güncelleme başarısız');
    } finally {
      setSaving(false);
    }
  };

  const handleSmtpSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    setSettingsSaving(true);
    try {
      await companyApi.updateSettings({
        smtp: smtpValues,
      });
      toast.success('SMTP ayarları güncellendi');
      loadSettings();
    } catch {
      toast.error('Güncelleme başarısız');
    } finally {
      setSettingsSaving(false);
    }
  };

  const handleSmsSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    setSettingsSaving(true);
    try {
      await companyApi.updateSettings({
        sms: smsValues,
      });
      toast.success('SMS ayarları güncellendi');
      loadSettings();
    } catch {
      toast.error('Güncelleme başarısız');
    } finally {
      setSettingsSaving(false);
    }
  };

  const handleGeneralSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    setSettingsSaving(true);
    try {
      await companyApi.updateSettings({
        general: generalValues,
      });
      toast.success('Genel ayarlar güncellendi');
      loadSettings();
    } catch {
      toast.error('Güncelleme başarısız');
    } finally {
      setSettingsSaving(false);
    }
  };

  const handleNotificationSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    setSettingsSaving(true);
    try {
      await companyApi.updateSettings({
        notifications: notificationValues,
      });
      toast.success('Bildirim tercihleri güncellendi');
      loadSettings();
    } catch {
      toast.error('Güncelleme başarısız');
    } finally {
      setSettingsSaving(false);
    }
  };

  const handleIntegrationSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    setSettingsSaving(true);
    try {
      await companyApi.updateSettings({
        integrations: integrationValues,
      });
      toast.success('Entegrasyon ayarları güncellendi');
      loadSettings();
    } catch {
      toast.error('Güncelleme başarısız');
    } finally {
      setSettingsSaving(false);
    }
  };

  const handleTestSmtp = async () => {
    const email = prompt('Test e-postası göndermek için e-posta adresinizi girin:');
    if (!email) return;

    setSmtpTesting(true);
    try {
      await companyApi.testSmtp({ to: email });
      toast.success('Test e-postası başarıyla gönderildi');
    } catch {
      toast.error('Test e-postası gönderilemedi');
    } finally {
      setSmtpTesting(false);
    }
  };

  const handleTestSms = async () => {
    const phone = prompt('Test SMS göndermek için telefon numaranızı girin:');
    if (!phone) return;

    setSmsTesting(true);
    try {
      await companyApi.testSms({ phone });
      toast.success('Test SMS başarıyla gönderildi');
    } catch {
      toast.error('Test SMS gönderilemedi');
    } finally {
      setSmsTesting(false);
    }
  };

  if (loading) {
    return (
      <div className="page-loading">
        <div className="loading-spinner" />
      </div>
    );
  }

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div className="page-header-content">
          <h1>Ayarlar</h1>
          <p>Firma bilgileri, ayarlar ve modül yönetimi</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="tabs" style={{ overflowX: 'auto', flexWrap: 'wrap' }}>
        <button
          className={`tab ${activeTab === 'general' ? 'active' : ''}`}
          onClick={() => setActiveTab('general')}
        >
          <BsBuilding style={{ marginRight: 6 }} /> Genel Bilgiler
        </button>
        <button
          className={`tab ${activeTab === 'smtp' ? 'active' : ''}`}
          onClick={() => setActiveTab('smtp')}
        >
          <BsEnvelope style={{ marginRight: 6 }} /> SMTP
        </button>
        <button
          className={`tab ${activeTab === 'sms' ? 'active' : ''}`}
          onClick={() => setActiveTab('sms')}
        >
          <BsChatDots style={{ marginRight: 6 }} /> SMS
        </button>
        <button
          className={`tab ${activeTab === 'settings' ? 'active' : ''}`}
          onClick={() => setActiveTab('settings')}
        >
          <BsGear style={{ marginRight: 6 }} /> Genel Ayarlar
        </button>
        <button
          className={`tab ${activeTab === 'notifications' ? 'active' : ''}`}
          onClick={() => setActiveTab('notifications')}
        >
          <BsShieldCheck style={{ marginRight: 6 }} /> Bildirimler
        </button>
        <button
          className={`tab ${activeTab === 'api-keys' ? 'active' : ''}`}
          onClick={() => setActiveTab('api-keys')}
        >
          <BsKey style={{ marginRight: 6 }} /> API Anahtarları
        </button>
        <button
          className={`tab ${activeTab === 'license' ? 'active' : ''}`}
          onClick={() => setActiveTab('license')}
        >
          <BsStar style={{ marginRight: 6 }} /> Lisans
        </button>
        <button
          className={`tab ${activeTab === 'integrations' ? 'active' : ''}`}
          onClick={() => setActiveTab('integrations')}
        >
          <BsPlug style={{ marginRight: 6 }} /> Entegrasyonlar
        </button>
        <button
          className={`tab ${activeTab === 'modules' ? 'active' : ''}`}
          onClick={() => setActiveTab('modules')}
        >
          <BsGrid style={{ marginRight: 6 }} /> Modüller
        </button>
      </div>

      {/* Content */}
      {activeTab === 'general' && (
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Firma Bilgileri</h3>
          </div>
          <div className="card-body">
            <form onSubmit={handleCompanySubmit}>
              {/* Logo */}
              <div className="form-group" style={{ marginBottom: '2rem' }}>
                <label className="form-label">Logo</label>
                <LogoUpload
                  currentLogo={company?.logo}
                  onUploadSuccess={() => loadCompany()}
                  onDeleteSuccess={() => loadCompany()}
                />
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 250px), 1fr))', gap: '1rem' }}>
                {/* Name */}
                <div className="form-group">
                  <label className="form-label">
                    Firma Adı <span style={{ color: 'var(--danger)' }}>*</span>
                  </label>
                  <input
                    type="text"
                    name="name"
                    className={`form-control ${companyErrors.name && companyTouched.name ? 'error' : ''}`}
                    value={companyValues.name}
                    onChange={handleCompanyChange}
                    onBlur={handleCompanyBlur}
                  />
                  {companyErrors.name && companyTouched.name && (
                    <div className="form-error">{companyErrors.name}</div>
                  )}
                </div>

                {/* Legal Name */}
                <div className="form-group">
                  <label className="form-label">Resmi Unvan</label>
                  <input
                    type="text"
                    name="legal_name"
                    className="form-control"
                    value={companyValues.legal_name}
                    onChange={handleCompanyChange}
                  />
                </div>

                {/* Tax Office */}
                <div className="form-group">
                  <label className="form-label">Vergi Dairesi</label>
                  <input
                    type="text"
                    name="tax_office"
                    className="form-control"
                    value={companyValues.tax_office}
                    onChange={handleCompanyChange}
                  />
                </div>

                {/* Tax Number */}
                <div className="form-group">
                  <label className="form-label">Vergi Numarası</label>
                  <input
                    type="text"
                    name="tax_number"
                    className="form-control"
                    value={companyValues.tax_number}
                    onChange={handleCompanyChange}
                    maxLength={10}
                  />
                </div>

                {/* Email */}
                <div className="form-group">
                  <label className="form-label">E-posta</label>
                  <input
                    type="email"
                    name="email"
                    className={`form-control ${companyErrors.email && companyTouched.email ? 'error' : ''}`}
                    value={companyValues.email}
                    onChange={handleCompanyChange}
                    onBlur={handleCompanyBlur}
                  />
                  {companyErrors.email && companyTouched.email && (
                    <div className="form-error">{companyErrors.email}</div>
                  )}
                </div>

                {/* Phone */}
                <div className="form-group">
                  <label className="form-label">Telefon</label>
                  <input
                    type="tel"
                    name="phone"
                    className="form-control"
                    value={companyValues.phone}
                    onChange={handleCompanyChange}
                    placeholder="0xxx xxx xx xx"
                  />
                </div>

                {/* Website */}
                <div className="form-group">
                  <label className="form-label">Web Sitesi</label>
                  <input
                    type="url"
                    name="website"
                    className={`form-control ${companyErrors.website && companyTouched.website ? 'error' : ''}`}
                    value={companyValues.website}
                    onChange={handleCompanyChange}
                    onBlur={handleCompanyBlur}
                    placeholder="https://example.com"
                  />
                  {companyErrors.website && companyTouched.website && (
                    <div className="form-error">{companyErrors.website}</div>
                  )}
                </div>

                {/* Country */}
                <div className="form-group">
                  <label className="form-label">Ülke</label>
                  <input
                    type="text"
                    name="country"
                    className="form-control"
                    value={companyValues.country}
                    onChange={handleCompanyChange}
                  />
                </div>

                {/* City */}
                <div className="form-group">
                  <label className="form-label">Şehir</label>
                  <input
                    type="text"
                    name="city"
                    className="form-control"
                    value={companyValues.city}
                    onChange={handleCompanyChange}
                  />
                </div>

                {/* District */}
                <div className="form-group">
                  <label className="form-label">İlçe</label>
                  <input
                    type="text"
                    name="district"
                    className="form-control"
                    value={companyValues.district}
                    onChange={handleCompanyChange}
                  />
                </div>

                {/* Postal Code */}
                <div className="form-group">
                  <label className="form-label">Posta Kodu</label>
                  <input
                    type="text"
                    name="postal_code"
                    className="form-control"
                    value={companyValues.postal_code}
                    onChange={handleCompanyChange}
                    maxLength={10}
                  />
                </div>

                {/* Sector */}
                <div className="form-group">
                  <label className="form-label">Sektör</label>
                  <input
                    type="text"
                    name="sector"
                    className="form-control"
                    value={companyValues.sector}
                    onChange={handleCompanyChange}
                  />
                </div>

                {/* Employee Count */}
                <div className="form-group">
                  <label className="form-label">Çalışan Sayısı</label>
                  <input
                    type="text"
                    name="employee_count"
                    className="form-control"
                    value={companyValues.employee_count}
                    onChange={handleCompanyChange}
                    placeholder="Örn: 1-10, 11-50"
                  />
                </div>
              </div>

              {/* Address */}
              <div className="form-group">
                <label className="form-label">Adres</label>
                <textarea
                  name="address"
                  className="form-control"
                  value={companyValues.address}
                  onChange={handleCompanyChange}
                  rows={2}
                />
              </div>

              <div style={{ marginTop: '1.5rem' }}>
                <button type="submit" className="btn btn-primary" disabled={saving}>
                  {saving ? (
                    <>
                      <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                      Kaydediliyor...
                    </>
                  ) : (
                    <>
                      <BsSave /> Kaydet
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {activeTab === 'smtp' && (
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">SMTP Mail Ayarları</h3>
          </div>
          <div className="card-body">
            {settingsLoading ? (
              <div className="page-loading" style={{ minHeight: 200 }}>
                <div className="loading-spinner" />
              </div>
            ) : (
              <form onSubmit={handleSmtpSubmit}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 250px), 1fr))', gap: '1rem' }}>
                  <div className="form-group" style={{ gridColumn: '1 / -1' }}>
                    <label className="form-label">SMTP Sunucu</label>
                    <input
                      type="text"
                      name="host"
                      className="form-control"
                      value={smtpValues.host}
                      onChange={handleSmtpChange}
                      placeholder="smtp.gmail.com"
                    />
                  </div>

                  <div className="form-group">
                    <label className="form-label">Port</label>
                    <input
                      type="number"
                      name="port"
                      className="form-control"
                      value={smtpValues.port}
                      onChange={handleSmtpChange}
                    />
                  </div>

                  <div className="form-group">
                    <label className="form-label">Şifreleme</label>
                    <select
                      name="encryption"
                      className="form-control"
                      value={smtpValues.encryption}
                      onChange={handleSmtpChange}
                    >
                      <option value="tls">TLS</option>
                      <option value="ssl">SSL</option>
                      <option value="none">Yok</option>
                    </select>
                  </div>

                  <div className="form-group">
                    <label className="form-label">Kullanıcı Adı</label>
                    <input
                      type="text"
                      name="username"
                      className="form-control"
                      value={smtpValues.username}
                      onChange={handleSmtpChange}
                    />
                  </div>

                  <div className="form-group">
                    <label className="form-label">Şifre</label>
                    <input
                      type="password"
                      name="password"
                      className="form-control"
                      value={smtpValues.password}
                      onChange={handleSmtpChange}
                      placeholder={settings?.smtp ? 'Değiştirmek için yeni şifre girin' : 'Şifre'}
                    />
                  </div>

                  <div className="form-group">
                    <label className="form-label">Gönderen E-posta</label>
                    <input
                      type="email"
                      name="from_address"
                      className="form-control"
                      value={smtpValues.from_address}
                      onChange={handleSmtpChange}
                    />
                  </div>

                  <div className="form-group">
                    <label className="form-label">Gönderen İsim</label>
                    <input
                      type="text"
                      name="from_name"
                      className="form-control"
                      value={smtpValues.from_name}
                      onChange={handleSmtpChange}
                    />
                  </div>
                </div>

                <div style={{ marginTop: '1.5rem', display: 'flex', gap: '0.5rem' }}>
                  <button type="submit" className="btn btn-primary" disabled={settingsSaving}>
                    {settingsSaving ? (
                      <>
                        <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                        Kaydediliyor...
                      </>
                    ) : (
                      <>
                        <BsSave /> Kaydet
                      </>
                    )}
                  </button>
                  <button
                    type="button"
                    className="btn btn-ghost"
                    onClick={handleTestSmtp}
                    disabled={smtpTesting || !smtpValues.host}
                  >
                    {smtpTesting ? (
                      <>
                        <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                        Test ediliyor...
                      </>
                    ) : (
                      <>
                        <BsEnvelope /> Test E-postası Gönder
                      </>
                    )}
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}

      {activeTab === 'sms' && (
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">SMS Ayarları</h3>
          </div>
          <div className="card-body">
            {settingsLoading ? (
              <div className="page-loading" style={{ minHeight: 200 }}>
                <div className="loading-spinner" />
              </div>
            ) : (
              <form onSubmit={handleSmsSubmit}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 250px), 1fr))', gap: '1rem' }}>
                  <div className="form-group">
                    <label className="form-label">SMS Sağlayıcı</label>
                    <select
                      name="provider"
                      className="form-control"
                      value={smsValues.provider}
                      onChange={handleSmsChange}
                    >
                      <option value="netgsm">NetGSM</option>
                      <option value="iletimerkezi">İleti Merkezi</option>
                      <option value="twilio">Twilio</option>
                      <option value="custom">Özel API</option>
                    </select>
                  </div>

                  <div className="form-group">
                    <label className="form-label">Kullanıcı Adı / API Key</label>
                    <input
                      type="text"
                      name="username"
                      className="form-control"
                      value={smsValues.username}
                      onChange={handleSmsChange}
                    />
                  </div>

                  <div className="form-group">
                    <label className="form-label">Şifre</label>
                    <input
                      type="password"
                      name="password"
                      className="form-control"
                      value={smsValues.password}
                      onChange={handleSmsChange}
                      placeholder={settings?.sms ? 'Değiştirmek için yeni şifre girin' : 'Şifre'}
                    />
                  </div>

                  <div className="form-group">
                    <label className="form-label">Gönderen (Sender)</label>
                    <input
                      type="text"
                      name="sender"
                      className="form-control"
                      value={smsValues.sender}
                      onChange={handleSmsChange}
                      maxLength={20}
                    />
                  </div>

                  {smsValues.provider === 'custom' && (
                    <div className="form-group" style={{ gridColumn: '1 / -1' }}>
                      <label className="form-label">API URL</label>
                      <input
                        type="url"
                        name="api_url"
                        className="form-control"
                        value={smsValues.api_url}
                        onChange={handleSmsChange}
                        placeholder="https://api.example.com/sms"
                      />
                    </div>
                  )}
                </div>

                <div style={{ marginTop: '1.5rem', display: 'flex', gap: '0.5rem' }}>
                  <button type="submit" className="btn btn-primary" disabled={settingsSaving}>
                    {settingsSaving ? (
                      <>
                        <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                        Kaydediliyor...
                      </>
                    ) : (
                      <>
                        <BsSave /> Kaydet
                      </>
                    )}
                  </button>
                  <button
                    type="button"
                    className="btn btn-ghost"
                    onClick={handleTestSms}
                    disabled={smsTesting || !smsValues.username}
                  >
                    {smsTesting ? (
                      <>
                        <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                        Test ediliyor...
                      </>
                    ) : (
                      <>
                        <BsChatDots /> Test SMS Gönder
                      </>
                    )}
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}

      {activeTab === 'settings' && (
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Genel Ayarlar</h3>
          </div>
          <div className="card-body">
            {settingsLoading ? (
              <div className="page-loading" style={{ minHeight: 200 }}>
                <div className="loading-spinner" />
              </div>
            ) : (
              <form onSubmit={handleGeneralSubmit}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 250px), 1fr))', gap: '1rem' }}>
                  <div className="form-group">
                    <label className="form-label">Saat Dilimi</label>
                    <select
                      name="timezone"
                      className="form-control"
                      value={generalValues.timezone}
                      onChange={handleGeneralChange}
                    >
                      <option value="Europe/Istanbul">Europe/Istanbul (GMT+3)</option>
                      <option value="UTC">UTC (GMT+0)</option>
                      <option value="America/New_York">America/New_York (GMT-5)</option>
                      <option value="Europe/London">Europe/London (GMT+0)</option>
                    </select>
                  </div>

                  <div className="form-group">
                    <label className="form-label">Dil</label>
                    <select
                      name="language"
                      className="form-control"
                      value={generalValues.language}
                      onChange={handleGeneralChange}
                    >
                      <option value="tr">Türkçe</option>
                      <option value="en">English</option>
                    </select>
                  </div>

                  <div className="form-group">
                    <label className="form-label">Tarih Formatı</label>
                    <select
                      name="date_format"
                      className="form-control"
                      value={generalValues.date_format}
                      onChange={handleGeneralChange}
                    >
                      <option value="d/m/Y">GG/AA/YYYY (31/12/2024)</option>
                      <option value="Y-m-d">YYYY-AA-GG (2024-12-31)</option>
                      <option value="d.m.Y">GG.AA.YYYY (31.12.2024)</option>
                    </select>
                  </div>

                  <div className="form-group">
                    <label className="form-label">Para Birimi</label>
                    <select
                      name="currency"
                      className="form-control"
                      value={generalValues.currency}
                      onChange={handleGeneralChange}
                    >
                      <option value="TRY">TRY (₺)</option>
                      <option value="USD">USD ($)</option>
                      <option value="EUR">EUR (€)</option>
                    </select>
                  </div>

                  <div className="form-group" style={{ gridColumn: '1 / -1' }}>
                    <label className="form-label">Çalışma Günleri</label>
                    <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                      {[
                        { value: 0, label: 'Pazar' },
                        { value: 1, label: 'Pazartesi' },
                        { value: 2, label: 'Salı' },
                        { value: 3, label: 'Çarşamba' },
                        { value: 4, label: 'Perşembe' },
                        { value: 5, label: 'Cuma' },
                        { value: 6, label: 'Cumartesi' },
                      ].map((day) => (
                        <label key={day.value} className="form-checkbox">
                          <input
                            type="checkbox"
                            checked={(generalValues.working_days as number[]).includes(day.value)}
                            onChange={(e) => {
                              const current = generalValues.working_days as number[];
                              if (e.target.checked) {
                                setGeneralValues({ ...generalValues, working_days: [...current, day.value] });
                              } else {
                                setGeneralValues({ ...generalValues, working_days: current.filter((d) => d !== day.value) });
                              }
                            }}
                          />
                          <span>{day.label}</span>
                        </label>
                      ))}
                    </div>
                  </div>
                </div>

                <div style={{ marginTop: '1.5rem' }}>
                  <button type="submit" className="btn btn-primary" disabled={settingsSaving}>
                    {settingsSaving ? (
                      <>
                        <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                        Kaydediliyor...
                      </>
                    ) : (
                      <>
                        <BsSave /> Kaydet
                      </>
                    )}
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}

      {activeTab === 'notifications' && (
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Bildirim Tercihleri</h3>
          </div>
          <div className="card-body">
            {settingsLoading ? (
              <div className="page-loading" style={{ minHeight: 200 }}>
                <div className="loading-spinner" />
              </div>
            ) : (
              <form onSubmit={handleNotificationSubmit}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '2rem' }}>
                  {/* E-posta Bildirimleri */}
                  <div>
                    <h4 style={{ marginBottom: '1rem', fontSize: '1rem', fontWeight: 600 }}>E-posta Bildirimleri</h4>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                      <label className="form-checkbox">
                        <input
                          type="checkbox"
                          name="email_enabled"
                          checked={notificationValues.email_enabled}
                          onChange={(e) => setNotificationFieldValue('email_enabled', e.target.checked)}
                        />
                        <span>E-posta bildirimlerini etkinleştir</span>
                      </label>
                      {notificationValues.email_enabled && (
                        <>
                          <label className="form-checkbox" style={{ marginLeft: '1.5rem' }}>
                            <input
                              type="checkbox"
                              name="email_leave_requests"
                              checked={notificationValues.email_leave_requests}
                              onChange={(e) => setNotificationFieldValue('email_leave_requests', e.target.checked)}
                            />
                            <span>İzin talepleri</span>
                          </label>
                          <label className="form-checkbox" style={{ marginLeft: '1.5rem' }}>
                            <input
                              type="checkbox"
                              name="email_approvals"
                              checked={notificationValues.email_approvals}
                              onChange={(e) => setNotificationFieldValue('email_approvals', e.target.checked)}
                            />
                            <span>Onay bildirimleri</span>
                          </label>
                          <label className="form-checkbox" style={{ marginLeft: '1.5rem' }}>
                            <input
                              type="checkbox"
                              name="email_reminders"
                              checked={notificationValues.email_reminders}
                              onChange={(e) => setNotificationFieldValue('email_reminders', e.target.checked)}
                            />
                            <span>Hatırlatmalar</span>
                          </label>
                          <label className="form-checkbox" style={{ marginLeft: '1.5rem' }}>
                            <input
                              type="checkbox"
                              name="email_reports"
                              checked={notificationValues.email_reports}
                              onChange={(e) => setNotificationFieldValue('email_reports', e.target.checked)}
                            />
                            <span>Raporlar</span>
                          </label>
                        </>
                      )}
                    </div>
                  </div>

                  {/* SMS Bildirimleri */}
                  <div>
                    <h4 style={{ marginBottom: '1rem', fontSize: '1rem', fontWeight: 600 }}>SMS Bildirimleri</h4>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                      <label className="form-checkbox">
                        <input
                          type="checkbox"
                          name="sms_enabled"
                          checked={notificationValues.sms_enabled}
                          onChange={(e) => setNotificationFieldValue('sms_enabled', e.target.checked)}
                        />
                        <span>SMS bildirimlerini etkinleştir</span>
                      </label>
                      {notificationValues.sms_enabled && (
                        <>
                          <label className="form-checkbox" style={{ marginLeft: '1.5rem' }}>
                            <input
                              type="checkbox"
                              name="sms_leave_requests"
                              checked={notificationValues.sms_leave_requests}
                              onChange={(e) => setNotificationFieldValue('sms_leave_requests', e.target.checked)}
                            />
                            <span>İzin talepleri</span>
                          </label>
                          <label className="form-checkbox" style={{ marginLeft: '1.5rem' }}>
                            <input
                              type="checkbox"
                              name="sms_approvals"
                              checked={notificationValues.sms_approvals}
                              onChange={(e) => setNotificationFieldValue('sms_approvals', e.target.checked)}
                            />
                            <span>Onay bildirimleri</span>
                          </label>
                          <label className="form-checkbox" style={{ marginLeft: '1.5rem' }}>
                            <input
                              type="checkbox"
                              name="sms_reminders"
                              checked={notificationValues.sms_reminders}
                              onChange={(e) => setNotificationFieldValue('sms_reminders', e.target.checked)}
                            />
                            <span>Hatırlatmalar</span>
                          </label>
                        </>
                      )}
                    </div>
                  </div>

                  {/* Push Bildirimleri */}
                  <div>
                    <h4 style={{ marginBottom: '1rem', fontSize: '1rem', fontWeight: 600 }}>Push Bildirimleri</h4>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                      <label className="form-checkbox">
                        <input
                          type="checkbox"
                          name="push_enabled"
                          checked={notificationValues.push_enabled}
                          onChange={(e) => setNotificationFieldValue('push_enabled', e.target.checked)}
                        />
                        <span>Push bildirimlerini etkinleştir</span>
                      </label>
                      {notificationValues.push_enabled && (
                        <>
                          <label className="form-checkbox" style={{ marginLeft: '1.5rem' }}>
                            <input
                              type="checkbox"
                              name="push_leave_requests"
                              checked={notificationValues.push_leave_requests}
                              onChange={(e) => setNotificationFieldValue('push_leave_requests', e.target.checked)}
                            />
                            <span>İzin talepleri</span>
                          </label>
                          <label className="form-checkbox" style={{ marginLeft: '1.5rem' }}>
                            <input
                              type="checkbox"
                              name="push_approvals"
                              checked={notificationValues.push_approvals}
                              onChange={(e) => setNotificationFieldValue('push_approvals', e.target.checked)}
                            />
                            <span>Onay bildirimleri</span>
                          </label>
                          <label className="form-checkbox" style={{ marginLeft: '1.5rem' }}>
                            <input
                              type="checkbox"
                              name="push_reminders"
                              checked={notificationValues.push_reminders}
                              onChange={(e) => setNotificationFieldValue('push_reminders', e.target.checked)}
                            />
                            <span>Hatırlatmalar</span>
                          </label>
                        </>
                      )}
                    </div>
                  </div>
                </div>

                <div style={{ marginTop: '1.5rem' }}>
                  <button type="submit" className="btn btn-primary" disabled={settingsSaving}>
                    {settingsSaving ? (
                      <>
                        <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                        Kaydediliyor...
                      </>
                    ) : (
                      <>
                        <BsSave /> Kaydet
                      </>
                    )}
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}

      {activeTab === 'api-keys' && (
        <div className="card">
          <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <h3 className="card-title">API Anahtarları</h3>
            <button
              className="btn btn-primary btn-sm"
              onClick={() => {
                setSelectedApiKey(null);
                setNewApiKey(null);
                setApiKeyFormOpen(true);
              }}
            >
              <BsPlus /> Yeni API Anahtarı
            </button>
          </div>
          <div className="card-body">
            {apiKeysLoading ? (
              <div className="page-loading" style={{ minHeight: 200 }}>
                <div className="loading-spinner" />
              </div>
            ) : apiKeys.length === 0 ? (
              <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
                <BsKey size={48} style={{ marginBottom: '1rem', opacity: 0.5 }} />
                <p>Henüz API anahtarı oluşturulmamış</p>
                <button
                  className="btn btn-primary btn-sm"
                  onClick={() => {
                    setSelectedApiKey(null);
                    setNewApiKey(null);
                    setApiKeyFormOpen(true);
                  }}
                >
                  <BsPlus /> İlk API Anahtarını Oluştur
                </button>
              </div>
            ) : (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                {apiKeys.map((apiKey) => (
                  <div
                    key={apiKey.id}
                    style={{
                      padding: '1rem',
                      background: 'var(--surface-glass)',
                      border: '1px solid var(--border-primary)',
                      borderRadius: 'var(--radius-md)',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'space-between',
                    }}
                  >
                    <div style={{ flex: 1 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
                        <h4 style={{ margin: 0, fontSize: '0.9375rem', fontWeight: 600 }}>
                          {apiKey.name}
                        </h4>
                        <span className={`badge ${apiKey.is_active ? 'badge-success' : 'badge-secondary'}`}>
                          {apiKey.is_active ? 'Aktif' : 'Pasif'}
                        </span>
                        {apiKey.expires_at && new Date(apiKey.expires_at) < new Date() && (
                          <span className="badge badge-danger">Süresi Dolmuş</span>
                        )}
                      </div>
                      {apiKey.description && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>
                          {apiKey.description}
                        </div>
                      )}
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)' }}>
                        {apiKey.last_used_at ? `Son kullanım: ${new Date(apiKey.last_used_at).toLocaleString('tr-TR')}` : 'Henüz kullanılmadı'}
                      </div>
                    </div>
                    <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center', flexWrap: 'wrap' }}>
                      {showApiKey[apiKey.id] && apiKey.key && (
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', padding: '0.5rem', background: 'var(--surface-primary)', borderRadius: 'var(--radius-sm)', fontSize: '0.75rem', fontFamily: 'monospace', maxWidth: 300 }}>
                          <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{apiKey.key}</span>
                          <button
                            className="btn btn-ghost btn-icon btn-xs"
                            onClick={() => {
                              if (apiKey.key) {
                                navigator.clipboard.writeText(apiKey.key);
                                toast.success('API anahtarı kopyalandı');
                              }
                            }}
                            title="Kopyala"
                          >
                            <BsCopy size={12} />
                          </button>
                        </div>
                      )}
                      <button
                        className="btn btn-ghost btn-icon btn-sm"
                        onClick={() => {
                          setShowApiKey((prev) => ({ ...prev, [apiKey.id]: !prev[apiKey.id] }));
                        }}
                        title={showApiKey[apiKey.id] ? 'Gizle' : 'Göster'}
                      >
                        {showApiKey[apiKey.id] ? <BsEyeSlash /> : <BsEye />}
                      </button>
                      <button
                        className="btn btn-ghost btn-icon btn-sm"
                        onClick={() => {
                          setSelectedApiKey(apiKey);
                          setApiKeyFormOpen(true);
                        }}
                        title="Düzenle"
                      >
                        <BsPencil />
                      </button>
                      <button
                        className="btn btn-ghost btn-icon btn-sm"
                        onClick={async () => {
                          if (confirm(`"${apiKey.name}" API anahtarını silmek istediğinize emin misiniz?`)) {
                            try {
                              await apiKeysApi.delete(apiKey.id);
                              toast.success('API anahtarı silindi');
                              loadApiKeys();
                            } catch {
                              toast.error('API anahtarı silinemedi');
                            }
                          }
                        }}
                        title="Sil"
                        style={{ color: 'var(--danger)' }}
                      >
                        <BsTrash />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      {/* API Key Form Modal */}
      <Modal
        isOpen={apiKeyFormOpen}
        onClose={() => {
          setApiKeyFormOpen(false);
          setSelectedApiKey(null);
          setNewApiKey(null);
        }}
        title={selectedApiKey ? 'API Anahtarı Düzenle' : 'Yeni API Anahtarı'}
        size="md"
      >
        <ApiKeyForm
          apiKey={selectedApiKey}
          onSuccess={(newKey?: string) => {
            if (newKey) {
              setNewApiKey(newKey);
            }
            loadApiKeys();
            setApiKeyFormOpen(false);
            setSelectedApiKey(null);
          }}
          onClose={() => {
            setApiKeyFormOpen(false);
            setSelectedApiKey(null);
            setNewApiKey(null);
          }}
        />
      </Modal>

      {/* New API Key Display Modal */}
      {newApiKey && (
        <Modal
          isOpen={!!newApiKey}
          onClose={() => setNewApiKey(null)}
          title="API Anahtarı Oluşturuldu"
          size="md"
        >
          <div style={{ padding: '1rem' }}>
            <div style={{ marginBottom: '1rem', fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
              API anahtarınızı güvenli bir yere kaydedin. Bu anahtarı bir daha göremeyeceksiniz.
            </div>
            <div style={{ 
              padding: '1rem', 
              background: 'var(--surface-glass)', 
              borderRadius: 'var(--radius-md)',
              fontFamily: 'monospace',
              fontSize: '0.875rem',
              wordBreak: 'break-all',
              marginBottom: '1rem'
            }}>
              {newApiKey}
            </div>
            <button
              className="btn btn-primary"
              onClick={() => {
                navigator.clipboard.writeText(newApiKey);
                toast.success('API anahtarı kopyalandı');
              }}
            >
              <BsCopy /> Kopyala
            </button>
          </div>
        </Modal>
      )}

      {activeTab === 'license' && company && (
        <>
          <div className="card" style={{ marginBottom: '1.5rem' }}>
            <div className="card-header">
              <h3 className="card-title">Lisans Bilgileri</h3>
            </div>
            <div className="card-body">
              <div style={{ 
                display: 'grid', 
                gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 180px), 1fr))', 
                gap: '1.5rem' 
              }}>
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.5rem', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Paket</div>
                  <div style={{ fontWeight: 600, color: 'var(--text-primary)', fontSize: '1.125rem' }}>
                    {company.package_type || 'Standart'}
                  </div>
                </div>
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.5rem', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Durum</div>
                  <span className={`badge ${company.status === 'active' ? 'badge-success' : 'badge-warning'}`}>
                    {company.status === 'active' ? 'Aktif' : company.status}
                  </span>
                </div>
                {company.license_start_date && (
                  <div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.5rem', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Başlangıç</div>
                    <div style={{ fontWeight: 500, color: 'var(--text-primary)' }}>{company.license_start_date}</div>
                  </div>
                )}
                {company.license_end_date && (
                  <div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.5rem', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Bitiş</div>
                    <div style={{ fontWeight: 500, color: 'var(--text-primary)' }}>{company.license_end_date}</div>
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Limitler</h3>
            </div>
            <div className="card-body">
              <div style={{ display: 'grid', gap: '1.5rem' }}>
                {/* Users */}
                <div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem', alignItems: 'center' }}>
                    <span style={{ fontWeight: 500 }}>Kullanıcılar</span>
                    <span style={{ color: 'var(--text-secondary)', fontSize: '0.875rem' }}>
                      {company.current_users || 0} / {company.user_limit === 0 ? '∞' : company.user_limit || 0}
                    </span>
                  </div>
                  <div className="progress-bar">
                    <div
                      className="progress-fill"
                      data-warning={(company.user_limit ?? 0) > 0 && ((company.current_users || 0) / (company.user_limit ?? 0)) >= 0.8 && ((company.current_users || 0) / (company.user_limit ?? 0)) < 0.95}
                      data-danger={(company.user_limit ?? 0) > 0 && ((company.current_users || 0) / (company.user_limit ?? 0)) >= 0.95}
                      style={{
                        width: `${(company.user_limit ?? 0) > 0 ? Math.min(((company.current_users || 0) / (company.user_limit ?? 0)) * 100, 100) : 0}%`,
                      }}
                    />
                  </div>
                </div>

                {/* Locations */}
                <div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem', alignItems: 'center' }}>
                    <span style={{ fontWeight: 500 }}>Şubeler</span>
                    <span style={{ color: 'var(--text-secondary)', fontSize: '0.875rem' }}>
                      {company.current_locations || 0} / {company.location_limit === 0 ? '∞' : company.location_limit || 0}
                    </span>
                  </div>
                  <div className="progress-bar">
                    <div
                      className="progress-fill"
                      data-warning={(company.location_limit ?? 0) > 0 && ((company.current_locations || 0) / (company.location_limit ?? 0)) >= 0.8 && ((company.current_locations || 0) / (company.location_limit ?? 0)) < 0.95}
                      data-danger={(company.location_limit ?? 0) > 0 && ((company.current_locations || 0) / (company.location_limit ?? 0)) >= 0.95}
                      style={{
                        width: `${(company.location_limit ?? 0) > 0 ? Math.min(((company.current_locations || 0) / (company.location_limit ?? 0)) * 100, 100) : 0}%`,
                      }}
                    />
                  </div>
                </div>

                {/* Employees */}
                {company.employee_limit != null && (
                  <div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem', alignItems: 'center' }}>
                      <span style={{ fontWeight: 500 }}>Çalışanlar</span>
                      <span style={{ color: 'var(--text-secondary)', fontSize: '0.875rem' }}>
                        {company.employee_count || 0} / {company.employee_limit === 0 ? '∞' : company.employee_limit || 0}
                      </span>
                    </div>
                    <div className="progress-bar">
                      <div
                        className="progress-fill"
                        data-warning={company.employee_limit > 0 && (parseInt(String(company.employee_count || '0'), 10) / company.employee_limit) >= 0.8 && (parseInt(String(company.employee_count || '0'), 10) / company.employee_limit) < 0.95}
                        data-danger={company.employee_limit > 0 && (parseInt(String(company.employee_count || '0'), 10) / company.employee_limit) >= 0.95}
                        style={{
                          width: `${company.employee_limit > 0 ? Math.min((parseInt(String(company.employee_count || '0'), 10) / company.employee_limit) * 100, 100) : 0}%`,
                        }}
                      />
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
        </>
      )}

      {activeTab === 'integrations' && (
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Entegrasyon Ayarları</h3>
          </div>
          <div className="card-body">
            {settingsLoading ? (
              <div className="page-loading" style={{ minHeight: 200 }}>
                <div className="loading-spinner" />
              </div>
            ) : (
              <form onSubmit={handleIntegrationSubmit}>
                <div style={{ display: 'grid', gap: '1rem' }}>
                  <div className="form-group">
                    <label className="form-label">Webhook URL</label>
                    <input
                      type="url"
                      name="webhook_url"
                      className="form-control"
                      value={integrationValues.webhook_url}
                      onChange={handleIntegrationChange}
                      placeholder="https://example.com/webhook"
                    />
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginTop: '0.25rem' }}>
                      Sistem olayları için webhook URL'i
                    </div>
                  </div>

                  <div className="form-group">
                    <label className="form-label">API Key</label>
                    <input
                      type="text"
                      name="api_key"
                      className="form-control"
                      value={integrationValues.api_key}
                      onChange={handleIntegrationChange}
                      placeholder="API anahtarı"
                    />
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginTop: '0.25rem' }}>
                      Harici entegrasyonlar için API anahtarı
                    </div>
                  </div>
                </div>

                <div style={{ marginTop: '1.5rem' }}>
                  <button type="submit" className="btn btn-primary" disabled={settingsSaving}>
                    {settingsSaving ? (
                      <>
                        <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                        Kaydediliyor...
                      </>
                    ) : (
                      <>
                        <BsSave /> Kaydet
                      </>
                    )}
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}

      {activeTab === 'modules' && (
        <>
          {/* Subscription Info */}
          {company && (
            <div className="card mb-4">
              <div className="card-body">
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '2rem' }}>
                  <div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>Paket</div>
                    <div style={{ fontWeight: 600, color: 'var(--text-primary)' }}>
                      {company.package_type || 'Standart'}
                    </div>
                  </div>
                  <div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>Durum</div>
                    <span className={`badge ${company.status === 'active' ? 'badge-success' : 'badge-warning'}`}>
                      {company.status === 'active' ? 'Aktif' : company.status}
                    </span>
                  </div>
                  {company.license_end_date && (
                    <div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>Lisans Bitiş</div>
                      <div style={{ fontWeight: 500, color: 'var(--text-primary)' }}>{company.license_end_date}</div>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Modules List */}
          <div className="card">
            <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <h3 className="card-title">Aktif Modüller</h3>
              <button
                className="btn btn-ghost btn-icon btn-sm"
                onClick={() => {
                  loadCompany();
                  loadModules();
                }}
                title="Yenile"
                disabled={modulesLoading}
              >
                <BsArrowClockwise />
              </button>
            </div>
            <div className="card-body">
              {modulesLoading ? (
                <div className="page-loading" style={{ minHeight: 200 }}>
                  <div className="loading-spinner" />
                </div>
              ) : modules.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
                  Modül bilgisi bulunamadı
                </div>
              ) : (
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(min(100%, 250px), 1fr))', gap: '1rem' }}>
                  {modules.map((mod) => (
                    <div
                      key={mod.id}
                      style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        padding: '0.875rem 1rem',
                        background: mod.is_active ? 'var(--primary-soft)' : 'var(--surface-glass)',
                        border: `1px solid ${mod.is_active ? 'var(--primary)' : 'var(--border-primary)'}`,
                        borderRadius: 'var(--radius-md)',
                      }}
                    >
                      <div>
                        <div style={{
                          fontWeight: 500,
                          fontSize: '0.875rem',
                          color: mod.is_active ? 'var(--primary)' : 'var(--text-secondary)',
                        }}>
                          {mod.name}
                        </div>
                        {mod.description && (
                          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginTop: '0.125rem' }}>
                            {mod.description}
                          </div>
                        )}
                        {mod.is_core && (
                          <span className="badge badge-secondary" style={{ marginTop: '0.375rem' }}>
                            Temel Modül
                          </span>
                        )}
                      </div>
                      <div
                        style={{
                          width: 28,
                          height: 28,
                          borderRadius: '50%',
                          background: mod.is_active ? 'var(--success)' : 'var(--bg-tertiary)',
                          color: mod.is_active ? 'white' : 'var(--text-muted)',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          flexShrink: 0,
                        }}
                      >
                        {mod.is_active ? <BsCheck size={16} /> : <BsX size={16} />}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
};

export default SettingsPage;
