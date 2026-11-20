import * as LegacyFileSystem from 'expo-file-system/legacy';
import { defaultDatabaseDirectory } from 'expo-sqlite';
import { Linking, Platform } from 'react-native';

/**
 * La base de travail reste dans le stockage privé de l'application.
 * Une copie est conservée dans un dossier public afin de survivre à la
 * désinstallation, puis restaurée au premier lancement suivant la réinstallation.
 */
export const PUBLIC_DIR_URI = 'file:///storage/emulated/0/Documents/FinanceMobile/';
export const PUBLIC_FILES_URI = `${PUBLIC_DIR_URI}files/`;
export const DOWNLOADS_URI = 'file:///storage/emulated/0/Download/';
const DATABASE_FILE = 'finance.sqlite';
const BACKUP_URI = `${PUBLIC_DIR_URI}${DATABASE_FILE}`;

let publicAccess: boolean | null = null;

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

/** Indique si le dossier public est accessible (permission « Tous les fichiers »). */
export function hasPublicStorageAccess(): boolean {
  return publicAccess === true;
}

async function ensureDirectory(uri: string): Promise<void> {
  const info = await LegacyFileSystem.getInfoAsync(uri);
  if (!info.exists) {
    await LegacyFileSystem.makeDirectoryAsync(uri, { intermediates: true });
  }
}

/**
 * Vérifie l'accès au dossier public sans jamais bloquer le démarrage de l'application.
 * L'utilisateur doit autoriser « Tous les fichiers » dans les paramètres Android.
 */
export async function ensureStorageAccess(): Promise<boolean> {
  if (Platform.OS !== 'android') {
    publicAccess = true;
    return true;
  }

  try {
    await ensureDirectory(PUBLIC_DIR_URI);
    await ensureDirectory(PUBLIC_FILES_URI);
    publicAccess = true;
  } catch {
    publicAccess = false;
  }
  return publicAccess;
}

export async function openStorageSettings(): Promise<void> {
  await Linking.openSettings();
}

export async function restoreDatabaseBackup(): Promise<void> {
  if (!(await ensureStorageAccess())) {
    return;
  }

  try {
    const [privateInfo, backupInfo] = await Promise.all([
      LegacyFileSystem.getInfoAsync(privateDatabaseUri()),
      LegacyFileSystem.getInfoAsync(BACKUP_URI),
    ]);

    if (!privateInfo.exists && backupInfo.exists) {
      const target = privateDatabaseUri();
      const targetDir = target.slice(0, target.lastIndexOf('/'));
      await LegacyFileSystem.makeDirectoryAsync(targetDir, { intermediates: true });
      await LegacyFileSystem.copyAsync({ from: BACKUP_URI, to: target });
    }
  } catch {
    // Une sauvegarde illisible ne doit pas empêcher la création d'une base neuve.
  }
}

export async function backupDatabase(): Promise<void> {
  if (!(await ensureStorageAccess())) {
    return;
  }

  try {
    const privateInfo = await LegacyFileSystem.getInfoAsync(privateDatabaseUri());
    if (!privateInfo.exists) {
      return;
    }

    const backupInfo = await LegacyFileSystem.getInfoAsync(BACKUP_URI);
    if (backupInfo.exists) {
      await LegacyFileSystem.deleteAsync(BACKUP_URI, { idempotent: true });
    }
    await LegacyFileSystem.copyAsync({ from: privateDatabaseUri(), to: BACKUP_URI });
  } catch {
    // La base privée reste utilisable si la copie publique échoue.
  }
}

/** Retourne l'URI publique où enregistrer un fichier exporté (XLSX, etc.). */
export async function publicFilePath(fileName: string): Promise<string> {
  await ensureStorageAccess();
  return `${PUBLIC_FILES_URI}${fileName}`;
}

/** Enregistre aussi le fichier dans le dossier Téléchargements visibile par l'utilisateur. */
export async function downloadsFilePath(
  sourceUri: string,
  fileName: string,
): Promise<string | null> {
  if (!(await ensureStorageAccess())) {
    return null;
  }

  try {
    await ensureDirectory(DOWNLOADS_URI);
    const destination = `${DOWNLOADS_URI}${fileName}`;
    await LegacyFileSystem.copyAsync({ from: sourceUri, to: destination });
    return destination;
  } catch {
    return null;
  }
}

export function isDownloadedFile(uri: string): boolean {
  return uri.startsWith(DOWNLOADS_URI) || uri.startsWith(PUBLIC_FILES_URI);
}
