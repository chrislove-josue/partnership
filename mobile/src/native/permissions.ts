import { PermissionsAndroid, Platform } from 'react-native';

/**
 * Aucune permission de notification n'est demandée : l'application n'affiche
 * pas de notifications. WRITE_EXTERNAL_STORAGE n'est demandé que sur Android < 10
 * et est plafonné à API 28 dans le manifeste ; au-delà, les fichiers passent par
 * MediaStore sans permission spéciale.
 */
export async function requestStartupPermissions(): Promise<void> {
  if (Platform.OS !== 'android') {
    return;
  }

  const version =
    typeof Platform.Version === 'number'
      ? Platform.Version
      : Number.parseInt(String(Platform.Version), 10);

  if (version >= 29) {
    return;
  }

  try {
    await PermissionsAndroid.request(
      PermissionsAndroid.PERMISSIONS.WRITE_EXTERNAL_STORAGE,
    );
  } catch {
    // L'application reste utilisable si l'utilisateur refuse la permission.
  }
}
