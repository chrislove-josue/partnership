import { openDatabaseAsync, type SQLiteDatabase } from 'expo-sqlite';
import { backupDatabase, ensureStorageAccess, restoreDatabaseBackup } from './storage';

export type EntityName = 'churches' | 'cellules' | 'categories' | 'partners' | 'donations';
export type SyncOperation = 'create' | 'update' | 'delete';

export const SYNCABLE_ENTITIES: EntityName[] = [
  'churches',
  'cellules',
  'categories',
  'partners',
  'donations',
];

interface EntityRow {
  data: string;
  updated_at: string | null;
}

export interface OutboxEntry {
  id: number;
  entity: EntityName;
  operation: SyncOperation;
  entity_id: string;
  payload: string | null;
  attempts: number;
  last_error: string | null;
}

export interface ConflictEntry {
  id: number;
  entity: EntityName;
  entity_id: string;
  message: string;
  created_at: string;
}

const DATABASE_NAME = 'finance.sqlite';

const SCHEMA = `
  CREATE TABLE IF NOT EXISTS entities (
    entity TEXT NOT NULL,
    id TEXT NOT NULL,
    data TEXT NOT NULL,
    updated_at TEXT,
    deleted INTEGER NOT NULL DEFAULT 0,
    pending INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (entity, id)
  );
  CREATE TABLE IF NOT EXISTS outbox (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entity TEXT NOT NULL,
    operation TEXT NOT NULL,
    entity_id TEXT NOT NULL,
    payload TEXT,
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error TEXT,
    created_at TEXT NOT NULL
  );
  CREATE TABLE IF NOT EXISTS meta (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
  );
  CREATE TABLE IF NOT EXISTS conflicts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entity TEXT NOT NULL,
    entity_id TEXT NOT NULL,
    message TEXT NOT NULL,
    created_at TEXT NOT NULL
  );
`;

let database: SQLiteDatabase | null = null;
let initialization: Promise<void> | null = null;

export async function initDatabase(): Promise<void> {
  if (database) {
    return;
  }
  if (!initialization) {
    initialization = (async () => {
      await ensureStorageAccess();
      await restoreDatabaseBackup();
      database = await openDatabaseAsync(DATABASE_NAME);
      await database.execAsync(SCHEMA);
    })().catch((error: unknown) => {
      initialization = null;
      throw error;
    });
  }
  return initialization;
}

function db(): SQLiteDatabase {
  if (!database) {
    throw new Error('La base locale n’est pas initialisée.');
  }
  return database;
}

export function isDatabaseReady(): boolean {
  return database !== null;
}

export async function upsertEntity(
  entity: EntityName,
  id: string | number,
  data: Record<string, unknown>,
  updatedAt: string | null = null,
  pending = false,
): Promise<void> {
  await db().runAsync(
    `INSERT INTO entities (entity, id, data, updated_at, deleted, pending)
     VALUES (?, ?, ?, ?, 0, ?)
     ON CONFLICT(entity, id) DO UPDATE SET
       data = excluded.data,
       updated_at = excluded.updated_at,
       deleted = 0,
       pending = excluded.pending`,
    [entity, String(id), JSON.stringify(data), updatedAt, pending ? 1 : 0],
  );
}

export async function listEntities<T>(entity: EntityName): Promise<T[]> {
  const rows = await db().getAllAsync<EntityRow>(
    'SELECT data FROM entities WHERE entity = ? AND deleted = 0',
    [entity],
  );
  return rows.map((row) => JSON.parse(row.data) as T);
}

export async function getEntity<T>(entity: EntityName, id: string | number): Promise<T | null> {
  const row = await db().getFirstAsync<EntityRow>(
    'SELECT data FROM entities WHERE entity = ? AND id = ? AND deleted = 0',
    [entity, String(id)],
  );
  return row ? (JSON.parse(row.data) as T) : null;
}

export async function isEntityPending(entity: EntityName, id: string | number): Promise<boolean> {
  const row = await db().getFirstAsync<{ pending: number }>(
    'SELECT pending FROM entities WHERE entity = ? AND id = ?',
    [entity, String(id)],
  );
  return (row?.pending ?? 0) === 1;
}

export async function clearEntityPending(entity: EntityName, id: string | number): Promise<void> {
  await db().runAsync(
    'UPDATE entities SET pending = 0 WHERE entity = ? AND id = ?',
    [entity, String(id)],
  );
}

export async function markEntityDeleted(entity: EntityName, id: string | number): Promise<void> {
  await db().runAsync(
    'UPDATE entities SET deleted = 1, pending = 1 WHERE entity = ? AND id = ?',
    [entity, String(id)],
  );
}

export async function deleteEntity(entity: EntityName, id: string | number): Promise<void> {
  await db().runAsync('DELETE FROM entities WHERE entity = ? AND id = ?', [entity, String(id)]);
}

export async function enqueueChange(
  entity: EntityName,
  operation: SyncOperation,
  entityId: string | number,
  payload: Record<string, unknown> | null,
): Promise<void> {
  await db().runAsync(
    `INSERT INTO outbox (entity, operation, entity_id, payload, created_at)
     VALUES (?, ?, ?, ?, ?)`,
    [entity, operation, String(entityId), payload ? JSON.stringify(payload) : null, new Date().toISOString()],
  );
  await db().runAsync(
    'UPDATE entities SET pending = 1 WHERE entity = ? AND id = ?',
    [entity, String(entityId)],
  );
}

export async function listOutbox(): Promise<OutboxEntry[]> {
  return db().getAllAsync<OutboxEntry>('SELECT * FROM outbox ORDER BY id ASC');
}

export async function recordOutboxAttempt(id: number, error: string): Promise<void> {
  await db().runAsync(
    'UPDATE outbox SET attempts = attempts + 1, last_error = ? WHERE id = ?',
    [error, id],
  );
}

export async function removeOutboxEntry(id: number): Promise<void> {
  await db().runAsync('DELETE FROM outbox WHERE id = ?', [id]);
}

export async function getMeta(key: string): Promise<string | null> {
  const row = await db().getFirstAsync<{ value: string }>('SELECT value FROM meta WHERE key = ?', [key]);
  return row?.value ?? null;
}

export async function setMeta(key: string, value: string): Promise<void> {
  await db().runAsync(
    `INSERT INTO meta (key, value) VALUES (?, ?)
     ON CONFLICT(key) DO UPDATE SET value = excluded.value`,
    [key, value],
  );
}

export async function addConflict(entity: EntityName, entityId: string, message: string): Promise<void> {
  await db().runAsync(
    'INSERT INTO conflicts (entity, entity_id, message, created_at) VALUES (?, ?, ?, ?)',
    [entity, entityId, message, new Date().toISOString()],
  );
}

export async function listConflicts(): Promise<ConflictEntry[]> {
  return db().getAllAsync<ConflictEntry>('SELECT * FROM conflicts ORDER BY id DESC LIMIT 50');
}

export async function clearConflicts(): Promise<void> {
  await db().runAsync('DELETE FROM conflicts');
}

export async function countPendingChanges(): Promise<number> {
  const row = await db().getFirstAsync<{ count: number }>('SELECT COUNT(*) AS count FROM outbox');
  return row?.count ?? 0;
}
