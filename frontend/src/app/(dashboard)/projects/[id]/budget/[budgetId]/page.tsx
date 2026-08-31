"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import Header from "@/components/layout/Header";
import { Budget, LedgerSummary, apiDownload, budgetApi } from "@/lib/api";
import { formatMoney } from "@/lib/utils";
import { useAuth } from "@/contexts/AuthContext";

export default function BudgetDetailPage() {
  const params = useParams();
  const projectId = Number(params.id);
  const budgetId = Number(params.budgetId);
  const { hasPermission } = useAuth();
  const [budget, setBudget] = useState<Budget | null>(null);
  const [ledger, setLedger] = useState<LedgerSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);

  const load = useCallback(() => {
    budgetApi.get(projectId, budgetId).then((res) => {
      setBudget(res.data);
      setLedger(res.ledger_summary);
    }).finally(() => setLoading(false));
  }, [projectId, budgetId]);

  useEffect(() => { load(); }, [load]);

  const handleSubmit = async () => {
    setActionLoading(true);
    try { await budgetApi.submit(projectId, budgetId); load(); } finally { setActionLoading(false); }
  };

  const handleApprove = async () => {
    setActionLoading(true);
    try { await budgetApi.approve(projectId, budgetId); load(); } finally { setActionLoading(false); }
  };

  const handleReject = async () => {
    const comment = prompt("Rejection reason:");
    if (!comment) return;
    setActionLoading(true);
    try { await budgetApi.reject(projectId, budgetId, comment); load(); } finally { setActionLoading(false); }
  };

  const handleExport = async () => {
    const blob = await apiDownload(budgetApi.exportUrl(projectId, budgetId));
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Budget_v${budget?.version_number}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
  };

  if (loading) return (
    <div className="flex flex-1 items-center justify-center">
      <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
    </div>
  );

  if (!budget) return <div className="flex flex-1 items-center justify-center text-red-500">Budget not found</div>;

  return (
    <>
      <Header
        title={`Budget v${budget.version_number}`}
        subtitle={budget.title}
        actions={
          <div className="flex flex-wrap gap-2">
            <Link href={`/projects/${projectId}/budget`} className="rounded-lg border px-3 py-2 text-sm hover:bg-zinc-50">← Back</Link>
            <button onClick={handleExport} className="rounded-lg border px-3 py-2 text-sm hover:bg-zinc-50">Export Excel</button>
            {budget.is_editable && budget.status === "draft" && (
              <button onClick={handleSubmit} disabled={actionLoading}
                className="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50">Submit</button>
            )}
            {budget.status === "submitted" && hasPermission("budget.approve") && (
              <>
                <button onClick={handleApprove} disabled={actionLoading}
                  className="rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white">Approve</button>
                <button onClick={handleReject} disabled={actionLoading}
                  className="rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white">Reject</button>
              </>
            )}
          </div>
        }
      />
      <div className="flex-1 overflow-y-auto p-6">
        <div className="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
          {[
            { label: "BOQ Total", value: formatMoney(budget.boq_total) },
            { label: `Contingency (${budget.contingency_percent}%)`, value: formatMoney(budget.contingency_amount) },
            { label: `Markup (${budget.markup_percent}%)`, value: formatMoney(budget.markup_amount) },
            { label: "Budget Total", value: formatMoney(budget.total_amount), accent: true },
            { label: "Status", value: budget.status, capitalize: true },
            { label: "Baseline", value: budget.is_baseline ? "Locked" : "—" },
          ].map((k) => (
            <div key={k.label} className="rounded-xl border border-zinc-200 bg-white p-4">
              <p className="text-xs text-zinc-500">{k.label}</p>
              <p className={`mt-1 font-semibold capitalize ${k.accent ? "text-amber-600" : ""}`}>{k.value}</p>
            </div>
          ))}
        </div>

        {ledger && budget.is_baseline && (
          <div className="mb-4 rounded-xl border border-green-200 bg-green-50 p-4">
            <p className="mb-2 text-sm font-semibold text-green-800">Cost Ledger (Posted)</p>
            <div className="grid grid-cols-3 gap-4 text-sm">
              <div>Budget: <strong>{formatMoney(ledger.budget)}</strong></div>
              <div>Committed: <strong>{formatMoney(ledger.committed)}</strong></div>
              <div>Actual: <strong>{formatMoney(ledger.actual)}</strong></div>
            </div>
          </div>
        )}

        <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-zinc-50 text-left text-xs uppercase tracking-wider text-zinc-500">
                <th className="px-5 py-3">Cost Code</th>
                <th className="px-5 py-3">Name</th>
                <th className="px-5 py-3 text-right">BOQ Amount</th>
                <th className="px-5 py-3 text-right">Budget Amount</th>
                <th className="px-5 py-3 text-right">Committed</th>
                <th className="px-5 py-3 text-right">Actual</th>
                <th className="px-5 py-3 text-right">Remaining</th>
              </tr>
            </thead>
            <tbody>
              {(budget.lines || []).map((line) => (
                <tr key={line.id} className="border-b hover:bg-zinc-50">
                  <td className="px-5 py-3 font-mono text-xs">{line.cost_code}</td>
                  <td className="px-5 py-3">{line.cost_code_name}</td>
                  <td className="px-5 py-3 text-right">{formatMoney(line.boq_amount)}</td>
                  <td className="px-5 py-3 text-right font-medium">{formatMoney(line.budget_amount)}</td>
                  <td className="px-5 py-3 text-right">{formatMoney(line.committed_amount)}</td>
                  <td className="px-5 py-3 text-right">{formatMoney(line.actual_amount)}</td>
                  <td className="px-5 py-3 text-right">{formatMoney(line.remaining)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr className="bg-zinc-50 font-semibold">
                <td colSpan={3} className="px-5 py-3 text-right">Total</td>
                <td className="px-5 py-3 text-right text-amber-600">{formatMoney(budget.total_amount)}</td>
                <td colSpan={3}></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </>
  );
}
