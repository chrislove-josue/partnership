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
  assigned_church?: { id: number; name: string; type: string } | null;
  is_active?: boolean;
}

export interface Role {
  id: number;
  name: string;
  label: string;
}

export interface Church {
  id: number;
  name: string;
  type: string;
  parent_id?: number | null;
  parent?: string | null;
  manager_id?: number | null;
  total_donations?: number;
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
}

export interface Donation {
  id: number;
  amount: number;
  date: string;
  church_id: number;
  church?: string | null;
  partner_id: number;
  partner?: string | null;
  category_id: number;
  category?: string | null;
  color?: string | null;
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
  church: { id: number; name: string; type: string; parentName?: string | null };
  period: { label: string; from: string; to: string; type: string };
  totals: ReportTotals;
  categories: Array<{ name: string; color: string; amount: number; count: number }>;
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
