import React, { useState } from "react";

// ─── Types ────────────────────────────────────────────────────────────────────
type Category = "CPU" | "GPU" | "RAM" | "Storage" | "Motherboard" | "PSU" | "Case" | "Cooling";
type Status = "In Stock" | "Low Stock" | "Out of Stock" | "Discontinued";
type BuildStatus = "Draft" | "Active" | "Completed" | "Cancelled";
type UserRole = "Admin" | "Technician" | "Viewer";
type Page = "dashboard" | "parts" | "builds" | "users" | "audit-logs" | "privileges" | "deletion-requests" | "catalog" | "configurator" | "my-builds";
type AuditAction = "CREATE" | "UPDATE" | "DELETE" | "LOGIN" | "LOGOUT" | "EXPORT" | "PERMISSION_CHANGE" | "STATUS_CHANGE";
type PrivilegeLevel = "Full Access" | "Read/Write" | "Read Only" | "Restricted";
type DeletionStatus = "Pending" | "Approved" | "Rejected" | "Processing";
type ViewMode = "admin" | "user";

interface Part {
  id: string;
  name: string;
  category: Category;
  brand: string;
  model: string;
  price: number;
  stock: number;
  status: Status;
  specs: string;
  sku: string;
  added: string;
}

interface BuildPart {
  partId: string;
  quantity: number;
}

interface Build {
  id: string;
  name: string;
  description: string;
  parts: BuildPart[];
  status: BuildStatus;
  totalCost: number;
  createdBy: string;
  createdAt: string;
  purpose: string;
}

interface AuditLog {
  id: string;
  timestamp: string;
  user: string;
  userRole: UserRole;
  action: AuditAction;
  target: string;
  targetType: "Part" | "Build" | "User" | "System" | "Privilege";
  details: string;
  ip: string;
  severity: "Info" | "Warning" | "Critical";
}

interface Privilege {
  id: string;
  userId: string;
  userName: string;
  email: string;
  role: UserRole;
  level: PrivilegeLevel;
  modules: { name: string; access: "Full" | "Write" | "Read" | "None" }[];
  grantedBy: string;
  grantedAt: string;
  expiresAt: string | null;
  notes: string;
}

interface DeletionRequest {
  id: string;
  requestedBy: string;
  requestedAt: string;
  targetType: "Part" | "Build" | "User" | "Data Export";
  targetName: string;
  targetId: string;
  reason: string;
  status: DeletionStatus;
  reviewedBy: string | null;
  reviewedAt: string | null;
  reviewNote: string | null;
  priority: "Low" | "Medium" | "High" | "Critical";
}

interface User {
  id: string;
  name: string;
  email: string;
  role: UserRole;
  department: string;
  builds: number;
  joined: string;
  active: boolean;
}

// ─── Initial Data ─────────────────────────────────────────────────────────────
const initialParts: Part[] = [
  { id: "p1", name: "Ryzen 9 7950X", category: "CPU", brand: "AMD", model: "100-100000514WOF", price: 699, stock: 12, status: "In Stock", specs: "16-Core, 32-Thread, 4.5GHz Base, 5.7GHz Boost, TDP 170W", sku: "CPU-AMD-7950X", added: "2025-11-01" },
  { id: "p2", name: "Core i9-14900K", category: "CPU", brand: "Intel", model: "BX8071514900K", price: 589, stock: 8, status: "In Stock", specs: "24-Core, 32-Thread, 3.2GHz Base, 6.0GHz Boost, TDP 125W", sku: "CPU-INT-14900K", added: "2025-11-15" },
  { id: "p3", name: "RTX 4090", category: "GPU", brand: "NVIDIA", model: "900-1G136-2530-000", price: 1599, stock: 4, status: "Low Stock", specs: "24GB GDDR6X, 16384 CUDA, 2520MHz Boost, 450W TDP", sku: "GPU-NV-4090", added: "2025-10-20" },
  { id: "p4", name: "RX 7900 XTX", category: "GPU", brand: "AMD", model: "21308-01-20G", price: 999, stock: 7, status: "In Stock", specs: "24GB GDDR6, 6144 CU, 2499MHz Boost, 355W TDP", sku: "GPU-AMD-7900XTX", added: "2025-10-25" },
  { id: "p5", name: "Trident Z5 RGB 64GB", category: "RAM", brand: "G.Skill", model: "F5-6400J3239G32GX2-TZ5RK", price: 249, stock: 20, status: "In Stock", specs: "2x32GB DDR5-6400, CL32, XMP 3.0", sku: "RAM-GS-TZ5-64", added: "2025-09-10" },
  { id: "p6", name: "Vengeance 32GB", category: "RAM", brand: "Corsair", model: "CMK32GX5M2B5600C36", price: 119, stock: 25, status: "In Stock", specs: "2x16GB DDR5-5600, CL36, Intel XMP 3.0", sku: "RAM-COR-V32", added: "2025-09-12" },
  { id: "p7", name: "990 Pro 2TB", category: "Storage", brand: "Samsung", model: "MZ-V9P2T0B/AM", price: 199, stock: 15, status: "In Stock", specs: "PCIe 4.0 NVMe M.2, 7450MB/s Read, 6900MB/s Write", sku: "SSD-SAM-990-2T", added: "2025-08-05" },
  { id: "p8", name: "WD Black SN850X 1TB", category: "Storage", brand: "Western Digital", model: "WDS100T2X0E", price: 129, stock: 18, status: "In Stock", specs: "PCIe 4.0 NVMe M.2, 7300MB/s Read, 6600MB/s Write", sku: "SSD-WD-SN850X-1T", added: "2025-08-10" },
  { id: "p9", name: "ROG Maximus Z790 Apex", category: "Motherboard", brand: "ASUS", model: "90MB1FP0-M0EAY0", price: 899, stock: 5, status: "Low Stock", specs: "Z790, LGA1700, DDR5, PCIe 5.0, WiFi 6E", sku: "MB-ASUS-Z790A", added: "2025-07-20" },
  { id: "p10", name: "MEG X670E ACE", category: "Motherboard", brand: "MSI", model: "7E13-003R", price: 699, stock: 6, status: "In Stock", specs: "X670E, AM5, DDR5, PCIe 5.0, WiFi 6E", sku: "MB-MSI-X670E", added: "2025-07-25" },
  { id: "p11", name: "AX1600i", category: "PSU", brand: "Corsair", model: "CP-9020087-NA", price: 399, stock: 9, status: "In Stock", specs: "1600W, 80+ Titanium, Digital ATX, Full Modular", sku: "PSU-COR-AX1600", added: "2025-06-15" },
  { id: "p12", name: "O11 Dynamic EVO", category: "Case", brand: "Lian Li", model: "PC-O11DEW", price: 179, stock: 11, status: "In Stock", specs: "Mid Tower, Tempered Glass, 360mm Radiator Support", sku: "CASE-LL-O11E", added: "2025-06-20" },
  { id: "p13", name: "Kraken Elite 360", category: "Cooling", brand: "NZXT", model: "RL-KR36E-B1", price: 249, stock: 6, status: "In Stock", specs: "360mm AIO, LCD Display, 3x 120mm Fans, AM5/LGA1700", sku: "COOL-NZXT-K360", added: "2025-05-30" },
  { id: "p14", name: "Core i7-14700K", category: "CPU", brand: "Intel", model: "BX8071514700K", price: 409, stock: 0, status: "Out of Stock", specs: "20-Core, 28-Thread, 3.4GHz Base, 5.6GHz Boost, TDP 125W", sku: "CPU-INT-14700K", added: "2025-11-01" },
];

const initialBuilds: Build[] = [
  { id: "b1", name: "Workstation Pro X", description: "High-end workstation for 3D rendering and video editing", parts: [{ partId: "p1", quantity: 1 }, { partId: "p9", quantity: 1 }, { partId: "p3", quantity: 2 }, { partId: "p5", quantity: 1 }, { partId: "p7", quantity: 2 }, { partId: "p11", quantity: 1 }, { partId: "p12", quantity: 1 }, { partId: "p13", quantity: 1 }], status: "Active", totalCost: 5780, createdBy: "Alex Chen", createdAt: "2025-12-01", purpose: "3D/Video Production" },
  { id: "b2", name: "Gaming Beast Alpha", description: "Ultimate gaming rig for 4K high-refresh competitive gaming", parts: [{ partId: "p2", quantity: 1 }, { partId: "p9", quantity: 1 }, { partId: "p3", quantity: 1 }, { partId: "p6", quantity: 1 }, { partId: "p8", quantity: 1 }, { partId: "p11", quantity: 1 }, { partId: "p12", quantity: 1 }, { partId: "p13", quantity: 1 }], status: "Completed", totalCost: 4162, createdBy: "Maya Rivera", createdAt: "2025-11-20", purpose: "Gaming" },
  { id: "b3", name: "Dev Machine Delta", description: "Development workstation for software engineering team", parts: [{ partId: "p1", quantity: 1 }, { partId: "p10", quantity: 1 }, { partId: "p4", quantity: 1 }, { partId: "p6", quantity: 1 }, { partId: "p7", quantity: 1 }, { partId: "p11", quantity: 1 }, { partId: "p12", quantity: 1 }], status: "Active", totalCost: 2784, createdBy: "Jordan Kim", createdAt: "2025-12-05", purpose: "Software Development" },
  { id: "b4", name: "Budget Studio", description: "Cost-effective creative workstation for multimedia tasks", parts: [{ partId: "p2", quantity: 1 }, { partId: "p10", quantity: 1 }, { partId: "p4", quantity: 1 }, { partId: "p5", quantity: 1 }, { partId: "p8", quantity: 1 }, { partId: "p11", quantity: 1 }, { partId: "p12", quantity: 1 }], status: "Draft", totalCost: 3613, createdBy: "Sam Torres", createdAt: "2025-12-10", purpose: "Content Creation" },
];

const initialUsers: User[] = [
  { id: "u1", name: "Alex Chen", email: "alex.chen@techcorp.io", role: "Admin", department: "IT Infrastructure", builds: 8, joined: "2024-03-15", active: true },
  { id: "u2", name: "Maya Rivera", email: "maya.r@techcorp.io", role: "Technician", department: "Hardware Lab", builds: 14, joined: "2024-06-01", active: true },
  { id: "u3", name: "Jordan Kim", email: "j.kim@techcorp.io", role: "Technician", department: "Engineering", builds: 5, joined: "2024-09-10", active: true },
  { id: "u4", name: "Sam Torres", email: "s.torres@techcorp.io", role: "Viewer", department: "Operations", builds: 2, joined: "2025-01-20", active: false },
  { id: "u5", name: "Riley Zhao", email: "r.zhao@techcorp.io", role: "Admin", department: "IT Infrastructure", builds: 11, joined: "2024-01-05", active: true },
];

const initialAuditLogs: AuditLog[] = [
  { id: "al1", timestamp: "2026-09-16 09:42:11", user: "Alex Chen", userRole: "Admin", action: "CREATE", target: "Workstation Pro X", targetType: "Build", details: "Created new build with 8 components, total cost $5,780", ip: "192.168.1.14", severity: "Info" },
  { id: "al2", timestamp: "2026-09-16 09:38:55", user: "Maya Rivera", userRole: "Technician", action: "UPDATE", target: "RTX 4090", targetType: "Part", details: "Stock updated from 7 to 4, status changed to Low Stock", ip: "192.168.1.22", severity: "Warning" },
  { id: "al3", timestamp: "2026-09-16 08:55:02", user: "Riley Zhao", userRole: "Admin", action: "PERMISSION_CHANGE", target: "Sam Torres", targetType: "Privilege", details: "Role changed from Admin to Viewer, access level downgraded", ip: "192.168.1.10", severity: "Critical" },
  { id: "al4", timestamp: "2026-09-16 08:30:44", user: "Jordan Kim", userRole: "Technician", action: "LOGIN", target: "System", targetType: "System", details: "Successful authentication from Chrome 120 / Windows 11", ip: "192.168.1.31", severity: "Info" },
  { id: "al5", timestamp: "2026-09-15 17:22:10", user: "Alex Chen", userRole: "Admin", action: "DELETE", target: "Core i7-14700K (p14)", targetType: "Part", details: "Part deleted — reason: discontinued by manufacturer", ip: "192.168.1.14", severity: "Warning" },
  { id: "al6", timestamp: "2026-09-15 16:50:33", user: "Sam Torres", userRole: "Viewer", action: "EXPORT", target: "Parts Inventory", targetType: "System", details: "Exported full parts CSV — 14 records, 48KB", ip: "192.168.1.55", severity: "Info" },
  { id: "al7", timestamp: "2026-09-15 15:11:09", user: "Maya Rivera", userRole: "Technician", action: "STATUS_CHANGE", target: "Gaming Beast Alpha", targetType: "Build", details: "Build status changed: Active → Completed", ip: "192.168.1.22", severity: "Info" },
  { id: "al8", timestamp: "2026-09-15 14:05:28", user: "Unknown", userRole: "Viewer", action: "LOGIN", target: "System", targetType: "System", details: "FAILED login attempt — invalid credentials (3rd attempt)", ip: "203.45.12.99", severity: "Critical" },
  { id: "al9", timestamp: "2026-09-15 11:40:17", user: "Riley Zhao", userRole: "Admin", action: "CREATE", target: "Riley Zhao", targetType: "User", details: "New user account created: r.zhao@techcorp.io, role: Admin", ip: "192.168.1.10", severity: "Info" },
  { id: "al10", timestamp: "2026-09-15 10:22:55", user: "Jordan Kim", userRole: "Technician", action: "UPDATE", target: "Dev Machine Delta", targetType: "Build", details: "Added 2 components: RX 7900 XTX, 990 Pro 2TB", ip: "192.168.1.31", severity: "Info" },
  { id: "al11", timestamp: "2026-09-14 16:30:00", user: "Alex Chen", userRole: "Admin", action: "LOGOUT", target: "System", targetType: "System", details: "Session ended after 4h 32m activity", ip: "192.168.1.14", severity: "Info" },
  { id: "al12", timestamp: "2026-09-14 09:15:44", user: "Maya Rivera", userRole: "Technician", action: "CREATE", target: "ROG Maximus Z790 Apex", targetType: "Part", details: "New part registered — SKU: MB-ASUS-Z790A, Stock: 5", ip: "192.168.1.22", severity: "Info" },
];

const initialPrivileges: Privilege[] = [
  {
    id: "prv1", userId: "u1", userName: "Alex Chen", email: "alex.chen@techcorp.io", role: "Admin", level: "Full Access",
    modules: [{ name: "Parts", access: "Full" }, { name: "Builds", access: "Full" }, { name: "Users", access: "Full" }, { name: "Audit Logs", access: "Full" }, { name: "Privileges", access: "Full" }, { name: "Deletion", access: "Full" }],
    grantedBy: "System", grantedAt: "2024-03-15", expiresAt: null, notes: "Founding administrator account",
  },
  {
    id: "prv2", userId: "u5", userName: "Riley Zhao", email: "r.zhao@techcorp.io", role: "Admin", level: "Full Access",
    modules: [{ name: "Parts", access: "Full" }, { name: "Builds", access: "Full" }, { name: "Users", access: "Full" }, { name: "Audit Logs", access: "Read" }, { name: "Privileges", access: "Write" }, { name: "Deletion", access: "Full" }],
    grantedBy: "Alex Chen", grantedAt: "2024-01-05", expiresAt: null, notes: "Secondary admin — no privilege self-modification",
  },
  {
    id: "prv3", userId: "u2", userName: "Maya Rivera", email: "maya.r@techcorp.io", role: "Technician", level: "Read/Write",
    modules: [{ name: "Parts", access: "Full" }, { name: "Builds", access: "Write" }, { name: "Users", access: "Read" }, { name: "Audit Logs", access: "None" }, { name: "Privileges", access: "None" }, { name: "Deletion", access: "None" }],
    grantedBy: "Alex Chen", grantedAt: "2024-06-01", expiresAt: "2027-06-01", notes: "Hardware lab technician — full parts access",
  },
  {
    id: "prv4", userId: "u3", userName: "Jordan Kim", email: "j.kim@techcorp.io", role: "Technician", level: "Read/Write",
    modules: [{ name: "Parts", access: "Read" }, { name: "Builds", access: "Write" }, { name: "Users", access: "None" }, { name: "Audit Logs", access: "None" }, { name: "Privileges", access: "None" }, { name: "Deletion", access: "None" }],
    grantedBy: "Riley Zhao", grantedAt: "2024-09-10", expiresAt: "2026-09-10", notes: "Engineering team — build configuration focus",
  },
  {
    id: "prv5", userId: "u4", userName: "Sam Torres", email: "s.torres@techcorp.io", role: "Viewer", level: "Read Only",
    modules: [{ name: "Parts", access: "Read" }, { name: "Builds", access: "Read" }, { name: "Users", access: "None" }, { name: "Audit Logs", access: "None" }, { name: "Privileges", access: "None" }, { name: "Deletion", access: "None" }],
    grantedBy: "Alex Chen", grantedAt: "2025-01-20", expiresAt: "2026-01-20", notes: "Ops observer — read-only access, expires annually",
  },
];

const initialDeletionRequests: DeletionRequest[] = [
  { id: "dr1", requestedBy: "Maya Rivera", requestedAt: "2026-09-16 08:10:00", targetType: "Part", targetName: "Core i7-14700K", targetId: "p14", reason: "Part officially discontinued by Intel. No remaining stock. Cluttering active inventory view.", status: "Pending", reviewedBy: null, reviewedAt: null, reviewNote: null, priority: "Medium" },
  { id: "dr2", requestedBy: "Jordan Kim", requestedAt: "2026-09-15 14:30:00", targetType: "Build", targetName: "Budget Studio", targetId: "b4", reason: "Client cancelled project. Build is in Draft status and was never activated.", status: "Approved", reviewedBy: "Alex Chen", reviewedAt: "2026-09-15 16:00:00", reviewNote: "Approved — draft build with no active orders. Safe to remove.", priority: "Low" },
  { id: "dr3", requestedBy: "Sam Torres", requestedAt: "2026-09-14 11:20:00", targetType: "Data Export", targetName: "Parts Inventory Export (CSV)", targetId: "exp-20260914", reason: "Accidental export containing internal SKU pricing. Needs to be purged from system logs.", status: "Processing", reviewedBy: "Riley Zhao", reviewedAt: "2026-09-14 12:45:00", reviewNote: "Approved with priority. IT team notified to purge from backup logs.", priority: "Critical" },
  { id: "dr4", requestedBy: "Riley Zhao", requestedAt: "2026-09-13 09:55:00", targetType: "User", targetName: "Sam Torres", targetId: "u4", reason: "User account inactive for 60+ days. No pending builds or assignments.", status: "Rejected", reviewedBy: "Alex Chen", reviewedAt: "2026-09-13 11:00:00", reviewNote: "Rejected — user has an active viewer contract until Jan 2026. Deactivate instead.", priority: "Low" },
  { id: "dr5", requestedBy: "Alex Chen", requestedAt: "2026-09-12 15:00:00", targetType: "Part", targetName: "WD Black SN850X 1TB", targetId: "p8", reason: "Superseded by newer SN900X model. Recommend removing to avoid ordering confusion.", status: "Pending", reviewedBy: null, reviewedAt: null, reviewNote: null, priority: "High" },
];

// ─── Icons (inline SVG) ────────────────────────────────────────────────────────
const Icon = {
  dashboard: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
    </svg>
  ),
  cpu: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M1 9h3M1 15h3M20 9h3M20 15h3"/>
    </svg>
  ),
  build: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
    </svg>
  ),
  users: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
    </svg>
  ),
  catalog: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
    </svg>
  ),
  plus: () => (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>
  ),
  edit: () => (
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
    </svg>
  ),
  trash: () => (
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
    </svg>
  ),
  search: () => (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
    </svg>
  ),
  close: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
  ),
  shield: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
    </svg>
  ),
  chip: () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
      <rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M1 9h3M1 15h3M20 9h3M20 15h3"/>
    </svg>
  ),
  toggle: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="1" y="5" width="22" height="14" rx="7"/><circle cx="8" cy="12" r="3"/>
    </svg>
  ),
  star: () => (
    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" stroke="none">
      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
    </svg>
  ),
  log: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
    </svg>
  ),
  key: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
    </svg>
  ),
  trash2: () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
    </svg>
  ),
  check: () => (
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="20 6 9 17 4 12"/>
    </svg>
  ),
  x: () => (
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round">
      <path d="M18 6 6 18M6 6l12 12"/>
    </svg>
  ),
  filter: () => (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
    </svg>
  ),
  lock: () => (
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
    </svg>
  ),
  unlock: () => (
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>
    </svg>
  ),
};

// ─── Badge Components ─────────────────────────────────────────────────────────
function StatusBadge({ status }: { status: Status }) {
  const colors: Record<Status, string> = {
    "In Stock": "bg-green-950 text-green-400 border border-green-800",
    "Low Stock": "bg-yellow-950 text-yellow-400 border border-yellow-800",
    "Out of Stock": "bg-red-950 text-red-400 border border-red-800",
    "Discontinued": "bg-gray-900 text-gray-500 border border-gray-700",
  };
  return <span className={`badge ${colors[status]}`}>{status}</span>;
}

function BuildStatusBadge({ status }: { status: BuildStatus }) {
  const colors: Record<BuildStatus, string> = {
    "Draft": "bg-slate-900 text-slate-400 border border-slate-700",
    "Active": "bg-cyan-950 text-cyan-400 border border-cyan-800",
    "Completed": "bg-green-950 text-green-400 border border-green-800",
    "Cancelled": "bg-red-950 text-red-400 border border-red-800",
  };
  return <span className={`badge ${colors[status]}`}>{status}</span>;
}

function RoleBadge({ role }: { role: UserRole }) {
  const colors: Record<UserRole, string> = {
    "Admin": "bg-purple-950 text-purple-400 border border-purple-800",
    "Technician": "bg-cyan-950 text-cyan-400 border border-cyan-800",
    "Viewer": "bg-slate-900 text-slate-400 border border-slate-700",
  };
  return <span className={`badge ${colors[role]}`}>{role}</span>;
}

// ─── Dashboard Page ────────────────────────────────────────────────────────────
function DashboardPage({ parts, builds, users }: { parts: Part[]; builds: Build[]; users: User[] }) {
  const totalPartsValue = parts.reduce((s, p) => s + p.price * p.stock, 0);
  const lowStock = parts.filter(p => p.status === "Low Stock").length;
  const outOfStock = parts.filter(p => p.status === "Out of Stock").length;
  const activeBuilds = builds.filter(b => b.status === "Active").length;

  const stats = [
    { label: "Total Parts", value: parts.length.toString(), sub: `${lowStock} low · ${outOfStock} out`, color: "var(--primary)" },
    { label: "Inventory Value", value: `$${totalPartsValue.toLocaleString()}`, sub: "across all SKUs", color: "#a78bfa" },
    { label: "Active Builds", value: activeBuilds.toString(), sub: `${builds.length} total builds`, color: "var(--accent)" },
    { label: "System Users", value: users.length.toString(), sub: `${users.filter(u => u.active).length} active`, color: "#f97316" },
  ];

  const categoryCount: Record<string, number> = {};
  parts.forEach(p => { categoryCount[p.category] = (categoryCount[p.category] || 0) + 1; });

  const recentBuilds = [...builds].sort((a, b) => b.createdAt.localeCompare(a.createdAt)).slice(0, 4);
  const lowStockParts = parts.filter(p => p.status === "Low Stock" || p.status === "Out of Stock").slice(0, 5);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-display text-3xl font-bold text-glow" style={{ color: "var(--primary)" }}>SYSTEM OVERVIEW</h1>
        <p className="text-sm mt-1" style={{ color: "var(--muted-foreground)" }}>TechSystem v2.4 · Node: PRIMARY · Uptime: 99.98%</p>
      </div>

      {/* Stat Cards */}
      <div className="grid grid-cols-2 gap-4" style={{ gridTemplateColumns: "repeat(4, 1fr)" }}>
        {stats.map(s => (
          <div key={s.label} className="stat-card p-5 rounded">
            <p className="text-xs font-mono mb-2" style={{ color: "var(--muted-foreground)" }}>{s.label.toUpperCase()}</p>
            <p className="font-display text-3xl font-bold" style={{ color: s.color }}>{s.value}</p>
            <p className="text-xs mt-1" style={{ color: "var(--muted-foreground)" }}>{s.sub}</p>
          </div>
        ))}
      </div>

      <div className="grid gap-6" style={{ gridTemplateColumns: "1fr 1fr" }}>
        {/* Recent Builds */}
        <div className="card-bg rounded p-5">
          <h2 className="font-display text-lg font-bold mb-4" style={{ color: "var(--foreground)" }}>RECENT BUILDS</h2>
          <div className="space-y-3">
            {recentBuilds.map(b => (
              <div key={b.id} className="flex items-center justify-between py-2" style={{ borderBottom: "1px solid var(--border)" }}>
                <div>
                  <p className="font-medium text-sm">{b.name}</p>
                  <p className="text-xs mt-0.5" style={{ color: "var(--muted-foreground)" }}>{b.createdBy} · {b.purpose}</p>
                </div>
                <div className="flex items-center gap-3">
                  <span className="font-mono text-xs" style={{ color: "var(--primary)" }}>${b.totalCost.toLocaleString()}</span>
                  <BuildStatusBadge status={b.status} />
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Inventory by Category */}
        <div className="card-bg rounded p-5">
          <h2 className="font-display text-lg font-bold mb-4" style={{ color: "var(--foreground)" }}>INVENTORY BREAKDOWN</h2>
          <div className="space-y-3">
            {Object.entries(categoryCount).map(([cat, count]) => {
              const max = Math.max(...Object.values(categoryCount));
              const pct = (count / max) * 100;
              return (
                <div key={cat}>
                  <div className="flex justify-between text-xs mb-1">
                    <span style={{ color: "var(--foreground)" }}>{cat}</span>
                    <span className="font-mono" style={{ color: "var(--muted-foreground)" }}>{count} SKU{count !== 1 ? "s" : ""}</span>
                  </div>
                  <div className="progress-bar">
                    <div className="progress-fill" style={{ width: `${pct}%` }} />
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>

      {/* Low Stock Alert */}
      {lowStockParts.length > 0 && (
        <div className="card-bg rounded p-5" style={{ borderColor: "rgba(255, 170, 0, 0.25)", borderWidth: 1 }}>
          <h2 className="font-display text-lg font-bold mb-4" style={{ color: "var(--warning)" }}>STOCK ALERTS</h2>
          <div className="space-y-2">
            {lowStockParts.map(p => (
              <div key={p.id} className="flex items-center justify-between text-sm py-1.5" style={{ borderBottom: "1px solid var(--border)" }}>
                <div className="flex items-center gap-3">
                  <span className="font-mono text-xs" style={{ color: "var(--muted-foreground)" }}>{p.sku}</span>
                  <span>{p.name}</span>
                </div>
                <div className="flex items-center gap-3">
                  <span className="font-mono text-xs" style={{ color: "var(--muted-foreground)" }}>Qty: {p.stock}</span>
                  <StatusBadge status={p.status} />
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

// ─── Parts Page ────────────────────────────────────────────────────────────────
function PartsPage({ parts, setParts }: { parts: Part[]; setParts: (p: Part[]) => void }) {
  const [search, setSearch] = useState("");
  const [filterCat, setFilterCat] = useState<string>("All");
  const [modal, setModal] = useState<null | "add" | "edit">(null);
  const [editing, setEditing] = useState<Part | null>(null);
  const [form, setForm] = useState<Partial<Part>>({});

  const categories: string[] = ["All", "CPU", "GPU", "RAM", "Storage", "Motherboard", "PSU", "Case", "Cooling"];

  const filtered = parts.filter(p => {
    const matchSearch = p.name.toLowerCase().includes(search.toLowerCase()) ||
      p.brand.toLowerCase().includes(search.toLowerCase()) ||
      p.sku.toLowerCase().includes(search.toLowerCase());
    const matchCat = filterCat === "All" || p.category === filterCat;
    return matchSearch && matchCat;
  });

  const openAdd = () => {
    setForm({ category: "CPU", status: "In Stock", stock: 0, price: 0 });
    setModal("add");
  };
  const openEdit = (p: Part) => { setEditing(p); setForm({ ...p }); setModal("edit"); };
  const deletePart = (id: string) => setParts(parts.filter(p => p.id !== id));
  const saveForm = () => {
    if (modal === "add") {
      const newPart: Part = {
        id: `p${Date.now()}`,
        name: form.name || "",
        category: (form.category as Category) || "CPU",
        brand: form.brand || "",
        model: form.model || "",
        price: Number(form.price) || 0,
        stock: Number(form.stock) || 0,
        status: (form.status as Status) || "In Stock",
        specs: form.specs || "",
        sku: form.sku || `SKU-${Date.now()}`,
        added: new Date().toISOString().split("T")[0],
      };
      setParts([...parts, newPart]);
    } else if (editing) {
      setParts(parts.map(p => p.id === editing.id ? { ...p, ...form } as Part : p));
    }
    setModal(null);
  };

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-display text-3xl font-bold" style={{ color: "var(--primary)" }}>PARTS INVENTORY</h1>
          <p className="text-sm mt-0.5" style={{ color: "var(--muted-foreground)" }}>{parts.length} components registered</p>
        </div>
        <button className="btn-primary flex items-center gap-2" onClick={openAdd}><Icon.plus /> ADD PART</button>
      </div>

      {/* Filters */}
      <div className="flex gap-3 flex-wrap items-center">
        <div className="relative flex-1" style={{ minWidth: 200, maxWidth: 300 }}>
          <span className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: "var(--muted-foreground)" }}><Icon.search /></span>
          <input className="input-field" style={{ paddingLeft: "2.25rem" }} placeholder="Search parts, brands, SKU..." value={search} onChange={e => setSearch(e.target.value)} />
        </div>
        <div className="flex gap-1 flex-wrap">
          {categories.map(c => (
            <button key={c} onClick={() => setFilterCat(c)} className="text-xs font-mono px-3 py-1.5 rounded transition-all"
              style={{ background: filterCat === c ? "rgba(0,212,255,0.12)" : "var(--secondary)", color: filterCat === c ? "var(--primary)" : "var(--muted-foreground)", border: `1px solid ${filterCat === c ? "rgba(0,212,255,0.3)" : "var(--border)"}` }}>
              {c}
            </button>
          ))}
        </div>
      </div>

      {/* Table */}
      <div className="card-bg rounded overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr style={{ borderBottom: "1px solid var(--border)", background: "var(--muted)" }}>
              {["SKU", "Component", "Category", "Brand", "Price", "Stock", "Status", "Actions"].map(h => (
                <th key={h} className="text-left px-4 py-3 font-mono text-xs font-semibold" style={{ color: "var(--muted-foreground)" }}>{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {filtered.map(p => (
              <tr key={p.id} className="table-row-hover" style={{ borderBottom: "1px solid var(--border)" }}>
                <td className="px-4 py-3 font-mono text-xs" style={{ color: "var(--muted-foreground)" }}>{p.sku}</td>
                <td className="px-4 py-3">
                  <p className="font-medium">{p.name}</p>
                  <p className="text-xs mt-0.5 font-mono" style={{ color: "var(--muted-foreground)" }}>{p.specs.slice(0, 45)}{p.specs.length > 45 ? "…" : ""}</p>
                </td>
                <td className="px-4 py-3"><span className="font-mono text-xs px-2 py-0.5 rounded" style={{ background: "var(--secondary)", color: "var(--foreground)" }}>{p.category}</span></td>
                <td className="px-4 py-3 text-sm" style={{ color: "var(--foreground)" }}>{p.brand}</td>
                <td className="px-4 py-3 font-mono text-sm" style={{ color: "var(--primary)" }}>${p.price.toLocaleString()}</td>
                <td className="px-4 py-3 font-mono text-sm">{p.stock}</td>
                <td className="px-4 py-3"><StatusBadge status={p.status} /></td>
                <td className="px-4 py-3">
                  <div className="flex gap-2">
                    <button className="btn-ghost py-1 px-2 flex items-center gap-1" onClick={() => openEdit(p)}><Icon.edit /> Edit</button>
                    <button className="btn-danger py-1" onClick={() => deletePart(p.id)}><Icon.trash /></button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {filtered.length === 0 && <div className="py-12 text-center" style={{ color: "var(--muted-foreground)" }}>No parts found matching your criteria.</div>}
      </div>

      {/* Modal */}
      {modal && (
        <div className="modal-overlay" onClick={() => setModal(null)}>
          <div className="modal-box p-6" onClick={e => e.stopPropagation()}>
            <div className="flex justify-between items-center mb-5">
              <h2 className="font-display text-xl font-bold" style={{ color: "var(--primary)" }}>{modal === "add" ? "ADD NEW PART" : "EDIT PART"}</h2>
              <button onClick={() => setModal(null)} style={{ color: "var(--muted-foreground)" }}><Icon.close /></button>
            </div>
            <div className="grid grid-cols-2 gap-4">
              {([
                { label: "Component Name", key: "name", type: "text" },
                { label: "Brand", key: "brand", type: "text" },
                { label: "Model Number", key: "model", type: "text" },
                { label: "SKU", key: "sku", type: "text" },
                { label: "Price ($)", key: "price", type: "number" },
                { label: "Stock Qty", key: "stock", type: "number" },
              ] as { label: string; key: keyof Part; type: string }[]).map(f => (
                <div key={f.key}>
                  <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>{f.label}</label>
                  <input className="input-field" type={f.type} value={(form[f.key] as string | number) || ""} onChange={e => setForm({ ...form, [f.key]: e.target.value })} />
                </div>
              ))}
              <div>
                <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Category</label>
                <select className="input-field" value={form.category || "CPU"} onChange={e => setForm({ ...form, category: e.target.value as Category })}>
                  {["CPU", "GPU", "RAM", "Storage", "Motherboard", "PSU", "Case", "Cooling"].map(c => <option key={c}>{c}</option>)}
                </select>
              </div>
              <div>
                <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Status</label>
                <select className="input-field" value={form.status || "In Stock"} onChange={e => setForm({ ...form, status: e.target.value as Status })}>
                  {["In Stock", "Low Stock", "Out of Stock", "Discontinued"].map(s => <option key={s}>{s}</option>)}
                </select>
              </div>
              <div className="col-span-2">
                <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Specifications</label>
                <textarea className="input-field" rows={3} style={{ resize: "none" }} value={form.specs || ""} onChange={e => setForm({ ...form, specs: e.target.value })} />
              </div>
            </div>
            <div className="flex gap-3 mt-6 justify-end">
              <button className="btn-ghost" onClick={() => setModal(null)}>CANCEL</button>
              <button className="btn-primary" onClick={saveForm}>SAVE PART</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ─── Builds Page ───────────────────────────────────────────────────────────────
function BuildsPage({ builds, setBuilds, parts }: { builds: Build[]; setBuilds: (b: Build[]) => void; parts: Part[] }) {
  const [modal, setModal] = useState(false);
  const [viewBuild, setViewBuild] = useState<Build | null>(null);
  const [form, setForm] = useState<Partial<Build>>({});
  const [selectedParts, setSelectedParts] = useState<BuildPart[]>([]);

  const openAdd = () => { setForm({ status: "Draft" }); setSelectedParts([]); setModal(true); };

  const addPartToBuild = (partId: string) => {
    if (selectedParts.find(p => p.partId === partId)) return;
    setSelectedParts([...selectedParts, { partId, quantity: 1 }]);
  };
  const removePart = (partId: string) => setSelectedParts(selectedParts.filter(p => p.partId !== partId));

  const saveBuild = () => {
    const totalCost = selectedParts.reduce((s, bp) => {
      const p = parts.find(x => x.id === bp.partId);
      return s + (p ? p.price * bp.quantity : 0);
    }, 0);
    const nb: Build = {
      id: `b${Date.now()}`,
      name: form.name || "New Build",
      description: form.description || "",
      parts: selectedParts,
      status: (form.status as BuildStatus) || "Draft",
      totalCost,
      createdBy: "Current User",
      createdAt: new Date().toISOString().split("T")[0],
      purpose: form.purpose || "",
    };
    setBuilds([...builds, nb]);
    setModal(false);
  };

  const deleteBuild = (id: string) => setBuilds(builds.filter(b => b.id !== id));
  const updateStatus = (id: string, status: BuildStatus) =>
    setBuilds(builds.map(b => b.id === id ? { ...b, status } : b));

  const getPartById = (id: string) => parts.find(p => p.id === id);

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-display text-3xl font-bold" style={{ color: "var(--primary)" }}>COMPUTER BUILDS</h1>
          <p className="text-sm mt-0.5" style={{ color: "var(--muted-foreground)" }}>{builds.length} build configurations</p>
        </div>
        <button className="btn-primary flex items-center gap-2" onClick={openAdd}><Icon.plus /> NEW BUILD</button>
      </div>

      <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(320px, 1fr))" }}>
        {builds.map(b => {
          const buildParts = b.parts.map(bp => ({ part: getPartById(bp.partId), qty: bp.quantity })).filter(x => x.part);
          return (
            <div key={b.id} className="card-bg rounded p-5 flex flex-col gap-3" style={{ transition: "border-color 0.15s" }}
              onMouseEnter={e => (e.currentTarget.style.borderColor = "rgba(0,212,255,0.25)")}
              onMouseLeave={e => (e.currentTarget.style.borderColor = "")}>
              <div className="flex items-start justify-between">
                <div>
                  <h3 className="font-display text-lg font-bold">{b.name}</h3>
                  <p className="text-xs mt-0.5" style={{ color: "var(--muted-foreground)" }}>{b.purpose}</p>
                </div>
                <BuildStatusBadge status={b.status} />
              </div>
              <p className="text-sm" style={{ color: "var(--muted-foreground)" }}>{b.description}</p>
              <div className="space-y-1">
                {buildParts.slice(0, 4).map(({ part, qty }) => (
                  <div key={part!.id} className="flex justify-between text-xs py-1" style={{ borderBottom: "1px solid var(--border)" }}>
                    <span style={{ color: "var(--foreground)" }}>{part!.name}</span>
                    <span className="font-mono" style={{ color: "var(--muted-foreground)" }}>×{qty} · ${(part!.price * qty).toLocaleString()}</span>
                  </div>
                ))}
                {buildParts.length > 4 && <p className="text-xs" style={{ color: "var(--muted-foreground)" }}>+{buildParts.length - 4} more components</p>}
              </div>
              <div className="flex items-center justify-between pt-1">
                <div>
                  <p className="font-mono text-lg font-bold" style={{ color: "var(--accent)" }}>${b.totalCost.toLocaleString()}</p>
                  <p className="text-xs" style={{ color: "var(--muted-foreground)" }}>by {b.createdBy} · {b.createdAt}</p>
                </div>
                <div className="flex gap-2">
                  <button className="btn-ghost py-1 px-2 text-xs" onClick={() => setViewBuild(b)}>VIEW</button>
                  <button className="btn-danger py-1 px-2" onClick={() => deleteBuild(b.id)}><Icon.trash /></button>
                </div>
              </div>
              <div className="flex gap-1 flex-wrap">
                {(["Draft", "Active", "Completed", "Cancelled"] as BuildStatus[]).map(s => (
                  <button key={s} onClick={() => updateStatus(b.id, s)}
                    className="text-xs font-mono px-2 py-0.5 rounded transition-all"
                    style={{ background: b.status === s ? "rgba(0,212,255,0.12)" : "transparent", color: b.status === s ? "var(--primary)" : "var(--muted-foreground)", border: `1px solid ${b.status === s ? "rgba(0,212,255,0.3)" : "var(--border)"}` }}>
                    {s}
                  </button>
                ))}
              </div>
            </div>
          );
        })}
      </div>

      {/* View Build Modal */}
      {viewBuild && (
        <div className="modal-overlay" onClick={() => setViewBuild(null)}>
          <div className="modal-box p-6" style={{ maxWidth: 600 }} onClick={e => e.stopPropagation()}>
            <div className="flex justify-between items-center mb-1">
              <h2 className="font-display text-xl font-bold" style={{ color: "var(--primary)" }}>{viewBuild.name}</h2>
              <button onClick={() => setViewBuild(null)} style={{ color: "var(--muted-foreground)" }}><Icon.close /></button>
            </div>
            <p className="text-sm mb-4" style={{ color: "var(--muted-foreground)" }}>{viewBuild.description}</p>
            <div className="space-y-2 mb-4">
              {viewBuild.parts.map(bp => {
                const p = getPartById(bp.partId);
                if (!p) return null;
                return (
                  <div key={bp.partId} className="flex justify-between text-sm py-2.5 px-3 rounded" style={{ background: "var(--secondary)" }}>
                    <div>
                      <p className="font-medium">{p.name}</p>
                      <p className="text-xs font-mono mt-0.5" style={{ color: "var(--muted-foreground)" }}>{p.category} · {p.brand}</p>
                    </div>
                    <div className="text-right">
                      <p className="font-mono" style={{ color: "var(--primary)" }}>${(p.price * bp.quantity).toLocaleString()}</p>
                      <p className="text-xs font-mono" style={{ color: "var(--muted-foreground)" }}>×{bp.quantity} unit{bp.quantity > 1 ? "s" : ""}</p>
                    </div>
                  </div>
                );
              })}
            </div>
            <div className="flex justify-between pt-3" style={{ borderTop: "1px solid var(--border)" }}>
              <p className="text-sm" style={{ color: "var(--muted-foreground)" }}>Total Cost</p>
              <p className="font-display text-2xl font-bold" style={{ color: "var(--accent)" }}>${viewBuild.totalCost.toLocaleString()}</p>
            </div>
          </div>
        </div>
      )}

      {/* Add Build Modal */}
      {modal && (
        <div className="modal-overlay" onClick={() => setModal(false)}>
          <div className="modal-box p-6" style={{ maxWidth: 640 }} onClick={e => e.stopPropagation()}>
            <div className="flex justify-between items-center mb-5">
              <h2 className="font-display text-xl font-bold" style={{ color: "var(--primary)" }}>CREATE BUILD</h2>
              <button onClick={() => setModal(false)} style={{ color: "var(--muted-foreground)" }}><Icon.close /></button>
            </div>
            <div className="grid grid-cols-2 gap-4 mb-4">
              <div className="col-span-2">
                <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Build Name</label>
                <input className="input-field" placeholder="e.g. Workstation Pro X" value={form.name || ""} onChange={e => setForm({ ...form, name: e.target.value })} />
              </div>
              <div>
                <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Purpose</label>
                <input className="input-field" placeholder="e.g. Gaming, Workstation" value={form.purpose || ""} onChange={e => setForm({ ...form, purpose: e.target.value })} />
              </div>
              <div>
                <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Status</label>
                <select className="input-field" value={form.status || "Draft"} onChange={e => setForm({ ...form, status: e.target.value as BuildStatus })}>
                  {["Draft", "Active", "Completed"].map(s => <option key={s}>{s}</option>)}
                </select>
              </div>
              <div className="col-span-2">
                <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Description</label>
                <textarea className="input-field" rows={2} style={{ resize: "none" }} value={form.description || ""} onChange={e => setForm({ ...form, description: e.target.value })} />
              </div>
            </div>
            <div className="mb-4">
              <label className="text-xs font-mono mb-2 block" style={{ color: "var(--muted-foreground)" }}>ADD COMPONENTS</label>
              <div className="grid grid-cols-2 gap-1 max-h-40 overflow-y-auto pr-1">
                {parts.map(p => (
                  <button key={p.id} onClick={() => addPartToBuild(p.id)}
                    className="text-left text-xs px-3 py-2 rounded transition-all"
                    style={{ background: selectedParts.find(sp => sp.partId === p.id) ? "rgba(0,212,255,0.1)" : "var(--secondary)", border: `1px solid ${selectedParts.find(sp => sp.partId === p.id) ? "rgba(0,212,255,0.3)" : "var(--border)"}`, color: "var(--foreground)" }}>
                    <p className="font-medium truncate">{p.name}</p>
                    <p className="font-mono" style={{ color: "var(--primary)" }}>${p.price.toLocaleString()}</p>
                  </button>
                ))}
              </div>
            </div>
            {selectedParts.length > 0 && (
              <div className="mb-4 space-y-1">
                <label className="text-xs font-mono mb-2 block" style={{ color: "var(--muted-foreground)" }}>SELECTED ({selectedParts.length})</label>
                {selectedParts.map(sp => {
                  const p = getPartById(sp.partId);
                  return p ? (
                    <div key={sp.partId} className="flex justify-between items-center text-xs py-1.5 px-3 rounded" style={{ background: "var(--secondary)" }}>
                      <span>{p.name}</span>
                      <div className="flex items-center gap-2">
                        <span className="font-mono" style={{ color: "var(--primary)" }}>${p.price.toLocaleString()}</span>
                        <button onClick={() => removePart(sp.partId)} style={{ color: "var(--danger)" }}><Icon.close /></button>
                      </div>
                    </div>
                  ) : null;
                })}
                <div className="flex justify-between pt-2 px-1">
                  <span className="text-xs font-mono" style={{ color: "var(--muted-foreground)" }}>Estimated Total</span>
                  <span className="font-mono font-bold" style={{ color: "var(--accent)" }}>
                    ${selectedParts.reduce((s, bp) => { const p = getPartById(bp.partId); return s + (p ? p.price : 0); }, 0).toLocaleString()}
                  </span>
                </div>
              </div>
            )}
            <div className="flex gap-3 justify-end">
              <button className="btn-ghost" onClick={() => setModal(false)}>CANCEL</button>
              <button className="btn-primary" onClick={saveBuild}>CREATE BUILD</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ─── Users Page (Superadmin) ───────────────────────────────────────────────────
function UsersPage({ users, setUsers }: { users: User[]; setUsers: (u: User[]) => void }) {
  const [modal, setModal] = useState(false);
  const [form, setForm] = useState<Partial<User>>({});

  const saveUser = () => {
    const nu: User = {
      id: `u${Date.now()}`,
      name: form.name || "",
      email: form.email || "",
      role: (form.role as UserRole) || "Viewer",
      department: form.department || "",
      builds: 0,
      joined: new Date().toISOString().split("T")[0],
      active: true,
    };
    setUsers([...users, nu]);
    setModal(false);
  };

  const toggleActive = (id: string) => setUsers(users.map(u => u.id === id ? { ...u, active: !u.active } : u));
  const deleteUser = (id: string) => setUsers(users.filter(u => u.id !== id));

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-display text-3xl font-bold" style={{ color: "var(--primary)" }}>USER MANAGEMENT</h1>
          <p className="text-sm mt-0.5" style={{ color: "var(--muted-foreground)" }}>{users.length} system accounts · {users.filter(u => u.active).length} active</p>
        </div>
        <button className="btn-primary flex items-center gap-2" onClick={() => { setForm({ role: "Viewer" }); setModal(true); }}><Icon.plus /> ADD USER</button>
      </div>

      <div className="card-bg rounded overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr style={{ borderBottom: "1px solid var(--border)", background: "var(--muted)" }}>
              {["User", "Email", "Role", "Department", "Builds", "Joined", "Status", "Actions"].map(h => (
                <th key={h} className="text-left px-4 py-3 font-mono text-xs" style={{ color: "var(--muted-foreground)" }}>{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {users.map(u => (
              <tr key={u.id} className="table-row-hover" style={{ borderBottom: "1px solid var(--border)" }}>
                <td className="px-4 py-3">
                  <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-full flex items-center justify-center font-display font-bold text-sm"
                      style={{ background: "rgba(0,212,255,0.1)", color: "var(--primary)", border: "1px solid rgba(0,212,255,0.2)" }}>
                      {u.name.split(" ").map(n => n[0]).join("")}
                    </div>
                    <span className="font-medium">{u.name}</span>
                  </div>
                </td>
                <td className="px-4 py-3 font-mono text-xs" style={{ color: "var(--muted-foreground)" }}>{u.email}</td>
                <td className="px-4 py-3"><RoleBadge role={u.role} /></td>
                <td className="px-4 py-3 text-xs" style={{ color: "var(--foreground)" }}>{u.department}</td>
                <td className="px-4 py-3 font-mono text-xs" style={{ color: "var(--primary)" }}>{u.builds}</td>
                <td className="px-4 py-3 font-mono text-xs" style={{ color: "var(--muted-foreground)" }}>{u.joined}</td>
                <td className="px-4 py-3">
                  <button onClick={() => toggleActive(u.id)}
                    className="badge cursor-pointer"
                    style={{ background: u.active ? "rgba(57,255,20,0.1)" : "rgba(100,116,139,0.1)", color: u.active ? "var(--accent)" : "var(--muted-foreground)", border: `1px solid ${u.active ? "rgba(57,255,20,0.3)" : "var(--border)"}` }}>
                    {u.active ? "ACTIVE" : "INACTIVE"}
                  </button>
                </td>
                <td className="px-4 py-3">
                  <div className="flex gap-2">
                    <button className="btn-ghost py-1 px-2 text-xs">EDIT</button>
                    <button className="btn-danger py-1" onClick={() => deleteUser(u.id)}><Icon.trash /></button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {modal && (
        <div className="modal-overlay" onClick={() => setModal(false)}>
          <div className="modal-box p-6" onClick={e => e.stopPropagation()}>
            <div className="flex justify-between items-center mb-5">
              <h2 className="font-display text-xl font-bold" style={{ color: "var(--primary)" }}>ADD USER</h2>
              <button onClick={() => setModal(false)} style={{ color: "var(--muted-foreground)" }}><Icon.close /></button>
            </div>
            <div className="space-y-4">
              {[{ label: "Full Name", key: "name" }, { label: "Email", key: "email" }, { label: "Department", key: "department" }].map(f => (
                <div key={f.key}>
                  <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>{f.label}</label>
                  <input className="input-field" value={(form as Record<string, string>)[f.key] || ""} onChange={e => setForm({ ...form, [f.key]: e.target.value })} />
                </div>
              ))}
              <div>
                <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Role</label>
                <select className="input-field" value={form.role || "Viewer"} onChange={e => setForm({ ...form, role: e.target.value as UserRole })}>
                  {["Admin", "Technician", "Viewer"].map(r => <option key={r}>{r}</option>)}
                </select>
              </div>
            </div>
            <div className="flex gap-3 mt-6 justify-end">
              <button className="btn-ghost" onClick={() => setModal(false)}>CANCEL</button>
              <button className="btn-primary" onClick={saveUser}>CREATE USER</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ─── Catalog Page (User View) ─────────────────────────────────────────────────
function CatalogPage({ parts }: { parts: Part[] }) {
  const [search, setSearch] = useState("");
  const [cat, setCat] = useState("All");
  const [sortBy, setSortBy] = useState<"name" | "price">("name");

  const filtered = parts
    .filter(p => (cat === "All" || p.category === cat) && (p.name.toLowerCase().includes(search.toLowerCase()) || p.brand.toLowerCase().includes(search.toLowerCase())))
    .sort((a, b) => sortBy === "price" ? a.price - b.price : a.name.localeCompare(b.name));

  return (
    <div className="space-y-5">
      <div>
        <h1 className="font-display text-3xl font-bold" style={{ color: "var(--primary)" }}>PARTS CATALOG</h1>
        <p className="text-sm mt-0.5" style={{ color: "var(--muted-foreground)" }}>Browse available components</p>
      </div>
      <div className="flex gap-3 flex-wrap items-center">
        <div className="relative" style={{ minWidth: 220 }}>
          <span className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: "var(--muted-foreground)" }}><Icon.search /></span>
          <input className="input-field" style={{ paddingLeft: "2.25rem" }} placeholder="Search components..." value={search} onChange={e => setSearch(e.target.value)} />
        </div>
        <div className="flex gap-1 flex-wrap">
          {["All", "CPU", "GPU", "RAM", "Storage", "Motherboard", "PSU", "Case", "Cooling"].map(c => (
            <button key={c} onClick={() => setCat(c)} className="text-xs font-mono px-3 py-1.5 rounded transition-all"
              style={{ background: cat === c ? "rgba(0,212,255,0.12)" : "var(--secondary)", color: cat === c ? "var(--primary)" : "var(--muted-foreground)", border: `1px solid ${cat === c ? "rgba(0,212,255,0.3)" : "var(--border)"}` }}>
              {c}
            </button>
          ))}
        </div>
        <button onClick={() => setSortBy(sortBy === "name" ? "price" : "name")} className="btn-ghost text-xs">
          Sort: {sortBy === "name" ? "Name" : "Price"}
        </button>
      </div>

      <div className="grid gap-3" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(260px, 1fr))" }}>
        {filtered.map(p => (
          <div key={p.id} className="card-bg rounded p-4 flex flex-col gap-2 transition-all cursor-default"
            onMouseEnter={e => (e.currentTarget.style.borderColor = "rgba(0,212,255,0.3)")}
            onMouseLeave={e => (e.currentTarget.style.borderColor = "")}>
            <div className="flex justify-between items-start">
              <span className="text-xs font-mono px-2 py-0.5 rounded" style={{ background: "var(--secondary)", color: "var(--muted-foreground)" }}>{p.category}</span>
              <StatusBadge status={p.status} />
            </div>
            <div>
              <p className="font-display font-bold text-base">{p.name}</p>
              <p className="text-xs" style={{ color: "var(--muted-foreground)" }}>{p.brand} · {p.model}</p>
            </div>
            <p className="text-xs font-mono" style={{ color: "var(--muted-foreground)" }}>{p.specs}</p>
            <div className="flex justify-between items-center pt-1" style={{ borderTop: "1px solid var(--border)" }}>
              <p className="font-mono font-bold text-lg" style={{ color: "var(--primary)" }}>${p.price.toLocaleString()}</p>
              <p className="text-xs font-mono" style={{ color: "var(--muted-foreground)" }}>Stock: {p.stock}</p>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

// ─── Configurator Page (User View) ────────────────────────────────────────────
function ConfiguratorPage({ parts, builds, setBuilds }: { parts: Part[]; builds: Build[]; setBuilds: (b: Build[]) => void }) {
  const [selected, setSelected] = useState<Record<Category, string | null>>({
    CPU: null, GPU: null, RAM: null, Storage: null, Motherboard: null, PSU: null, Case: null, Cooling: null,
  });
  const [buildName, setBuildName] = useState("My Custom Build");

  const categories: Category[] = ["CPU", "Motherboard", "GPU", "RAM", "Storage", "PSU", "Case", "Cooling"];
  const totalCost = Object.values(selected).reduce((s, id) => {
    if (!id) return s;
    const p = parts.find(x => x.id === id);
    return s + (p ? p.price : 0);
  }, 0);

  const saveBuild = () => {
    const selectedParts: BuildPart[] = Object.values(selected).filter(Boolean).map(id => ({ partId: id!, quantity: 1 }));
    if (selectedParts.length === 0) return;
    const nb: Build = {
      id: `b${Date.now()}`,
      name: buildName,
      description: "Custom build from configurator",
      parts: selectedParts,
      status: "Draft",
      totalCost,
      createdBy: "Current User",
      createdAt: new Date().toISOString().split("T")[0],
      purpose: "Custom",
    };
    setBuilds([...builds, nb]);
    alert("Build saved!");
  };

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div>
          <h1 className="font-display text-3xl font-bold" style={{ color: "var(--primary)" }}>BUILD CONFIGURATOR</h1>
          <p className="text-sm mt-0.5" style={{ color: "var(--muted-foreground)" }}>Select components to create your build</p>
        </div>
        <div className="flex items-center gap-3">
          <input className="input-field" style={{ width: 220 }} value={buildName} onChange={e => setBuildName(e.target.value)} placeholder="Build name..." />
          <div className="text-right">
            <p className="font-mono text-xl font-bold" style={{ color: "var(--accent)" }}>${totalCost.toLocaleString()}</p>
            <p className="text-xs" style={{ color: "var(--muted-foreground)" }}>estimated</p>
          </div>
          <button className="btn-primary" onClick={saveBuild} disabled={totalCost === 0} style={{ opacity: totalCost === 0 ? 0.5 : 1 }}>SAVE BUILD</button>
        </div>
      </div>

      <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(340px, 1fr))" }}>
        {categories.map(cat => {
          const catParts = parts.filter(p => p.category === cat && p.status !== "Out of Stock" && p.status !== "Discontinued");
          const chosen = selected[cat] ? parts.find(p => p.id === selected[cat]) : null;
          return (
            <div key={cat} className="card-bg rounded p-4" style={{ borderColor: chosen ? "rgba(0,212,255,0.25)" : "" }}>
              <div className="flex items-center justify-between mb-3">
                <h3 className="font-display font-bold text-base" style={{ color: chosen ? "var(--primary)" : "var(--foreground)" }}>{cat}</h3>
                {chosen && <button className="text-xs" style={{ color: "var(--muted-foreground)" }} onClick={() => setSelected({ ...selected, [cat]: null })}>Clear</button>}
              </div>
              {chosen ? (
                <div className="p-3 rounded" style={{ background: "rgba(0,212,255,0.05)", border: "1px solid rgba(0,212,255,0.15)" }}>
                  <p className="font-medium text-sm">{chosen.name}</p>
                  <p className="text-xs font-mono mt-0.5" style={{ color: "var(--muted-foreground)" }}>{chosen.brand}</p>
                  <p className="font-mono font-bold mt-1" style={{ color: "var(--primary)" }}>${chosen.price.toLocaleString()}</p>
                </div>
              ) : (
                <div className="space-y-1 max-h-36 overflow-y-auto pr-1">
                  {catParts.length === 0 ? (
                    <p className="text-xs py-2" style={{ color: "var(--muted-foreground)" }}>No available parts</p>
                  ) : catParts.map(p => (
                    <button key={p.id} onClick={() => setSelected({ ...selected, [cat]: p.id })}
                      className="w-full text-left text-xs px-3 py-2 rounded transition-all flex justify-between items-center"
                      style={{ background: "var(--secondary)", border: "1px solid var(--border)", color: "var(--foreground)" }}
                      onMouseEnter={e => { e.currentTarget.style.borderColor = "rgba(0,212,255,0.3)"; }}
                      onMouseLeave={e => { e.currentTarget.style.borderColor = "var(--border)"; }}>
                      <span className="truncate mr-2">{p.name}</span>
                      <span className="font-mono shrink-0" style={{ color: "var(--primary)" }}>${p.price.toLocaleString()}</span>
                    </button>
                  ))}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ─── Audit Logs Page ──────────────────────────────────────────────────────────
function AuditLogsPage({ logs }: { logs: AuditLog[] }) {
  const [search, setSearch] = useState("");
  const [filterAction, setFilterAction] = useState("All");
  const [filterSeverity, setFilterSeverity] = useState("All");
  const [expanded, setExpanded] = useState<string | null>(null);

  const actions = ["All", "CREATE", "UPDATE", "DELETE", "LOGIN", "LOGOUT", "EXPORT", "PERMISSION_CHANGE", "STATUS_CHANGE"];
  const severities = ["All", "Info", "Warning", "Critical"];

  const filtered = logs.filter(l => {
    const matchSearch = l.user.toLowerCase().includes(search.toLowerCase()) ||
      l.target.toLowerCase().includes(search.toLowerCase()) ||
      l.details.toLowerCase().includes(search.toLowerCase()) ||
      l.ip.includes(search);
    const matchAction = filterAction === "All" || l.action === filterAction;
    const matchSev = filterSeverity === "All" || l.severity === filterSeverity;
    return matchSearch && matchAction && matchSev;
  });

  const severityColor = (s: AuditLog["severity"]) => ({
    Info: { bg: "rgba(0,212,255,0.08)", text: "var(--primary)", border: "rgba(0,212,255,0.2)" },
    Warning: { bg: "rgba(255,170,0,0.08)", text: "var(--warning)", border: "rgba(255,170,0,0.2)" },
    Critical: { bg: "rgba(255,59,92,0.08)", text: "var(--danger)", border: "rgba(255,59,92,0.2)" },
  }[s]);

  const actionColor = (a: AuditAction) => {
    if (a === "DELETE") return "var(--danger)";
    if (a === "PERMISSION_CHANGE") return "#a78bfa";
    if (a === "LOGIN" || a === "LOGOUT") return "var(--muted-foreground)";
    if (a === "CREATE") return "var(--accent)";
    if (a === "EXPORT") return "var(--warning)";
    return "var(--primary)";
  };

  const criticalCount = logs.filter(l => l.severity === "Critical").length;
  const warningCount = logs.filter(l => l.severity === "Warning").length;

  return (
    <div className="space-y-5">
      <div className="flex items-start justify-between flex-wrap gap-4">
        <div>
          <h1 className="font-display text-3xl font-bold" style={{ color: "var(--primary)" }}>AUDIT LOGS</h1>
          <p className="text-sm mt-0.5" style={{ color: "var(--muted-foreground)" }}>{logs.length} events recorded · immutable trail</p>
        </div>
        <div className="flex gap-3">
          <div className="stat-card px-4 py-2 rounded text-center">
            <p className="font-display text-xl font-bold" style={{ color: "var(--danger)" }}>{criticalCount}</p>
            <p className="text-xs font-mono" style={{ color: "var(--muted-foreground)" }}>CRITICAL</p>
          </div>
          <div className="stat-card px-4 py-2 rounded text-center">
            <p className="font-display text-xl font-bold" style={{ color: "var(--warning)" }}>{warningCount}</p>
            <p className="text-xs font-mono" style={{ color: "var(--muted-foreground)" }}>WARNING</p>
          </div>
          <div className="stat-card px-4 py-2 rounded text-center">
            <p className="font-display text-xl font-bold" style={{ color: "var(--accent)" }}>{logs.length - criticalCount - warningCount}</p>
            <p className="text-xs font-mono" style={{ color: "var(--muted-foreground)" }}>INFO</p>
          </div>
        </div>
      </div>

      {/* Filters */}
      <div className="flex gap-3 flex-wrap items-center">
        <div className="relative" style={{ minWidth: 240 }}>
          <span className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: "var(--muted-foreground)" }}><Icon.search /></span>
          <input className="input-field" style={{ paddingLeft: "2.25rem" }} placeholder="Search user, target, IP..." value={search} onChange={e => setSearch(e.target.value)} />
        </div>
        <div className="flex gap-1 flex-wrap">
          {severities.map(s => (
            <button key={s} onClick={() => setFilterSeverity(s)} className="text-xs font-mono px-3 py-1.5 rounded transition-all"
              style={{ background: filterSeverity === s ? "rgba(0,212,255,0.12)" : "var(--secondary)", color: filterSeverity === s ? "var(--primary)" : "var(--muted-foreground)", border: `1px solid ${filterSeverity === s ? "rgba(0,212,255,0.3)" : "var(--border)"}` }}>
              {s}
            </button>
          ))}
        </div>
        <select className="input-field text-xs" style={{ width: "auto", paddingRight: "2rem" }} value={filterAction} onChange={e => setFilterAction(e.target.value)}>
          {actions.map(a => <option key={a}>{a}</option>)}
        </select>
      </div>

      {/* Log Table */}
      <div className="card-bg rounded overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr style={{ borderBottom: "1px solid var(--border)", background: "var(--muted)" }}>
              {["Timestamp", "User", "Action", "Target", "IP Address", "Severity", ""].map(h => (
                <th key={h} className="text-left px-4 py-3 font-mono text-xs font-semibold" style={{ color: "var(--muted-foreground)" }}>{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {filtered.map(log => {
              const sc = severityColor(log.severity);
              const isOpen = expanded === log.id;
              return (
                <>
                  <tr key={log.id} className="table-row-hover cursor-pointer" style={{ borderBottom: isOpen ? "none" : "1px solid var(--border)" }} onClick={() => setExpanded(isOpen ? null : log.id)}>
                    <td className="px-4 py-3 font-mono text-xs" style={{ color: "var(--muted-foreground)" }}>{log.timestamp}</td>
                    <td className="px-4 py-3">
                      <p className="font-medium text-sm">{log.user}</p>
                      <p className="text-xs font-mono" style={{ color: "var(--muted-foreground)" }}>{log.userRole}</p>
                    </td>
                    <td className="px-4 py-3">
                      <span className="badge font-mono text-xs" style={{ background: "rgba(0,0,0,0.3)", color: actionColor(log.action), border: `1px solid ${actionColor(log.action)}33` }}>{log.action}</span>
                    </td>
                    <td className="px-4 py-3">
                      <p className="text-sm">{log.target}</p>
                      <p className="text-xs font-mono" style={{ color: "var(--muted-foreground)" }}>{log.targetType}</p>
                    </td>
                    <td className="px-4 py-3 font-mono text-xs" style={{ color: "var(--muted-foreground)" }}>{log.ip}</td>
                    <td className="px-4 py-3">
                      <span className="badge" style={{ background: sc.bg, color: sc.text, border: `1px solid ${sc.border}` }}>{log.severity}</span>
                    </td>
                    <td className="px-4 py-3 text-xs" style={{ color: "var(--muted-foreground)" }}>{isOpen ? "▲" : "▼"}</td>
                  </tr>
                  {isOpen && (
                    <tr key={`${log.id}-detail`} style={{ borderBottom: "1px solid var(--border)" }}>
                      <td colSpan={7} className="px-4 pb-3">
                        <div className="rounded px-4 py-3 font-mono text-xs" style={{ background: "var(--secondary)", color: "var(--foreground)", lineHeight: 1.7 }}>
                          <span style={{ color: "var(--muted-foreground)" }}>DETAILS › </span>{log.details}
                        </div>
                      </td>
                    </tr>
                  )}
                </>
              );
            })}
          </tbody>
        </table>
        {filtered.length === 0 && <div className="py-12 text-center font-mono text-sm" style={{ color: "var(--muted-foreground)" }}>No log entries match your filters.</div>}
      </div>
    </div>
  );
}

// ─── Privileges Page ──────────────────────────────────────────────────────────
function PrivilegesPage({ privileges, setPrivileges, users }: { privileges: Privilege[]; setPrivileges: (p: Privilege[]) => void; users: User[] }) {
  const [modal, setModal] = useState(false);
  const [editingPriv, setEditingPriv] = useState<Privilege | null>(null);
  const [form, setForm] = useState<Partial<Privilege>>({});

  const accessColors: Record<string, { bg: string; text: string; border: string }> = {
    Full: { bg: "rgba(57,255,20,0.08)", text: "var(--accent)", border: "rgba(57,255,20,0.25)" },
    Write: { bg: "rgba(0,212,255,0.08)", text: "var(--primary)", border: "rgba(0,212,255,0.25)" },
    Read: { bg: "rgba(148,163,184,0.08)", text: "#94a3b8", border: "rgba(148,163,184,0.2)" },
    None: { bg: "rgba(255,59,92,0.06)", text: "var(--danger)", border: "rgba(255,59,92,0.15)" },
  };

  const levelColors: Record<PrivilegeLevel, string> = {
    "Full Access": "var(--accent)",
    "Read/Write": "var(--primary)",
    "Read Only": "#94a3b8",
    "Restricted": "var(--danger)",
  };

  const allModules = ["Parts", "Builds", "Users", "Audit Logs", "Privileges", "Deletion"];

  const openEdit = (p: Privilege) => {
    setEditingPriv(p);
    setForm({ ...p });
    setModal(true);
  };

  const openAdd = () => {
    setEditingPriv(null);
    setForm({
      level: "Read Only",
      modules: allModules.map(name => ({ name, access: "Read" as const })),
      expiresAt: null,
      notes: "",
    });
    setModal(true);
  };

  const savePrivilege = () => {
    if (editingPriv) {
      setPrivileges(privileges.map(p => p.id === editingPriv.id ? { ...p, ...form } as Privilege : p));
    } else {
      const user = users.find(u => u.id === form.userId);
      const np: Privilege = {
        id: `prv${Date.now()}`,
        userId: form.userId || "",
        userName: user?.name || "Unknown",
        email: user?.email || "",
        role: user?.role || "Viewer",
        level: (form.level as PrivilegeLevel) || "Read Only",
        modules: (form.modules as Privilege["modules"]) || allModules.map(n => ({ name: n, access: "Read" as const })),
        grantedBy: "Alex Chen",
        grantedAt: new Date().toISOString().split("T")[0],
        expiresAt: form.expiresAt || null,
        notes: form.notes || "",
      };
      setPrivileges([...privileges, np]);
    }
    setModal(false);
  };

  const cycleAccess = (modName: string) => {
    const order: ("Full" | "Write" | "Read" | "None")[] = ["Full", "Write", "Read", "None"];
    const mods = (form.modules as Privilege["modules"]) || [];
    const idx = mods.findIndex(m => m.name === modName);
    if (idx === -1) return;
    const cur = mods[idx].access;
    const next = order[(order.indexOf(cur) + 1) % order.length];
    const updated = [...mods];
    updated[idx] = { ...updated[idx], access: next };
    setForm({ ...form, modules: updated });
  };

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-display text-3xl font-bold" style={{ color: "var(--primary)" }}>PRIVILEGES</h1>
          <p className="text-sm mt-0.5" style={{ color: "var(--muted-foreground)" }}>Access control and permission management</p>
        </div>
        <button className="btn-primary flex items-center gap-2" onClick={openAdd}><Icon.plus /> GRANT PRIVILEGE</button>
      </div>

      <div className="space-y-3">
        {privileges.map(prv => {
          const expired = prv.expiresAt && new Date(prv.expiresAt) < new Date();
          return (
            <div key={prv.id} className="card-bg rounded p-5" style={{ borderColor: expired ? "rgba(255,59,92,0.2)" : "" }}>
              <div className="flex items-start justify-between flex-wrap gap-4">
                <div className="flex items-center gap-4">
                  <div className="w-10 h-10 rounded-full flex items-center justify-center font-display font-bold"
                    style={{ background: "rgba(0,212,255,0.1)", color: "var(--primary)", border: "1px solid rgba(0,212,255,0.2)", fontSize: "0.9rem" }}>
                    {prv.userName.split(" ").map(n => n[0]).join("")}
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <p className="font-display font-bold text-base">{prv.userName}</p>
                      <RoleBadge role={prv.role} />
                      {expired && <span className="badge" style={{ background: "rgba(255,59,92,0.1)", color: "var(--danger)", border: "1px solid rgba(255,59,92,0.3)" }}>EXPIRED</span>}
                    </div>
                    <p className="text-xs font-mono mt-0.5" style={{ color: "var(--muted-foreground)" }}>{prv.email}</p>
                  </div>
                </div>
                <div className="flex items-center gap-4">
                  <div className="text-right">
                    <p className="font-display font-bold text-sm" style={{ color: levelColors[prv.level] }}>{prv.level}</p>
                    <p className="text-xs font-mono" style={{ color: "var(--muted-foreground)" }}>Granted by {prv.grantedBy} · {prv.grantedAt}</p>
                    {prv.expiresAt && <p className="text-xs font-mono" style={{ color: expired ? "var(--danger)" : "var(--warning)" }}>Expires: {prv.expiresAt}</p>}
                  </div>
                  <button className="btn-ghost flex items-center gap-1 text-xs" onClick={() => openEdit(prv)}><Icon.edit /> Edit</button>
                </div>
              </div>

              {/* Module access grid */}
              <div className="mt-4 grid gap-2" style={{ gridTemplateColumns: "repeat(6, 1fr)" }}>
                {prv.modules.map(m => {
                  const ac = accessColors[m.access];
                  return (
                    <div key={m.name} className="rounded px-2 py-2 text-center" style={{ background: ac.bg, border: `1px solid ${ac.border}` }}>
                      <p className="text-xs font-mono font-semibold" style={{ color: ac.text }}>{m.access}</p>
                      <p className="text-xs mt-0.5" style={{ color: "var(--muted-foreground)", fontSize: "0.65rem" }}>{m.name}</p>
                    </div>
                  );
                })}
              </div>

              {prv.notes && (
                <p className="mt-3 text-xs font-mono" style={{ color: "var(--muted-foreground)" }}>
                  <span style={{ color: "var(--primary)" }}>NOTE › </span>{prv.notes}
                </p>
              )}
            </div>
          );
        })}
      </div>

      {/* Modal */}
      {modal && (
        <div className="modal-overlay" onClick={() => setModal(false)}>
          <div className="modal-box p-6" style={{ maxWidth: 560 }} onClick={e => e.stopPropagation()}>
            <div className="flex justify-between items-center mb-5">
              <h2 className="font-display text-xl font-bold" style={{ color: "var(--primary)" }}>{editingPriv ? "EDIT PRIVILEGE" : "GRANT PRIVILEGE"}</h2>
              <button onClick={() => setModal(false)} style={{ color: "var(--muted-foreground)" }}><Icon.close /></button>
            </div>

            {!editingPriv && (
              <div className="mb-4">
                <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>User</label>
                <select className="input-field" value={form.userId || ""} onChange={e => setForm({ ...form, userId: e.target.value })}>
                  <option value="">— Select user —</option>
                  {users.map(u => <option key={u.id} value={u.id}>{u.name} ({u.email})</option>)}
                </select>
              </div>
            )}

            <div className="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Access Level</label>
                <select className="input-field" value={form.level || "Read Only"} onChange={e => setForm({ ...form, level: e.target.value as PrivilegeLevel })}>
                  {["Full Access", "Read/Write", "Read Only", "Restricted"].map(l => <option key={l}>{l}</option>)}
                </select>
              </div>
              <div>
                <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Expires (optional)</label>
                <input className="input-field" type="date" value={form.expiresAt || ""} onChange={e => setForm({ ...form, expiresAt: e.target.value || null })} />
              </div>
            </div>

            <div className="mb-4">
              <label className="text-xs font-mono mb-2 block" style={{ color: "var(--muted-foreground)" }}>MODULE PERMISSIONS <span style={{ color: "var(--primary)" }}>(click to cycle)</span></label>
              <div className="grid grid-cols-3 gap-2">
                {(form.modules as Privilege["modules"] || []).map(m => {
                  const ac = { Full: { bg: "rgba(57,255,20,0.08)", text: "var(--accent)", border: "rgba(57,255,20,0.25)" }, Write: { bg: "rgba(0,212,255,0.08)", text: "var(--primary)", border: "rgba(0,212,255,0.25)" }, Read: { bg: "rgba(148,163,184,0.08)", text: "#94a3b8", border: "rgba(148,163,184,0.2)" }, None: { bg: "rgba(255,59,92,0.06)", text: "var(--danger)", border: "rgba(255,59,92,0.15)" } }[m.access];
                  return (
                    <button key={m.name} onClick={() => cycleAccess(m.name)}
                      className="rounded px-3 py-2.5 text-center transition-all cursor-pointer"
                      style={{ background: ac.bg, border: `1px solid ${ac.border}` }}>
                      <p className="text-xs font-mono font-bold" style={{ color: ac.text }}>{m.access}</p>
                      <p className="text-xs mt-0.5" style={{ color: "var(--muted-foreground)" }}>{m.name}</p>
                    </button>
                  );
                })}
              </div>
            </div>

            <div className="mb-5">
              <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Notes</label>
              <textarea className="input-field" rows={2} style={{ resize: "none" }} value={form.notes || ""} onChange={e => setForm({ ...form, notes: e.target.value })} />
            </div>

            <div className="flex gap-3 justify-end">
              <button className="btn-ghost" onClick={() => setModal(false)}>CANCEL</button>
              <button className="btn-primary" onClick={savePrivilege}>SAVE PRIVILEGE</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ─── Deletion Requests Page ───────────────────────────────────────────────────
function DeletionRequestsPage({ requests, setRequests }: { requests: DeletionRequest[]; setRequests: (r: DeletionRequest[]) => void }) {
  const [modal, setModal] = useState(false);
  const [reviewModal, setReviewModal] = useState<DeletionRequest | null>(null);
  const [filterStatus, setFilterStatus] = useState("All");
  const [reviewNote, setReviewNote] = useState("");
  const [form, setForm] = useState<Partial<DeletionRequest>>({});

  const statusColors: Record<DeletionStatus, { bg: string; text: string; border: string }> = {
    Pending: { bg: "rgba(255,170,0,0.08)", text: "var(--warning)", border: "rgba(255,170,0,0.25)" },
    Approved: { bg: "rgba(57,255,20,0.08)", text: "var(--accent)", border: "rgba(57,255,20,0.25)" },
    Rejected: { bg: "rgba(255,59,92,0.08)", text: "var(--danger)", border: "rgba(255,59,92,0.25)" },
    Processing: { bg: "rgba(0,212,255,0.08)", text: "var(--primary)", border: "rgba(0,212,255,0.25)" },
  };

  const priorityColors: Record<DeletionRequest["priority"], string> = {
    Low: "var(--muted-foreground)",
    Medium: "var(--warning)",
    High: "var(--danger)",
    Critical: "#ff0040",
  };

  const filtered = requests.filter(r => filterStatus === "All" || r.status === filterStatus);

  const review = (id: string, action: "Approved" | "Rejected") => {
    setRequests(requests.map(r => r.id === id ? {
      ...r,
      status: action,
      reviewedBy: "Alex Chen",
      reviewedAt: new Date().toISOString().replace("T", " ").slice(0, 19),
      reviewNote: reviewNote || null,
    } : r));
    setReviewModal(null);
    setReviewNote("");
  };

  const submitRequest = () => {
    const nr: DeletionRequest = {
      id: `dr${Date.now()}`,
      requestedBy: "Alex Chen",
      requestedAt: new Date().toISOString().replace("T", " ").slice(0, 19),
      targetType: (form.targetType as DeletionRequest["targetType"]) || "Part",
      targetName: form.targetName || "",
      targetId: form.targetId || `id-${Date.now()}`,
      reason: form.reason || "",
      status: "Pending",
      reviewedBy: null,
      reviewedAt: null,
      reviewNote: null,
      priority: (form.priority as DeletionRequest["priority"]) || "Medium",
    };
    setRequests([nr, ...requests]);
    setModal(false);
    setForm({});
  };

  const pending = requests.filter(r => r.status === "Pending").length;
  const critical = requests.filter(r => r.priority === "Critical" && r.status === "Pending").length;

  return (
    <div className="space-y-5">
      <div className="flex items-start justify-between flex-wrap gap-4">
        <div>
          <h1 className="font-display text-3xl font-bold" style={{ color: "var(--primary)" }}>DELETION REQUESTS</h1>
          <p className="text-sm mt-0.5" style={{ color: "var(--muted-foreground)" }}>Review and approve data removal requests</p>
        </div>
        <div className="flex items-center gap-3">
          {pending > 0 && (
            <div className="flex items-center gap-2 px-3 py-1.5 rounded" style={{ background: "rgba(255,170,0,0.08)", border: "1px solid rgba(255,170,0,0.2)" }}>
              <span className="w-2 h-2 rounded-full" style={{ background: "var(--warning)" }} />
              <span className="font-mono text-xs" style={{ color: "var(--warning)" }}>{pending} PENDING{critical > 0 ? ` · ${critical} CRITICAL` : ""}</span>
            </div>
          )}
          <button className="btn-primary flex items-center gap-2" onClick={() => { setForm({ targetType: "Part", priority: "Medium" }); setModal(true); }}><Icon.plus /> NEW REQUEST</button>
        </div>
      </div>

      {/* Status Tabs */}
      <div className="flex gap-1">
        {["All", "Pending", "Processing", "Approved", "Rejected"].map(s => (
          <button key={s} onClick={() => setFilterStatus(s)} className="text-xs font-mono px-4 py-2 rounded transition-all"
            style={{ background: filterStatus === s ? "rgba(0,212,255,0.12)" : "var(--secondary)", color: filterStatus === s ? "var(--primary)" : "var(--muted-foreground)", border: `1px solid ${filterStatus === s ? "rgba(0,212,255,0.3)" : "var(--border)"}` }}>
            {s} {s !== "All" && `(${requests.filter(r => r.status === s).length})`}
          </button>
        ))}
      </div>

      {/* Request Cards */}
      <div className="space-y-3">
        {filtered.map(req => {
          const sc = statusColors[req.status];
          return (
            <div key={req.id} className="card-bg rounded p-5" style={{ borderColor: req.priority === "Critical" && req.status === "Pending" ? "rgba(255,0,64,0.3)" : "" }}>
              <div className="flex items-start justify-between flex-wrap gap-4">
                <div className="flex-1">
                  <div className="flex items-center gap-2 flex-wrap mb-1">
                    <span className="badge font-mono" style={{ background: sc.bg, color: sc.text, border: `1px solid ${sc.border}` }}>{req.status.toUpperCase()}</span>
                    <span className="badge font-mono" style={{ background: "var(--secondary)", color: priorityColors[req.priority], border: "1px solid var(--border)" }}>
                      {req.priority.toUpperCase()} PRIORITY
                    </span>
                    <span className="badge font-mono" style={{ background: "var(--secondary)", color: "var(--muted-foreground)", border: "1px solid var(--border)" }}>{req.targetType.toUpperCase()}</span>
                  </div>
                  <h3 className="font-display font-bold text-lg">{req.targetName}</h3>
                  <p className="text-xs font-mono mt-0.5" style={{ color: "var(--muted-foreground)" }}>ID: {req.targetId}</p>
                </div>
                {req.status === "Pending" && (
                  <div className="flex gap-2">
                    <button className="flex items-center gap-1.5 text-xs font-mono font-semibold px-3 py-1.5 rounded transition-all"
                      style={{ background: "rgba(57,255,20,0.08)", color: "var(--accent)", border: "1px solid rgba(57,255,20,0.25)" }}
                      onClick={() => { setReviewModal(req); setReviewNote(""); }}>
                      <Icon.check /> APPROVE
                    </button>
                    <button className="flex items-center gap-1.5 text-xs font-mono font-semibold px-3 py-1.5 rounded transition-all"
                      style={{ background: "rgba(255,59,92,0.08)", color: "var(--danger)", border: "1px solid rgba(255,59,92,0.25)" }}
                      onClick={() => { setReviewModal(req); setReviewNote(""); }}>
                      <Icon.x /> REJECT
                    </button>
                  </div>
                )}
              </div>

              <div className="mt-3 p-3 rounded" style={{ background: "var(--secondary)" }}>
                <p className="text-xs font-mono mb-1" style={{ color: "var(--muted-foreground)" }}>REASON FOR DELETION</p>
                <p className="text-sm">{req.reason}</p>
              </div>

              <div className="mt-3 flex flex-wrap gap-4 text-xs font-mono" style={{ color: "var(--muted-foreground)" }}>
                <span>Requested by <span style={{ color: "var(--foreground)" }}>{req.requestedBy}</span></span>
                <span>· {req.requestedAt}</span>
                {req.reviewedBy && (
                  <>
                    <span>· Reviewed by <span style={{ color: "var(--foreground)" }}>{req.reviewedBy}</span></span>
                    <span>· {req.reviewedAt}</span>
                  </>
                )}
              </div>

              {req.reviewNote && (
                <div className="mt-2 p-2.5 rounded" style={{ background: req.status === "Approved" ? "rgba(57,255,20,0.04)" : "rgba(255,59,92,0.04)", border: `1px solid ${req.status === "Approved" ? "rgba(57,255,20,0.15)" : "rgba(255,59,92,0.15)"}` }}>
                  <p className="text-xs font-mono" style={{ color: "var(--muted-foreground)" }}>REVIEW NOTE › <span style={{ color: "var(--foreground)" }}>{req.reviewNote}</span></p>
                </div>
              )}
            </div>
          );
        })}
        {filtered.length === 0 && <div className="py-12 text-center font-mono text-sm" style={{ color: "var(--muted-foreground)" }}>No deletion requests in this category.</div>}
      </div>

      {/* Review Modal */}
      {reviewModal && (
        <div className="modal-overlay" onClick={() => setReviewModal(null)}>
          <div className="modal-box p-6" style={{ maxWidth: 480 }} onClick={e => e.stopPropagation()}>
            <div className="flex justify-between items-center mb-4">
              <h2 className="font-display text-xl font-bold" style={{ color: "var(--primary)" }}>REVIEW REQUEST</h2>
              <button onClick={() => setReviewModal(null)} style={{ color: "var(--muted-foreground)" }}><Icon.close /></button>
            </div>
            <div className="p-3 rounded mb-4" style={{ background: "var(--secondary)" }}>
              <p className="font-medium">{reviewModal.targetName}</p>
              <p className="text-xs font-mono mt-0.5" style={{ color: "var(--muted-foreground)" }}>{reviewModal.targetType} · {reviewModal.targetId}</p>
              <p className="text-sm mt-2">{reviewModal.reason}</p>
            </div>
            <div className="mb-5">
              <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Review Note (optional)</label>
              <textarea className="input-field" rows={3} style={{ resize: "none" }} placeholder="Add a note for audit trail..." value={reviewNote} onChange={e => setReviewNote(e.target.value)} />
            </div>
            <div className="flex gap-3 justify-end">
              <button className="btn-ghost" onClick={() => setReviewModal(null)}>CANCEL</button>
              <button className="flex items-center gap-2 text-xs font-mono font-semibold px-4 py-2 rounded transition-all"
                style={{ background: "rgba(255,59,92,0.1)", color: "var(--danger)", border: "1px solid rgba(255,59,92,0.3)" }}
                onClick={() => review(reviewModal.id, "Rejected")}>
                <Icon.x /> REJECT
              </button>
              <button className="flex items-center gap-2 text-xs font-mono font-semibold px-4 py-2 rounded transition-all"
                style={{ background: "rgba(57,255,20,0.1)", color: "var(--accent)", border: "1px solid rgba(57,255,20,0.3)" }}
                onClick={() => review(reviewModal.id, "Approved")}>
                <Icon.check /> APPROVE
              </button>
            </div>
          </div>
        </div>
      )}

      {/* New Request Modal */}
      {modal && (
        <div className="modal-overlay" onClick={() => setModal(false)}>
          <div className="modal-box p-6" onClick={e => e.stopPropagation()}>
            <div className="flex justify-between items-center mb-5">
              <h2 className="font-display text-xl font-bold" style={{ color: "var(--primary)" }}>NEW DELETION REQUEST</h2>
              <button onClick={() => setModal(false)} style={{ color: "var(--muted-foreground)" }}><Icon.close /></button>
            </div>
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Target Type</label>
                  <select className="input-field" value={form.targetType || "Part"} onChange={e => setForm({ ...form, targetType: e.target.value as DeletionRequest["targetType"] })}>
                    {["Part", "Build", "User", "Data Export"].map(t => <option key={t}>{t}</option>)}
                  </select>
                </div>
                <div>
                  <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Priority</label>
                  <select className="input-field" value={form.priority || "Medium"} onChange={e => setForm({ ...form, priority: e.target.value as DeletionRequest["priority"] })}>
                    {["Low", "Medium", "High", "Critical"].map(p => <option key={p}>{p}</option>)}
                  </select>
                </div>
              </div>
              <div>
                <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Target Name</label>
                <input className="input-field" placeholder="e.g. RTX 4090, Build X..." value={form.targetName || ""} onChange={e => setForm({ ...form, targetName: e.target.value })} />
              </div>
              <div>
                <label className="text-xs font-mono mb-1.5 block" style={{ color: "var(--muted-foreground)" }}>Reason for Deletion</label>
                <textarea className="input-field" rows={4} style={{ resize: "none" }} placeholder="Provide a detailed reason..." value={form.reason || ""} onChange={e => setForm({ ...form, reason: e.target.value })} />
              </div>
            </div>
            <div className="flex gap-3 mt-6 justify-end">
              <button className="btn-ghost" onClick={() => setModal(false)}>CANCEL</button>
              <button className="btn-primary" onClick={submitRequest}>SUBMIT REQUEST</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ─── Main App ─────────────────────────────────────────────────────────────────
export default function App() {
  const [parts, setParts] = useState<Part[]>(initialParts);
  const [builds, setBuilds] = useState<Build[]>(initialBuilds);
  const [users, setUsers] = useState<User[]>(initialUsers);
  const [auditLogs] = useState<AuditLog[]>(initialAuditLogs);
  const [privileges, setPrivileges] = useState<Privilege[]>(initialPrivileges);
  const [deletionRequests, setDeletionRequests] = useState<DeletionRequest[]>(initialDeletionRequests);
  const [page, setPage] = useState<Page>("dashboard");
  const [viewMode, setViewMode] = useState<ViewMode>("admin");

  const pendingDeletions = deletionRequests.filter(r => r.status === "Pending").length;

  const adminNav: { id: Page; label: string; icon: () => React.ReactElement; badge?: number }[] = [
    { id: "dashboard", label: "Dashboard", icon: Icon.dashboard },
    { id: "parts", label: "Parts Inventory", icon: Icon.cpu },
    { id: "builds", label: "Builds", icon: Icon.build },
    { id: "users", label: "Users", icon: Icon.users },
    { id: "audit-logs", label: "Audit Logs", icon: Icon.log },
    { id: "privileges", label: "Privileges", icon: Icon.key },
    { id: "deletion-requests", label: "Deletion Requests", icon: Icon.trash2, badge: pendingDeletions },
  ];

  const userNav: { id: Page; label: string; icon: () => React.ReactElement }[] = [
    { id: "catalog", label: "Parts Catalog", icon: Icon.catalog },
    { id: "configurator", label: "Configurator", icon: Icon.build },
    { id: "my-builds", label: "My Builds", icon: Icon.star },
  ];

  const activeNav = viewMode === "admin" ? adminNav : userNav;

  const switchMode = (mode: ViewMode) => {
    setViewMode(mode);
    setPage(mode === "admin" ? "dashboard" : "catalog");
  };

  return (
    <div className="flex h-screen overflow-hidden" style={{ background: "var(--background)" }}>
      {/* Sidebar */}
      <aside className="sidebar flex flex-col shrink-0" style={{ width: 220, borderRight: "1px solid var(--border)", background: "var(--card)" }}>
        {/* Logo */}
        <div className="flex items-center gap-3 px-5 py-5" style={{ borderBottom: "1px solid var(--border)" }}>
          <div className="shrink-0" style={{ color: "var(--primary)" }}><Icon.chip /></div>
          <div className="sidebar-logo-text">
            <p className="font-display font-bold text-base leading-tight" style={{ color: "var(--primary)" }}>TECHSYS</p>
            <p className="font-mono text-xs" style={{ color: "var(--muted-foreground)" }}>v2.4.0</p>
          </div>
        </div>

        {/* Mode Toggle */}
        <div className="px-3 py-3" style={{ borderBottom: "1px solid var(--border)" }}>
          <div className="flex rounded overflow-hidden" style={{ border: "1px solid var(--border)" }}>
            <button onClick={() => switchMode("admin")} className="flex-1 py-1.5 text-xs font-mono font-semibold transition-all"
              style={{ background: viewMode === "admin" ? "rgba(0,212,255,0.15)" : "transparent", color: viewMode === "admin" ? "var(--primary)" : "var(--muted-foreground)" }}>
              ADMIN
            </button>
            <button onClick={() => switchMode("user")} className="flex-1 py-1.5 text-xs font-mono font-semibold transition-all"
              style={{ background: viewMode === "user" ? "rgba(0,212,255,0.15)" : "transparent", color: viewMode === "user" ? "var(--primary)" : "var(--muted-foreground)", borderLeft: "1px solid var(--border)" }}>
              USER
            </button>
          </div>
        </div>

        {/* Nav */}
        <nav className="flex-1 py-3 space-y-0.5 px-2">
          {viewMode === "admin" && (
            <div className="px-2 pb-1">
              <div className="flex items-center gap-1.5">
                <Icon.shield />
                <span className="text-xs font-mono font-semibold" style={{ color: "var(--muted-foreground)" }}>SUPERADMIN</span>
              </div>
            </div>
          )}
          {activeNav.map(item => (
            <button key={item.id} onClick={() => setPage(item.id)}
              className={`nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded text-sm ${page === item.id ? "nav-item-active" : ""}`}
              style={{ color: page === item.id ? "var(--primary)" : "var(--muted-foreground)" }}>
              <item.icon />
              <span className="sidebar-label font-medium flex-1 text-left">{item.label}</span>
              {("badge" in item) && (item as { badge?: number }).badge ? (
                <span className="sidebar-label font-mono text-xs px-1.5 py-0.5 rounded font-bold" style={{ background: "rgba(255,170,0,0.15)", color: "var(--warning)", border: "1px solid rgba(255,170,0,0.25)" }}>{(item as { badge?: number }).badge}</span>
              ) : null}
            </button>
          ))}
        </nav>

        {/* Footer */}
        <div className="px-4 py-4" style={{ borderTop: "1px solid var(--border)" }}>
          <div className="flex items-center gap-2.5">
            <div className="w-7 h-7 rounded-full flex items-center justify-center text-xs font-display font-bold"
              style={{ background: "rgba(0,212,255,0.15)", color: "var(--primary)", border: "1px solid rgba(0,212,255,0.2)" }}>
              {viewMode === "admin" ? "SA" : "U"}
            </div>
            <div className="sidebar-logo-text">
              <p className="text-xs font-semibold" style={{ color: "var(--foreground)" }}>{viewMode === "admin" ? "Super Admin" : "User"}</p>
              <div className="flex items-center gap-1">
                <span className="w-1.5 h-1.5 rounded-full inline-block" style={{ background: "var(--accent)" }} />
                <p className="text-xs font-mono" style={{ color: "var(--muted-foreground)" }}>Online</p>
              </div>
            </div>
          </div>
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 overflow-y-auto p-6">
        {page === "dashboard" && <DashboardPage parts={parts} builds={builds} users={users} />}
        {page === "parts" && <PartsPage parts={parts} setParts={setParts} />}
        {page === "builds" && <BuildsPage builds={builds} setBuilds={setBuilds} parts={parts} />}
        {page === "users" && <UsersPage users={users} setUsers={setUsers} />}
        {page === "audit-logs" && <AuditLogsPage logs={auditLogs} />}
        {page === "privileges" && <PrivilegesPage privileges={privileges} setPrivileges={setPrivileges} users={users} />}
        {page === "deletion-requests" && <DeletionRequestsPage requests={deletionRequests} setRequests={setDeletionRequests} />}
        {page === "catalog" && <CatalogPage parts={parts} />}
        {page === "configurator" && <ConfiguratorPage parts={parts} builds={builds} setBuilds={setBuilds} />}
        {page === "my-builds" && (
          <div className="space-y-5">
            <div>
              <h1 className="font-display text-3xl font-bold" style={{ color: "var(--primary)" }}>MY BUILDS</h1>
              <p className="text-sm mt-0.5" style={{ color: "var(--muted-foreground)" }}>Your saved build configurations</p>
            </div>
            <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(300px, 1fr))" }}>
              {builds.map(b => (
                <div key={b.id} className="card-bg rounded p-5" style={{ transition: "border-color 0.15s" }}
                  onMouseEnter={e => (e.currentTarget.style.borderColor = "rgba(0,212,255,0.25)")}
                  onMouseLeave={e => (e.currentTarget.style.borderColor = "")}>
                  <div className="flex justify-between mb-2">
                    <h3 className="font-display font-bold">{b.name}</h3>
                    <BuildStatusBadge status={b.status} />
                  </div>
                  <p className="text-sm mb-3" style={{ color: "var(--muted-foreground)" }}>{b.description}</p>
                  <p className="font-mono text-xl font-bold" style={{ color: "var(--primary)" }}>${b.totalCost.toLocaleString()}</p>
                  <p className="text-xs font-mono mt-1" style={{ color: "var(--muted-foreground)" }}>{b.parts.length} components · {b.createdAt}</p>
                </div>
              ))}
            </div>
          </div>
        )}
      </main>
    </div>
  );
}
