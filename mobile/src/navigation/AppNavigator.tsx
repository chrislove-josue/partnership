import React, { useEffect } from 'react';
import { ActivityIndicator, AppState, StyleSheet, View } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createBottomTabNavigator, BottomTabBar, type BottomTabBarProps } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import Ionicons from '@expo/vector-icons/Ionicons';
import { useAuth } from '../context/AuthContext';
import { syncNow } from '../db/sync';
import { requestStartupPermissions } from '../native/permissions';
import { colors } from '../components/ui';
import { LoginScreen } from '../screens/LoginScreen';
import { DashboardScreen } from '../screens/DashboardScreen';
import { DonationsScreen } from '../screens/DonationsScreen';
import { PartnersScreen } from '../screens/PartnersScreen';
import { ReportsScreen } from '../screens/ReportsScreen';
import { NotificationsScreen } from '../screens/NotificationsScreen';
import { ProfileScreen } from '../screens/ProfileScreen';
import { AdminScreen } from '../screens/AdminScreen';
import { BirthdaysScreen } from '../screens/BirthdaysScreen';
import { TopPartnersScreen } from '../screens/TopPartnersScreen';
import { LoginHistoryScreen } from '../screens/LoginHistoryScreen';

const Stack = createNativeStackNavigator();
const Tabs = createBottomTabNavigator();

const tabIcons: Record<string, keyof typeof Ionicons.glyphMap> = {
  Accueil: 'home-outline',
  Dons: 'cash-outline',
  Partenaires: 'people-outline',
  'Top partenaires': 'trophy-outline',
  Rapports: 'document-text-outline',
  Notifications: 'notifications-outline',
  Profil: 'person-outline',
  Administration: 'settings-outline',
};

/**
 * Barre d'onglets qui masque les écrans secondaires (historique, anniversaires)
 * sans laisser d'espace vide : on retire ces routes du state avant rendu.
 */
function FilteredTabBar(props: BottomTabBarProps) {
  const hidden = ['HistoriqueConnexion', 'Anniversaires'];
  const routes = props.state.routes.filter((route) => !hidden.includes(route.name));
  const state = {
    ...props.state,
    routes,
    index: Math.min(props.state.index, routes.length - 1),
  };

  return <BottomTabBar {...props} state={state} />;
}

function MainTabs() {
  return (
    <Tabs.Navigator
      tabBar={FilteredTabBar}
      screenOptions={({ route }) => ({
        tabBarIcon: ({ color, size }) => (
          <Ionicons name={tabIcons[route.name] ?? 'ellipse-outline'} size={size} color={color} />
        ),
        tabBarActiveTintColor: colors.blue,
        tabBarInactiveTintColor: colors.muted,
        headerShown: false,
        tabBarLabelStyle: { fontSize: 11 },
      })}
    >
      <Tabs.Screen name="Accueil" component={DashboardScreen} />
      <Tabs.Screen name="Dons" component={DonationsScreen} />
      <Tabs.Screen name="Partenaires" component={PartnersScreen} />
      <Tabs.Screen name="Top partenaires" component={TopPartnersScreen} />
      <Tabs.Screen name="Rapports" component={ReportsScreen} />
      <Tabs.Screen name="Notifications" component={NotificationsScreen} />
      <Tabs.Screen name="Profil" component={ProfileScreen} />
      <Tabs.Screen name="Administration" component={AdminScreen} />
      <Tabs.Screen
        name="HistoriqueConnexion"
        component={LoginHistoryScreen}
        options={{ tabBarButton: () => null }}
      />
      <Tabs.Screen
        name="Anniversaires"
        component={BirthdaysScreen}
        options={{ tabBarButton: () => null }}
      />
    </Tabs.Navigator>
  );
}

export function AppNavigator() {
  const { user, loading, markSynced } = useAuth();

  useEffect(() => {
    void requestStartupPermissions();
  }, []);

  useEffect(() => {
    if (!user) {
      return;
    }

    const run = () => {
      void (async () => {
        const summary = await syncNow();
        if (!summary.skipped) {
          await markSynced();
        }
      })().catch(() => {
        // La synchronisation reprendra automatiquement au prochain cycle.
      });
    };

    run();
    const timer = setInterval(run, 5 * 60 * 1000);
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') {
        run();
      }
    });

    return () => {
      clearInterval(timer);
      subscription.remove();
    };
  }, [user, markSynced]);

  if (loading) {
    return (
      <View style={styles.loading}>
        <ActivityIndicator size="large" color={colors.blue} />
      </View>
    );
  }

  return (
    <NavigationContainer key={user ? 'application' : 'connexion'}>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {user ? (
          <Stack.Screen name="Application" component={MainTabs} />
        ) : (
          <Stack.Screen name="Connexion" component={LoginScreen} />
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}

const styles = StyleSheet.create({
  loading: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.background },
});
