import React, { useCallback, useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { api, getErrorMessage, invalidateCache } from '../api/client';
import { FilterCard } from '../components/FilterCard';
import { filterBy } from '../utils/search';
import type { AppNotification } from '../api/types';
import {
  Badge,
  Card,
  colors,
  EmptyState,
  ErrorView,
  formatDate,
  LoadingView,
  Screen,
  SelectField,
} from '../components/ui';

export function NotificationsScreen() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [unread, setUnread] = useState(0);
  const [search, setSearch] = useState('');
  const [readFilter, setReadFilter] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const response = await api<{ data: AppNotification[]; unread_count: number }>('/notifications', {
        cacheKey: 'notifications',
      });
      setNotifications(response.data);
      setUnread(response.unread_count);
    } catch (caught) {
      setError(getErrorMessage(caught, 'Impossible de charger les notifications.'));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  useFocusEffect(useCallback(() => {
    void load();
  }, [load]));

  const visibleNotifications = filterBy(notifications, search, [
    (notification) => notification.title,
    (notification) => notification.message,
    (notification) => notification.created_at,
  ]).filter((notification) => !readFilter || (notification.is_read ? 'read' : 'unread') === readFilter);

  const resetFilters = () => {
    setSearch('');
    setReadFilter('');
  };

  const markRead = async (id: string) => {
    try {
      await api(`/notifications/${id}/read`, { method: 'POST' });
      invalidateCache('notifications');
      await load();
    } catch (caught) {
      setError(getErrorMessage(caught, 'Mise à jour impossible.'));
    }
  };

  return (
    <Screen
      title="Notifications"
      subtitle={`${unread} non lue(s)`}
      actions={unread > 0 ? <Badge text={`${unread}`} color={colors.gold} /> : undefined}
    >
      <FilterCard
        query={search}
        onQueryChange={setSearch}
        placeholder="Titre, message"
        onReset={resetFilters}
        active={Boolean(search || readFilter)}
      >
        <SelectField
          label="Statut"
          value={readFilter}
          options={[
            { label: 'Toutes les notifications', value: '' },
            { label: 'Non lues', value: 'unread' },
            { label: 'Lues', value: 'read' },
          ]}
          onChange={setReadFilter}
        />
      </FilterCard>

      {loading ? <LoadingView /> : null}
      {!loading && error ? <ErrorView message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && visibleNotifications.length === 0 ? <EmptyState message="Aucune notification trouvée." /> : null}

      {!loading && !error ? visibleNotifications.map((notification) => (
        <Card key={notification.id} style={notification.is_read ? undefined : styles.unread}>
          <Text style={styles.title}>{notification.title}</Text>
          <Text style={styles.message}>{notification.message}</Text>
          <Text style={styles.meta}>{formatDate(notification.created_at)}</Text>
          {!notification.is_read ? (
            <Pressable style={styles.read} onPress={() => void markRead(notification.id)}>
              <Text style={styles.readText}>Marquer comme lue</Text>
            </Pressable>
          ) : null}
        </Card>
      )) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  unread: { borderLeftWidth: 4, borderLeftColor: colors.blue },
  title: { fontSize: 15, fontWeight: '700', color: colors.ink },
  message: { fontSize: 14, color: colors.muted, marginTop: 6, lineHeight: 20 },
  meta: { fontSize: 12, color: colors.muted, marginTop: 8 },
  read: { marginTop: 10, alignSelf: 'flex-start' },
  readText: { color: colors.blue, fontWeight: '700', fontSize: 13 },
});
