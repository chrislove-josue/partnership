import { saveToDocuments, saveToDownloads } from '../native/mediaStore';
import {
  enqueueChange,
  getEntity,
  initDatabase,
  isDatabaseReady,
  listEntities,
  markEntityDeleted,
  upsertEntity,
  deleteEntity,
  type EntityName,
} from '../db/database';
import { readCache, readStaleCache, writeCache } from './cache';

export { clearCache, invalidateCache } from './cache';

const DEFAULT_API_URL = process.env.EXPO_PUBLIC_API_URL ?? '';

const INVALID_API_URL_MESSAGE =
  'Adresse de l’API invalide. Utilisez une URL HTTPS ; le trafic en clair n’est autorisé que sur le réseau local (IP privée ou nom se terminant par .local).';

/**
 * Vrai pour un hôte appartenant à un réseau local. Les octets sont comparés
 * numériquement : aucune adresse IP n'est écrite en dur dans le code.
 */
function isLocalNetworkHost(rawHostname: string): boolean {
  const hostname = rawHostname.toLowerCase().replace(/^\[|\]$/g, '');

  if (hostname === 'localhost' || hostname.endsWith('.localhost') || hostname.endsWith('.local')) {
    return true;
  }

  const parts = hostname.split('.');
  if (parts.length === 4 && parts.every((part) => /^\d{1,3}$/.test(part))) {
    const [a, b] = parts.map(Number);
    if ([a, b].some((octet) => octet > 255)) {
      return false;
    }
    return (
      a === 10 ||
      (a === 172 && b >= 16 && b <= 31) ||
      (a === 192 && b === 168) ||
      (a === 169 && b === 254) ||
      a === 127
    );
  }

  // IPv6 boucle, unique locale (fc00::/7) et lien local (fe80::/10).
  return hostname === '::1' || /^f[cd]/.test(hostname) || /^fe[89ab]/.test(hostname);
}

/**
 * Valide et normalise une URL d'API : pas d'identifiants, ni query, ni hash.
 * HTTPS est exigé sur Internet. Le trafic en clair est réservé au réseau local
 * (IP privée ou .local), y compris dans la release, pour les serveurs installés
 * sur le réseau de l'église.
 */
export function normalizeApiUrl(value: string): string {
  const trimmed = value.trim();
  if (!trimmed) {
    return '';
  }

  let parsed: URL;
  try {
    parsed = new URL(trimmed);
  } catch {
    throw new Error(INVALID_API_URL_MESSAGE);
  }

  const isDev = typeof __DEV__ !== 'undefined' && __DEV__;
  const isDevCleartext = isDev && parsed.hostname.toLowerCase().endsWith('.test');
  const isLocalCleartext =
    parsed.protocol === 'http:' && (isLocalNetworkHost(parsed.hostname) || isDevCleartext);

  if (parsed.protocol !== 'https:' && !isLocalCleartext) {
    throw new Error(INVALID_API_URL_MESSAGE);
  }
  if (parsed.username || parsed.password) {
    throw new Error(INVALID_API_URL_MESSAGE);
  }

  parsed.search = '';
  parsed.hash = '';
  return parsed.toString().replace(/\/+$/, '');
}

function initialApiUrl(): string {
  try {
    return normalizeApiUrl(DEFAULT_API_URL);
  } catch {
    // Une variable d'environnement invalide ne doit pas empêcher le démarrage.
    return '';
  }
}

let apiUrl = initialApiUrl();

export function getApiUrl(): string {
  return apiUrl;
}

export function setApiUrl(url: string): string {
  apiUrl = normalizeApiUrl(url);
  return apiUrl;
}

let authToken: string | null = null;
let unauthorizedHandler: (() => void) | null = null;

export function setAuthToken(token: string | null): void {
  authToken = token;
}

export function getAuthToken(): string | null {
  return authToken;
}

/** Déconnexion côté serveur, sans jamais bloquer la déconnexion locale. */
export async function notifyServerLogout(): Promise<void> {
  const token = authToken;
  if (!token) {
    return;
  }

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 3000);
  try {
    await fetch(`${getApiUrl()}/logout`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      signal: controller.signal,
    });
  } catch {
    // La session locale est supprimée même si le serveur est injoignable.
  } finally {
    clearTimeout(timer);
  }
}

/** Appelé lorsqu'une requête authentifiée reçoit un 401 (session expirée). */
export function setUnauthorizedHandler(handler: (() => void) | null): void {
  unauthorizedHandler = handler;
}

function handleUnauthorized(sendsToken: boolean): void {
  if (!sendsToken) {
    return;
  }
  authToken = null;
  unauthorizedHandler?.();
}

export const NETWORK_ERROR_MESSAGE =
  'Impossible de joindre le serveur. Vérifiez votre connexion et l’adresse de l’API.';

export const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
export const UPLOAD_TOO_LARGE_MESSAGE = 'Le fichier ne doit pas dépasser 10 Mo.';

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

function defaultMessage(status: number, fallback: string): string {
  switch (status) {
    case 0:
      return NETWORK_ERROR_MESSAGE;
    case 401:
      return 'Votre session a expiré. Veuillez vous reconnecter.';
    case 403:
      return 'Vous n’avez pas l’autorisation pour effectuer cette action.';
    case 404:
      return 'La ressource demandée est introuvable.';
    case 422:
      return 'Vérifiez les informations saisies.';
    default:
      if (status >= 500) {
        return 'Une erreur est survenue côté serveur. Veuillez réessayer.';
      }
      return fallback;
  }
}

function messageFromData(data: Record<string, unknown>, status: number, fallback: string): string {
  // Les erreurs 5xx et les corps non JSON sont remplacés par un message figé :
  // le texte du serveur peut contenir des détails techniques sensibles.
  if (status >= 500) {
    return defaultMessage(status, fallback);
  }

  const message = typeof data.message === 'string' ? data.message.trim().slice(0, 200) : '';
  return message || defaultMessage(status, fallback);
}

/** Transforme toute erreur en message français affichable par l'application. */
export function getErrorMessage(caught: unknown, fallback: string): string {
  if (caught instanceof ApiError) {
    const details = Object.values(caught.errors ?? {})
      .flat()
      .filter((detail): detail is string => typeof detail === 'string' && detail.trim() !== '');

    return details[0] ?? (caught.message || fallback);
  }

  if (caught instanceof Error) {
    if (/network request failed|failed to fetch|networkerror|timeout/i.test(caught.message)) {
      return NETWORK_ERROR_MESSAGE;
    }

    return fallback;
  }

  return fallback;
}

export function isNetworkError(caught: unknown): boolean {
  return caught instanceof ApiError
    ? caught.status === 0
    : caught instanceof Error && /network request failed|failed to fetch|networkerror|timeout/i.test(caught.message);
}

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PATCH' | 'DELETE';
  body?: Record<string, unknown>;
  authenticated?: boolean;
  /** Clé de cache pour les requêtes GET ; les données sont renvoyées instantanément. */
  cacheKey?: string;
  /** Durée de validité du cache en millisecondes (5 minutes par défaut). */
  cacheTtl?: number;
  /** Ignore le cache et recharge depuis le serveur. */
  refresh?: boolean;
  /** Utilisé par la synchronisation pour éviter de repasser par la couche hors-ligne. */
  skipOffline?: boolean;
}

async function parseResponse(response: Response): Promise<Record<string, unknown>> {
  const text = await response.text();

  if (!text) {
    return {};
  }

  try {
    return JSON.parse(text) as Record<string, unknown>;
  } catch {
    // Le texte brut n'est jamais transformé en message affichable.
    return {};
  }
}

async function performRequest<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  const {
    method = 'GET',
    body,
    authenticated = true,
    cacheKey,
    cacheTtl,
    refresh = false,
  } = options;

  if (method === 'GET' && cacheKey && !refresh) {
    const cached = readCache<T>(cacheKey, cacheTtl);
    if (cached !== null) {
      return cached;
    }
  }

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };

  const sendsToken = authenticated && authToken !== null;
  if (sendsToken) {
    headers.Authorization = `Bearer ${authToken}`;
  }

  let response: Response;
  try {
    response = await fetch(`${apiUrl}${path}`, {
      method,
      headers,
      body: body ? JSON.stringify(body) : undefined,
    });
  } catch (error) {
    if (method === 'GET' && cacheKey && !refresh) {
      const stale = readStaleCache<T>(cacheKey);
      if (stale !== null) {
        return stale;
      }
    }
    throw new ApiError(NETWORK_ERROR_MESSAGE, 0);
  }

  const data = await parseResponse(response);

  if (!response.ok) {
    handleUnauthorized(sendsToken && response.status === 401);

    // Une session expirée ne doit jamais être masquée par des données périmées.
    if (method === 'GET' && cacheKey && !refresh && response.status !== 401) {
      const stale = readStaleCache<T>(cacheKey);
      if (stale !== null) {
        return stale;
      }
    }
    const errors = (data.errors ?? undefined) as Record<string, string[]> | undefined;
    throw new ApiError(
      messageFromData(data, response.status, 'Une erreur est survenue. Veuillez réessayer.'),
      response.status,
      errors,
    );
  }

  const result = data as T;
  if (method === 'GET' && cacheKey) {
    writeCache(cacheKey, result);
  }

  return result;
}

const SYNCABLE_LIST_PATTERN = /^\/(churches|cellules|categories|partners|donations)(?:\?|$)/;
const SYNCABLE_WRITE_PATTERN = /^\/(churches|cellules|categories|partners|donations)(?:\/(\d+))?$/;

const SINGULAR_NAMES: Record<EntityName, string> = {
  churches: 'church',
  cellules: 'cellule',
  categories: 'category',
  partners: 'partner',
  donations: 'donation',
};

type LocalRecord = Record<string, unknown>;

function applyLocalFilters(
  entity: EntityName,
  rows: LocalRecord[],
  query: string,
): LocalRecord[] {
  const params = new URLSearchParams(query);
  const search = params.get('search')?.trim().toLowerCase();

  return rows.filter((row) => {
    if (search && entity === 'partners') {
      const searchable = [row.first_name, row.last_name, row.phone, row.handle_kc]
        .map((value) => String(value ?? '').toLowerCase())
        .join(' ');
      if (!searchable.includes(search)) {
        return false;
      }
    }

    for (const key of ['church_id', 'cellule_id', 'category_id', 'partner_id']) {
      const expected = params.get(key);
      if (expected && String(row[key] ?? '') !== expected) {
        return false;
      }
    }

    const date = String(row.date ?? '').slice(0, 10);
    const from = params.get('date_from');
    const to = params.get('date_to');
    if (from && date < from) {
      return false;
    }
    if (to && date > to) {
      return false;
    }
    return true;
  });
}

async function tryReadLocal<T>(path: string): Promise<T | null> {
  const match = path.match(SYNCABLE_LIST_PATTERN);
  if (!match) {
    return null;
  }

  if (!isDatabaseReady()) {
    await initDatabase().catch(() => null);
  }
  if (!isDatabaseReady()) {
    return null;
  }

  const entity = match[1] as EntityName;
  const queryIndex = path.indexOf('?');
  const query = queryIndex >= 0 ? path.slice(queryIndex + 1) : '';
  const rows = await listEntities<LocalRecord>(entity);

  return { data: applyLocalFilters(entity, rows, query) } as T;
}

function responseRecord(entity: EntityName, result: unknown): LocalRecord | null {
  if (!result || typeof result !== 'object') {
    return null;
  }
  const candidate = (result as LocalRecord)[SINGULAR_NAMES[entity]];
  return candidate && typeof candidate === 'object' ? (candidate as LocalRecord) : null;
}

async function buildLocalRecord(
  entity: EntityName,
  id: string | number,
  body: LocalRecord,
): Promise<LocalRecord> {
  const record: LocalRecord = {
    ...body,
    id,
    updated_at: new Date().toISOString(),
  };

  if (entity === 'donations') {
    record.amount = Number(body.amount ?? 0);
    record.date = String(body.date ?? '');
    const [churches, partners, categories] = await Promise.all([
      listEntities<LocalRecord>('churches'),
      listEntities<LocalRecord>('partners'),
      listEntities<LocalRecord>('categories'),
    ]);
    record.church = churches.find((item) => String(item.id) === String(body.church_id))?.name ?? '—';
    const partner = partners.find((item) => String(item.id) === String(body.partner_id));
    record.partner = partner?.full_name
      ?? (partner ? `${String(partner.first_name ?? '')} ${String(partner.last_name ?? '')}`.trim() : 'Partenaire inconnu');
    const category = categories.find((item) => String(item.id) === String(body.category_id));
    record.category = category?.name ?? '—';
    record.color = category?.color ?? '#1F4E78';
  }

  if (entity === 'partners') {
    record.full_name = `${String(body.first_name ?? '')} ${String(body.last_name ?? '')}`.trim();
    const [churches, cellules] = await Promise.all([
      listEntities<LocalRecord>('churches'),
      listEntities<LocalRecord>('cellules'),
    ]);
    record.church = churches.find((item) => String(item.id) === String(body.church_id))?.name ?? '—';
    record.cellule = cellules.find((item) => String(item.id) === String(body.cellule_id))?.name ?? null;
    record.total_donations = Number(body.total_donations ?? 0);
  }

  return record;
}

async function mirrorOnlineWrite(
  path: string,
  method: string,
  body: LocalRecord | undefined,
  result: unknown,
): Promise<void> {
  const match = path.match(SYNCABLE_WRITE_PATTERN);
  if (!match || !isDatabaseReady()) {
    return;
  }

  const entity = match[1] as EntityName;
  if (method === 'DELETE') {
    await deleteEntity(entity, match[2] ?? '');
    return;
  }

  const record = responseRecord(entity, result) ?? body ?? {};
  const id = record.id ?? match[2];
  if (typeof id !== 'string' && typeof id !== 'number') {
    return;
  }

  const data: LocalRecord = { ...body, ...record, id };
  await upsertEntity(
    entity,
    id,
    data,
    typeof record.updated_at === 'string' ? record.updated_at : null,
  );
}

async function triggerSync(): Promise<void> {
  try {
    const { syncNow } = await import('../db/sync');
    await syncNow();
  } catch {
    // La synchronisation est best-effort : elle reprendra au prochain démarrage.
  }
}

async function queueOfflineWrite<T>(
  path: string,
  method: string,
  body: LocalRecord,
): Promise<T> {
  await initDatabase();
  const match = path.match(SYNCABLE_WRITE_PATTERN);
  if (!match) {
    throw new ApiError(NETWORK_ERROR_MESSAGE, 0);
  }

  const entity = match[1] as EntityName;
  const existing = match[2] ? await getEntity<LocalRecord>(entity, match[2]) : null;
  const id = method === 'POST'
    ? `local-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
    : match[2] ?? '';
  const record = await buildLocalRecord(entity, id, { ...(existing ?? {}), ...body });

  await upsertEntity(entity, id, record, record.updated_at as string | null, true);
  if (method === 'DELETE') {
    await markEntityDeleted(entity, id);
  }
  await enqueueChange(
    entity,
    method === 'POST' ? 'create' : method === 'DELETE' ? 'delete' : 'update',
    id,
    body,
  );

  void triggerSync();

  if (method === 'DELETE') {
    return { message: 'Suppression enregistrée hors-ligne. Elle sera synchronisée plus tard.' } as T;
  }
  return {
    message: 'Modification enregistrée hors-ligne. Elle sera synchronisée automatiquement.',
    [SINGULAR_NAMES[entity]]: record,
  } as T;
}

/** Appel réseau avec cache local et file d'attente pour les écritures hors-ligne. */
export async function api<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  const { method = 'GET', skipOffline = false, refresh = false } = options;

  try {
    const result = await performRequest<T>(path, options);
    if (!skipOffline && method !== 'GET') {
      await mirrorOnlineWrite(path, method, options.body, result);
    }
    return result;
  } catch (error) {
    if (skipOffline) {
      throw error;
    }

    if (isNetworkError(error)) {
      if (method !== 'GET' && SYNCABLE_WRITE_PATTERN.test(path)) {
        return queueOfflineWrite<T>(path, method, options.body ?? {});
      }
      if (method === 'GET' && !refresh) {
        const local = await tryReadLocal<T>(path);
        if (local !== null) {
          return local;
        }
      }
    }
    throw error;
  }
}

export interface UploadFile {
  uri: string;
  name: string;
  mimeType?: string | null;
  size?: number | null;
}

export async function apiUpload<T>(
  path: string,
  file: UploadFile,
  field = 'file',
): Promise<T> {
  if (typeof file.size === 'number' && file.size > MAX_UPLOAD_BYTES) {
    throw new ApiError(UPLOAD_TOO_LARGE_MESSAGE, 413);
  }

  const form = new FormData();
  form.append(field, {
    uri: file.uri,
    name: file.name,
    type: file.mimeType ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  } as unknown as Blob);

  const sendsToken = authToken !== null;
  let response: Response;
  try {
    response = await fetch(`${apiUrl}${path}`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        ...(sendsToken ? { Authorization: `Bearer ${authToken}` } : {}),
      },
      body: form,
    });
  } catch {
    throw new ApiError(NETWORK_ERROR_MESSAGE, 0);
  }

  const data = await parseResponse(response);
  if (!response.ok) {
    handleUnauthorized(sendsToken && response.status === 401);
    const errors = (data.errors ?? undefined) as Record<string, string[]> | undefined;
    throw new ApiError(
      messageFromData(data, response.status, "L'envoi du fichier a échoué."),
      response.status,
      errors,
    );
  }

  return data as T;
}

export async function apiDownload(path: string, fileName: string): Promise<string> {
  const sendsToken = authToken !== null;
  let response: Response;
  try {
    response = await fetch(`${apiUrl}${path}`, {
      headers: {
        Accept: 'application/json, application/octet-stream',
        ...(sendsToken ? { Authorization: `Bearer ${authToken}` } : {}),
      },
    });
  } catch {
    throw new ApiError(NETWORK_ERROR_MESSAGE, 0);
  }

  if (!response.ok) {
    handleUnauthorized(sendsToken && response.status === 401);
    throw new ApiError('Impossible de télécharger le fichier.', response.status);
  }

  const blob = await response.blob();
  const base64 = await new Promise<string>((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const result = String(reader.result);
      resolve(result.substring(result.indexOf(',') + 1));
    };
    reader.onerror = () => reject(new Error('Lecture du fichier impossible.'));
    reader.readAsDataURL(blob);
  });

  const mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

  // Copie de conservation, discrète pour l'utilisateur.
  try {
    await saveToDocuments(base64, 'Documents/FinanceMobile/files', fileName, mimeType);
  } catch {
    // Le téléchargement dans Téléchargements reste prioritaire.
  }

  return saveToDownloads(base64, fileName, mimeType);
}
