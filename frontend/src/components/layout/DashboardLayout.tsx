"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import { ProjectProvider } from "@/contexts/ProjectContext";
import Sidebar from "./Sidebar";
import TopBar from "./TopBar";

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const { user, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user) {
      router.replace("/login");
    }
  }, [user, loading, router]);

  if (loading) {
    return (
      <div className="flex h-screen items-center justify-center bg-zinc-50">
        <div className="text-center">
          <div className="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
          <p className="mt-3 text-sm text-zinc-500">กำลังโหลด...</p>
        </div>
      </div>
    );
  }

  if (!user) return null;

  return (
    <ProjectProvider>
      <div className="flex h-screen overflow-hidden">
        <Sidebar />
        <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
          <TopBar />
          <main className="flex min-h-0 flex-1 flex-col overflow-hidden bg-zinc-50">
            {children}
          </main>
        </div>
      </div>
    </ProjectProvider>
  );
}
