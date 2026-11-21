import { ApiError, api, getAuthToken, getErrorMessage } from '../api/client';
import {
  addConflict,
  countPendingChanges,
  clearEntityPending,
  deleteEntity,
  getMeta,
  initDatabase,
  isEntityPending,
  listOutbox,
  recordOutboxAttempt,
  removeOutboxEntry,
  setMeta,
  SYNCABLE_ENTITIES,
  upsertEntity,
  type EntityName,
} from './database';

export interface SyncSummary {
  pushed: number;
  pulled: number;
  conflicts: number;
  pending: number;
  skipped: boolean;
}

interface DeletionEntry {
  id: number;
  entity: string;
  entity_id: string;
  deleted_at: string;
}

interface DeletionsResponse {
  data: DeletionEntry[];
  has_more: boolean;
  server_time: string;
}

interface EntityPage {
  data: Record<string, unknown>[];
  has_more?: boolean;
}

const PAGE_SIZE = 500;
const CONFLICT_STATUSES = [403, 404, 409, 422];

const SINGULAR_NAMES: Record<EntityName, string> = {
  churches: 'church',
  cellules: 'cellule',
  categories: 'category',
  partners: 'partner',
  donations: 'donation',
};

function recordFromResponse(entity: EntityName, result: unknown): Record<string, unknown> | null {
  if (!result || typeof result !== 'object') {
    return null;
  }
  const candidate = (result as Record<string, unknown>)[SINGULAR_NAMES[entity]];
  return candidate && typeof candidate === 'object' ? (candidate as Record<string, unknown>) : null;
}

function numericId(value: unknown): number | null {
  const id = Number(value);
  return value !== undefined && value !== null && Number.isFinite(id) ? id : null;
}

async function pushOutbox(): Promise<{ pushed: number; conflicts: number }> {
  let pushed = 0;
  let conflicts = 0;

  for (const entry of await listOutbox()) {
    let payload: Record<string, unknown> = {};
    try {
      payload = entry.payload ? (JSON.parse(entry.payload) as Record<string, unknown>) : {};
    } catch {
      // Une entrée corrompue ne doit pas bloquer toutes les autres.
      payload = {};
    }
    const path = entry.entity_id.startsWith('local-')
      ? `/${entry.entity}`
      : `/${entry.entity}/${entry.entity_id}`;

    try {
      let result: unknown;
      if (entry.operation === 'create') {
        result = await api(path, { method: 'POST', body: payload, skipOffline: true });
      } else if (entry.operation === 'update') {
        result = await api(path, { method: 'PATCH', body: payload, skipOffline: true });
      } else {
        result = await api(path, { method: 'DELETE', skipOffline: true });
      }

      const created = recordFromResponse(entry.entity, result);
      if (
        entry.entity_id.startsWith('local-')
        && created
        && (typeof created.id === 'string' || typeof created.id === 'number')
      ) {
        // Remplace l'identifiant temporaire par celui du serveur pour éviter un doublon.
        await deleteEntity(entry.entity, entry.entity_id);
        await upsertEntity(entry.entity, created.id, created);
      } else if (entry.operation === 'delete') {
        // La suppression est confirmée : on retire définitivement la copie locale.
        await deleteEntity(entry.entity, entry.entity_id);
      } else {
        await clearEntityPending(entry.entity, entry.entity_id);
      }

      await removeOutboxEntry(entry.id);
      pushed += 1;
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        throw error;
      }

      if (error instanceof ApiError && CONFLICT_STATUSES.includes(error.status)) {
        // Le serveur reste la source de vérité : la copie locale est retirée et
        // sera restaurée par le prochain rapatriement.
        await addConflict(
          entry.entity,
          entry.entity_id,
          getErrorMessage(error, 'La modification locale a été ignorée.'),
        );
        await deleteEntity(entry.entity, entry.entity_id);
        await removeOutboxEntry(entry.id);
        conflicts += 1;
        continue;
      }

      await recordOutboxAttempt(entry.id, getErrorMessage(error, 'Synchronisation impossible.'));
      break;
    }
  }

  return { pushed, conflicts };
}

async function pullDeletions(since: string): Promise<string> {
  let afterId = 0;
  let serverTime = new Date().toISOString();

  do {
    const params = new URLSearchParams({ limit: String(PAGE_SIZE) });
    if (since) {
      params.set('since', since);
    }
    if (afterId > 0) {
      params.set('after_id', String(afterId));
    }

    const response = await api<DeletionsResponse>(`/sync/deletions?${params}`, { skipOffline: true });
    serverTime = response.server_time || serverTime;

    let lastId = 0;
    for (const deletion of response.data) {
      if ((SYNCABLE_ENTITIES as string[]).includes(deletion.entity)) {
        await deleteEntity(deletion.entity as EntityName, deletion.entity_id);
      }
      const id = numericId(deletion.id);
      if (id !== null) {
        lastId = Math.max(lastId, id);
      }
    }

    afterId = response.has_more && lastId > 0 ? lastId : 0;
  } while (afterId > 0);

  return serverTime;
}

async function pullEntity(entity: EntityName, since: string): Promise<number> {
  let afterId = 0;
  let pulled = 0;

  do {
    const params = new URLSearchParams({ sync: '1', limit: String(PAGE_SIZE) });
    if (since) {
      params.set('updated_after', since);
    }
    if (afterId > 0) {
      params.set('after_id', String(afterId));
    }

    const response = await api<EntityPage>(`/${entity}?${params}`, { skipOffline: true });
    let lastId = 0;

    for (const row of response.data) {
      const id = numericId(row.id);
      if (id === null) {
        continue;
      }
      lastId = Math.max(lastId, id);

      // Une modification locale non encore transmise ne doit pas être écrasée.
      if (await isEntityPending(entity, id)) {
        continue;
      }
      await upsertEntity(
        entity,
        id,
        row,
        typeof row.updated_at === 'string' ? row.updated_at : null,
      );
      pulled += 1;
    }

    afterId = response.has_more && lastId > 0 ? lastId : 0;
  } while (afterId > 0);

  return pulled;
}

async function pullChanges(since: string): Promise<number> {
  // Les suppressions sont traitées en premier : une entité supprimée côté serveur
  // ne doit pas être réintroduite par la liste des modifications.
  const serverTime = await pullDeletions(since);

  let pulled = 0;
  for (const entity of SYNCABLE_ENTITIES) {
    pulled += await pullEntity(entity, since);
  }

  await setMeta('last_sync', serverTime);
  return pulled;
}

async function runSync(): Promise<SyncSummary> {
  await initDatabase();

  if (!getAuthToken()) {
    return { pushed: 0, pulled: 0, conflicts: 0, pending: await countPendingChanges(), skipped: true };
  }

  const { pushed, conflicts } = await pushOutbox();
  const pulled = await pullChanges((await getMeta('last_sync')) ?? '');

  return {
    pushed,
    pulled,
    conflicts,
    pending: await countPendingChanges(),
    skipped: false,
  };
}

let activeSync: Promise<SyncSummary> | null = null;

/** Pousse les modifications locales puis récupère les changements du serveur. */
export async function syncNow(): Promise<SyncSummary> {
  if (!activeSync) {
    activeSync = runSync().finally(() => {
      activeSync = null;
    });
  }
  return activeSync;
}
