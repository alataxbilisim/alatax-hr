import React, { useEffect, useState, useCallback, useMemo } from 'react';
import { onboardingApi, lookupsApi, type LookupItem } from '@shared/services/api';
import toast from 'react-hot-toast';
import { DataTable, ConfirmDialog, EmptyState } from '../../components/ui';
import TemplateForm from '../../components/onboarding/TemplateForm';
import ProcessForm from '../../components/onboarding/ProcessForm';
import { useLocation, useNavigate } from 'react-router-dom';
import {
  BsPlus,
  BsPersonCheck,
  BsFileText,
  BsPencil,
  BsTrash,
  BsEye,
  BsCopy,
} from 'react-icons/bs';

interface Template {
  id: number;
  name: string;
  description?: string;
  tasks: Array<{
    title: string;
    description: string;
    type: string;
    is_required: boolean;
    days_offset: number;
  }>;
  estimated_days: number;
  is_active: boolean;
  is_default: boolean;
  processes_count?: number;
}

interface Process {
  id: number;
  title: string;
  user: { id: number; name: string };
  template?: { id: number; name: string };
  assigned_to?: { id: number; name: string };
  status: string;
  progress: number;
  start_date: string;
  target_end_date?: string;
  tasks_count?: number;
  created_at: string;
}

type TabType = 'processes' | 'templates';

const statusBadgeClass: Record<string, string> = {
  pending: 'badge-warning',
  in_progress: 'badge-info',
  completed: 'badge-success',
  cancelled: 'badge-danger',
};

const OnboardingPage: React.FC = () => {
  const navigate = useNavigate();
  const location = useLocation();

  const activeTab: TabType = useMemo(() => {
    if (location.pathname.includes('/onboarding/templates')) return 'templates';
    return 'processes';
  }, [location.pathname]);

  const handleTabChange = (tab: TabType) => {
    navigate(tab === 'templates' ? '/onboarding/templates' : '/onboarding');
  };

  const [processes, setProcesses] = useState<Process[]>([]);
  const [processesLoading, setProcessesLoading] = useState(true);
  const [processPage, setProcessPage] = useState(1);
  const [processTotalPages, setProcessTotalPages] = useState(1);
  const [processFormOpen, setProcessFormOpen] = useState(false);

  const [templates, setTemplates] = useState<Template[]>([]);
  const [templatesLoading, setTemplatesLoading] = useState(true);
  const [templatePage, setTemplatePage] = useState(1);
  const [templateTotalPages, setTemplateTotalPages] = useState(1);
  const [templateFormOpen, setTemplateFormOpen] = useState(false);
  const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null);

  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [itemToDelete, setItemToDelete] = useState<{ type: 'template' | 'process'; item: Template | Process } | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const [processStatusOptions, setProcessStatusOptions] = useState<LookupItem[]>([]);

  const loadLookups = useCallback(async () => {
    try {
      const statusRes = await lookupsApi.forType('onboarding_process_status');
      setProcessStatusOptions(statusRes.data.data ?? []);
    } catch {
      console.error('Onboarding lookup listeleri yüklenemedi');
    }
  }, []);

  const processStatusLabel = (value: string) =>
    processStatusOptions.find((o) => o.value === value)?.label || value;

  const loadProcesses = useCallback(async () => {
    try {
      setProcessesLoading(true);
      const response = await onboardingApi.processes.list({ page: processPage });
      const data = response.data.data;
      setProcesses(data.data || []);
      setProcessTotalPages(data.last_page || 1);
    } catch {
      toast.error('Süreçler yüklenemedi');
    } finally {
      setProcessesLoading(false);
    }
  }, [processPage]);

  const loadTemplates = useCallback(async () => {
    try {
      setTemplatesLoading(true);
      const response = await onboardingApi.templates.list({ page: templatePage });
      const data = response.data.data;
      setTemplates(data.data || []);
      setTemplateTotalPages(data.last_page || 1);
    } catch {
      toast.error('Şablonlar yüklenemedi');
    } finally {
      setTemplatesLoading(false);
    }
  }, [templatePage]);

  useEffect(() => {
    void loadLookups();
  }, [loadLookups]);

  useEffect(() => {
    if (activeTab === 'processes') {
      loadProcesses();
    } else {
      loadTemplates();
    }
  }, [activeTab, loadProcesses, loadTemplates]);

  const handleTemplateSubmit = async (data: Omit<Template, 'id'>) => {
    if (selectedTemplate) {
      await onboardingApi.templates.update(selectedTemplate.id, data);
      toast.success('Şablon güncellendi');
    } else {
      await onboardingApi.templates.create(data);
      toast.success('Şablon oluşturuldu');
    }
    loadTemplates();
  };

  const handleDuplicate = async (template: Template) => {
    try {
      await onboardingApi.templates.duplicate(template.id);
      toast.success('Şablon kopyalandı');
      loadTemplates();
    } catch {
      // Error handled by interceptor
    }
  };

  const handleDelete = async () => {
    if (!itemToDelete) return;
    setDeleteLoading(true);
    try {
      if (itemToDelete.type === 'template') {
        await onboardingApi.templates.delete((itemToDelete.item as Template).id);
        toast.success('Şablon silindi');
        loadTemplates();
      } else {
        await onboardingApi.processes.delete((itemToDelete.item as Process).id);
        toast.success('Süreç silindi');
        loadProcesses();
      }
      setDeleteDialogOpen(false);
      setItemToDelete(null);
    } catch {
      // Error handled by interceptor
    } finally {
      setDeleteLoading(false);
    }
  };

  const processColumns = [
    {
      key: 'title',
      title: 'Süreç',
      render: (process: Process) => (
        <div>
          <div style={{ fontWeight: 500 }}>{process.title}</div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
            {process.user?.name}
          </div>
        </div>
      ),
    },
    {
      key: 'template',
      title: 'Şablon',
      render: (process: Process) => process.template?.name || '-',
    },
    {
      key: 'progress',
      title: 'İlerleme',
      render: (process: Process) => (
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <div style={{
            width: '60px',
            height: '6px',
            background: 'var(--bg-tertiary)',
            borderRadius: '3px',
            overflow: 'hidden',
          }}>
            <div style={{
              width: `${process.progress || 0}%`,
              height: '100%',
              background: 'var(--accent)',
              transition: 'width 0.3s',
            }} />
          </div>
          <span style={{ fontSize: '0.75rem' }}>{process.progress || 0}%</span>
        </div>
      ),
    },
    {
      key: 'status',
      title: 'Durum',
      render: (process: Process) => (
        <span className={`badge ${statusBadgeClass[process.status] || 'badge-secondary'}`}>
          {processStatusLabel(process.status)}
        </span>
      ),
    },
    {
      key: 'start_date',
      title: 'Başlangıç',
      render: (process: Process) => new Date(process.start_date).toLocaleDateString('tr-TR'),
    },
    {
      key: 'actions',
      title: '',
      render: (process: Process) => (
        <div style={{ display: 'flex', gap: '0.25rem' }}>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => navigate(`/onboarding/processes/${process.id}`)}
            title="Detay"
          >
            <BsEye size={14} />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => {
              setItemToDelete({ type: 'process', item: process });
              setDeleteDialogOpen(true);
            }}
            title="Sil"
            style={{ color: 'var(--danger)' }}
          >
            <BsTrash size={14} />
          </button>
        </div>
      ),
    },
  ];

  const templateColumns = [
    {
      key: 'name',
      title: 'Şablon Adı',
      render: (template: Template) => (
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <span style={{ fontWeight: 500 }}>{template.name}</span>
            {template.is_default && (
              <span className="badge badge-info" style={{ fontSize: '0.625rem' }}>Varsayılan</span>
            )}
          </div>
          {template.description && (
            <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
              {template.description}
            </div>
          )}
        </div>
      ),
    },
    {
      key: 'tasks',
      title: 'Görevler',
      render: (template: Template) => `${template.tasks?.length || 0} görev`,
    },
    {
      key: 'estimated_days',
      title: 'Tahmini Süre',
      render: (template: Template) => `${template.estimated_days} gün`,
    },
    {
      key: 'processes_count',
      title: 'Kullanım',
      render: (template: Template) => `${template.processes_count || 0} süreç`,
    },
    {
      key: 'is_active',
      title: 'Durum',
      render: (template: Template) => (
        <span className={`badge ${template.is_active ? 'badge-success' : 'badge-warning'}`}>
          {template.is_active ? 'Aktif' : 'Pasif'}
        </span>
      ),
    },
    {
      key: 'actions',
      title: '',
      render: (template: Template) => (
        <div style={{ display: 'flex', gap: '0.25rem' }}>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => {
              setSelectedTemplate(template);
              setTemplateFormOpen(true);
            }}
            title="Düzenle"
          >
            <BsPencil size={14} />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => handleDuplicate(template)}
            title="Kopyala"
          >
            <BsCopy size={14} />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => {
              setItemToDelete({ type: 'template', item: template });
              setDeleteDialogOpen(true);
            }}
            title="Sil"
            style={{ color: 'var(--danger)' }}
          >
            <BsTrash size={14} />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div>
          <h1 className="page-title">Onboarding</h1>
          <p className="page-subtitle">İşe alım süreçleri ve şablon yönetimi</p>
        </div>
        <button
          className="btn btn-primary"
          onClick={() => {
            if (activeTab === 'processes') {
              setProcessFormOpen(true);
            } else {
              setSelectedTemplate(null);
              setTemplateFormOpen(true);
            }
          }}
        >
          <BsPlus size={18} />
          {activeTab === 'processes' ? 'Yeni Süreç' : 'Yeni Şablon'}
        </button>
      </div>

      {/* Tabs */}
      <div className="tabs" style={{ marginBottom: '1.5rem' }}>
        <button
          className={`tab ${activeTab === 'processes' ? 'active' : ''}`}
          onClick={() => handleTabChange('processes')}
        >
          <BsPersonCheck size={16} />
          Süreçler
        </button>
        <button
          className={`tab ${activeTab === 'templates' ? 'active' : ''}`}
          onClick={() => handleTabChange('templates')}
        >
          <BsFileText size={16} />
          Şablonlar
        </button>
      </div>

      {/* Content */}
      {activeTab === 'processes' ? (
        processesLoading ? (
          <div className="loading-container">
            <div className="loading-spinner" />
          </div>
        ) : processes.length === 0 ? (
          <EmptyState
            icon={<BsPersonCheck size={48} />}
            title="Henüz onboarding süreci yok"
            description="Yeni bir onboarding süreci başlatarak çalışanlarınızın işe alım sürecini takip edin."
            action={
              <button className="btn btn-primary" onClick={() => setProcessFormOpen(true)}>
                <BsPlus size={18} />
                İlk Süreci Başlat
              </button>
            }
          />
        ) : (
          <DataTable
            columns={processColumns}
            data={processes}
            currentPage={processPage}
            totalPages={processTotalPages}
            onPageChange={setProcessPage}
          />
        )
      ) : templatesLoading ? (
        <div className="loading-container">
          <div className="loading-spinner" />
        </div>
      ) : templates.length === 0 ? (
        <EmptyState
          icon={<BsFileText size={48} />}
          title="Henüz şablon yok"
          description="Onboarding şablonları oluşturarak süreçlerinizi standartlaştırın."
          action={
            <button className="btn btn-primary" onClick={() => { setSelectedTemplate(null); setTemplateFormOpen(true); }}>
              <BsPlus size={18} />
              İlk Şablonu Oluştur
            </button>
          }
        />
      ) : (
        <DataTable
          columns={templateColumns}
          data={templates}
          currentPage={templatePage}
          totalPages={templateTotalPages}
          onPageChange={setTemplatePage}
        />
      )}

      {/* Process Form */}
      <ProcessForm
        isOpen={processFormOpen}
        onClose={() => setProcessFormOpen(false)}
        onSuccess={loadProcesses}
      />

      {/* Template Form */}
      <TemplateForm
        isOpen={templateFormOpen}
        onClose={() => { setTemplateFormOpen(false); setSelectedTemplate(null); }}
        onSubmit={handleTemplateSubmit}
        template={selectedTemplate}
      />

      {/* Delete Confirm */}
      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => { setDeleteDialogOpen(false); setItemToDelete(null); }}
        onConfirm={handleDelete}
        title={itemToDelete?.type === 'template' ? 'Şablonu Sil' : 'Süreci Sil'}
        message={`"${itemToDelete?.type === 'template' 
          ? (itemToDelete.item as Template).name 
          : (itemToDelete?.item as Process)?.title}" ${itemToDelete?.type === 'template' ? 'şablonunu' : 'sürecini'} silmek istediğinize emin misiniz?`}
        confirmText="Sil"
        variant="danger"
        loading={deleteLoading}
      />
    </div>
  );
};

export default OnboardingPage;

