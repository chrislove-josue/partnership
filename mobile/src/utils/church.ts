import type { Church } from '../api/types';

/**
 * Église proposée par défaut dans les formulaires (don, partenaire, cellule,
 * rapport) : celle qui est associée au compte connecté, si elle fait partie des
 * églises accessibles. Sinon, la première église de la liste.
 */
export function defaultChurchId(
  churches: Church[],
  assignedChurchId?: number | null,
): string {
  // Pour les formulaires qui écrivent une donnée (don, partenaire, cellule),
  // il faut une église locale : un groupe/sous-groupe ne reçoit pas de dons
  // directs et ne peut pas porter de cellule.
  if (assignedChurchId) {
    const assigned = churches.find((church) => church.id === assignedChurchId);
    if (assigned?.type === 'LOCAL') return String(assigned.id);

    const child = churches.find((church) => church.parent_id === assignedChurchId && church.type === 'LOCAL');
    if (child) return String(child.id);
  }

  const local = churches.find((church) => church.type === 'LOCAL');
  if (local) return String(local.id);

  return churches[0] ? String(churches[0].id) : '';
}

/** Église locale à utiliser par défaut pour les créations (don, partenaire, cellule). */
export function defaultLocalChurchId(
  churches: Church[],
  assignedChurchId?: number | null,
): string {
  return defaultChurchId(churches, assignedChurchId);
}
