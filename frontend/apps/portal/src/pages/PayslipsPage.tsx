import React, { useEffect, useState } from 'react';
import { portalApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsCurrencyDollar, BsDownload, BsEye } from 'react-icons/bs';

interface Payslip {
  id: number;
  period: string;
  period_label: string;
  year: number;
  month: number;
  net_salary: number;
  is_viewed: boolean;
  published_at: string;
  has_file: boolean;
}

const PayslipsPage: React.FC = () => {
  const [payslips, setPayslips] = useState<Payslip[]>([]);
  const [loading, setLoading] = useState(true);
  const [, setSelectedPayslip] = useState<Payslip | null>(null);

  useEffect(() => {
    loadPayslips();
  }, []);

  const loadPayslips = async () => {
    try {
      const response = await portalApi.payslips.list();
      setPayslips(response.data.data.data || []);
    } catch {
      toast.error('Bordrolar yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const handleDownload = async (payslip: Payslip) => {
    try {
      const response = await portalApi.payslips.download(payslip.id);
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `Bordro_${payslip.period}.pdf`);
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch {
      toast.error('Bordro indirilemedi');
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('tr-TR', {
      style: 'currency',
      currency: 'TRY',
    }).format(amount);
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Bordrolarım</h1>
          <p className="page-subtitle">Aylık maaş bordrolarınızı görüntüleyin</p>
        </div>
      </div>

      <div className="card">
        <div className="card-body">
          {loading ? (
            <div className="page-loading">
              <div className="loading-spinner"></div>
            </div>
          ) : payslips.length > 0 ? (
            <div className="row">
              {payslips.map((payslip) => (
                <div key={payslip.id} className="col-md-6 col-lg-4 mb-3">
                  <div className="payslip-card">
                    <div>
                      <div className="payslip-period">{payslip.period_label}</div>
                      <div className="payslip-amount">{formatCurrency(payslip.net_salary)}</div>
                      <div className={`payslip-status ${payslip.is_viewed ? 'viewed' : 'new'}`}>
                        {payslip.is_viewed ? 'Görüntülendi' : '✨ Yeni'}
                      </div>
                    </div>
                    <div className="btn-group">
                      <button
                        className="btn btn-sm btn-ghost"
                        title="Görüntüle"
                        onClick={() => setSelectedPayslip(payslip)}
                      >
                        <BsEye />
                      </button>
                      {payslip.has_file && (
                        <button
                          className="btn btn-sm btn-ghost"
                          title="İndir"
                          onClick={() => handleDownload(payslip)}
                        >
                          <BsDownload />
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="empty-state">
              <BsCurrencyDollar size={64} className="text-muted mb-3" />
              <h3>Henüz bordro yok</h3>
              <p>Yayınlanan bordrolar burada görünecektir</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default PayslipsPage;

