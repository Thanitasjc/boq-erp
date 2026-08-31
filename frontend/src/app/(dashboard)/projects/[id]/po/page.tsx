"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import Header from "@/components/layout/Header";
import { PurchaseOrder, mastersApi, poApi, prApi } from "@/lib/api";
import { formatMoney, statusColor, statusLabel } from "@/lib/utils";
import { useAuth } from "@/contexts/AuthContext";

export default function PoListPage() {
  const params = useParams();
  const searchParams = useSearchParams();
  const projectId = Number(params.id);
  const { hasPermission } = useAuth();
  const [pos, setPos] = useState<PurchaseOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [suppliers, setSuppliers] = useState<{ id: number; name: string }[]>([]);
  const [form, setForm] = useState({ purchase_request_id: 0, supplier_id: 0, title: "" });

  const load = () => {
    poApi.list(projectId).then((res) => setPos(res.data)).finally(() => setLoading(false));
  };

  useEffect(() => {
    load();
    mastersApi.suppliers.list().then((res) => setSuppliers(res.data));
    const prId = searchParams.get("pr");
    if (prId) {
      setForm((f) => ({ ...f, purchase_request_id: Number(prId) }));
      setShowForm(true);
    }
  }, [projectId, searchParams]);

  const handleCreate = async () => {
    await poApi.create(projectId, {
      purchase_request_id: form.purchase_request_id || undefined,
      supplier_id: form.supplier_id,
      title: form.title || "ใบสั่งซื้อ",
    });
    setShowForm(false);
    load();
  };

  const handleAction = async (id: number, action: "submit" | "approve" | "issue") => {
    if (action === "submit") await poApi.submit(projectId, id);
    else if (action === "approve") await poApi.approve(projectId, id);
    else await poApi.issue(projectId, id);
    load();
  };

  return (
    <>
      <Header
        title="ใบสั่งซื้อ (PO)"
        subtitle={`โครงการ #${projectId}`}
        actions={
          <div className="flex gap-2">
            <Link href={`/projects/${projectId}/pr`} className="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50">← PR</Link>
            {hasPermission("procurement.create") && (
              <button onClick={() => setShowForm(true)} className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900">
                + สร้าง PO
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
        ) : pos.length === 0 ? (
          <div className="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
            <p className="text-zinc-500">ยังไม่มีใบสั่งซื้อ</p>
            <p className="mt-2 text-sm text-zinc-400">อนุมัติ PR ก่อน แล้วสร้าง PO จาก PR</p>
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-zinc-50 text-left text-xs uppercase tracking-wider text-zinc-500">
                  <th className="px-5 py-3">เลขที่</th>
                  <th className="px-5 py-3">หัวข้อ</th>
                  <th className="px-5 py-3">ผู้ขาย</th>
                  <th className="px-5 py-3">สถานะ</th>
                  <th className="px-5 py-3 text-right">มูลค่า</th>
                  <th className="px-5 py-3 text-right">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                {pos.map((po) => (
                  <tr key={po.id} className="border-b hover:bg-zinc-50">
                    <td className="px-5 py-3 font-mono text-xs">{po.document_number}</td>
                    <td className="px-5 py-3 font-medium">{po.title}</td>
                    <td className="px-5 py-3 text-zinc-500">{po.supplier?.name || "—"}</td>
                    <td className="px-5 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColor(po.status)}`}>
                        {statusLabel(po.status)}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-right">{formatMoney(po.total_amount)}</td>
                    <td className="px-5 py-3 text-right space-x-2">
                      {po.status === "draft" && (
                        <button onClick={() => handleAction(po.id, "submit")} className="text-blue-600 hover:underline text-xs">ส่งอนุมัติ</button>
                      )}
                      {po.status === "submitted" && hasPermission("procurement.approve") && (
                        <>
                          <button onClick={() => handleAction(po.id, "approve")} className="text-green-600 hover:underline text-xs">อนุมัติ</button>
                          <button onClick={() => handleAction(po.id, "issue")} className="text-amber-600 hover:underline text-xs">ออก PO</button>
                        </>
                      )}
                      {po.status === "approved" && hasPermission("procurement.approve") && (
                        <button onClick={() => handleAction(po.id, "issue")} className="text-amber-600 hover:underline text-xs">ออก PO</button>
                      )}
                      {["issued", "partially_received"].includes(po.status) && (
                        <Link href={`/projects/${projectId}/gr?po=${po.id}`} className="text-amber-600 hover:underline text-xs">รับสินค้า (GR)</Link>
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
            <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
              <h3 className="text-lg font-bold">สร้างใบสั่งซื้อ (PO)</h3>
              <div className="mt-4 space-y-3">
                <input placeholder="PR ID (ถ้ามี)" type="number" value={form.purchase_request_id || ""}
                  onChange={(e) => setForm({ ...form, purchase_request_id: Number(e.target.value) })}
                  className="w-full rounded-lg border px-3 py-2 text-sm" />
                <select value={form.supplier_id} onChange={(e) => setForm({ ...form, supplier_id: Number(e.target.value) })}
                  className="w-full rounded-lg border px-3 py-2 text-sm">
                  <option value={0}>เลือกผู้ขาย</option>
                  {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                </select>
                <input placeholder="หัวข้อ" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })}
                  className="w-full rounded-lg border px-3 py-2 text-sm" />
              </div>
              <div className="mt-6 flex justify-end gap-2">
                <button onClick={() => setShowForm(false)} className="rounded-lg border px-4 py-2 text-sm">ยกเลิก</button>
                <button onClick={handleCreate} disabled={!form.supplier_id}
                  className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold disabled:opacity-50">บันทึก</button>
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
