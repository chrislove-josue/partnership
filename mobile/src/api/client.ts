import { saveToDownloads } from '../native/mediaStore';

/** Erreur réseau : l'APK fonctionne exclusivement avec la base de données distante. */
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
  } = options;

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
  } catch {
    throw new ApiError(NETWORK_ERROR_MESSAGE, 0);
  }

  const data = await parseResponse(response);

  if (!response.ok) {
    handleUnauthorized(sendsToken && response.status === 401);
    const errors = (data.errors ?? undefined) as Record<string, string[]> | undefined;
    throw new ApiError(
      messageFromData(data, response.status, 'Une erreur est survenue. Veuillez réessayer.'),
      response.status,
      errors,
    );
  }

  return data as T;
}

/**
 * Toutes les données sont lues et écrites directement dans la base distante.
 * Aucun cache fichier, aucune copie locale : une panne réseau affiche une erreur.
 */
export async function api<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  return performRequest<T>(path, options);
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

  // L'export est enregistré uniquement dans Téléchargements (action explicite de l'utilisateur).
  return saveToDownloads(base64, fileName, mimeType);
}
