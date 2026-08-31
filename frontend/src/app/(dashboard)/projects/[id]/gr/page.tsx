"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import Header from "@/components/layout/Header";
import { GoodsReceipt, PurchaseOrder, grApi } from "@/lib/api";
import { formatMoney, statusColor, statusLabel } from "@/lib/utils";
import { useAuth } from "@/contexts/AuthContext";

export default function GrListPage() {
  const params = useParams();
  const searchParams = useSearchParams();
  const projectId = Number(params.id);
  const { hasPermission } = useAuth();
  const [grs, setGrs] = useState<GoodsReceipt[]>([]);
  const [orders, setOrders] = useState<PurchaseOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [selectedPo, setSelectedPo] = useState<PurchaseOrder | null>(null);
  const [quantities, setQuantities] = useState<Record<number, number>>({});

  const load = () => {
    Promise.all([grApi.list(projectId), grApi.issuableOrders(projectId)])
      .then(([grRes, poRes]) => {
        setGrs(grRes.data);
        setOrders(poRes.data);
      })
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    load();
    const poId = searchParams.get("po");
    if (poId) {
      grApi.issuableOrders(projectId).then((res) => {
        const po = res.data.find((o) => o.id === Number(poId));
        if (po) { setSelectedPo(po); setShowForm(true); }
      });
    }
  }, [projectId, searchParams]);

  const openForm = (po: PurchaseOrder) => {
    setSelectedPo(po);
    const qty: Record<number, number> = {};
    po.items?.forEach((item) => { qty[item.id] = item.remaining_quantity; });
    setQuantities(qty);
    setShowForm(true);
  };

  const handleCreate = async () => {
    if (!selectedPo) return;
    const items = Object.entries(quantities)
      .filter(([, q]) => q > 0)
      .map(([id, quantity]) => ({ purchase_order_item_id: Number(id), quantity }));

    await grApi.create(projectId, { purchase_order_id: selectedPo.id, items });
    setShowForm(false);
    load();
  };

  const handleConfirm = async (id: number) => {
    await grApi.confirm(projectId, id);
    load();
  };

  return (
    <>
      <Header
        title="ใบรับสินค้า (GR)"
        subtitle={`โครงการ #${projectId}`}
        actions={
          <Link href={`/projects/${projectId}/po`} className="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50">
            ← PO
          </Link>
        }
      />
      <div className="flex-1 overflow-y-auto p-6">
        {orders.length > 0 && hasPermission("procurement.create") && (
          <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p className="text-sm font-medium text-amber-800">PO ที่พร้อมรับสินค้า</p>
            <div className="mt-2 flex flex-wrap gap-2">
              {orders.map((po) => (
                <button key={po.id} onClick={() => openForm(po)}
                  className="rounded-lg bg-white px-3 py-1.5 text-sm border hover:bg-amber-100">
                  {po.document_number} — {formatMoney(po.total_amount)}
                </button>
              ))}
            </div>
          </div>
        )}

        {loading ? (
          <div className="flex justify-center py-12">
            <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
          </div>
        ) : grs.length === 0 ? (
          <div className="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
            <p className="text-zinc-500">ยังไม่มีใบรับสินค้า</p>
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-zinc-50 text-left text-xs uppercase tracking-wider text-zinc-500">
                  <th className="px-5 py-3">เลขที่</th>
                  <th className="px-5 py-3">PO</th>
                  <th className="px-5 py-3">วันที่รับ</th>
                  <th className="px-5 py-3">สถานะ</th>
                  <th className="px-5 py-3 text-right">มูลค่า</th>
                  <th className="px-5 py-3 text-right">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                {grs.map((gr) => (
                  <tr key={gr.id} className="border-b hover:bg-zinc-50">
                    <td className="px-5 py-3 font-mono text-xs">{gr.document_number}</td>
                    <td className="px-5 py-3">{gr.purchase_order?.document_number}</td>
                    <td className="px-5 py-3">{gr.receipt_date}</td>
                    <td className="px-5 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColor(gr.status)}`}>
                        {statusLabel(gr.status)}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-right">{formatMoney(gr.total_amount)}</td>
                    <td className="px-5 py-3 text-right">
                      {gr.status === "draft" && hasPermission("procurement.create") && (
                        <button onClick={() => handleConfirm(gr.id)} className="text-green-600 hover:underline text-xs">ยืนยันรับสินค้า</button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {showForm && selectedPo && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
              <h3 className="text-lg font-bold">รับสินค้า — {selectedPo.document_number}</h3>
              <div className="mt-4 space-y-2">
                {selectedPo.items?.map((item) => (
                  <div key={item.id} className="flex items-center justify-between gap-2 text-sm">
                    <span className="flex-1 truncate">{item.description}</span>
                    <span className="text-zinc-400 text-xs">คงเหลือ {item.remaining_quantity}</span>
                    <input type="number" min={0} max={item.remaining_quantity}
                      value={quantities[item.id] ?? 0}
                      onChange={(e) => setQuantities({ ...quantities, [item.id]: Number(e.target.value) })}
                      className="w-20 rounded border px-2 py-1 text-sm" />
                  </div>
                ))}
              </div>
              <div className="mt-6 flex justify-end gap-2">
                <button onClick={() => setShowForm(false)} className="rounded-lg border px-4 py-2 text-sm">ยกเลิก</button>
                <button onClick={handleCreate} className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold">บันทึก GR</button>
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
