export type Role =
  | 'SUPER_ADMIN'
  | 'GROUP_PASTOR'
  | 'SUBGROUP_PASTOR'
  | 'LOCAL_PASTOR'
  | 'STAFF';

export interface User {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  handle_kc?: string | null;
  role?: Role | string;
  roles?: string[];
  permissions?: string[];
  assigned_church_id?: number | null;
  assigned_church?: { id: number; name: string; type: string } | string | null;
  is_active?: boolean;
}

/** Vrai pour un super administrateur, y compris quand le rôle est cumulé. */
export function isSuperAdmin(user: User | null | undefined): boolean {
  return user?.role === 'SUPER_ADMIN' || (user?.roles ?? []).includes('SUPER_ADMIN');
}

export interface RoleOption {
  id: number;
  name: string;
  label: string;
}

export interface Church {
  id: number;
  name: string;
  type: string;
  parent_id?: number | null;
  parent_name?: string | null;
  hierarchy_path?: string | null;
  parent?: string | null;
  manager_id?: number | null;
  total_donations?: number;
  is_active?: boolean;
}

export interface Partner {
  id: number;
  first_name: string;
  last_name: string;
  full_name?: string;
  email?: string | null;
  phone?: string | null;
  handle_kc?: string | null;
  address?: string | null;
  birthdate?: string | null;
  church_id: number;
  church?: string | null;
  cellule_id?: number | null;
  cellule?: string | null;
  is_user?: boolean;
  total_donations?: number;
}

export interface Cellule {
  id: number;
  name: string;
  church_id: number;
  church?: string | null;
  leader_id?: number | null;
  leader?: string | null;
  total_donations?: number;
}

export interface Category {
  id: number;
  name: string;
  color: string;
  group?: string | null;
  group_label?: string | null;
}

export interface Donation {
  id: number;
  amount: number;
  date: string;
  church_id: number;
  church?: string | null;
  worship_church_id?: number | null;
  worship_church?: string | null;
  partner_id: number;
  partner?: string | null;
  category_id: number;
  category?: string | null;
  color?: string | null;
  is_active?: boolean;
}

export interface ImportResult {
  imported: number;
  skipped: number;
  error_count: number;
  errors: string[];
  total: number;
  message: string;
}

export interface LoginHistoryEntry {
  id: number;
  event: 'login' | 'logout';
  event_label: string;
  source: 'web' | 'mobile';
  source_label: string;
  ip_address: string | null;
  user_agent: string | null;
  happened_at: string;
}

export interface AppNotification {
  id: string;
  type: string;
  title: string;
  message: string;
  data?: Record<string, unknown>;
  is_read: boolean;
  created_at: string;
}

export interface BirthdayEntry {
  day: number;
  date: string;
  name: string;
  age: number | null;
  church: string | null;
  cellule: string | null;
  phone: string | null;
  email: string | null;
  is_user: boolean;
}

export interface Recipient {
  name: string;
  email: string;
  role?: string;
}

export interface ReportTotals {
  amount: number;
  donationCount: number;
  partnersWithDonation: number;
  partnersWithoutDonation: number;
  registeredPartners: number;
}

export interface PartnershipReport {
  church: { id: number; name: string; type: string; parentName?: string | null; hierarchyPath?: string | null };
  period: { label: string; from: string; to: string; type: string };
  totals: ReportTotals;
  categories: Array<{ name: string; color: string; group?: string | null; group_label?: string | null; amount: number; count: number }>;
  partners: Array<{ name: string; amount: number; count: number; cellule?: string | null }>;
  partnersWithoutDonation: Array<{ name: string; cellule?: string | null }>;
  donations: Array<{ amount: number; date: string; category: string; partner: string }>;
}

export interface RecipientsPreview {
  recipients: Recipient[];
  cc: Recipient[];
  targetLabel: string;
  church: PartnershipReport['church'];
  period: PartnershipReport['period'];
  totals: ReportTotals;
  confirmRequired: boolean;
}
