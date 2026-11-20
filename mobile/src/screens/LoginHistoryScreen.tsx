import React, { useCallback, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { api, getErrorMessage } from '../api/client';
import type { LoginHistoryEntry } from '../api/types';
import {
  Badge,
  Card,
  colors,
  EmptyState,
  ErrorView,
  LoadingView,
  Screen,
  SecondaryButton,
} from '../components/ui';

function formatDateTime(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'long',
    timeStyle: 'short',
  }).format(date);
}

export function LoginHistoryScreen() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [entries, setEntries] = useState<LoginHistoryEntry[]>([]);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const response = await api<{ data: LoginHistoryEntry[] }>('/auth/history?per_page=100');
      setEntries(response.data);
    } catch (caught) {
      setError(getErrorMessage(caught, 'Historique indisponible.'));
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(useCallback(() => {
    void load();
  }, [load]));

  return (
    <Screen
      title="Historique de connexion"
      subtitle={`${entries.length} événement(s)`}
    >
      <SecondaryButton title="Actualiser" onPress={() => void load()} />

      {loading ? <LoadingView /> : null}
      {!loading && error ? <ErrorView message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && entries.length === 0 ? (
        <EmptyState message="Aucune connexion enregistrée." />
      ) : null}

      {!loading && !error ? entries.map((entry) => (
        <Card key={entry.id}>
          <View style={styles.row}>
            <Badge
              text={entry.event_label}
              color={entry.event === 'login' ? colors.green : colors.red}
            />
            <Text style={styles.source}>{entry.source_label}</Text>
          </View>
          <Text style={styles.date}>{formatDateTime(entry.happened_at)}</Text>
          {entry.ip_address ? <Text style={styles.meta}>Adresse IP : {entry.ip_address}</Text> : null}
          {entry.user_agent ? <Text style={styles.meta}>Appareil : {entry.user_agent}</Text> : null}
        </Card>
      )) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 10 },
  source: { fontSize: 13, color: colors.muted, flexShrink: 1, textAlign: 'right' },
  date: { fontSize: 15, fontWeight: '700', color: colors.ink, marginTop: 10 },
  meta: { fontSize: 12, color: colors.muted, marginTop: 5 },
});
