import { Linking } from 'react-native';
import { deleteDocument } from '../native/mediaStore';

// Anciens emplacements publics utilisés par les versions < 1.5.1 pour la copie
// non chiffrée de finance.sqlite. Ils ne servent plus qu'à nettoyer à la coupure
// de session ou au changement d'utilisateur.
const DOCUMENTS_RELATIVE_PATH = 'Documents/FinanceMobile';
const DOWNLOAD_BACKUP_RELATIVE_PATH = 'Download/FinanceMobile';
const DATABASE_FILE = 'finance.sqlite';
const DOWNLOADS_URI = 'file:///storage/emulated/0/Download/';
const PUBLIC_FILES_URI = 'file:///storage/emulated/0/Documents/FinanceMobile/files/';

/** Conservé pour compatibilité : MediaStore n'exige plus de permission spéciale. */
export function hasPublicStorageAccess(): boolean {
  return true;
}

export async function ensureStorageAccess(): Promise<boolean> {
  return true;
}

/** Conservé pour compatibilité ; aucune action n'est requise par l'utilisateur. */
export async function openStorageSettings(): Promise<void> {
  await Linking.openSettings();
}

/** Supprime l'ancienne copie publique de la base, si elle existe encore. */
export async function deleteLegacyPublicBackup(): Promise<void> {
  for (const relativePath of [DOCUMENTS_RELATIVE_PATH, DOWNLOAD_BACKUP_RELATIVE_PATH]) {
    try {
      await deleteDocument(relativePath, DATABASE_FILE);
    } catch {
      // L'absence du module natif ou un fichier déjà supprimé n'est pas bloquant.
    }
  }
}

export function isDownloadedFile(uri: string): boolean {
  return (
    uri.startsWith('content://') ||
    uri.startsWith(DOWNLOADS_URI) ||
    uri.startsWith(PUBLIC_FILES_URI)
  );
}
