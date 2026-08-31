"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import Header from "@/components/layout/Header";
import { Budget, budgetApi } from "@/lib/api";
import { formatMoney } from "@/lib/utils";
import { useAuth } from "@/contexts/AuthContext";

function statusBadge(status: string) {
  const s: Record<string, string> = {
    draft: "bg-zinc-100 text-zinc-700",
    submitted: "bg-blue-100 text-blue-800",
    approved: "bg-green-100 text-green-800",
    rejected: "bg-red-100 text-red-800",
  };
  return s[status] || "bg-zinc-100";
}

export default function BudgetListPage() {
  const params = useParams();
  const projectId = Number(params.id);
  const { hasPermission } = useAuth();
  const [budgets, setBudgets] = useState<Budget[]>([]);
  const [boqVersions, setBoqVersions] = useState<{ id: number; version_number: string; title: string; total_amount: number }[]>([]);
  const [loading, setLoading] = useState(true);
  const [showGenerate, setShowGenerate] = useState(false);
  const [generating, setGenerating] = useState(false);
  const [form, setForm] = useState({ boq_version_id: 0, contingency_percent: 5, markup_percent: 10, title: "" });

  const load = () => {
    Promise.all([
      budgetApi.list(projectId),
      budgetApi.approvedBoqVersions(projectId),
    ])
      .then(([budgetRes, boqRes]) => {
        setBudgets(budgetRes.data);
        setBoqVersions(boqRes.data);
        if (boqRes.data.length > 0) {
          setForm((f) => ({ ...f, boq_version_id: boqRes.data[0].id }));
        }
      })
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    load();
  }, [projectId]);

  const handleGenerate = async () => {
    setGenerating(true);
    try {
      await budgetApi.generate(projectId, form);
      setShowGenerate(false);
      load();
    } finally {
      setGenerating(false);
    }
  };

  return (
    <>
      <Header
        title="Budget"
        subtitle={`Project #${projectId}`}
        actions={
          <div className="flex gap-2">
            <Link href="/projects" className="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50">← Projects</Link>
            {hasPermission("budget.create") && boqVersions.length > 0 && (
              <button onClick={() => setShowGenerate(true)}
                className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900">
                + Generate from BOQ
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
        ) : budgets.length === 0 ? (
          <div className="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
            <p className="text-zinc-500">No budgets yet.</p>
            {boqVersions.length === 0 ? (
              <p className="mt-2 text-sm text-zinc-400">Approve a BOQ version first.</p>
            ) : (
              <button onClick={() => setShowGenerate(true)}
                className="mt-4 rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold">
                Generate from Approved BOQ
              </button>
            )}
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-zinc-50 text-left text-xs uppercase tracking-wider text-zinc-500">
                  <th className="px-5 py-3">Doc No</th>
                  <th className="px-5 py-3">Version</th>
                  <th className="px-5 py-3">Title</th>
                  <th className="px-5 py-3">Status</th>
                  <th className="px-5 py-3">Baseline</th>
                  <th className="px-5 py-3 text-right">Total</th>
                  <th className="px-5 py-3"></th>
                </tr>
              </thead>
              <tbody>
                {budgets.map((b) => (
                  <tr key={b.id} className="border-b hover:bg-zinc-50">
                    <td className="px-5 py-3 font-mono text-xs">{b.document_number}</td>
                    <td className="px-5 py-3">v{b.version_number}</td>
                    <td className="px-5 py-3">{b.title}</td>
                    <td className="px-5 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusBadge(b.status)}`}>{b.status}</span>
                    </td>
                    <td className="px-5 py-3">{b.is_baseline ? "✓" : "—"}</td>
                    <td className="px-5 py-3 text-right font-medium">{formatMoney(b.total_amount)}</td>
                    <td className="px-5 py-3 text-right">
                      <Link href={`/projects/${projectId}/budget/${b.id}`} className="font-medium text-amber-600 hover:text-amber-700">
                        Open →
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {showGenerate && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <h3 className="mb-4 text-lg font-bold">Generate Budget from BOQ</h3>
            <div className="space-y-4">
              <div>
                <label className="mb-1 block text-sm font-medium">BOQ Version</label>
                <select value={form.boq_version_id} onChange={(e) => setForm({ ...form, boq_version_id: Number(e.target.value) })}
                  className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm">
                  {boqVersions.map((v) => (
                    <option key={v.id} value={v.id}>v{v.version_number} — {v.title} ({formatMoney(v.total_amount)})</option>
                  ))}
                </select>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="mb-1 block text-sm font-medium">Contingency %</label>
                  <input type="number" value={form.contingency_percent}
                    onChange={(e) => setForm({ ...form, contingency_percent: Number(e.target.value) })}
                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
                </div>
                <div>
                  <label className="mb-1 block text-sm font-medium">Markup %</label>
                  <input type="number" value={form.markup_percent}
                    onChange={(e) => setForm({ ...form, markup_percent: Number(e.target.value) })}
                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
                </div>
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <button onClick={() => setShowGenerate(false)} className="rounded-lg border px-4 py-2 text-sm">Cancel</button>
                <button onClick={handleGenerate} disabled={generating}
                  className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold disabled:opacity-50">
                  {generating ? "Generating..." : "Generate"}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
