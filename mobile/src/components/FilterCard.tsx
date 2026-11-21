import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { colors, SecondaryButton, TextField } from './ui';

/**
 * Carte de recherche commune aux listes. Le bouton de réinitialisation
 * n’apparaît que lorsqu’au moins un critère est actif.
 */
export function FilterCard({
  query,
  onQueryChange,
  placeholder = 'Rechercher',
  children,
  onReset,
  active = true,
}: {
  query: string;
  onQueryChange: (value: string) => void;
  placeholder?: string;
  children?: React.ReactNode;
  onReset: () => void;
  active?: boolean;
}) {
  return (
    <View style={styles.card}>
      <Text style={styles.title}>Recherche et filtres</Text>
      <TextField label="Rechercher" value={query} onChangeText={onQueryChange} placeholder={placeholder} />
      {children}
      {active ? <SecondaryButton title="Réinitialiser les filtres" onPress={onReset} /> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.surface,
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: colors.border,
    marginBottom: 12,
  },
  title: { fontSize: 15, fontWeight: '700', color: colors.navy, marginBottom: 8 },
});
