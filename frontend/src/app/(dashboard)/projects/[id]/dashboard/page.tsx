"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import {
  LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from "recharts";
import Header from "@/components/layout/Header";
import { ProjectDashboard, progressApi } from "@/lib/api";
import { formatMoney } from "@/lib/utils";

function KpiCard({ label, value, sub, accent }: { label: string; value: string; sub?: string; accent?: string }) {
  return (
    <div className="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
      <p className="text-xs font-medium text-zinc-500">{label}</p>
      <p className={`mt-1 text-xl font-bold ${accent || "text-zinc-900"}`}>{value}</p>
      {sub && <p className="mt-0.5 text-xs text-zinc-400">{sub}</p>}
    </div>
  );
}

export default function ProjectDashboardPage() {
  const params = useParams();
  const projectId = Number(params.id);
  const [data, setData] = useState<ProjectDashboard | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    progressApi.dashboard(projectId)
      .then((res) => setData(res.data))
      .finally(() => setLoading(false));
  }, [projectId]);

  if (loading || !data) {
    return (
      <div className="flex flex-1 items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
      </div>
    );
  }

  const { project, kpis, scurve } = data;
  const chartData = scurve.map((p) => ({
    name: p.label,
    แผน: p.planned_percent,
    จริง: p.actual_percent ?? 0,
    PV: p.planned_value / 1000000,
    EV: (p.earned_value ?? 0) / 1000000,
    AC: p.actual_cost / 1000000,
  }));

  return (
    <>
      <Header
        title={`แดชบอร์ดโครงการ — ${project.name}`}
        subtitle={`${project.code} · ความคืบหน้า ${kpis.actual_progress}% / แผน ${kpis.planned_progress}%`}
        actions={
          <div className="flex gap-2">
            <Link href="/dashboard" className="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50">← Company</Link>
            <Link href={`/projects/${projectId}/progress`} className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900">บันทึกความคืบหน้า</Link>
          </div>
        }
      />
      <div className="flex-1 overflow-y-auto p-6">
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
          <KpiCard label="มูลค่าสัญญา" value={formatMoney(kpis.contract_value)} accent="text-amber-600" />
          <KpiCard label="งบประมาณ" value={formatMoney(kpis.budget)} />
          <KpiCard label="ผูกพัน" value={formatMoney(kpis.committed)} />
          <KpiCard label="จริง (AC)" value={formatMoney(kpis.actual)} />
          <KpiCard label="คงเหลือ" value={formatMoney(kpis.remaining)} />
          <KpiCard label="กำไร" value={formatMoney(kpis.profit)} accent="text-green-600" />
        </div>

        <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <KpiCard label="PV" value={formatMoney(kpis.pv)} />
          <KpiCard label="EV" value={formatMoney(kpis.ev)} />
          <KpiCard label="AC" value={formatMoney(kpis.ac)} />
          <KpiCard label="SPI" value={kpis.spi.toFixed(2)} accent={kpis.spi < 0.9 ? "text-red-600" : "text-green-600"} sub={kpis.spi < 0.9 ? "ล่าช้า" : "ตามแผน"} />
          <KpiCard label="CPI" value={kpis.cpi.toFixed(2)} accent={kpis.cpi < 1 ? "text-red-600" : "text-green-600"} />
          <KpiCard label="EAC" value={formatMoney(kpis.eac)} />
        </div>

        <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
          <div className="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <h3 className="mb-4 font-semibold text-zinc-900">S-Curve — % ความคืบหน้า</h3>
            <ResponsiveContainer width="100%" height={280}>
              <LineChart data={chartData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e4e4e7" />
                <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                <YAxis tick={{ fontSize: 11 }} unit="%" />
                <Tooltip />
                <Legend />
                <Line type="monotone" dataKey="แผน" stroke="#f59e0b" strokeWidth={2} dot={false} />
                <Line type="monotone" dataKey="จริง" stroke="#3b82f6" strokeWidth={2} dot={false} />
              </LineChart>
            </ResponsiveContainer>
          </div>

          <div className="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <h3 className="mb-4 font-semibold text-zinc-900">S-Curve — มูลค่า (ล้านบาท)</h3>
            <ResponsiveContainer width="100%" height={280}>
              <LineChart data={chartData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e4e4e7" />
                <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                <YAxis tick={{ fontSize: 11 }} />
                <Tooltip />
                <Legend />
                <Line type="monotone" dataKey="PV" stroke="#f59e0b" strokeWidth={2} dot={false} name="PV (แผน)" />
                <Line type="monotone" dataKey="EV" stroke="#22c55e" strokeWidth={2} dot={false} name="EV (มูลค่า)" />
                <Line type="monotone" dataKey="AC" stroke="#ef4444" strokeWidth={2} dot={false} name="AC (จริง)" />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="mt-6 flex flex-wrap gap-2">
          <Link href={`/projects/${projectId}/boq`} className="rounded-lg border px-3 py-1.5 text-sm hover:bg-zinc-50">BOQ</Link>
          <Link href={`/projects/${projectId}/budget`} className="rounded-lg border px-3 py-1.5 text-sm hover:bg-zinc-50">งบประมาณ</Link>
          <Link href={`/projects/${projectId}/pr`} className="rounded-lg border px-3 py-1.5 text-sm hover:bg-zinc-50">PR</Link>
          <Link href={`/projects/${projectId}/po`} className="rounded-lg border px-3 py-1.5 text-sm hover:bg-zinc-50">PO</Link>
          <Link href={`/projects/${projectId}/billing`} className="rounded-lg border px-3 py-1.5 text-sm hover:bg-zinc-50">การเงิน</Link>
          <Link href={`/projects/${projectId}/vo`} className="rounded-lg border px-3 py-1.5 text-sm hover:bg-zinc-50">VO</Link>
          <Link href={`/projects/${projectId}/daily-report`} className="rounded-lg border px-3 py-1.5 text-sm hover:bg-zinc-50">หน้างาน</Link>
        </div>
      </div>
    </>
  );
}
