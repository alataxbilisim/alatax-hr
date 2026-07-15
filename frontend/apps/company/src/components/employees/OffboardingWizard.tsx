import React, { useEffect, useState } from 'react';
import { Modal } from '../ui';
import { Select } from '@shared/components';
import { useTranslation } from '@shared/i18n';
import {
  employeesApi,
  lookupsApi,
  onboardingApi,
  type LookupItem,
} from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';

interface TemplatePreview {
  id: number;
  name: string;
  tasks: Array<{ title: string; type: string; is_required: boolean }>;
  estimated_days?: number;
}

interface OffboardingWizardProps {
  isOpen: boolean;
  onClose: () => void;
  employeeId: number;
  onStarted: (processId: number) => void;
}

const OffboardingWizard: React.FC<OffboardingWizardProps> = ({
  isOpen,
  onClose,
  employeeId,
  onStarted,
}) => {
  const { t } = useTranslation('common');
  const [step, setStep] = useState<1 | 2>(1);
  const [loading, setLoading] = useState(false);
  const [reasons, setReasons] = useState<LookupItem[]>([]);
  const [templates, setTemplates] = useState<TemplatePreview[]>([]);
  const [reasonCode, setReasonCode] = useState('');
  const [terminationDate, setTerminationDate] = useState(
    () => new Date().toISOString().slice(0, 10)
  );
  const [exitNotes, setExitNotes] = useState('');
  const [templateId, setTemplateId] = useState<number | null>(null);

  useEffect(() => {
    if (!isOpen) return;
    setStep(1);
    setReasonCode('');
    setExitNotes('');
    setTerminationDate(new Date().toISOString().slice(0, 10));
    setTemplateId(null);

    void lookupsApi
      .forType('termination_reason')
      .then((res) => setReasons(res.data.data ?? []))
      .catch(() => setReasons([]));

    void onboardingApi.templates
      .list({ process_type: 'offboarding', per_page: 50 })
      .then((res) => {
        const rows = (res.data.data?.data ?? res.data.data ?? []) as TemplatePreview[];
        setTemplates(Array.isArray(rows) ? rows : []);
        const def = rows.find((r) => r.id);
        if (def) setTemplateId(def.id);
      })
      .catch(() => setTemplates([]));
  }, [isOpen]);

  const selectedTemplate = templates.find((tpl) => tpl.id === templateId) ?? null;

  const reasonOptions = reasons.map((r) => ({
    value: r.value,
    label: r.label,
  }));

  const templateOptions = templates.map((tpl) => ({
    value: String(tpl.id),
    label: tpl.name,
  }));

  const handleStart = async () => {
    if (!reasonCode || !terminationDate) {
      toast.error(t('offboarding.selectReason'));
      return;
    }
    try {
      setLoading(true);
      const payload: {
        termination_reason_code: string;
        termination_date: string;
        exit_notes?: string;
        template_id?: number;
      } = {
        termination_reason_code: reasonCode,
        termination_date: terminationDate,
      };
      if (exitNotes.trim() !== '') {
        payload.exit_notes = exitNotes.trim();
      }
      if (templateId !== null) {
        payload.template_id = templateId;
      }
      const res = await employeesApi.startOffboarding(employeeId, payload);
      const processId = res.data.data?.id as number;
      toast.success(t('offboarding.started'));
      onStarted(processId);
      onClose();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('offboarding.startError')));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={t('offboarding.wizardTitle')}
      size="md"
    >
      <p style={{ color: 'var(--text-secondary)', marginBottom: 'var(--sp-3)', fontSize: '0.875rem' }}>
        {t('offboarding.employeeStillActive')}
      </p>

      {step === 1 ? (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--sp-3)' }}>
          <h3 style={{ margin: 0, fontSize: '1rem' }}>{t('offboarding.wizardStep1')}</h3>
          <div className="form-group">
            <label className="form-label">{t('offboarding.reasonCode')} *</label>
            <Select
              value={reasonCode}
              onChange={setReasonCode}
              options={reasonOptions}
              placeholder={t('offboarding.selectReason')}
              aria-label={t('offboarding.reasonCode')}
            />
          </div>
          <div className="form-group">
            <label className="form-label">{t('offboarding.terminationDate')} *</label>
            <input
              type="date"
              className="form-input"
              value={terminationDate}
              onChange={(e) => setTerminationDate(e.target.value)}
              required
            />
          </div>
          <div className="form-group">
            <label className="form-label">{t('offboarding.exitNotes')}</label>
            <textarea
              className="form-input"
              rows={3}
              value={exitNotes}
              onChange={(e) => setExitNotes(e.target.value)}
            />
          </div>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 'var(--sp-2)' }}>
            <button type="button" className="btn btn-ghost" onClick={onClose}>
              {t('cancel')}
            </button>
            <button
              type="button"
              className="btn btn-primary"
              disabled={!reasonCode || !terminationDate}
              onClick={() => setStep(2)}
            >
              {t('offboarding.next')}
            </button>
          </div>
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--sp-3)' }}>
          <h3 style={{ margin: 0, fontSize: '1rem' }}>{t('offboarding.wizardStep2')}</h3>
          <div className="form-group">
            <label className="form-label">{t('offboarding.selectTemplate')}</label>
            <Select
              value={templateId !== null ? String(templateId) : ''}
              onChange={(v) => setTemplateId(v ? Number(v) : null)}
              options={templateOptions}
              placeholder={t('offboarding.selectTemplate')}
              aria-label={t('offboarding.selectTemplate')}
            />
          </div>
          {selectedTemplate && (
            <div>
              <div style={{ fontWeight: 500, marginBottom: 'var(--sp-2)' }}>
                {t('offboarding.previewTasks')}
              </div>
              <ul style={{ margin: 0, paddingLeft: '1.25rem', color: 'var(--text-secondary)' }}>
                {selectedTemplate.tasks.map((task, idx) => (
                  <li key={`${task.title}-${idx}`}>{task.title}</li>
                ))}
              </ul>
            </div>
          )}
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 'var(--sp-2)' }}>
            <button type="button" className="btn btn-ghost" onClick={() => setStep(1)}>
              {t('offboarding.back')}
            </button>
            <button
              type="button"
              className="btn btn-primary"
              disabled={loading}
              onClick={() => void handleStart()}
            >
              {loading ? t('loading') : t('offboarding.startConfirm')}
            </button>
          </div>
        </div>
      )}
    </Modal>
  );
};

export default OffboardingWizard;
