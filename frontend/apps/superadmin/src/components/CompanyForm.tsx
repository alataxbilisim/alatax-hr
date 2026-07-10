import React, { useState, useEffect } from 'react';
import Modal from './Modal';
import { adminApi } from '@shared/services/api';
import toast from 'react-hot-toast';

interface Company {
  id?: number;
  name: string;
  slug?: string;
  email: string;
  phone: string;
  address?: string;
  city?: string;
  country?: string;
  tax_number?: string;
  tax_office?: string;
  status: string;
  license_package_id?: number | null;
  license_start_date?: string | null;
  license_end_date?: string | null;
  admin_name?: string;
  admin_email?: string;
  admin_password?: string;
}

interface FormData {
  name: string;
  email: string;
  phone: string;
  address: string;
  city: string;
  country: string;
  tax_number: string;
  tax_office: string;
  status: string;
  license_package_id: number | undefined;
  license_start_date: string;
  license_end_date: string;
  admin_name: string;
  admin_email: string;
  admin_password: string;
}

interface LicensePackage {
  id: number;
  name: string;
}

interface CompanyFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  company?: Company | null;
}

// ISO tarih formatını yyyy-MM-dd formatına dönüştür
const formatDateForInput = (dateString: string | null | undefined): string => {
  if (!dateString) return '';
  try {
    // Zaten yyyy-MM-dd formatındaysa direkt döndür
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
      return dateString;
    }
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '';
    return date.toISOString().split('T')[0];
  } catch {
    return '';
  }
};

const getInitialFormData = (): FormData => ({
  name: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  country: 'Türkiye',
  tax_number: '',
  tax_office: '',
  status: 'trial',
  license_package_id: undefined,
  license_start_date: new Date().toISOString().split('T')[0],
  license_end_date: '',
  admin_name: '',
  admin_email: '',
  admin_password: '',
});

const CompanyForm: React.FC<CompanyFormProps> = ({
  isOpen,
  onClose,
  onSuccess,
  company,
}) => {
  const [loading, setLoading] = useState(false);
  const [packages, setPackages] = useState<LicensePackage[]>([]);
  const [formData, setFormData] = useState<FormData>(getInitialFormData());

  useEffect(() => {
    if (isOpen) {
      loadPackages();
      if (company) {
        setFormData({
          name: company.name ?? '',
          email: company.email ?? '',
          phone: company.phone ?? '',
          address: company.address ?? '',
          city: company.city ?? '',
          country: company.country ?? 'Türkiye',
          tax_number: company.tax_number ?? '',
          tax_office: company.tax_office ?? '',
          status: company.status ?? 'trial',
          license_package_id: company.license_package_id ?? undefined,
          license_start_date: formatDateForInput(company.license_start_date),
          license_end_date: formatDateForInput(company.license_end_date),
          admin_name: company.admin_name ?? '',
          admin_email: company.admin_email ?? '',
          admin_password: '',
        });
      } else {
        setFormData(getInitialFormData());
      }
    }
  }, [isOpen, company]);

  const loadPackages = async () => {
    try {
      const response = await adminApi.licensePackages.list();
      setPackages(response.data.data || []);
    } catch (error) {
      console.error('Paketler yüklenemedi:', error);
      toast.error('Paketler yüklenemedi');
    }
  };

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    const { name, value } = e.target;
    
    if (name === 'license_package_id') {
      const newValue = value === '' ? undefined : Number(value);
      setFormData((prev) => ({ 
        ...prev, 
        license_package_id: newValue
      }));
    } else {
      setFormData((prev) => ({ ...prev, [name]: value }));
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      // Gönderilecek veriyi hazırla
      const submitData: Record<string, unknown> = {
        name: formData.name,
        email: formData.email,
        status: formData.status,
      };

      // Opsiyonel alanları sadece dolu ise ekle
      if (formData.phone) submitData.phone = formData.phone;
      if (formData.address) submitData.address = formData.address;
      if (formData.city) submitData.city = formData.city;
      if (formData.country) submitData.country = formData.country;
      if (formData.tax_number) submitData.tax_number = formData.tax_number;
      if (formData.tax_office) submitData.tax_office = formData.tax_office;
      if (formData.license_start_date) submitData.license_start_date = formData.license_start_date;
      if (formData.license_end_date) submitData.license_end_date = formData.license_end_date;
      
      // License package ID - her zaman gönder (undefined ise null olarak gönder)
      // Böylece backend paketi kaldırabilir veya güncelleyebilir
      submitData.license_package_id = formData.license_package_id !== undefined 
        ? formData.license_package_id 
        : null;

      // Yeni firma oluştururken admin bilgilerini ekle
      if (!company?.id) {
        if (formData.admin_name) submitData.admin_name = formData.admin_name;
        if (formData.admin_email) submitData.admin_email = formData.admin_email;
        if (formData.admin_password) submitData.admin_password = formData.admin_password;
      }

      if (company?.id) {
        await adminApi.companies.update(company.id, submitData);
        toast.success('Firma başarıyla güncellendi');
      } else {
        await adminApi.companies.create(submitData);
        toast.success('Firma başarıyla oluşturuldu');
      }
      onSuccess();
      onClose();
    } catch (error: unknown) {
      console.error('Firma kaydetme hatası:', error);
      let errorMessage = 'İşlem sırasında bir hata oluştu';
      
      const err = error as { response?: { data?: { errors?: Record<string, string[]>; message?: string } }; message?: string };
      if (err?.response?.data) {
        if (err.response.data.errors) {
          const errors = Object.values(err.response.data.errors).flat();
          errorMessage = errors.join(', ') || err.response.data.message || errorMessage;
        } else {
          errorMessage = err.response.data.message || errorMessage;
        }
      } else if (err?.message) {
        errorMessage = err.message;
      }
      
      toast.error(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={company ? 'Firma Düzenle' : 'Yeni Firma'}
      size="lg"
      footer={
        <>
          <button
            type="button"
            className="btn btn-secondary"
            onClick={onClose}
            disabled={loading}
          >
            İptal
          </button>
          <button
            type="submit"
            form="company-form"
            className="btn btn-primary"
            disabled={loading}
          >
            {loading ? 'Kaydediliyor...' : company ? 'Güncelle' : 'Oluştur'}
          </button>
        </>
      }
    >
      <form id="company-form" onSubmit={handleSubmit}>
        <div className="row">
          {/* Firma Bilgileri */}
          <div className="col-md-6">
            <h4 className="mb-3" style={{ fontSize: '1rem', fontWeight: 600 }}>
              Firma Bilgileri
            </h4>

            <div className="form-group">
              <label className="form-label">Firma Adı *</label>
              <input
                type="text"
                className="form-control"
                name="name"
                value={formData.name}
                onChange={handleChange}
                required
                placeholder="Örn: ABC Teknoloji A.Ş."
              />
            </div>

            <div className="form-group">
              <label className="form-label">E-posta *</label>
              <input
                type="email"
                className="form-control"
                name="email"
                value={formData.email}
                onChange={handleChange}
                required
                placeholder="info@firma.com"
              />
            </div>

            <div className="form-group">
              <label className="form-label">Telefon</label>
              <input
                type="tel"
                className="form-control"
                name="phone"
                value={formData.phone}
                onChange={handleChange}
                placeholder="+90 555 123 4567"
              />
            </div>

            <div className="form-group">
              <label className="form-label">Adres</label>
              <textarea
                className="form-control"
                name="address"
                value={formData.address}
                onChange={handleChange}
                rows={2}
                placeholder="Firma adresi"
              />
            </div>

            <div className="row">
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Şehir</label>
                  <input
                    type="text"
                    className="form-control"
                    name="city"
                    value={formData.city}
                    onChange={handleChange}
                    placeholder="İstanbul"
                  />
                </div>
              </div>
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Ülke</label>
                  <input
                    type="text"
                    className="form-control"
                    name="country"
                    value={formData.country}
                    onChange={handleChange}
                    placeholder="Türkiye"
                  />
                </div>
              </div>
            </div>

            <div className="row">
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Vergi No</label>
                  <input
                    type="text"
                    className="form-control"
                    name="tax_number"
                    value={formData.tax_number}
                    onChange={handleChange}
                    placeholder="1234567890"
                  />
                </div>
              </div>
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Vergi Dairesi</label>
                  <input
                    type="text"
                    className="form-control"
                    name="tax_office"
                    value={formData.tax_office}
                    onChange={handleChange}
                    placeholder="Kadıköy V.D."
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Lisans ve Admin Bilgileri */}
          <div className="col-md-6">
            <h4 className="mb-3" style={{ fontSize: '1rem', fontWeight: 600 }}>
              Lisans Bilgileri
            </h4>

            <div className="form-group">
              <label className="form-label">Durum</label>
              <select
                className="form-select"
                name="status"
                value={formData.status}
                onChange={handleChange}
              >
                <option value="trial">Deneme</option>
                <option value="active">Aktif</option>
                <option value="suspended">Askıda</option>
                <option value="cancelled">İptal Edildi</option>
              </select>
            </div>

            <div className="form-group">
              <label className="form-label">Lisans Paketi</label>
              <select
                className="form-select"
                name="license_package_id"
                value={formData.license_package_id !== undefined ? String(formData.license_package_id) : ''}
                onChange={handleChange}
              >
                <option value="">Paket Seçin</option>
                {packages.map((pkg) => (
                  <option key={pkg.id} value={pkg.id}>
                    {pkg.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="row">
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Lisans Başlangıç</label>
                  <input
                    type="date"
                    className="form-control"
                    name="license_start_date"
                    value={formData.license_start_date}
                    onChange={handleChange}
                  />
                </div>
              </div>
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Lisans Bitiş</label>
                  <input
                    type="date"
                    className="form-control"
                    name="license_end_date"
                    value={formData.license_end_date}
                    onChange={handleChange}
                  />
                </div>
              </div>
            </div>

            {!company && (
              <>
                <hr className="my-3" />
                <h4 className="mb-3" style={{ fontSize: '1rem', fontWeight: 600 }}>
                  Admin Kullanıcı
                </h4>

                <div className="form-group">
                  <label className="form-label">Admin Adı *</label>
                  <input
                    type="text"
                    className="form-control"
                    name="admin_name"
                    value={formData.admin_name}
                    onChange={handleChange}
                    required={!company}
                    placeholder="Ahmet Yılmaz"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Admin E-posta *</label>
                  <input
                    type="email"
                    className="form-control"
                    name="admin_email"
                    value={formData.admin_email}
                    onChange={handleChange}
                    required={!company}
                    placeholder="admin@firma.com"
                  />
                </div>

                <div className="form-group">
                  <label className="form-label">Admin Şifre *</label>
                  <input
                    type="password"
                    className="form-control"
                    name="admin_password"
                    value={formData.admin_password}
                    onChange={handleChange}
                    required={!company}
                    placeholder="••••••••"
                    minLength={8}
                  />
                  <small className="form-text">En az 8 karakter</small>
                </div>
              </>
            )}
          </div>
        </div>
      </form>
    </Modal>
  );
};

export default CompanyForm;
