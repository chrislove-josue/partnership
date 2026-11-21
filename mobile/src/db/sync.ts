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
  entity: string;
  entity_id: string;
  deleted_at: string;
}

interface DeletionsResponse {
  data: DeletionEntry[];
  server_time: string;
}

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

async function pushOutbox(): Promise<{ pushed: number; conflicts: number }> {
  let pushed = 0;
  let conflicts = 0;

  for (const entry of await listOutbox()) {
    const payload = entry.payload ? (JSON.parse(entry.payload) as Record<string, unknown>) : {};
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
      } else {
        await clearEntityPending(entry.entity, entry.entity_id);
      }

      await removeOutboxEntry(entry.id);
      pushed += 1;
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        throw error;
      }

      if (error instanceof ApiError && [403, 404, 422].includes(error.status)) {
        await addConflict(
          entry.entity,
          entry.entity_id,
          getErrorMessage(error, 'La modification locale a été ignorée.'),
        );
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

async function pullChanges(since: string): Promise<number> {
  const query = since ? `?since=${encodeURIComponent(since)}` : '';
  const deletions = await api<DeletionsResponse>(`/sync/deletions${query}`, { skipOffline: true });

  for (const deletion of deletions.data) {
    if ((SYNCABLE_ENTITIES as string[]).includes(deletion.entity)) {
      await deleteEntity(deletion.entity as EntityName, deletion.entity_id);
    }
  }

  let pulled = 0;
  for (const entity of SYNCABLE_ENTITIES) {
    const suffix = since ? `?updated_after=${encodeURIComponent(since)}` : '';
    const response = await api<{ data: Record<string, unknown>[] }>(`/${entity}${suffix}`, {
      skipOffline: true,
    });

    for (const row of response.data) {
      if (typeof row.id !== 'string' && typeof row.id !== 'number') {
        continue;
      }
      // Une modification locale non encore transmise ne doit pas être écrasée.
      if (await isEntityPending(entity, row.id)) {
        continue;
      }
      await upsertEntity(
        entity,
        row.id,
        row,
        typeof row.updated_at === 'string' ? row.updated_at : null,
      );
      pulled += 1;
    }
  }

  await setMeta('last_sync', deletions.server_time);
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
