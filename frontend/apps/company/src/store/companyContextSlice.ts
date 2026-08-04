import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import api from '@shared/services/api';

export const COMPANY_STORAGE_KEY = 'alatax_company_id';

export interface ContextCompany {
  id: number;
  name: string;
  slug: string;
  is_active: boolean;
}

export interface CompanyContextState {
  companies: ContextCompany[];
  activeCompanyId: number | null;
  /** Seçili şirket id (string) veya henüz seçim yoksa '' */
  selectedCompanyId: string;
  /** Şirket değişince listeleri yenilemek için */
  version: number;
  loaded: boolean;
  loading: boolean;
}

function readStoredCompanyId(): string {
  try {
    const v = localStorage.getItem(COMPANY_STORAGE_KEY);
    if (v && v.length > 0) return v;
  } catch {
    /* ignore */
  }
  return '';
}

const initialState: CompanyContextState = {
  companies: [],
  activeCompanyId: null,
  selectedCompanyId: readStoredCompanyId(),
  version: 0,
  loaded: false,
  loading: false,
};

export const fetchCompanyContext = createAsyncThunk(
  'companyContext/fetch',
  async () => {
    const res = await api.get<{
      success: boolean;
      data: {
        companies: ContextCompany[];
        active_company_id: number | null;
      };
    }>('/context/companies');
    return res.data.data;
  }
);

const companyContextSlice = createSlice({
  name: 'companyContext',
  initialState,
  reducers: {
    setSelectedCompanyId(state, action: PayloadAction<string>) {
      state.selectedCompanyId = action.payload;
      state.version += 1;
      try {
        localStorage.setItem(COMPANY_STORAGE_KEY, action.payload);
      } catch {
        /* ignore */
      }
    },
    resetCompanyContext(state) {
      state.companies = [];
      state.activeCompanyId = null;
      state.selectedCompanyId = '';
      state.loaded = false;
      state.version += 1;
      try {
        localStorage.removeItem(COMPANY_STORAGE_KEY);
      } catch {
        /* ignore */
      }
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(fetchCompanyContext.pending, (state) => {
        state.loading = true;
      })
      .addCase(fetchCompanyContext.fulfilled, (state, action) => {
        state.loading = false;
        state.loaded = true;
        state.companies = action.payload.companies;
        state.activeCompanyId = action.payload.active_company_id;

        if (action.payload.companies.length === 1) {
          // Tek şirket: seçim otomatik ve kilitli.
          state.selectedCompanyId = String(action.payload.companies[0].id);
          try {
            localStorage.setItem(COMPANY_STORAGE_KEY, state.selectedCompanyId);
          } catch {
            /* ignore */
          }
        } else if (
          !action.payload.companies.some((c) => String(c.id) === state.selectedCompanyId)
        ) {
          state.selectedCompanyId =
            action.payload.active_company_id !== null
              ? String(action.payload.active_company_id)
              : String(action.payload.companies[0]?.id ?? '');
          try {
            localStorage.setItem(COMPANY_STORAGE_KEY, state.selectedCompanyId);
          } catch {
            /* ignore */
          }
        }
      })
      .addCase(fetchCompanyContext.rejected, (state) => {
        state.loading = false;
        state.loaded = true;
      });
  },
});

export const { setSelectedCompanyId, resetCompanyContext } = companyContextSlice.actions;
export default companyContextSlice.reducer;
