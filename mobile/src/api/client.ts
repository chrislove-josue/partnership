import { File, Paths } from 'expo-file-system';
import { readCache, readStaleCache, writeCache } from './cache';

export { clearCache, invalidateCache } from './cache';

const DEFAULT_API_URL =
  process.env.EXPO_PUBLIC_API_URL ?? 'http://172.16.206.66:8000/api';

let apiUrl = DEFAULT_API_URL;

export function getApiUrl(): string {
  return apiUrl;
}

export function setApiUrl(url: string): void {
  apiUrl = url.trim().replace(/\/+$/, '');
}

let authToken: string | null = null;
let unauthorizedHandler: (() => void) | null = null;

export function setAuthToken(token: string | null): void {
  authToken = token;
}

export function getAuthToken(): string | null {
  return authToken;
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
    case 500:
      return 'Une erreur est survenue côté serveur. Veuillez réessayer.';
    default:
      return fallback;
  }
}

function messageFromData(data: Record<string, unknown>, status: number, fallback: string): string {
  const message = typeof data.message === 'string' ? data.message.trim() : '';

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

  if (caught instanceof Error && caught.message.trim() !== '') {
    if (/network request failed|failed to fetch|networkerror|timeout/i.test(caught.message)) {
      return NETWORK_ERROR_MESSAGE;
    }

    return caught.message;
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
}

async function parseResponse(response: Response): Promise<Record<string, unknown>> {
  const text = await response.text();

  if (!text) {
    return {};
  }

  try {
    return JSON.parse(text) as Record<string, unknown>;
  } catch {
    return { message: text };
  }
}

export async function api<T>(
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
    if (method === 'GET' && cacheKey) {
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
    if (method === 'GET' && cacheKey && response.status !== 401) {
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

export interface UploadFile {
  uri: string;
  name: string;
  mimeType?: string | null;
}

export async function apiUpload<T>(
  path: string,
  file: UploadFile,
  field = 'file',
): Promise<T> {
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

  const file = new File(Paths.cache, fileName);
  file.write(base64, { encoding: 'base64' });

  return file.uri;
}
