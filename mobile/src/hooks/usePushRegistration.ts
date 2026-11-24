import { useEffect, useRef } from 'react';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';

// Affiche les notifications même quand l'app est au premier plan.
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
    shouldShowBanner: true,
    shouldShowList: true,
  }),
});

export function usePushRegistration(): void {
  const { user } = useAuth();
  const registeredFor = useRef<string | null>(null);

  useEffect(() => {
    if (!user) {
      return;
    }

    let cancelled = false;

    (async () => {
      try {
        const { status: existing } = await Notifications.getPermissionsAsync();
        let finalStatus = existing;
        if (existing !== 'granted') {
          const { status } = await Notifications.requestPermissionsAsync();
          finalStatus = status;
        }

        if (finalStatus !== 'granted') {
          return;
        }

        if (Platform.OS === 'android') {
          await Notifications.setNotificationChannelAsync('default', {
            name: 'Notifications',
            importance: Notifications.AndroidImportance.HIGH,
            vibrationPattern: [0, 250, 250, 250],
            lightColor: '#0F2B46',
          });
        }

        const tokenResponse = await Notifications.getDevicePushTokenAsync();
        const token = tokenResponse.data;

        if (cancelled || !token || registeredFor.current === token) {
          return;
        }

        registeredFor.current = token;
        await api('/push-tokens', {
          method: 'POST',
          body: { token, platform: Platform.OS },
        });
      } catch (error) {
        // L'enregistrement du push est optionnel : on ne bloque jamais l'app.
        console.warn('Enregistrement push impossible :', error);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [user]);
}
