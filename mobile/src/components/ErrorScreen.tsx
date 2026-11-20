import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Ionicons from '@expo/vector-icons/Ionicons';
import { AppFooter, colors, PrimaryButton } from './ui';

/** Page plein écran affichée lorsqu'une erreur empêche l'application de s'afficher. */
export function ErrorScreen({
  title = 'Une erreur est survenue',
  message,
  onRetry,
}: {
  title?: string;
  message: string;
  onRetry?: () => void;
}) {
  return (
    <SafeAreaView style={styles.safe} edges={['top', 'left', 'right', 'bottom']}>
      <View style={styles.content}>
        <Ionicons name="alert-circle-outline" size={64} color={colors.red} />
        <Text style={styles.title}>{title}</Text>
        <Text style={styles.message}>{message}</Text>
        {onRetry ? <PrimaryButton title="Réessayer" onPress={onRetry} /> : null}
      </View>
      <AppFooter />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background, paddingHorizontal: 24 },
  content: { flex: 1, justifyContent: 'center', alignItems: 'center', gap: 14 },
  title: { fontSize: 22, fontWeight: '700', color: colors.navy, textAlign: 'center' },
  message: { fontSize: 14, color: colors.muted, textAlign: 'center', lineHeight: 21 },
});
