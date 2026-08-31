"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import Header from "@/components/layout/Header";
import {
  ApprovalItem, approvalApi,
  boqApi, budgetApi, claimApi, dailyReportApi,
  poApi, prApi, voApi,
} from "@/lib/api";
import { approvalTypeLabel, formatMoney } from "@/lib/utils";
import { useAuth } from "@/contexts/AuthContext";

export default function ApprovalsPage() {
  const { hasPermission } = useAuth();
  const [items, setItems] = useState<ApprovalItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState("");
  const [rejectItem, setRejectItem] = useState<ApprovalItem | null>(null);
  const [rejectComment, setRejectComment] = useState("");

  const load = () => {
    approvalApi.pending(filter || undefined).then((res) => setItems(res.data)).finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, [filter]);

  const approve = async (item: ApprovalItem) => {
    const pid = item.project_id;
    const id = item.id;
    switch (item.type) {
      case "boq": await boqApi.approve(pid, id); break;
      case "budget": await budgetApi.approve(pid, id); break;
      case "pr": await prApi.approve(pid, id); break;
      case "po": await poApi.approve(pid, id); break;
      case "claim": await claimApi.approve(pid, id); break;
      case "vo": await voApi.approve(pid, id); break;
      case "daily_report": await dailyReportApi.approve(pid, id); break;
    }
    load();
  };

  const reject = async () => {
    if (!rejectItem || !rejectComment.trim()) return;
    const pid = rejectItem.project_id;
    const id = rejectItem.id;
    switch (rejectItem.type) {
      case "boq": await boqApi.reject(pid, id, rejectComment); break;
      case "budget": await budgetApi.reject(pid, id, rejectComment); break;
      case "pr": await prApi.reject(pid, id, rejectComment); break;
      case "po": await poApi.reject(pid, id, rejectComment); break;
      case "claim": await claimApi.reject(pid, id, rejectComment); break;
      case "vo": await voApi.reject(pid, id, rejectComment); break;
      case "daily_report": await dailyReportApi.reject(pid, id, rejectComment); break;
    }
    setRejectItem(null);
    setRejectComment("");
    load();
  };

  const filters = [
    { key: "", label: "ทั้งหมด" },
    { key: "boq", label: "BOQ" },
    { key: "budget", label: "งบประมาณ" },
    { key: "pr", label: "PR" },
    { key: "po", label: "PO" },
    { key: "claim", label: "เคลม" },
    { key: "vo", label: "VO" },
    { key: "daily_report", label: "รายงานหน้างาน" },
  ];

  return (
    <>
      <Header title="รายการรออนุมัติ" subtitle="รวมทุกโมดูลที่ส่งอนุมัติแล้ว" />
      <div className="flex-1 overflow-y-auto p-6">
        <div className="mb-4 flex flex-wrap gap-2">
          {filters.map((f) => (
            <button key={f.key} onClick={() => { setLoading(true); setFilter(f.key); }}
              className={`rounded-lg px-3 py-1.5 text-sm ${filter === f.key ? "bg-amber-400 font-semibold text-zinc-900" : "border text-zinc-600 hover:bg-zinc-50"}`}>
              {f.label}
            </button>
          ))}
        </div>

        {loading ? (
          <div className="flex justify-center py-12">
            <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
          </div>
        ) : items.length === 0 ? (
          <div className="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
            <p className="text-zinc-500">ไม่มีรายการรออนุมัติ</p>
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-zinc-50 text-left text-xs uppercase text-zinc-500">
                  <th className="px-5 py-3">ประเภท</th>
                  <th className="px-5 py-3">เลขที่</th>
                  <th className="px-5 py-3">โครงการ</th>
                  <th className="px-5 py-3">หัวข้อ</th>
                  <th className="px-5 py-3 text-right">มูลค่า</th>
                  <th className="px-5 py-3 text-right">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={`${item.type}-${item.id}`} className="border-b hover:bg-zinc-50">
                    <td className="px-5 py-3">
                      <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-xs">{approvalTypeLabel(item.type)}</span>
                    </td>
                    <td className="px-5 py-3 font-mono text-xs">{item.document_number}</td>
                    <td className="px-5 py-3">
                      <Link href={`/projects/${item.project_id}/dashboard`} className="text-amber-600 hover:underline">
                        {item.project_code}
                      </Link>
                    </td>
                    <td className="px-5 py-3 max-w-xs truncate">{item.title}</td>
                    <td className="px-5 py-3 text-right">{formatMoney(item.amount)}</td>
                    <td className="px-5 py-3 text-right space-x-2">
                      <Link href={item.href} className="text-zinc-500 hover:underline text-xs">ดู</Link>
                      {hasPermission(item.approve_permission) && (
                        <>
                          <button onClick={() => approve(item)} className="text-green-600 hover:underline text-xs">อนุมัติ</button>
                          <button onClick={() => setRejectItem(item)} className="text-red-600 hover:underline text-xs">ปฏิเสธ</button>
                        </>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {rejectItem && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
              <h3 className="text-lg font-bold">ปฏิเสธ — {approvalTypeLabel(rejectItem.type)}</h3>
              <textarea placeholder="เหตุผล" value={rejectComment} onChange={(e) => setRejectComment(e.target.value)} className="mt-3 w-full rounded-lg border px-3 py-2 text-sm" rows={3} />
              <div className="mt-4 flex justify-end gap-2">
                <button onClick={() => { setRejectItem(null); setRejectComment(""); }} className="rounded-lg border px-4 py-2 text-sm">ยกเลิก</button>
                <button onClick={reject} disabled={!rejectComment.trim()} className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">ปฏิเสธ</button>
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
