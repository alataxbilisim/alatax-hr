import React, { useEffect, useState, useCallback } from 'react';
import { assetsApi, lookupsApi, type LookupItem } from '@shared/services/api';
import toast from 'react-hot-toast';
import { DataTable, ConfirmDialog, EmptyState } from '../../components/ui';
import AssetForm from '../../components/assets/AssetForm';
import CategoryForm from '../../components/assets/CategoryForm';
import AssignmentForm from '../../components/assets/AssignmentForm';
import { useNavigate } from 'react-router-dom';
import {
  BsPlus,
  BsLaptop,
  BsFolder,
  BsPencil,
  BsTrash,
  BsEye,
  BsPersonPlus,
  BsArrowReturnLeft,
} from 'react-icons/bs';

interface Category {
  id: number;
  name: string;
  description?: string;
  assets_count?: number;
}

interface Asset {
  id: number;
  name: string;
  description?: string;
  category?: { id: number; name: string };
  category_id?: number;
  asset_code?: string;
  serial_number?: string;
  brand?: string;
  model?: string;
  purchase_date?: string;
  purchase_price?: number;
  warranty_end_date?: string;
  status: string;
  condition: string;
  current_assignment?: {
    user: { id: number; name: string };
    assigned_date: string;
  };
}

type TabType = 'assets' | 'categories';

const statusBadgeClass: Record<string, string> = {
  available: 'badge-success',
  assigned: 'badge-info',
  maintenance: 'badge-warning',
  disposed: 'badge-secondary',
};

const AssetsPage: React.FC = () => {
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState<TabType>('assets');

  // Assets state
  const [assets, setAssets] = useState<Asset[]>([]);
  const [assetsLoading, setAssetsLoading] = useState(true);
  const [assetPage, setAssetPage] = useState(1);
  const [assetTotalPages, setAssetTotalPages] = useState(1);
  const [assetFormOpen, setAssetFormOpen] = useState(false);
  const [selectedAsset, setSelectedAsset] = useState<Asset | null>(null);

  // Categories state
  const [categories, setCategories] = useState<Category[]>([]);
  const [categoriesLoading, setCategoriesLoading] = useState(true);
  const [categoryFormOpen, setCategoryFormOpen] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<Category | null>(null);

  // Assignment state
  const [assignmentFormOpen, setAssignmentFormOpen] = useState(false);
  const [assetToAssign, setAssetToAssign] = useState<Asset | null>(null);

  // Delete state
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [itemToDelete, setItemToDelete] = useState<{ type: 'asset' | 'category'; item: Asset | Category } | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  // Return dialog
  const [returnDialogOpen, setReturnDialogOpen] = useState(false);
  const [assetToReturn, setAssetToReturn] = useState<Asset | null>(null);

  const [statusOptions, setStatusOptions] = useState<LookupItem[]>([]);
  const [conditionOptions, setConditionOptions] = useState<LookupItem[]>([]);

  const loadLookups = useCallback(async () => {
    try {
      const [statusRes, conditionRes] = await Promise.all([
        lookupsApi.forType('asset_status'),
        lookupsApi.forType('asset_condition'),
      ]);
      setStatusOptions(statusRes.data.data ?? []);
      setConditionOptions(conditionRes.data.data ?? []);
    } catch {
      console.error('Varlık lookup listeleri yüklenemedi');
    }
  }, []);

  const statusLabel = (value: string) =>
    statusOptions.find((o) => o.value === value)?.label || value;

  const conditionLabel = (value: string) =>
    conditionOptions.find((o) => o.value === value)?.label || value;

  const loadCategories = useCallback(async () => {
    try {
      setCategoriesLoading(true);
      const response = await assetsApi.categories.list();
      setCategories(response.data.data || []);
    } catch {
      toast.error('Kategoriler yüklenemedi');
    } finally {
      setCategoriesLoading(false);
    }
  }, []);

  const loadAssets = useCallback(async () => {
    try {
      setAssetsLoading(true);
      const response = await assetsApi.items.list({ page: assetPage });
      const data = response.data.data;
      setAssets(data.data || []);
      setAssetTotalPages(data.last_page || 1);
    } catch {
      toast.error('Varlıklar yüklenemedi');
    } finally {
      setAssetsLoading(false);
    }
  }, [assetPage]);

  useEffect(() => {
    loadCategories();
    loadLookups();
  }, [loadCategories, loadLookups]);

  useEffect(() => {
    if (activeTab === 'assets') {
      loadAssets();
    }
  }, [activeTab, loadAssets]);

  

  

  const handleAssetSubmit = async (data: Omit<Asset, 'id'>) => {
    if (selectedAsset) {
      await assetsApi.items.update(selectedAsset.id, data);
      toast.success('Varlık güncellendi');
    } else {
      await assetsApi.items.create(data);
      toast.success('Varlık oluşturuldu');
    }
    loadAssets();
  };

  const handleCategorySubmit = async (data: Omit<Category, 'id'>) => {
    if (selectedCategory) {
      await assetsApi.categories.update(selectedCategory.id, data);
      toast.success('Kategori güncellendi');
    } else {
      await assetsApi.categories.create(data);
      toast.success('Kategori oluşturuldu');
    }
    loadCategories();
  };

  const handleAssign = async (data: { user_id: number; notes?: string; assigned_date?: string }) => {
    if (!assetToAssign) return;
    try {
      await assetsApi.items.assign(assetToAssign.id, data);
      toast.success('Varlık zimmetlendi');
      loadAssets();
    } catch {
      throw new Error('Assignment failed');
    }
  };

  const handleReturn = async () => {
    if (!assetToReturn) return;
    try {
      await assetsApi.items.return(assetToReturn.id);
      toast.success('Varlık iade alındı');
      setReturnDialogOpen(false);
      setAssetToReturn(null);
      loadAssets();
    } catch {
      // Error handled by interceptor
    }
  };

  const handleDelete = async () => {
    if (!itemToDelete) return;
    setDeleteLoading(true);
    try {
      if (itemToDelete.type === 'asset') {
        await assetsApi.items.delete((itemToDelete.item as Asset).id);
        toast.success('Varlık silindi');
        loadAssets();
      } else {
        await assetsApi.categories.delete((itemToDelete.item as Category).id);
        toast.success('Kategori silindi');
        loadCategories();
      }
      setDeleteDialogOpen(false);
      setItemToDelete(null);
    } catch {
      // Error handled by interceptor
    } finally {
      setDeleteLoading(false);
    }
  };

  const assetColumns = [
    {
      key: 'name',
      title: 'Varlık',
      render: (a: Asset) => (
        <div>
          <div style={{ fontWeight: 500 }}>{a.name}</div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
            {a.asset_code || a.serial_number || '-'}
          </div>
        </div>
      ),
    },
    {
      key: 'category',
      title: 'Kategori',
      render: (a: Asset) => a.category?.name || '-',
    },
    {
      key: 'brand',
      title: 'Marka/Model',
      render: (a: Asset) => a.brand && a.model ? `${a.brand} ${a.model}` : a.brand || a.model || '-',
    },
    {
      key: 'status',
      title: 'Durum',
      render: (a: Asset) => (
        <span className={`badge ${statusBadgeClass[a.status] || 'badge-secondary'}`}>
          {statusLabel(a.status)}
        </span>
      ),
    },
    {
      key: 'assigned_to',
      title: 'Zimmetli',
      render: (a: Asset) => a.current_assignment?.user?.name || '-',
    },
    {
      key: 'condition',
      title: 'Kondisyon',
      render: (a: Asset) => conditionLabel(a.condition),
    },
    {
      key: 'actions',
      title: '',
      render: (a: Asset) => (
        <div style={{ display: 'flex', gap: '0.25rem' }}>
          {a.status === 'available' && (
            <button
              className="btn btn-ghost btn-icon btn-sm"
              onClick={() => { setAssetToAssign(a); setAssignmentFormOpen(true); }}
              title="Zimmetle"
              style={{ color: 'var(--accent)' }}
            >
              <BsPersonPlus size={14} />
            </button>
          )}
          {a.status === 'assigned' && (
            <button
              className="btn btn-ghost btn-icon btn-sm"
              onClick={() => { setAssetToReturn(a); setReturnDialogOpen(true); }}
              title="İade Al"
              style={{ color: 'var(--warning)' }}
            >
              <BsArrowReturnLeft size={14} />
            </button>
          )}
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => navigate(`/assets/${a.id}`)}
            title="Detay"
          >
            <BsEye size={14} />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => { setSelectedAsset(a); setAssetFormOpen(true); }}
            title="Düzenle"
          >
            <BsPencil size={14} />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => { setItemToDelete({ type: 'asset', item: a }); setDeleteDialogOpen(true); }}
            title="Sil"
            style={{ color: 'var(--danger)' }}
          >
            <BsTrash size={14} />
          </button>
        </div>
      ),
    },
  ];

  const categoryColumns = [
    {
      key: 'name',
      label: 'Kategori Adı',
      render: (c: Category) => (
        <div>
          <div style={{ fontWeight: 500 }}>{c.name}</div>
          {c.description && (
            <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{c.description}</div>
          )}
        </div>
      ),
    },
    {
      key: 'assets_count',
      label: 'Varlık Sayısı',
      render: (c: Category) => `${c.assets_count || 0} varlık`,
    },
    {
      key: 'actions',
      label: '',
      render: (c: Category) => (
        <div className="table-actions">
          <button
            type="button"
            className="btn btn-ghost btn-icon"
            onClick={() => { setSelectedCategory(c); setCategoryFormOpen(true); }}
            title="Düzenle"
            aria-label="Düzenle"
          >
            <BsPencil />
          </button>
          <button
            type="button"
            className="btn btn-ghost btn-icon"
            onClick={() => { setItemToDelete({ type: 'category', item: c }); setDeleteDialogOpen(true); }}
            title="Sil"
            aria-label="Sil"
            style={{ color: 'var(--danger)' }}
          >
            <BsTrash />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="animate-fade-in list-page">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Varlıklar</h1>
        </div>
        <div className="page-header-actions">
          <button
            type="button"
            className="btn btn-primary btn-sm"
            onClick={() => {
              if (activeTab === 'assets') {
                setSelectedAsset(null);
                setAssetFormOpen(true);
              } else {
                setSelectedCategory(null);
                setCategoryFormOpen(true);
              }
            }}
          >
            <BsPlus />
            {activeTab === 'assets' ? 'Yeni Varlık' : 'Yeni Kategori'}
          </button>
        </div>
      </div>

      <div className="tabs">
        <button
          type="button"
          className={`tab ${activeTab === 'assets' ? 'active' : ''}`}
          onClick={() => setActiveTab('assets')}
        >
          <BsLaptop size={16} />
          Varlıklar
        </button>
        <button
          type="button"
          className={`tab ${activeTab === 'categories' ? 'active' : ''}`}
          onClick={() => setActiveTab('categories')}
        >
          <BsFolder size={16} />
          Kategoriler
        </button>
      </div>

      {activeTab === 'assets' ? (
        assetsLoading ? (
          <div className="page-loading"><div className="loading-spinner" /></div>
        ) : assets.length === 0 ? (
          <EmptyState
            icon={<BsLaptop size={48} />}
            title="Henüz varlık yok"
            description="Yeni bir varlık ekleyerek başlayın."
            action={
              <button type="button" className="btn btn-primary btn-sm" onClick={() => { setSelectedAsset(null); setAssetFormOpen(true); }}>
                <BsPlus />
                İlk Varlığı Ekle
              </button>
            }
          />
        ) : (
          <DataTable
            columns={assetColumns}
            data={assets}
            currentPage={assetPage}
            totalPages={assetTotalPages}
            onPageChange={setAssetPage}
          />
        )
      ) : categoriesLoading ? (
        <div className="page-loading"><div className="loading-spinner" /></div>
      ) : categories.length === 0 ? (
        <EmptyState
          icon={<BsFolder size={48} />}
          title="Henüz kategori yok"
          description="Varlıkları gruplamak için kategoriler oluşturun."
          action={
            <button type="button" className="btn btn-primary btn-sm" onClick={() => { setSelectedCategory(null); setCategoryFormOpen(true); }}>
              <BsPlus />
              İlk Kategoriyi Oluştur
            </button>
          }
        />
      ) : (
        <div className="card">
          <div className="table-container">
            <table className="table">
              <thead>
                <tr>
                  {categoryColumns.map(col => <th key={col.key}>{col.label}</th>)}
                </tr>
              </thead>
              <tbody>
                {categories.map(c => (
                  <tr key={c.id}>
                    {categoryColumns.map(col => <td key={col.key}>{col.render(c)}</td>)}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Asset Form */}
      <AssetForm
        isOpen={assetFormOpen}
        onClose={() => { setAssetFormOpen(false); setSelectedAsset(null); }}
        onSubmit={handleAssetSubmit}
        asset={selectedAsset}
        categories={categories}
      />

      {/* Category Form */}
      <CategoryForm
        isOpen={categoryFormOpen}
        onClose={() => { setCategoryFormOpen(false); setSelectedCategory(null); }}
        onSubmit={handleCategorySubmit}
        category={selectedCategory}
      />

      {/* Assignment Form */}
      <AssignmentForm
        isOpen={assignmentFormOpen}
        onClose={() => { setAssignmentFormOpen(false); setAssetToAssign(null); }}
        onSubmit={handleAssign}
        assetName={assetToAssign?.name || ''}
      />

      {/* Return Dialog */}
      <ConfirmDialog
        isOpen={returnDialogOpen}
        onClose={() => { setReturnDialogOpen(false); setAssetToReturn(null); }}
        onConfirm={handleReturn}
        title="Varlık İade"
        message={`"${assetToReturn?.name}" varlığını ${assetToReturn?.current_assignment?.user?.name}'dan iade almak istiyor musunuz?`}
        confirmText="İade Al"
        variant="warning"
      />

      {/* Delete Confirm */}
      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => { setDeleteDialogOpen(false); setItemToDelete(null); }}
        onConfirm={handleDelete}
        title={itemToDelete?.type === 'asset' ? 'Varlığı Sil' : 'Kategoriyi Sil'}
        message={`"${(itemToDelete?.item as Asset | Category)?.name}" silinecek. Emin misiniz?`}
        confirmText="Sil"
        variant="danger"
        loading={deleteLoading}
      />
    </div>
  );
};

export default AssetsPage;

