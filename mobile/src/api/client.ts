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

export function setAuthToken(token: string | null): void {
  authToken = token;
}

export function getAuthToken(): string | null {
  return authToken;
}

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

  if (authenticated && authToken) {
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
    throw error;
  }

  const data = await parseResponse(response);

  if (!response.ok) {
    if (method === 'GET' && cacheKey) {
      const stale = readStaleCache<T>(cacheKey);
      if (stale !== null) {
        return stale;
      }
    }
    const errors = (data.errors ?? undefined) as Record<string, string[]> | undefined;
    throw new ApiError(
      String(data.message ?? 'Une erreur est survenue. Veuillez réessayer.'),
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

  const response = await fetch(`${apiUrl}${path}`, {
    method: 'POST',
    headers: authToken ? { Authorization: `Bearer ${authToken}` } : {},
    body: form,
  });

  const data = await parseResponse(response);
  if (!response.ok) {
    const errors = (data.errors ?? undefined) as Record<string, string[]> | undefined;
    throw new ApiError(
      String(data.message ?? "L'envoi du fichier a échoué."),
      response.status,
      errors,
    );
  }

  return data as T;
}

export async function apiDownload(path: string, fileName: string): Promise<string> {
  const response = await fetch(`${apiUrl}${path}`, {
    headers: authToken ? { Authorization: `Bearer ${authToken}` } : {},
  });

  if (!response.ok) {
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
