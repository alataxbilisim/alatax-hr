import React from 'react';
import { BsBriefcase, BsCurrencyDollar, BsShieldCheck } from 'react-icons/bs';

interface Employee {
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
  notes?: string;
}

interface WorkTabProps {
  employee: Employee;
}

const WorkTab: React.FC<WorkTabProps> = ({ employee }) => {
  const formatDate = (date?: string) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('tr-TR', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  };

  const formatCurrency = (amount?: number, currency?: string) => {
    if (!amount) return '-';
    return new Intl.NumberFormat('tr-TR', {
      style: 'currency',
      currency: currency || 'TRY',
    }).format(amount);
  };

  const getContractTypeLabel = (type?: string) => {
    const map: Record<string, string> = {
      permanent: 'Süresiz',
      temporary: 'Süreli',
      intern: 'Stajyer',
      contract: 'Sözleşmeli',
    };
    return type ? map[type] || type : '-';
  };

  const getWorkTypeLabel = (type?: string) => {
    const map: Record<string, string> = {
      full_time: 'Tam Zamanlı',
      part_time: 'Yarı Zamanlı',
      remote: 'Uzaktan',
      hybrid: 'Hibrit',
    };
    return type ? map[type] || type : '-';
  };

  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 280px), 1fr))', gap: 'var(--card-gap)' }}>
      {/* Sözleşme Bilgileri */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title"><BsBriefcase style={{ marginRight: 'var(--sp-2)' }} />Sözleşme Bilgileri</h3>
        </div>
        <div className="card-body">
          <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--sp-2)', fontSize: 'var(--fs-body)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--sp-2)' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>İşe Giriş Tarihi</span>
              <span>{formatDate(employee.hire_date)}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--sp-2)' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Sözleşme Başlangıç</span>
              <span>{formatDate(employee.contract_start_date)}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--sp-2)' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Sözleşme Bitiş</span>
              <span>{formatDate(employee.contract_end_date)}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Sözleşme Tipi</span>
              <span className="badge badge-info">{getContractTypeLabel(employee.contract_type)}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Çalışma Tipi</span>
              <span>{getWorkTypeLabel(employee.work_type)}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Maaş Bilgileri — alanlar/API yetkisi değiştirilmedi (Faz 2) */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title"><BsCurrencyDollar style={{ marginRight: 'var(--sp-2)' }} />Maaş Bilgileri</h3>
        </div>
        <div className="card-body">
          <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--sp-2)', fontSize: 'var(--fs-body)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--sp-2)' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Brüt Maaş</span>
              <span style={{ fontWeight: 600 }}>{formatCurrency(employee.gross_salary, employee.currency)}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--sp-2)' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Net Maaş</span>
              <span style={{ fontWeight: 600 }}>{formatCurrency(employee.net_salary, employee.currency)}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--sp-2)' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Banka</span>
              <span>{employee.bank_name || '-'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--sp-2)' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>IBAN</span>
              <span style={{ fontFamily: 'monospace', fontSize: 'var(--fs-caption)' }}>
                {employee.iban ? `***${employee.iban.slice(-4)}` : '-'}
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* SGK Bilgileri */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title"><BsShieldCheck style={{ marginRight: 'var(--sp-2)' }} />SGK Bilgileri</h3>
        </div>
        <div className="card-body">
          <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--sp-2)', fontSize: 'var(--fs-body)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--sp-2)' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>SGK Numarası</span>
              <span>{employee.sgk_number ? `***${employee.sgk_number.slice(-4)}` : '-'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--sp-2)' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>SGK Başlangıç</span>
              <span>{formatDate(employee.sgk_start_date)}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Notlar */}
      {employee.notes && (
        <div className="card" style={{ gridColumn: '1 / -1' }}>
          <div className="card-header">
            <h3 className="card-title">Notlar</h3>
          </div>
          <div className="card-body">
            <p style={{ color: 'var(--text-secondary)', margin: 0, whiteSpace: 'pre-wrap' }}>
              {employee.notes}
            </p>
          </div>
        </div>
      )}
    </div>
  );
};

export default WorkTab;

