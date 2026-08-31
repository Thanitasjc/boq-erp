"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { ApiError } from "@/lib/api";
import { useAuth } from "@/contexts/AuthContext";

export default function LoginPage() {
  const { login, user } = useAuth();
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  if (user) {
    router.replace("/dashboard");
    return null;
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setLoading(true);
    try {
      await login(email, password);
      router.push("/dashboard");
    } catch (err) {
      if (err instanceof ApiError) {
        const msg = err.errors?.email?.[0] || err.message;
        setError(msg === "The provided credentials are incorrect." ? "อีเมลหรือรหัสผ่านไม่ถูกต้อง" : msg);
      } else {
        setError("ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาตรวจสอบ API");
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-zinc-900">
      <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
        <div className="mb-8 text-center">
          <p className="text-sm font-semibold uppercase tracking-wider text-amber-500">BOQ ERP</p>
          <h1 className="mt-2 text-2xl font-bold text-zinc-900">ระบบควบคุมโครงการ</h1>
          <p className="mt-1 text-sm text-zinc-500">เข้าสู่ระบบด้วยบัญชีของคุณ</p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          {error && (
            <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
          )}

          <div>
            <label className="mb-1 block text-sm font-medium text-zinc-700">อีเมล</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              placeholder="admin@boq.local"
              className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/20"
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-zinc-700">รหัสผ่าน</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/20"
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full rounded-lg bg-amber-400 py-2.5 text-sm font-semibold text-zinc-900 transition-colors hover:bg-amber-500 disabled:opacity-50"
          >
            {loading ? "กำลังเข้าสู่ระบบ..." : "เข้าสู่ระบบ"}
          </button>
        </form>

        <div className="mt-6 rounded-lg bg-zinc-50 p-4 text-xs text-zinc-500">
          <p className="font-medium text-zinc-700">บัญชีทดสอบ</p>
          <p className="mt-1">Admin: admin@boq.local / password</p>
          <p>PM: pm@boq.local / password</p>
          <p>Site: site@boq.local / password</p>
        </div>
      </div>
    </div>
  );
}
