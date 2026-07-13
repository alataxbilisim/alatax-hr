import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { employeesApi, customFieldsApi, lookupsApi, branchesApi, positionsApi, type LookupItem, type PositionCatalogItem } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import { CustomFieldRenderer, CustomFieldDefinition, Select } from '@shared/components';
import type { CustomFieldValue } from '@shared/types/modules';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import { BsSave, BsX, BsPersonBadge, BsBuilding, BsBriefcase, BsCurrencyDollar, BsShieldCheck, BsGear } from 'react-icons/bs';

interface Department {
  id: number;
  name: string;
  parent_id?: number;
}

interface BranchOption {
  id: number;
  name: string;
  code?: string | null;
}

interface Manager {
  id: number;
  employee_code: string;
  position?: string;
  title?: string;
  user?: {
    id: number;
    name: string;
  };
}

interface EmployeeFormData {
  employee_code: string;
  name: string;
  department_id?: number;
  branch_id?: number;
  title?: string;
  position?: string;
  manager_id?: number;
  birth_date?: string;
  national_id?: string;
  gender?: string;
  marital_status?: string;
  blood_type?: string;
  education_level?: string;
  personal_email?: string;
  personal_phone?: string;
  address?: string;
  city?: string;
  district?: string;
  postal_code?: string;
  emergency_contact_name?: string;
  emergency_contact_phone?: string;
  emergency_contact_relation?: string;
  hire_date?: string;
  contract_start_date?: string;
  contract_end_date?: string;
  contract_type?: string;
  work_type?: string;
  gross_salary?: number;
  net_salary?: number;
  currency?: string;
  bank_name?: string;
  iban?: string;
  sgk_number?: string;
  sgk_start_date?: string;
  status?: string;
  notes?: string;
  custom_fields?: Record<string, CustomFieldValue>;
  create_portal_access?: boolean;
  portal_email?: string;
  portal_access_mode?: 'invite' | 'set_password';
  portal_password?: string;
}

const EmployeeForm: React.FC = () => {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const isEdit = !!id;

  const [activeTab, setActiveTab] = useState('general');
  const [loading, setLoading] = useState(false);
  const [customFields, setCustomFields] = useState<CustomFieldDefinition[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [branches, setBranches] = useState<BranchOption[]>([]);
  const [managers, setManagers] = useState<Manager[]>([]);
  const [positions, setPositions] = useState<PositionCatalogItem[]>([]);
  const [statusOptions, setStatusOptions] = useState<LookupItem[]>([]);
  const [workTypeOptions, setWorkTypeOptions] = useState<LookupItem[]>([]);
  const [genderOptions, setGenderOptions] = useState<LookupItem[]>([]);
  const [maritalOptions, setMaritalOptions] = useState<LookupItem[]>([]);
  const [bloodOptions, setBloodOptions] = useState<LookupItem[]>([]);
  const [educationOptions, setEducationOptions] = useState<LookupItem[]>([]);
  const [relationOptions, setRelationOptions] = useState<LookupItem[]>([]);
  const [contractOptions, setContractOptions] = useState<LookupItem[]>([]);
  const [currencyOptions, setCurrencyOptions] = useState<LookupItem[]>([]);
  
  const [formData, setFormData] = useState<EmployeeFormData>({
    employee_code: '',
    name: '',
    currency: 'TRY',
    status: 'active',
    custom_fields: {},
    create_portal_access: false,
    portal_access_mode: 'invite',
  });

  const loadDepartments = useCallback(async () => {
    try {
      const response = await employeesApi.getDepartments();
      setDepartments(response.data.data);
    } catch (error) {
      console.error('Departmanlar yüklenemedi:', error);
    }
  }, []);

  const loadBranches = useCallback(async () => {
    try {
      const response = await branchesApi.list({ per_page: 100, is_active: true });
      const raw = response.data.data;
      const list = Array.isArray(raw) ? raw : raw?.data ?? [];
      setBranches(
        (list as BranchOption[]).map((b) => ({
          id: b.id,
          name: b.name,
          code: b.code,
        }))
      );
    } catch (error) {
      console.error('Şubeler yüklenemedi:', error);
    }
  }, []);

  const loadManagers = useCallback(async () => {
    try {
      const response = await employeesApi.getManagers();
      setManagers(response.data.data);
    } catch (error) {
      console.error('Yöneticiler yüklenemedi:', error);
    }
  }, []);

  const loadPositions = useCallback(async () => {
    try {
      const response = await positionsApi.getAll({ active_only: true, per_page: 100 });
      setPositions(response.data.data || []);
    } catch (error) {
      console.error('Pozisyonlar yüklenemedi:', error);
    }
  }, []);

  const loadCustomFields = useCallback(async () => {
    try {
      const response = await customFieldsApi.getAll('employee');
      setCustomFields(response.data.data);
    } catch (error) {
      console.error('Custom fields yüklenemedi:', error);
    }
  }, []);

  const loadLookups = useCallback(async () => {
    try {
      const types = [
        'employee_status',
        'work_type',
        'gender',
        'marital_status',
        'blood_type',
        'education_level',
        'emergency_relation',
        'contract_type',
        'currency',
      ] as const;
      const results = await Promise.all(types.map((t) => lookupsApi.forType(t)));
      setStatusOptions(results[0].data.data ?? []);
      setWorkTypeOptions(results[1].data.data ?? []);
      setGenderOptions(results[2].data.data ?? []);
      setMaritalOptions(results[3].data.data ?? []);
      setBloodOptions(results[4].data.data ?? []);
      setEducationOptions(results[5].data.data ?? []);
      setRelationOptions(results[6].data.data ?? []);
      setContractOptions(results[7].data.data ?? []);
      setCurrencyOptions(results[8].data.data ?? []);
    } catch (error) {
      console.error('Lookup listeleri yüklenemedi:', error);
      toast.error('Seçenek listeleri yüklenemedi');
    }
  }, []);

  const loadEmployee = useCallback(async () => {
    try {
      setLoading(true);
      const response = await employeesApi.getById(Number(id));
      const data = response.data.data;
      const employee = data.employee || data; // Yeni API yapısını destekle
      
      setFormData({
        ...employee,
        name: employee.user?.name || '',
        portal_email: employee.user?.email || '',
        create_portal_access: !!employee.user_id,
      });
    } catch {
      toast.error('Personel yüklenemedi');
      navigate('/employees');
    } finally {
      setLoading(false);
    }
  }, [id, navigate]);

  useEffect(() => {
    loadDepartments();
    loadBranches();
    loadManagers();
    loadPositions();
    loadCustomFields();
    loadLookups();
    if (id) {
      loadEmployee();
    }
  }, [id, loadDepartments, loadBranches, loadManagers, loadPositions, loadCustomFields, loadLookups, loadEmployee]);

  

  

  

  

  const handleChange = (field: string, value: string | number | boolean | undefined) => {
    setFormData(prev => ({ ...prev, [field]: value }));
  };

  const handleCustomFieldChange = (key: string, value: CustomFieldValue) => {
    setFormData(prev => ({
      ...prev,
      custom_fields: {
        ...prev.custom_fields,
        [key]: value,
      },
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    try {
      setLoading(true);
      
      if (isEdit) {
        await employeesApi.update(Number(id), formData);
        toast.success('Personel başarıyla güncellendi');
      } else {
        await employeesApi.create(formData);
        toast.success('Personel başarıyla oluşturuldu');
      }
      
      navigate('/employees');
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'İşlem başarısız oldu'));
    } finally {
      setLoading(false);
    }
  };

  const tabs = [
    { id: 'general', label: 'Genel', icon: <BsPersonBadge /> },
    { id: 'personal', label: 'Kişisel', icon: <BsBuilding /> },
    { id: 'contact', label: 'İletişim', icon: <BsBuilding /> },
    { id: 'work', label: 'İş Bilgileri', icon: <BsBriefcase /> },
    { id: 'salary', label: 'Maaş', icon: <BsCurrencyDollar /> },
    { id: 'sgk', label: 'SGK', icon: <BsShieldCheck /> },
    { id: 'custom', label: 'Özel Alanlar', icon: <BsGear /> },
  ];

  if (loading && isEdit) {
    return (
      <div className="animate-fade-in form-page">
        <div className="card">
          <div className="card-body text-center py-5">
            <div className="spinner-border" role="status">
              <span className="visually-hidden">Yükleniyor...</span>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="animate-fade-in form-page">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">{isEdit ? 'Personel Düzenle' : 'Yeni Personel'}</h1>
        </div>
      </div>

      <form onSubmit={handleSubmit}>
        <div className="card">
          <div className="tabs" style={{ marginBottom: 0, padding: '0 var(--card-padding)' }}>
            {tabs.map(tab => (
              <button
                key={tab.id}
                type="button"
                className={`tab ${activeTab === tab.id ? 'active' : ''}`}
                onClick={() => setActiveTab(tab.id)}
              >
                {tab.icon}
                {tab.label}
              </button>
            ))}
          </div>

          <div className="card-body">
            {/* Genel Bilgiler */}
            {activeTab === 'general' && (
              <div className="form-grid form-grid-2">
                <div className="form-group">
                  <label className="form-label">Sicil No <span style={{ color: 'var(--danger)' }}>*</span></label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.employee_code}
                    onChange={(e) => handleChange('employee_code', e.target.value)}
                    required
                    placeholder="PRS001"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Ad Soyad <span style={{ color: 'var(--danger)' }}>*</span></label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.name}
                    onChange={(e) => handleChange('name', e.target.value)}
                    required
                    placeholder="Ahmet Yılmaz"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">{t('form.branch')}</label>
                  <Select
                    value={formData.branch_id ? String(formData.branch_id) : ''}
                    onChange={(v) => handleChange('branch_id', v ? Number(v) : undefined)}
                    options={branches.map((b) => ({
                      value: String(b.id),
                      label: b.code ? `${b.name} (${b.code})` : b.name,
                    }))}
                    allowEmpty
                    placeholder={t('form.selectPlaceholder')}
                    aria-label={t('form.branch')}
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Departman</label>
                  <Select
                    value={formData.department_id ? String(formData.department_id) : ''}
                    onChange={(v) => handleChange('department_id', v ? Number(v) : undefined)}
                    options={departments.map((dept) => ({
                      value: String(dept.id),
                      label: dept.name,
                    }))}
                    allowEmpty
                    placeholder="Seçiniz..."
                    aria-label="Departman"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Yönetici</label>
                  <Select
                    value={formData.manager_id ? String(formData.manager_id) : ''}
                    onChange={(v) => handleChange('manager_id', v ? Number(v) : undefined)}
                    options={managers
                      .filter((m) => m.id !== Number(id))
                      .map((manager) => ({
                        value: String(manager.id),
                        label: `${manager.user?.name || manager.employee_code}${manager.position ? ` - ${manager.position}` : ''}`,
                      }))}
                    allowEmpty
                    placeholder="Seçiniz..."
                    aria-label="Yönetici"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">{t('positions.formLabel')}</label>
                  <Select
                    value={formData.position || ''}
                    onChange={(v) => handleChange('position', v || undefined)}
                    options={(() => {
                      const catalog = positions.map((p) => ({
                        value: p.name,
                        label: p.sgk_occupation_code
                          ? `${p.code} — ${p.name} (${p.sgk_occupation_code})`
                          : `${p.code} — ${p.name}`,
                      }));
                      const current = formData.position?.trim();
                      if (current && !catalog.some((o) => o.value === current)) {
                        catalog.unshift({
                          value: current,
                          label: t('positions.formLegacyOption', { name: current }),
                        });
                      }
                      return catalog;
                    })()}
                    allowEmpty
                    placeholder={t('positions.formPlaceholder')}
                    aria-label={t('positions.formLabel')}
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Ünvan</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.title || ''}
                    onChange={(e) => handleChange('title', e.target.value)}
                    placeholder="Kıdemli Mühendis"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Durum</label>
                  <Select
                    value={formData.status || ''}
                    onChange={(v) => handleChange('status', v)}
                    options={statusOptions.map((opt) => ({
                      value: opt.value,
                      label: opt.label,
                      color: opt.color,
                    }))}
                    placeholder="Seçiniz..."
                    aria-label="Durum"
                  />
                </div>

                {/* Portal Erişimi */}
                {!isEdit && (
                  <div className="form-group" style={{ gridColumn: '1 / -1' }}>
                    <div
                      style={{
                        padding: '1rem',
                        background: 'var(--bg-tertiary)',
                        borderRadius: 'var(--radius-md)',
                        border: '1px solid var(--border-color)',
                      }}
                    >
                      <div className="form-check" style={{ marginBottom: formData.create_portal_access ? '1rem' : 0 }}>
                        <input
                          type="checkbox"
                          className="form-check-input"
                          id="create_portal_access"
                          checked={formData.create_portal_access}
                          onChange={(e) => handleChange('create_portal_access', e.target.checked)}
                        />
                        <label className="form-check-label" htmlFor="create_portal_access">
                          <strong>Portal erişimi ver</strong>
                          <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>
                            Personel için kullanıcı hesabı oluşturulacak ve davet emaili gönderilecek
                          </div>
                        </label>
                      </div>

                      {formData.create_portal_access && (
                        <>
                          <div className="form-group" style={{ marginBottom: '1rem' }}>
                            <label className="form-label">Portal Giriş Email <span style={{ color: 'var(--danger)' }}>*</span></label>
                            <input
                              type="email"
                              className="form-control"
                              value={formData.portal_email || ''}
                              onChange={(e) => handleChange('portal_email', e.target.value)}
                              required={formData.create_portal_access}
                              placeholder="personel@sirket.com"
                            />
                          </div>
                          <div className="form-group" style={{ marginBottom: formData.portal_access_mode === 'set_password' ? '1rem' : 0 }}>
                            <label className="form-label">Erişim yöntemi</label>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                              <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
                                <input
                                  type="radio"
                                  name="portal_access_mode"
                                  checked={(formData.portal_access_mode || 'invite') === 'invite'}
                                  onChange={() => handleChange('portal_access_mode', 'invite')}
                                />
                                <span>Davet e-postası gönder (kişi kendi şifresini belirler)</span>
                              </label>
                              <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
                                <input
                                  type="radio"
                                  name="portal_access_mode"
                                  checked={formData.portal_access_mode === 'set_password'}
                                  onChange={() => handleChange('portal_access_mode', 'set_password')}
                                />
                                <span>Şifreyi şimdi belirle (ilk girişte değiştirme zorunlu)</span>
                              </label>
                            </div>
                          </div>
                          {formData.portal_access_mode === 'set_password' && (
                            <div className="form-group" style={{ marginBottom: 0 }}>
                              <label className="form-label">Portal şifresi <span style={{ color: 'var(--danger)' }}>*</span></label>
                              <input
                                type="password"
                                className="form-control"
                                value={formData.portal_password || ''}
                                onChange={(e) => handleChange('portal_password', e.target.value)}
                                required={formData.portal_access_mode === 'set_password'}
                                autoComplete="new-password"
                                placeholder="En az 8 karakter"
                              />
                            </div>
                          )}
                        </>
                      )}
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* Kişisel Bilgiler */}
            {activeTab === 'personal' && (
              <div className="form-grid form-grid-2">
                <div className="form-group">
                  <label className="form-label">Doğum Tarihi</label>
                  <input
                    type="date"
                    className="form-control"
                    value={formData.birth_date || ''}
                    onChange={(e) => handleChange('birth_date', e.target.value)}
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">TC Kimlik No</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.national_id || ''}
                    onChange={(e) => handleChange('national_id', e.target.value)}
                    maxLength={11}
                    placeholder="12345678901"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Cinsiyet</label>
                  <Select
                    value={formData.gender || ''}
                    onChange={(v) => handleChange('gender', v)}
                    options={genderOptions.map((o) => ({ value: o.value, label: o.label, color: o.color }))}
                    allowEmpty
                    aria-label="Cinsiyet"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Medeni Durum</label>
                  <Select
                    value={formData.marital_status || ''}
                    onChange={(v) => handleChange('marital_status', v)}
                    options={maritalOptions.map((o) => ({ value: o.value, label: o.label, color: o.color }))}
                    allowEmpty
                    aria-label="Medeni Durum"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Kan Grubu</label>
                  <Select
                    value={formData.blood_type || ''}
                    onChange={(v) => handleChange('blood_type', v)}
                    options={bloodOptions.map((o) => ({ value: o.value, label: o.label, color: o.color }))}
                    allowEmpty
                    aria-label="Kan Grubu"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Eğitim Seviyesi</label>
                  <Select
                    value={formData.education_level || ''}
                    onChange={(v) => handleChange('education_level', v)}
                    options={educationOptions.map((o) => ({ value: o.value, label: o.label, color: o.color }))}
                    allowEmpty
                    aria-label="Eğitim Seviyesi"
                  />
                </div>
              </div>
            )}

            {/* İletişim */}
            {activeTab === 'contact' && (
              <div className="form-grid form-grid-2">
                <div className="form-group">
                  <label className="form-label">Kişisel Email</label>
                  <input
                    type="email"
                    className="form-control"
                    value={formData.personal_email || ''}
                    onChange={(e) => handleChange('personal_email', e.target.value)}
                    placeholder="kisisel@email.com"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Kişisel Telefon</label>
                  <input
                    type="tel"
                    className="form-control"
                    value={formData.personal_phone || ''}
                    onChange={(e) => handleChange('personal_phone', e.target.value)}
                    placeholder="05XX XXX XX XX"
                  />
                </div>

                <div className="form-group" style={{ gridColumn: '1 / -1' }}>
                  <label className="form-label">Adres</label>
                  <textarea
                    className="form-control"
                    value={formData.address || ''}
                    onChange={(e) => handleChange('address', e.target.value)}
                    rows={3}
                    placeholder="Mahalle, Sokak, Bina No, Daire No..."
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">İl</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.city || ''}
                    onChange={(e) => handleChange('city', e.target.value)}
                    placeholder="İstanbul"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">İlçe</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.district || ''}
                    onChange={(e) => handleChange('district', e.target.value)}
                    placeholder="Kadıköy"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Posta Kodu</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.postal_code || ''}
                    onChange={(e) => handleChange('postal_code', e.target.value)}
                    placeholder="34000"
                  />
                </div>

                <div style={{ gridColumn: '1 / -1', borderTop: '1px solid var(--border-color)', paddingTop: '1rem', marginTop: '0.5rem' }}>
                  <h4 style={{ fontSize: '1rem', marginBottom: '1rem' }}>Acil Durum İletişim</h4>
                  <div className="form-grid form-grid-2">
                    <div className="form-group">
                      <label className="form-label">Acil Durum Kişisi</label>
                      <input
                        type="text"
                        className="form-control"
                        value={formData.emergency_contact_name || ''}
                        onChange={(e) => handleChange('emergency_contact_name', e.target.value)}
                        placeholder="Ad Soyad"
                      />
                    </div>

                    <div className="form-group">
                      <label className="form-label">Acil Durum Telefon</label>
                      <input
                        type="tel"
                        className="form-control"
                        value={formData.emergency_contact_phone || ''}
                        onChange={(e) => handleChange('emergency_contact_phone', e.target.value)}
                        placeholder="05XX XXX XX XX"
                      />
                    </div>

                    <div className="form-group">
                      <label className="form-label">Yakınlık Derecesi</label>
                      <Select
                        value={formData.emergency_contact_relation || ''}
                        onChange={(v) => handleChange('emergency_contact_relation', v)}
                        options={relationOptions.map((o) => ({ value: o.value, label: o.label, color: o.color }))}
                        allowEmpty
                        aria-label="Yakınlık Derecesi"
                      />
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* İş Bilgileri */}
            {activeTab === 'work' && (
              <div className="form-grid form-grid-2">
                <div className="form-group">
                  <label className="form-label">İşe Giriş Tarihi</label>
                  <input
                    type="date"
                    className="form-control"
                    value={formData.hire_date || ''}
                    onChange={(e) => handleChange('hire_date', e.target.value)}
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Sözleşme Başlangıç</label>
                  <input
                    type="date"
                    className="form-control"
                    value={formData.contract_start_date || ''}
                    onChange={(e) => handleChange('contract_start_date', e.target.value)}
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Sözleşme Bitiş</label>
                  <input
                    type="date"
                    className="form-control"
                    value={formData.contract_end_date || ''}
                    onChange={(e) => handleChange('contract_end_date', e.target.value)}
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Sözleşme Tipi</label>
                  <Select
                    value={formData.contract_type || ''}
                    onChange={(v) => handleChange('contract_type', v)}
                    options={contractOptions.map((o) => ({ value: o.value, label: o.label, color: o.color }))}
                    allowEmpty
                    aria-label="Sözleşme Tipi"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Çalışma Tipi</label>
                  <Select
                    value={formData.work_type || ''}
                    onChange={(v) => handleChange('work_type', v)}
                    options={workTypeOptions.map((opt) => ({
                      value: opt.value,
                      label: opt.label,
                      color: opt.color,
                    }))}
                    placeholder="Seçiniz..."
                    aria-label="Çalışma Tipi"
                  />
                </div>
              </div>
            )}

            {/* Maaş Bilgileri */}
            {activeTab === 'salary' && (
              <div className="form-grid form-grid-2">
                <div className="form-group">
                  <label className="form-label">Brüt Maaş</label>
                  <input
                    type="number"
                    className="form-control"
                    value={formData.gross_salary || ''}
                    onChange={(e) => handleChange('gross_salary', e.target.value ? Number(e.target.value) : undefined)}
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Net Maaş</label>
                  <input
                    type="number"
                    className="form-control"
                    value={formData.net_salary || ''}
                    onChange={(e) => handleChange('net_salary', e.target.value ? Number(e.target.value) : undefined)}
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Para Birimi</label>
                  <Select
                    value={formData.currency || 'TRY'}
                    onChange={(v) => handleChange('currency', v)}
                    options={currencyOptions.map((o) => ({ value: o.value, label: o.label, color: o.color }))}
                    aria-label="Para Birimi"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Banka Adı</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.bank_name || ''}
                    onChange={(e) => handleChange('bank_name', e.target.value)}
                    placeholder="Garanti Bankası"
                  />
                </div>

                <div className="form-group" style={{ gridColumn: '1 / -1' }}>
                  <label className="form-label">IBAN</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.iban || ''}
                    onChange={(e) => handleChange('iban', e.target.value.toUpperCase())}
                    maxLength={34}
                    placeholder="TR00 0000 0000 0000 0000 0000 00"
                  />
                </div>
              </div>
            )}

            {/* SGK Bilgileri */}
            {activeTab === 'sgk' && (
              <div className="form-grid form-grid-2">
                <div className="form-group">
                  <label className="form-label">SGK Numarası</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.sgk_number || ''}
                    onChange={(e) => handleChange('sgk_number', e.target.value)}
                    placeholder="SGK sicil numarası"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">SGK Başlangıç Tarihi</label>
                  <input
                    type="date"
                    className="form-control"
                    value={formData.sgk_start_date || ''}
                    onChange={(e) => handleChange('sgk_start_date', e.target.value)}
                  />
                </div>

                <div className="form-group" style={{ gridColumn: '1 / -1' }}>
                  <label className="form-label">Notlar</label>
                  <textarea
                    className="form-control"
                    value={formData.notes || ''}
                    onChange={(e) => handleChange('notes', e.target.value)}
                    rows={4}
                    placeholder="Personel ile ilgili özel notlar..."
                  />
                </div>
              </div>
            )}

            {/* Özel Alanlar */}
            {activeTab === 'custom' && (
              <div>
                {customFields.length === 0 ? (
                  <div style={{ textAlign: 'center', padding: '3rem', color: 'var(--text-tertiary)' }}>
                    <BsGear size={48} style={{ marginBottom: '1rem', opacity: 0.5 }} />
                    <p>Henüz özel alan tanımlanmamış</p>
                    <p style={{ fontSize: '0.875rem' }}>
                      Ayarlar &gt; Özel Alanlar sayfasından personeller için özel alanlar oluşturabilirsiniz.
                    </p>
                  </div>
                ) : (
                  <div className="form-grid form-grid-2">
                    <CustomFieldRenderer
                      fields={customFields}
                      values={formData.custom_fields || {}}
                      onChange={handleCustomFieldChange}
                    />
                  </div>
                )}
              </div>
            )}
          </div>

          <div className="form-actions-sticky">
            <button
              type="button"
              className="btn btn-secondary"
              onClick={() => navigate('/employees')}
              disabled={loading}
            >
              <BsX /> İptal
            </button>
            <button
              type="submit"
              className="btn btn-primary"
              disabled={loading}
            >
              {loading ? (
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
        </div>
      </form>
    </div>
  );
};

export default EmployeeForm;
