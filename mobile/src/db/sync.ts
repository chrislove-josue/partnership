import { ApiError, api, getAuthToken, getErrorMessage } from '../api/client';
import {
  addConflict,
  countPendingChanges,
  deleteEntity,
  enqueueChange,
  getMeta,
  initDatabase,
  listOutbox,
  recordOutboxAttempt,
  removeOutboxEntry,
  setMeta,
  SYNCABLE_ENTITIES,
  upsertEntity,
  type EntityName,
} from './database';
import { backupDatabase } from './storage';

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
      if (entry.operation === 'create') {
        await api(path, { method: 'POST', body: payload, skipOffline: true });
      } else if (entry.operation === 'update') {
        await api(path, { method: 'PATCH', body: payload, skipOffline: true });
      } else {
        await api(path, { method: 'DELETE', skipOffline: true });
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
      if (row.id === undefined || row.id === null) {
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
  await backupDatabase();

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
