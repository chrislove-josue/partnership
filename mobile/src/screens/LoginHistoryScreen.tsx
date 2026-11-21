import React, { useCallback, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { api, getErrorMessage } from '../api/client';
import { FilterCard } from '../components/FilterCard';
import { SecondaryButton } from '../components/ui';
import { filterBy } from '../utils/search';
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
  SelectField,
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
  const [search, setSearch] = useState('');
  const [eventFilter, setEventFilter] = useState('');
  const [sourceFilter, setSourceFilter] = useState('');

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

  const visibleEntries = filterBy(entries, search, [
    (entry) => entry.event_label,
    (entry) => entry.event,
    (entry) => entry.source_label,
    (entry) => entry.source,
    (entry) => entry.ip_address,
    (entry) => entry.user_agent,
    (entry) => entry.happened_at,
  ])
    .filter((entry) => !eventFilter || entry.event === eventFilter)
    .filter((entry) => !sourceFilter || entry.source === sourceFilter);

  const resetFilters = () => {
    setSearch('');
    setEventFilter('');
    setSourceFilter('');
  };

  return (
    <Screen
      title="Historique de connexion"
      subtitle={`${visibleEntries.length} événement(s)`}
    >
      <FilterCard
        query={search}
        onQueryChange={setSearch}
        placeholder="Événement, source, adresse IP, appareil"
        onReset={resetFilters}
        active={Boolean(search || eventFilter || sourceFilter)}
      >
        <SelectField
          label="Événement"
          value={eventFilter}
          options={[
            { label: 'Tous les événements', value: '' },
            { label: 'Connexions', value: 'login' },
            { label: 'Déconnexions', value: 'logout' },
          ]}
          onChange={setEventFilter}
        />
        <SelectField
          label="Source"
          value={sourceFilter}
          options={[
            { label: 'Toutes les sources', value: '' },
            { label: 'Web', value: 'web' },
            { label: 'Appli mobile', value: 'mobile' },
          ]}
          onChange={setSourceFilter}
        />
      </FilterCard>
      <View style={styles.refresh}>
        <SecondaryButton title="Actualiser" onPress={() => void load()} />
      </View>

      {loading ? <LoadingView /> : null}
      {!loading && error ? <ErrorView message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && visibleEntries.length === 0 ? (
        <EmptyState message="Aucun événement trouvé." />
      ) : null}

      {!loading && !error ? visibleEntries.map((entry) => (
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
  refresh: { marginBottom: 12 },
});
