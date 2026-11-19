import { Directory, File, Paths } from 'expo-file-system';

interface CacheEntry<T> {
  timestamp: number;
  data: T;
}

const registeredKeys = new Set<string>();
const DEFAULT_TTL = 5 * 60 * 1000;

function fileFor(key: string): File {
  registeredKeys.add(key);
  const safeKey = key.replace(/[^a-zA-Z0-9_-]/g, '_');
  return new File(Paths.cache, `churchmanager-cache-${safeKey}.json`);
}

export function readCache<T>(key: string, maxAge = DEFAULT_TTL): T | null {
  const file = fileFor(key);
  if (!file.exists) {
    return null;
  }

  try {
    const entry = JSON.parse(file.textSync()) as CacheEntry<T>;
    if (!Number.isFinite(entry.timestamp) || Date.now() - entry.timestamp > maxAge) {
      return null;
    }
    return entry.data;
  } catch {
    return null;
  }
}

export function readStaleCache<T>(key: string): T | null {
  const file = fileFor(key);
  if (!file.exists) {
    return null;
  }

  try {
    return (JSON.parse(file.textSync()) as CacheEntry<T>).data;
  } catch {
    return null;
  }
}

export function writeCache<T>(key: string, data: T): void {
  try {
    fileFor(key).write(JSON.stringify({ timestamp: Date.now(), data } satisfies CacheEntry<T>));
  } catch {
    // Le cache est une optimisation : une erreur d'écriture ne doit pas bloquer l'application.
  }
}

export function invalidateCache(prefix?: string): void {
  registeredKeys.forEach((key) => {
    if (!prefix || key.startsWith(prefix)) {
      try {
        const file = fileFor(key);
        if (file.exists) {
          file.delete();
        }
      } catch {
        // Ignorer les fichiers déjà supprimés.
      }
    }
  });
}

export function clearCache(): void {
  invalidateCache();

  try {
    const cacheDir = new Directory(Paths.cache);
    if (!cacheDir.exists) {
      return;
    }
    cacheDir.list().forEach((entry) => {
      if (entry instanceof File && entry.uri.includes('churchmanager-cache-') && entry.exists) {
        entry.delete();
      }
    });
  } catch {
    // Le cache reste une optimisation : ne jamais bloquer la déconnexion.
  }
}
