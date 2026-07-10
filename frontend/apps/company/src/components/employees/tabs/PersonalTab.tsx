import React from 'react';
import { BsPersonBadge, BsHeart, BsPhone } from 'react-icons/bs';

interface Employee {
  birth_date?: string;
  national_id?: string;
  gender?: string;
  marital_status?: string;
  blood_type?: string;
  education_level?: string;
  address?: string;
  city?: string;
  district?: string;
  postal_code?: string;
  emergency_contact_name?: string;
  emergency_contact_phone?: string;
  emergency_contact_relation?: string;
}

interface PersonalTabProps {
  employee: Employee;
}

const PersonalTab: React.FC<PersonalTabProps> = ({ employee }) => {
  const formatDate = (date?: string) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('tr-TR', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  };

  const getGenderLabel = (gender?: string) => {
    const map: Record<string, string> = {
      male: 'Erkek',
      female: 'Kadın',
      other: 'Diğer',
    };
    return gender ? map[gender] || gender : '-';
  };

  const getMaritalStatusLabel = (status?: string) => {
    const map: Record<string, string> = {
      single: 'Bekar',
      married: 'Evli',
      divorced: 'Boşanmış',
      widowed: 'Dul',
    };
    return status ? map[status] || status : '-';
  };

  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '1.5rem' }}>
      {/* Kişisel Bilgiler */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title"><BsPersonBadge style={{ marginRight: '0.5rem' }} />Kişisel Bilgiler</h3>
        </div>
        <div className="card-body">
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Doğum Tarihi</span>
              <span>{formatDate(employee.birth_date)}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>TC Kimlik No</span>
              <span>{employee.national_id ? '***' + employee.national_id.slice(-4) : '-'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Cinsiyet</span>
              <span>{getGenderLabel(employee.gender)}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Medeni Durum</span>
              <span>{getMaritalStatusLabel(employee.marital_status)}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Kan Grubu</span>
              <span>{employee.blood_type || '-'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Eğitim Seviyesi</span>
              <span>{employee.education_level || '-'}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Adres */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title"><BsHeart style={{ marginRight: '0.5rem' }} />Adres Bilgileri</h3>
        </div>
        <div className="card-body">
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Adres</span>
              <span style={{ textAlign: 'right', maxWidth: '60%' }}>{employee.address || '-'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>İl</span>
              <span>{employee.city || '-'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>İlçe</span>
              <span>{employee.district || '-'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Posta Kodu</span>
              <span>{employee.postal_code || '-'}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Acil Durum */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title"><BsPhone style={{ marginRight: '0.5rem' }} />Acil Durum İletişim</h3>
        </div>
        <div className="card-body">
          {employee.emergency_contact_name ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-tertiary)' }}>Ad Soyad</span>
                <span>{employee.emergency_contact_name}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-tertiary)' }}>Telefon</span>
                <span>{employee.emergency_contact_phone || '-'}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-tertiary)' }}>Yakınlık</span>
                <span>{employee.emergency_contact_relation || '-'}</span>
              </div>
            </div>
          ) : (
            <p style={{ color: 'var(--text-tertiary)', textAlign: 'center', padding: '1rem' }}>
              Acil durum kişisi tanımlanmamış
            </p>
          )}
        </div>
      </div>
    </div>
  );
};

export default PersonalTab;

