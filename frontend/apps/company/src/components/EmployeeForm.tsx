import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { employeesApi, customFieldsApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import { CustomFieldRenderer, CustomFieldDefinition } from '@shared/components';
import type { CustomFieldValue } from '@shared/types/modules';
import toast from 'react-hot-toast';
import { BsSave, BsX, BsPersonBadge, BsBuilding, BsBriefcase, BsCurrencyDollar, BsShieldCheck, BsGear } from 'react-icons/bs';

interface Department {
  id: number;
  name: string;
  parent_id?: number;
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
}

const EmployeeForm: React.FC = () => {
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const isEdit = !!id;

  const [activeTab, setActiveTab] = useState('general');
  const [loading, setLoading] = useState(false);
  const [customFields, setCustomFields] = useState<CustomFieldDefinition[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [managers, setManagers] = useState<Manager[]>([]);
  
  const [formData, setFormData] = useState<EmployeeFormData>({
    employee_code: '',
    name: '',
    currency: 'TRY',
    status: 'active',
    custom_fields: {},
    create_portal_access: false,
  });

  const loadDepartments = useCallback(async () => {
    try {
      const response = await employeesApi.getDepartments();
      setDepartments(response.data.data);
    } catch (error) {
      console.error('Departmanlar yüklenemedi:', error);
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

  const loadCustomFields = useCallback(async () => {
    try {
      const response = await customFieldsApi.getAll('employee');
      setCustomFields(response.data.data);
    } catch (error) {
      console.error('Custom fields yüklenemedi:', error);
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
    loadManagers();
    loadCustomFields();
    if (id) {
      loadEmployee();
    }
  }, [id, loadDepartments, loadManagers, loadCustomFields, loadEmployee]);

  

  

  

  

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
                  <label className="form-label">Departman</label>
                  <select
                    className="form-select"
                    value={formData.department_id || ''}
                    onChange={(e) => handleChange('department_id', e.target.value ? Number(e.target.value) : undefined)}
                  >
                    <option value="">Seçiniz...</option>
                    {departments.map((dept) => (
                      <option key={dept.id} value={dept.id}>
                        {dept.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="form-group">
                  <label className="form-label">Yönetici</label>
                  <select
                    className="form-select"
                    value={formData.manager_id || ''}
                    onChange={(e) => handleChange('manager_id', e.target.value ? Number(e.target.value) : undefined)}
                  >
                    <option value="">Seçiniz...</option>
                    {managers
                      .filter(m => m.id !== Number(id)) // Kendini seçemesin
                      .map((manager) => (
                        <option key={manager.id} value={manager.id}>
                          {manager.user?.name || manager.employee_code} 
                          {manager.position ? ` - ${manager.position}` : ''}
                        </option>
                      ))}
                  </select>
                </div>

                <div className="form-group">
                  <label className="form-label">Pozisyon</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.position || ''}
                    onChange={(e) => handleChange('position', e.target.value)}
                    placeholder="Yazılım Geliştirici"
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
                  <select
                    className="form-select"
                    value={formData.status}
                    onChange={(e) => handleChange('status', e.target.value)}
                  >
                    <option value="active">Aktif</option>
                    <option value="on_leave">İzinli</option>
                    <option value="suspended">Askıda</option>
                    <option value="terminated">İşten Çıkmış</option>
                  </select>
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
                        <div className="form-group" style={{ marginBottom: 0 }}>
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
                  <select
                    className="form-select"
                    value={formData.gender || ''}
                    onChange={(e) => handleChange('gender', e.target.value)}
                  >
                    <option value="">Seçiniz...</option>
                    <option value="male">Erkek</option>
                    <option value="female">Kadın</option>
                    <option value="other">Diğer</option>
                  </select>
                </div>

                <div className="form-group">
                  <label className="form-label">Medeni Durum</label>
                  <select
                    className="form-select"
                    value={formData.marital_status || ''}
                    onChange={(e) => handleChange('marital_status', e.target.value)}
                  >
                    <option value="">Seçiniz...</option>
                    <option value="single">Bekar</option>
                    <option value="married">Evli</option>
                    <option value="divorced">Boşanmış</option>
                    <option value="widowed">Dul</option>
                  </select>
                </div>

                <div className="form-group">
                  <label className="form-label">Kan Grubu</label>
                  <select
                    className="form-select"
                    value={formData.blood_type || ''}
                    onChange={(e) => handleChange('blood_type', e.target.value)}
                  >
                    <option value="">Seçiniz...</option>
                    <option value="A Rh+">A Rh+</option>
                    <option value="A Rh-">A Rh-</option>
                    <option value="B Rh+">B Rh+</option>
                    <option value="B Rh-">B Rh-</option>
                    <option value="AB Rh+">AB Rh+</option>
                    <option value="AB Rh-">AB Rh-</option>
                    <option value="0 Rh+">0 Rh+</option>
                    <option value="0 Rh-">0 Rh-</option>
                  </select>
                </div>

                <div className="form-group">
                  <label className="form-label">Eğitim Seviyesi</label>
                  <select
                    className="form-select"
                    value={formData.education_level || ''}
                    onChange={(e) => handleChange('education_level', e.target.value)}
                  >
                    <option value="">Seçiniz...</option>
                    <option value="İlkokul">İlkokul</option>
                    <option value="Ortaokul">Ortaokul</option>
                    <option value="Lise">Lise</option>
                    <option value="Önlisans">Önlisans</option>
                    <option value="Lisans">Lisans</option>
                    <option value="Yüksek Lisans">Yüksek Lisans</option>
                    <option value="Doktora">Doktora</option>
                  </select>
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
                      <select
                        className="form-select"
                        value={formData.emergency_contact_relation || ''}
                        onChange={(e) => handleChange('emergency_contact_relation', e.target.value)}
                      >
                        <option value="">Seçiniz...</option>
                        <option value="Eş">Eş</option>
                        <option value="Anne">Anne</option>
                        <option value="Baba">Baba</option>
                        <option value="Kardeş">Kardeş</option>
                        <option value="Çocuk">Çocuk</option>
                        <option value="Arkadaş">Arkadaş</option>
                        <option value="Diğer">Diğer</option>
                      </select>
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
                  <select
                    className="form-select"
                    value={formData.contract_type || ''}
                    onChange={(e) => handleChange('contract_type', e.target.value)}
                  >
                    <option value="">Seçiniz...</option>
                    <option value="permanent">Süresiz (Belirsiz Süreli)</option>
                    <option value="temporary">Süreli (Belirli Süreli)</option>
                    <option value="intern">Stajyer</option>
                    <option value="contract">Sözleşmeli</option>
                  </select>
                </div>

                <div className="form-group">
                  <label className="form-label">Çalışma Tipi</label>
                  <select
                    className="form-select"
                    value={formData.work_type || ''}
                    onChange={(e) => handleChange('work_type', e.target.value)}
                  >
                    <option value="">Seçiniz...</option>
                    <option value="full_time">Tam Zamanlı</option>
                    <option value="part_time">Yarı Zamanlı</option>
                    <option value="remote">Uzaktan</option>
                    <option value="hybrid">Hibrit</option>
                  </select>
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
                  <select
                    className="form-select"
                    value={formData.currency}
                    onChange={(e) => handleChange('currency', e.target.value)}
                  >
                    <option value="TRY">TRY - Türk Lirası</option>
                    <option value="USD">USD - Amerikan Doları</option>
                    <option value="EUR">EUR - Euro</option>
                    <option value="GBP">GBP - İngiliz Sterlini</option>
                  </select>
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
