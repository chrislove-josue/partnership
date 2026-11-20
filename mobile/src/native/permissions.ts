import { PermissionsAndroid, Platform } from 'react-native';

/**
 * Demandes système au premier lancement, sans écran ni explication dans l'app.
 * Android 13+ impose une boîte de dialogue pour les notifications : elle ne
 * peut pas être accordée silencieusement par l'application.
 */
export async function requestStartupPermissions(): Promise<void> {
  if (Platform.OS !== 'android') {
    return;
  }

  const version =
    typeof Platform.Version === 'number'
      ? Platform.Version
      : Number.parseInt(String(Platform.Version), 10);

  try {
    if (version >= 33) {
      await PermissionsAndroid.request(
        PermissionsAndroid.PERMISSIONS.POST_NOTIFICATIONS,
      );
    }
    if (version < 29) {
      await PermissionsAndroid.request(
        PermissionsAndroid.PERMISSIONS.WRITE_EXTERNAL_STORAGE,
      );
    }
  } catch {
    // L'application reste utilisable si l'utilisateur refuse une permission.
  }
}
