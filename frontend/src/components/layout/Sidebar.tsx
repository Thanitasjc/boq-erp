"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { useProjectContext } from "@/contexts/ProjectContext";
import { approvalApi } from "@/lib/api";

interface NavItem {
  label: string;
  href?: string;
  projectPath?: string;
  matchPaths?: string[];
  permission?: string;
  badgeKey?: "approvals" | "procurement";
}

interface NavGroup {
  id: string;
  section: string;
  defaultOpen?: boolean;
  items: NavItem[];
}

const navigation: NavGroup[] = [
  {
    id: "main",
    section: "หลัก",
    defaultOpen: true,
    items: [
      { label: "แดชบอร์ด", href: "/dashboard", permission: "dashboard.company" },
      { label: "โครงการ", href: "/projects", permission: "projects.view" },
      { label: "อนุมัติ", href: "/approvals", badgeKey: "approvals" },
      { label: "ศูนย์รายงาน", href: "/reports", permission: "reports.view" },
    ],
  },
  {
    id: "project",
    section: "โมดูลโครงการ",
    defaultOpen: true,
    items: [
      { label: "BOQ & ประมาณราคา", projectPath: "boq", permission: "boq.view" },
      { label: "สัญญา & งบประมาณ", projectPath: "budget", matchPaths: ["budget", "contract"], permission: "budget.view" },
      { label: "จัดซื้อจัดจ้าง", projectPath: "pr", matchPaths: ["pr", "po", "gr"], permission: "procurement.view", badgeKey: "procurement" },
      { label: "หน้างาน", projectPath: "daily-report", permission: "site.view" },
      { label: "ความคืบหน้างาน", projectPath: "dashboard", matchPaths: ["dashboard", "progress"], permission: "dashboard.project" },
      { label: "การเงิน", projectPath: "billing", permission: "finance.view" },
      { label: "VO — เปลี่ยนแปลงงาน", projectPath: "vo", permission: "vo.view" },
    ],
  },
  {
    id: "masters",
    section: "ข้อมูลหลัก",
    defaultOpen: false,
    items: [
      { label: "รหัสต้นทุน", href: "/masters/cost-codes", permission: "masters.view" },
      { label: "หมวดหมู่รหัสต้นทุน", href: "/masters/cost-code-categories", permission: "masters.view" },
      { label: "หน่วยนับ", href: "/masters/uoms", permission: "masters.view" },
      { label: "ผู้ขาย/ผู้รับเหมา", href: "/masters/suppliers", permission: "masters.view" },
    ],
  },
  {
    id: "system",
    section: "ระบบ",
    defaultOpen: false,
    items: [
      { label: "จัดการผู้ใช้", href: "/admin/users", permission: "admin.users" },
    ],
  },
];

const STORAGE_KEY = "sidebar_open_sections";

function resolveProjectHref(projectId: number | null, projectPath: string): string {
  if (projectId) return `/projects/${projectId}/${projectPath}`;
  return "/projects";
}

function isItemActive(pathname: string, item: NavItem, projectId: number | null): boolean {
  if (item.href) {
    return pathname === item.href || (item.href !== "/projects" && pathname.startsWith(item.href + "/"));
  }
  if (!item.projectPath || !projectId) return false;
  const segments = item.matchPaths ?? [item.projectPath];
  return segments.some((seg) => {
    const base = `/projects/${projectId}/${seg}`;
    return pathname === base || pathname.startsWith(base + "/");
  });
}

function ChevronIcon({ open }: { open: boolean }) {
  return (
    <svg
      className={`h-4 w-4 shrink-0 text-zinc-500 transition-transform duration-200 ${open ? "rotate-180" : ""}`}
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
    >
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
    </svg>
  );
}

export default function Sidebar() {
  const pathname = usePathname();
  const { user, logout, hasPermission } = useAuth();
  const { selectedProjectId, projects } = useProjectContext();
  const [badges, setBadges] = useState({ approvals: 0, procurement: 0 });
  const [openSections, setOpenSections] = useState<Record<string, boolean>>({});

  const activeProjectId = selectedProjectId ?? projects[0]?.id ?? null;

  const visibleGroups = useMemo(
    () =>
      navigation
        .map((group) => ({
          ...group,
          items: group.items.filter((item) => !item.permission || hasPermission(item.permission)),
        }))
        .filter((group) => group.items.length > 0),
    [hasPermission],
  );

  const isGroupActive = useCallback(
    (group: NavGroup) => group.items.some((item) => isItemActive(pathname, item, activeProjectId)),
    [pathname, activeProjectId],
  );

  useEffect(() => {
    approvalApi.count().then((res) => {
      const by = res.data.by_type;
      setBadges({
        approvals: res.data.total,
        procurement: (by.pr ?? 0) + (by.po ?? 0),
      });
    }).catch(() => {});
  }, [pathname]);

  useEffect(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    const defaults: Record<string, boolean> = {};
    visibleGroups.forEach((g) => {
      defaults[g.id] = g.defaultOpen ?? true;
    });

    if (saved) {
      try {
        const parsed = JSON.parse(saved) as Record<string, boolean>;
        setOpenSections({ ...defaults, ...parsed });
        return;
      } catch {
        /* use defaults */
      }
    }
    setOpenSections(defaults);
  }, [visibleGroups]);

  useEffect(() => {
    const activeGroup = visibleGroups.find((g) => isGroupActive(g));
    if (activeGroup) {
      setOpenSections((prev) => ({ ...prev, [activeGroup.id]: true }));
    }
  }, [pathname, visibleGroups, isGroupActive]);

  const toggleSection = (id: string) => {
    setOpenSections((prev) => {
      const next = { ...prev, [id]: !prev[id] };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
      return next;
    });
  };

  const getBadge = (key?: NavItem["badgeKey"]) => {
    if (!key) return undefined;
    const count = badges[key];
    return count > 0 ? count : undefined;
  };

  const getGroupBadge = (group: NavGroup) => {
    let total = 0;
    group.items.forEach((item) => {
      const b = getBadge(item.badgeKey);
      if (b) total += b;
    });
    return total > 0 ? total : undefined;
  };

  return (
    <aside className="flex w-64 flex-col bg-zinc-900 text-white">
      <div className="border-b border-zinc-700 p-5">
        <p className="text-xs font-medium uppercase tracking-wider text-amber-400">BOQ ERP</p>
        <h1 className="mt-1 text-sm font-bold leading-tight">ระบบควบคุมโครงการ</h1>
        {user?.company && (
          <p className="mt-2 truncate text-xs text-zinc-400">{user.company.name}</p>
        )}
      </div>

      <nav className="flex-1 overflow-y-auto p-3">
        {visibleGroups.map((group) => {
          const isOpen = openSections[group.id] ?? group.defaultOpen ?? true;
          const groupActive = isGroupActive(group);
          const groupBadge = getGroupBadge(group);

          return (
            <div key={group.id} className="mb-1">
              <button
                type="button"
                onClick={() => toggleSection(group.id)}
                className={`flex w-full items-center justify-between rounded-lg px-3 py-2 text-left transition-colors hover:bg-zinc-800 ${
                  groupActive && !isOpen ? "text-amber-400" : "text-zinc-400"
                }`}
              >
                <span className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider">
                  {group.section}
                  {groupBadge !== undefined && (
                    <span className="flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[9px] font-bold text-white normal-case">
                      {groupBadge}
                    </span>
                  )}
                </span>
                <ChevronIcon open={isOpen} />
              </button>

              <div
                className={`overflow-hidden transition-all duration-200 ease-in-out ${
                  isOpen ? "max-h-[600px] opacity-100" : "max-h-0 opacity-0"
                }`}
              >
                <ul className="mb-2 mt-1 space-y-0.5 pl-1">
                  {group.items.map((item) => {
                    const href = item.href ?? resolveProjectHref(activeProjectId, item.projectPath!);
                    const active = isItemActive(pathname, item, activeProjectId);
                    const badge = getBadge(item.badgeKey);
                    return (
                      <li key={item.label + (item.href ?? item.projectPath)}>
                        <Link
                          href={href}
                          className={`flex items-center justify-between rounded-lg py-2 pl-4 pr-3 text-sm transition-colors ${
                            active
                              ? "bg-amber-400 font-medium text-zinc-900"
                              : "text-zinc-300 hover:bg-zinc-800 hover:text-white"
                          }`}
                        >
                          <span>{item.label}</span>
                          {badge !== undefined && (
                            <span className={`flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[10px] font-bold ${
                              active ? "bg-zinc-900 text-amber-400" : "bg-amber-500 text-white"
                            }`}>
                              {badge}
                            </span>
                          )}
                        </Link>
                      </li>
                    );
                  })}
                </ul>
              </div>
            </div>
          );
        })}
      </nav>

      <div className="border-t border-zinc-700 p-4">
        <div className="mb-3">
          <p className="text-sm font-medium">{user?.name}</p>
          <p className="text-xs text-zinc-400">{user?.position}</p>
        </div>
        <button
          onClick={() => logout()}
          className="w-full rounded-lg border border-zinc-600 px-3 py-1.5 text-xs text-zinc-300 transition-colors hover:border-zinc-400 hover:text-white"
        >
          ออกจากระบบ
        </button>
      </div>
    </aside>
  );
}
