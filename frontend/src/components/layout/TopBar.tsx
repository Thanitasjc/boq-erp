"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { useAuth } from "@/contexts/AuthContext";
import { useProjectContext } from "@/contexts/ProjectContext";
import { SearchResult, notificationsApi, searchApi } from "@/lib/api";

export default function TopBar() {
  const router = useRouter();
  const { user } = useAuth();
  const { projects, selectedProjectId, setSelectedProjectId } = useProjectContext();
  const [search, setSearch] = useState("");
  const [results, setResults] = useState<SearchResult[]>([]);
  const [showResults, setShowResults] = useState(false);
  const [unreadCount, setUnreadCount] = useState(0);
  const [showNotifications, setShowNotifications] = useState(false);
  const searchRef = useRef<HTMLDivElement>(null);

  const initials = user?.name
    ?.split(" ")
    .map((n) => n[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();

  useEffect(() => {
    notificationsApi.list().then((res) => setUnreadCount(res.unread_count)).catch(() => {});
  }, []);

  useEffect(() => {
    if (search.length < 2) {
      setResults([]);
      return;
    }
    const timer = setTimeout(() => {
      searchApi.query(search).then((res) => {
        setResults(res.data);
        setShowResults(true);
      }).catch(() => setResults([]));
    }, 300);
    return () => clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (searchRef.current && !searchRef.current.contains(e.target as Node)) {
        setShowResults(false);
      }
    };
    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, []);

  const handleProjectChange = (value: string) => {
    if (value === "all") {
      setSelectedProjectId(null);
      router.push("/dashboard");
    } else {
      const id = Number(value);
      setSelectedProjectId(id);
      router.push(`/projects/${id}/dashboard`);
    }
  };

  return (
    <div className="flex h-14 items-center justify-between border-b border-zinc-200 bg-white px-4">
      <div className="flex items-center gap-3">
        <select
          value={selectedProjectId ?? "all"}
          onChange={(e) => handleProjectChange(e.target.value)}
          className="max-w-[240px] rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-sm font-medium text-zinc-700"
        >
          <option value="all">ALL — ทุกโครงการ (Company View)</option>
          {projects.map((p) => (
            <option key={p.id} value={p.id}>{p.code} — {p.name}</option>
          ))}
        </select>
      </div>

      <div className="relative mx-4 hidden max-w-xl flex-1 md:block" ref={searchRef}>
        <svg className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          type="text"
          placeholder="ค้นหา BOQ, PR, PO, VO, เอกสาร..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onFocus={() => results.length > 0 && setShowResults(true)}
          className="w-full rounded-lg border border-zinc-200 bg-zinc-50 py-2 pl-10 pr-4 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/20"
        />
        {showResults && results.length > 0 && (
          <div className="absolute left-0 right-0 top-full z-50 mt-1 max-h-64 overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-lg">
            {results.map((r, i) => (
              <Link
                key={`${r.type}-${r.id}-${i}`}
                href={r.href}
                onClick={() => { setShowResults(false); setSearch(""); }}
                className="block px-4 py-2.5 text-sm hover:bg-zinc-50"
              >
                <span className="mr-2 rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-zinc-500">{r.type}</span>
                {r.label}
              </Link>
            ))}
          </div>
        )}
      </div>

      <div className="flex items-center gap-3">
        <div className="relative">
          <button
            onClick={() => setShowNotifications(!showNotifications)}
            className="relative rounded-lg p-2 text-zinc-500 hover:bg-zinc-100"
          >
            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            {unreadCount > 0 && (
              <span className="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[10px] font-bold text-white">
                {unreadCount > 9 ? "9+" : unreadCount}
              </span>
            )}
          </button>
          {showNotifications && (
            <div className="absolute right-0 top-full z-50 mt-1 w-80 rounded-lg border border-zinc-200 bg-white shadow-lg">
              <div className="border-b px-4 py-2 text-sm font-medium">การแจ้งเตือน</div>
              <NotificationList onClose={() => setShowNotifications(false)} onRead={() => notificationsApi.list().then((r) => setUnreadCount(r.unread_count))} />
            </div>
          )}
        </div>

        <div className="flex items-center gap-2 rounded-lg border border-zinc-200 px-2 py-1">
          <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">
            {initials}
          </div>
          <span className="hidden text-sm font-medium text-zinc-700 sm:block">{user?.name}</span>
        </div>
      </div>
    </div>
  );
}

function NotificationList({ onClose, onRead }: { onClose: () => void; onRead: () => void }) {
  const [items, setItems] = useState<{ id: number; title: string; message: string; link: string | null; read_at: string | null }[]>([]);

  useEffect(() => {
    notificationsApi.list().then((res) => setItems(res.data));
  }, []);

  if (items.length === 0) {
    return <p className="px-4 py-6 text-center text-sm text-zinc-400">ไม่มีการแจ้งเตือน</p>;
  }

  return (
    <div className="max-h-64 overflow-y-auto">
      {items.map((n) => (
        <Link
          key={n.id}
          href={n.link || "#"}
          onClick={() => {
            if (!n.read_at) notificationsApi.markRead(n.id).then(onRead);
            onClose();
          }}
          className={`block border-b px-4 py-3 text-sm hover:bg-zinc-50 ${!n.read_at ? "bg-amber-50/50" : ""}`}
        >
          <p className="font-medium text-zinc-800">{n.title}</p>
          <p className="mt-0.5 text-xs text-zinc-500 line-clamp-2">{n.message}</p>
        </Link>
      ))}
    </div>
  );
}
