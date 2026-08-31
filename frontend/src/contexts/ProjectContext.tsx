"use client";

import { createContext, useContext, useEffect, useState } from "react";
import { usePathname } from "next/navigation";
import { Project, projectsApi } from "@/lib/api";

interface ProjectContextValue {
  projects: Project[];
  selectedProjectId: number | null;
  setSelectedProjectId: (id: number | null) => void;
  loading: boolean;
}

const ProjectContext = createContext<ProjectContextValue>({
  projects: [],
  selectedProjectId: null,
  setSelectedProjectId: () => {},
  loading: true,
});

export function ProjectProvider({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const [projects, setProjects] = useState<Project[]>([]);
  const [selectedProjectId, setSelectedProjectIdState] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const saved = localStorage.getItem("selected_project_id");
    if (saved) setSelectedProjectIdState(Number(saved));

    projectsApi
      .list({ per_page: "100" })
      .then((res) => setProjects(res.data))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    const match = pathname.match(/^\/projects\/(\d+)/);
    if (match) {
      const id = Number(match[1]);
      setSelectedProjectIdState((prev) => {
        if (prev === id) return prev;
        localStorage.setItem("selected_project_id", String(id));
        return id;
      });
    }
  }, [pathname]);

  const setSelectedProjectId = (id: number | null) => {
    setSelectedProjectIdState(id);
    if (id) localStorage.setItem("selected_project_id", String(id));
    else localStorage.removeItem("selected_project_id");
  };

  return (
    <ProjectContext.Provider value={{ projects, selectedProjectId, setSelectedProjectId, loading }}>
      {children}
    </ProjectContext.Provider>
  );
}

export function useProjectContext() {
  return useContext(ProjectContext);
}
