"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import Header from "@/components/layout/Header";
import { DailyReport, DailyReportSummary, dailyReportApi } from "@/lib/api";
import { formatMoney, formatNumber, itemTypeLabel, statusColor, statusLabel } from "@/lib/utils";
import { useAuth } from "@/contexts/AuthContext";

const emptyItem = { item_type: "labor" as const, description: "", cost_code: "", uom_code: "EA", quantity: 1, unit_cost: 0 };

function KpiCard({ label, value, accent }: { label: string; value: string; accent?: string }) {
  return (
    <div className="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
      <p className="text-xs text-zinc-500">{label}</p>
      <p className={`mt-1 text-lg font-bold ${accent || ""}`}>{value}</p>
    </div>
  );
}

export default function DailyReportPage() {
  const params = useParams();
  const projectId = Number(params.id);
  const { hasPermission } = useAuth();
  const [reports, setReports] = useState<DailyReport[]>([]);
  const [summary, setSummary] = useState<DailyReportSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    report_date: new Date().toISOString().slice(0, 10),
    weather: "sunny",
    workforce_count: 0,
    summary: "",
    items: [{ ...emptyItem }],
  });

  const load = () => {
    dailyReportApi.list(projectId).then((res) => {
      setReports(res.data);
      setSummary(res.summary);
    }).finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, [projectId]);

  const handleCreate = async () => {
    setSaving(true);
    try {
      await dailyReportApi.create(projectId, form);
      setShowForm(false);
      setForm({ report_date: new Date().toISOString().slice(0, 10), weather: "sunny", workforce_count: 0, summary: "", items: [{ ...emptyItem }] });
      load();
    } finally {
      setSaving(false);
    }
  };

  const handleAction = async (id: number, action: "submit" | "approve") => {
    if (action === "submit") await dailyReportApi.submit(projectId, id);
    else await dailyReportApi.approve(projectId, id);
    load();
  };

  const updateItem = (idx: number, field: string, value: string | number) => {
    const items = [...form.items];
    items[idx] = { ...items[idx], [field]: value };
    setForm({ ...form, items });
  };

  return (
    <>
      <Header
        title="รายงานประจำวันหน้างาน"
        subtitle={`โครงการ #${projectId}`}
        actions={
          <div className="flex gap-2">
            <Link href={`/projects/${projectId}/dashboard`} className="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50">← แดชบอร์ด</Link>
            {hasPermission("site.create") && (
              <button onClick={() => setShowForm(true)} className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900">+ บันทึกรายงาน</button>
            )}
          </div>
        }
      />
      <div className="flex-1 overflow-y-auto p-6">
        {summary && (
          <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <KpiCard label="รายงานอนุมัติแล้ว" value={String(summary.total_reports)} />
            <KpiCard label="แรงงานรวม (คน-วัน)" value={formatNumber(summary.total_workforce)} accent="text-blue-600" />
            <KpiCard label="มูลค่ารวม" value={formatMoney(summary.total_cost)} accent="text-amber-600" />
            <KpiCard label="รออนุมัติ" value={String(summary.pending_count)} accent="text-red-600" />
          </div>
        )}

        {loading ? (
          <div className="flex justify-center py-12">
            <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
          </div>
        ) : reports.length === 0 ? (
          <div className="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
            <p className="text-zinc-500">ยังไม่มีรายงานประจำวัน</p>
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-zinc-50 text-left text-xs uppercase text-zinc-500">
                  <th className="px-5 py-3">เลขที่</th>
                  <th className="px-5 py-3">วันที่</th>
                  <th className="px-5 py-3">สรุปงาน</th>
                  <th className="px-5 py-3">แรงงาน</th>
                  <th className="px-5 py-3">สถานะ</th>
                  <th className="px-5 py-3 text-right">มูลค่า</th>
                  <th className="px-5 py-3 text-right">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                {reports.map((r) => (
                  <tr key={r.id} className="border-b hover:bg-zinc-50">
                    <td className="px-5 py-3 font-mono text-xs">{r.document_number}</td>
                    <td className="px-5 py-3">{r.report_date}</td>
                    <td className="px-5 py-3 max-w-xs truncate">{r.summary || "—"}</td>
                    <td className="px-5 py-3">{r.workforce_count} คน</td>
                    <td className="px-5 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColor(r.status)}`}>{statusLabel(r.status)}</span>
                    </td>
                    <td className="px-5 py-3 text-right">{formatMoney(r.total_amount)}</td>
                    <td className="px-5 py-3 text-right space-x-2">
                      {r.status === "draft" && (
                        <button onClick={() => handleAction(r.id, "submit")} className="text-blue-600 hover:underline text-xs">ส่งอนุมัติ</button>
                      )}
                      {r.status === "submitted" && hasPermission("site.approve") && (
                        <button onClick={() => handleAction(r.id, "approve")} className="text-green-600 hover:underline text-xs">อนุมัติ</button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {showForm && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
              <h3 className="text-lg font-bold">บันทึกรายงานประจำวัน</h3>
              <div className="mt-4 space-y-3">
                <input type="date" value={form.report_date} onChange={(e) => setForm({ ...form, report_date: e.target.value })} className="w-full rounded-lg border px-3 py-2 text-sm" />
                <select value={form.weather} onChange={(e) => setForm({ ...form, weather: e.target.value })} className="w-full rounded-lg border px-3 py-2 text-sm">
                  <option value="sunny">แดดจัด</option>
                  <option value="cloudy">มีเมฆ</option>
                  <option value="rainy">ฝนตก</option>
                </select>
                <input type="number" placeholder="จำนวนแรงงาน" value={form.workforce_count} onChange={(e) => setForm({ ...form, workforce_count: Number(e.target.value) })} className="w-full rounded-lg border px-3 py-2 text-sm" />
                <textarea placeholder="สรุปงานประจำวัน" value={form.summary} onChange={(e) => setForm({ ...form, summary: e.target.value })} className="w-full rounded-lg border px-3 py-2 text-sm" rows={2} />
                <div className="border-t pt-3">
                  <p className="mb-2 text-sm font-medium">รายการ (วัสดุ/แรงงาน/เครื่องจักร)</p>
                  {form.items.map((item, idx) => (
                    <div key={idx} className="mb-2 grid grid-cols-12 gap-2">
                      <select value={item.item_type} onChange={(e) => updateItem(idx, "item_type", e.target.value)} className="col-span-2 rounded border px-2 py-1 text-xs">
                        <option value="labor">แรงงาน</option>
                        <option value="material">วัสดุ</option>
                        <option value="equipment">เครื่องจักร</option>
                      </select>
                      <input placeholder="รายละเอียด" value={item.description} onChange={(e) => updateItem(idx, "description", e.target.value)} className="col-span-4 rounded border px-2 py-1 text-sm" />
                      <input type="number" placeholder="จำนวน" value={item.quantity} onChange={(e) => updateItem(idx, "quantity", Number(e.target.value))} className="col-span-2 rounded border px-2 py-1 text-sm" />
                      <input type="number" placeholder="ราคา" value={item.unit_cost} onChange={(e) => updateItem(idx, "unit_cost", Number(e.target.value))} className="col-span-2 rounded border px-2 py-1 text-sm" />
                      <span className="col-span-2 flex items-center text-xs text-zinc-500">{itemTypeLabel(item.item_type)}</span>
                    </div>
                  ))}
                  <button type="button" onClick={() => setForm({ ...form, items: [...form.items, { ...emptyItem }] })} className="text-xs text-amber-600 hover:underline">+ เพิ่มรายการ</button>
                </div>
              </div>
              <div className="mt-6 flex justify-end gap-2">
                <button onClick={() => setShowForm(false)} className="rounded-lg border px-4 py-2 text-sm">ยกเลิก</button>
                <button onClick={handleCreate} disabled={saving || !form.summary} className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold disabled:opacity-50">{saving ? "กำลังบันทึก..." : "บันทึก"}</button>
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
