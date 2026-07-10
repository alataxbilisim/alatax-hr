import React, { useState, useEffect } from 'react';
import { IMaskInput } from 'react-imask';
import { branchesApi } from '@shared/services/api';
import { Branch, User } from '@shared/types/modules';
import toast from 'react-hot-toast';
import { BsX, BsSave } from 'react-icons/bs';
import { useFormValidation } from '@shared/hooks/useFormValidation';
import { required, email, maxLength } from '@shared/utils/validation';

interface BranchFormProps {
  branch?: Branch;
  users: User[];
  onClose: () => void;
  onSuccess: () => void;
}

const BranchForm: React.FC<BranchFormProps> = ({ branch, users, onClose, onSuccess }) => {
  const [saving, setSaving] = useState(false);
  const [isHeadquarters, setIsHeadquarters] = useState(branch?.is_headquarters || false);

  const {
    values,
    errors,
    touched,
    handleChange,
    handleBlur,
    setFieldValue,
    setValues,
    validate,
  } = useFormValidation({
    initialValues: {
      name: branch?.name || '',
      code: branch?.code || '',
      address: branch?.address || '',
      city: branch?.city || '',
      district: branch?.district || '',
      postal_code: branch?.postal_code || '',
      country: branch?.country || 'Türkiye',
      phone: branch?.phone || '',
      email: branch?.email || '',
      manager_id: branch?.manager_id ? String(branch.manager_id) : '',
      is_active: branch?.is_active ?? true,
      latitude: branch?.latitude != null ? String(branch.latitude) : '',
      longitude: branch?.longitude != null ? String(branch.longitude) : '',
    },
    schema: {
      name: [required()],
      email: [email()],
      postal_code: [maxLength(10)],
    },
  });

  useEffect(() => {
    if (branch) {
      setValues({
        name: branch.name || '',
        code: branch.code || '',
        address: branch.address || '',
        city: branch.city || '',
        district: branch.district || '',
        postal_code: branch.postal_code || '',
        country: branch.country || 'Türkiye',
        phone: branch.phone || '',
        email: branch.email || '',
        manager_id: branch.manager_id ? String(branch.manager_id) : '',
        is_active: branch.is_active ?? true,
        latitude: branch.latitude?.toString() || '',
        longitude: branch.longitude?.toString() || '',
      });
      setIsHeadquarters(branch.is_headquarters || false);
    }
  }, [branch, setValues]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validate()) {
      return;
    }

    setSaving(true);
    try {
      const data: Record<string, unknown> = {
        name: values.name,
        code: values.code || null,
        address: values.address || null,
        city: values.city || null,
        district: values.district || null,
        postal_code: values.postal_code || null,
        country: values.country || 'Türkiye',
        phone: values.phone || null,
        email: values.email || null,
        manager_id: values.manager_id || null,
        is_active: values.is_active,
        is_headquarters: isHeadquarters,
      };

      if (values.latitude) {
        data.latitude = parseFloat(values.latitude);
      }
      if (values.longitude) {
        data.longitude = parseFloat(values.longitude);
      }

      if (branch) {
        await branchesApi.update(branch.id, data);
        toast.success('Şube güncellendi');
      } else {
        await branchesApi.create(data);
        toast.success('Şube oluşturuldu');
      }

      onSuccess();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err?.response?.data?.message || 'İşlem başarısız');
    } finally {
      setSaving(false);
    }
  };

  // Otomatik şube kodu oluştur
  const generateBranchCode = () => {
    if (!values.name) return '';
    return values.name
      .toUpperCase()
      .replace(/[^A-Z0-9]/g, '')
      .substring(0, 6) + '-' + Date.now().toString().slice(-4);
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 900, maxHeight: '90vh', overflowY: 'auto' }}>
        <div className="modal-header">
          <h2>{branch ? 'Şube Düzenle' : 'Yeni Şube'}</h2>
          <button className="btn btn-ghost btn-icon" onClick={onClose}>
            <BsX />
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="modal-body">
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '1rem' }}>
              {/* Name */}
              <div className="form-group" style={{ gridColumn: '1 / -1' }}>
                <label className="form-label">
                  Şube Adı <span style={{ color: 'var(--danger)' }}>*</span>
                </label>
                <input
                  type="text"
                  name="name"
                  className={`form-control ${errors.name && touched.name ? 'error' : ''}`}
                  value={values.name}
                  onChange={handleChange}
                  onBlur={handleBlur}
                />
                {errors.name && touched.name && (
                  <div className="form-error">{errors.name}</div>
                )}
              </div>

              {/* Code */}
              <div className="form-group">
                <label className="form-label">Şube Kodu</label>
                <div style={{ display: 'flex', gap: '0.5rem' }}>
                  <input
                    type="text"
                    name="code"
                    className="form-control"
                    value={values.code}
                    onChange={handleChange}
                    placeholder="Otomatik oluşturulacak"
                  />
                  {!branch && (
                    <button
                      type="button"
                      className="btn btn-secondary"
                      onClick={() => setFieldValue('code', generateBranchCode())}
                      disabled={!values.name}
                      title="Otomatik kod oluştur"
                    >
                      Oluştur
                    </button>
                  )}
                </div>
              </div>

              {/* Country */}
              <div className="form-group">
                <label className="form-label">Ülke</label>
                <select
                  name="country"
                  className="form-control"
                  value={values.country}
                  onChange={handleChange}
                >
                  <option value="Türkiye">Türkiye</option>
                </select>
              </div>

              {/* City */}
              <div className="form-group">
                <label className="form-label">Şehir</label>
                <select
                  name="city"
                  className="form-control"
                  value={values.city}
                  onChange={handleChange}
                >
                  <option value="">Şehir Seçin</option>
                  <option value="Adana">Adana</option>
                  <option value="Adıyaman">Adıyaman</option>
                  <option value="Afyonkarahisar">Afyonkarahisar</option>
                  <option value="Ağrı">Ağrı</option>
                  <option value="Aksaray">Aksaray</option>
                  <option value="Amasya">Amasya</option>
                  <option value="Ankara">Ankara</option>
                  <option value="Antalya">Antalya</option>
                  <option value="Ardahan">Ardahan</option>
                  <option value="Artvin">Artvin</option>
                  <option value="Aydın">Aydın</option>
                  <option value="Balıkesir">Balıkesir</option>
                  <option value="Bartın">Bartın</option>
                  <option value="Batman">Batman</option>
                  <option value="Bayburt">Bayburt</option>
                  <option value="Bilecik">Bilecik</option>
                  <option value="Bingöl">Bingöl</option>
                  <option value="Bitlis">Bitlis</option>
                  <option value="Bolu">Bolu</option>
                  <option value="Burdur">Burdur</option>
                  <option value="Bursa">Bursa</option>
                  <option value="Çanakkale">Çanakkale</option>
                  <option value="Çankırı">Çankırı</option>
                  <option value="Çorum">Çorum</option>
                  <option value="Denizli">Denizli</option>
                  <option value="Diyarbakır">Diyarbakır</option>
                  <option value="Düzce">Düzce</option>
                  <option value="Edirne">Edirne</option>
                  <option value="Elazığ">Elazığ</option>
                  <option value="Erzincan">Erzincan</option>
                  <option value="Erzurum">Erzurum</option>
                  <option value="Eskişehir">Eskişehir</option>
                  <option value="Gaziantep">Gaziantep</option>
                  <option value="Giresun">Giresun</option>
                  <option value="Gümüşhane">Gümüşhane</option>
                  <option value="Hakkari">Hakkari</option>
                  <option value="Hatay">Hatay</option>
                  <option value="Iğdır">Iğdır</option>
                  <option value="Isparta">Isparta</option>
                  <option value="İstanbul">İstanbul</option>
                  <option value="İzmir">İzmir</option>
                  <option value="Kahramanmaraş">Kahramanmaraş</option>
                  <option value="Karabük">Karabük</option>
                  <option value="Karaman">Karaman</option>
                  <option value="Kars">Kars</option>
                  <option value="Kastamonu">Kastamonu</option>
                  <option value="Kayseri">Kayseri</option>
                  <option value="Kırıkkale">Kırıkkale</option>
                  <option value="Kırklareli">Kırklareli</option>
                  <option value="Kırşehir">Kırşehir</option>
                  <option value="Kilis">Kilis</option>
                  <option value="Kocaeli">Kocaeli</option>
                  <option value="Konya">Konya</option>
                  <option value="Kütahya">Kütahya</option>
                  <option value="Malatya">Malatya</option>
                  <option value="Manisa">Manisa</option>
                  <option value="Mardin">Mardin</option>
                  <option value="Mersin">Mersin</option>
                  <option value="Muğla">Muğla</option>
                  <option value="Muş">Muş</option>
                  <option value="Nevşehir">Nevşehir</option>
                  <option value="Niğde">Niğde</option>
                  <option value="Ordu">Ordu</option>
                  <option value="Osmaniye">Osmaniye</option>
                  <option value="Rize">Rize</option>
                  <option value="Sakarya">Sakarya</option>
                  <option value="Samsun">Samsun</option>
                  <option value="Siirt">Siirt</option>
                  <option value="Sinop">Sinop</option>
                  <option value="Sivas">Sivas</option>
                  <option value="Şanlıurfa">Şanlıurfa</option>
                  <option value="Şırnak">Şırnak</option>
                  <option value="Tekirdağ">Tekirdağ</option>
                  <option value="Tokat">Tokat</option>
                  <option value="Trabzon">Trabzon</option>
                  <option value="Tunceli">Tunceli</option>
                  <option value="Uşak">Uşak</option>
                  <option value="Van">Van</option>
                  <option value="Yalova">Yalova</option>
                  <option value="Yozgat">Yozgat</option>
                  <option value="Zonguldak">Zonguldak</option>
                </select>
              </div>

              {/* District */}
              <div className="form-group">
                <label className="form-label">İlçe</label>
                <input
                  type="text"
                  name="district"
                  className="form-control"
                  value={values.district}
                  onChange={handleChange}
                  placeholder="İlçe giriniz"
                />
              </div>

              {/* Postal Code */}
              <div className="form-group">
                <label className="form-label">Posta Kodu</label>
                <input
                  type="text"
                  name="postal_code"
                  className={`form-control ${errors.postal_code && touched.postal_code ? 'error' : ''}`}
                  value={values.postal_code}
                  onChange={handleChange}
                  onBlur={handleBlur}
                  maxLength={10}
                  placeholder="34000"
                />
                {errors.postal_code && touched.postal_code && (
                  <div className="form-error">{errors.postal_code}</div>
                )}
              </div>

              {/* Phone */}
              <div className="form-group">
                <label className="form-label">Telefon</label>
                <IMaskInput
                  mask="(000) 000 00 00"
                  value={values.phone || ''}
                  onAccept={(value: string) => {
                    setFieldValue('phone', value);
                  }}
                  onBlur={handleBlur}
                  className="form-control"
                  placeholder="(5xx) xxx xx xx"
                  name="phone"
                />
              </div>

              {/* Email */}
              <div className="form-group">
                <label className="form-label">E-posta</label>
                <input
                  type="email"
                  name="email"
                  className={`form-control ${errors.email && touched.email ? 'error' : ''}`}
                  value={values.email}
                  onChange={handleChange}
                  onBlur={handleBlur}
                  placeholder="ornek@firma.com"
                />
                {errors.email && touched.email && (
                  <div className="form-error">{errors.email}</div>
                )}
              </div>

              {/* Manager */}
              <div className="form-group">
                <label className="form-label">Şube Yöneticisi</label>
                <select
                  name="manager_id"
                  className="form-control"
                  value={values.manager_id}
                  onChange={handleChange}
                >
                  <option value="">Yönetici seçiniz</option>
                  {users.map((user) => (
                    <option key={user.id} value={user.id}>
                      {user.name} {user.email ? `(${user.email})` : ''}
                    </option>
                  ))}
                </select>
              </div>

              {/* Active Status */}
              <div className="form-group">
                <label className="form-checkbox" style={{ marginTop: '1.75rem' }}>
                  <input
                    type="checkbox"
                    name="is_active"
                    checked={values.is_active}
                    onChange={handleChange}
                  />
                  <span>Aktif</span>
                </label>
              </div>

              {/* Address */}
              <div className="form-group" style={{ gridColumn: '1 / -1' }}>
                <label className="form-label">Adres</label>
                <textarea
                  name="address"
                  className="form-control"
                  value={values.address}
                  onChange={handleChange}
                  rows={2}
                  placeholder="Tam adres giriniz"
                />
              </div>

              {/* Coordinates */}
              <div className="form-group">
                <label className="form-label">Enlem (Latitude)</label>
                <input
                  type="number"
                  step="any"
                  name="latitude"
                  className="form-control"
                  value={values.latitude}
                  onChange={handleChange}
                  placeholder="41.0082"
                />
              </div>

              <div className="form-group">
                <label className="form-label">Boylam (Longitude)</label>
                <input
                  type="number"
                  step="any"
                  name="longitude"
                  className="form-control"
                  value={values.longitude}
                  onChange={handleChange}
                  placeholder="28.9784"
                />
              </div>

              {/* Headquarters */}
              {!branch?.is_headquarters && (
                <div className="form-group">
                  <label className="form-checkbox" style={{ marginTop: '1.75rem' }}>
                    <input
                      type="checkbox"
                      checked={isHeadquarters}
                      onChange={(e) => setIsHeadquarters(e.target.checked)}
                    />
                    <span>Merkez Şube</span>
                  </label>
                </div>
              )}
            </div>
          </div>

          <div className="modal-footer">
            <button type="button" className="btn btn-ghost" onClick={onClose} disabled={saving}>
              İptal
            </button>
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
  );
};

export default BranchForm;

