"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import Header from "@/components/layout/Header";
import { ProgressEntry, progressApi } from "@/lib/api";
import { formatMoney } from "@/lib/utils";

export default function ProgressPage() {
  const params = useParams();
  const projectId = Number(params.id);
  const [entries, setEntries] = useState<ProgressEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ period_month: new Date().toISOString().slice(0, 7) + "-01", actual_percent: 0, notes: "" });

  const load = () => {
    progressApi.list(projectId).then((res) => setEntries(res.data)).finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, [projectId]);

  const handleSubmit = async () => {
    await progressApi.create(projectId, form);
    setShowForm(false);
    load();
  };

  const handleGenerateBaseline = async () => {
    await progressApi.generateBaseline(projectId);
    load();
  };

  return (
    <>
      <Header
        title="บันทึกความคืบหน้างาน"
        subtitle={`โครงการ #${projectId}`}
        actions={
          <div className="flex gap-2">
            <Link href={`/projects/${projectId}/dashboard`} className="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50">← แดชบอร์ด</Link>
            <button onClick={handleGenerateBaseline} className="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50">สร้าง Baseline</button>
            <button onClick={() => setShowForm(true)} className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900">+ บันทึกความคืบหน้า</button>
          </div>
        }
      />
      <div className="flex-1 overflow-y-auto p-6">
        {loading ? (
          <div className="flex justify-center py-12">
            <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
          </div>
        ) : entries.length === 0 ? (
          <div className="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
            <p className="text-zinc-500">ยังไม่มีข้อมูลความคืบหน้า</p>
            <button onClick={handleGenerateBaseline} className="mt-4 rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold">สร้าง Baseline S-Curve</button>
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-zinc-50 text-left text-xs uppercase tracking-wider text-zinc-500">
                  <th className="px-5 py-3">เดือน</th>
                  <th className="px-5 py-3 text-right">% จริง</th>
                  <th className="px-5 py-3 text-right">มูลค่า (EV)</th>
                  <th className="px-5 py-3">หมายเหตุ</th>
                  <th className="px-5 py-3">สถานะ</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((e) => (
                  <tr key={e.id} className="border-b hover:bg-zinc-50">
                    <td className="px-5 py-3">{e.period_month}</td>
                    <td className="px-5 py-3 text-right font-medium">{e.actual_percent}%</td>
                    <td className="px-5 py-3 text-right">{formatMoney(e.earned_value)}</td>
                    <td className="px-5 py-3 text-zinc-500">{e.notes || "—"}</td>
                    <td className="px-5 py-3">{e.status}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {showForm && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
              <h3 className="text-lg font-bold">บันทึกความคืบหน้า</h3>
              <div className="mt-4 space-y-3">
                <input type="month" value={form.period_month.slice(0, 7)}
                  onChange={(e) => setForm({ ...form, period_month: e.target.value + "-01" })}
                  className="w-full rounded-lg border px-3 py-2 text-sm" />
                <input type="number" min={0} max={100} placeholder="% ความคืบหน้าสะสม"
                  value={form.actual_percent}
                  onChange={(e) => setForm({ ...form, actual_percent: Number(e.target.value) })}
                  className="w-full rounded-lg border px-3 py-2 text-sm" />
                <textarea placeholder="หมายเหตุ" value={form.notes}
                  onChange={(e) => setForm({ ...form, notes: e.target.value })}
                  className="w-full rounded-lg border px-3 py-2 text-sm" rows={2} />
              </div>
              <div className="mt-6 flex justify-end gap-2">
                <button onClick={() => setShowForm(false)} className="rounded-lg border px-4 py-2 text-sm">ยกเลิก</button>
                <button onClick={handleSubmit} className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold">บันทึก</button>
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
