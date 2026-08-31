# BOQ & Project Control ERP

Construction ERP system — BOQ, Budget, Procurement, Progress, Finance.

## Stack

| Layer | Technology | Deploy |
|-------|-----------|--------|
| Frontend | Next.js 16 + TypeScript + Tailwind | Vercel |
| Backend | Laravel 12 API + Sanctum | Render |
| Database | SQLite (dev) / PostgreSQL (prod) | Supabase |

## Structure

```
boq-erp/
├── backend/     Laravel API
└── frontend/    Next.js App
```

## Quick Start (Development)

### Backend

```bash
cd backend
cp .env.example .env   # already configured for SQLite
composer install
php artisan migrate --seed
php artisan serve      # http://localhost:8000
```

### Frontend

```bash
cd frontend
cp .env.local.example .env.local
npm install
npm run dev            # http://localhost:3000
```

### Default Login

| Email | Password | Role |
|-------|----------|------|
| admin@boq.local | password | Super Admin |
| pm@boq.local | password | Project Manager |
| site@boq.local | password | Site Engineer |

Manage users at `/admin/users` (Super Admin only).

## Demo Data (after seed)

| Menu | Sample Data |
|------|-------------|
| แดชบอร์ด | 4 โครงการ, KPI, กราฟ Budget/Cost |
| BOQ | P0001 อนุมัติแล้ว + ฉบับ 2 รออนุมัติ, P0002 อนุมัติ, P0003 ร่าง |
| สัญญา & งบ | P0001/P0002/P0003 มีสัญญา, งบ P0002 รออนุมัติ |
| จัดซื้อ | PR→PO→GR ครบ flow P0001, PO/PR รออนุมัติ |
| หน้างาน | รายงาน P0001 (2 รายการ), P0002 (1 รายการ) |
| ความคืบหน้า | S-Curve P0001 + P0002 |
| การเงิน | เคลม/รับเงิน/จ่ายเงิน P0001, เคลมรออนุมัติ |
| VO | VO อนุมัติ + VO รออนุมัติ |
| อนุมัติ | รวม 7+ รายการรออนุมัติ |
| ข้อมูลหลัก | Cost codes, UOMs, 3 suppliers |

## API

Base URL: `http://localhost:8000/api`

- `POST /login` — authenticate
- `POST /logout` — revoke token
- `GET /user` — current user + permissions
- `GET /dashboard/company` — company dashboard KPIs
- `GET /projects` — project list
- `GET /masters/cost-codes` — cost code master
- `GET /masters/uoms` — UOM master
- `GET /projects/{id}/boq-versions` — BOQ list
- `POST /projects/{id}/boq-versions` — create BOQ version
- `GET /projects/{id}/boq-versions/{vid}` — BOQ detail + items
- `POST /projects/{id}/boq-versions/{vid}/submit` — submit for approval
- `POST /projects/{id}/boq-versions/{vid}/approve` — approve BOQ
- `POST /projects/{id}/boq-versions/{vid}/import/preview` — Excel import preview
- `POST /projects/{id}/boq-versions/{vid}/import/confirm` — confirm import
- `GET /projects/{id}/boq-versions/{vid}/export` — export Excel

## Development Phases

- [x] Phase 0 — Foundation (Auth, RBAC, Master Data, Layout)
- [x] Phase 1 — BOQ Core (Version, Items, Import, Export, Approval)
- [x] Phase 2 — Budget & Contract (Generate, Approval, Cost Ledger)
- [x] Phase 3 — Procurement (PR/PO/GR, Budget Check, Ledger)
- [x] Phase 4 — Progress & S-Curve (Baseline, EV/PV/AC, Project Dashboard)
- [x] Phase 5 — Finance (Claims, Payments, Cash Flow, Ledger)
- [x] Phase 6 — VO (Variation Orders, Budget Revision, Ledger)
- [x] Phase 7 — Site Operations (Daily Reports, Labor/Material/Equipment)
- [x] Phase 8 — Company Dashboard (Charts: Budget vs Cost, Status Pie)
- [x] Phase 9 — Reports & Polish (Approval Inbox, Report Center, Dynamic Badges)

## Production Deployment

| Service | Platform | Purpose |
|---------|----------|---------|
| Frontend | [Vercel](https://vercel.com) | Next.js app |
| Backend API | [Render](https://dashboard.render.com) | Laravel API (Docker) |
| Database | [Supabase](https://supabase.com) | PostgreSQL |

### Architecture

```
Browser → Vercel (Next.js) → Render (Laravel API) → Supabase (PostgreSQL)
```

### 1. GitHub

```bash
git init -b main
git add -A
git commit -m "Initial commit: BOQ ERP"
gh repo create boq-erp --public --source=. --remote=origin --push
```

### 2. Supabase (Database)

1. Create project at [supabase.com/dashboard](https://supabase.com/dashboard)
2. Go to **Project Settings → Database**
3. Copy **Connection string** (URI mode, port `6543` pooler recommended)
4. Save as `DATABASE_URL` for Render (never commit this value)

### 3. Render (Backend API)

1. Open [Render Dashboard](https://dashboard.render.com/)
2. **New → Blueprint** → connect GitHub repo `boq-erp`
3. Render reads `render.yaml` at repo root
4. Set environment variables in the `boq-api` service:

| Variable | Example |
|----------|---------|
| `DATABASE_URL` | `postgresql://postgres.[ref]:***@...supabase.com:6543/postgres` |
| `APP_URL` | `https://boq-api.onrender.com` |
| `FRONTEND_URL` | `https://your-app.vercel.app` |
| `RUN_SEED` | `true` (first deploy only, then set `false`) |

5. Deploy — entrypoint runs `php artisan migrate --force`
6. Verify: `https://boq-api.onrender.com/up` returns healthy

### 4. Vercel (Frontend)

1. Open [vercel.com](https://vercel.com) → **Add New Project**
2. Import GitHub repo `boq-erp`
3. Set **Root Directory** = `frontend`
4. Environment variable:

| Variable | Value |
|----------|-------|
| `NEXT_PUBLIC_API_URL` | `https://boq-api.onrender.com/api` |

5. Deploy → copy production URL
6. Update Render `FRONTEND_URL` to the Vercel URL and redeploy API (for CORS)

### 5. Post-deploy checklist

- [ ] Login works on Vercel URL
- [ ] API health: `/up`
- [ ] CORS: `FRONTEND_URL` matches Vercel domain exactly
- [ ] After seed: set `RUN_SEED=false` on Render
- [ ] Change default passwords in production

### Environment reference

**Backend (`Render`)**

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=<auto-generated>
APP_URL=https://boq-api.onrender.com
FRONTEND_URL=https://your-app.vercel.app
DB_CONNECTION=pgsql
DATABASE_URL=<supabase-connection-string>
RUN_SEED=true
```

**Frontend (`Vercel`)**

```env
NEXT_PUBLIC_API_URL=https://boq-api.onrender.com/api
```

### Local vs Production

| | Development | Production |
|---|-------------|------------|
| Frontend | `http://localhost:3000` | `https://*.vercel.app` |
| API | `http://localhost:8000/api` | `https://boq-api.onrender.com/api` |
| Database | SQLite | Supabase PostgreSQL |

> **Security:** Never commit `.env`, database passwords, or API keys. Set secrets only in Render/Vercel dashboards.
