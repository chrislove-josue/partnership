import * as SecureStore from 'expo-secure-store';

const SESSION_KEY = 'churchmanager_session';

export interface SessionInfo {
  connectedAt: string;
  lastSyncAt: string;
}

const MONTHS = [
  'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
  'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
];

export async function saveSession(session: SessionInfo): Promise<void> {
  await SecureStore.setItemAsync(SESSION_KEY, JSON.stringify(session));
}

export async function loadSession(): Promise<SessionInfo | null> {
  const raw = await SecureStore.getItemAsync(SESSION_KEY);
  if (!raw) {
    return null;
  }

  try {
    const parsed = JSON.parse(raw) as Partial<SessionInfo>;
    if (typeof parsed.connectedAt !== 'string' || typeof parsed.lastSyncAt !== 'string') {
      return null;
    }
    return { connectedAt: parsed.connectedAt, lastSyncAt: parsed.lastSyncAt };
  } catch {
    return null;
  }
}

export async function clearSession(): Promise<void> {
  await SecureStore.deleteItemAsync(SESSION_KEY);
}

export function formatSessionDate(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '—';
  }

  const pad = (part: number): string => String(part).padStart(2, '0');
  return `${pad(date.getDate())} ${MONTHS[date.getMonth()]} ${date.getFullYear()} à ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}
