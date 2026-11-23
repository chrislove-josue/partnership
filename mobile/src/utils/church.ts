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
  if (assignedChurchId && churches.some((church) => church.id === assignedChurchId)) {
    return String(assignedChurchId);
  }

  return churches[0] ? String(churches[0].id) : '';
}
