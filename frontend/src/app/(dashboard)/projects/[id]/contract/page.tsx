"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import Header from "@/components/layout/Header";
import { Contract, contractApi } from "@/lib/api";
import { formatMoney } from "@/lib/utils";
import { useAuth } from "@/contexts/AuthContext";

export default function ContractPage() {
  const params = useParams();
  const projectId = Number(params.id);
  const { hasPermission } = useAuth();
  const [contract, setContract] = useState<Contract | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [editMode, setEditMode] = useState(false);
  const [form, setForm] = useState({
    title: "",
    contract_number: "",
    client_name: "",
    contract_value: 0,
    signed_date: "",
    start_date: "",
    end_date: "",
    retention_percent: 5,
    terms: "",
    status: "draft",
  });

  const load = () => {
    contractApi
      .get(projectId)
      .then((res) => {
        setContract(res.data);
        if (res.data) {
          setForm({
            title: res.data.title,
            contract_number: res.data.contract_number || "",
            client_name: res.data.client_name || "",
            contract_value: res.data.contract_value,
            signed_date: res.data.signed_date || "",
            start_date: res.data.start_date || "",
            end_date: res.data.end_date || "",
            retention_percent: res.data.retention_percent,
            terms: res.data.terms || "",
            status: res.data.status,
          });
        } else {
          setEditMode(true);
        }
      })
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    load();
  }, [projectId]);

  const handleSave = async () => {
    setSaving(true);
    try {
      if (contract) {
        const res = await contractApi.update(projectId, form);
        setContract(res.data);
      } else {
        const res = await contractApi.create(projectId, form);
        setContract(res.data);
      }
      setEditMode(false);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex flex-1 items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
      </div>
    );
  }

  return (
    <>
      <Header
        title="Contract"
        subtitle={`Project #${projectId}`}
        actions={
          <div className="flex gap-2">
            <Link href={`/projects`} className="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50">
              ← Projects
            </Link>
            {hasPermission("contract.edit") && !editMode && (
              <button onClick={() => setEditMode(true)} className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900">
                Edit
              </button>
            )}
          </div>
        }
      />
      <div className="flex-1 overflow-y-auto p-6">
        <div className="mx-auto max-w-2xl rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
          {editMode ? (
            <div className="space-y-4">
              <div>
                <label className="mb-1 block text-sm font-medium">Title</label>
                <input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })}
                  className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="mb-1 block text-sm font-medium">Contract No</label>
                  <input value={form.contract_number} onChange={(e) => setForm({ ...form, contract_number: e.target.value })}
                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
                </div>
                <div>
                  <label className="mb-1 block text-sm font-medium">Client</label>
                  <input value={form.client_name} onChange={(e) => setForm({ ...form, client_name: e.target.value })}
                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
                </div>
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium">Contract Value (฿)</label>
                <input type="number" value={form.contract_value} onChange={(e) => setForm({ ...form, contract_value: Number(e.target.value) })}
                  className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
              </div>
              <div className="grid grid-cols-3 gap-4">
                <div>
                  <label className="mb-1 block text-sm font-medium">Signed Date</label>
                  <input type="date" value={form.signed_date} onChange={(e) => setForm({ ...form, signed_date: e.target.value })}
                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
                </div>
                <div>
                  <label className="mb-1 block text-sm font-medium">Start</label>
                  <input type="date" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })}
                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
                </div>
                <div>
                  <label className="mb-1 block text-sm font-medium">End</label>
                  <input type="date" value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })}
                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
                </div>
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium">Retention %</label>
                <input type="number" value={form.retention_percent} onChange={(e) => setForm({ ...form, retention_percent: Number(e.target.value) })}
                  className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
              </div>
              <div className="flex gap-2 pt-2">
                <button onClick={handleSave} disabled={saving}
                  className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold disabled:opacity-50">
                  {saving ? "Saving..." : "Save"}
                </button>
                {contract && (
                  <button onClick={() => setEditMode(false)} className="rounded-lg border px-4 py-2 text-sm">
                    Cancel
                  </button>
                )}
              </div>
            </div>
          ) : contract ? (
            <dl className="space-y-4">
              <div className="flex justify-between border-b pb-4">
                <div>
                  <dt className="text-xs text-zinc-500">Document No</dt>
                  <dd className="font-mono text-sm">{contract.document_number}</dd>
                </div>
                <span className="rounded-full bg-green-100 px-3 py-1 text-xs font-medium capitalize text-green-800">
                  {contract.status}
                </span>
              </div>
              <div><dt className="text-xs text-zinc-500">Title</dt><dd className="font-medium">{contract.title}</dd></div>
              <div className="grid grid-cols-2 gap-4">
                <div><dt className="text-xs text-zinc-500">Contract No</dt><dd>{contract.contract_number}</dd></div>
                <div><dt className="text-xs text-zinc-500">Client</dt><dd>{contract.client_name}</dd></div>
              </div>
              <div><dt className="text-xs text-zinc-500">Contract Value</dt><dd className="text-xl font-bold text-amber-600">{formatMoney(contract.contract_value)}</dd></div>
              <div className="grid grid-cols-3 gap-4 text-sm">
                <div><dt className="text-xs text-zinc-500">Signed</dt><dd>{contract.signed_date || "—"}</dd></div>
                <div><dt className="text-xs text-zinc-500">Start</dt><dd>{contract.start_date || "—"}</dd></div>
                <div><dt className="text-xs text-zinc-500">End</dt><dd>{contract.end_date || "—"}</dd></div>
              </div>
              <div><dt className="text-xs text-zinc-500">Retention</dt><dd>{contract.retention_percent}%</dd></div>
            </dl>
          ) : null}
        </div>
      </div>
    </>
  );
}
