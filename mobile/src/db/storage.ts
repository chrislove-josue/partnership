import * as LegacyFileSystem from 'expo-file-system/legacy';
import { defaultDatabaseDirectory } from 'expo-sqlite';
import { Linking } from 'react-native';
import { readFromDocuments, saveToDocuments } from '../native/mediaStore';

/**
 * Les fichiers partagés passent par MediaStore : plus besoin d'autorisation
 * « Tous les fichiers ». La base reste copiée dans Documents/FinanceMobile
 * (avec repli dans Download/FinanceMobile) et survit à la désinstallation.
 */
export const PUBLIC_DIR_URI = 'file:///storage/emulated/0/Documents/FinanceMobile/';
export const PUBLIC_FILES_URI = `${PUBLIC_DIR_URI}files/`;
export const DOWNLOADS_URI = 'file:///storage/emulated/0/Download/';
const DOCUMENTS_RELATIVE_PATH = 'Documents/FinanceMobile';
const DOWNLOAD_BACKUP_RELATIVE_PATH = 'Download/FinanceMobile';
const DATABASE_FILE = 'finance.sqlite';
const BACKUP_URI = `${PUBLIC_DIR_URI}${DATABASE_FILE}`;

/** expo-sqlite place ses bases dans `files/SQLite` (defaultDatabaseDirectory). */
export function privateDatabaseUri(): string {
  const fallback = `${LegacyFileSystem.documentDirectory ?? ''}SQLite`.replace(/^file:\/\//, '');
  const raw = String(defaultDatabaseDirectory ?? fallback).replace(/\/+$/, '');
  const base = raw.startsWith('file://')
    ? raw
    : `file://${raw.startsWith('/') ? '' : '/'}${raw}`;
  return `${base}/${DATABASE_FILE}`;
}

export function publicBackupUri(): string {
  return BACKUP_URI;
}

/** Conservé pour compatibilité : MediaStore n'exige plus de permission spéciale. */
export function hasPublicStorageAccess(): boolean {
  return true;
}

export async function ensureStorageAccess(): Promise<boolean> {
  return true;
}

/** Conservé pour compatibilité ; aucune action requis par l'utilisateur. */
export async function openStorageSettings(): Promise<void> {
  await Linking.openSettings();
}

export async function restoreDatabaseBackup(): Promise<void> {
  try {
    const privateInfo = await LegacyFileSystem.getInfoAsync(privateDatabaseUri());
    if (privateInfo.exists) {
      return;
    }

    const content =
      (await readFromDocuments(DOCUMENTS_RELATIVE_PATH, DATABASE_FILE)) ??
      (await readFromDocuments(DOWNLOAD_BACKUP_RELATIVE_PATH, DATABASE_FILE));
    if (!content) {
      return;
    }

    const target = privateDatabaseUri();
    const targetDir = target.slice(0, target.lastIndexOf('/'));
    await LegacyFileSystem.makeDirectoryAsync(targetDir, { intermediates: true });
    await LegacyFileSystem.writeAsStringAsync(target, content, { encoding: 'base64' });
  } catch {
    // Une sauvegarde illisible ne doit pas empêcher la création d'une base neuve.
  }
}

export async function backupDatabase(): Promise<void> {
  try {
    const privateInfo = await LegacyFileSystem.getInfoAsync(privateDatabaseUri());
    if (!privateInfo.exists) {
      return;
    }

    const content = await LegacyFileSystem.readAsStringAsync(privateDatabaseUri(), {
      encoding: 'base64',
    });
    await saveToDocuments(
      content,
      DOCUMENTS_RELATIVE_PATH,
      DATABASE_FILE,
      'application/x-sqlite3',
    );
  } catch {
    // La base privée reste utilisable si la copie publique échoue.
  }
}

export function isDownloadedFile(uri: string): boolean {
  return (
    uri.startsWith('content://') ||
    uri.startsWith(DOWNLOADS_URI) ||
    uri.startsWith(PUBLIC_FILES_URI)
  );
}
