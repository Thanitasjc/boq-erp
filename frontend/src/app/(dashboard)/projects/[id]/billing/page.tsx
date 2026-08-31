"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from "recharts";
import Header from "@/components/layout/Header";
import {
  CashDisbursement, CashFlowPoint, FinanceSummary,
  PaymentReceipt, ProgressClaim,
  claimApi, disbursementApi, financeApi, paymentApi,
} from "@/lib/api";
import { formatMoney, statusColor, statusLabel } from "@/lib/utils";
import { useAuth } from "@/contexts/AuthContext";

type Tab = "claims" | "payments" | "disbursements" | "cashflow";

function KpiCard({ label, value, accent }: { label: string; value: string; accent?: string }) {
  return (
    <div className="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
      <p className="text-xs text-zinc-500">{label}</p>
      <p className={`mt-1 text-lg font-bold ${accent || ""}`}>{value}</p>
    </div>
  );
}

export default function BillingPage() {
  const params = useParams();
  const projectId = Number(params.id);
  const { hasPermission } = useAuth();
  const [tab, setTab] = useState<Tab>("claims");
  const [claims, setClaims] = useState<ProgressClaim[]>([]);
  const [payments, setPayments] = useState<PaymentReceipt[]>([]);
  const [disbursements, setDisbursements] = useState<CashDisbursement[]>([]);
  const [summary, setSummary] = useState<FinanceSummary | null>(null);
  const [cashFlow, setCashFlow] = useState<CashFlowPoint[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [showClaimForm, setShowClaimForm] = useState(false);
  const [showPaymentForm, setShowPaymentForm] = useState(false);
  const [showDisbForm, setShowDisbForm] = useState(false);
  const [claimForm, setClaimForm] = useState({ title: "", progress_percent: 25, notes: "" });
  const [paymentForm, setPaymentForm] = useState({ progress_claim_id: 0, amount: 0, payment_method: "transfer", reference_no: "" });
  const [disbForm, setDisbForm] = useState({ amount: 0, payee: "", description: "" });

  const load = () => {
    if (!projectId || Number.isNaN(projectId)) return;
    setLoading(true);
    setError("");
    Promise.all([
      claimApi.list(projectId),
      paymentApi.list(projectId),
      disbursementApi.list(projectId),
      financeApi.summary(projectId),
      financeApi.cashFlow(projectId),
    ])
      .then(([c, p, d, s, cf]) => {
        setClaims(c.data);
        setPayments(p.data);
        setDisbursements(d.data);
        setSummary(s.data);
        setCashFlow(cf.data);
      })
      .catch((e) => {
        setClaims([]);
        setPayments([]);
        setDisbursements([]);
        setSummary(null);
        setCashFlow([]);
        setError(
          e instanceof Error
            ? e.message
            : "โหลดข้อมูลการเงินไม่สำเร็จ — กรุณาตรวจสอบว่า login แล้วและ API ทำงานอยู่",
        );
      })
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, [projectId]);

  const handleCreateClaim = async () => {
    await claimApi.create(projectId, claimForm);
    setShowClaimForm(false);
    load();
  };

  const handleCreatePayment = async () => {
    await paymentApi.create(projectId, {
      ...paymentForm,
      progress_claim_id: paymentForm.progress_claim_id || undefined,
    });
    setShowPaymentForm(false);
    load();
  };

  const handleCreateDisb = async () => {
    await disbursementApi.create(projectId, disbForm);
    setShowDisbForm(false);
    load();
  };

  const claimAction = async (id: number, action: "submit" | "approve" | "invoice") => {
    if (action === "submit") await claimApi.submit(projectId, id);
    else if (action === "approve") await claimApi.approve(projectId, id);
    else await claimApi.invoice(projectId, id);
    load();
  };

  const tabs: { key: Tab; label: string }[] = [
    { key: "claims", label: "เคลมงาน" },
    { key: "payments", label: "รับเงิน" },
    { key: "disbursements", label: "จ่ายเงิน" },
    { key: "cashflow", label: "Cash Flow" },
  ];

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <Header
        title="การเงิน — เคลม & วางบิล"
        subtitle={`โครงการ #${projectId}`}
        actions={
          <Link href={`/projects/${projectId}/dashboard`} className="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50">
            ← แดชบอร์ด
          </Link>
        }
      />
      <div className="min-h-0 flex-1 overflow-y-auto p-6">
        {error && (
          <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {error}
            <p className="mt-1 text-xs text-red-600">
              ถ้าเปิดใน Chrome แล้วไม่เห็นข้อมูล ให้ login ใหม่ที่{" "}
              <strong>http://localhost:3000/login</strong> (อย่าใช้ 127.0.0.1)
            </p>
          </div>
        )}
        {summary && (
          <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
            <KpiCard label="วางบิลแล้ว" value={formatMoney(summary.billing)} accent="text-amber-600" />
            <KpiCard label="รับเงิน" value={formatMoney(summary.cash_in ?? 0)} accent="text-green-600" />
            <KpiCard label="จ่ายเงิน" value={formatMoney(summary.cash_out ?? 0)} accent="text-red-600" />
            <KpiCard label="กำไร" value={formatMoney(summary.profit)} />
            <KpiCard label="Margin" value={`${summary.margin ?? 0}%`} />
            <KpiCard label="รออนุมัติ" value={formatMoney(summary.pending_claims)} />
          </div>
        )}

        <div className="mb-4 flex flex-wrap gap-2 border-b border-zinc-200 pb-2">
          {tabs.map((t) => (
            <button key={t.key} onClick={() => setTab(t.key)}
              className={`rounded-lg px-4 py-2 text-sm font-medium ${tab === t.key ? "bg-amber-400 text-zinc-900" : "text-zinc-600 hover:bg-zinc-100"}`}>
              {t.label}
            </button>
          ))}
          {hasPermission("finance.create") && tab === "claims" && (
            <button onClick={() => setShowClaimForm(true)} className="ml-auto rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900">+ สร้างเคลม</button>
          )}
          {hasPermission("finance.create") && tab === "payments" && (
            <button onClick={() => setShowPaymentForm(true)} className="ml-auto rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900">+ บันทึกรับเงิน</button>
          )}
          {hasPermission("finance.create") && tab === "disbursements" && (
            <button onClick={() => setShowDisbForm(true)} className="ml-auto rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900">+ บันทึกจ่ายเงิน</button>
          )}
        </div>

        {loading ? (
          <div className="flex justify-center py-12">
            <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
          </div>
        ) : tab === "claims" ? (
          claims.length === 0 ? (
            <div className="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
              <p className="text-zinc-500">ยังไม่มีเคลมงาน</p>
            </div>
          ) : (
          <div className="overflow-hidden rounded-xl border bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-zinc-50 text-left text-xs uppercase text-zinc-500">
                  <th className="px-5 py-3">เลขที่</th>
                  <th className="px-5 py-3">หัวข้อ</th>
                  <th className="px-5 py-3">%</th>
                  <th className="px-5 py-3 text-right">ก่อนหัก</th>
                  <th className="px-5 py-3 text-right">Retention</th>
                  <th className="px-5 py-3 text-right">สุทธิ</th>
                  <th className="px-5 py-3">สถานะ</th>
                  <th className="px-5 py-3 text-right">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                {claims.map((c) => (
                  <tr key={c.id} className="border-b hover:bg-zinc-50">
                    <td className="px-5 py-3 font-mono text-xs">{c.document_number}</td>
                    <td className="px-5 py-3 font-medium">{c.title}</td>
                    <td className="px-5 py-3">{c.progress_percent}%</td>
                    <td className="px-5 py-3 text-right">{formatMoney(c.gross_amount)}</td>
                    <td className="px-5 py-3 text-right text-zinc-500">{formatMoney(c.retention_amount)}</td>
                    <td className="px-5 py-3 text-right font-medium">{formatMoney(c.net_amount)}</td>
                    <td className="px-5 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs ${statusColor(c.status)}`}>{statusLabel(c.status)}</span>
                    </td>
                    <td className="px-5 py-3 text-right space-x-2">
                      {c.status === "draft" && <button onClick={() => claimAction(c.id, "submit")} className="text-blue-600 text-xs hover:underline">ส่งอนุมัติ</button>}
                      {c.status === "submitted" && hasPermission("finance.approve") && <button onClick={() => claimAction(c.id, "approve")} className="text-green-600 text-xs hover:underline">อนุมัติ</button>}
                      {c.status === "approved" && <button onClick={() => claimAction(c.id, "invoice")} className="text-amber-600 text-xs hover:underline">ออก Invoice</button>}
                      {["approved", "invoiced"].includes(c.status) && (
                        <button onClick={() => { setPaymentForm({ ...paymentForm, progress_claim_id: c.id, amount: c.net_amount }); setTab("payments"); setShowPaymentForm(true); }}
                          className="text-green-600 text-xs hover:underline">รับเงิน</button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          )
        ) : tab === "payments" ? (
          payments.length === 0 ? (
            <div className="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
              <p className="text-zinc-500">ยังไม่มีรายการรับเงิน</p>
            </div>
          ) : (
          <div className="overflow-hidden rounded-xl border bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-zinc-50 text-left text-xs uppercase text-zinc-500">
                  <th className="px-5 py-3">เลขที่</th>
                  <th className="px-5 py-3">วันที่</th>
                  <th className="px-5 py-3">เคลม</th>
                  <th className="px-5 py-3 text-right">จำนวน</th>
                  <th className="px-5 py-3">สถานะ</th>
                  <th className="px-5 py-3 text-right">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                {payments.map((p) => (
                  <tr key={p.id} className="border-b hover:bg-zinc-50">
                    <td className="px-5 py-3 font-mono text-xs">{p.document_number}</td>
                    <td className="px-5 py-3">{p.payment_date}</td>
                    <td className="px-5 py-3">{p.progress_claim?.document_number || "—"}</td>
                    <td className="px-5 py-3 text-right font-medium">{formatMoney(p.amount)}</td>
                    <td className="px-5 py-3"><span className={`rounded-full px-2 py-0.5 text-xs ${statusColor(p.status)}`}>{statusLabel(p.status)}</span></td>
                    <td className="px-5 py-3 text-right">
                      {p.status === "draft" && hasPermission("finance.create") && (
                        <button onClick={async () => { await paymentApi.confirm(projectId, p.id); load(); }} className="text-green-600 text-xs hover:underline">ยืนยันรับเงิน</button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          )
        ) : tab === "disbursements" ? (
          disbursements.length === 0 ? (
            <div className="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
              <p className="text-zinc-500">ยังไม่มีรายการจ่ายเงิน</p>
            </div>
          ) : (
          <div className="overflow-hidden rounded-xl border bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-zinc-50 text-left text-xs uppercase text-zinc-500">
                  <th className="px-5 py-3">เลขที่</th>
                  <th className="px-5 py-3">วันที่</th>
                  <th className="px-5 py-3">ผู้รับ</th>
                  <th className="px-5 py-3 text-right">จำนวน</th>
                  <th className="px-5 py-3">สถานะ</th>
                  <th className="px-5 py-3 text-right">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                {disbursements.map((d) => (
                  <tr key={d.id} className="border-b hover:bg-zinc-50">
                    <td className="px-5 py-3 font-mono text-xs">{d.document_number}</td>
                    <td className="px-5 py-3">{d.disbursement_date}</td>
                    <td className="px-5 py-3">{d.payee || "—"}</td>
                    <td className="px-5 py-3 text-right font-medium">{formatMoney(d.amount)}</td>
                    <td className="px-5 py-3"><span className={`rounded-full px-2 py-0.5 text-xs ${statusColor(d.status)}`}>{statusLabel(d.status)}</span></td>
                    <td className="px-5 py-3 text-right">
                      {d.status === "draft" && hasPermission("finance.create") && (
                        <button onClick={async () => { await disbursementApi.confirm(projectId, d.id); load(); }} className="text-red-600 text-xs hover:underline">ยืนยันจ่ายเงิน</button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          )
        ) : (
          <div className="rounded-xl border bg-white p-5 shadow-sm">
            <h3 className="mb-4 font-semibold">Cash Flow รายเดือน</h3>
            {cashFlow.length === 0 ? (
              <p className="text-center text-zinc-500 py-8">ยังไม่มีข้อมูล Cash Flow</p>
            ) : (
              <ResponsiveContainer width="100%" height={320}>
                <BarChart data={cashFlow}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="period" tick={{ fontSize: 11 }} />
                  <YAxis tick={{ fontSize: 11 }} />
                  <Tooltip formatter={(v) => formatMoney(Number(v ?? 0))} />
                  <Legend />
                  <Bar dataKey="cash_in" name="รับเงิน" fill="#22c55e" />
                  <Bar dataKey="cash_out" name="จ่ายเงิน" fill="#ef4444" />
                  <Bar dataKey="billing" name="วางบิล" fill="#f59e0b" />
                </BarChart>
              </ResponsiveContainer>
            )}
          </div>
        )}

        {showClaimForm && (
          <Modal title="สร้างเคลมงาน" onClose={() => setShowClaimForm(false)} onSave={handleCreateClaim}>
            <input placeholder="หัวข้อ" value={claimForm.title} onChange={(e) => setClaimForm({ ...claimForm, title: e.target.value })} className="w-full rounded-lg border px-3 py-2 text-sm" />
            <input type="number" min={1} max={100} placeholder="% ความคืบหน้าสะสม" value={claimForm.progress_percent}
              onChange={(e) => setClaimForm({ ...claimForm, progress_percent: Number(e.target.value) })} className="w-full rounded-lg border px-3 py-2 text-sm" />
            <p className="text-xs text-zinc-500">ระบบคำนวณ gross, retention และ net อัตโนมัติจากสัญญา</p>
          </Modal>
        )}

        {showPaymentForm && (
          <Modal title="บันทึกรับเงิน" onClose={() => setShowPaymentForm(false)} onSave={handleCreatePayment}>
            <select value={paymentForm.progress_claim_id} onChange={(e) => setPaymentForm({ ...paymentForm, progress_claim_id: Number(e.target.value) })} className="w-full rounded-lg border px-3 py-2 text-sm">
              <option value={0}>เลือกเคลม (ถ้ามี)</option>
              {claims.filter((c) => ["approved", "invoiced"].includes(c.status)).map((c) => (
                <option key={c.id} value={c.id}>{c.document_number} — {formatMoney(c.net_amount)}</option>
              ))}
            </select>
            <input type="number" placeholder="จำนวนเงิน" value={paymentForm.amount || ""} onChange={(e) => setPaymentForm({ ...paymentForm, amount: Number(e.target.value) })} className="w-full rounded-lg border px-3 py-2 text-sm" />
            <input placeholder="เลขอ้างอิง" value={paymentForm.reference_no} onChange={(e) => setPaymentForm({ ...paymentForm, reference_no: e.target.value })} className="w-full rounded-lg border px-3 py-2 text-sm" />
          </Modal>
        )}

        {showDisbForm && (
          <Modal title="บันทึกจ่ายเงิน" onClose={() => setShowDisbForm(false)} onSave={handleCreateDisb}>
            <input type="number" placeholder="จำนวนเงิน" value={disbForm.amount || ""} onChange={(e) => setDisbForm({ ...disbForm, amount: Number(e.target.value) })} className="w-full rounded-lg border px-3 py-2 text-sm" />
            <input placeholder="ผู้รับเงิน" value={disbForm.payee} onChange={(e) => setDisbForm({ ...disbForm, payee: e.target.value })} className="w-full rounded-lg border px-3 py-2 text-sm" />
            <input placeholder="รายละเอียด" value={disbForm.description} onChange={(e) => setDisbForm({ ...disbForm, description: e.target.value })} className="w-full rounded-lg border px-3 py-2 text-sm" />
          </Modal>
        )}
      </div>
    </div>
  );
}

function Modal({ title, children, onClose, onSave }: { title: string; children: React.ReactNode; onClose: () => void; onSave: () => void }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
        <h3 className="text-lg font-bold">{title}</h3>
        <div className="mt-4 space-y-3">{children}</div>
        <div className="mt-6 flex justify-end gap-2">
          <button onClick={onClose} className="rounded-lg border px-4 py-2 text-sm">ยกเลิก</button>
          <button onClick={onSave} className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold">บันทึก</button>
        </div>
      </div>
    </div>
  );
}
