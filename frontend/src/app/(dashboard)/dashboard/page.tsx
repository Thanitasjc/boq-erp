"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import {
  Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart,
  ResponsiveContainer, Tooltip, XAxis, YAxis,
} from "recharts";
import PageHeader from "@/components/layout/PageHeader";
import FilterBar, { FilterSelect } from "@/components/layout/FilterBar";
import { CompanyDashboard, dashboardApi } from "@/lib/api";
import { formatMoney, formatThaiDate, statusColor, statusLabel } from "@/lib/utils";

const STATUS_COLORS: Record<string, string> = {
  active: "#22c55e",
  planning: "#3b82f6",
  on_hold: "#eab308",
  completed: "#a1a1aa",
  cancelled: "#ef4444",
};

function KpiCard({ label, value, sub, accent }: { label: string; value: string; sub?: string; accent?: string }) {
  return (
    <div className="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
      <p className="text-xs font-medium uppercase tracking-wider text-zinc-500">{label}</p>
      <p className={`mt-2 text-2xl font-bold ${accent || "text-zinc-900"}`}>{value}</p>
      {sub && <p className="mt-1 text-xs text-zinc-400">{sub}</p>}
    </div>
  );
}

export default function DashboardPage() {
  const [data, setData] = useState<CompanyDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [year, setYear] = useState("2569");
  const [status, setStatus] = useState("");
  const [pmId, setPmId] = useState("");
  const printRef = useRef<HTMLDivElement>(null);

  const buildParams = useCallback(() => {
    const params: Record<string, string> = { year };
    if (status) params.status = status;
    if (pmId) params.project_manager_id = pmId;
    return params;
  }, [year, status, pmId]);

  const load = useCallback(() => {
    setLoading(true);
    dashboardApi
      .company(buildParams())
      .then(setData)
      .catch(() => setError("ไม่สามารถโหลดข้อมูลแดชบอร์ดได้"))
      .finally(() => setLoading(false));
  }, [buildParams]);

  useEffect(() => { load(); }, [load]);

  const handleClear = () => {
    setYear("2569");
    setStatus("");
    setPmId("");
    setLoading(true);
    dashboardApi
      .company({ year: "2569" })
      .then(setData)
      .finally(() => setLoading(false));
  };

  const handleExportExcel = async () => {
    const blob = await dashboardApi.exportExcel(buildParams());
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `dashboard-${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const handleExportPdf = () => {
    window.print();
  };

  if (loading && !data) {
    return (
      <div className="flex flex-1 items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="flex flex-1 items-center justify-center">
        <p className="text-sm text-red-500">{error || "ไม่มีข้อมูล"}</p>
      </div>
    );
  }

  const { kpis, projects, chart_data, status_chart } = data;
  const managers = data.filters?.project_managers ?? [];
  const barData = (chart_data ?? []).map((p) => ({
    name: p.code,
    งบประมาณ: p.budget,
    ผูกพัน: p.committed,
    จริง: p.actual,
  }));
  const pieData = (status_chart ?? Object.entries(data.projects_by_status ?? {}).map(([status, count]) => ({
    status, label: statusLabel(status), count,
  }))).map((s) => ({
    name: statusLabel(s.status),
    value: s.count,
    fill: STATUS_COLORS[s.status] || "#a1a1aa",
  }));

  return (
    <>
      <PageHeader
        breadcrumbs={[{ label: "หน้าแรก", href: "/dashboard" }, { label: "Company Dashboard" }]}
        title="ภาพรวมทุกโครงการ"
        badge="Management View"
        meta={`ข้อมูล ณ ${formatThaiDate()} สกุลเงิน THB ปีงบประมาณ ${year}`}
        actions={
          <>
            <button onClick={handleExportExcel} className="flex items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm hover:bg-zinc-50 print:hidden">
              Excel
            </button>
            <button onClick={handleExportPdf} className="flex items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm hover:bg-zinc-50 print:hidden">
              PDF
            </button>
            <Link href="/projects?new=1" className="flex items-center gap-1.5 rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900 hover:bg-amber-500 print:hidden">
              + โครงการใหม่
            </Link>
          </>
        }
      />

      <FilterBar onSearch={load} onClear={handleClear}>
        <FilterSelect value={year} onChange={setYear}>
          {(data.filters?.years ?? [{ value: "2569", label: "ปี 2569 (2026)" }]).map((y) => (
            <option key={y.value} value={y.value}>{y.label}</option>
          ))}
        </FilterSelect>
        <FilterSelect value={status} onChange={setStatus}>
          <option value="">ทุกสถานะ</option>
          <option value="active">ดำเนินการ</option>
          <option value="planning">วางแผน</option>
          <option value="on_hold">พักงาน</option>
          <option value="completed">เสร็จสิ้น</option>
        </FilterSelect>
        <FilterSelect value={pmId} onChange={setPmId}>
          <option value="">Project Manager ทั้งหมด</option>
          {managers.map((m) => (
            <option key={m.id} value={String(m.id)}>{m.name}</option>
          ))}
        </FilterSelect>
      </FilterBar>

      <div ref={printRef} className="flex-1 overflow-y-auto p-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard label="โครงการทั้งหมด" value={String(kpis.total_projects)} sub={`${kpis.active_projects} ดำเนินการ · ${kpis.planning_projects} วางแผน`} />
          <KpiCard label="มูลค่าสัญญา" value={formatMoney(kpis.contract_value)} accent="text-amber-600" />
          <KpiCard label="งบประมาณปัจจุบัน" value={formatMoney(kpis.revised_budget)} />
          <KpiCard label="กำไรคาดการณ์" value={formatMoney(kpis.forecast_profit)} sub={`อัตรากำไร ${kpis.profit_margin.toFixed(1)}%`} accent="text-green-600" />
        </div>

        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard label="ต้นทุนผูกพัน" value={formatMoney(kpis.committed_cost)} />
          <KpiCard label="ต้นทุนจริง" value={formatMoney(kpis.actual_cost)} />
          <KpiCard label="งบคงเหลือ" value={formatMoney(kpis.remaining_budget)} />
          <KpiCard label="ต้นทุนคาดการณ์" value={formatMoney(kpis.forecast_cost)} />
        </div>

        {barData.length > 0 && (
          <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div className="lg:col-span-2 rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
              <h2 className="mb-4 font-semibold text-zinc-900">งบประมาณ vs ต้นทุน ต่อโครงการ</h2>
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={barData}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                  <YAxis tickFormatter={(v) => `${(Number(v) / 1_000_000).toFixed(0)}M`} />
                  <Tooltip formatter={(v) => formatMoney(Number(v ?? 0))} />
                  <Legend />
                  <Bar dataKey="งบประมาณ" fill="#f59e0b" />
                  <Bar dataKey="ผูกพัน" fill="#8b5cf6" />
                  <Bar dataKey="จริง" fill="#ef4444" />
                </BarChart>
              </ResponsiveContainer>
            </div>
            <div className="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
              <h2 className="mb-4 font-semibold text-zinc-900">สถานะโครงการ</h2>
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie data={pieData} dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius={90} label={({ name, value }) => `${name} (${value})`}>
                    {pieData.map((entry, i) => (
                      <Cell key={i} fill={entry.fill} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            </div>
          </div>
        )}

        <div className="mt-6 rounded-xl border border-zinc-200 bg-white shadow-sm">
          <div className="border-b border-zinc-200 px-5 py-4">
            <h2 className="font-semibold text-zinc-900">โครงการ ({projects.length})</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-zinc-100 bg-zinc-50 text-left text-xs uppercase tracking-wider text-zinc-500">
                  <th className="px-5 py-3">รหัส</th>
                  <th className="px-5 py-3">โครงการ</th>
                  <th className="px-5 py-3">PM</th>
                  <th className="px-5 py-3">สถานะ</th>
                  <th className="px-5 py-3 text-right">มูลค่าสัญญา</th>
                  <th className="px-5 py-3 text-right">งบประมาณ</th>
                </tr>
              </thead>
              <tbody>
                {projects.map((p) => (
                  <tr key={p.id} className="border-b border-zinc-50 hover:bg-zinc-50">
                    <td className="px-5 py-3 font-mono text-xs">
                      <Link href={`/projects/${p.id}/dashboard`} className="text-amber-600 hover:underline">{p.code}</Link>
                    </td>
                    <td className="px-5 py-3 font-medium">{p.name}</td>
                    <td className="px-5 py-3 text-zinc-500">{p.project_manager?.name || "—"}</td>
                    <td className="px-5 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColor(p.status)}`}>{statusLabel(p.status)}</span>
                    </td>
                    <td className="px-5 py-3 text-right">{formatMoney(p.contract_value)}</td>
                    <td className="px-5 py-3 text-right">{formatMoney(p.revised_budget)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </>
  );
}
