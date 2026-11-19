import React from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import Ionicons from '@expo/vector-icons/Ionicons';
import { useAuth } from '../context/AuthContext';
import { colors } from '../components/ui';
import { LoginScreen } from '../screens/LoginScreen';
import { DashboardScreen } from '../screens/DashboardScreen';
import { DonationsScreen } from '../screens/DonationsScreen';
import { PartnersScreen } from '../screens/PartnersScreen';
import { ReportsScreen } from '../screens/ReportsScreen';
import { NotificationsScreen } from '../screens/NotificationsScreen';
import { ProfileScreen } from '../screens/ProfileScreen';
import { AdminScreen } from '../screens/AdminScreen';

const Stack = createNativeStackNavigator();
const Tabs = createBottomTabNavigator();

const tabIcons: Record<string, keyof typeof Ionicons.glyphMap> = {
  Accueil: 'home-outline',
  Dons: 'cash-outline',
  Partenaires: 'people-outline',
  Rapports: 'document-text-outline',
  Notifications: 'notifications-outline',
  Profil: 'person-outline',
  Administration: 'settings-outline',
};

function MainTabs() {
  return (
    <Tabs.Navigator
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
      <Tabs.Screen name="Rapports" component={ReportsScreen} />
      <Tabs.Screen name="Notifications" component={NotificationsScreen} />
      <Tabs.Screen name="Profil" component={ProfileScreen} />
      <Tabs.Screen name="Administration" component={AdminScreen} />
    </Tabs.Navigator>
  );
}

export function AppNavigator() {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <View style={styles.loading}>
        <ActivityIndicator size="large" color={colors.blue} />
      </View>
    );
  }

  return (
    <NavigationContainer>
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
