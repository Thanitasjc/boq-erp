const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api";

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem("auth_token");
}

export function setToken(token: string): void {
  localStorage.setItem("auth_token", token);
}

export function clearToken(): void {
  localStorage.removeItem("auth_token");
}

export async function apiFetch<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const token = getToken();
  const headers: Record<string, string> = {
    Accept: "application/json",
    ...(options.headers as Record<string, string>),
  };

  if (!(options.body instanceof FormData)) {
    headers["Content-Type"] = "application/json";
  }

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 20000);

  try {
    const res = await fetch(`${API_URL}${path}`, {
      ...options,
      headers,
      signal: controller.signal,
    });

    if (!res.ok) {
      const body = await res.json().catch(() => ({}));
      throw new ApiError(
        body.message || "Request failed",
        res.status,
        body.errors,
      );
    }

    if (res.status === 204) return undefined as T;
    return res.json();
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") {
      throw new ApiError("Request timed out", 0);
    }
    throw err;
  } finally {
    clearTimeout(timeout);
  }
}

export async function apiDownload(path: string): Promise<Blob> {
  const token = getToken();
  const res = await fetch(`${API_URL}${path}`, {
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  if (!res.ok) throw new ApiError("Download failed", res.status);
  return res.blob();
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
}

export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  position: string | null;
  is_active: boolean;
  company: { id: number; code: string; name: string } | null;
  roles: string[];
  permissions: string[];
}

export interface LoginResponse {
  token: string;
  user: User;
}

export interface Project {
  id: number;
  code: string;
  name: string;
  client_name: string | null;
  status: string;
  start_date: string | null;
  end_date: string | null;
  contract_value: number;
  original_budget: number;
  revised_budget: number;
  description: string | null;
  location: string | null;
  project_manager: { id: number; name: string } | null;
}

export interface CompanyDashboard {
  kpis: {
    total_projects: number;
    active_projects: number;
    planning_projects: number;
    contract_value: number;
    original_budget: number;
    revised_budget: number;
    committed_cost: number;
    actual_cost: number;
    remaining_budget: number;
    forecast_cost: number;
    forecast_profit: number;
    profit_margin: number;
  };
  projects_by_status: Record<string, number>;
  chart_data?: ChartDataPoint[];
  status_chart?: { status: string; label: string; count: number }[];
  projects: Project[];
  filters?: {
    project_managers: { id: number; name: string }[];
    years: { value: string; label: string; ce_year: number }[];
  };
}

export interface ChartDataPoint {
  id: number;
  code: string;
  name: string;
  budget: number;
  committed: number;
  actual: number;
  contract_value: number;
  profit: number;
}

export interface SearchResult {
  type: string;
  id: number;
  label: string;
  href: string;
}

export const authApi = {
  login: (email: string, password: string) =>
    apiFetch<LoginResponse>("/login", {
      method: "POST",
      body: JSON.stringify({ email, password }),
    }),
  logout: () => apiFetch<{ message: string }>("/logout", { method: "POST" }),
  user: () => apiFetch<User>("/user"),
};

export const dashboardApi = {
  company: (params?: Record<string, string>) => {
    const qs = params ? "?" + new URLSearchParams(params).toString() : "";
    return apiFetch<CompanyDashboard>(`/dashboard/company${qs}`);
  },
  exportExcel: (params?: Record<string, string>) => {
    const qs = params ? "?" + new URLSearchParams(params).toString() : "";
    return apiDownload(`/dashboard/company/export${qs}`);
  },
};

export const searchApi = {
  query: (q: string) => apiFetch<{ data: SearchResult[] }>(`/search?q=${encodeURIComponent(q)}`),
};

export const notificationsApi = {
  list: () => apiFetch<{ data: { id: number; title: string; message: string; link: string | null; read_at: string | null }[]; unread_count: number }>("/notifications"),
  markRead: (id: number) => apiFetch(`/notifications/${id}/read`, { method: "POST" }),
};

export const projectsApi = {
  list: (params?: Record<string, string>) => {
    const qs = params ? "?" + new URLSearchParams(params).toString() : "";
    return apiFetch<PaginatedResponse<Project>>(`/projects${qs}`);
  },
  get: (id: number) => apiFetch<Project>(`/projects/${id}`),
  create: (data: {
    name: string;
    client_name?: string;
    status?: string;
    start_date?: string;
    end_date?: string;
    contract_value?: number;
    description?: string;
    location?: string;
  }) =>
    apiFetch<{ message: string; data: Project }>("/projects", {
      method: "POST",
      body: JSON.stringify(data),
    }),
};

export interface BoqVersion {
  id: number;
  document_number: string | null;
  version_number: string;
  title: string;
  status: "draft" | "submitted" | "approved" | "rejected";
  total_amount: number;
  notes: string | null;
  is_editable: boolean;
  items_count?: number;
  creator?: { id: number; name: string };
  submitted_at: string | null;
  approved_at: string | null;
  rejection_reason: string | null;
  created_at: string;
}

export interface BoqItem {
  id: number;
  wbs_code: string | null;
  cost_code: string | null;
  item_code: string | null;
  description: string;
  specification: string | null;
  uom_code: string | null;
  quantity: number;
  material_rate: number;
  labor_rate: number;
  equipment_rate: number;
  unit_rate: number;
  amount: number;
  sort_order: number;
  remarks: string | null;
}

export interface BoqDetail {
  data: BoqVersion;
  items: BoqItem[];
}

export interface ImportPreview {
  headers: string[];
  mapped_columns: Record<string, number>;
  rows: {
    row: number;
    data: Record<string, string>;
    errors: string[];
    warnings: string[];
    status: string;
  }[];
  summary: { total: number; valid: number; warning: number; error: number };
}

export const boqApi = {
  list: (projectId: number, params?: Record<string, string>) => {
    const qs = params ? "?" + new URLSearchParams(params).toString() : "";
    return apiFetch<PaginatedResponse<BoqVersion>>(
      `/projects/${projectId}/boq-versions${qs}`,
    );
  },
  get: (projectId: number, versionId: number) =>
    apiFetch<BoqDetail>(`/projects/${projectId}/boq-versions/${versionId}`),
  create: (projectId: number, data: { title?: string; notes?: string }) =>
    apiFetch<{ data: BoqVersion }>(`/projects/${projectId}/boq-versions`, {
      method: "POST",
      body: JSON.stringify(data),
    }),
  duplicate: (projectId: number, versionId: number) =>
    apiFetch<{ data: BoqVersion }>(
      `/projects/${projectId}/boq-versions/${versionId}/duplicate`,
      { method: "POST" },
    ),
  submit: (projectId: number, versionId: number, comment?: string) =>
    apiFetch<{ data: BoqVersion }>(
      `/projects/${projectId}/boq-versions/${versionId}/submit`,
      { method: "POST", body: JSON.stringify({ comment }) },
    ),
  approve: (projectId: number, versionId: number, comment?: string) =>
    apiFetch<{ data: BoqVersion }>(
      `/projects/${projectId}/boq-versions/${versionId}/approve`,
      { method: "POST", body: JSON.stringify({ comment }) },
    ),
  reject: (projectId: number, versionId: number, comment: string) =>
    apiFetch<{ data: BoqVersion }>(
      `/projects/${projectId}/boq-versions/${versionId}/reject`,
      { method: "POST", body: JSON.stringify({ comment }) },
    ),
  addItem: (projectId: number, versionId: number, data: Partial<BoqItem>) =>
    apiFetch<{ data: BoqItem }>(
      `/projects/${projectId}/boq-versions/${versionId}/items`,
      { method: "POST", body: JSON.stringify(data) },
    ),
  deleteItem: (projectId: number, versionId: number, itemId: number) =>
    apiFetch(`/projects/${projectId}/boq-versions/${versionId}/items/${itemId}`, {
      method: "DELETE",
    }),
  importPreview: (projectId: number, versionId: number, file: File) => {
    const form = new FormData();
    form.append("file", file);
    return apiFetch<ImportPreview>(
      `/projects/${projectId}/boq-versions/${versionId}/import/preview`,
      { method: "POST", body: form },
    );
  },
  importConfirm: (
    projectId: number,
    versionId: number,
    file: File,
    columnMap: Record<string, number>,
    replaceExisting = false,
  ) => {
    const form = new FormData();
    form.append("file", file);
    form.append("column_map", JSON.stringify(columnMap));
    form.append("replace_existing", replaceExisting ? "1" : "0");
    return apiFetch(
      `/projects/${projectId}/boq-versions/${versionId}/import/confirm`,
      { method: "POST", body: form },
    );
  },
  exportUrl: (projectId: number, versionId: number) =>
    `/projects/${projectId}/boq-versions/${versionId}/export`,
};

export interface Contract {
  id: number;
  document_number: string | null;
  contract_number: string | null;
  title: string;
  client_name: string | null;
  contract_value: number;
  signed_date: string | null;
  start_date: string | null;
  end_date: string | null;
  retention_percent: number;
  terms: string | null;
  status: string;
}

export interface BudgetLine {
  id: number;
  cost_code: string | null;
  cost_code_name: string | null;
  boq_amount: number;
  budget_amount: number;
  committed_amount: number;
  actual_amount: number;
  remaining: number;
}

export interface Budget {
  id: number;
  document_number: string | null;
  version_number: string;
  title: string;
  status: string;
  boq_total: number;
  contingency_percent: number;
  contingency_amount: number;
  markup_percent: number;
  markup_amount: number;
  total_amount: number;
  is_baseline: boolean;
  is_editable: boolean;
  notes: string | null;
  boq_version?: { id: number; version_number: string; title: string };
  lines?: BudgetLine[];
  lines_count?: number;
  approved_at: string | null;
  rejection_reason: string | null;
}

export interface LedgerSummary {
  budget: number;
  committed: number;
  actual: number;
  remaining: number;
  billing: number;
  cash_in?: number;
  cash_out?: number;
  profit: number;
  margin?: number;
}

export const contractApi = {
  get: (projectId: number) =>
    apiFetch<{ data: Contract | null }>(`/projects/${projectId}/contract`),
  create: (projectId: number, data: Partial<Contract>) =>
    apiFetch<{ data: Contract }>(`/projects/${projectId}/contract`, {
      method: "POST",
      body: JSON.stringify(data),
    }),
  update: (projectId: number, data: Partial<Contract>) =>
    apiFetch<{ data: Contract }>(`/projects/${projectId}/contract`, {
      method: "PUT",
      body: JSON.stringify(data),
    }),
};

export const budgetApi = {
  list: (projectId: number) =>
    apiFetch<PaginatedResponse<Budget>>(`/projects/${projectId}/budgets`),
  get: (projectId: number, budgetId: number) =>
    apiFetch<{ data: Budget; ledger_summary: LedgerSummary }>(
      `/projects/${projectId}/budgets/${budgetId}`,
    ),
  approvedBoqVersions: (projectId: number) =>
    apiFetch<{ data: { id: number; version_number: string; title: string; total_amount: number }[] }>(
      `/projects/${projectId}/budgets/approved-boq-versions`,
    ),
  generate: (
    projectId: number,
    data: {
      boq_version_id: number;
      contingency_percent?: number;
      markup_percent?: number;
      title?: string;
    },
  ) =>
    apiFetch<{ data: Budget }>(`/projects/${projectId}/budgets/generate`, {
      method: "POST",
      body: JSON.stringify(data),
    }),
  submit: (projectId: number, budgetId: number) =>
    apiFetch<{ data: Budget }>(
      `/projects/${projectId}/budgets/${budgetId}/submit`,
      { method: "POST" },
    ),
  approve: (projectId: number, budgetId: number) =>
    apiFetch<{ data: Budget }>(
      `/projects/${projectId}/budgets/${budgetId}/approve`,
      { method: "POST" },
    ),
  reject: (projectId: number, budgetId: number, comment: string) =>
    apiFetch<{ data: Budget }>(
      `/projects/${projectId}/budgets/${budgetId}/reject`,
      { method: "POST", body: JSON.stringify({ comment }) },
    ),
  exportUrl: (projectId: number, budgetId: number) =>
    `/projects/${projectId}/budgets/${budgetId}/export`,
  ledger: (projectId: number) =>
    apiFetch<{ summary: LedgerSummary }>(`/projects/${projectId}/cost-ledger`),
};

export interface PurchaseRequestItem {
  id?: number;
  cost_code_id?: number | null;
  cost_code?: string | null;
  description: string;
  uom_code?: string | null;
  quantity: number;
  unit_price: number;
  amount?: number;
}

export interface PurchaseRequest {
  id: number;
  document_number: string | null;
  title: string;
  description: string | null;
  required_date: string | null;
  status: string;
  total_amount: number;
  notes: string | null;
  is_editable: boolean;
  items?: PurchaseRequestItem[];
  items_count?: number;
  creator?: { id: number; name: string };
  approved_at: string | null;
  rejection_reason: string | null;
  created_at: string;
}

export interface PurchaseOrderItem {
  id: number;
  cost_code_id?: number | null;
  cost_code?: string | null;
  description: string;
  uom_code?: string | null;
  quantity: number;
  unit_price: number;
  amount: number;
  received_quantity: number;
  remaining_quantity: number;
}

export interface PurchaseOrder {
  id: number;
  document_number: string | null;
  title: string;
  order_date: string | null;
  delivery_date: string | null;
  status: string;
  total_amount: number;
  notes: string | null;
  is_editable: boolean;
  purchase_request?: { id: number; document_number: string; title: string };
  supplier?: { id: number; code: string; name: string };
  items?: PurchaseOrderItem[];
  items_count?: number;
  approved_at: string | null;
  issued_at: string | null;
  rejection_reason: string | null;
  created_at: string;
}

export interface GoodsReceipt {
  id: number;
  document_number: string | null;
  receipt_date: string;
  status: string;
  total_amount: number;
  notes: string | null;
  purchase_order?: { id: number; document_number: string; title: string };
  supplier?: { id: number; name: string };
  items?: { id: number; purchase_order_item_id: number; description: string; quantity: number; unit_price: number; amount: number }[];
  items_count?: number;
  confirmed_at: string | null;
  created_at: string;
}

export interface CostCodeCategory {
  id: number;
  code: string;
  name: string;
  name_en: string | null;
  sort_order: number;
  is_active: boolean;
  cost_codes_count?: number;
}

export interface CostCode {
  id: number;
  code: string;
  name: string;
  name_en: string | null;
  category: string;
  is_active: boolean;
}

export interface Uom {
  id: number;
  code: string;
  name: string;
  name_en: string | null;
  is_active: boolean;
}

export interface Supplier {
  id: number;
  code: string;
  name: string;
  type: string;
  tax_id?: string | null;
  contact_person?: string | null;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  is_active?: boolean;
}

export const prApi = {
  list: (projectId: number, params?: Record<string, string>) => {
    const qs = params ? "?" + new URLSearchParams(params).toString() : "";
    return apiFetch<PaginatedResponse<PurchaseRequest>>(`/projects/${projectId}/purchase-requests${qs}`);
  },
  get: (projectId: number, id: number) =>
    apiFetch<{ data: PurchaseRequest }>(`/projects/${projectId}/purchase-requests/${id}`),
  create: (projectId: number, data: { title: string; description?: string; required_date?: string; notes?: string; items: PurchaseRequestItem[] }) =>
    apiFetch<{ data: PurchaseRequest }>(`/projects/${projectId}/purchase-requests`, { method: "POST", body: JSON.stringify(data) }),
  submit: (projectId: number, id: number) =>
    apiFetch<{ data: PurchaseRequest }>(`/projects/${projectId}/purchase-requests/${id}/submit`, { method: "POST" }),
  approve: (projectId: number, id: number) =>
    apiFetch<{ data: PurchaseRequest }>(`/projects/${projectId}/purchase-requests/${id}/approve`, { method: "POST" }),
  reject: (projectId: number, id: number, comment: string) =>
    apiFetch<{ data: PurchaseRequest }>(`/projects/${projectId}/purchase-requests/${id}/reject`, { method: "POST", body: JSON.stringify({ comment }) }),
};

export const poApi = {
  list: (projectId: number, params?: Record<string, string>) => {
    const qs = params ? "?" + new URLSearchParams(params).toString() : "";
    return apiFetch<PaginatedResponse<PurchaseOrder>>(`/projects/${projectId}/purchase-orders${qs}`);
  },
  get: (projectId: number, id: number) =>
    apiFetch<{ data: PurchaseOrder }>(`/projects/${projectId}/purchase-orders/${id}`),
  create: (projectId: number, data: { purchase_request_id?: number; supplier_id: number; title: string; order_date?: string; delivery_date?: string; notes?: string; items?: PurchaseRequestItem[] }) =>
    apiFetch<{ data: PurchaseOrder }>(`/projects/${projectId}/purchase-orders`, { method: "POST", body: JSON.stringify(data) }),
  submit: (projectId: number, id: number) =>
    apiFetch<{ data: PurchaseOrder }>(`/projects/${projectId}/purchase-orders/${id}/submit`, { method: "POST" }),
  approve: (projectId: number, id: number) =>
    apiFetch<{ data: PurchaseOrder }>(`/projects/${projectId}/purchase-orders/${id}/approve`, { method: "POST" }),
  issue: (projectId: number, id: number) =>
    apiFetch<{ data: PurchaseOrder }>(`/projects/${projectId}/purchase-orders/${id}/issue`, { method: "POST" }),
  reject: (projectId: number, id: number, comment: string) =>
    apiFetch<{ data: PurchaseOrder }>(`/projects/${projectId}/purchase-orders/${id}/reject`, { method: "POST", body: JSON.stringify({ comment }) }),
};

export const grApi = {
  list: (projectId: number) =>
    apiFetch<PaginatedResponse<GoodsReceipt>>(`/projects/${projectId}/goods-receipts`),
  get: (projectId: number, id: number) =>
    apiFetch<{ data: GoodsReceipt }>(`/projects/${projectId}/goods-receipts/${id}`),
  issuableOrders: (projectId: number) =>
    apiFetch<{ data: PurchaseOrder[] }>(`/projects/${projectId}/goods-receipts/issuable-orders`),
  create: (projectId: number, data: { purchase_order_id: number; receipt_date?: string; notes?: string; items: { purchase_order_item_id: number; quantity: number; unit_price?: number }[] }) =>
    apiFetch<{ data: GoodsReceipt }>(`/projects/${projectId}/goods-receipts`, { method: "POST", body: JSON.stringify(data) }),
  confirm: (projectId: number, id: number) =>
    apiFetch<{ data: GoodsReceipt }>(`/projects/${projectId}/goods-receipts/${id}/confirm`, { method: "POST" }),
};

function masterCrud<T>(endpoint: string) {
  return {
    list: (params?: Record<string, string>) => {
      const qs = params ? "?" + new URLSearchParams(params).toString() : "";
      return apiFetch<PaginatedResponse<T>>(`${endpoint}${qs}`);
    },
    create: (data: Record<string, unknown>) =>
      apiFetch<{ data: T; message: string }>(endpoint, {
        method: "POST",
        body: JSON.stringify(data),
      }),
    update: (id: number, data: Record<string, unknown>) =>
      apiFetch<{ data: T; message: string }>(`${endpoint}/${id}`, {
        method: "PUT",
        body: JSON.stringify(data),
      }),
    delete: (id: number) =>
      apiFetch<{ message: string }>(`${endpoint}/${id}`, { method: "DELETE" }),
  };
}

export const mastersApi = {
  costCodeCategories: masterCrud<CostCodeCategory>("/masters/cost-code-categories"),
  costCodes: masterCrud<CostCode>("/masters/cost-codes"),
  uoms: masterCrud<Uom>("/masters/uoms"),
  suppliers: masterCrud<Supplier>("/masters/suppliers"),
};

export interface ScurvePoint {
  period: string;
  label: string;
  planned_percent: number;
  planned_value: number;
  actual_percent: number | null;
  earned_value: number | null;
  actual_cost: number;
}

export interface ProjectDashboard {
  project: { id: number; code: string; name: string; status: string; contract_value: number; revised_budget: number };
  kpis: {
    contract_value: number;
    budget: number;
    committed: number;
    actual: number;
    remaining: number;
    billing: number;
    profit: number;
    planned_progress: number;
    actual_progress: number;
    variance: number;
    pv: number;
    ev: number;
    ac: number;
    spi: number;
    cpi: number;
    eac: number;
  };
  scurve: ScurvePoint[];
  ledger: LedgerSummary;
}

export interface ProgressEntry {
  id: number;
  period_month: string;
  actual_percent: number;
  earned_value: number;
  notes: string | null;
  status: string;
}

export const progressApi = {
  dashboard: (projectId: number) =>
    apiFetch<{ data: ProjectDashboard }>(`/projects/${projectId}/dashboard`),
  scurve: (projectId: number) =>
    apiFetch<{ data: ScurvePoint[] }>(`/projects/${projectId}/progress/scurve`),
  list: (projectId: number) =>
    apiFetch<PaginatedResponse<ProgressEntry>>(`/projects/${projectId}/progress`),
  create: (projectId: number, data: { period_month: string; actual_percent: number; notes?: string }) =>
    apiFetch(`/projects/${projectId}/progress`, { method: "POST", body: JSON.stringify(data) }),
  generateBaseline: (projectId: number) =>
    apiFetch(`/projects/${projectId}/progress/generate-baseline`, { method: "POST" }),
};

export interface ProgressClaim {
  id: number;
  document_number: string | null;
  title: string;
  claim_date: string;
  period_month: string | null;
  progress_percent: number;
  previous_percent: number;
  gross_amount: number;
  retention_percent: number;
  retention_amount: number;
  net_amount: number;
  status: string;
  notes: string | null;
  is_editable: boolean;
  contract?: { id: number; contract_number: string; contract_value: number };
  approved_at: string | null;
  rejection_reason: string | null;
  created_at: string;
}

export interface PaymentReceipt {
  id: number;
  document_number: string | null;
  payment_date: string;
  amount: number;
  payment_method: string | null;
  reference_no: string | null;
  status: string;
  notes: string | null;
  progress_claim?: { id: number; document_number: string; title: string };
  confirmed_at: string | null;
  created_at: string;
}

export interface CashDisbursement {
  id: number;
  document_number: string | null;
  disbursement_date: string;
  amount: number;
  payee: string | null;
  description: string | null;
  status: string;
  notes: string | null;
  confirmed_at: string | null;
  created_at: string;
}

export interface FinanceSummary extends LedgerSummary {
  total_claimed: number;
  pending_claims: number;
}

export interface CashFlowPoint {
  period: string;
  cash_in: number;
  cash_out: number;
  billing: number;
  net: number;
}

export const claimApi = {
  list: (projectId: number) =>
    apiFetch<PaginatedResponse<ProgressClaim>>(`/projects/${projectId}/progress-claims`),
  create: (projectId: number, data: { title?: string; progress_percent: number; claim_date?: string; notes?: string }) =>
    apiFetch<{ data: ProgressClaim }>(`/projects/${projectId}/progress-claims`, { method: "POST", body: JSON.stringify(data) }),
  submit: (projectId: number, id: number) =>
    apiFetch<{ data: ProgressClaim }>(`/projects/${projectId}/progress-claims/${id}/submit`, { method: "POST" }),
  approve: (projectId: number, id: number) =>
    apiFetch<{ data: ProgressClaim }>(`/projects/${projectId}/progress-claims/${id}/approve`, { method: "POST" }),
  reject: (projectId: number, id: number, comment: string) =>
    apiFetch<{ data: ProgressClaim }>(`/projects/${projectId}/progress-claims/${id}/reject`, {
      method: "POST",
      body: JSON.stringify({ comment }),
    }),
  invoice: (projectId: number, id: number) =>
    apiFetch<{ data: ProgressClaim }>(`/projects/${projectId}/progress-claims/${id}/invoice`, { method: "POST" }),
};

export const paymentApi = {
  list: (projectId: number) =>
    apiFetch<PaginatedResponse<PaymentReceipt>>(`/projects/${projectId}/payment-receipts`),
  create: (projectId: number, data: { progress_claim_id?: number; amount: number; payment_date?: string; payment_method?: string; reference_no?: string; notes?: string }) =>
    apiFetch<{ data: PaymentReceipt }>(`/projects/${projectId}/payment-receipts`, { method: "POST", body: JSON.stringify(data) }),
  confirm: (projectId: number, id: number) =>
    apiFetch<{ data: PaymentReceipt }>(`/projects/${projectId}/payment-receipts/${id}/confirm`, { method: "POST" }),
};

export const disbursementApi = {
  list: (projectId: number) =>
    apiFetch<PaginatedResponse<CashDisbursement>>(`/projects/${projectId}/cash-disbursements`),
  create: (projectId: number, data: { amount: number; payee?: string; description?: string; disbursement_date?: string; notes?: string }) =>
    apiFetch<{ data: CashDisbursement }>(`/projects/${projectId}/cash-disbursements`, { method: "POST", body: JSON.stringify(data) }),
  confirm: (projectId: number, id: number) =>
    apiFetch<{ data: CashDisbursement }>(`/projects/${projectId}/cash-disbursements/${id}/confirm`, { method: "POST" }),
};

export const financeApi = {
  summary: (projectId: number) =>
    apiFetch<{ data: FinanceSummary }>(`/projects/${projectId}/finance/summary`),
  cashFlow: (projectId: number) =>
    apiFetch<{ data: CashFlowPoint[] }>(`/projects/${projectId}/finance/cash-flow`),
};

export interface VariationOrderItem {
  id?: number;
  description: string;
  cost_code_id?: number | null;
  cost_code?: string | null;
  uom_code?: string | null;
  quantity: number;
  unit_price: number;
  amount?: number;
}

export interface VariationOrder {
  id: number;
  document_number: string | null;
  vo_number: string | null;
  title: string;
  description: string | null;
  vo_type: "addition" | "omission" | "modification";
  status: string;
  total_amount: number;
  signed_amount: number;
  reason: string | null;
  notes: string | null;
  is_editable: boolean;
  items?: VariationOrderItem[];
  items_count?: number;
  contract?: { id: number; contract_number: string; contract_value: number };
  creator?: { id: number; name: string };
  approved_at: string | null;
  rejection_reason: string | null;
  created_at: string;
}

export interface VoSummary {
  total_vos: number;
  total_additions: number;
  total_omissions: number;
  net_variation: number;
  pending_count: number;
}

export const voApi = {
  list: (projectId: number, params?: { status?: string; vo_type?: string }) => {
    const qs = params ? "?" + new URLSearchParams(params as Record<string, string>).toString() : "";
    return apiFetch<PaginatedResponse<VariationOrder> & { summary: VoSummary }>(
      `/projects/${projectId}/variation-orders${qs}`,
    );
  },
  get: (projectId: number, id: number) =>
    apiFetch<{ data: VariationOrder }>(`/projects/${projectId}/variation-orders/${id}`),
  summary: (projectId: number) =>
    apiFetch<{ data: VoSummary }>(`/projects/${projectId}/variation-orders/summary`),
  create: (projectId: number, data: {
    title: string;
    description?: string;
    vo_type?: string;
    vo_number?: string;
    reason?: string;
    notes?: string;
    items: VariationOrderItem[];
  }) =>
    apiFetch<{ data: VariationOrder }>(`/projects/${projectId}/variation-orders`, {
      method: "POST",
      body: JSON.stringify(data),
    }),
  update: (projectId: number, id: number, data: Partial<{
    title: string;
    description?: string;
    vo_type?: string;
    reason?: string;
    notes?: string;
    items: VariationOrderItem[];
  }>) =>
    apiFetch<{ data: VariationOrder }>(`/projects/${projectId}/variation-orders/${id}`, {
      method: "PUT",
      body: JSON.stringify(data),
    }),
  submit: (projectId: number, id: number) =>
    apiFetch<{ data: VariationOrder }>(`/projects/${projectId}/variation-orders/${id}/submit`, { method: "POST" }),
  approve: (projectId: number, id: number) =>
    apiFetch<{ data: VariationOrder }>(`/projects/${projectId}/variation-orders/${id}/approve`, { method: "POST" }),
  reject: (projectId: number, id: number, comment: string) =>
    apiFetch<{ data: VariationOrder }>(`/projects/${projectId}/variation-orders/${id}/reject`, {
      method: "POST",
      body: JSON.stringify({ comment }),
    }),
};

export interface DailyReportItem {
  id?: number;
  item_type: "material" | "labor" | "equipment";
  description: string;
  cost_code_id?: number | null;
  cost_code?: string | null;
  uom_code?: string | null;
  quantity: number;
  unit_cost: number;
  amount?: number;
  notes?: string | null;
}

export interface DailyReport {
  id: number;
  document_number: string | null;
  report_date: string;
  weather: string | null;
  workforce_count: number;
  summary: string | null;
  status: string;
  total_amount: number;
  notes: string | null;
  is_editable: boolean;
  items?: DailyReportItem[];
  items_count?: number;
  creator?: { id: number; name: string };
  approved_at: string | null;
  rejection_reason: string | null;
  created_at: string;
}

export interface DailyReportSummary {
  total_reports: number;
  total_workforce: number;
  total_cost: number;
  pending_count: number;
}

export const dailyReportApi = {
  list: (projectId: number) =>
    apiFetch<PaginatedResponse<DailyReport> & { summary: DailyReportSummary }>(
      `/projects/${projectId}/daily-reports`,
    ),
  create: (projectId: number, data: {
    report_date: string;
    weather?: string;
    workforce_count?: number;
    summary?: string;
    notes?: string;
    items: DailyReportItem[];
  }) =>
    apiFetch<{ data: DailyReport }>(`/projects/${projectId}/daily-reports`, {
      method: "POST",
      body: JSON.stringify(data),
    }),
  submit: (projectId: number, id: number) =>
    apiFetch<{ data: DailyReport }>(`/projects/${projectId}/daily-reports/${id}/submit`, { method: "POST" }),
  approve: (projectId: number, id: number) =>
    apiFetch<{ data: DailyReport }>(`/projects/${projectId}/daily-reports/${id}/approve`, { method: "POST" }),
  reject: (projectId: number, id: number, comment: string) =>
    apiFetch<{ data: DailyReport }>(`/projects/${projectId}/daily-reports/${id}/reject`, {
      method: "POST",
      body: JSON.stringify({ comment }),
    }),
};

export interface ApprovalItem {
  type: string;
  id: number;
  project_id: number;
  project_code: string | null;
  project_name: string | null;
  document_number: string | null;
  title: string | null;
  amount: number;
  submitted_at: string | null;
  approve_permission: string;
  href: string;
}

export const approvalApi = {
  pending: (type?: string) => {
    const qs = type ? `?type=${type}` : "";
    return apiFetch<{ data: ApprovalItem[]; meta: { total: number } }>(`/approvals/pending${qs}`);
  },
  count: () =>
    apiFetch<{ data: { total: number; by_type: Record<string, number> } }>("/approvals/count"),
};

export interface ReportType {
  type: string;
  label: string;
  requires_project: boolean;
  permission: string;
}

export const reportApi = {
  list: () => apiFetch<{ data: ReportType[] }>("/reports"),
  download: (type: string, params?: Record<string, string>) => {
    const qs = params ? "?" + new URLSearchParams(params).toString() : "";
    return apiDownload(`/reports/${type}/download${qs}`);
  },
};

export interface Role {
  id: number;
  name: string;
  label: string;
  description: string | null;
}

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  position: string | null;
  is_active: boolean;
  roles: { id: number; name: string; label: string }[];
  created_at: string;
}

export const usersApi = {
  list: (params?: Record<string, string>) => {
    const qs = params ? "?" + new URLSearchParams(params).toString() : "";
    return apiFetch<PaginatedResponse<AdminUser>>(`/admin/users${qs}`);
  },
  create: (data: {
    name: string;
    email: string;
    password: string;
    phone?: string;
    position?: string;
    is_active?: boolean;
    role_ids: number[];
  }) =>
    apiFetch<{ message: string; data: AdminUser }>("/admin/users", {
      method: "POST",
      body: JSON.stringify(data),
    }),
  update: (id: number, data: Partial<{
    name: string;
    email: string;
    password: string;
    phone: string;
    position: string;
    is_active: boolean;
    role_ids: number[];
  }>) =>
    apiFetch<{ message: string; data: AdminUser }>(`/admin/users/${id}`, {
      method: "PUT",
      body: JSON.stringify(data),
    }),
  delete: (id: number) =>
    apiFetch<{ message: string }>(`/admin/users/${id}`, { method: "DELETE" }),
  roles: () => apiFetch<{ data: Role[] }>("/admin/roles"),
};
