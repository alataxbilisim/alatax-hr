import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import api from '@shared/services/api';

export const BRANCH_STORAGE_KEY = 'alatax_branch_id';
export const BRANCH_ALL = 'all';

export interface ContextBranch {
  id: number;
  name: string;
  code: string | null;
}

export interface BranchContextState {
  branches: ContextBranch[];
  canSelectAll: boolean;
  lockedBranchId: number | null;
  /** 'all' veya şube id string */
  selectedBranchId: string;
  /** Şube değişince listeleri yenilemek için */
  version: number;
  loaded: boolean;
  loading: boolean;
}

function readStoredBranchId(): string {
  try {
    const v = localStorage.getItem(BRANCH_STORAGE_KEY);
    if (v && v.length > 0) return v;
  } catch {
    /* ignore */
  }
  return BRANCH_ALL;
}

const initialState: BranchContextState = {
  branches: [],
  canSelectAll: false,
  lockedBranchId: null,
  selectedBranchId: readStoredBranchId(),
  version: 0,
  loaded: false,
  loading: false,
};

export const fetchBranchContext = createAsyncThunk(
  'branchContext/fetch',
  async () => {
    const res = await api.get<{
      success: boolean;
      data: {
        branches: ContextBranch[];
        can_select_all: boolean;
        locked_branch_id: number | null;
      };
    }>('/context/branches');
    return res.data.data;
  }
);

const branchContextSlice = createSlice({
  name: 'branchContext',
  initialState,
  reducers: {
    setSelectedBranchId(state, action: PayloadAction<string>) {
      let next = action.payload;
      if (!state.canSelectAll && state.lockedBranchId !== null) {
        next = String(state.lockedBranchId);
      }
      if (!state.canSelectAll && next === BRANCH_ALL && state.lockedBranchId !== null) {
        next = String(state.lockedBranchId);
      }
      state.selectedBranchId = next;
      state.version += 1;
      try {
        localStorage.setItem(BRANCH_STORAGE_KEY, next);
      } catch {
        /* ignore */
      }
    },
    resetBranchContext(state) {
      state.branches = [];
      state.canSelectAll = false;
      state.lockedBranchId = null;
      state.selectedBranchId = BRANCH_ALL;
      state.loaded = false;
      state.version += 1;
      try {
        localStorage.removeItem(BRANCH_STORAGE_KEY);
      } catch {
        /* ignore */
      }
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(fetchBranchContext.pending, (state) => {
        state.loading = true;
      })
      .addCase(fetchBranchContext.fulfilled, (state, action) => {
        state.loading = false;
        state.loaded = true;
        state.branches = action.payload.branches;
        state.canSelectAll = action.payload.can_select_all;
        state.lockedBranchId = action.payload.locked_branch_id;

        if (!action.payload.can_select_all && action.payload.locked_branch_id !== null) {
          state.selectedBranchId = String(action.payload.locked_branch_id);
          try {
            localStorage.setItem(BRANCH_STORAGE_KEY, state.selectedBranchId);
          } catch {
            /* ignore */
          }
        } else if (
          state.selectedBranchId !== BRANCH_ALL &&
          !action.payload.branches.some((b) => String(b.id) === state.selectedBranchId)
        ) {
          state.selectedBranchId = action.payload.can_select_all
            ? BRANCH_ALL
            : String(action.payload.branches[0]?.id ?? BRANCH_ALL);
          try {
            localStorage.setItem(BRANCH_STORAGE_KEY, state.selectedBranchId);
          } catch {
            /* ignore */
          }
        }
      })
      .addCase(fetchBranchContext.rejected, (state) => {
        state.loading = false;
        state.loaded = true;
      });
  },
});

export const { setSelectedBranchId, resetBranchContext } = branchContextSlice.actions;
export default branchContextSlice.reducer;
