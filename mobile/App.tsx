import React from 'react';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { ErrorBoundary } from './src/components/ErrorBoundary';
import { AuthProvider } from './src/context/AuthContext';
import { AppNavigator } from './src/navigation/AppNavigator';
import { usePushRegistration } from './src/hooks/usePushRegistration';

function PushRegistrar() {
  usePushRegistration();
  return null;
}

export default function App() {
  return (
    <SafeAreaProvider>
      <AuthProvider>
        <StatusBar style="dark" />
        <ErrorBoundary>
          <PushRegistrar />
          <AppNavigator />
        </ErrorBoundary>
      </AuthProvider>
    </SafeAreaProvider>
  );
}
