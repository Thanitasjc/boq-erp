"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import Header from "@/components/layout/Header";
import { VariationOrder, VoSummary, voApi } from "@/lib/api";
import { formatMoney, statusColor, statusLabel, voTypeLabel } from "@/lib/utils";
import { useAuth } from "@/contexts/AuthContext";

const emptyItem = { description: "", cost_code: "", uom_code: "LS", quantity: 1, unit_price: 0 };

function KpiCard({ label, value, accent }: { label: string; value: string; accent?: string }) {
  return (
    <div className="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
      <p className="text-xs text-zinc-500">{label}</p>
      <p className={`mt-1 text-lg font-bold ${accent || ""}`}>{value}</p>
    </div>
  );
}

export default function VoPage() {
  const params = useParams();
  const projectId = Number(params.id);
  const { hasPermission } = useAuth();
  const [vos, setVos] = useState<VariationOrder[]>([]);
  const [summary, setSummary] = useState<VoSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);
  const [rejectId, setRejectId] = useState<number | null>(null);
  const [rejectComment, setRejectComment] = useState("");
  const [form, setForm] = useState({
    title: "",
    description: "",
    vo_type: "addition" as "addition" | "omission" | "modification",
    reason: "",
    items: [{ ...emptyItem }],
  });

  const load = () => {
    voApi.list(projectId).then((res) => {
      setVos(res.data);
      setSummary(res.summary);
    }).finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, [projectId]);

  const handleCreate = async () => {
    setSaving(true);
    try {
      await voApi.create(projectId, form);
      setShowForm(false);
      setForm({ title: "", description: "", vo_type: "addition", reason: "", items: [{ ...emptyItem }] });
      load();
    } finally {
      setSaving(false);
    }
  };

  const handleAction = async (id: number, action: "submit" | "approve") => {
    if (action === "submit") await voApi.submit(projectId, id);
    else await voApi.approve(projectId, id);
    load();
  };

  const handleReject = async () => {
    if (!rejectId || !rejectComment.trim()) return;
    await voApi.reject(projectId, rejectId, rejectComment);
    setRejectId(null);
    setRejectComment("");
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
        title="VO — ใบสั่งเปลี่ยนแปลงงาน"
        subtitle={`โครงการ #${projectId}`}
        actions={
          <div className="flex gap-2">
            <Link href={`/projects/${projectId}/dashboard`} className="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50">
              ← แดชบอร์ด
            </Link>
            {hasPermission("vo.create") && (
              <button onClick={() => setShowForm(true)} className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900">
                + สร้าง VO
              </button>
            )}
          </div>
        }
      />
      <div className="flex-1 overflow-y-auto p-6">
        {summary && (
          <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <KpiCard label="VO อนุมัติแล้ว" value={String(summary.total_vos)} />
            <KpiCard label="เพิ่มงาน" value={formatMoney(summary.total_additions)} accent="text-green-600" />
            <KpiCard label="ลดงาน" value={formatMoney(summary.total_omissions)} accent="text-red-600" />
            <KpiCard label="สุทธิเปลี่ยนแปลง" value={formatMoney(summary.net_variation)} accent="text-amber-600" />
            <KpiCard label="รออนุมัติ" value={String(summary.pending_count)} accent="text-blue-600" />
          </div>
        )}

        {loading ? (
          <div className="flex justify-center py-12">
            <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
          </div>
        ) : vos.length === 0 ? (
          <div className="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
            <p className="text-zinc-500">ยังไม่มีใบสั่งเปลี่ยนแปลงงาน (VO)</p>
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-zinc-50 text-left text-xs uppercase tracking-wider text-zinc-500">
                  <th className="px-5 py-3">เลขที่</th>
                  <th className="px-5 py-3">หัวข้อ</th>
                  <th className="px-5 py-3">ประเภท</th>
                  <th className="px-5 py-3">สถานะ</th>
                  <th className="px-5 py-3 text-right">มูลค่า</th>
                  <th className="px-5 py-3 text-right">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                {vos.map((vo) => (
                  <tr key={vo.id} className="border-b hover:bg-zinc-50">
                    <td className="px-5 py-3 font-mono text-xs">{vo.document_number}</td>
                    <td className="px-5 py-3 font-medium">{vo.title}</td>
                    <td className="px-5 py-3">
                      <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-xs">{voTypeLabel(vo.vo_type)}</span>
                    </td>
                    <td className="px-5 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColor(vo.status)}`}>
                        {statusLabel(vo.status)}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-right">
                      <span className={vo.signed_amount < 0 ? "text-red-600" : ""}>
                        {formatMoney(vo.signed_amount)}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-right space-x-2">
                      {vo.status === "draft" && (
                        <button onClick={() => handleAction(vo.id, "submit")} className="text-blue-600 hover:underline text-xs">
                          ส่งอนุมัติ
                        </button>
                      )}
                      {vo.status === "submitted" && hasPermission("vo.approve") && (
                        <>
                          <button onClick={() => handleAction(vo.id, "approve")} className="text-green-600 hover:underline text-xs">
                            อนุมัติ
                          </button>
                          <button onClick={() => setRejectId(vo.id)} className="text-red-600 hover:underline text-xs">
                            ปฏิเสธ
                          </button>
                        </>
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
              <h3 className="text-lg font-bold">สร้าง VO — ใบสั่งเปลี่ยนแปลงงาน</h3>
              <div className="mt-4 space-y-3">
                <input
                  placeholder="หัวข้อ"
                  value={form.title}
                  onChange={(e) => setForm({ ...form, title: e.target.value })}
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                />
                <textarea
                  placeholder="รายละเอียด"
                  value={form.description}
                  onChange={(e) => setForm({ ...form, description: e.target.value })}
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                  rows={2}
                />
                <select
                  value={form.vo_type}
                  onChange={(e) => setForm({ ...form, vo_type: e.target.value as typeof form.vo_type })}
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                >
                  <option value="addition">เพิ่มงาน (Addition)</option>
                  <option value="omission">ลดงาน (Omission)</option>
                  <option value="modification">แก้ไขงาน (Modification)</option>
                </select>
                <input
                  placeholder="เหตุผล"
                  value={form.reason}
                  onChange={(e) => setForm({ ...form, reason: e.target.value })}
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                />
                <div className="border-t pt-3">
                  <p className="mb-2 text-sm font-medium">รายการ</p>
                  {form.items.map((item, idx) => (
                    <div key={idx} className="mb-2 grid grid-cols-12 gap-2">
                      <input
                        placeholder="รายละเอียด"
                        value={item.description}
                        onChange={(e) => updateItem(idx, "description", e.target.value)}
                        className="col-span-5 rounded border px-2 py-1 text-sm"
                      />
                      <input
                        placeholder="Cost Code"
                        value={item.cost_code}
                        onChange={(e) => updateItem(idx, "cost_code", e.target.value)}
                        className="col-span-2 rounded border px-2 py-1 text-sm"
                      />
                      <input
                        type="number"
                        placeholder="จำนวน"
                        value={item.quantity}
                        onChange={(e) => updateItem(idx, "quantity", Number(e.target.value))}
                        className="col-span-2 rounded border px-2 py-1 text-sm"
                      />
                      <input
                        type="number"
                        placeholder="ราคา/หน่วย"
                        value={item.unit_price}
                        onChange={(e) => updateItem(idx, "unit_price", Number(e.target.value))}
                        className="col-span-3 rounded border px-2 py-1 text-sm"
                      />
                    </div>
                  ))}
                  <button
                    type="button"
                    onClick={() => setForm({ ...form, items: [...form.items, { ...emptyItem }] })}
                    className="text-xs text-amber-600 hover:underline"
                  >
                    + เพิ่มรายการ
                  </button>
                </div>
              </div>
              <div className="mt-6 flex justify-end gap-2">
                <button onClick={() => setShowForm(false)} className="rounded-lg border px-4 py-2 text-sm">ยกเลิก</button>
                <button
                  onClick={handleCreate}
                  disabled={saving || !form.title}
                  className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold disabled:opacity-50"
                >
                  {saving ? "กำลังบันทึก..." : "บันทึก"}
                </button>
              </div>
            </div>
          </div>
        )}

        {rejectId && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
              <h3 className="text-lg font-bold">ปฏิเสธ VO</h3>
              <textarea
                placeholder="เหตุผลในการปฏิเสธ"
                value={rejectComment}
                onChange={(e) => setRejectComment(e.target.value)}
                className="mt-3 w-full rounded-lg border px-3 py-2 text-sm"
                rows={3}
              />
              <div className="mt-4 flex justify-end gap-2">
                <button onClick={() => { setRejectId(null); setRejectComment(""); }} className="rounded-lg border px-4 py-2 text-sm">ยกเลิก</button>
                <button onClick={handleReject} disabled={!rejectComment.trim()} className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                  ปฏิเสธ
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
