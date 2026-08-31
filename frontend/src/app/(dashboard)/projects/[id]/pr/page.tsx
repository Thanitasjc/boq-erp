"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import Header from "@/components/layout/Header";
import { PurchaseRequest, prApi } from "@/lib/api";
import { formatMoney, statusColor, statusLabel } from "@/lib/utils";
import { useAuth } from "@/contexts/AuthContext";

const emptyItem = { description: "", cost_code: "", uom_code: "EA", quantity: 1, unit_price: 0 };

export default function PrListPage() {
  const params = useParams();
  const projectId = Number(params.id);
  const { hasPermission } = useAuth();
  const [prs, setPrs] = useState<PurchaseRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ title: "", description: "", required_date: "", items: [{ ...emptyItem }] });

  const load = () => {
    prApi.list(projectId).then((res) => setPrs(res.data)).finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, [projectId]);

  const handleCreate = async () => {
    setSaving(true);
    try {
      await prApi.create(projectId, form);
      setShowForm(false);
      setForm({ title: "", description: "", required_date: "", items: [{ ...emptyItem }] });
      load();
    } finally {
      setSaving(false);
    }
  };

  const handleAction = async (id: number, action: "submit" | "approve", comment?: string) => {
    if (action === "submit") await prApi.submit(projectId, id);
    else await prApi.approve(projectId, id);
    load();
  };

  return (
    <>
      <Header
        title="ใบขอซื้อ (PR)"
        subtitle={`โครงการ #${projectId}`}
        actions={
          <div className="flex gap-2">
            <Link href="/projects" className="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50">← โครงการ</Link>
            {hasPermission("procurement.create") && (
              <button onClick={() => setShowForm(true)} className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900">
                + สร้าง PR
              </button>
            )}
          </div>
        }
      />
      <div className="flex-1 overflow-y-auto p-6">
        {loading ? (
          <div className="flex justify-center py-12">
            <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
          </div>
        ) : prs.length === 0 ? (
          <div className="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
            <p className="text-zinc-500">ยังไม่มีใบขอซื้อ</p>
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-zinc-50 text-left text-xs uppercase tracking-wider text-zinc-500">
                  <th className="px-5 py-3">เลขที่</th>
                  <th className="px-5 py-3">หัวข้อ</th>
                  <th className="px-5 py-3">สถานะ</th>
                  <th className="px-5 py-3 text-right">มูลค่า</th>
                  <th className="px-5 py-3 text-right">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                {prs.map((pr) => (
                  <tr key={pr.id} className="border-b hover:bg-zinc-50">
                    <td className="px-5 py-3 font-mono text-xs">{pr.document_number}</td>
                    <td className="px-5 py-3 font-medium">{pr.title}</td>
                    <td className="px-5 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColor(pr.status)}`}>
                        {statusLabel(pr.status)}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-right">{formatMoney(pr.total_amount)}</td>
                    <td className="px-5 py-3 text-right space-x-2">
                      {pr.status === "draft" && (
                        <button onClick={() => handleAction(pr.id, "submit")} className="text-blue-600 hover:underline text-xs">ส่งอนุมัติ</button>
                      )}
                      {pr.status === "submitted" && hasPermission("procurement.approve") && (
                        <button onClick={() => handleAction(pr.id, "approve")} className="text-green-600 hover:underline text-xs">อนุมัติ</button>
                      )}
                      {pr.status === "approved" && (
                        <Link href={`/projects/${projectId}/po?pr=${pr.id}`} className="text-amber-600 hover:underline text-xs">สร้าง PO</Link>
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
            <div className="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
              <h3 className="text-lg font-bold">สร้างใบขอซื้อ (PR)</h3>
              <div className="mt-4 space-y-3">
                <input placeholder="หัวข้อ" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })}
                  className="w-full rounded-lg border px-3 py-2 text-sm" />
                <input type="date" value={form.required_date} onChange={(e) => setForm({ ...form, required_date: e.target.value })}
                  className="w-full rounded-lg border px-3 py-2 text-sm" />
                {form.items.map((item, i) => (
                  <div key={i} className="grid grid-cols-5 gap-2">
                    <input placeholder="รายละเอียด" value={item.description}
                      onChange={(e) => { const items = [...form.items]; items[i].description = e.target.value; setForm({ ...form, items }); }}
                      className="col-span-2 rounded border px-2 py-1 text-sm" />
                    <input placeholder="รหัสต้นทุน" value={item.cost_code}
                      onChange={(e) => { const items = [...form.items]; items[i].cost_code = e.target.value; setForm({ ...form, items }); }}
                      className="rounded border px-2 py-1 text-sm" />
                    <input type="number" placeholder="จำนวน" value={item.quantity}
                      onChange={(e) => { const items = [...form.items]; items[i].quantity = Number(e.target.value); setForm({ ...form, items }); }}
                      className="rounded border px-2 py-1 text-sm" />
                    <input type="number" placeholder="ราคา/หน่วย" value={item.unit_price}
                      onChange={(e) => { const items = [...form.items]; items[i].unit_price = Number(e.target.value); setForm({ ...form, items }); }}
                      className="rounded border px-2 py-1 text-sm" />
                  </div>
                ))}
                <button onClick={() => setForm({ ...form, items: [...form.items, { ...emptyItem }] })}
                  className="text-sm text-amber-600">+ เพิ่มรายการ</button>
              </div>
              <div className="mt-6 flex justify-end gap-2">
                <button onClick={() => setShowForm(false)} className="rounded-lg border px-4 py-2 text-sm">ยกเลิก</button>
                <button onClick={handleCreate} disabled={saving || !form.title}
                  className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold disabled:opacity-50">
                  {saving ? "กำลังบันทึก..." : "บันทึก"}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
