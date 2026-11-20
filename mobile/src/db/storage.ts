import * as LegacyFileSystem from 'expo-file-system/legacy';
import { Linking, Platform } from 'react-native';

/**
 * La base de travail reste dans le stockage privé de l'application.
 * Une copie est conservée dans un dossier public afin de survivre à la
 * désinstallation, puis restaurée au premier lancement suivant la réinstallation.
 */
export const PUBLIC_DIR_URI = 'file:///storage/emulated/0/Documents/FinanceMobile/';
export const PUBLIC_FILES_URI = `${PUBLIC_DIR_URI}files/`;
const DATABASE_FILE = 'finance.sqlite';
const BACKUP_URI = `${PUBLIC_DIR_URI}${DATABASE_FILE}`;

export function privateDatabaseUri(): string {
  return `${LegacyFileSystem.documentDirectory ?? ''}${DATABASE_FILE}`;
}

export function publicBackupUri(): string {
  return BACKUP_URI;
}

async function ensureDirectory(uri: string): Promise<void> {
  const info = await LegacyFileSystem.getInfoAsync(uri);
  if (!info.exists) {
    await LegacyFileSystem.makeDirectoryAsync(uri, { intermediates: true });
  }
}

/** Vérifie l'accès au dossier public ; l'utilisateur doit autoriser « Tous les fichiers ». */
export async function ensureStorageAccess(): Promise<void> {
  if (Platform.OS !== 'android') {
    return;
  }

  await ensureDirectory(PUBLIC_DIR_URI);
  await ensureDirectory(PUBLIC_FILES_URI);
}

export async function openStorageSettings(): Promise<void> {
  await Linking.openSettings();
}

export async function restoreDatabaseBackup(): Promise<void> {
  if (Platform.OS !== 'android') {
    return;
  }

  const [privateInfo, backupInfo] = await Promise.all([
    LegacyFileSystem.getInfoAsync(privateDatabaseUri()),
    LegacyFileSystem.getInfoAsync(BACKUP_URI),
  ]);

  if (!privateInfo.exists && backupInfo.exists) {
    await LegacyFileSystem.copyAsync({ from: BACKUP_URI, to: privateDatabaseUri() });
  }
}

export async function backupDatabase(): Promise<void> {
  if (Platform.OS !== 'android') {
    return;
  }

  await ensureStorageAccess();
  const privateInfo = await LegacyFileSystem.getInfoAsync(privateDatabaseUri());
  if (!privateInfo.exists) {
    return;
  }

  const backupInfo = await LegacyFileSystem.getInfoAsync(BACKUP_URI);
  if (backupInfo.exists) {
    await LegacyFileSystem.deleteAsync(BACKUP_URI, { idempotent: true });
  }
  await LegacyFileSystem.copyAsync({ from: privateDatabaseUri(), to: BACKUP_URI });
}

/** Retourne l'URI publique où enregistrer un fichier exporté (XLSX, etc.). */
export async function publicFilePath(fileName: string): Promise<string> {
  await ensureStorageAccess();
  return `${PUBLIC_FILES_URI}${fileName}`;
}
